<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once BASE_PATH . '/config/upload.php';

Auth::requireLogin('admin');
$adminUser = Auth::user();

$zones  = all_zones();
$skills = all_skills();

// HANDLE POST ACTIONS
if (is_post()) {
    Csrf::verify();
    $action = (string)input('action');

    // 1. TAMBAH / LANTIK PEGAWAI TEKNIKAL
    if ($action === 'create_pt') {
        $name        = trim((string)input('full_name'));
        $email       = mb_strtolower(trim((string)input('email')));
        $phone       = trim((string)input('phone'));
        $institution = trim((string)input('institution'));
        $zoneId      = (int)input('zone_id');
        $skillId     = (int)input('skill_id') ?: null;
        $shirtSize   = trim((string)input('shirt_size'));
        $pass        = (string)input('password');
        $errors      = [];

        if (mb_strlen($name) < 3) $errors[] = 'Nama penuh Pegawai Teknikal diperlukan.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format e-mel tidak sah.';
        if ($zoneId <= 0) $errors[] = 'Sila pilih zon bagi Pegawai Teknikal.';

        // Check if email already exists
        $dup = db()->prepare('SELECT id, full_name, role FROM users WHERE email=?');
        $dup->execute([$email]);
        if ($dup->fetch()) {
            $errors[] = "E-mel '$email' telah digunakan oleh pengguna lain.";
        }

        if ($pass === '') {
            $pass = bin2hex(random_bytes(12));
        } elseif (strlen($pass) < 8) {
            $errors[] = 'Kata laluan mesti sekurang-kurangnya 8 aksara.';
        }

        $photoPath = null;
        if (!empty($_FILES['photo']['name'])) {
            try {
                $uploaded = store_image_upload($_FILES['photo'], 'users');
                $photoPath = $uploaded['relative_path'];
            } catch (Throwable $e) {
                $errors[] = 'Gambar Profil: ' . $e->getMessage();
            }
        }

        if (!$errors) {
            $stmt = db()->prepare("
                INSERT INTO users(zone_id, skill_id, role, status, full_name, email, phone, institution, image_path, shirt_size, password_hash, approved_at, approved_by)
                VALUES(?, ?, 'technical', 'active', ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $zoneId,
                $skillId,
                $name,
                $email,
                $phone ?: null,
                $institution ?: null,
                $photoPath,
                $shirtSize ?: null,
                password_hash($pass, PASSWORD_DEFAULT),
                Auth::id()
            ]);
            $newId = (int)db()->lastInsertId();
            audit(Auth::id(), 'create_technical_officer', 'user', $newId, ['email' => $email, 'zone_id' => $zoneId, 'skill_id' => $skillId]);

            // Save credentials to show
            $_SESSION['_pt_created_cred'] = [
                'name'     => $name,
                'email'    => $email,
                'password' => $pass,
            ];

            flash('success', "Pegawai Teknikal '$name' berjaya didaftarkan dan diaktifkan.");
            redirect('admin/pegawai_teknikal.php');
        } else {
            flash('error', implode(' ', $errors));
            redirect('admin/pegawai_teknikal.php');
        }
    }

    // 2. KEMAS KINI MAKLUMAT PEGAWAI TEKNIKAL
    if ($action === 'update_pt') {
        $ptId        = (int)input('user_id');
        $name        = trim((string)input('full_name'));
        $phone       = trim((string)input('phone'));
        $institution = trim((string)input('institution'));
        $zoneId      = (int)input('zone_id');
        $skillId     = (int)input('skill_id') ?: null;
        $shirtSize   = trim((string)input('shirt_size'));
        $status      = (string)input('status');

        if (!in_array($status, ['active', 'pending', 'disabled'], true)) $status = 'active';

        if ($ptId > 0 && mb_strlen($name) >= 3) {
            $stmt = db()->prepare("UPDATE users SET full_name=?, phone=?, institution=?, zone_id=?, skill_id=?, shirt_size=?, status=? WHERE id=? AND role='technical'");
            $stmt->execute([$name, $phone ?: null, $institution ?: null, $zoneId > 0 ? $zoneId : null, $skillId, $shirtSize ?: null, $status, $ptId]);
            audit(Auth::id(), 'update_technical_officer', 'user', $ptId, ['status' => $status, 'zone_id' => $zoneId, 'skill_id' => $skillId]);
            flash('success', 'Maklumat Pegawai Teknikal berjaya dikemas kini.');
        } else {
            flash('error', 'Sila pastikan nama penuh dimasukkan dengan betul.');
        }
        redirect('admin/pegawai_teknikal.php');
    }

    // 3. RESET KATA LALUAN PT
    if ($action === 'reset_password') {
        $ptId    = (int)input('user_id');
        $newPass = (string)input('new_password');

        if ($newPass === '') {
            $newPass = bin2hex(random_bytes(12));
        }

        if ($ptId > 0 && strlen($newPass) >= 8) {
            $stmt = db()->prepare("UPDATE users SET password_hash=? WHERE id=? AND role='technical'");
            $stmt->execute([password_hash($newPass, PASSWORD_DEFAULT), $ptId]);
            audit(Auth::id(), 'reset_password_pt', 'user', $ptId);

            flash('success', "Kata laluan Pegawai Teknikal berjaya dikemas kini kepada: <strong>$newPass</strong>");
        } else {
            flash('error', 'Kata laluan mesti sekurang-kurangnya 8 aksara.');
        }
        redirect('admin/pegawai_teknikal.php');
    }

    // 4. TOGGLE STATUS (AKTIF / GANTUNG)
    if ($action === 'toggle_status') {
        $ptId      = (int)input('user_id');
        $newStatus = input('new_status') === 'active' ? 'active' : 'disabled';
        if ($ptId > 0) {
            $stmt = db()->prepare("UPDATE users SET status=? WHERE id=? AND role='technical'");
            $stmt->execute([$newStatus, $ptId]);
            audit(Auth::id(), 'toggle_status_pt', 'user', $ptId, ['status' => $newStatus]);
            flash('success', 'Status akaun Pegawai Teknikal dikemas kini.');
        }
        redirect('admin/pegawai_teknikal.php');
    }

    // 5. PADAM AKAUN PT
    if ($action === 'delete_pt') {
        $ptId = (int)input('user_id');
        if ($ptId > 0) {
            try {
                $stmt = db()->prepare("DELETE FROM users WHERE id=? AND role='technical'");
                $stmt->execute([$ptId]);
                audit(Auth::id(), 'delete_technical_officer', 'user', $ptId);
                flash('success', 'Akaun Pegawai Teknikal telah dipadam.');
            } catch (Throwable) {
                // If foreign key constraint blocks deletion, disable instead
                db()->prepare("UPDATE users SET status='disabled' WHERE id=? AND role='technical'")->execute([$ptId]);
                flash('warning', 'Akaun ini mempunyai rekod aktiviti pendaftaran dan telah dinyahaktifkan bagi mengekalkan integriti sistem.');
            }
        }
        redirect('admin/pegawai_teknikal.php');
    }
}

