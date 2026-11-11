<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once BASE_PATH . '/app/CoachRecovery.php';

header('Cache-Control: no-store');
$token = $_POST['token'] ?? $_GET['token'] ?? '';
$token = is_string($token) ? $token : 'invalid';
$error = '';
if (is_post()) {
    Csrf::verify();
    if ($token === '') {
        $email = $_POST['email'] ?? '';
        CoachRecovery::request(is_string($email) ? mb_strtolower(trim($email)) : '');
        flash('success', 'Jika akaun anda layak, pautan pemulihan akan dihantar ke e-mel berdaftar.');
        redirect('jurulatih/forgot_password.php');
    }
    $password = $_POST['new_password'] ?? '';
    if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0") || $password !== ($_POST['confirm_password'] ?? null)) {
        $error = 'Gunakan kata laluan 12 hingga 72 bait dan pastikan pengesahan sepadan.';
    } elseif (CoachRecovery::redeem($token, $password)) {
        flash('success', 'Kata laluan telah dikemas kini. Sila log masuk semula.');
        redirect('jurulatih/login.php');
    } else {
        $error = 'Pautan pemulihan tidak sah atau telah tamat. Mohon pautan baharu.';
    }
}
$pageTitle = 'Lupa Kata Laluan Jurulatih';
require BASE_PATH . '/partials/head.php';
?>
<main class="login-wrapper">
    <a href="<?= e(url('jurulatih/login.php')) ?>" class="login-back">Kembali ke Log Masuk</a>
    <div class="login-card">
        <h1 class="h2">Lupa Kata Laluan Jurulatih</h1>
        <?php if ($error): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
        <?php if ($message = flash('success')): ?><p role="status"><?= e($message) ?></p><?php endif; ?>
        <form method="post" action="<?= e(url('jurulatih/forgot_password.php')) ?>">
            <?= Csrf::field() ?>
            <?php if ($token === ''): ?>
                <label for="email">E-mel berdaftar</label>
                <input id="email" class="form-control" type="email" name="email" required autocomplete="email">
                <button class="login-btn mt-3">Hantar pautan pemulihan</button>
            <?php else: ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <label for="new_password">Kata laluan baharu</label>
                <input id="new_password" class="form-control" type="password" name="new_password" minlength="12" required autocomplete="new-password">
                <label for="confirm_password">Sahkan kata laluan baharu</label>
                <input id="confirm_password" class="form-control" type="password" name="confirm_password" minlength="12" required autocomplete="new-password">
                <button class="login-btn mt-3">Simpan kata laluan</button>
                <a href="<?= e(url('jurulatih/forgot_password.php')) ?>">Mohon pautan baharu</a>
            <?php endif; ?>
        </form>
    </div>
</main>
</body>
</html>
