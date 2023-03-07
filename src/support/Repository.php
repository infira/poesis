<?php

namespace Infira\Poesis\support;


use Infira\Poesis\Connection;
use Infira\Poesis\ConnectionManager;

class Repository
{
    private static $data = [];

    public static function dbSchema(string $connectionName)
    {
        if (!isset(self::$data[$connectionName]['dbSchema'])) {
            $cn = self::$data[$connectionName]['dbSchemaClassName'];
            self::$data[$connectionName]['dbSchema'] = new $cn();
        }

        return self::$data[$connectionName]['dbSchema'];
    }

    public static function connection(string $connectionName): Connection
    {
        return ConnectionManager::get($connectionName);
    }

    public static function __setDbSchemaClass(string $connectionName, string $connector)
    {
        self::$data[$connectionName]['dbSchemaClassName'] = $connector;
    }
}