// QUERY ALL TECHNICAL OFFICERS WITH ZONE, SKILL & ACTIVITY METRICS
$zoneFilter = (int)($_GET['zone_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));

$sql = "
    SELECT u.*, z.name AS zone_name, z.code AS zone_code, s.name AS skill_name,
           (SELECT COUNT(*) FROM registrations r WHERE r.zone_id = u.zone_id AND r.status = 'approved') AS total_approved,
           (SELECT COUNT(*) FROM registrations r WHERE r.zone_id = u.zone_id AND r.status = 'submitted') AS total_pending,
           (SELECT COUNT(*) FROM results res JOIN registrations r ON r.id = res.registration_id WHERE r.zone_id = u.zone_id) AS total_scored
    FROM users u
    LEFT JOIN zones z ON z.id = u.zone_id
    LEFT JOIN skills s ON s.id = u.skill_id
    WHERE u.role = 'technical'
";
$params = [];
if ($zoneFilter > 0) {
    $sql .= " AND u.zone_id = ?";
    $params[] = $zoneFilter;
}
if ($q !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.institution LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$sql .= " ORDER BY (u.status='active') DESC, z.sort_order, u.full_name";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$officers = $stmt->fetchAll();

// STATS
$totalPT    = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='technical'")->fetchColumn();
$activePT   = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='technical' AND status='active'")->fetchColumn();
$disabledPT = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='technical' AND status='disabled'")->fetchColumn();
$zonesCovered = (int)db()->query("SELECT COUNT(DISTINCT zone_id) FROM users WHERE role='technical' AND status='active' AND zone_id IS NOT NULL")->fetchColumn();

$isScoringEnabled = is_pt_score_entry_enabled();

$pageTitle  = 'Pengurusan Pegawai Teknikal';
$activePage = 'admin_technical';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>
<section class="dashboard-header py-4">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-2 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-user-gear text-warning"></i>
            <span>Pusat Kawalan Pentadbir &middot; Pengurusan Pegawai</span>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-1">Pengurusan Pegawai Teknikal</h1>
        <p class="text-muted mb-0">Lantik, selia zon penugasan, dan pantau status serta aktiviti dashboard Pegawai Teknikal KVSkills.</p>
    </div>
</section>

<section class="container py-4">
    <!-- STATS ROW -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <i class="fa-solid fa-user-gear text-primary"></i>
                <strong><?= $totalPT ?></strong>
                <span>Jumlah Pegawai Teknikal</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <i class="fa-solid fa-circle-check text-success"></i>
                <strong><?= $activePT ?></strong>
                <span>Pegawai Aktif</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <i class="fa-solid fa-map-location-dot text-info"></i>
                <strong><?= $zonesCovered ?> / 10</strong>
                <span>Zon Berpenugasan</span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <i class="fa-solid fa-pen-ruler <?= $isScoringEnabled ? 'text-success' : 'text-danger' ?>"></i>
                <strong class="fs-5"><?= $isScoringEnabled ? 'DIBUKA' : 'DIKUNCI' ?></strong>
                <span>Kemasukan Markah PT</span>
            </div>
        </div>
    </div>

    <!-- ACTION BUTTON & SEARCH BAR -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <form method="get" class="d-flex flex-wrap gap-2 flex-grow-1">
                    <input type="text" name="q" value="<?= e($q) ?>" class="form-control" style="max-width: 320px;" placeholder="Cari nama, emel atau kolej...">
                    <select name="zone_id" class="form-select" style="max-width: 220px;">
                        <option value="0">Semua Zon Kontinjen</option>
                        <?php foreach ($zones as $z): ?>
                            <option value="<?= (int)$z['id'] ?>" <?= selected($zoneFilter, $z['id']) ?>><?= e($z['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-outline-primary"><i class="fa-solid fa-magnifying-glass me-1"></i> Tapis</button>
                    <?php if ($q !== '' || $zoneFilter > 0): ?>
                        <a href="<?= e(url('admin/pegawai_teknikal.php')) ?>" class="btn btn-outline-secondary">Set Semula</a>
                    <?php endif; ?>
                </form>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#createPtModal">
                        <i class="fa-solid fa-user-plus me-1"></i> Lantik Pegawai Teknikal
                    </button>
                    <a href="<?= e(url('pegawai_teknikal/dashboard.php')) ?>" class="btn btn-outline-dark text-nowrap" target="_blank" title="Lihat paparan sebenar dashboard Pegawai Teknikal">
                        <i class="fa-solid fa-gauge me-1"></i> Pantau Dashboard PT <i class="fa-solid fa-arrow-up-right-from-square ms-1 small"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- OFFICERS LIST TABLE -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Pegawai Teknikal</th>
                            <th>Zon &amp; Bidang</th>
                            <th>Kolej / Institusi</th>
                            <th>Status Aktiviti Dashboard PT</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($officers as $pt): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= e(user_avatar_url($pt['image_path'])) ?>" class="rounded-circle" style="width: 44px; height: 44px; object-fit: cover;" alt="Avatar">
                                        <div>
                                            <strong class="d-block text-dark"><?= e($pt['full_name']) ?></strong>
                                            <small class="text-muted"><i class="fa-regular fa-envelope me-1"></i><?= e($pt['email']) ?></small>
                                            <?php if ($pt['phone']): ?>
                                                <small class="text-muted ms-2"><i class="fa-solid fa-phone me-1"></i><?= e($pt['phone']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($pt['zone_name']): ?>
                                        <span class="badge text-bg-primary px-2 py-1"><?= e($pt['zone_name']) ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning text-dark px-2 py-1">Belum Ditetapkan</span>
                                    <?php endif; ?>
                                    <?php if ($pt['skill_name']): ?>
                                        <small class="d-block text-muted mt-1"><i class="fa-solid fa-wrench me-1"></i><?= e($pt['skill_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span><?= e($pt['institution'] ?: '-') ?></span>
                                    <?php if ($pt['shirt_size']): ?>
                                        <span class="badge text-bg-light border text-muted ms-1"><?= e($pt['shirt_size']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge text-bg-light border" title="Pendaftaran peserta yang telah diluluskan dalam zon ini">
                                            <i class="fa-solid fa-user-check text-success me-1"></i> <?= (int)$pt['total_approved'] ?> Diluluskan
                                        </span>
                                        <span class="badge text-bg-light border" title="Pendaftaran yang menunggu semakan">
                                            <i class="fa-solid fa-clock text-warning me-1"></i> <?= (int)$pt['total_pending'] ?> Menunggu
                                        </span>
                                        <span class="badge text-bg-light border" title="Markah pertandingan yang telah dimasukkan">
                                            <i class="fa-solid fa-trophy text-primary me-1"></i> <?= (int)$pt['total_scored'] ?> Markah Direkod
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($pt['status'] === 'active'): ?>
                                        <span class="badge bg-success px-2 py-1">Aktif</span>
                                    <?php elseif ($pt['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark px-2 py-1">Menunggu</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger px-2 py-1">Digantung</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <!-- Edit Modal Button -->
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPtModal-<?= (int)$pt['id'] ?>" title="Kemaskini Maklumat">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <!-- Reset Password Modal Button -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetPassModal-<?= (int)$pt['id'] ?>" title="Reset Kata Laluan">
                                            <i class="fa-solid fa-key"></i>
                                        </button>
                                        <!-- Toggle Status Form -->
                                        <form method="post" class="d-inline">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= (int)$pt['id'] ?>">
                                            <input type="hidden" name="new_status" value="<?= $pt['status'] === 'active' ? 'disabled' : 'active' ?>">
                                            <button type="submit" class="btn btn-sm <?= $pt['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="<?= $pt['status'] === 'active' ? 'Gantung Akaun' : 'Aktifkan Akaun' ?>">
                                                <i class="fa-solid <?= $pt['status'] === 'active' ? 'fa-ban' : 'fa-check' ?>"></i>
                                            </button>
                                        </form>
                                        <!-- Delete Button -->
                                        <form method="post" class="d-inline" onsubmit="return confirm('Adakah anda pasti ingin memadam akaun Pegawai Teknikal ini?')">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="action" value="delete_pt">
                                            <input type="hidden" name="user_id" value="<?= (int)$pt['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Padam Akaun">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- EDIT MODAL -->
                                    <div class="modal fade text-start" id="editPtModal-<?= (int)$pt['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <form method="post" class="modal-content">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="action" value="update_pt">
                                                <input type="hidden" name="user_id" value="<?= (int)$pt['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fs-6 fw-bold"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Kemaskini Pegawai Teknikal</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Penuh <span class="text-danger">*</span></label>
                                                        <input type="text" name="full_name" class="form-control" value="<?= e($pt['full_name']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">E-mel (Hanya Paparan)</label>
                                                        <input type="email" class="form-control" value="<?= e($pt['email']) ?>" readonly disabled>
                                                    </div>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">No. Telefon</label>
                                                            <input type="text" name="phone" class="form-control" value="<?= e($pt['phone'] ?? '') ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Saiz Baju</label>
                                                            <select name="shirt_size" class="form-select">
                                                                <option value="">Pilih Saiz</option>
                                                                <?php foreach (['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'] as $sz): ?>
                                                                    <option value="<?= $sz ?>" <?= selected($pt['shirt_size'] ?? '', $sz) ?>><?= $sz ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Zon Kontinjen <span class="text-danger">*</span></label>
                                                            <select name="zone_id" class="form-select" required>
                                                                <option value="">Pilih Zon</option>
                                                                <?php foreach ($zones as $z): ?>
                                                                    <option value="<?= (int)$z['id'] ?>" <?= selected((int)$pt['zone_id'], $z['id']) ?>><?= e($z['name']) ?> (<?= e($z['code']) ?>)</option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Status Akaun</label>
                                                            <select name="status" class="form-select">
                                                                <option value="active" <?= selected($pt['status'], 'active') ?>>Aktif</option>
                                                                <option value="pending" <?= selected($pt['status'], 'pending') ?>>Menunggu</option>
                                                                <option value="disabled" <?= selected($pt['status'], 'disabled') ?>>Digantung</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Bidang Kemahiran Diselia</label>
                                                        <select name="skill_id" class="form-select">
                                                            <option value="">Semua Bidang / Tiada Khusus</option>
                                                            <?php foreach ($skills as $s): ?>
                                                                <option value="<?= (int)$s['id'] ?>" <?= selected((int)($pt['skill_id'] ?? 0), $s['id']) ?>><?= e($s['name']) ?> (<?= e($s['code']) ?>)</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Kolej Vokasional / Institusi</label>
                                                        <input type="text" name="institution" class="form-control" value="<?= e($pt['institution'] ?? '') ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- RESET PASSWORD MODAL -->
                                    <div class="modal fade text-start" id="resetPassModal-<?= (int)$pt['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-sm">
                                            <form method="post" class="modal-content">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="user_id" value="<?= (int)$pt['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fs-6 fw-bold"><i class="fa-solid fa-key me-2 text-warning"></i>Reset Kata Laluan</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="small text-muted mb-2">Pegawai: <strong><?= e($pt['full_name']) ?></strong></p>
                                                    <label class="form-label">Kata Laluan Baharu</label>
                                                    <input type="text" name="new_password" class="form-control" placeholder="Biarkan kosong untuk auto-jana">
                                                    <div class="form-text">Jika dikosongkan, sistem akan menjana kata laluan rawak selamat secara automatik.</div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-warning">Reset Sekarang</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$officers): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fa-solid fa-user-slash fs-1 d-block mb-3 opacity-25"></i>
                                    Tiada rekod Pegawai Teknikal ditemui mengikut tapisan semasa.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- MODAL LANTIK PEGAWAI TEKNIKAL BAHARU -->
<div class="modal fade" id="createPtModal" tabindex="-1" aria-labelledby="createPtModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="create_pt">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="createPtModalLabel">
                    <i class="fa-solid fa-user-plus me-2 text-primary"></i>Lantik Pegawai Teknikal Baharu
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Nama Penuh Pegawai Teknikal <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" placeholder="Cth: Ts. Mohd Nor bin Zulkifli" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">E-mel Rasmi <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="pt.selangor@kvskills.my" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">No. Telefon (Opsyenal)</label>
                        <input type="text" name="phone" class="form-control" placeholder="012-3456789">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Zon Kontinjen Ditugaskan <span class="text-danger">*</span></label>
                        <select name="zone_id" class="form-select" required>
                            <option value="">Pilih Zon</option>
                            <?php foreach ($zones as $z): ?>
                                <option value="<?= (int)$z['id'] ?>"><?= e($z['name']) ?> (<?= e($z['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Bidang Kemahiran Diselia</label>
                        <select name="skill_id" class="form-select">
                            <option value="">Semua Bidang / Tiada Khusus</option>
                            <?php foreach ($skills as $s): ?>
                                <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kolej Vokasional / Institusi (Opsyenal)</label>
                        <input type="text" name="institution" class="form-control" placeholder="Cth: KV Shah Alam">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Saiz Baju (Opsyenal)</label>
                        <select name="shirt_size" class="form-select">
                            <option value="">Pilih Saiz</option>
                            <?php foreach (['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'] as $sz): ?>
                                <option value="<?= $sz ?>"><?= $sz ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Kata Laluan Sementara (Opsyenal)</label>
                        <input type="text" name="password" class="form-control" placeholder="Biarkan kosong untuk auto-jana">
                        <div class="form-text">Kata laluan selamat akan dijana secara automatik jika dikosongkan.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Gambar Profil (Opsyenal)</label>
                        <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">Format: JPG, PNG, WEBP (Maksimum 5MB).</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fa-solid fa-check me-1"></i> Sahkan &amp; Lantik Pegawai
                </button>
            </div>
        </form>
    </div>
</div>

<?php require BASE_PATH . '/partials/footer.php'; ?>
