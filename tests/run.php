<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/env.php';
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/session.php';
require_once BASE_PATH . '/config/upload.php';
require_once BASE_PATH . '/app/helpers.php';
require_once BASE_PATH . '/app/Auth.php';
require_once BASE_PATH . '/app/Repository.php';
require_once BASE_PATH . '/app/CompetitionService.php';

$failures=[];
function check(bool $condition,string $message): void { global $failures; if(!$condition)$failures[]=$message; }
function data(string $name): array { return json_decode((string)file_get_contents(BASE_PATH.'/database/data/'.$name.'.json'),true,512,JSON_THROW_ON_ERROR); }

$zones=data('zones');$skills=data('skills');$venues=data('venues');$briefings=data('briefings');$documents=data('documents');
check(count($zones)===10,'Zon mesti berjumlah 10.');
check(count($skills)===22,'Bidang mesti berjumlah 22.');
check(count($venues)===9,'Lokasi mesti berjumlah 9.');
check(count($briefings)===44,'Penataran mesti berjumlah 44.');
check(count(array_filter($documents,fn($d)=>$d['category']==='national_schedule'))===19,'Dokumen jadual bidang yang dibekalkan mesti berjumlah 19.');

$venueSkills=[];foreach($venues as $v)foreach($v['skills'] as $slug)$venueSkills[]=$slug;
check(count($venueSkills)===22,'Pemetaan lokasi mesti meliputi 22 bidang.');
check(count(array_unique($venueSkills))===22,'Setiap bidang mesti mempunyai tepat satu lokasi.');

$bySlug=[];foreach($skills as $s)$bySlug[$s['slug']]=$s;
check(($bySlug['beauty-therapy']['assistant_count']??0)===1 && ($bySlug['beauty-therapy']['model_count']??0)===1,'Beauty Therapy mesti mempunyai 1 pembantu dan 1 model.');
check(($bySlug['cooking']['assistant_count']??0)===1 && ($bySlug['cooking']['model_count']??0)===0,'Cooking mesti mempunyai 1 pembantu.');
check(($bySlug['landscape-gardening']['assistant_count']??0)===1,'Landscape Gardening mesti mempunyai 1 pembantu.');
check(CompetitionService::medalForScore(90,'heavy')==='Emas','Ambang Emas Heavy tidak tepat.');
check(CompetitionService::medalForScore(89.99,'heavy')==='Perak','Ambang Perak Heavy tidak tepat.');
check(CompetitionService::medalForScore(95,'light')==='Emas','Ambang Emas Light tidak tepat.');
check(CompetitionService::medalForScore(74.99,'light')===null,'Markah Light di bawah Medallion mesti tiada anugerah.');
foreach($documents as $d){$path=BASE_PATH.'/'.$d['file_path'];check(is_file($path),'Fail dokumen hilang: '.$d['file_path']);if(is_file($path))check(hash_file('sha256',$path)===$d['checksum_sha256'],'Checksum dokumen tidak sepadan: '.$d['file_path']);}

// New tests for restructurings
check(is_file(BASE_PATH . '/admin/dashboard.php'), 'admin/dashboard.php tidak wujud.');
check(is_file(BASE_PATH . '/admin/pengguna.php'), 'admin/pengguna.php tidak wujud.');
check(is_file(BASE_PATH . '/admin/pegawai_teknikal.php'), 'admin/pegawai_teknikal.php tidak wujud.');
check(is_file(BASE_PATH . '/admin/keputusan.php'), 'admin/keputusan.php tidak wujud.');
check(is_file(BASE_PATH . '/admin/login.php'), 'admin/login.php tidak wujud.');
check(is_file(BASE_PATH . '/admin/tetapan.php'), 'admin/tetapan.php tidak wujud.');
check(is_file(BASE_PATH . '/pegawai_teknikal/daftar_pegawai_teknikal.php'), 'pegawai_teknikal/daftar_pegawai_teknikal.php tidak wujud.');
check(is_file(BASE_PATH . '/pegawai_teknikal/soalan.php'), 'pegawai_teknikal/soalan.php tidak wujud.');
check(is_file(BASE_PATH . '/pegawai_teknikal/daftar_jurulatih.php'), 'pegawai_teknikal/daftar_jurulatih.php tidak wujud.');
check(is_file(BASE_PATH . '/pegawai_teknikal/tetapan.php'), 'pegawai_teknikal/tetapan.php tidak wujud.');
check(is_file(BASE_PATH . '/jurulatih/tetapan.php'), 'jurulatih/tetapan.php tidak wujud.');
check(is_file(BASE_PATH . '/jurulatih/register.php'), 'jurulatih/register.php tidak wujud.');
check(is_file(BASE_PATH . '/jurulatih/forgot_password.php'), 'jurulatih/forgot_password.php tidak wujud.');
check(is_file(BASE_PATH . '/jurulatih/soalan.php'), 'jurulatih/soalan.php tidak wujud.');

$adminRes = Auth::attempt((string)env('INITIAL_ADMIN_EMAIL'), (string)env('INITIAL_ADMIN_PASSWORD'), 'admin');
check($adminRes === true, 'Log masuk admin gagal.');
check(Auth::isAdmin() === true, 'Auth::isAdmin() tidak tepat.');

$uCols = db()->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
check(in_array('image_path', $uCols, true), 'users.image_path tiada.');
check(in_array('shirt_size', $uCols, true), 'users.shirt_size tiada.');
check(in_array('skill_id', $uCols, true), 'users.skill_id tiada.');
check(in_array('ic_number', $uCols, true), 'users.ic_number tiada.');

$rCols = db()->query('SHOW COLUMNS FROM registrations')->fetchAll(PDO::FETCH_COLUMN);
check(in_array('image_path', $rCols, true), 'registrations.image_path tiada.');
check(in_array('shirt_size', $rCols, true), 'registrations.shirt_size tiada.');

$tables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
check(in_array('system_settings', $tables, true), 'Jadual system_settings tiada.');
check(is_bool(is_pt_score_entry_enabled()), 'Fungsi is_pt_score_entry_enabled() gagal.');

if($failures){fwrite(STDERR,"UJIAN GAGAL\n- ".implode("\n- ",$failures)."\n");exit(1);}
echo "Semua ujian lulus: 10 zon, 22 bidang, 9 lokasi, 44 penataran, peraturan pembantu, ambang anugerah, peranan admin, struktur portal baharu dan integriti dokumen.\n";

