<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config/bootstrap.php';
Auth::requireLogin();
if(is_post()){
    Csrf::verify();
    $current=(string)input('current_password');$new=(string)input('password');$confirm=(string)input('password_confirmation');$errors=[];
    $stmt=db()->prepare('SELECT password_hash FROM users WHERE id=?');$stmt->execute([Auth::id()]);$hash=(string)$stmt->fetchColumn();
    if(!password_verify($current,$hash))$errors[]='Kata laluan semasa tidak tepat.';
    if(strlen($new)<12)$errors[]='Kata laluan baharu mesti sekurang-kurangnya 12 aksara.';
    if($new!==$confirm)$errors[]='Pengesahan kata laluan baharu tidak sepadan.';
    if(hash_equals($current,$new))$errors[]='Kata laluan baharu mesti berbeza.';
    if(!$errors){db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),Auth::id()]);audit(Auth::id(),'change_password','user',Auth::id());flash('success','Kata laluan berjaya ditukar.');redirect('auth/password.php');}
    flash('error',implode(' ',$errors));redirect('auth/password.php');
}
$pageTitle='Tukar Kata Laluan';$activePage='password';require BASE_PATH.'/partials/head.php';require BASE_PATH.'/partials/sidebar.php';require BASE_PATH.'/partials/flash.php';
?>
<section class="dashboard-header"><div class="container text-center"><h1>Tukar Kata Laluan</h1><p>Gunakan kata laluan unik sekurang-kurangnya 12 aksara.</p></div></section><section class="container py-5"><div class="card shadow-sm mx-auto" style="max-width:620px"><div class="card-body"><form method="post"><?= Csrf::field() ?><div class="mb-3"><label class="form-label">Kata Laluan Semasa</label><input type="password" name="current_password" class="form-control" required autocomplete="current-password"></div><div class="mb-3"><label class="form-label">Kata Laluan Baharu</label><input type="password" name="password" class="form-control" minlength="12" required autocomplete="new-password"></div><div class="mb-3"><label class="form-label">Ulang Kata Laluan Baharu</label><input type="password" name="password_confirmation" class="form-control" minlength="12" required autocomplete="new-password"></div><button class="btn btn-primary w-100">Simpan Kata Laluan</button></form></div></div></section><?php require BASE_PATH.'/partials/footer.php';?>
