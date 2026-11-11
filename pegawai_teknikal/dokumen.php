<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config/bootstrap.php';
require_once BASE_PATH.'/config/upload.php';
Auth::requireLogin(['technical','admin']);
$skills=all_skills();

if(is_post()){
    Csrf::verify();
    $action=(string)input('action','upload');
    if($action==='disable'){
        $id=(int)input('id');
        db()->prepare('UPDATE documents SET is_active=0 WHERE id=?')->execute([$id]);
        audit(Auth::id(),'disable','document',$id);
        flash('success','Dokumen dinyahaktifkan.');
        redirect('pegawai_teknikal/dokumen.php');
    }

    $stored=null;
    try{
        $title=trim((string)input('title'));
        $category=(string)input('category');
        $skillId=(int)input('skill_id')?:null;
        $visibility=(string)input('visibility');
        if($title==='' || !in_array($category,['official_letter','briefing','national_schedule','question','result','other'],true) || !in_array($visibility,['public','authenticated','private'],true)){
            throw new RuntimeException('Maklumat dokumen tidak lengkap.');
        }
        $stored=store_document_upload($_FILES['document']??[],[
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
        $stmt=db()->prepare('INSERT INTO documents(skill_id,uploaded_by,title,category,file_path,original_filename,mime_type,file_size,checksum_sha256,visibility,is_active) VALUES(?,?,?,?,?,?,?,?,?,?,1)');
        $stmt->execute([$skillId,Auth::id(),$title,$category,$stored['relative_path'],$stored['original_filename'],$stored['mime_type'],$stored['file_size'],$stored['checksum_sha256'],$visibility]);
        audit(Auth::id(),'upload','document',(int)db()->lastInsertId());
        flash('success','Dokumen berjaya dimuat naik.');
    }catch(Throwable $e){
        if($stored && isset($stored['relative_path'])){
            $orphan=BASE_PATH.'/'.ltrim((string)$stored['relative_path'],'/');
            if(is_file($orphan))@unlink($orphan);
        }
        flash('error', get_class($e) === RuntimeException::class ? $e->getMessage() : 'Dokumen gagal disimpan. Semak maklumat dan cuba lagi.');
    }
    redirect('pegawai_teknikal/dokumen.php');
}
$docs = db()->query('SELECT d.*, s.name AS skill_name, u.full_name AS uploader FROM documents d LEFT JOIN skills s ON s.id=d.skill_id LEFT JOIN users u ON u.id=d.uploaded_by ORDER BY d.created_at DESC')->fetchAll();
$pageTitle  = 'Pengurusan Dokumen';
$activePage = 'technical_dokumen';
require BASE_PATH . '/partials/head.php';
require BASE_PATH . '/partials/sidebar.php';
require BASE_PATH . '/partials/flash.php';
?>
<section class="dashboard-header py-4">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1.5 mb-3 rounded-pill" style="background: #eff6ff; border: 1px solid #bfdbfe; font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #1d4ed8;">
            <i class="fa-solid fa-folder-open text-warning"></i>
            <span>Repositori Dokumen Rasmi &middot; Pegawai Berautoriti</span>
        </div>
        <h1 class="display-6 fw-bold text-dark mb-2">Pengurusan Dokumen Teknikal</h1>
        <p class="lead mx-auto mb-0 text-muted" style="max-width: 720px; font-size: 1.05rem;">
            Muat naik surat siaran, jadual kebangsaan, modul penataran, dan dokumen teknikal KVSkills Hub (Maksimum <?= (int)env('MAX_UPLOAD_MB', 10) ?> MB).
        </p>
    </div>
</section>

<section class="container py-4">
    <!-- UPLOAD CARD -->
    <div class="card border shadow-sm mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-cloud-arrow-up text-primary fs-5"></i>
                <h2 class="h5 mb-0 fw-bold text-dark">Muat Naik Dokumen Baharu</h2>
            </div>
            <a href="<?= e(url('pegawai_teknikal/soalan.php')) ?>" class="btn btn-sm btn-outline-danger shadow-xs">
                <i class="fa-solid fa-file-circle-question me-1"></i> Masukkan Kertas Soalan Projek
            </a>
        </div>
        <div class="card-body p-4">
            <form method="post" enctype="multipart/form-data">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="upload">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-dark mb-1">Tajuk Dokumen <span class="text-danger">*</span></label>
                        <input name="title" class="form-control" placeholder="Cth: Jadual Induk Pertandingan KVSkills 2026" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark mb-1">Kategori Dokumen <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <option value="national_schedule">Jadual Kebangsaan</option>
                            <option value="briefing">Penataran &amp; Taklimat</option>
                            <option value="official_letter">Surat Rasmi / Pekeliling</option>
                            <option value="question">Soalan Pertandingan</option>
                            <option value="result">Keputusan</option>
                            <option value="other">Lain-lain</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-dark mb-1">Tahap Akses <span class="text-danger">*</span></label>
                        <select name="visibility" class="form-select" required>
                            <option value="public">Awam (Semua)</option>
                            <option value="authenticated">Pengguna Log Masuk Sahaja</option>
                            <option value="private">Teknikal Sahaja (Sulit)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-dark mb-1">Bidang Kemahiran (Opsyenal)</label>
                        <select name="skill_id" class="form-select">
                            <option value="0">Umum / Semua Bidang</option>
                            <?php foreach ($skills as $s): ?>
                                <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Pilih "Umum" sekiranya dokumen merangkumi semua bidang.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-dark mb-1">Fail Dokumen <span class="text-danger">*</span></label>
                        <input type="file" name="document" class="form-control" accept=".pdf,.docx,.xlsx" required>
                        <div class="form-text">Format yang diterima: PDF, DOCX, XLSX (Maksimum <?= (int)env('MAX_UPLOAD_MB', 10) ?> MB).</div>
                    </div>
                </div>
                <div class="pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4 py-2 shadow-xs d-inline-flex align-items-center gap-2">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Muat Naik Dokumen</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- DOCUMENTS TABLE -->
    <div class="card border shadow-sm">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-folder-closed text-primary fs-5"></i>
                <div>
                    <h2 class="h5 mb-0 fw-bold text-dark">Senarai Dokumen Rasmi</h2>
                    <small class="text-muted">Semua dokumen sistem yang telah dimuat naik</small>
                </div>
            </div>
            <span class="badge text-bg-light border text-secondary px-3 py-2 font-monospace">
                <?= count($docs) ?> dokumen
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 36%;">Tajuk Dokumen &amp; Fail</th>
                            <th style="width: 22%;">Kategori &amp; Bidang</th>
                            <th class="text-center" style="width: 14%;">Tahap Akses</th>
                            <th class="text-center" style="width: 10%;">Status</th>
                            <th class="text-end pe-4" style="width: 18%;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($docs as $d): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="doc-icon-wrap icon-pdf">
                                            <i class="fa-solid fa-file-lines"></i>
                                        </div>
                                        <div>
                                            <strong class="d-block text-dark fw-bold"><?= e($d['title']) ?></strong>
                                            <small class="text-muted font-monospace"><i class="fa-solid fa-paperclip me-1"></i><?= e($d['original_filename']) ?></small>
                                            <?php if (!empty($d['file_size'])): ?>
                                                <small class="text-secondary font-monospace">&middot; <?= number_format(((int)$d['file_size']) / 1024, 1) ?> KB</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge text-bg-light border text-secondary font-monospace mb-1">
                                        <?= e(strtoupper(str_replace('_', ' ', (string)$d['category']))) ?>
                                    </span>
                                    <small class="d-block text-muted"><?= e($d['skill_name'] ?? 'Umum / Semua Bidang') ?></small>
                                </td>
                                <td class="text-center">
                                    <?php if ($d['visibility'] === 'private'): ?>
                                        <span class="access-badge access-private">
                                            <i class="fa-solid fa-lock"></i> Sulit
                                        </span>
                                    <?php elseif ($d['visibility'] === 'authenticated'): ?>
                                        <span class="access-badge access-auth">
                                            <i class="fa-solid fa-user-check"></i> Log Masuk
                                        </span>
                                    <?php else: ?>
                                        <span class="access-badge access-public">
                                            <i class="fa-solid fa-globe"></i> Awam
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($d['is_active']): ?>
                                        <span class="badge bg-success px-2 py-1">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary px-2 py-1">Nyahaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary shadow-xs d-inline-flex align-items-center gap-1" href="<?= e(url('download.php?id=' . (int)$d['id'])) ?>">
                                            <i class="fa-solid fa-download"></i>
                                            <span>Muat Turun</span>
                                        </a>
                                        <?php if ($d['is_active']): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Adakah anda pasti ingin menyahaktifkan dokumen ini?')">
                                                <?= Csrf::field() ?>
                                                <input type="hidden" name="action" value="disable">
                                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger shadow-xs" title="Nyahaktifkan Dokumen">
                                                    <i class="fa-solid fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$docs): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <div class="py-4">
                                        <i class="fa-solid fa-folder-open fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                        <h3 class="h6 fw-bold text-dark mb-1">Tiada Dokumen Ditemui</h3>
                                        <p class="small text-muted mb-0">Belum ada dokumen yang dimuat naik ke dalam sistem.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php require BASE_PATH . '/partials/footer.php'; ?>
