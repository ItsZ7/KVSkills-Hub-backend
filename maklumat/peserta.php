<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config/bootstrap.php';
$q=trim((string)($_GET['q']??''));$skillId=(int)($_GET['skill_id']??0);$zoneId=(int)($_GET['zone_id']??0);
$skills=all_skills();$zones=all_zones();
$sql="SELECT r.id,r.participant_name,r.shirt_size,r.institution,s.name AS skill_name,z.name AS zone_name,u.full_name AS coach_name FROM registrations r JOIN skills s ON s.id=r.skill_id JOIN zones z ON z.id=r.zone_id JOIN users u ON u.id=r.coach_user_id WHERE r.status='approved'";$params=[];
if($q!==''){$sql.=' AND (r.participant_name LIKE ? OR r.institution LIKE ? OR u.full_name LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}if($skillId){$sql.=' AND r.skill_id=?';$params[]=$skillId;}if($zoneId){$sql.=' AND r.zone_id=?';$params[]=$zoneId;}$sql.=' ORDER BY s.sort_order,z.sort_order,r.participant_name';
$stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
$pageTitle='Senarai Peserta';$activePage='peserta';require BASE_PATH.'/partials/head.php';require BASE_PATH.'/partials/public_navbar.php';
?>
<section class="dashboard-header">
    <div class="container text-center">
        <h1>Senarai Peserta KVSkills</h1>
        <p>Direktori rasmi kontinjen dan peserta yang telah disahkan kelayakan bertanding peringkat kebangsaan.</p>
    </div>
</section>

<section class="container py-4">
    <!-- SEARCH & FILTER -->
    <form method="get" class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-lg-5">
                    <label class="form-label fw-bold">Carian Peserta / Institusi</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input name="q" value="<?= e($q) ?>" class="form-control border-start-0" placeholder="Cari nama peserta, kolej vokasional atau jurulatih...">
                    </div>
                </div>
                <div class="col-lg-3">
                    <label class="form-label fw-bold">Bidang Kemahiran</label>
                    <select name="skill_id" class="form-select">
                        <option value="0">Semua Bidang (22)</option>
                        <?php foreach($skills as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= selected($skillId, $s['id']) ?>>
                                <?= e($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-bold">Zon</label>
                    <select name="zone_id" class="form-select">
                        <option value="0">Semua Zon (10)</option>
                        <?php foreach($zones as $z): ?>
                            <option value="<?= (int)$z['id'] ?>" <?= selected($zoneId, $z['id']) ?>>
                                <?= e($z['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="fa-solid fa-filter me-1"></i> Tapis
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- RESULTS TABLE CARD -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-user-check text-primary fs-5"></i>
                <h2 class="h5 mb-0 fw-bold text-dark">Kontinjen Peserta Disahkan</h2>
            </div>
            <span class="badge bg-light text-dark border">
                <?= count($rows) ?> Peserta
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">Bil.</th>
                            <th>Nama Peserta</th>
                            <th>Bidang Kemahiran</th>
                            <th class="text-center">Saiz Baju</th>
                            <th>Kolej / Zon</th>
                            <th>Jurulatih Pembimbing</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rows as $i => $r): ?>
                            <tr>
                                <td class="text-center text-muted tabular-nums fw-medium"><?= $i + 1 ?></td>
                                <td>
                                    <strong class="text-dark d-block"><?= e($r['participant_name']) ?></strong>
                                    <small class="text-muted"><i class="fa-regular fa-id-badge me-1"></i>Calon Rasmi</small>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                        <?= e($r['skill_name']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-light border text-secondary px-2 py-1 font-mono">
                                        <?= e($r['shirt_size'] ?: '-') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark d-block"><?= e($r['institution']) ?></span>
                                    <small class="text-muted"><i class="fa-solid fa-map-pin me-1 text-danger"></i><?= e($r['zone_name']) ?></small>
                                </td>
                                <td>
                                    <span class="text-slate-800"><i class="fa-solid fa-user-tie text-secondary me-1"></i><?= e($r['coach_name']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(!$rows): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fa-solid fa-users-slash text-secondary mb-2 d-block" style="font-size: 36px; opacity: 0.35;"></i>
                                    Tiada peserta yang sepadan dengan carian atau tapisan ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php require BASE_PATH.'/partials/footer.php'; ?>
