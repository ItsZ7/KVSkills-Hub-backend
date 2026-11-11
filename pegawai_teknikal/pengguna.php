<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
Auth::requireLogin(['technical', 'admin']);

if (Auth::role() === 'admin') {
    redirect('admin/pengguna.php');
}

flash('error', 'Modul pengurusan pengguna kini di bawah kawalan Pentadbir Sistem (Admin) sahaja.');
redirect('pegawai_teknikal/dashboard.php');