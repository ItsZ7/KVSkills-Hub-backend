<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
Auth::requireLogin('admin');
$me = Auth::user();

if (is_post()) {
    Csrf::verify();
    $action = (string)input('action', 'status');
    $id = (int)input('id');

    if ($id <= 0) {
        flash('error', 'Pengguna tidak sah.');
        redirect('admin/pengguna.php');
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        flash('error', 'Pengguna tidak ditemui.');
        redirect('admin/pengguna.php');
    }

    if ($action === 'status') {
        $status = (string)input('status');
        if (!in_array($status, ['active', 'disabled', 'pending'], true)) {
            flash('error', 'Status tidak sah.');
            redirect('admin/pengguna.php');
        }
        if ($id === Auth::id() && $status !== 'active') {
            flash('error', 'Anda tidak boleh menyahaktifkan akaun anda sendiri.');
            redirect('admin/pengguna.php');
        }

        $stmt = db()->prepare("UPDATE users SET status=?, approved_at=IF(?='active', COALESCE(approved_at, NOW()), approved_at), approved_by=? WHERE id=?");
        $stmt->execute([$status, $status, Auth::id(), $id]);
        audit(Auth::id(), 'change_status', 'user', $id, ['status' => $status]);
        flash('success', 'Status pengguna "' . $target['full_name'] . '" telah dikemas kini kepada ' . ucfirst($status) . '.');
        redirect('admin/pengguna.php');
    }

    if ($action === 'role') {
        $role = (string)input('role');
        if (!in_array($role, ['admin', 'technical', 'coach'], true)) {
            flash('error', 'Peranan tidak sah.');
            redirect('admin/pengguna.php');
        }
        if ($id === Auth::id() && $role !== 'admin') {
            flash('error', 'Anda tidak boleh menukar peranan akaun anda sendiri.');
            redirect('admin/pengguna.php');
        }

        $stmt = db()->prepare("UPDATE users SET role=? WHERE id=?");
        $stmt->execute([$role, $id]);
        audit(Auth::id(), 'change_role', 'user', $id, ['role' => $role]);
        flash('success', 'Peranan pengguna "' . $target['full_name'] . '" telah ditukar kepada ' . ucfirst($role) . '.');
        redirect('admin/pengguna.php');
    }

    if ($action === 'delete') {
        if ($id === Auth::id()) {
            flash('error', 'Anda tidak boleh memadam akaun anda sendiri.');
            redirect('admin/pengguna.php');
        }
        try {
            // Check if user has registrations
            $hasRegs = (int)db()->prepare("SELECT COUNT(*) FROM registrations WHERE coach_user_id=?")->execute([$id]) ? (int)db()->query("SELECT COUNT(*) FROM registrations WHERE coach_user_id=$id")->fetchColumn() : 0;
            if ($hasRegs > 0) {
                // If user has registrations, disable instead of deleting
                db()->prepare("UPDATE users SET status='disabled' WHERE id=?")->execute([$id]);
                audit(Auth::id(), 'disable_instead_delete', 'user', $id);
                flash('warning', 'Pengguna mempunyai data pendaftaran berkaitan, status akaun ditukar kepada dinyahaktif.');
            } else {
                db()->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
                audit(Auth::id(), 'delete', 'user', $id, ['name' => $target['full_name'], 'email' => $target['email']]);
                flash('success', 'Akaun pengguna telah dipadam.');
            }
        } catch (Throwable $e) {
            flash('error', 'Pengguna tidak dapat dipadam. Semak rekod yang masih berkaitan.');
        }
        redirect('admin/pengguna.php');
    }
}

$role = (string)($_GET['role'] ?? '');
$status = (string)($_GET['status'] ?? '');
$search = trim((string)($_GET['q'] ?? ''));

$sql = 'SELECT u.*, z.name AS zone_name FROM users u LEFT JOIN zones z ON z.id=u.zone_id WHERE 1=1';
$params = [];

