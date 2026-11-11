<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once BASE_PATH . '/config/upload.php';

if (Auth::check()) {
    $role = Auth::role();
    if ($role === 'coach') redirect('jurulatih/dashboard.php');
    if ($role === 'admin') redirect('admin/dashboard.php');
    redirect('pegawai_teknikal/dashboard.php');
}

$comp   = registration_competition();
$zones  = all_zones();
$skills = all_skills();

if (is_post()) {
    Csrf::verify();
    $errors = [];

    if (!$comp) {
        flash('error', 'Tiada edisi pertandingan yang sedang membuka pendaftaran pada masa ini.');
        redirect('jurulatih/register.php');
    }

    // 1. MAKLUMAT JURULATIH
    $coachName     = trim((string)input('coach_name'));
    $coachEmail    = mb_strtolower(trim((string)input('coach_email')));
    $coachIc       = trim((string)input('coach_ic_number'));
    $coachPhone    = trim((string)input('coach_phone'));
    $coachZoneId   = (int)input('zone_id');
    $institution   = trim((string)input('institution'));
    $coachShirt    = trim((string)input('coach_shirt_size'));
    $password      = (string)input('password');
    $confirmPass   = (string)input('confirm_password');

    if (mb_strlen($coachName) < 3) $errors[] = 'Nama penuh Jurulatih diperlukan.';
    if (!filter_var($coachEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format e-mel Jurulatih tidak sah.';
    if ($coachIc === '') $errors[] = 'No. Kad Pengenalan Jurulatih diperlukan.';
    if ($coachZoneId <= 0) $errors[] = 'Sila pilih zon kontinjen anda.';
    if ($institution === '') $errors[] = 'Sila masukkan nama Kolej Vokasional / Institusi.';
    if (strlen($password) < 8) $errors[] = 'Kata laluan mesti sekurang-kurangnya 8 aksara.';
    if ($password !== $confirmPass) $errors[] = 'Kata laluan dan pengesahan kata laluan tidak sepadan.';

    // Check duplicate email
    $chk = db()->prepare("SELECT id FROM users WHERE email = ?");
    $chk->execute([$coachEmail]);
    if ($chk->fetch()) {
        $errors[] = "E-mel '$coachEmail' telah digunakan. Sila gunakan e-mel lain atau log masuk.";
    }

    // 2. MAKLUMAT PESERTA
    $partName   = trim((string)input('participant_name'));
    $partIc     = trim((string)input('participant_ic'));
    $partShirt  = trim((string)input('participant_shirt_size'));
    $partEmail  = trim((string)input('participant_email'));
    $partPhone  = trim((string)input('participant_phone'));
    $skillId    = (int)input('skill_id');
    $assistant  = trim((string)input('assistant_name'));
    $model      = trim((string)input('model_name'));

    if (mb_strlen($partName) < 3) $errors[] = 'Nama penuh peserta diperlukan.';
    if ($partShirt === '') $errors[] = 'Sila pilih saiz baju peserta.';
    if ($skillId <= 0) $errors[] = 'Sila pilih bidang kemahiran yang disertai.';

    $skill = $skillId > 0 ? skill_by_id($skillId) : null;
    if (!$skill) {
        $errors[] = 'Bidang kemahiran yang dipilih tidak sah.';
    } else {
        $errors = array_merge($errors, CompetitionService::validateHelpers($skill, [
            'assistant' => $assistant !== '' ? [$assistant] : [],
            'model'     => $model !== '' ? [$model] : []
        ]));
    }

    // 3. GAMBAR UPLOADS
    $coachPhotoPath = null;
    if (!$errors && !empty($_FILES['coach_photo']['name'])) {
        try {
            $up = store_image_upload($_FILES['coach_photo'], 'users');
            $coachPhotoPath = $up['relative_path'];
        } catch (Throwable $e) {
            $errors[] = 'Gambar Jurulatih: ' . $e->getMessage();
        }
    }

    $partPhotoPath = null;
    if (!$errors && !empty($_FILES['participant_photo']['name'])) {
        try {
            $up2 = store_image_upload($_FILES['participant_photo'], 'participants');
            $partPhotoPath = $up2['relative_path'];
        } catch (Throwable $e) {
            $errors[] = 'Gambar Peserta: ' . $e->getMessage();
        }
    }

    // EXECUTE REGISTRATION
    if (!$errors) {
        try {
            db()->beginTransaction();
            lock_registration_slot((int)$comp['id'], $skillId);

            // A. Insert Coach
            $stmtCoach = db()->prepare("
                INSERT INTO users(zone_id, role, status, full_name, email, phone, ic_number, institution, image_path, shirt_size, password_hash, approved_at)
                VALUES(?, 'coach', 'active', ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtCoach->execute([
                $coachZoneId,
                $coachName,
                $coachEmail,
                $coachPhone ?: null,
                $coachIc,
                $institution,
                $coachPhotoPath,
                $coachShirt ?: null,
                password_hash($password, PASSWORD_DEFAULT)
            ]);
            $newCoachId = (int)db()->lastInsertId();

            // B. Insert Participant Registration
            $stmtReg = db()->prepare("
                INSERT INTO registrations(competition_id, zone_id, skill_id, coach_user_id, participant_name, ic_number, shirt_size, participant_email, participant_phone, institution, image_path, status, submitted_at)
                VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', NOW())
            ");
            $stmtReg->execute([
                (int)$comp['id'],
                $coachZoneId,
                $skillId,
                $newCoachId,
                $partName,
                $partIc ?: null,
                $partShirt,
                $partEmail ?: null,
                $partPhone ?: null,
                $institution,
                $partPhotoPath
            ]);
            $newRegId = (int)db()->lastInsertId();

            // C. Insert Helpers if applicable
            $stmtH = db()->prepare("INSERT INTO registration_helpers(registration_id, helper_type, full_name) VALUES(?, ?, ?)");
            if ($assistant !== '') $stmtH->execute([$newRegId, 'assistant', $assistant]);
            if ($model !== '')     $stmtH->execute([$newRegId, 'model', $model]);

            db()->commit();
            $coachPhotoPath = $partPhotoPath = null;

            audit($newCoachId, 'coach_signup_with_participant', 'user', $newCoachId, ['reg_id' => $newRegId]);

            // Auto-login the new coach
            if (Auth::attempt($coachEmail, $password, 'coach')) {
                flash('success', "Tahniah {$coachName}! Akaun Jurulatih anda telah diaktifkan dan pendaftaran peserta bagi bidang '{$skill['name']}' telah dihantar kepada Pegawai Teknikal.");
                redirect('jurulatih/dashboard.php');
            } else {
                flash('success', "Pendaftaran berjaya! Sila log masuk ke akaun Jurulatih anda.");
                redirect('jurulatih/login.php');
            }
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $errors[] = 'Pendaftaran gagal disimpan. Semak maklumat dan slot bidang.';
        }
    }

    if ($errors) {
        discard_new_photo($coachPhotoPath);
        discard_new_photo($partPhotoPath);
        flash('error', implode(' ', $errors));
    }
}

$pageTitle = 'Pendaftaran Jurulatih & Peserta';
require BASE_PATH . '/partials/head.php';
?>
<div class="login-wrapper" style="padding: 48px 16px;">
    <div style="width: 100%; max-width: 920px;">
        <a href="<?= e(url('jurulatih/login.php')) ?>" class="login-back">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Kembali ke Log Masuk</span>
        </a>

        <div class="login-header-block">
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 mb-3 rounded-pill" style="background: rgba(22, 33, 92, 0.08); border: 1px solid rgba(22, 33, 92, 0.14); font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--brand-navy);">
                <i class="fa-solid fa-award text-warning"></i>
                <span>Kementerian Pendidikan Malaysia &middot; BPLTV</span>
            </div>
            <div>
                <img src="<?= e(url('assets/images/Logo KVSkills.png')) ?>" class="login-logo" alt="Logo KVSkills">
            </div>
            <div class="login-brand">KVSkills Hub Malaysia</div>
            <p class="text-muted small mb-0">Pendaftaran Rasmi Jurulatih Pengiring &amp; Calon Peserta Kontinjen</p>
        </div>

        <div class="card border shadow-md mx-auto" style="border-radius: var(--radius-xl); overflow: hidden;">
            <div class="card-header py-4 px-4 px-md-5" style="background: var(--brand-navy); border-bottom: 3px solid var(--brand-amber); color: #ffffff;">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1 text-white">Borang Pendaftaran Kontinjen Kebangsaan</h2>
                        <p class="small mb-0 text-slate-300">
                            <?= $comp ? e($comp['name']) : 'Pertandingan Kemahiran Kolej Vokasional Peringkat Kebangsaan' ?>
                        </p>
                    </div>
                    <span class="badge" style="background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2); font-size: 11.5px; font-weight: 600; padding: 6px 12px;">
                        <i class="fa-solid fa-clipboard-check text-warning me-1"></i> Borang Rasmi BPLTV
                    </span>
                </div>
            </div>

            <div class="card-body p-4 p-md-5 bg-white">
                <?php if ($m = flash('error')): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-4 small border-0 shadow-xs">
                        <i class="fa-solid fa-circle-exclamation flex-shrink-0"></i>
                        <div><?= e($m) ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($m = flash('success')): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2 py-2 mb-4 small border-0 shadow-xs">
                        <i class="fa-solid fa-circle-check flex-shrink-0"></i>
                        <div><?= e($m) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!$comp): ?><p role="status">Tiada pertandingan yang sedang membuka pendaftaran.</p><?php endif; ?>
                <form method="post" enctype="multipart/form-data">
                    <?= Csrf::field() ?>

                    <!-- BAHAGIAN 1: MAKLUMAT JURULATIH -->
                    <div class="mb-5">
                        <div class="form-section-header">
                            <div class="form-section-icon icon-navy">
                                <i class="fa-solid fa-user-tie"></i>
                            </div>
                            <div>
                                <h3 class="form-section-title">Bahagian 1: Maklumat Jurulatih Pengiring</h3>
                                <p class="form-section-desc">Akaun portal rasmi dan profil pegawai pengiring kontinjen Kolej Vokasional.</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Nama Penuh Jurulatih <span class="text-danger">*</span></label>
                                <input type="text" name="coach_name" class="form-control" value="<?= e(old('coach_name')) ?>" placeholder="Cth: Cikgu Ahmad Faiz bin Ismail" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">E-mel Rasmi Jurulatih <span class="text-danger">*</span></label>
                                <input type="email" name="coach_email" class="form-control" value="<?= e(old('coach_email')) ?>" placeholder="ahmadfaiz@moe.gov.my" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">No. Kad Pengenalan (No. IC) <span class="text-danger">*</span></label>
                                <input type="text" name="coach_ic_number" class="form-control font-monospace" value="<?= e(old('coach_ic_number')) ?>" placeholder="Cth: 850512-04-5123" required>
                                <div class="form-text small text-muted" style="font-size: 11.5px;">Diperlukan untuk pengesahan identiti dan pemulihan kata laluan.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">No. Telefon</label>
                                <input type="text" name="coach_phone" class="form-control" value="<?= e(old('coach_phone')) ?>" placeholder="Cth: 012-3456789">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Zon Kontinjen <span class="text-danger">*</span></label>
                                <select name="zone_id" class="form-select" required>
                                    <option value="">Pilih Zon Kontinjen</option>
                                    <?php foreach ($zones as $z): ?>
                                        <option value="<?= (int)$z['id'] ?>" <?= selected(old('zone_id'), $z['id']) ?>><?= e($z['name']) ?> (<?= e($z['code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Kolej Vokasional / Institusi <span class="text-danger">*</span></label>
                                <input type="text" name="institution" class="form-control" value="<?= e(old('institution')) ?>" placeholder="Cth: KV Shah Alam" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Saiz Baju Jurulatih</label>
                                <select name="coach_shirt_size" class="form-select">
                                    <option value="">Pilih Saiz Baju</option>
                                    <?php foreach (['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'] as $sz): ?>
                                        <option value="<?= $sz ?>" <?= selected(old('coach_shirt_size'), $sz) ?>><?= $sz ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Foto Profil Jurulatih (Opsyenal)</label>
                                <input type="file" name="coach_photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Kata Laluan Akaun <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" placeholder="Minimum 8 aksara" required autocomplete="new-password">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Sahkan Kata Laluan <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Ulang kata laluan" required autocomplete="new-password">
                            </div>
                        </div>
                    </div>

                    <!-- BAHAGIAN 2: MAKLUMAT PESERTA -->
                    <div class="mb-4">
                        <div class="form-section-header">
                            <div class="form-section-icon icon-emerald">
                                <i class="fa-solid fa-graduation-cap"></i>
                            </div>
                            <div>
                                <h3 class="form-section-title">Bahagian 2: Maklumat Peserta &amp; Bidang Kemahiran</h3>
                                <p class="form-section-desc">Butiran calon peserta bertanding serta pembantu atau model mengikut peraturan teknikal.</p>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Nama Penuh Peserta <span class="text-danger">*</span></label>
                                <input type="text" name="participant_name" class="form-control" value="<?= e(old('participant_name')) ?>" placeholder="Cth: Muhammad Harith bin Zulkifli" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">No. Kad Pengenalan Peserta</label>
                                <input type="text" name="participant_ic" class="form-control font-monospace" value="<?= e(old('participant_ic')) ?>" placeholder="Cth: 060412-10-5421">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Bidang Kemahiran KVSkills <span class="text-danger">*</span></label>
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
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">Saiz Baju Peserta <span class="text-danger">*</span></label>
                                <select name="participant_shirt_size" class="form-select" required>
                                    <option value="">Pilih Saiz Baju</option>
                                    <?php foreach (['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'] as $sz): ?>
                                        <option value="<?= $sz ?>" <?= selected(old('participant_shirt_size'), $sz) ?>><?= $sz ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">E-mel Peserta (Opsyenal)</label>
                                <input type="email" name="participant_email" class="form-control" value="<?= e(old('participant_email')) ?>" placeholder="pelajar@gmail.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-dark mb-1">No. Telefon Peserta (Opsyenal)</label>
                                <input type="text" name="participant_phone" class="form-control" value="<?= e(old('participant_phone')) ?>" placeholder="011-2345678">
                            </div>
                            <div class="col-md-6" id="assistantField" style="display: none;">
                                <label class="form-label small fw-bold text-dark mb-1">Nama Pembantu Bertanding (Helper)</label>
                                <input type="text" name="assistant_name" class="form-control" value="<?= e(old('assistant_name')) ?>" placeholder="Nama penuh pembantu">
                            </div>
                            <div class="col-md-6" id="modelField" style="display: none;">
                                <label class="form-label small fw-bold text-dark mb-1">Nama Model Peserta</label>
                                <input type="text" name="model_name" class="form-control" value="<?= e(old('model_name')) ?>" placeholder="Nama penuh model">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-dark mb-1">Foto Calon Peserta (Opsyenal)</label>
                                <input type="file" name="participant_photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text small text-muted" style="font-size: 11.5px;">Format: JPG, PNG, atau WEBP. Gambar formal pasport latar belakang biru atau putih.</div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-top mt-4">
                        <button type="submit" <?= !$comp ? 'disabled' : '' ?>  class="btn btn-primary w-100 py-3 fw-bold fs-6 shadow-sm">
                            <i class="fa-solid fa-user-check me-2"></i>Daftar Jurulatih &amp; Hantar Penyertaan Peserta
                        </button>
                    </div>

                    <div class="text-center mt-3 small text-muted">
                        Sudah mempunyai akaun berdaftar?
                        <a href="<?= e(url('jurulatih/login.php')) ?>" class="fw-bold text-primary">Log Masuk di sini</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const s = document.getElementById('skillSelect');
    const a = document.getElementById('assistantField');
    const m = document.getElementById('modelField');
    const n = document.getElementById('helperNote');
    const c = document.getElementById('helperNoticeContainer');

    function updateHelpers() {
        if (!s) return;
        const o = s.options[s.selectedIndex];
        const ac = Number(o?.dataset.assistant || 0);
        const mc = Number(o?.dataset.model || 0);

        if (a) a.style.display = ac ? 'block' : 'none';
        if (m) m.style.display = mc ? 'block' : 'none';

        const aInput = a ? a.querySelector('input') : null;
        const mInput = m ? m.querySelector('input') : null;
        if (aInput) aInput.required = ac > 0;
        if (mInput) mInput.required = mc > 0;

        if (o?.dataset.note) {
            if (n) n.textContent = 'Ketetapan Teknikal: ' + o.dataset.note;
            if (c) c.style.display = 'block';
        } else {
            if (n) n.textContent = '';
            if (c) c.style.display = 'none';
        }
    }

    if (s) {
        s.addEventListener('change', updateHelpers);
        updateHelpers();
    }
});
</script>
</body>
</html>
