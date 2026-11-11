<?php
declare(strict_types=1);

final class AccessLimit
{
    public static function reserve(string $action, string $email, int $accountLimit, int $ipLimit): bool
    {
        $pdo = db();
        if (random_int(1, 100) === 1) {
            $pdo->exec('DELETE FROM auth_limits WHERE expires_at <= NOW() LIMIT 500');
        }
        $stmt = $pdo->prepare('INSERT INTO auth_limits (bucket, attempts, expires_at)
            VALUES (?, LAST_INSERT_ID(1), NOW() + INTERVAL 15 MINUTE)
            ON DUPLICATE KEY UPDATE
                attempts = LAST_INSERT_ID(IF(expires_at <= NOW(), 1, attempts + 1)),
                expires_at = IF(expires_at <= NOW(), NOW() + INTERVAL 15 MINUTE, expires_at)');
        foreach (['ip' => [client_ip(), $ipLimit], 'account' => [$email, $accountLimit]] as $type => [$value, $limit]) {
            $stmt->execute([hash('sha256', "$action|$type|$value")]);
            if ((int)$pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn() > $limit) {
                return false;
            }
        }
        return true;
    }
    public static function successfulLogin(string $email): void
    {
        $stmt = db()->prepare('UPDATE auth_limits SET attempts=GREATEST(1, attempts)-1 WHERE bucket IN (?,?) AND expires_at > NOW()');
        $stmt->execute([hash('sha256', 'login|account|' . $email), hash('sha256', 'login|ip|' . client_ip())]);
    }
}
