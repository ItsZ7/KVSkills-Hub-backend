<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';

if (Auth::check()) {
    $role = Auth::role();
    if ($role === 'admin') redirect('admin/dashboard.php');
    if ($role === 'technical') redirect('pegawai_teknikal/dashboard.php');
    redirect('jurulatih/dashboard.php');
}

if (is_post()) {
    Csrf::verify();
    $email = trim((string)input('email'));
    $password = (string)input('password');
    if (Auth::attempt($email, $password, 'admin')) {
        flash('success', 'Selamat datang ke Dashboard Pentadbir Sistem.');
        redirect('admin/dashboard.php');
    }
    flash('error', 'Log masuk gagal. Sila semak e-mel, kata laluan atau kebenaran akaun pentadbir.');
}

$pageTitle = 'Log Masuk Pentadbir';
require BASE_PATH . '/partials/head.php';
?>
<div class="login-wrapper">
    <a href="<?= e(url('jawatan.php')) ?>" class="login-back">
        <i class="fa-solid fa-arrow-left"></i>Kembali ke Pilihan Peranan
    </a>
    <div class="login-header-block text-center mb-3">
        <img src="<?= e(url('assets/images/Logo KVSkills.png')) ?>" class="login-logo" alt="Logo KVSkills">
        <div class="login-brand">KVSkills Hub Malaysia</div>
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1 mt-1 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-shield-halved text-warning"></i>
            <span>Portal Kawalan Pentadbir Sistem</span>
        </div>
    </div>
    <div class="login-card">
        <h2>Log Masuk Pentadbir</h2>
        <div class="login-underline"></div>
        <?php if ($m = flash('error')): ?>
            <div class="alert alert-danger"><?= e($m) ?></div>
        <?php endif; ?>
        <?php if ($m = flash('success')): ?>
            <div class="alert alert-success"><?= e($m) ?></div>
        <?php endif; ?>
        <form method="post">
            <?= Csrf::field() ?>
            <div class="mb-3">
                <label for="email">E-mel Pentadbir</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="fa-solid fa-shield-halved"></i></span>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email" autofocus>
                </div>
            </div>
            <div class="mb-3">
                <label for="password">Kata Laluan</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                </div>
            </div>
            <button class="login-btn mt-2" type="submit"><i class="fa-solid fa-right-to-bracket me-2"></i>Log Masuk Pentadbir</button>
        </form>
    </div>
</div>
</body>
</html>