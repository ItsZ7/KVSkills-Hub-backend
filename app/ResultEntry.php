<?php
declare(strict_types=1);

final class ResultEntry
{
    public static function save(array $form, bool $adminPortal): void
    {
        $score = $form['score'] ?? null;
        $rank = $form['rank_position'] ?? '';
        $category = $form['skill_category'] ?? '';
        if (!is_string($score) || !preg_match('/\A[0-9]{1,3}(?:\.[0-9]{1,2})?\z/', $score) || (float)$score > 100) {
            throw new DomainException('Markah mesti antara 0 dan 100 dengan maksimum dua tempat perpuluhan.');
        }
        if ($rank !== '' && (!is_string($rank) || !ctype_digit($rank) || (int)$rank < 1 || (int)$rank > 65535)) {
            throw new DomainException('Kedudukan mesti nombor bulat positif yang sah.');
        }
        if (!in_array($category, ['heavy', 'light'], true)) throw new DomainException('Kategori kemahiran tidak sah.');
        $id = filter_var($form['registration_id'] ?? null, FILTER_VALIDATE_INT);
        $published = (int)(($form['is_published'] ?? '') === '1');
        $notes = $form['notes'] ?? '';
        if (!is_string($notes) || mb_strlen($notes) > 500) throw new DomainException('Catatan tidak sah.');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Serialize score entries with each other and with changes to the scoring switch.
            $switch = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='pt_score_entry_enabled' FOR UPDATE")->fetchColumn();
            if ($switch === false || (!Auth::isAdmin() && $switch !== '1')) throw new DomainException('Kemasukan markah dikunci.');
            $stmt = $pdo->prepare("SELECT competition_id,skill_id FROM registrations WHERE id=? AND status='approved' FOR UPDATE");
            $stmt->execute([$id]);
            $reg = $stmt->fetch();
            if (!$reg) throw new DomainException('Pendaftaran belum diluluskan atau tidak sah.');
            if ($rank !== '') {
                $stmt = $pdo->prepare('SELECT 1 FROM results res JOIN registrations r ON r.id=res.registration_id
                    WHERE r.competition_id=? AND r.skill_id=? AND res.rank_position=? AND r.id<>? LIMIT 1');
                $stmt->execute([$reg['competition_id'], $reg['skill_id'], $rank, $id]);
                if ($stmt->fetchColumn()) throw new DomainException('Kedudukan telah digunakan bagi bidang dan pertandingan ini.');
            }
            $award = CompetitionService::medalForScore((float)$score, $category) ?? 'Tiada';
            $stmt = $pdo->prepare('INSERT INTO results(registration_id, score, skill_category, award, rank_position, is_published, notes, entered_by, published_at)
                VALUES(?,?,?,?,?,?,?,?,IF(?=1,NOW(),NULL))
                ON DUPLICATE KEY UPDATE score=VALUES(score), skill_category=VALUES(skill_category), award=VALUES(award),
                rank_position=VALUES(rank_position), is_published=VALUES(is_published), notes=VALUES(notes), entered_by=VALUES(entered_by),
                published_at=IF(VALUES(is_published)=1,COALESCE(published_at,NOW()),NULL)');
            $stmt->execute([$id, $score, $category, $award, $rank === '' ? null : $rank, $published, trim($notes), Auth::id(), $published]);
            audit(Auth::id(), $adminPortal ? 'admin_save_result' : 'save', 'result', $id, ['score'=>$score,'award'=>$award,'published'=>$published]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
