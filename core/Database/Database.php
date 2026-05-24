<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    self::$config['host'],
                    self::$config['port'],
                    self::$config['database']
                );

                self::$instance = new PDO($dsn, self::$config['username'], self::$config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                self::$instance->exec("SET NAMES 'utf8mb4'");
                self::$instance->exec("SET timezone = 'Asia/Shanghai'");
            } catch (PDOException $e) {
                throw new \RuntimeException('数据库连接失败: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function close(): void
    {
        self::$instance = null;
    }
}
