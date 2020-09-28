<?php

namespace Infira\Poesis;

use Infira\Poesis\dr\DataRetrieval;

/**
 * Makes a new connection with mysqli
 *
 * @package Infira\Poesis
 */
class Connection
{
	use \PoesisConnectionExtendor;
	
	/**
	 * @var \mysqli
	 */
	private $mysqli;
	
	public $dbName = false;
	
	private $lastQueryInfo;
	
	/**
	 * Connect using mysqli
	 *
	 * @param string      $host
	 * @param string      $user
	 * @param string      $pass
	 * @param string      $db
	 * @param int|null    $port   - if null default port will be used
	 * @param string|null $socket - if null default socket will be used
	 */
	public function __construct(string $host, string $user, string $pass, string $db, int $port = null, string $socket = null)
	{
		$this->mysqli = new \mysqli($host, $user, $pass, $db, $port, $socket);
		if ($this->mysqli->connect_errno)
		{
			$err = 'Could not connect to database (<strong>' . $db . '</strong>) (' . $this->mysqli->connect_errno . ')' . $this->mysqli->connect_error . ' hostis :("<strong>' . $host . '</strong>")';
			if (!defined("DATABASE_CONNECTION_SUCESS"))
			{
				define("DATABASE_CONNECTION_SUCESS", false);
			}
			Poesis::error($err);
		}
		else
		{
			if (!defined("DATABASE_CONNECTION_SUCESS"))
			{
				define("DATABASE_CONNECTION_SUCESS", false);
			}
		}
		$this->mysqli->set_charset('utf8mb4');
		$this->query("SET collation_connection = utf8mb4_unicode_ci");
		$this->dbName = $db;
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
	 * @param string $query
	 * @return DataRetrieval
	 */
	public function dr(string $query)
	{
		if (empty($query))
		{
			Poesis::error("query cannot be empty");
		}
		
		return new DataRetrieval($query, $this);
	}
	
	/**
	 * execuete mysqli_query
	 *
	 * @param string $query
	 * @return mysqli_result
	 */
	public function query(string $query)
	{
		return $this->execute($query, "query");
	}
	
	/**
	 * Mysql real query
	 *
	 * @param string $query sql query
	 * @return bool
	 */
	public function realQuery(string $query): bool
	{
		return $this->execute($query, "real_query");
	}
	
	/**
	 * Run a mysqli run query
	 *
	 * @param string        $query
	 * @param callable|null $callback - for row callback
	 * @return void
	 */
	public function multiQuery(string $query, callable $callback = null): void
	{
		if ($this->execute($query, "multi_query"))
		{
			do
			{
				if (is_callable($callback))
				{
					if ($result = $this->mysqli->store_result())
					{
						while ($row = $result->fetch_row())
						{
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
	 * @param string $fileLocation
	 * @param array  $vars
	 */
	public function fileQuery(string $fileLocation, array $vars = []): void
	{
		if (!file_exists($fileLocation))
		{
			Poesis::error("File does not exists");
		}
		
		$queries = [];
		$lines   = file($fileLocation);
		$k       = 0;
		foreach ($lines as $line)
		{
			$line = trim($line);
			
			if (substr($line, 0, 2) == '--' || $line == '')
			{
				continue;
			}
			
			if (!array_key_exists($k, $queries))
			{
				$queries[$k] = "";
			}
			$queries[$k] .= $line . NL;
			if (substr(trim($line), -1, 1) == ';')
			{
				$k++;
			}
		}
		if (checkArray($queries))
		{
			foreach ($queries as $query)
			{
				$sqlQuery = Variable::assign($vars, trim($query));
				if ($sqlQuery)
				{
					$this->query($sqlQuery);
				}
			}
		}
	}
	
	/**
	 * set variable to database
	 *
	 * @param string $name
	 * @param bool   $value
	 */
	public function setVar(string $name, bool $value = false)
	{
		$sql = "";
		if (checkArray($name))
		{
			$vars = [];
			foreach ($name as $n => $v)
			{
				$vars[] = "@$n = " . $this->escape($v);
			}
			$sql .= join(", ", $vars);
		}
		else
		{
			if (is_bool($value))
			{
				if ($value == false)
				{
					$value = "false";
				}
				else
				{
					$value = "true";
				}
			}
			else
			{
				$value = $this->escape($value);
			}
			$sql = "@$name = " . $value;
		}
		$this->query("SET " . $sql);
	}
	
	/**
	 * Get mysql variable value
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function getVar(string $name)
	{
		return $this->query("SELECT @$name")->fetch_assoc()["@$name"];
	}
	
	//###################################################### Other helpers
	
	/**
	 * @param mixed $data
	 * @param bool  $checkArray
	 * @return mixed
	 */
	public function escape($data)
	{
		return $this->mysqli->real_escape_string($data);
	}
	
	
	/**
	 * Returns last mysql insert_id
	 *
	 * @see https://www.php.net/manual/en/mysqli.insert-id.php
	 * @return int
	 */
	public function getLastInsertID()
	{
		return $this->mysqli->insert_id;
	}
	
	public function getLastQueryInfo(): \stdClass
	{
		return $this->lastQueryInfo;
	}
	
	public function debugLastQuery()
	{
		debug($this->lastQueryInfo);
	}
	
	//###################################################### Private methods
	
	private function execute(string $query, string $type)
	{
		// $runBeginTime = microtime(true);
		$this->lastQueryInfo          = new \stdClass();
		$this->lastQueryInfo->dbName  = $this->dbName;
		$this->lastQueryInfo->runtime = microtime(true);
		if ($type == "query")
		{
			$sqlQueryResult = $this->mysqli->query($query);
		}
		elseif ($type == "real_query")
		{
			$sqlQueryResult = $this->mysqli->real_query($query);
		}
		elseif ($type == "multi_query")
		{
			$sqlQueryResult = $this->mysqli->multi_query($query);
		}
		else
		{
			Poesis::error("Unknown query type", ['queryType' => $type]);
		}
		$this->lastQueryInfo->runtime = microtime(true) - $this->lastQueryInfo->runtime;
		$this->lastQueryInfo->query   = $query;
		
		$db = $this->dbName;
		if ($this->mysqli->error)
		{
			$error = 'SQL "' . $db . '" error : ' . $this->mysqli->error . ' < br><br > ';
			$error .= "SQL \"$db\" query : " . $query;
			Poesis::error(str_replace(NL, BR, $error));
			exit();
		}
		
		$this->runCustomMethod('afterQuery', [$this->lastQueryInfo]);
		
		return $sqlQueryResult;
	}
	
	private function runCustomMethod($method, $args)
	{
		if (method_exists($this, $method))
		{
			return $this->$method(...$args);
		}
		
		return null;
	}
	
}

?>