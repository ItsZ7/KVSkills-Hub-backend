<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function verify(): void
    {
        $token = (string)($_POST['_token'] ?? '');
        if ($token === '' || !hash_equals((string)($_SESSION['_csrf'] ?? ''), $token)) {
            http_response_code(419);
            exit('Sesi borang telah tamat. Sila muat semula halaman dan cuba lagi.');
        }
    }
}
