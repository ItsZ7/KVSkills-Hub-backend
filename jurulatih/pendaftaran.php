<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once BASE_PATH . '/config/upload.php';

Auth::requireLogin('coach');
$user        = Auth::user();
$competition = registration_competition();
$skills      = all_skills();

if (is_post()) {
    Csrf::verify();
    $action = (string)input('action', 'create');

    // 1. PADAM PENDAFTARAN
    if ($action === 'delete') {
        $id = (int)input('id');
        $stmt = db()->prepare("DELETE FROM registrations WHERE id=? AND coach_user_id=? AND status IN ('submitted','rejected','draft')");
        $stmt->execute([$id, Auth::id()]);
        flash('success', $stmt->rowCount() ? 'Pendaftaran peserta dipadam.' : 'Pendaftaran tidak boleh dipadam.');
        redirect('jurulatih/pendaftaran.php');
    }

    // 2. KEMASKINI MAKLUMAT PESERTA (EDIT)
    if ($action === 'update_participant') {
        $id          = (int)input('id');
        $participant = trim((string)input('participant_name'));
        $icNumber    = trim((string)input('ic_number'));
        $shirtSize   = trim((string)input('shirt_size'));
        $email       = trim((string)input('participant_email'));
        $phone       = trim((string)input('participant_phone'));
        $assistant   = trim((string)input('assistant_name'));
        $model       = trim((string)input('model_name'));
        $errors      = [];

        // Verify registration belongs to coach
        $stmtCheck = db()->prepare("SELECT r.*, s.name, s.assistant_count, s.model_count FROM registrations r JOIN skills s ON s.id=r.skill_id WHERE r.id=? AND r.coach_user_id=?");
        $stmtCheck->execute([$id, Auth::id()]);
        $existingReg = $stmtCheck->fetch();

        if (!$existingReg) {
            flash('error', 'Rekod pendaftaran tidak ditemui.');
            redirect('jurulatih/pendaftaran.php');
        }

        if (mb_strlen($participant) < 3) $errors[] = 'Nama peserta diperlukan.';
        if ($shirtSize === '') $errors[] = 'Sila pilih saiz baju peserta.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mel peserta tidak sah.';

        $errors = array_merge($errors, CompetitionService::validateHelpers($existingReg, ['assistant' => [$assistant], 'model' => [$model]]));
        $newPhotoPath = null;
        if (!$errors && !empty($_FILES['participant_photo']['name'])) {
            try {
                $uploaded = store_image_upload($_FILES['participant_photo'], 'participants');
                $newPhotoPath = $uploaded['relative_path'];
            } catch (Throwable $e) {
                $errors[] = 'Gambar Peserta: ' . $e->getMessage();
            }
        }

        if (!$errors) {
            try {
                db()->beginTransaction();
                $updateSql = "
                    UPDATE registrations
                    SET participant_name = ?,
                        ic_number = ?,
                        shirt_size = ?,
                        participant_email = ?,
                        participant_phone = ?,
                        image_path = COALESCE(?, image_path),

                        rejection_reason = IF(status = 'rejected', NULL, rejection_reason),
                        submitted_at = IF(status = 'rejected', NOW(), submitted_at),
                        status = IF(status = 'rejected', 'submitted', status)
                    WHERE id = ? AND coach_user_id = ?
                ";
                $stmtUpdate = db()->prepare($updateSql);
                $stmtUpdate->execute([
                    $participant,
                    $icNumber ?: null,
                    $shirtSize,
                    $email ?: null,
                    $phone ?: null,
                    $newPhotoPath,
                    $id,
                    Auth::id()
                ]);

                // Update helpers
                db()->prepare("DELETE FROM registration_helpers WHERE registration_id = ?")->execute([$id]);
                $hs = db()->prepare('INSERT INTO registration_helpers(registration_id, helper_type, full_name) VALUES(?, ?, ?)');
                if ($assistant !== '') $hs->execute([$id, 'assistant', $assistant]);
                if ($model !== '')     $hs->execute([$id, 'model', $model]);

                db()->commit();
                audit(Auth::id(), 'update_participant', 'registration', $id);
                flash('success', "Maklumat peserta '<strong>" . e($participant) . "</strong>' berjaya dikemas kini.");
            } catch (Throwable $e) {
                if (db()->inTransaction()) db()->rollBack();
                discard_new_photo($newPhotoPath);
                flash('error', 'Gagal mengemas kini maklumat peserta. Semak medan dan cuba lagi.');
            }
            redirect('jurulatih/pendaftaran.php');
        } else {
            flash('error', implode(' ', $errors));
            redirect('jurulatih/pendaftaran.php');
        }
    }

    // 3. DAFTAR PESERTA BAHARU
    if (!$competition) {
        flash('error', 'Tiada pertandingan yang sedang membuka pendaftaran.');
        redirect('jurulatih/pendaftaran.php');
    }
    $skillId     = (int)input('skill_id');
    $skill       = skill_by_id($skillId);
    $participant = trim((string)input('participant_name'));
    $icNumber    = trim((string)input('ic_number'));
    $shirtSize   = trim((string)input('shirt_size'));
    $email       = trim((string)input('participant_email'));
    $phone       = trim((string)input('participant_phone'));
    $institution = trim((string)input('institution', $user['institution'] ?? ''));
    $assistant   = trim((string)input('assistant_name'));
    $model       = trim((string)input('model_name'));
    $errors      = [];

    if (!$skill) $errors[] = 'Bidang tidak sah.';
    if (mb_strlen($participant) < 3) $errors[] = 'Nama peserta diperlukan.';
    if ($shirtSize === '') $errors[] = 'Sila pilih saiz baju peserta.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mel peserta tidak sah.';
    if ($institution === '') $errors[] = 'Nama kolej diperlukan.';
    if (empty($user['zone_id'])) $errors[] = 'Akaun jurulatih belum mempunyai zon.';
    if ($skill) $errors = array_merge($errors, CompetitionService::validateHelpers($skill, ['assistant' => $assistant !== '' ? [$assistant] : [], 'model' => $model !== '' ? [$model] : []]));

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
            lock_registration_slot((int)$competition['id'], $skillId);
            $stmt = db()->prepare("INSERT INTO registrations(competition_id,zone_id,skill_id,coach_user_id,participant_name,ic_number,shirt_size,participant_email,participant_phone,institution,image_path,status,submitted_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,'submitted',NOW())");
            $stmt->execute([(int)$competition['id'], (int)$user['zone_id'], $skillId, Auth::id(), $participant, $icNumber ?: null, $shirtSize, $email ?: null, $phone ?: null, $institution, $partPhotoPath]);
            $regId = (int)db()->lastInsertId();
            $hs = db()->prepare('INSERT INTO registration_helpers(registration_id,helper_type,full_name) VALUES(?,?,?)');
            if ($assistant !== '') $hs->execute([$regId, 'assistant', $assistant]);
            if ($model !== '')     $hs->execute([$regId, 'model', $model]);
            db()->commit();
            audit(Auth::id(), 'create', 'registration', $regId, ['skill_id' => $skillId]);
            clear_old();
            flash('success', 'Pendaftaran peserta dihantar untuk pengesahan pegawai teknikal.');
            redirect('jurulatih/pendaftaran.php');
        } catch (PDOException $e) {
            if (db()->inTransaction()) db()->rollBack();
            $errors[] = $e->getCode() === '23000' ? 'Zon anda telah mempunyai pendaftaran untuk bidang ini.' : 'Pendaftaran gagal disimpan.';
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $errors[] = 'Pendaftaran gagal disimpan.';
        }
    }
    if ($errors) {
        discard_new_photo($partPhotoPath);
        fail_validation($errors, 'jurulatih/pendaftaran.php');
    }
}

