<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once BASE_PATH . '/config/upload.php';

Auth::requireLogin(['technical', 'admin']);
$currentUser = Auth::user();
$zones  = all_zones();
$skills = all_skills();

$createdCredentials = $_SESSION['_new_pt_credentials'] ?? null;
unset($_SESSION['_new_pt_credentials']);

if (is_post()) {
    Csrf::verify();
    $action = (string)input('action');

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

        if (mb_strlen($name) < 3) {
            $errors[] = 'Nama penuh Pegawai Teknikal diperlukan.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format e-mel tidak sah.';
        }
        if ($zoneId <= 0) {
            $errors[] = 'Sila pilih zon penugasan Pegawai Teknikal.';
        }

        // Check duplicate email
        $dup = db()->prepare('SELECT id FROM users WHERE email=?');
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

            // Save credentials to session
            $_SESSION['_new_pt_credentials'] = [
                'name'     => $name,
                'email'    => $email,
                'password' => $pass,
            ];

            flash('success', "Pegawai Teknikal '$name' berjaya didaftarkan.");
            redirect('pegawai_teknikal/daftar_pegawai_teknikal.php');
        } else {
            flash('error', implode(' ', $errors));
            redirect('pegawai_teknikal/daftar_pegawai_teknikal.php');
        }
    }
}

// Query Technical Officers
$userZoneId = (int)($currentUser['zone_id'] ?? 0);
$sql = "
    SELECT u.*, z.name AS zone_name, z.code AS zone_code, s.name AS skill_name
    FROM users u
    LEFT JOIN zones z ON z.id = u.zone_id
    LEFT JOIN skills s ON s.id = u.skill_id
    WHERE u.role = 'technical'
";
$params = [];
if ($currentUser['role'] === 'technical' && $userZoneId > 0) {
    // Show PT in the same zone or all if admin
    $sql .= " AND (u.zone_id = ? OR u.zone_id IS NULL)";
    $params[] = $userZoneId;
}
$sql .= " ORDER BY (u.status='active') DESC, z.sort_order, u.full_name";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$officers = $stmt->fetchAll();

$pageTitle  = 'Daftar Pegawai Teknikal';
$activePage = 'technical_daftar_pt';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>
<section class="dashboard-header py-4">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-3 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-user-shield text-warning"></i>
            <span>Pengurusan Lantikan &middot; Pegawai Berautoriti</span>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-2">Pendaftaran Pegawai Teknikal</h1>
        <p class="lead mx-auto mb-0 text-muted" style="max-width: 720px; font-size: 1.05rem;">
            Lantik dan daftarkan akaun Pegawai Teknikal kontinjen mengikut zon dan bidang kemahiran yang diselia.
        </p>
    </div>
</section>

