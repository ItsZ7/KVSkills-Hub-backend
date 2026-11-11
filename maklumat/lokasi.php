<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config/bootstrap.php';
$competition=active_competition();
$sql="SELECT v.id, v.code, v.name, v.address, v.map_url, GROUP_CONCAT(DISTINCT s.name ORDER BY s.sort_order SEPARATOR '||') AS skills 
      FROM venues v 
      LEFT JOIN competition_skills cs ON cs.venue_id=v.id AND cs.competition_id=? 
      LEFT JOIN skills s ON s.id=cs.skill_id 
      WHERE v.is_active=1 
      GROUP BY v.id 
      ORDER BY v.code";
$stmt=db()->prepare($sql);
$stmt->execute([(int)($competition['id']??0)]);
$venues=$stmt->fetchAll();
$pageTitle='Lokasi Pertandingan';
$activePage='lokasi';
require BASE_PATH.'/partials/head.php';
require BASE_PATH.'/partials/public_navbar.php';
?>
<section class="dashboard-header">
    <div class="container text-center">
        <h1>Pusat &amp; Lokasi Pertandingan</h1>
        <p>9 lokasi rasmi Kolej Vokasional hos di Zon Melaka &amp; Negeri Sembilan bagi kesemua 22 bidang kemahiran kebangsaan.</p>
    </div>
</section>

<section class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h5 mb-0 fw-bold text-dark">Senarai Pusat Pertandingan</h2>
            <p class="text-muted small mb-0">Klik pada panduan arah untuk navigasi terus melalui aplikasi peta.</p>
        </div>
        <span class="badge bg-primary px-3 py-2 fs-6">
            <i class="fa-solid fa-map-location-dot me-1"></i> <?= count($venues) ?> Pusat Hos
        </span>
    </div>

    <div class="row g-4">
        <?php foreach($venues as $venue): 
            $items = array_filter(explode('||', (string)$venue['skills']));
            $mapsUrl = !empty($venue['map_url']) 
                ? (string)$venue['map_url'] 
                : 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode((string)$venue['name']);
        ?>
        <div class="col-lg-6">
            <div class="card lokasi-card border-0 shadow-sm h-100 d-flex flex-column">
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="venue-code flex-shrink-0"><?= e($venue['code']) ?></div>
                        <div class="flex-grow-1">
                            <h3 class="h5 mb-1 text-dark fw-bold"><?= e($venue['name']) ?></h3>
                            <?php if(!empty($venue['address'])): ?>
                                <p class="text-muted small mb-2"><i class="fa-solid fa-location-dot me-1 text-danger"></i><?= e($venue['address']) ?></p>
                            <?php endif; ?>
                            <span class="badge bg-light text-dark border">
                                <i class="fa-solid fa-layer-group me-1 text-primary"></i><?= count($items) ?> Bidang Kemahiran
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-4 flex-grow-1">
                        <div class="small fw-bold text-slate-700 mb-2">Bidang Kemahiran Dipertandingkan:</div>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach($items as $item): ?>
                                <span class="badge text-bg-light border text-dark fw-medium px-2 py-1" style="font-size: 12px;">
                                    <?= e($item) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="pt-3 border-top mt-auto d-flex justify-content-between align-items-center">
                        <span class="small text-muted"><i class="fa-solid fa-building-columns me-1"></i>Pusat Rasmi</span>
                        <a href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary" aria-label="Dapatkan arah ke <?= e($venue['name']) ?> melalui Google Maps">
                            <i class="fa-solid fa-diamond-turn-right me-1"></i> Panduan Arah (Peta)
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require BASE_PATH.'/partials/footer.php'; ?>