$stmt = db()->prepare("
    SELECT r.*, s.name AS skill_name, s.code AS skill_code, s.assistant_count, s.model_count, s.assistant_note, z.name AS zone_name,
           GROUP_CONCAT(CONCAT(h.helper_type, ': ', h.full_name) SEPARATOR '||') AS helpers,
           MAX(CASE WHEN h.helper_type='assistant' THEN h.full_name ELSE NULL END) AS assistant_name,
           MAX(CASE WHEN h.helper_type='model' THEN h.full_name ELSE NULL END) AS model_name
    FROM registrations r
    JOIN skills s ON s.id=r.skill_id
    JOIN zones z ON z.id=r.zone_id
    LEFT JOIN registration_helpers h ON h.registration_id=r.id
    WHERE r.coach_user_id=?
    GROUP BY r.id
    ORDER BY s.sort_order
");
$stmt->execute([Auth::id()]);
$rows = $stmt->fetchAll();

$pageTitle  = 'Pendaftaran Peserta';
$activePage = 'coach_pendaftaran';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>
<!-- Header Section -->
<section class="dashboard-header py-4">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-3 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-clipboard-user text-warning"></i>
            <span>Modul Pengurusan Kontinjen &middot; Jurulatih</span>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-2">Pendaftaran &amp; Pengurusan Peserta</h1>
        <p class="lead mx-auto mb-0 text-muted" style="max-width: 720px; font-size: 1.05rem;">
            Daftarkan calon peserta kontinjen bagi 22 bidang kemahiran dan pantau status kelulusan Pegawai Teknikal.
        </p>
    </div>
</section>

<section class="container py-4">
    <?php if (!$competition): ?>
        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center gap-2 mb-4">
            <i class="fa-solid fa-triangle-exclamation flex-shrink-0"></i>
            <div>Tiada edisi pertandingan yang sedang membuka pendaftaran pada masa ini.</div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- FORM PENDAFTARAN PESERTA BAHARU -->
        <div class="col-lg-5">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-user-plus text-primary fs-5"></i>
                        <h2 class="h5 mb-0 fw-bold text-dark">Daftar Peserta Baharu</h2>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form method="post" enctype="multipart/form-data">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="create">

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">Bidang Kemahiran <span class="text-danger">*</span></label>
                            <select name="skill_id" id="skillSelect" class="form-select" required>
                                <option value="">Pilih Bidang (22 Bidang Tersedia)</option>
                                <?php foreach ($skills as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>"
                                            data-assistant="<?= (int)$s['assistant_count'] ?>"
                                            data-model="<?= (int)$s['model_count'] ?>"
                                            data-note="<?= e($s['assistant_note'] ?? '') ?>"
                                            <?= selected(old('skill_id'), $s['id']) ?>>
                                        <?= e($s['name']) ?> (<?= e($s['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="helperNoticeContainer" style="display:none;">
                                <div class="helper-notice-box" id="helperNotice">
                                    <i class="fa-solid fa-circle-info mt-1 flex-shrink-0"></i>
                                    <span id="helperNote"></span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">Nama Penuh Peserta <span class="text-danger">*</span></label>
                            <input name="participant_name" class="form-control" value="<?= e(old('participant_name')) ?>" placeholder="Cth: Muhammad Amirul bin Rosli" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">No. Kad Pengenalan</label>
                                <input name="ic_number" class="form-control font-monospace" value="<?= e(old('ic_number')) ?>" placeholder="060412-10-5421">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Saiz Baju <span class="text-danger">*</span></label>
                                <select name="shirt_size" class="form-select" required>
                                    <option value="">Pilih Saiz</option>
                                    <?php foreach (['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'] as $sz): ?>
                                        <option value="<?= $sz ?>" <?= selected(old('shirt_size'), $sz) ?>><?= $sz ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">E-mel Peserta</label>
                                <input type="email" name="participant_email" class="form-control" value="<?= e(old('participant_email')) ?>" placeholder="pelajar@gmail.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">No. Telefon Peserta</label>
                                <input type="text" name="participant_phone" class="form-control" value="<?= e(old('participant_phone')) ?>" placeholder="011-2345678">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">Kolej Vokasional / Institusi <span class="text-danger">*</span></label>
                            <input name="institution" class="form-control" value="<?= e(old('institution', $user['institution'] ?? '')) ?>" required>
                        </div>

                        <div class="mb-3" id="assistantField" style="display: none;">
                            <label class="form-label small fw-bold text-dark mb-1">Nama Pembantu Bertanding (Helper)</label>
                            <input name="assistant_name" class="form-control" value="<?= e(old('assistant_name')) ?>" placeholder="Nama penuh pembantu">
                        </div>

                        <div class="mb-3" id="modelField" style="display: none;">
                            <label class="form-label small fw-bold text-dark mb-1">Nama Model Peserta</label>
                            <input name="model_name" class="form-control" value="<?= e(old('model_name')) ?>" placeholder="Nama penuh model">
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark mb-1">Foto Calon Peserta (Opsyenal)</label>
                            <input type="file" name="participant_photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text small text-muted" style="font-size: 11.5px;">Format: JPG, PNG, atau WEBP. Gambar pasport rasmi.</div>
                        </div>

                        <button type="submit" <?= !$competition ? 'disabled' : '' ?>  class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="fa-solid fa-paper-plane me-1"></i> Hantar Pendaftaran Peserta
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- SENARAI PESERTA BERDAFTAR -->
        <div class="col-lg-7">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-users text-secondary fs-5"></i>
                        <h2 class="h5 mb-0 fw-bold text-dark">Senarai Calon Peserta Kontinjen Anda</h2>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 font-monospace"><?= count($rows) ?> Peserta</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Peserta</th>
                                    <th>Bidang Kemahiran</th>
                                    <th class="text-center">Saiz</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end pe-4">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="<?= e(participant_photo_url($r['image_path'])) ?>" class="rounded-circle border shadow-xs" style="width: 40px; height: 40px; object-fit: cover;" alt="Peserta">
                                                <div>
                                                    <strong class="d-block text-dark small"><?= e($r['participant_name']) ?></strong>
                                                    <?php if ($r['ic_number']): ?>
                                                        <small class="text-muted d-block font-monospace" style="font-size: 11.5px;"><?= e($r['ic_number']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong class="d-block text-primary small"><?= e($r['skill_name']) ?></strong>
                                            <small class="text-muted"><?= e($r['skill_code']) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border"><?= e($r['shirt_size'] ?: '-') ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($r['status'] === 'approved'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    <i class="fa-solid fa-circle-check me-1"></i> Diluluskan
                                                </span>
                                            <?php elseif ($r['status'] === 'submitted'): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                                    <i class="fa-solid fa-clock me-1"></i> Menunggu
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                    <i class="fa-solid fa-circle-xmark me-1"></i> Ditolak
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($r['rejection_reason']): ?>
                                                <div class="small text-danger mt-1" style="font-size: 11px;" title="<?= e($r['rejection_reason']) ?>">
                                                    <i class="fa-solid fa-circle-exclamation me-1"></i>Sebab Ditolak
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="d-inline-flex gap-1">
                                                <!-- BUTANG PAPAR (VIEW MODAL) -->
                                                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewModal-<?= (int)$r['id'] ?>" title="Papar Maklumat Lengkap">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>

                                                <!-- BUTANG EDIT (EDIT MODAL) -->
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal-<?= (int)$r['id'] ?>" title="Edit Maklumat Peserta">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>

                                                <!-- BUTANG PADAM -->
                                                <?php if (in_array($r['status'], ['submitted', 'rejected', 'draft'], true)): ?>
                                                    <form method="post" class="d-inline" onsubmit="return confirm('Adakah anda pasti ingin memadam pendaftaran peserta ini?')">
                                                        <?= Csrf::field() ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Padam Pendaftaran">
                                                            <i class="fa-solid fa-trash-can"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>

                                            <!-- 1. MODAL PAPAR MAKLUMAT PESERTA -->
                                            <div class="modal fade text-start" id="viewModal-<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg); overflow: hidden;">
                                                        <div class="modal-header py-3 px-4" style="background: var(--brand-navy); color: #ffffff;">
                                                            <h5 class="modal-title fs-6 fw-bold text-white">
                                                                <i class="fa-solid fa-id-badge me-2 text-warning"></i>Maklumat Lengkap Calon Peserta
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <div class="text-center mb-4 pb-3 border-bottom">
                                                                <img src="<?= e(participant_photo_url($r['image_path'])) ?>" class="rounded-circle border shadow-sm mb-2" style="width: 88px; height: 88px; object-fit: cover;" alt="Peserta">
                                                                <h4 class="h5 fw-bold mb-1 text-dark"><?= e($r['participant_name']) ?></h4>
                                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 px-3 py-1"><?= e($r['skill_name']) ?> (<?= e($r['skill_code']) ?>)</span>
                                                            </div>

                                                            <ul class="profile-meta-list small">
                                                                <li class="profile-meta-item">
                                                                    <span class="profile-meta-label">No. Kad Pengenalan:</span>
                                                                    <span class="profile-meta-val font-monospace"><?= e($r['ic_number'] ?: 'Tidak dinyatakan') ?></span>
                                                                </li>
                                                                <li class="profile-meta-item">
                                                                    <span class="profile-meta-label">Saiz Baju:</span>
                                                                    <span class="profile-meta-val"><span class="badge bg-light text-dark border"><?= e($r['shirt_size'] ?: '-') ?></span></span>
                                                                </li>
                                                                <li class="profile-meta-item">
                                                                    <span class="profile-meta-label">Kolej Vokasional:</span>
                                                                    <span class="profile-meta-val"><?= e($r['institution']) ?></span>
                                                                </li>
                                                                <li class="profile-meta-item">
                                                                    <span class="profile-meta-label">Zon Kontinjen:</span>
                                                                    <span class="profile-meta-val"><span class="badge bg-secondary-subtle text-secondary border"><?= e($r['zone_name']) ?></span></span>
                                                                </li>
                                                                <li class="profile-meta-item">
                                                                    <span class="profile-meta-label">E-mel Peserta:</span>
                                                                    <span class="profile-meta-val"><?= e($r['participant_email'] ?: '-') ?></span>
                                                                </li>
                                                                <li class="profile-meta-item">
                                                                    <span class="profile-meta-label">No. Telefon:</span>
                                                                    <span class="profile-meta-val"><?= e($r['participant_phone'] ?: '-') ?></span>
                                                                </li>
                                                                <?php if ($r['assistant_name']): ?>
                                                                    <li class="profile-meta-item">
                                                                        <span class="profile-meta-label">Pembantu (Helper):</span>
                                                                        <span class="profile-meta-val text-primary fw-bold"><?= e($r['assistant_name']) ?></span>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <?php if ($r['model_name']): ?>
                                                                    <li class="profile-meta-item">
                                                                        <span class="profile-meta-label">Model:</span>
                                                                        <span class="profile-meta-val text-primary fw-bold"><?= e($r['model_name']) ?></span>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <li class="profile-meta-item">
                                                                    <span class="profile-meta-label">Status Pengesahan:</span>
                                                                    <span class="profile-meta-val">
                                                                        <?php if ($r['status'] === 'approved'): ?>
                                                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Diluluskan</span>
                                                                        <?php elseif ($r['status'] === 'submitted'): ?>
                                                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Menunggu Semakan</span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Ditolak</span>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </li>
                                                                <?php if ($r['rejection_reason']): ?>
                                                                    <li class="py-2">
                                                                        <span class="text-danger fw-semibold d-block mb-1">Catatan Penolakan oleh PT:</span>
                                                                        <div class="alert alert-danger py-2 mb-0 small border-0"><?= e($r['rejection_reason']) ?></div>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <li class="profile-meta-item">
                                                                    <span class="profile-meta-label">Tarikh Hantar:</span>
                                                                    <span class="profile-meta-val font-monospace"><?= $r['submitted_at'] ? date('d/m/Y, h:i A', strtotime($r['submitted_at'])) : '-' ?></span>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                        <div class="modal-footer bg-light py-2 px-4">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- 2. MODAL EDIT MAKLUMAT PESERTA -->
                                            <div class="modal fade text-start" id="editModal-<?= (int)$r['id'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <form method="post" enctype="multipart/form-data" class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-lg); overflow: hidden;">
                                                        <?= Csrf::field() ?>
                                                        <input type="hidden" name="action" value="update_participant">
                                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                                        <div class="modal-header py-3 px-4" style="background: var(--brand-navy); color: #ffffff;">
                                                            <h5 class="modal-title fs-6 fw-bold text-white">
                                                                <i class="fa-solid fa-pen-to-square me-2 text-warning"></i>Kemas Kini Maklumat Peserta
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <div class="alert alert-light border small mb-3 d-flex align-items-center justify-content-between">
                                                                <div>
                                                                    Bidang: <strong><?= e($r['skill_name']) ?> (<?= e($r['skill_code']) ?>)</strong>
                                                                </div>
                                                                <div>
                                                                    Institusi: <strong><?= e($r['institution']) ?></strong>
                                                                </div>
                                                            </div>

                                                            <div class="row g-3">
                                                                <div class="col-md-12">
                                                                    <label class="form-label small fw-bold text-dark mb-1">Nama Penuh Peserta <span class="text-danger">*</span></label>
                                                                    <input type="text" name="participant_name" class="form-control" value="<?= e($r['participant_name']) ?>" required>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label small fw-bold text-dark mb-1">No. Kad Pengenalan</label>
                                                                    <input type="text" name="ic_number" class="form-control font-monospace" value="<?= e($r['ic_number'] ?? '') ?>" placeholder="Cth: 060412-10-5421">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label small fw-bold text-dark mb-1">Saiz Baju <span class="text-danger">*</span></label>
                                                                    <select name="shirt_size" class="form-select" required>
                                                                        <option value="">Pilih Saiz Baju</option>
                                                                        <?php foreach (['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'] as $sz): ?>
                                                                            <option value="<?= $sz ?>" <?= selected($r['shirt_size'], $sz) ?>><?= $sz ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label small fw-bold text-dark mb-1">E-mel Peserta</label>
                                                                    <input type="email" name="participant_email" class="form-control" value="<?= e($r['participant_email'] ?? '') ?>">
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label small fw-bold text-dark mb-1">No. Telefon Peserta</label>
                                                                    <input type="text" name="participant_phone" class="form-control" value="<?= e($r['participant_phone'] ?? '') ?>">
                                                                </div>

                                                                <?php if ((int)$r['assistant_count'] > 0): ?>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label small fw-bold text-dark mb-1">Nama Pembantu (Helper)</label>
                                                                        <input type="text" name="assistant_name" class="form-control" value="<?= e($r['assistant_name'] ?? '') ?>" placeholder="Nama pembantu">
                                                                    </div>
                                                                <?php endif; ?>

                                                                <?php if ((int)$r['model_count'] > 0): ?>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label small fw-bold text-dark mb-1">Nama Model</label>
                                                                        <input type="text" name="model_name" class="form-control" value="<?= e($r['model_name'] ?? '') ?>" placeholder="Nama model">
                                                                    </div>
                                                                <?php endif; ?>

                                                                <div class="col-md-12">
                                                                    <label class="form-label small fw-bold text-dark mb-1">Ganti Foto Peserta (Opsyenal)</label>
                                                                    <input type="file" name="participant_photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                                                                    <div class="form-text small text-muted" style="font-size: 11.5px;">Biarkan kosong jika tidak ingin menukar foto sedia ada.</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer bg-light py-2 px-4">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" class="btn btn-primary btn-sm px-3 fw-semibold">
                                                                <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>

                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$rows): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-5">
                                            <i class="fa-solid fa-user-group fs-2 d-block mb-2 opacity-25"></i>
                                            Belum ada calon peserta didaftarkan oleh anda.
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const s = document.getElementById('skillSelect');
    const a = document.getElementById('assistantField');
    const m = document.getElementById('modelField');
    const n = document.getElementById('helperNote');
    const c = document.getElementById('helperNoticeContainer');

    function update() {
        if (!s) return;
        const o = s.options[s.selectedIndex];
        const ac = Number(o?.dataset.assistant || 0);
        const mc = Number(o?.dataset.model || 0);
        if (a) a.style.display = ac ? 'block' : 'none';
        if (m) m.style.display = mc ? 'block' : 'none';
        if (a && a.querySelector('input')) a.querySelector('input').required = ac > 0;
        if (m && m.querySelector('input')) m.querySelector('input').required = mc > 0;
        if (o?.dataset.note) {
            if (n) n.textContent = 'Ketetapan Teknikal: ' + o.dataset.note;
            if (c) c.style.display = 'block';
        } else {
            if (n) n.textContent = '';
            if (c) c.style.display = 'none';
        }
    }
    if (s) {
        s.addEventListener('change', update);
        update();
    }
});
</script>
<?php require BASE_PATH . '/partials/footer.php'; ?>
