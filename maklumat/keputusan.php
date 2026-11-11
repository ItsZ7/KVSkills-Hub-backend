<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config/bootstrap.php';
$q=trim((string)($_GET['q']??''));$skillId=(int)($_GET['skill_id']??0);$skills=all_skills();
$sql="SELECT res.*,r.participant_name,r.institution,s.name AS skill_name,z.name AS zone_name FROM results res JOIN registrations r ON r.id=res.registration_id JOIN skills s ON s.id=r.skill_id JOIN zones z ON z.id=r.zone_id WHERE res.is_published=1 AND r.status='approved'";$params=[];
if($q!==''){$sql.=' AND (r.participant_name LIKE ? OR r.institution LIKE ? OR s.name LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}if($skillId){$sql.=' AND r.skill_id=?';$params[]=$skillId;}$sql.=' ORDER BY s.sort_order,COALESCE(res.rank_position,999),res.score DESC';$stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
$pageTitle='Keputusan';$activePage='keputusan';require BASE_PATH.'/partials/head.php';require BASE_PATH.'/partials/public_navbar.php';
?>
<section class="dashboard-header">
    <div class="container text-center">
        <h1>Keputusan Rasmi Pertandingan</h1>
        <p>Keputusan rasmi, kedudukan peserta dan agihan anugerah medal yang telah disahkan dan diterbitkan.</p>
    </div>
</section>

<section class="container py-4">
    <!-- SEARCH & FILTER -->
    <form method="get" class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-lg-7">
                    <label class="form-label fw-bold">Carian Peserta / Kolej / Bidang</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input name="q" value="<?= e($q) ?>" class="form-control border-start-0" placeholder="Cari nama pemenang, kolej vokasional atau bidang kemahiran...">
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
                <div class="col-lg-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="fa-solid fa-filter me-1"></i> Tapis
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- RESULTS TABLE -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-medal text-warning fs-5"></i>
                <h2 class="h5 mb-0 fw-bold text-dark">Papan Kedudukan &amp; Keputusan Rasmi</h2>
            </div>
            <?php if ($rows): ?>
                <span class="badge bg-light text-dark border">
                    <?= count($rows) ?> Rekod Pemenang
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (!$rows): ?>
                <div class="py-5 text-center my-3">
                    <div class="d-inline-flex p-3 rounded-circle bg-light mb-3">
                        <i class="fa-solid fa-trophy text-muted" style="font-size: 42px; opacity: 0.4;"></i>
                    </div>
                    <h3 class="h5 fw-bold text-dark mb-2">Tiada Keputusan Rasmi Diterbitkan</h3>
                    <p class="text-muted small mb-0" style="max-width: 500px; margin: 0 auto;">
                        Keputusan rasmi bagi edisi ini belum diterbitkan oleh Pegawai Teknikal / Pentadbir atau tiada rekod yang sepadan dengan tapisan carian anda.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive border-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 90px;">Kedudukan</th>
                                <th>Peserta Pemenang</th>
                                <th>Kolej / Zon</th>
                                <th>Bidang Kemahiran</th>
                                <th class="text-center">Kategori</th>
                                <th class="text-center">Anugerah Medal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($rows as $r): ?>
                                <tr>
                                    <td class="text-center tabular-nums">
                                        <?php if ($r['rank_position'] == 1): ?>
                                            <span class="badge rounded-circle p-2 bg-warning text-dark fs-6" style="width: 36px; height: 36px; display: inline-grid; place-items: center;" title="Juara (Kedudukan 1)">1</span>
                                        <?php elseif ($r['rank_position'] == 2): ?>
                                            <span class="badge rounded-circle p-2 bg-secondary text-white fs-6" style="width: 36px; height: 36px; display: inline-grid; place-items: center;" title="Naib Juara (Kedudukan 2)">2</span>
                                        <?php elseif ($r['rank_position'] == 3): ?>
                                            <span class="badge rounded-circle p-2 bg-danger text-white fs-6" style="width: 36px; height: 36px; display: inline-grid; place-items: center;" title="Tempat Ketiga (Kedudukan 3)">3</span>
                                        <?php else: ?>
                                            <span class="fw-bold text-muted fs-6"><?= $r['rank_position'] ? e($r['rank_position']) : '-' ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="text-dark d-block"><?= e($r['participant_name']) ?></strong>
                                        <small class="text-muted"><i class="fa-solid fa-award me-1 text-warning"></i>Peserta Kebangsaan</small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark d-block"><?= e($r['institution']) ?></span>
                                        <small class="text-muted"><i class="fa-solid fa-map-pin me-1 text-danger"></i><?= e($r['zone_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="text-slate-800"><?= e($r['skill_name']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= ($r['skill_category'] ?? '') === 'heavy' ? 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25' : 'bg-info bg-opacity-10 text-info border border-info border-opacity-25' ?> px-2 py-1">
                                            <?= ($r['skill_category'] ?? '') === 'heavy' ? 'Heavy Skill' : 'Light Skill' ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= e(CompetitionService::medalBadge($r['award'])) ?> px-3 py-2 fw-bold" style="letter-spacing: 0.03em;">
                                            <i class="fa-solid fa-medal me-1"></i> <?= e($r['award']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- AWARD THRESHOLDS EXPLANATION CARD -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <i class="fa-solid fa-scale-balanced text-primary fs-5"></i>
                <h2 class="h5 mb-0 fw-bold text-dark">Ambang Pemarkahan &amp; Anugerah Rasmi</h2>
            </div>
            <p class="text-muted small mb-3">Piawaian ambang kelayakan pingat pertandingan berdasarkan ketetapan rasmi Jawatankuasa Teknikal KVSkills Kebangsaan:</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <strong class="text-dark d-block mb-1"><i class="fa-solid fa-wrench text-primary me-1"></i> Kategori Heavy Skills</strong>
                        <p class="mb-0 small text-muted">
                            <span class="badge bg-warning text-dark me-1">Emas</span> 90.00 &ndash; 100.00 &middot; 
                            <span class="badge bg-secondary text-white me-1">Perak</span> 80.00 &ndash; 89.99 &middot; 
                            <span class="badge bg-danger text-white me-1">Gangsa</span> 70.00 &ndash; 79.99 &middot; 
                            <span class="badge bg-info text-dark">Medallion</span> 65.00 &ndash; 69.99
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <strong class="text-dark d-block mb-1"><i class="fa-solid fa-feather text-info me-1"></i> Kategori Light Skills</strong>
                        <p class="mb-0 small text-muted">
                            <span class="badge bg-warning text-dark me-1">Emas</span> 95.00 &ndash; 100.00 &middot; 
                            <span class="badge bg-secondary text-white me-1">Perak</span> 90.00 &ndash; 94.99 &middot; 
                            <span class="badge bg-danger text-white me-1">Gangsa</span> 85.00 &ndash; 89.99 &middot; 
                            <span class="badge bg-info text-dark">Medallion</span> 75.00 &ndash; 84.99
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require BASE_PATH.'/partials/footer.php'; ?>