if (in_array($role, ['coach', 'technical', 'admin'], true)) {
    $sql .= ' AND u.role=?';
    $params[] = $role;
}
if (in_array($status, ['pending', 'active', 'disabled'], true)) {
    $sql .= ' AND u.status=?';
    $params[] = $status;
}
if ($search !== '') {
    $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.institution LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY FIELD(u.status, 'pending', 'active', 'disabled'), u.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Pengurusan Pengguna';
$activePage = 'admin_users';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>
<section class="dashboard-header py-4">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-2 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-users-gear text-primary"></i>
            <span>Pusat Kawalan Pentadbir &middot; Pengurusan Pengguna</span>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-1">Pengurusan Pengguna &amp; Akses</h1>
        <p class="text-muted mb-0">Kawalan penuh akaun Pentadbir, Pegawai Teknikal, dan Jurulatih.</p>
    </div>
</section>

<section class="container py-4">
    <!-- FILTER BAR -->
    <form class="card card-body shadow-sm mb-4" method="get">
        <div class="row g-3">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control" placeholder="Cari nama, emel, kolej..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="">Semua Peranan</option>
                    <option value="admin" <?= selected($role, 'admin') ?>>Pentadbir (Admin)</option>
                    <option value="technical" <?= selected($role, 'technical') ?>>Pegawai Teknikal</option>
                    <option value="coach" <?= selected($role, 'coach') ?>>Jurulatih</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="pending" <?= selected($status, 'pending') ?>>Menunggu</option>
                    <option value="active" <?= selected($status, 'active') ?>>Aktif</option>
                    <option value="disabled" <?= selected($status, 'disabled') ?>>Dinyahaktif</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Tapis</button>
            </div>
        </div>
    </form>

    <!-- USERS TABLE -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Pengguna</th>
                            <th>Peranan</th>
                            <th>Kolej / Zon</th>
                            <th>Status</th>
                            <th>Tarikh Daftar</th>
                            <th class="text-end pe-4">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= e(user_avatar_url($u['image_path'] ?? null)) ?>" class="rounded-circle" style="width: 44px; height: 44px; object-fit: cover;" alt="Avatar">
                                        <div>
                                            <strong class="d-block"><?= e($u['full_name']) ?></strong>
                                            <small class="text-muted"><?= e($u['email']) ?></small>
                                            <?php if (!empty($u['phone'])): ?>
                                                <small class="text-muted d-block"><i class="fa-solid fa-phone me-1"></i><?= e($u['phone']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $roleBadge = match($u['role']) {
                                        'admin' => 'bg-danger text-white',
                                        'technical' => 'bg-primary text-white',
                                        'coach' => 'bg-success text-white',
                                        default => 'bg-secondary text-white'
                                    };
                                    ?>
                                    <span class="badge <?= $roleBadge ?>"><?= e(ucfirst($u['role'])) ?></span>
                                </td>
                                <td>
                                    <?= e($u['institution'] ?? '-') ?><br>
                                    <small class="text-muted"><?= e($u['zone_name'] ?? 'Tiada zon') ?></small>
                                </td>
                                <td>
                                    <span class="status status-<?= e($u['status']) ?>"><?= e(ucfirst($u['status'])) ?></span>
                                </td>
                                <td>
                                    <small><?= e(format_date($u['created_at'], 'd/m/Y H:i')) ?></small>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <?php if ($u['status'] !== 'active'): ?>
                                            <form method="post" class="d-inline">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                <input type="hidden" name="action" value="status">
                                                <input type="hidden" name="status" value="active">
                                                <button class="btn btn-sm btn-success" type="submit" title="Aktifkan Akaun">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($u['status'] === 'active' && $u['id'] !== Auth::id()): ?>
                                            <form method="post" class="d-inline">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                <input type="hidden" name="action" value="status">
                                                <input type="hidden" name="status" value="disabled">
                                                <button class="btn btn-sm btn-outline-warning" type="submit" title="Nyahaktifkan Akaun">
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($u['id'] !== Auth::id()): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Adakah anda pasti ingin memadam pengguna ini?');">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Padam Akaun">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$users): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Tiada pengguna dijumpai.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php require BASE_PATH . '/partials/footer.php'; ?>