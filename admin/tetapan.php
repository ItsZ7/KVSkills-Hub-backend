<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once BASE_PATH . '/config/upload.php';
Auth::requireLogin('admin');
$user = Auth::user();

if (is_post()) {
    Csrf::verify();
    $action = (string)input('action');

    if ($action === 'profile') {
        $name = trim((string)input('full_name'));
        $phone = trim((string)input('phone'));
        $institution = trim((string)input('institution'));
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
            $stmt = db()->prepare('UPDATE users SET full_name=?, phone=?, institution=?, image_path=? WHERE id=?');
            $stmt->execute([$name, $phone ?: null, $institution ?: null, $imagePath, Auth::id()]);
            audit(Auth::id(), 'update_profile', 'user', Auth::id());
            Auth::refresh();
            flash('success', 'Profil pentadbir berjaya dikemas kini.');
            redirect('admin/tetapan.php');
        }
        flash('error', implode(' ', $errors));
        redirect('admin/tetapan.php');
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
            
            flash('success', 'Kata laluan pentadbir berjaya ditukar.');
            redirect('admin/tetapan.php');
        }
        flash('error', implode(' ', $errors));
        redirect('admin/tetapan.php');
    }
}

$pageTitle = 'Tetapan Pentadbir';
$activePage = 'admin_settings';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>
<section class="dashboard-header py-4">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-2 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-gears text-primary"></i>
            <span>Pusat Kawalan Pentadbir &middot; Konfigurasi Akaun</span>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-1">Tetapan Pentadbir</h1>
        <p class="text-muted mb-0">Kemaskini profil peribadi dan kata laluan pentadbir sistem.</p>
    </div>
</section>

<section class="container py-4">
    <div class="row g-4">
        <!-- PROFILE FORM -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3"><i class="fa-solid fa-user-pen me-2 text-primary"></i>Kemas Kini Profil</h2>
                    <form method="post" enctype="multipart/form-data">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="profile">
                        <div class="text-center mb-4">
                            <img src="<?= e(user_avatar_url($user['image_path'] ?? null)) ?>" class="rounded-circle mb-2" style="width: 100px; height: 100px; object-fit: cover;" alt="Avatar">
                            <div class="small text-muted"><?= e($user['email']) ?> (Pentadbir)</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Gambar Profil</label>
                            <input type="file" name="profile_photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">Format: JPG, PNG, atau WEBP. Maksimum 5MB.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Penuh</label>
                            <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">No. Telefon</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Institusi / Jabatan</label>
                            <input type="text" name="institution" class="form-control" value="<?= e($user['institution'] ?? '') ?>">
                        </div>
                        <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Simpan Profil</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- PASSWORD FORM -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3"><i class="fa-solid fa-key me-2 text-warning"></i>Tukar Kata Laluan</h2>
                    <form method="post">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="password">
                        <div class="mb-3">
                            <label class="form-label">Kata Laluan Semasa</label>
                            <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kata Laluan Baharu</label>
                            <input type="password" name="password" class="form-control" minlength="12" required autocomplete="new-password">
                            <div class="form-text">Minimum 12 aksara selamat.</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Ulang Kata Laluan Baharu</label>
                            <input type="password" name="password_confirmation" class="form-control" minlength="12" required autocomplete="new-password">
                        </div>
                        <button class="btn btn-warning w-100" type="submit"><i class="fa-solid fa-shield-halved me-1"></i>Tukar Kata Laluan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require BASE_PATH . '/partials/footer.php'; ?>