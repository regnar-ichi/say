<?php

namespace App\Core;

use mysqli;

class Database
{
    private static ?mysqli $connection = null;

    public static function connect(): mysqli
    {
        if (self::$connection === null) {

            $config = require __DIR__ . '/../../config/db.php';

            self::$connection = new mysqli(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['database'],
                $config['port']
            );

            if (self::$connection->connect_errno) {
                exit('Database connection error');
            }

            self::$connection->set_charset($config['charset']);
        }

        return self::$connection;
    }
}