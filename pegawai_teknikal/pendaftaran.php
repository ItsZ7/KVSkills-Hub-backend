<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
Auth::requireLogin(['technical', 'admin']);

if (is_post()) {
    Csrf::verify();
    $id     = (int) input('id');
    $action = (string) input('action');
    $reason = trim((string) input('reason'));

    $stmt = db()->prepare('SELECT id, status FROM registrations WHERE id = ?');
    $stmt->execute([$id]);
    $reg = $stmt->fetch();

    if (!$reg) {
        flash('error', 'Pendaftaran tidak ditemui.');
        redirect('pegawai_teknikal/pendaftaran.php');
    }

    if ($action === 'approve') {
        $check = db()->prepare("
            SELECT s.name, s.assistant_count, s.model_count,
                   SUM(h.helper_type = 'assistant') AS actual_assistants,
                   SUM(h.helper_type = 'model') AS actual_models
            FROM registrations r
            JOIN skills s ON s.id = r.skill_id
            LEFT JOIN registration_helpers h ON h.registration_id = r.id
            WHERE r.id = ?
            GROUP BY r.id, s.id
        ");
        $check->execute([$id]);
        $rules = $check->fetch();

        $invalidAssistant = !$rules || (int)$rules['assistant_count'] !== (int)$rules['actual_assistants'];
        $invalidModel     = !$rules || (int)$rules['model_count'] !== (int)$rules['actual_models'];

        if ($invalidAssistant || $invalidModel) {
            flash('error', 'Pendaftaran tidak mematuhi bilangan pembantu/model bagi bidang tersebut.');
            redirect('pegawai_teknikal/pendaftaran.php');
        }

        $stmt = db()->prepare("UPDATE registrations SET status = 'approved', approved_at = NOW(), approved_by = ?, rejection_reason = NULL WHERE id = ? AND status = 'submitted'");
        $stmt->execute([Auth::id(), $id]);
        if (!$stmt->rowCount()) {
            flash('error', 'Rekod telah disemak. Muat semula senarai.');
            redirect('pegawai_teknikal/pendaftaran.php');
        }
        audit(Auth::id(), 'approve', 'registration', $id);
        flash('success', 'Pendaftaran peserta telah diluluskan.');
    } elseif ($action === 'reject') {
        if ($reason === '') {
            flash('error', 'Sebab penolakan diperlukan.');
            redirect('pegawai_teknikal/pendaftaran.php');
        }
        $stmt = db()->prepare("UPDATE registrations SET status = 'rejected', rejection_reason = ?, approved_at = NULL, approved_by = NULL WHERE id = ? AND status = 'submitted'");
        $stmt->execute([$reason, $id]);
        if (!$stmt->rowCount()) {
            flash('error', 'Rekod telah disemak. Muat semula senarai.');
            redirect('pegawai_teknikal/pendaftaran.php');
        }
        audit(Auth::id(), 'reject', 'registration', $id, ['reason' => $reason]);
        flash('success', 'Pendaftaran peserta telah ditolak.');
    }
    redirect('pegawai_teknikal/pendaftaran.php');
}

$allowedStatuses = ['submitted', 'approved', 'rejected'];
$status = $_GET['status'] ?? 'submitted';
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'submitted';
}

$skillId = (int)($_GET['skill_id'] ?? 0);
$skills = all_skills();

$sql = "
    SELECT r.*, s.name AS skill_name, s.code AS skill_code, z.name AS zone_name, u.full_name AS coach_name, u.image_path AS coach_image,
           GROUP_CONCAT(CONCAT(h.helper_type, ': ', h.full_name) SEPARATOR '||') AS helpers
    FROM registrations r
    JOIN skills s ON s.id = r.skill_id
    JOIN zones z ON z.id = r.zone_id
    JOIN users u ON u.id = r.coach_user_id
    LEFT JOIN registration_helpers h ON h.registration_id = r.id
    WHERE r.status = ?
";
$params = [$status];

if ($skillId > 0) {
    $sql .= " AND r.skill_id = ?";
    $params[] = $skillId;
}

$sql .= " GROUP BY r.id ORDER BY s.sort_order, z.sort_order";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle  = 'Semakan Pendaftaran';
$activePage = 'technical_pendaftaran';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>
<section class="dashboard-header py-4">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-3 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-clipboard-check text-warning"></i>
            <span>Modul Verifikasi Teknikal &middot; Pegawai Berautoriti</span>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-2">Semakan Pendaftaran Peserta</h1>
        <p class="lead mx-auto mb-0 text-muted" style="max-width: 720px; font-size: 1.05rem;">
            Sahkan kelayakan peserta, pembantu dan model mengikut peraturan teknikal pekeliling bagi setiap bidang kemahiran.
        </p>
    </div>
</section>

