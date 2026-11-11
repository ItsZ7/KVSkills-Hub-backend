<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = (string)env('DB_HOST', '127.0.0.1');
        $port = (string)env('DB_PORT', '3306');
        $database = (string)env('DB_NAME', 'kvskills_hub');
        $username = (string)env('DB_USER', 'root');
        $password = (string)env('DB_PASS', '');
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        self::$connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);

        return self::$connection;
    }

    public static function disconnect(): void
    {
        self::$connection = null;
    }
}

function db(): PDO
{
    return Database::connection();
}
