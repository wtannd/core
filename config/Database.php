<?php

declare(strict_types=1);

namespace config;

use PDO;
use PDOException;

/**
 * Database Connection Class
 * 
 * Uses PDO for secure database interactions.
 * Credentials should be stored in environment variables.
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Get the PDO database instance (Singleton).
     *
     * @return PDO
     * @throws PDOException
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = DB_HOST;
            $db   = DB_NAME;
            $user = DB_USER;
            $pass = DB_PASS;
            $charset = DB_CHARSET;

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

if (version_compare(PHP_VERSION, '8.5.0', '>=')) { 
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO\Mysql::ATTR_INIT_COMMAND => 'SET SESSION sql_mode="NO_AUTO_VALUE_ON_ZERO", time_zone = "+00:00"'
            ];
} else {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION sql_mode="NO_AUTO_VALUE_ON_ZERO", time_zone = "+00:00"'
            ];
}

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // In production, you might want to log this instead of throwing
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }

        return self::$instance;
    }

    /**
     * Prevent cloning of the instance.
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance.
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize a singleton.");
    }

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {}
}
