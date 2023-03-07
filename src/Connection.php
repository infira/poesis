<?php

namespace Infira\Poesis;

use Infira\Poesis\dr\DataMethods;
use Infira\Poesis\support\Utils;
use mysqli;
use stdClass;

/**
 * Makes a new connection with mysqli
 *
 * @package Infira\Poesis
 */
class Connection
{
    /**
     * @var \mysqli
     */
    private $mysqli;

    public $dbName = false;
    public $name;

    private $lastQueryInfo;

    /**
     * Connect using mysqli
     *
     * @param  string  $name
     * @param  string  $host
     * @param  string  $user
     * @param  string  $pass
     * @param  string  $db
     * @param  int|null  $port  - if null default port will be used
     * @param  string|null  $socket  - if null default socket will be used
     */
    public function __construct(string $name, string $host, string $user, string $pass, string $db, int $port = null, string $socket = null)
    {
        $this->name = $name;
        $this->mysqli = new mysqli($host, $user, $pass, $db, $port, $socket);
        if ($this->mysqli->connect_errno) {
            $err = 'Could not connect to database (<strong>'.$db.'</strong>) ('.$this->mysqli->connect_errno.')'.$this->mysqli->connect_error.' hostis :("<strong>'.$host.'</strong>")';
            if (!defined("DATABASE_CONNECTION_SUCESS")) {
                define("DATABASE_CONNECTION_SUCESS", false);
            }
            Poesis::error($err);
        }
        else {
            if (!defined("DATABASE_CONNECTION_SUCESS")) {
                define("DATABASE_CONNECTION_SUCESS", false);
            }
        }
        $this->mysqli->set_charset('utf8mb4');
        $this->query("SET collation_connection = utf8mb4_unicode_ci");
        $this->dbName = $db;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get currrent connection db name
     *
     * @return string
     */
    public function getDbName(): string
    {
        return $this->dbName;
    }

    /**
     * Close mysql connection
     */
    public function close()
    {
        $this->mysqli->close();
    }

    // Run Queries #

    /**
     * Data retrieval
     *
     * @param  string  $query
     * @return DataMethods
     */
    public function dr(string $query): DataMethods
    {
        if (empty($query)) {
            Poesis::error("query cannot be empty");
        }

        $dm = new DataMethods();
        $dm->setQuery($query);
        $dm->setConnection($this);

        return $dm;
    }

    /**
     * execuete mysqli_query
     *
     * @param  string  $query
     * @return \mysqli_result|bool
     */
    public function query(string $query)
    {
        return $this->execute($query, "query");
    }

    /**
     * Mysql real query
     *
     * @param  string  $query  sql query
     * @return bool
     */
    public function realQuery(string $query): bool
    {
        return $this->execute($query, "real_query");
    }

    /**
     * Run a mysqli run query
     *
     * @param  string  $query
     * @param  callable|null  $callback  - for row callback
     * @return void
     */
    public function multiQuery(string $query, callable $callback = null): void
    {
        if ($this->execute($query, "multi_query")) {
            do {
                if (is_callable($callback)) {
                    if ($result = $this->mysqli->store_result()) {
                        while ($row = $result->fetch_row()) {
                            $callback($result->fetch_object());
                        }
                        $result->free();
                    }
                }
            }
            while ($this->mysqli->more_results() && $this->mysqli->next_result());
        }
    }

    /**
     * Run sql query from file
     *
     * @param  string  $fileLocation
     * @param  array  $vars
     */
    public function fileQuery(string $fileLocation, array $vars = []): void
    {
        if (!file_exists($fileLocation)) {
            Poesis::error("query file $fileLocation does not exists");
        }
        $this->complexQuery(Utils::strVariables($vars, file_get_contents($fileLocation)));
    }

    /**
     * Run complex query (variables, comments, etc)
     *
     * @param  string  $query
     * @param  array  $vars
     */
    public function complexQuery(string $query, array $vars = []): void
    {
        $query = Utils::strVariables($vars, trim($query));

        $realQueries = [];
        $k = 0;
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $query) as $line) {
            $line = trim($line);

            if (substr($line, 0, 2) == '--' || $line == '') {
                continue;
            }

            if (!array_key_exists($k, $realQueries)) {
                $realQueries[$k] = "";
            }
            if ($line) {
                $realQueries[$k] .= $line."\n";
            }
            if (substr(trim($line), -1, 1) == ';') {
                $k++;
            }
        }
        foreach ($realQueries as $query) {
            $this->query($query);
        }
    }

    /**
     * set variable to database
     *
     * @param  string  $name
     * @param  mixed  $value
     */
    public function setVar(string $name, $value)
    {
        if (is_bool($value)) {
            $value = $value === true ? 'true' : 'false';
        }
        else {
            $value = $this->escape($value);
        }
        $this->query("SET @$name = ".$value);
    }

    /**
     * Get mysql variable value
     *
     * @param  string  $name
     * @return mixed
     */
    public function getVar(string $name)
    {
        return $this->query("SELECT @$name")->fetch_assoc()["@$name"];
    }

    //###################################################### Other helpers

    /**
     * @param  mixed  $data
     * @return string
     */
    public function escape(string $data): string
    {
        return $this->mysqli->real_escape_string($data);
    }

    /**
     * Returns last mysql insert_id
     *
     * @see https://www.php.net/manual/en/mysqli.insert-id.php
     * @return int
     */
    public function getLastInsertID(): int
    {
        return $this->mysqli->insert_id;
    }

    public function getLastQueryInfo(): stdClass
    {
        return $this->lastQueryInfo;
    }

    public function debugLastQuery()
    {
        debug($this->lastQueryInfo);
    }

    //###################################################### Private methods

    /**
     * @param  string  $query
     * @param  string  $type
     * @return bool|\mysqli_result
     */
    private function execute(string $query, string $type)
    {
        // $runBeginTime = microtime(true);
        $this->lastQueryInfo = new stdClass();
        $this->lastQueryInfo->dbName = $this->dbName;
        $this->lastQueryInfo->runtime = microtime(true);
        if ($type == "query") {
            $sqlQueryResult = $this->mysqli->query($query);
        }
        elseif ($type == "real_query") {
            $sqlQueryResult = $this->mysqli->real_query($query);
        }
        elseif ($type == "multi_query") {
            $sqlQueryResult = $this->mysqli->multi_query($query);
        }
        else {
            Poesis::error("Unknown query type", ['queryType' => $type]);
        }
        $this->lastQueryInfo->runtime = microtime(true) - $this->lastQueryInfo->runtime;
        $this->lastQueryInfo->query = $query;

        $db = $this->dbName;
        if ($this->mysqli->error) {
            $error = 'SQL "'.$db.'" error : '.$this->mysqli->error.' < br><br > ';
            $error .= "SQL \"$db\" query : ".$query;
            Poesis::error(str_replace("\n", '<br>', $error));
            exit();
        }

        if (Poesis::isQueryHistoryEnabled()) {
            QueryHistory::add($this->lastQueryInfo->query, $this->lastQueryInfo->runtime);
        }

        $this->runCustomMethod('afterQuery', [$this->lastQueryInfo]);

        return $sqlQueryResult;
    }

    private function runCustomMethod(string $method, array $args = [])
    {
        if (method_exists($this, $method)) {
            return $this->$method(...$args);
        }

        return null;
    }

    public function getMysqli(): mysqli
    {
        return $this->mysqli;
    }
}