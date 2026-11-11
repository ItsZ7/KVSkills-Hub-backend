<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
$id=(int)($_GET['id']??0);
$stmt=db()->prepare('SELECT * FROM documents WHERE id=? AND is_active=1');
$stmt->execute([$id]);
$doc=$stmt->fetch();
if (!$doc) { http_response_code(404); exit('Dokumen tidak ditemui.'); }
if ($doc['visibility']==='authenticated' && !Auth::check()) { http_response_code(403); exit('Log masuk diperlukan.'); }
if ($doc['visibility']==='private' && !in_array(Auth::user()['role']??'', ['technical','admin'], true)) { http_response_code(403); exit('Akses tidak dibenarkan.'); }
$path=BASE_PATH . '/' . ltrim((string)$doc['file_path'],'/');
$real=realpath($path); $root=realpath(STORAGE_PATH);
if (!$real || !$root || !str_starts_with($real,$root . DIRECTORY_SEPARATOR) || !is_file($real)) { http_response_code(404); exit('Fail tidak tersedia.'); }
if (!empty($doc['checksum_sha256']) && !hash_equals((string)$doc['checksum_sha256'], hash_file('sha256',$real))) { http_response_code(500); exit('Integriti fail gagal disahkan.'); }
header('Content-Type: '. $doc['mime_type']);
header('Content-Length: '. filesize($real));
header('X-Content-Type-Options: nosniff');
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode((string)$doc['original_filename']));
readfile($real);
exit;
