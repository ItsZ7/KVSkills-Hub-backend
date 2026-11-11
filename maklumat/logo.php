<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$pageTitle  = 'Muat Turun Logo';
$activePage = 'logo';

require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/public_navbar.php';
?>

<section class="dashboard-header">
    <div class="container text-center">
        <h1>Logo Rasmi KVSkills Malaysia</h1>
        <p>Aset penjenamaan rasmi beresolusi tinggi untuk kegunaan dokumen, kain rentang dan promosi kontinjen.</p>
    </div>
</section>

<section class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body p-4 p-md-5">
                    <div class="p-4 bg-light rounded-4 d-inline-block border mb-4">
                        <img src="<?= e(url('assets/images/Logo KVSkills.png')) ?>" class="logo-preview img-fluid" alt="Logo KVSkills Malaysia">
                    </div>
                    <h2 class="h4 fw-bold text-dark mb-1">Logo Vektor Rasmi KVSkills</h2>
                    <p class="text-muted small mb-4">Format PNG Lutsinar (Transparent Background) &middot; Resolusi Tinggi</p>
                    
                    <div class="d-grid gap-2 mb-4">
                        <a href="<?= e(url('assets/images/Logo KVSkills.png')) ?>" download="Logo-KVSkills-Malaysia.png" class="btn btn-primary btn-lg">
                            <i class="fa-solid fa-cloud-arrow-down me-2"></i> Muat Turun Fail PNG (133 KB)
                        </a>
                    </div>

                    <div class="text-start p-3 bg-light rounded-3 border">
                        <strong class="text-dark d-block mb-1 small"><i class="fa-solid fa-circle-check text-success me-1"></i> Garis Panduan Penggunaan:</strong>
                        <ul class="mb-0 ps-3 small text-muted">
                            <li>Jangan ubah nisbah aspek asal atau memutar logo.</li>
                            <li>Gunakan latar belakang berkontras tinggi untuk keterbacaan maksimum.</li>
                            <li>Dikhaskan bagi urusan rasmi berkaitan pertandingan KVSkills peringkat zon dan kebangsaan.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require BASE_PATH . '/partials/footer.php'; ?>
