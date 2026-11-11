<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Pemasangan pangkalan data hanya dibenarkan melalui CLI.\n");
}

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';

function read_json(string $name): array
{
    $path = BASE_PATH . '/database/data/' . $name . '.json';
    $data = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    return is_array($data) ? $data : [];
}

try {
    $pdo = db();
    if (in_array('--auth-limits-only', $argv, true)) {
        $pdo->exec(file_get_contents(BASE_PATH . '/database/auth_limits.sql'));
        echo "Jadual had akses tersedia.\n";
        exit(0);
    }
    $schema=(string)file_get_contents(BASE_PATH . '/database/schema.sql');
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [] as $statement) {
        $statement=trim($statement);
        if ($statement !== '') $pdo->exec($statement);
    }
    $pdo->exec(file_get_contents(BASE_PATH . '/database/auth_limits.sql'));
    // Migration helper for existing databases
    $colsUsers = $pdo->query("SHOW COLUMNS FROM users LIKE 'image_path'")->fetchAll();
    if (empty($colsUsers)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN image_path VARCHAR(500) NULL AFTER institution");
    }
    $colsUsersShirt = $pdo->query("SHOW COLUMNS FROM users LIKE 'shirt_size'")->fetchAll();
    if (empty($colsUsersShirt)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN shirt_size VARCHAR(10) NULL AFTER image_path");
    }
    $colsUsersSkill = $pdo->query("SHOW COLUMNS FROM users LIKE 'skill_id'")->fetchAll();
    if (empty($colsUsersSkill)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN skill_id BIGINT UNSIGNED NULL AFTER zone_id");
    }
    $colsUsersIc = $pdo->query("SHOW COLUMNS FROM users LIKE 'ic_number'")->fetchAll();
    if (empty($colsUsersIc)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN ic_number VARCHAR(20) NULL AFTER phone");
    }
    $colsRegs = $pdo->query("SHOW COLUMNS FROM registrations LIKE 'image_path'")->fetchAll();
    if (empty($colsRegs)) {
        $pdo->exec("ALTER TABLE registrations ADD COLUMN image_path VARCHAR(500) NULL AFTER institution");
    }
    $colsRegsShirt = $pdo->query("SHOW COLUMNS FROM registrations LIKE 'shirt_size'")->fetchAll();
    if (empty($colsRegsShirt)) {
        $pdo->exec("ALTER TABLE registrations ADD COLUMN shirt_size VARCHAR(10) NULL AFTER image_path");
    }
    $colsRegsIc = $pdo->query("SHOW COLUMNS FROM registrations LIKE 'ic_number'")->fetchAll();
    if (empty($colsRegsIc)) {
        $pdo->exec("ALTER TABLE registrations ADD COLUMN ic_number VARCHAR(20) NULL AFTER participant_name");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(80) PRIMARY KEY,
            setting_value TEXT NULL,
            description VARCHAR(255) NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        INSERT INTO system_settings (setting_key, setting_value, description)
        VALUES ('pt_score_entry_enabled', '1', 'Kebenaran kemasukan markah oleh Pegawai Teknikal (1 = Dibuka, 0 = Dikunci)')
        ON DUPLICATE KEY UPDATE setting_key = setting_key
    ");

    $pdo->beginTransaction();

    $zoneStmt=$pdo->prepare('INSERT INTO zones(code,name,sort_order,is_active) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name),sort_order=VALUES(sort_order),is_active=1');
    foreach(read_json('zones') as $z) $zoneStmt->execute([$z['code'],$z['name'],$z['sort_order']]);

    $skillStmt=$pdo->prepare('INSERT INTO skills(code,slug,name,sort_order,assistant_count,model_count,assistant_note,is_active) VALUES(?,?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name),sort_order=VALUES(sort_order),assistant_count=VALUES(assistant_count),model_count=VALUES(model_count),assistant_note=VALUES(assistant_note),is_active=1');
    foreach(read_json('skills') as $s) $skillStmt->execute([$s['code'],$s['slug'],$s['name'],$s['sort_order'],$s['assistant_count'],$s['model_count'],$s['assistant_note']]);

    $c=read_json('competition');
    $stmt=$pdo->prepare('INSERT INTO competitions(code,name,level,start_date,end_date,host_zone,status,description) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),start_date=VALUES(start_date),end_date=VALUES(end_date),host_zone=VALUES(host_zone),status=VALUES(status),description=VALUES(description)');
    $stmt->execute([$c['code'],$c['name'],$c['level'],$c['start_date'],$c['end_date'],$c['host_zone'],$c['status'],$c['description']]);
    $competitionId=(int)$pdo->query("SELECT id FROM competitions WHERE code=".$pdo->quote($c['code']))->fetchColumn();

    $venueStmt=$pdo->prepare('INSERT INTO venues(code,name,is_active) VALUES(?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1');
    foreach(read_json('venues') as $v) $venueStmt->execute([$v['code'],$v['name']]);
    $skillMap=$pdo->query('SELECT slug,id FROM skills')->fetchAll(PDO::FETCH_KEY_PAIR);
    $venueMap=$pdo->query('SELECT code,id FROM venues')->fetchAll(PDO::FETCH_KEY_PAIR);
    $csStmt=$pdo->prepare('INSERT INTO competition_skills(competition_id,skill_id,venue_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE venue_id=VALUES(venue_id)');
    foreach(read_json('venues') as $v) foreach($v['skills'] as $slug) $csStmt->execute([$competitionId,(int)$skillMap[$slug],(int)$venueMap[$v['code']]]);

    $briefStmt=$pdo->prepare('INSERT INTO briefings(skill_id,level,briefing_date,start_time,meeting_url,is_published) VALUES(?,?,?,?,?,1) ON DUPLICATE KEY UPDATE meeting_url=VALUES(meeting_url),is_published=1');
    foreach(read_json('briefings') as $b) $briefStmt->execute([(int)$skillMap[$b['skill_slug']],$b['level'],$b['briefing_date'],$b['start_time'],$b['meeting_url']]);

    $docStmt=$pdo->prepare('INSERT INTO documents(skill_id,title,category,file_path,original_filename,mime_type,file_size,checksum_sha256,visibility,is_active) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),file_size=VALUES(file_size),checksum_sha256=VALUES(checksum_sha256),visibility=VALUES(visibility),is_active=VALUES(is_active)');
    foreach(read_json('documents') as $d) {
        $skillId=$d['skill_slug'] ? (int)$skillMap[$d['skill_slug']] : null;
        $docStmt->execute([$skillId,$d['title'],$d['category'],$d['file_path'],$d['original_filename'],$d['mime_type'],$d['file_size'],$d['checksum_sha256'],$d['visibility'],$d['is_active']]);
    }

    $ruleStmt=$pdo->prepare('INSERT INTO award_rules(category,award,min_score,max_score) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE min_score=VALUES(min_score),max_score=VALUES(max_score)');
    foreach(read_json('award_rules') as $r) $ruleStmt->execute([$r['category'],$r['award'],$r['min_score'],$r['max_score']]);

    $adminEmail=mb_strtolower(trim((string)env('INITIAL_ADMIN_EMAIL','')));
    $adminPassword=(string)env('INITIAL_ADMIN_PASSWORD','');
    $adminName=trim((string)env('INITIAL_ADMIN_NAME','Pentadbir KVSkills'));
    if ($adminEmail === '' || strlen($adminPassword) < 12) {
        throw new RuntimeException('Tetapkan INITIAL_ADMIN_EMAIL dan INITIAL_ADMIN_PASSWORD (minimum 12 aksara) dalam .env.');
    }
    $adminStmt=$pdo->prepare("INSERT INTO users(role,status,full_name,email,password_hash,approved_at) VALUES('admin','active',?,?,?,NOW()) ON DUPLICATE KEY UPDATE id=id");
    $adminStmt->execute([$adminName,$adminEmail,password_hash($adminPassword,PASSWORD_DEFAULT)]);

    $pdo->commit();
    echo "Pemasangan berjaya. Data zon, 22 bidang, lokasi, jadual penataran, dokumen dan akaun awal admin telah dimasukkan.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,"Pemasangan gagal: {$e->getMessage()}\n");
    exit(1);
}