<section class="container py-4">
    <!-- CREDENTIALS BANNER AFTER REGISTRATION -->
    <?php if ($createdCredentials): ?>
        <div class="card border-success shadow-sm mb-4">
            <div class="card-header bg-success text-white py-3 px-4 d-flex align-items-center gap-2">
                <i class="fa-solid fa-circle-check fs-5"></i>
                <h4 class="h6 fw-bold mb-0">Akaun Pegawai Teknikal Berjaya Dicipta!</h4>
            </div>
            <div class="card-body p-4 bg-light">
                <p class="mb-3 small text-secondary">Sila salin dan serahkan maklumat log masuk sementara berikut kepada Pegawai Teknikal yang dilantik:</p>
                <div class="bg-white p-3 rounded-3 border">
                    <div class="row g-2 align-items-center">
                        <div class="col-sm-6">
                            <span class="small text-muted d-block">Nama Penuh:</span>
                            <strong class="text-dark"><?= e($createdCredentials['name']) ?></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="small text-muted d-block">E-mel Rasmi:</span>
                            <strong class="text-primary font-monospace"><?= e($createdCredentials['email']) ?></strong>
                        </div>
                        <div class="col-sm-6 mt-2">
                            <span class="small text-muted d-block">Kata Laluan Sementara:</span>
                            <code class="fs-6 fw-bold bg-light px-2 py-1 border rounded text-danger"><?= e($createdCredentials['password']) ?></code>
                        </div>
                        <div class="col-sm-6 mt-2">
                            <span class="small text-muted d-block">Pautan Log Masuk:</span>
                            <a href="<?= e(url('pegawai_teknikal/login.php')) ?>" class="small font-monospace text-secondary" target="_blank">
                                <?= e(url('pegawai_teknikal/login.php')) ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- REGISTRATION FORM -->
        <div class="col-lg-5">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-user-plus text-primary fs-5"></i>
                        <div>
                            <h2 class="h5 mb-0 fw-bold text-dark">Daftar Pegawai Teknikal</h2>
                            <small class="text-muted">Lantikan baharu jawatankuasa teknikal pertandingan</small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form method="post" enctype="multipart/form-data">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="create_pt">

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">Nama Penuh Pegawai <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" placeholder="Cth: Ts. Ahmad Zulkifli bin Razak" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">E-mel Rasmi <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="pt.bidang@kvskills.my" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">No. Telefon</label>
                                <input type="text" name="phone" class="form-control" placeholder="012-3456789">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Saiz Baju</label>
                                <select name="shirt_size" class="form-select">
                                    <option value="">Pilih Saiz</option>
                                    <?php foreach (['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'] as $sz): ?>
                                        <option value="<?= $sz ?>"><?= $sz ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">Zon Kontinjen <span class="text-danger">*</span></label>
                            <select name="zone_id" class="form-select" required>
                                <option value="">Pilih Zon</option>
                                <?php foreach ($zones as $z): ?>
                                    <option value="<?= (int)$z['id'] ?>" <?= selected((int)($currentUser['zone_id'] ?? 0), $z['id']) ?>>
                                        <?= e($z['name']) ?> (<?= e($z['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">Bidang Kemahiran Diselia <span class="text-danger">*</span></label>
                            <select name="skill_id" class="form-select" required>
                                <option value="">Pilih Bidang Kemahiran (22)</option>
                                <?php foreach ($skills as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text"><i class="fa-solid fa-circle-info me-1"></i>Pegawai bertanggungjawab menyelia penilaian dan modul dalam bidang ini.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">Kolej Vokasional / Institusi</label>
                            <input type="text" name="institution" class="form-control" placeholder="Cth: KV Shah Alam">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">Kata Laluan Sementara</label>
                            <input type="text" name="password" class="form-control font-monospace" placeholder="Biarkan kosong untuk auto-jana">
                            <div class="form-text">Kata laluan selamat akan dijana secara automatik sekiranya dikosongkan.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark mb-1">Gambar Profil (Opsyenal)</label>
                            <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-xs d-inline-flex align-items-center justify-content-center gap-2">
                            <i class="fa-solid fa-user-plus"></i>
                            <span>Daftar Pegawai Teknikal</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- TECHNICAL OFFICERS LIST TABLE -->
        <div class="col-lg-7">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-users-gear text-primary fs-5"></i>
                        <div>
                            <h2 class="h5 mb-0 fw-bold text-dark">Senarai Pegawai Teknikal</h2>
                            <small class="text-muted">Pegawai yang telah dilantik dan berdaftar</small>
                        </div>
                    </div>
                    <span class="badge text-bg-light border text-secondary px-3 py-2 font-monospace">
                        <?= count($officers) ?> pegawai
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Pegawai Teknikal</th>
                                    <th>Bidang &amp; Zon</th>
                                    <th class="text-center">Saiz</th>
                                    <th class="text-center pe-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($officers as $pt): ?>
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="<?= e(user_avatar_url($pt['image_path'])) ?>" class="rounded-circle border shadow-xs" style="width: 38px; height: 38px; object-fit: cover;" alt="Avatar">
                                                <div>
                                                    <strong class="d-block text-dark small fw-bold"><?= e($pt['full_name']) ?></strong>
                                                    <small class="text-muted font-monospace d-block"><?= e($pt['email']) ?></small>
                                                    <?php if ($pt['institution']): ?>
                                                        <small class="text-secondary d-block"><?= e($pt['institution']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($pt['skill_name']): ?>
                                                <span class="badge text-bg-light border text-primary d-block mb-1 text-truncate" style="max-width: 180px;" title="<?= e($pt['skill_name']) ?>">
                                                    <i class="fa-solid fa-wrench me-1"></i><?= e($pt['skill_name']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge text-bg-secondary d-block mb-1">Semua Bidang</span>
                                            <?php endif; ?>
                                            <small class="text-secondary fw-semibold font-monospace"><?= e($pt['zone_name'] ?? 'Tiada Zon') ?></small>
                                        </td>
                                        <td class="text-center">
                                            <?= $pt['shirt_size'] ? '<span class="badge text-bg-light border font-monospace">' . e($pt['shirt_size']) . '</span>' : '<span class="text-muted small font-monospace">-</span>' ?>
                                        </td>
                                        <td class="text-center pe-4">
                                            <?php if ($pt['status'] === 'active'): ?>
                                                <span class="badge bg-success px-2 py-1">Aktif</span>
                                            <?php elseif ($pt['status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark px-2 py-1">Menunggu</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger px-2 py-1">Digantung</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$officers): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-5">
                                            <div class="py-4">
                                                <i class="fa-solid fa-user-shield fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                                <h3 class="h6 fw-bold text-dark mb-1">Tiada Pegawai Teknikal</h3>
                                                <p class="small text-muted mb-0">Tiada rekod Pegawai Teknikal berdaftar ditemui.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require BASE_PATH . '/partials/footer.php'; ?>
