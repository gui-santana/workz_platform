<?php
// src/Core/Database.php

namespace Workz\Platform\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    // O construtor é privado para impedir a criação de instâncias diretas.
    private function __construct() {}

    // O método clone é privado para impedir a clonagem da instância.
    private function __clone() {}

    /**
     * Retorna a instância única da conexão PDO com o banco de dados.
     *
     * @return PDO
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            // As informações de conexão vêm do nosso docker-compose.yml
            $host = 'mysql';
            $db   = 'workz_db';
            $user = 'user';
            $pass = 'password';
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                 self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                 // Em um app real, logaríamos este erro em vez de exibi-lo.
                 throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }

        return self::$instance;
    }
}