<section class="container py-4">
    <!-- FILTER -->
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-dark mb-1">Status Pendaftaran</label>
                    <select name="status" class="form-select">
                        <option value="submitted" <?= selected($status, 'submitted') ?>>Menunggu Semakan</option>
                        <option value="approved" <?= selected($status, 'approved') ?>>Diluluskan</option>
                        <option value="rejected" <?= selected($status, 'rejected') ?>>Ditolak</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-dark mb-1">Bidang Kemahiran</label>
                    <select name="skill_id" class="form-select">
                        <option value="0">Semua Bidang Kemahiran (22)</option>
                        <?php foreach ($skills as $skill): ?>
                            <option value="<?= (int)$skill['id'] ?>" <?= selected($skillId, $skill['id']) ?>>
                                <?= e($skill['code']) ?>: <?= e($skill['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="fa-solid fa-filter me-1"></i> Tapis
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TABLE -->
    <div class="card border shadow-sm">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-user-check text-primary fs-5"></i>
                <div>
                    <h2 class="h5 mb-0 fw-bold text-dark">Senarai Pendaftaran Kontinjen</h2>
                    <small class="text-muted">Semak butiran peserta, kelayakan pembantu, dan model sebelum meluluskan</small>
                </div>
            </div>
            <span class="badge text-bg-light border text-secondary px-3 py-2 font-monospace">
                <?= count($rows) ?> calon tersenarai
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 24%;">Peserta</th>
                            <th style="width: 18%;">Bidang Kemahiran</th>
                            <th class="text-center" style="width: 8%;">Saiz</th>
                            <th style="width: 16%;">Zon / Institusi</th>
                            <th style="width: 14%;">Jurulatih</th>
                            <th style="width: 10%;">Pembantu / Model</th>
                            <th class="text-end pe-4" style="width: 10%;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= e(participant_photo_url($row['image_path'])) ?>" class="rounded-circle border shadow-xs" style="width: 44px; height: 44px; object-fit: cover;" alt="Peserta">
                                        <div>
                                            <strong class="d-block text-dark fw-bold"><?= e($row['participant_name']) ?></strong>
                                            <small class="text-muted font-monospace"><?= e($row['participant_email'] ?: $row['participant_phone'] ?: 'Peserta') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong class="d-block text-dark small fw-bold"><?= e($row['skill_name']) ?></strong>
                                    <span class="badge text-bg-light border text-secondary font-monospace mt-1"><?= e($row['skill_code']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-light border text-dark font-monospace px-2 py-1"><?= e($row['shirt_size'] ?: '-') ?></span>
                                </td>
                                <td>
                                    <span class="badge text-bg-light border text-secondary font-monospace mb-1"><?= e($row['zone_name']) ?></span>
                                    <small class="d-block text-muted text-wrap"><?= e($row['institution']) ?></small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?= e(user_avatar_url($row['coach_image'] ?? null)) ?>" class="rounded-circle border" style="width: 30px; height: 30px; object-fit: cover;" alt="Jurulatih">
                                        <span class="small fw-semibold text-dark"><?= e($row['coach_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php $helpers = array_filter(explode('||', (string)$row['helpers'])); ?>
                                    <?php if ($helpers): ?>
                                        <?php foreach ($helpers as $helper): ?>
                                            <div class="small text-secondary mb-1">
                                                <i class="fa-solid fa-tag me-1 text-muted"></i><?= e($helper) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted small font-monospace">Tiada</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($row['status'] === 'submitted'): ?>
                                        <div class="d-flex flex-column align-items-end gap-2">
                                            <form method="post" class="d-inline">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-sm btn-success px-3 shadow-xs" title="Luluskan Calon">
                                                    <i class="fa-solid fa-check me-1"></i> Lulus
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline d-flex gap-1">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Sebab penolakan" required style="width: 140px;">
                                                <button type="submit" class="btn btn-sm btn-outline-danger shadow-xs" title="Tolak">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-end">
                                            <?php if ($row['status'] === 'approved'): ?>
                                                <span class="badge bg-success px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i>Diluluskan</span>
                                            <?php elseif ($row['status'] === 'rejected'): ?>
                                                <span class="badge bg-danger px-2 py-1"><i class="fa-solid fa-ban me-1"></i>Ditolak</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary px-2 py-1"><?= e(ucfirst($row['status'])) ?></span>
                                            <?php endif; ?>
                                            <?php if ($row['rejection_reason']): ?>
                                                <div class="small text-danger mt-1 text-wrap text-end" style="max-width: 180px;">
                                                    <i class="fa-solid fa-triangle-exclamation me-1"></i><?= e($row['rejection_reason']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <div class="py-4">
                                        <i class="fa-solid fa-user-check fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                        <h3 class="h6 fw-bold text-dark mb-1">Tiada Rekod Pendaftaran</h3>
                                        <p class="small text-muted mb-0">Tiada rekod pendaftaran mengikut tapisan semasa.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php require BASE_PATH . '/partials/footer.php'; ?>