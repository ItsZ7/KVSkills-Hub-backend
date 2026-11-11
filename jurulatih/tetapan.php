<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once BASE_PATH . '/config/upload.php';
Auth::requireLogin('coach');
$user = Auth::user();

if (is_post()) {
    Csrf::verify();
    $action = (string)input('action');

    if ($action === 'profile') {
        $name = trim((string)input('full_name'));
        $phone = trim((string)input('phone'));
        $errors = [];

        if (mb_strlen($name) < 3) $errors[] = 'Nama penuh diperlukan (minimum 3 aksara).';

        $imagePath = $user['image_path'] ?? null;
        if (!empty($_FILES['profile_photo']['name'])) {
            try {
                $stored = store_image_upload($_FILES['profile_photo'], 'users');
                $imagePath = $stored['relative_path'];
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!$errors) {
            $stmt = db()->prepare('UPDATE users SET full_name=?, phone=?, image_path=? WHERE id=?');
            $stmt->execute([$name, $phone ?: null, $imagePath, Auth::id()]);
            audit(Auth::id(), 'update_profile', 'user', Auth::id());
            Auth::refresh();
            flash('success', 'Profil anda berjaya dikemas kini.');
            redirect('jurulatih/tetapan.php');
        }
        flash('error', implode(' ', $errors));
        redirect('jurulatih/tetapan.php');
    }

    if ($action === 'password') {
        $current = (string)input('current_password');
        $new = (string)input('password');
        $confirm = (string)input('password_confirmation');
        $errors = [];

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id=?');
        $stmt->execute([Auth::id()]);
        $hash = (string)$stmt->fetchColumn();

        if (!password_verify($current, $hash)) $errors[] = 'Kata laluan semasa tidak tepat.';
        if (strlen($new) < 12) $errors[] = 'Kata laluan baharu mesti sekurang-kurangnya 12 aksara.';
        if ($new !== $confirm) $errors[] = 'Pengesahan kata laluan baharu tidak sepadan.';
        if (hash_equals($current, $new)) $errors[] = 'Kata laluan baharu mesti berbeza daripada kata laluan semasa.';

        if (!$errors) {
            Auth::changeOwnPassword($new);
            audit(Auth::id(), 'change_password', 'user', Auth::id());
            
            flash('success', 'Kata laluan berjaya ditukar.');
            redirect('jurulatih/tetapan.php');
        }
        flash('error', implode(' ', $errors));
        redirect('jurulatih/tetapan.php');
    }
}

$pageTitle = 'Tetapan Jurulatih';
$activePage = 'coach_settings';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>
<section class="dashboard-header py-4">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-3 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-user-gear text-warning"></i>
            <span>Konfigurasi Akaun &middot; Jurulatih Rasmi</span>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-2">Tetapan Profil &amp; Keselamatan</h1>
        <p class="lead mx-auto mb-0 text-muted" style="max-width: 680px; font-size: 1.05rem;">
            Kemaskini profil diri, gambar akaun, dan kata laluan keselamatan akaun KVSkills Hub anda.
        </p>
    </div>
</section>

<section class="container py-4">
    <div class="row g-4">
        <!-- PROFILE FORM -->
        <div class="col-lg-6">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-user-pen text-primary fs-5"></i>
                        <div>
                            <h2 class="h5 mb-0 fw-bold text-dark">Kemas Kini Profil</h2>
                            <small class="text-muted">Maklumat peribadi dan institusi bimbingan anda</small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form method="post" enctype="multipart/form-data">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="profile">

                        <!-- AVATAR HEADER -->
                        <div class="d-flex align-items-center gap-3 p-3 mb-4 rounded-3" style="background: var(--slate-50); border: 1px solid var(--border-subtle);">
                            <img src="<?= e(user_avatar_url($user['image_path'] ?? null)) ?>"
                                 class="rounded-circle border shadow-xs"
                                 style="width: 72px; height: 72px; object-fit: cover;"
                                 alt="Avatar">
                            <div>
                                <h3 class="h6 fw-bold text-dark mb-1"><?= e($user['full_name']) ?></h3>
                                <div class="small text-muted mb-1">
                                    <i class="fa-solid fa-envelope me-1"></i><?= e($user['email']) ?>
                                </div>
                                <span class="badge text-bg-light border text-secondary small font-monospace">
                                    <?= e($user['zone_name'] ?? 'Zon belum ditetapkan') ?>
                                </span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">Muat Naik Gambar Profil Baharu</label>
                            <input type="file" name="profile_photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">Format dibenarkan: JPG, PNG, atau WEBP. Maksimum 5MB.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">Nama Penuh <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">No. Telefon</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="Cth: 012-3456789">
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark mb-1">Kolej Vokasional (Institusi)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted">
                                    <i class="fa-solid fa-building-columns"></i>
                                </span>
                                <input type="text" class="form-control bg-light border-start-0 text-secondary" value="<?= e($user['institution'] ?? '') ?>" readonly>
                            </div>
                            <div class="form-text"><i class="fa-solid fa-circle-info me-1"></i>Institusi ditetapkan secara rasmi oleh Pegawai Teknikal zon anda.</div>
                        </div>

                        <button class="btn btn-primary w-100 py-2 d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Simpan Profil</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- PASSWORD FORM -->
        <div class="col-lg-6">
            <div class="card border shadow-sm h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fa-solid fa-shield-halved text-warning fs-5"></i>
                        <div>
                            <h2 class="h5 mb-0 fw-bold text-dark">Tukar Kata Laluan</h2>
                            <small class="text-muted">Perkukuh keselamatan akaun portal anda</small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form method="post">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="password">

                        <div class="p-3 mb-4 rounded-3" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 13px; color: #1e40af;">
                            <div class="d-flex align-items-start gap-2">
                                <i class="fa-solid fa-circle-check mt-1 text-primary"></i>
                                <div>
                                    <strong>Syarat Keselamatan Kata Laluan:</strong>
                                    <div class="mt-1">Minimum 12 aksara. Disyorkan gabungan huruf besar, huruf kecil, nombor, dan simbol unik untuk perlindungan maksimum.</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">Kata Laluan Semasa <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" class="form-control" required autocomplete="current-password" placeholder="Masukkan kata laluan semasa">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-dark mb-1">Kata Laluan Baharu <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" minlength="12" required autocomplete="new-password" placeholder="Minimum 12 aksara">
                            <div class="form-text">Kata laluan baharu mesti berbeza daripada kata laluan semasa.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-dark mb-1">Pengesahan Kata Laluan Baharu <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" class="form-control" minlength="12" required autocomplete="new-password" placeholder="Ulang kata laluan baharu">
                        </div>

                        <button class="btn btn-warning w-100 py-2 fw-semibold text-dark d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                            <i class="fa-solid fa-key"></i>
                            <span>Tukar Kata Laluan</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require BASE_PATH . '/partials/footer.php'; ?>