<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct()
    {
        Environment::load(Environment::get('GOLDENSITE_ENV_FILE', dirname(__DIR__, 2) . '/.env'));
        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $host = Environment::get('DB_HOST', $config['host'] ?? '127.0.0.1');
        $db   = Environment::get('DB_NAME', $config['dbname'] ?? 'goldensite_cms');
        $user = Environment::get('DB_USER', $config['username'] ?? 'root');
        $pass = Environment::get('DB_PASS', $config['password'] ?? '');
        $port = Environment::get('DB_PORT', $config['port'] ?? '3306');
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $db) || !ctype_digit($port) || preg_match('/[;\r\n]/', $host)) {
            throw new \RuntimeException('Configuración de base de datos inválida.');
        }

        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

        try {
            $this->connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('No se pudo conectar con la base de datos.', 0, $e);
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        return $this->fetchOne($sql, $params);
    }

    public function query(string $sql, array $params = []): bool
    {
        return $this->execute($sql, $params);
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($params);
    }

    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }
}
