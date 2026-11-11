<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
if (!is_post()) { http_response_code(405); exit('Method not allowed'); }
Csrf::verify();
Auth::logout();
flash('success','Anda telah log keluar.');
redirect('index.php');
