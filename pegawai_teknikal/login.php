<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (Auth::check()) {
    redirect((Auth::user()['role'] === 'coach') ? 'jurulatih/dashboard.php' : 'pegawai_teknikal/dashboard.php');
}

if (is_post()) {
    Csrf::verify();
    if (Auth::attempt(trim((string)input('email')), (string)input('password'), ['technical', 'admin'])) {
        flash('success', 'Selamat datang ke dashboard pegawai teknikal.');
        redirect('pegawai_teknikal/dashboard.php');
    }
    flash('error', 'Log masuk gagal. Sila semak e-mel, kata laluan dan status akaun.');
}

$pageTitle = 'Log Masuk Pegawai Teknikal';
require BASE_PATH . '/partials/head.php';
?>

<div class="login-wrapper">
    <a href="<?= e(url('jawatan.php')) ?>" class="login-back">
        <i class="fa-solid fa-arrow-left"></i>Kembali ke Pemilihan Peranan
    </a>
    <div class="login-card">
        <div class="login-header-block">
            <img src="<?= e(url('assets/images/Logo KVSkills.png')) ?>" class="login-logo" alt="Logo KVSkills">
            <div class="d-inline-flex align-items-center gap-2 px-3 py-1 mb-2 rounded-pill" style="background: #f1f5f9; border: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #475569;">
                <i class="fa-solid fa-medal text-warning"></i>
                <span>Kementerian Pendidikan Malaysia &middot; BPLTV</span>
            </div>
            <div class="login-brand">KVSKILLS HUB MALAYSIA</div>
            <p class="text-muted small mb-0">Portal Rasmi Pertandingan Kemahiran TVET Kebangsaan</p>
        </div>

        <h2 class="h5 fw-bold text-dark mb-1">Log Masuk Pegawai Teknikal</h2>
        <p class="text-muted small mb-3">Sila masukkan e-mel rasmi dan kata laluan pegawai berautoriti untuk mengakses konsol operasi.</p>
        <div class="login-underline mb-4"></div>

        <?php if ($m = flash('error')): ?>
            <div class="alert alert-danger py-2 small mb-3"><?= e($m) ?></div>
        <?php endif; ?>
        <?php if ($m = flash('success')): ?>
            <div class="alert alert-success py-2 small mb-3"><?= e($m) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= Csrf::field() ?>
            <div class="mb-3">
                <label for="email" class="form-label">E-mel Rasmi Pegawai</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email" autofocus placeholder="pt.bidang@kvskills.my">
                </div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Kata Laluan</label>
                <div class="input-group login-input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password" placeholder="Masukkan kata laluan">
                </div>
            </div>
            <button class="login-btn mt-2" type="submit">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Log Masuk Pegawai
            </button>
            <div class="text-center mt-3 small">
                <a href="<?= e(url('pegawai_teknikal/register.php')) ?>" class="text-muted">
                    <i class="fa-solid fa-user-shield me-1"></i>Pendaftaran pegawai berautoriti
                </a>
            </div>
        </form>
    </div>
</div>
</body>
</html>

