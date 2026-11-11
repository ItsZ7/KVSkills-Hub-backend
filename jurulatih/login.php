<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';

if (Auth::check()) {
    $role = Auth::role();
    if ($role === 'coach') redirect('jurulatih/dashboard.php');
    if ($role === 'admin') redirect('admin/dashboard.php');
    redirect('pegawai_teknikal/dashboard.php');
}

if (is_post()) {
    Csrf::verify();
    $email = trim((string)input('email'));
    $password = (string)input('password');
    if (Auth::attempt($email, $password, 'coach')) {
        flash('success', 'Selamat datang ke Dashboard Jurulatih.');
        redirect('jurulatih/dashboard.php');
    }
    flash('error', 'Log masuk gagal. Sila semak e-mel dan kata laluan yang dibekalkan oleh Pegawai Teknikal.');
}

$pageTitle = 'Log Masuk Jurulatih';
require BASE_PATH . '/partials/head.php';
?>
<div class="login-wrapper">
    <div style="width: 100%; max-width: 460px;">
        <a href="<?= e(url('jawatan.php')) ?>" class="login-back">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Kembali ke Pilihan Peranan</span>
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
            <p class="text-muted small mb-0">Portal Pengurusan Kontinjen &middot; Akses Jurulatih</p>
        </div>

        <div class="login-card">
            <h2>Log Masuk Jurulatih</h2>
            <div class="login-underline"></div>

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

            <form method="post">
                <?= Csrf::field() ?>
                <div class="mb-3">
                    <label for="email" class="form-label small fw-bold text-dark mb-1">E-mel Rasmi Jurulatih</label>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" placeholder="nama@kolejvokasional.edu.my" required autocomplete="email" autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label small fw-bold text-dark mb-0">Kata Laluan</label>
                        <a href="<?= e(url('jurulatih/forgot_password.php')) ?>" class="small text-decoration-none text-muted">Lupa Kata Laluan?</a>
                    </div>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan kata laluan" required autocomplete="current-password">
                    </div>
                </div>
                <button class="login-btn" type="submit">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>Log Masuk Portal</span>
                </button>
                <div class="text-center mt-4 pt-3 border-top small text-muted">
                    Belum mempunyai akaun kontinjen?
                    <a href="<?= e(url('jurulatih/register.php')) ?>" class="fw-bold text-primary d-block mt-1">
                        Daftar Akaun Jurulatih &amp; Peserta <i class="fa-solid fa-arrow-right ms-1 fs-xs"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>