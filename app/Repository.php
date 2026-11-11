<?php
declare(strict_types=1);

function all_zones(): array
{
    return db()->query('SELECT * FROM zones WHERE is_active=1 ORDER BY sort_order, name')->fetchAll();
}

function all_skills(): array
{
    return db()->query('SELECT * FROM skills WHERE is_active=1 ORDER BY sort_order, name')->fetchAll();
}

function active_competition(): ?array
{
    $stmt = db()->query("SELECT * FROM competitions ORDER BY start_date DESC LIMIT 1");
    return $stmt->fetch() ?: null;
}

function registration_competition(): ?array
{
    $stmt = db()->query("SELECT * FROM competitions WHERE status='registration' ORDER BY start_date DESC LIMIT 1");
    return $stmt->fetch() ?: null;
}

function skill_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM skills WHERE id=? AND is_active=1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function public_documents(?string $category = null): array
{
    $sql = 'SELECT d.*, s.name AS skill_name FROM documents d LEFT JOIN skills s ON s.id=d.skill_id WHERE d.visibility=\'public\' AND d.is_active=1';
    $params = [];
    if ($category) { $sql .= ' AND d.category=?'; $params[]=$category; }
    $sql .= ' ORDER BY s.sort_order, d.title';
    $stmt=db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}

function lock_registration_slot(int $competitionId, int $skillId): void
{
    $stmt = db()->prepare("SELECT cs.id FROM competition_skills cs JOIN competitions c ON c.id=cs.competition_id
        WHERE c.id=? AND cs.skill_id=? AND c.status='registration' FOR UPDATE");
    $stmt->execute([$competitionId, $skillId]);
    if (!$stmt->fetchColumn()) throw new DomainException('Pendaftaran bagi pertandingan dan bidang ini telah ditutup.');
}
