<?php
// src/Core/Database.php

namespace Workz\Platform\Core;

use PDO;
use PDOException;

class Database
{
    /** @var PDO[] */
    private static array $instances = [];

    private function __construct() {}
    private function __clone()    {}

    /**
     * Retorna a instância de PDO para o database informado.
     * Se ainda não existir, cria uma nova.
     *
     * @param string|null $databaseName Nome do schema. Se null, usa $_ENV['DB_NAME'].
     * @return PDO
     * @throws \RuntimeException em caso de configuração inválida ou falha de conexão
     */
    public static function getInstance(?string $databaseName = null): PDO
    {
        // Define o nome do banco, pegando do env se necessário
        $dbName = $databaseName ?? ($_ENV['DB_NAME'] ?? ($_ENV['DB_DATABASE'] ?? ''));
        if ($dbName === '') {
            throw new \RuntimeException('Nome do banco de dados não informado.');
        }

        // Se já existe conexão para este banco, retorna
        if (isset(self::$instances[$dbName])) {
            return self::$instances[$dbName];
        }

        // Carrega variáveis de ambiente
        $host    = $_ENV['DB_HOST']     ?? 'mysql';
        $user    = $_ENV['DB_USERNAME'] ?? 'root';
        $pass    = $_ENV['DB_PASSWORD'] ?? 'root_password';
        $port    = $_ENV['DB_PORT']     ?? 3306;
        $charset = 'utf8mb4';

        // Validações básicas
        if (!$host || !$user) {
            throw new \RuntimeException('Configuração de conexão incompleta (DB_HOST ou DB_USERNAME).');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$instances[$dbName] = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Aqui você pode registrar no seu logger antes de lançar
            throw new \RuntimeException('Não foi possível conectar ao banco de dados "'
                . $dbName . '": ' . $e->getMessage());
        }

        return self::$instances[$dbName];
    }
}
