<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once BASE_PATH . '/config/upload.php';
Auth::requireLogin(['technical', 'admin']);
$user = Auth::user();
$comp = registration_competition();
$zones = all_zones();
$skills = all_skills();

$createdCredentials = $_SESSION['_new_coach_credentials'] ?? null;
unset($_SESSION['_new_coach_credentials']);

if (is_post()) {
    Csrf::verify();
    $errors = [];

    if (!$comp) {
        flash('error', 'Tiada edisi pertandingan yang aktif untuk menerima pendaftaran.');
        redirect('pegawai_teknikal/daftar_jurulatih.php');
    }

    // 1. Coach Info
    $coachName   = trim((string)input('coach_name'));
    $coachEmail  = mb_strtolower(trim((string)input('coach_email')));
    $coachPhone  = trim((string)input('coach_phone'));
    $institution = trim((string)input('institution'));
    $zoneId      = (int)input('zone_id');
    $coachPass   = (string)input('coach_password');
    $coachShirt  = trim((string)input('coach_shirt_size'));

    // 2. Participant Info
    $partName    = trim((string)input('participant_name'));
    $partEmail   = mb_strtolower(trim((string)input('participant_email')));
    $partPhone   = trim((string)input('participant_phone'));
    $partShirt   = trim((string)input('participant_shirt_size'));
    $skillId     = (int)input('skill_id');
    $assistant   = trim((string)input('assistant_name'));
    $model       = trim((string)input('model_name'));

    // Validation
    if (mb_strlen($coachName) < 3) $errors[] = 'Nama penuh jurulatih diperlukan.';
    if (!filter_var($coachEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mel jurulatih tidak sah.';
    if ($institution === '') $errors[] = 'Nama Kolej Vokasional (Institusi) diperlukan.';
    if ($zoneId <= 0) $errors[] = 'Sila pilih zon bagi jurulatih dan kontinjen.';

    // Check if coach email already exists
    $existingUser = db()->prepare('SELECT id, full_name, role FROM users WHERE email=?');
    $existingUser->execute([$coachEmail]);
    $existingCoach = $existingUser->fetch();
    if ($existingCoach) {
        $errors[] = "E-mel '$coachEmail' telah digunakan oleh pengguna {$existingCoach['full_name']} ({$existingCoach['role']}).";
    }

    // Generate temp password if not provided
    if ($coachPass === '') {
        $coachPass = bin2hex(random_bytes(12));
    } elseif (strlen($coachPass) < 8) {
        $errors[] = 'Kata laluan jurulatih mesti sekurang-kurangnya 8 aksara.';
    }

    if (mb_strlen($partName) < 3) $errors[] = 'Nama penuh peserta diperlukan.';
    if ($partShirt === '') $errors[] = 'Sila pilih saiz baju peserta.';
    if ($skillId <= 0) $errors[] = 'Sila pilih bidang kemahiran peserta.';

    $skill = skill_by_id($skillId);
    if (!$skill) {
        $errors[] = 'Bidang kemahiran tidak sah.';
    } else {
        $helperErrors = CompetitionService::validateHelpers($skill, [
            'assistant' => $assistant !== '' ? [$assistant] : [],
            'model'     => $model !== '' ? [$model] : []
        ]);
        if ($helperErrors) $errors = array_merge($errors, $helperErrors);
    }

    // Check if zone + skill already registered in this competition
    $checkDup = db()->prepare("SELECT id FROM registrations WHERE competition_id=? AND zone_id=? AND skill_id=?");
    $checkDup->execute([(int)$comp['id'], $zoneId, $skillId]);
    if ($checkDup->fetch()) {
        $errors[] = 'Zon ini telah mempunyai pendaftaran peserta bagi bidang kemahiran tersebut.';
    }

    // Handle Coach Photo Upload
    $coachPhotoPath = null;
    if (!$errors && !empty($_FILES['coach_photo']['name'])) {
        try {
            $uploaded = store_image_upload($_FILES['coach_photo'], 'users');
            $coachPhotoPath = $uploaded['relative_path'];
        } catch (Throwable $e) {
            $errors[] = 'Gambar Jurulatih: ' . $e->getMessage();
        }
    }

    // Handle Participant Photo Upload
    $partPhotoPath = null;
    if (!$errors && !empty($_FILES['participant_photo']['name'])) {
        try {
            $uploaded = store_image_upload($_FILES['participant_photo'], 'participants');
            $partPhotoPath = $uploaded['relative_path'];
        } catch (Throwable $e) {
            $errors[] = 'Gambar Peserta: ' . $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            db()->beginTransaction();
            lock_registration_slot((int)$comp['id'], $skillId);

            // 1. Insert Coach
            $stmt = db()->prepare("
                INSERT INTO users(zone_id, role, status, full_name, email, phone, institution, image_path, shirt_size, password_hash, approved_at, approved_by)
                VALUES(?, 'coach', 'active', ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $zoneId,
                $coachName,
                $coachEmail,
                $coachPhone ?: null,
                $institution,
                $coachPhotoPath,
                $coachShirt ?: null,
                password_hash($coachPass, PASSWORD_DEFAULT),
                Auth::id()
            ]);
            $coachId = (int)db()->lastInsertId();

            // 2. Insert Registration
            $stmt = db()->prepare("
                INSERT INTO registrations(competition_id, zone_id, skill_id, coach_user_id, participant_name, shirt_size, participant_email, participant_phone, institution, image_path, status, submitted_at, approved_at, approved_by)
                VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW(), NOW(), ?)
            ");
            $stmt->execute([
                (int)$comp['id'],
                $zoneId,
                $skillId,
                $coachId,
                $partName,
                $partShirt,
                $partEmail ?: null,
                $partPhone ?: null,
                $institution,
                $partPhotoPath,
                Auth::id()
            ]);
            $regId = (int)db()->lastInsertId();

            // 3. Insert Helpers if applicable
            $stmtHelper = db()->prepare("INSERT INTO registration_helpers(registration_id, helper_type, full_name) VALUES(?, ?, ?)");
            if ($assistant !== '') $stmtHelper->execute([$regId, 'assistant', $assistant]);
            if ($model !== '') $stmtHelper->execute([$regId, 'model', $model]);

            db()->commit();
            $coachPhotoPath = $partPhotoPath = null;

            audit(Auth::id(), 'register_coach_and_participant', 'user', $coachId, [
                'coach_email' => $coachEmail,
                'participant' => $partName,
                'skill_id'    => $skillId
            ]);

            // Save credentials to display to technical officer
            $zoneObj = db()->prepare("SELECT name FROM zones WHERE id=?");
            $zoneObj->execute([$zoneId]);
            $zoneName = $zoneObj->fetchColumn() ?: 'Zon';

            $_SESSION['_new_coach_credentials'] = [
                'coach_name'   => $coachName,
                'coach_email'  => $coachEmail,
                'password'     => $coachPass,
                'institution'  => $institution,
                'zone_name'    => $zoneName,
                'participant'  => $partName,
                'skill_name'   => $skill['name'] ?? ''
            ];

            clear_old();
            flash('success', 'Akaun Jurulatih dan Pendaftaran Peserta berjaya didaftarkan serentak.');
            redirect('pegawai_teknikal/daftar_jurulatih.php');

        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $errors[] = 'Pendaftaran gagal disimpan. Semak maklumat dan slot bidang.';
        }
    }

    if ($errors) {
        discard_new_photo($coachPhotoPath);
        discard_new_photo($partPhotoPath);
        fail_validation($errors, 'pegawai_teknikal/daftar_jurulatih.php');
    }
}

$pageTitle = 'Pendaftaran Jurulatih & Peserta';
$activePage = 'technical_daftar_jurulatih';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
$errors = validation_errors();
?>
<section class="dashboard-header py-4">
    <div class="container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <span class="badge text-bg-primary-subtle text-primary border border-primary-subtle px-3 py-1.5 rounded-pill mb-2 fw-semibold">
                    <i class="fa-solid fa-user-plus me-1.5"></i> ENROLMEN KONTINJEN PUSAT
                </span>
                <h1 class="h2 text-dark fw-bold mb-1">Pendaftaran Berpusat Jurulatih & Peserta</h1>
                <p class="text-muted mb-0">Daftarkan akaun rasmi Jurulatih (jana kata laluan sementara) dan peserta di bawah bimbingannya secara serentak.</p>
            </div>
            <div>
                <a href="pendaftaran.php" class="btn btn-outline-secondary btn-sm px-3 bg-white shadow-xs">
                    <i class="fa-solid fa-list-check me-1.5"></i> Semak Senarai Pendaftaran
                </a>
            </div>
        </div>
    </div>
</section>

<section class="container py-4">
    <!-- NEWLY CREATED CREDENTIALS ALERT CARD -->
    <?php if ($createdCredentials): ?>
        <div class="credential-display-box mb-4">
            <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom border-secondary-subtle">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success p-2 rounded-circle">
                        <i class="fa-solid fa-check text-white"></i>
                    </span>
                    <div>
                        <h4 class="h6 mb-0 text-dark fw-bold">Pendaftaran Berjaya: Kredensial Akaun Jurulatih Telah Dijana</h4>
                        <small class="text-secondary">Sila salin dan serahkan maklumat log masuk berikut kepada Jurulatih untuk capaian portal rasmi.</small>
                    </div>
                </div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1.5 rounded-pill fw-semibold">
                    <i class="fa-solid fa-shield-halved me-1"></i> Akaun Aktif
                </span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="text-secondary small fw-medium">Nama Jurulatih:</div>
                        <div class="fw-bold text-dark fs-6"><?= e($createdCredentials['coach_name']) ?></div>
                        <div class="text-secondary small fw-medium mt-2">Kolej Vokasional / Zon:</div>
                        <div class="text-dark"><?= e($createdCredentials['institution']) ?> <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1"><?= e($createdCredentials['zone_name']) ?></span></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-3 border border-warning-subtle">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-secondary small fw-medium">E-mel Log Masuk:</span>
                            <span class="badge text-bg-warning">Kredensial Rasmi</span>
                        </div>
                        <div class="text-primary fw-bold fs-6 mb-2"><?= e($createdCredentials['coach_email']) ?></div>
                        <div class="text-secondary small fw-medium">Kata Laluan Sementara:</div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <code class="credential-code fs-6 px-2.5 py-1 rounded" id="tempPassCode"><?= e($createdCredentials['password']) ?></code>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="navigator.clipboard.writeText('<?= e($createdCredentials['coach_email']) ?> / <?= e($createdCredentials['password']) ?>'); alert('Kredensial log masuk telah disalin ke papan keratan.');">
                                <i class="fa-solid fa-copy me-1"></i>Salin
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="p-2.5 bg-success-subtle border border-success-subtle rounded-3 text-success-emphasis small d-flex align-items-center gap-2">
                        <i class="fa-solid fa-user-check text-success fs-5"></i>
                        <div>
                            Peserta <strong><?= e($createdCredentials['participant']) ?></strong> telah didaftarkan dan diluluskan secara automatik dalam bidang <strong><?= e($createdCredentials['skill_name']) ?></strong>.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-start gap-2 mb-4">
            <i class="fa-solid fa-circle-exclamation text-danger mt-1"></i>
            <div>
                <strong class="d-block mb-1">Sila perbetulkan ralat pendaftaran berikut:</strong>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$comp): ?><p role="status">Tiada pertandingan yang sedang membuka pendaftaran.</p><?php endif; ?>
                <form method="post" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <div class="row g-4">
            <!-- LEFT COLUMN: JURULATIH -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="fa-solid fa-user-tie"></i>
                        </div>
                        <div>
                            <h2 class="h6 mb-0 text-dark fw-bold">1. Maklumat Akaun Jurulatih</h2>
                            <small class="text-secondary">Pendaftaran akaun portal dan data pegawai pengiring</small>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-medium">Nama Penuh Jurulatih <span class="text-danger">*</span></label>
                            <input type="text" name="coach_name" class="form-control" value="<?= e(old('coach_name')) ?>" placeholder="Cth: Cikgu Ahmad Faiz bin Ismail" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-7">
                                <label class="form-label fw-medium">E-mel Jurulatih <span class="text-danger">*</span></label>
                                <input type="email" name="coach_email" class="form-control" value="<?= e(old('coach_email')) ?>" placeholder="ahmad@kvmelaka.edu.my" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-medium">No. Telefon</label>
                                <input type="text" name="coach_phone" class="form-control" value="<?= e(old('coach_phone')) ?>" placeholder="012-3456789">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Kolej Vokasional (Institusi) <span class="text-danger">*</span></label>
                            <input type="text" name="institution" class="form-control" value="<?= e(old('institution')) ?>" placeholder="Kolej Vokasional Melaka Tengah" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Zon Kontinjen <span class="text-danger">*</span></label>
                            <select name="zone_id" class="form-select" required>
                                <option value="">Pilih Zon</option>
                                <?php foreach ($zones as $z): ?>
                                    <option value="<?= (int)$z['id'] ?>" <?= selected(old('zone_id'), $z['id']) ?>><?= e($z['name']) ?> (<?= e($z['code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Kata Laluan Sementara (Opsyenal)</label>
                            <input type="text" name="coach_password" class="form-control" placeholder="Biarkan kosong untuk penjanaan automatik">
                            <div class="form-text">Jika dikosongkan, sistem akan menjana kata laluan rawak selamat secara automatik.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Saiz Baju Jurulatih (Opsyenal)</label>
                            <select name="coach_shirt_size" class="form-select">
                                <option value="">Pilih Saiz Baju</option>
                                <?php foreach (['XS','S','M','L','XL','2XL','3XL','4XL','5XL'] as $sz): ?>
                                    <option value="<?= $sz ?>" <?= selected(old('coach_shirt_size'), $sz) ?>><?= $sz ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Gambar Profil Jurulatih (Opsyenal)</label>
                            <input type="file" name="coach_photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">Format yang disokong: JPG, PNG, WEBP (Maksimum 5MB).</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: PESERTA -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100 border-0">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="fa-solid fa-user-graduate"></i>
                        </div>
                        <div>
                            <h2 class="h6 mb-0 text-dark fw-bold">2. Maklumat Peserta / Pelajar</h2>
                            <small class="text-secondary">Maklumat calon pertandingan dan bidang kemahiran</small>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-medium">Nama Penuh Peserta <span class="text-danger">*</span></label>
                            <input type="text" name="participant_name" class="form-control" value="<?= e(old('participant_name')) ?>" placeholder="Cth: Muhammad Amirul bin Rosli" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-medium">E-mel Peserta (Opsyenal)</label>
                                <input type="email" name="participant_email" class="form-control" value="<?= e(old('participant_email')) ?>" placeholder="pelajar@gmail.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">No. Telefon Peserta (Opsyenal)</label>
                                <input type="text" name="participant_phone" class="form-control" value="<?= e(old('participant_phone')) ?>" placeholder="011-1234567">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Bidang Kemahiran <span class="text-danger">*</span></label>
                            <select name="skill_id" id="skillSelect" class="form-select" required>
                                <option value="">Pilih Bidang Kemahiran</option>
                                <?php foreach ($skills as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>"
                                            data-assistant="<?= (int)$s['assistant_count'] ?>"
                                            data-model="<?= (int)$s['model_count'] ?>"
                                            data-note="<?= e($s['assistant_note']) ?>"
                                            <?= selected(old('skill_id'), $s['id']) ?>>
                                        <?= e($s['code']) ?> - <?= e($s['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="helperNote" class="form-text text-primary mt-1 fw-medium"></div>
                        </div>

                        <!-- DYNAMIC HELPER & MODEL FIELDS -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-6 helper-field" id="assistantField">
                                <label class="form-label fw-medium">Nama Pembantu <span class="text-danger">*</span></label>
                                <input type="text" name="assistant_name" class="form-control" value="<?= e(old('assistant_name')) ?>" placeholder="Nama Penuh Pembantu">
                            </div>
                            <div class="col-md-6 helper-field" id="modelField">
                                <label class="form-label fw-medium">Nama Model <span class="text-danger">*</span></label>
                                <input type="text" name="model_name" class="form-control" value="<?= e(old('model_name')) ?>" placeholder="Nama Penuh Model">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-medium">Saiz Baju Peserta <span class="text-danger">*</span></label>
                            <select name="participant_shirt_size" class="form-select" required>
                                <option value="">Pilih Saiz Baju</option>
                                <?php foreach (['XS','S','M','L','XL','2XL','3XL','4XL','5XL'] as $sz): ?>
                                    <option value="<?= $sz ?>" <?= selected(old('participant_shirt_size'), $sz) ?>><?= $sz ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-medium">Gambar Peserta (Opsyenal)</label>
                            <input type="file" name="participant_photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">Format yang disokong: JPG, PNG, WEBP (Maksimum 5MB).</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SUBMIT BUTTON & INFORMATION NOTICE -->
            <div class="col-12 mt-2">
                <div class="card border-0 bg-primary-subtle p-3 rounded-3 mb-3">
                    <div class="d-flex align-items-center gap-2 text-primary-emphasis small">
                        <i class="fa-solid fa-circle-info fs-5"></i>
                        <div>
                            <strong>Pengesahan Enrolmen Rasmi:</strong> Tindakan ini akan mendaftarkan akaun Jurulatih dan meluluskan pendaftaran calon bagi zon terpilih secara serentak. Sila pastikan maklumat kontinjen disemak sebelum menekan butang pendaftaran.
                        </div>
                    </div>
                </div>
                <div class="text-center">
                    <button type="submit" <?= !$comp ? 'disabled' : '' ?>  class="btn btn-primary btn-lg px-5 shadow-sm fw-semibold">
                        <i class="fa-solid fa-paper-plane me-2"></i>Daftarkan Jurulatih & Peserta Sekarang
                    </button>
                </div>
            </div>
        </div>
    </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const s = document.getElementById('skillSelect');
    const a = document.getElementById('assistantField');
    const m = document.getElementById('modelField');
    const n = document.getElementById('helperNote');

    function update() {
        const o = s.options[s.selectedIndex];
        const ac = Number(o?.dataset.assistant || 0);
        const mc = Number(o?.dataset.model || 0);
        a.style.display = ac ? 'block' : 'none';
        m.style.display = mc ? 'block' : 'none';
        if (a.querySelector('input')) a.querySelector('input').required = ac > 0;
        if (m.querySelector('input')) m.querySelector('input').required = mc > 0;
        n.textContent = o?.dataset.note ? ('Perhatian: ' + o.dataset.note) : '';
    }
    s.addEventListener('change', update);
    update();
});
</script>
<?php require BASE_PATH . '/partials/footer.php'; ?>