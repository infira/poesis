<?php

namespace Infira\Poesis\orm;

use stdClass;
use Infira\Poesis\Poesis;
use Infira\Poesis\Connection;
use Infira\Poesis\ConnectionManager;
use Infira\Poesis\orm\node\Statement;
use Infira\Utils\Regex;
use Infira\Utils\Session;
use Infira\Utils\Http;
use Infira\Utils\Variable;
use Infira\Poesis\orm\node\Clause;
use Infira\Poesis\orm\node\Field;
use Infira\Poesis\orm\node\LogicalOperator;
use Infira\Poesis\QueryCompiler;


/**
 * A class to provide simple db query functions, update,insert,delet, aso.
 *
 * @property Model $Where
 */
class Model
{
	public $__groupIndex = -1;
	public $__isCloned   = false;
	
	/**
	 * Defines what order by set to sql query
	 *
	 * @var string
	 */
	protected $___orderBy = '';
	
	/**
	 * Defiens what to group by set to sql query
	 *
	 * @var string
	 */
	private $___groupBy = '';
	
	/**
	 * Defiens what order by set to sql query
	 *
	 * @var string
	 */
	private $___limit = '';
	
	/**
	 * Defines last inserted primary column value getted by mysqli_insert_id();
	 *
	 * @var int
	 */
	private $lastInsertID = false;
	
	/**
	 * Last runned sql query string
	 *
	 * @var string string
	 */
	private $lastQuery = "";
	
	/**
	 * Last runned query type (insert,update,delete,replace)
	 *
	 * @var string|false
	 */
	private $lastQueryType = false;
	
	/**
	 * Last fields and where used in building query
	 *
	 * @var \stdClass
	 */
	private $lastFields;
	
	private $nullFieldsAfterAction = true;
	
	/**
	 * @var Connection - a database connection
	 */
	public $Con;
	
	protected $schemaName = '';
	
	/**
	 * @var Schema
	 */
	public $Schema;
	
	/**
	 * @var Clause
	 */
	public  $Clause;
	private $collection      = [];// For multiqueries
	private $eventListeners  = [];
	private $voidTablesToLog = [];
	private $loggerEnabled   = true;
	private $extraLogData    = [];
	private $rowParsers      = [];
	private $dataMethodsClassName = '\Infira\Poesis\dr\ModelDataMethods';
	
	public function __construct(array $options = [])
	{
		$this->lastFields = new stdClass();
		$this->Con        = ConnectionManager::default();
		if (!array_key_exists('isGenerator', $options))
		{
			$this->Clause = new Clause($this->Schema, $this->Con->getName());
		}
		if (isset($options['dataMethods']))
		{
			$this->dataMethodsClassName = $options['dataMethods'];
		}
		
	}
	
	/**
	 * Magic method __get()
	 *[
	 *
	 * @param $name
	 * @see https://www.php.net/manual/en/language.oop5.overloading.php#object.get
	 * @return ModelColumn
	 */
	public final function __get($name)
	{
		if ($name == "Where")
		{
			$this->Where = new $this();
		}
		elseif ($this->Schema::checkColumn($name))
		{
			if (!$this->__isCloned)
			{
				$this->__groupIndex++;
			}
			
			return new ModelColumn($this, $name);
		}
		
		return $this->$name;
	}
	
	/**
	 * @param $name
	 * @param $value
	 * @see https://www.php.net/manual/en/language.oop5.overloading.php#object.set
	 */
	public final function __set($name, $value)
	{
		if (in_array($name, ['Where']))
		{
			$this->$name = $value;
		}
		elseif ($this->Schema::checkColumn($name))
		{
			$this->add($name, $value);
		}
	}
	
	/**
	 * @param $method
	 * @param $arguments
	 */
	public final function __call($method, $arguments)
	{
		Poesis::error('You are tring to call un callable method <B>"' . $method . '</B>" it doesn\'t exits in ' . get_class($this) . ' class');
	}
	
	//region ################### query constructors
	
	/**
	 * Add logical OR operator to query
	 *
	 * @return $this
	 */
	public final function or(): Model
	{
		return $this->addOperator('OR');
	}
	
	/**
	 * Set a order flag to select sql query
	 *
	 * @param string $order
	 * @return Model
	 */
	public final function orderBy(string $order): Model
	{
		$this->___orderBy = $order;
		
		return $this;
	}
	
	/**
	 * Set a broup BY flag to select sql query
	 *
	 * @param string $group
	 * @return Model
	 */
	public final function groupBy(string $group): Model
	{
		$this->___groupBy = $group;
		
		return $this;
	}
	
	/**
	 * Get current order by
	 *
	 * @return string
	 */
	public final function getOrderBy(): string
	{
		return $this->___orderBy;
	}
	
	/**
	 * Get query group by
	 *
	 * @return string
	 */
	public final function getGroupBy(): string
	{
		return $this->___groupBy;
	}
	
	/**
	 * Set a limit flag to select sql query
	 * if ($p1 AND !$p1) then query will be .... LIMIT $p1 else $p1 will ac as start $p2 will act as limit LIMIT $p1, $p2
	 *
	 * @param string $p1
	 * @param string $p2
	 * @return Model
	 */
	public final function limit(string $p1, string $p2 = ""): Model
	{
		if ($p1 !== null and $p2 != null)
		{
			$this->___limit = "$p2 OFFSET $p1";
		}
		else
		{
			$this->___limit = $p1;
		}
		
		return $this;
	}
	
	/**
	 * Get query limit
	 *
	 * @return string
	 */
	public final function getLimit(): string
	{
		return $this->___limit;
	}
	
	/**
	 * Add Logical AND operator to query
	 *
	 * @return $this
	 */
	public final function and()
	{
		return $this->addOperator('AND');
	}
	
	/**
	 * Add XOR operator to query
	 *
	 * @return $this
	 */
	public final function xor()
	{
		return $this->addOperator('XOR');
	}
	
	private final function addOperator(string $op): Model
	{
		if (!$this->__isCloned)
		{
			$this->__groupIndex++;
		}
		$this->Clause->addOperator($this->__groupIndex, new LogicalOperator($op));
		
		return $this;
	}
	
	/**
	 * Add raw sql to final query
	 *
	 * @param string $query
	 * @return $this
	 */
	public final function raw(string $query): Model
	{
		return $this->add(QueryCompiler::RAW_QUERY, ComplexValue::raw($query));
	}
	
	/**
	 * Set were cluasel
	 *
	 * @param string $column
	 * @param mixed  $value
	 * @return Model
	 */
	public final function where(string $column, $value): Model
	{
		$this->Where->add($column, $value);
		
		return $this;
	}
	
	/**
	 * Map columns
	 *
	 * @param array|object $columns
	 * @param array        $voidColumns
	 * @param array        $overWrite
	 * @return $this
	 */
	public final function map($columns, $voidColumns = [], array $overWrite = []): Model
	{
		$columns     = array_merge(Variable::toArray($columns), Variable::toArray($overWrite));
		$voidColumns = Variable::toArray($voidColumns);
		if (checkArray($columns))
		{
			foreach ($columns as $f => $value)
			{
				if (!in_array($f, $voidColumns) and $this->Schema::columnExists($f))
				{
					$this->add($f, $value);
				}
			}
		}
		
		return $this;
	}
	//endregion
	
	//region ################### data transactions
	/**
	 * Select data from database
	 *
	 * @param string|array $columns - fields to use in SELECT $fields FROM, * - use to select all fields, otherwise it will be exploded by comma
	 */
	protected function doSelect($columns = null)
	{
		return $this->execute('select', $this->makeStatement('select', $columns));
	}
	
	/**
	 * Runs a sql replace query width setted values
	 *
	 * @return bool - success
	 */
	public final function replace(): bool
	{
		return $this->execute('replace');
	}
	
	/**
	 * Runs a sql insert query width setted values
	 *
	 * @return bool - success
	 */
	public final function insert(): bool
	{
		return $this->execute('insert');
	}
	
	/**
	 * Runs a sql update query width setted values
	 *
	 * @return bool - success
	 */
	public final function update(): bool
	{
		return $this->execute('update');
	}
	
	/**
	 * Runs a sql delete query with setted values
	 *
	 * @return bool - success
	 */
	public final function delete(): bool
	{
		if ($this->isCollection())
		{
			Poesis::error('Can\'t delete collection');
		}
		
		return $this->execute('delete');
	}
	
	/**
	 * Duplicate values by Where
	 *
	 * @param array|null $overwrite
	 * @return $this
	 */
	public final function duplicate(array $overwrite = null)
	{
		$this->dontNullFields();
		$className = $this->Schema::getClassName();
		$DbCurrent = new $className();
		if ($this->Where->Clause->hasValues() and $this->Clause->hasValues())
		{
			$DbCurrent->map($this->Where->Clause->getValues());
		}
		elseif (!$this->Where->Clause->hasValues() and $this->Clause->hasValues())
		{
			$DbCurrent->map($this->Clause->getValues());
		}
		
		$DbNew               = new $className;
		$voidFields          = $this->Schema::getPrimaryColumns();
		$extraFieldsIsSetted = $this->Where->Clause->hasValues();
		$DbCurrent->select()->each(function ($CurrentRow) use (&$DbNew, $voidFields, $extraFieldsIsSetted, &$overwrite)
		{
			$DbNew->map($CurrentRow, $voidFields);
			if ($extraFieldsIsSetted)
			{
				$DbNew->map($this->Clause->getValues(), $voidFields);
			}
			if ($overwrite)
			{
				$DbNew->map($overwrite, $voidFields);
			}
			$DbNew->insert();
		});
		
		return $DbNew;
	}
	
	/**
	 * Truncate table
	 */
	public final function truncate()
	{
		$this->Con->realQuery("TRUNCATE TABLE " . $this->Schema::getTableName());
	}
	
	/**
	 * Execute update or insert
	 *
	 * @param null $mapData
	 * @return $this|string
	 */
	public final function save($mapData = null)
	{
		return $this->doAutoSave($mapData, false);
	}
	
	/**
	 * Execute update or insert
	 *
	 * @param null $mapData
	 * @param bool $returnQuery - return output as sql query
	 * @return $this|string
	 */
	private final function doAutoSave($mapData = null, bool $returnQuery = false)
	{
		if ($this->isCollection())
		{
			Poesis::error("autosave does not work on collections");
		}
		if ($mapData)
		{
			$this->map($mapData);
		}
		if ($this->Clause->hasValues() and !$this->Where->Clause->hasValues()) //no where is detected then has to decide based primary columns whatever insert or update
		{
			if ($this->Schema::hasPrimaryColumns())
			{
				$className    = $this->Schema::getClassName();
				$settedValues = $this->Clause->getValues();
				/**
				 * @var Model $CheckWhere
				 */
				$CheckWhere = new $className();
				$values     = $this->Clause->getValues();
				$c          = count($values);
				if ($c > 1)
				{
					foreach ($values as $groupIndex => $groupItems)
					{
						if (count($groupItems) > 1)
						{
							Poesis::error('Cant have multime items in group on autoSave');
						}
						$Node = $groupItems[0];
						$f    = $Node->getColumn();
						if ($this->Schema::isPrimaryColumn($f))
						{
							$CheckWhere->add($f, $Node);
							unset($values[$groupIndex]);
						}
					}
				}
				else
				{
					$newValues = [];
					foreach ($values[0] as $Node)
					{
						$f = $Node->getColumn();
						if ($this->Schema::isPrimaryColumn($f))
						{
							$CheckWhere->add($f, $Node);
						}
						else
						{
							$newValues[] = $Node;
						}
					}
					$values = [$newValues];
				}
				if ($CheckWhere->Clause->hasValues())
				{
					$CheckWhere->dontNullFields();
					if ($CheckWhere->hasRows())
					{
						$this->Clause->setValues($values);
						$this->Where->Clause->setValues($CheckWhere->Clause->getValues());
						if ($returnQuery)
						{
							return $this->getUpdateQuery();
						}
						else
						{
							$this->update();
						}
					}
					else
					{
						$this->Clause->setValues($settedValues);
						if ($returnQuery)
						{
							return $this->getInsertQuery();
						}
						else
						{
							$this->insert();
						}
					}
				}
				else
				{
					if ($returnQuery)
					{
						return $this->getInsertQuery();
					}
					else
					{
						$this->insert();
					}
				}
			}
			else
			{
				if ($returnQuery)
				{
					return $this->getInsertQuery();
				}
				else
				{
					$this->insert();
				}
			}
			
			return $this;
		}
		else //update
		{
			$cloned = new $this;
			$cloned->Clause->setValues($this->Where->Clause->getValues());
			if ($cloned->hasRows())
			{
				if ($returnQuery)
				{
					return $this->getUpdateQuery();
				}
				else
				{
					$this->update();
				}
				
				return $cloned;
			}
			else
			{
				$insert = new $this;
				$insert->Clause->setValues($this->Clause->getValues());
				if ($returnQuery)
				{
					return $insert->getInsertQuery();
				}
				else
				{
					$insert->insert();
				}
				
				return $insert;
			}
		}
	}
	//endregion
	
	//region ################### query generators
	/**
	 * Get select query
	 *
	 * @param string|array $columns - fields to use in SELECT $columns FROM, * - use to select all fields, otherwise it will be exploded by comma
	 * @return string
	 */
	public final function getSelectQuery($columns = null): string
	{
		return $this->makeStatement('select', $columns)->query();
	}
	
	/**
	 * Get save query (update or insert)
	 *
	 * @param array|null $mapData
	 * @return string
	 */
	public final function getSaveQuery(array $mapData = null): string
	{
		return $this->doAutoSave($mapData, true);
	}
	
	/**
	 * Get update query
	 *
	 * @return string
	 */
	public final function getUpdateQuery(): string
	{
		return $this->makeStatement('update')->query();
	}
	
	/**
	 * Get insert query
	 *
	 * @return string
	 */
	public final function getInsertQuery(): string
	{
		return $this->makeStatement('insert')->query();
	}
	
	/**
	 * Get replace query
	 *
	 * @return string
	 */
	public final function getReplaceQuery(): string
	{
		return $this->makeStatement('replace')->query();
	}
	
	/**
	 * Get delete query
	 *
	 * @return string
	 */
	public final function getDeleteQuery(): string
	{
		return $this->makeStatement('delete')->query();
	}
	
	/**
	 * Debug current sql query
	 *
	 * @param bool|string|array $columns - false means *
	 */
	public final function debugQuery($columns = null): void
	{
		debug($this->getSelectQuery($columns));
	}
	//endregion
	
	//region ################### logging
	/**
	 * Void loggis for current data transaction
	 * If Poesis::isLoggerEnabled() == false, then it doesnt matter
	 *
	 * @return void
	 */
	public final function voidLog()
	{
		$this->loggerEnabled = false;
	}
	
	/**
	 * Add extra log data to go along with current data transaction
	 *
	 * @param string $name - data key name
	 * @param mixed  $data
	 * @return void
	 */
	public final function addLogData(string $name, $data): void
	{
		$this->extraLogData[$name] = $data;
	}
	
	private final function makeLog(string $table, string $queryType, Statement $statement): void
	{
		if (!Poesis::isLoggerEnabled())
		{
			return;
		}
		$LogData               = new stdClass();
		$LogData->setClauses   = $statement->clauses();
		$LogData->whereClauses = $statement->whereClauses();
		if (checkArray($LogData->setClauses))
		{
			foreach ($LogData->setClauses as $groupIndex => $groupItems)
			{
				foreach ($groupItems as $valueIndex => $Node)
				{
					$LogData->setClauses[$groupIndex][$valueIndex] = $Node->getValue();
				}
			}
		}
		if (checkArray($LogData->whereClauses))
		{
			foreach ($LogData->whereClauses as $groupIndex => $groupItems)
			{
				foreach ($groupItems as $valueIndex => $Node)
				{
					$where[$groupIndex][$valueIndex] = $Node->getValue();
				}
			}
		}
		
		if (Poesis::isLogEnabled($table, $LogData->setClauses, $LogData->whereClauses))
		{
			if ($this->isCollection())
			{
				Poesis::error("collection is not implemented");
			}
			
			$ok = Poesis::isLogEnabled($table, $LogData->setClauses, $LogData->whereClauses);
			//Add here some exeptions
			if ($ok)
			{
				$Db     = Poesis::getLoggerModel();
				$userID = 0;
				if (defined("__USER_ID"))
				{
					$userID = __USER_ID;
				}
				$Db->userID($userID);
				
				$LogData->extra        = $this->extraLogData;
				$LogData->trace        = getTrace();
				$LogData->primKeysUsed = false;
				
				
				$LogData->time      = date("d.m.Y H:i:s");
				$LogData->phpInput  = file_get_contents("php://input");
				$LogData->POST      = Http::getPOST();
				$LogData->GET       = Http::getGET();
				$LogData->SessionID = null;
				$LogData->SESSION   = null;
				if (isset($_SESSION))
				{
					$LogData->SessionID = Session::getSID();
					$LogData->SESSION   = Session::get();
					foreach ($LogData->SESSION as $key => $val)
					{
						if (Regex::isMatch("/__allCacheKeys/", $key))
						{
							unset($LogData->SESSION[$key]);
							break;
						}
					}
				}
				$LogData->SERVER = [];
				$voidFields      = ["HTTP_COOKIE", "SERVER_SIGNATURE"];
				foreach ($_SERVER as $f => $val)
				{
					if (!in_array($f, $voidFields) and strpos($f, "SSL") === false and strpos($f, "REDIRECT") === false or in_array($f, ["REDIRECT_URL", "REDIRECT_QUERY_STRING"]))
					{
						$LogData->SERVER[$f] = $_SERVER[$f];
					}
				}
				
				$lastID = null;
				if ($queryType !== 'delete')
				{
					if ($this->Schema::hasAIColumn())
					{
						$lastID = $this->getLastSaveID();
						$Db->tableRowID($lastID);
					}
					else
					{
						Poesis::error("table row ID is not implemented");
					}
				}
				$Db->data->compress(json_encode($LogData));
				$Db->tableName($table);
				$Db->eventName($queryType);
				$Db->microTime(microtime(true));
				$uri = (isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : Http::getCurrentUrl();
				$Db->url($uri);
				$Db->ip(getUserIP());
				$Db->voidLog();
				$Db->insert();
			}
		}
	}
	
	//endregion
	
	//region ################### private flag helpers
	private final function isCollection(): bool
	{
		return checkArray($this->collection);
	}
	
	/**
	 * Make query node
	 *
	 * @param string       $queryType - update,insert,replace,select
	 * @param string|array $columns   - columns to use in SELECT $columns FROM, * - use to select all fields, otherwise it will be exploded by comma
	 * @return Statement
	 */
	private final function makeStatement(string $queryType, $columns = '*'): Statement
	{
		$Statement = new Statement();
		if (in_array($queryType, ['insert', 'replace'], true) and $this->Where->Clause->hasValues())
		{
			Poesis::error("->Where cannot have values during insert/replace query");
		}
		
		if (!in_array($queryType, ['select', 'insert', 'replace', 'delete', 'update']))
		{
			Poesis::error("Unknown query type $queryType");
		}
		if ($this->isCollection() and in_array($queryType, ['select', 'delete']))
		{
			Poesis::error(strtoupper($queryType) . ' collection is not implemented');
		}
		
		$Statement->table($this->Schema::getTableName());
		$Statement->model($this->Schema::getClassName());
		
		$columns = $columns === null ? '*' : $columns;
		$Statement->columns($columns);
		$Statement->orderBy($this->getOrderBy());
		$Statement->limit($this->getLimit());
		$Statement->groupBy($this->getGroupBy());
		if ($this->isCollection())
		{
			$Statement->setToCollection($this->collection['values']);
		}
		
		if (in_array($queryType, ['select', 'delete']))
		{
			$Statement->whereClauses($this->getWhereConditions());
		}
		else
		{
			$Statement->whereClauses($this->Where->Clause->getValues());
			$this->Clause->checkForErrors();
			$Statement->clauses($this->Clause->getValues());
		}
		
		if ($queryType == 'select')
		{
			if ($this->rowParsers)
			{
				$Statement->rowParsers($this->rowParsers);
			}
			$Statement->query(QueryCompiler::select($Statement));
		}
		elseif ($queryType == 'update')
		{
			$Statement->query(QueryCompiler::update($Statement));
		}
		elseif ($queryType == 'delete')
		{
			$Statement->query(QueryCompiler::delete($Statement));
		}
		elseif ($queryType == 'insert')
		{
			$Statement->query(QueryCompiler::insert($Statement));
		}
		elseif ($queryType == 'replace')
		{
			$Statement->query(QueryCompiler::replace($Statement));
		}
		
		return $Statement;
	}
	
	/**
	 * Construct SQL query
	 *
	 * @param string         $queryType - update,insert,replace,select
	 * @param Statement|null $statement
	 * @return bool|object
	 */
	private final function execute(string $queryType, Statement $statement = null)
	{
		if ($this->Schema::isView() and $queryType !== 'select')
		{
			Poesis::error('Can\'t save into view :' . $this->Schema::getTableName());
		}
		
		if ($queryType != 'select')
		{
			if ($queryType == 'update')
			{
				$beforeEvent = 'beforeUpdate';
				$afterEvent  = 'afterUpdate';
			}
			elseif ($queryType == 'insert')
			{
				$beforeEvent = 'beforeInsert';
				$afterEvent  = 'afterInsert';
			}
			elseif ($queryType == 'insert')
			{
				$beforeEvent = 'beforeReplace';
				$afterEvent  = 'afterReplace';
			}
			else
			{
				$beforeEvent = 'beforeDelete';
				$afterEvent  = 'afterDelete';
			}
			if ($this->hasEventListener($beforeEvent))
			{
				if ($this->callBeforeEventListener($beforeEvent) === false)
				{
					return false;
				}
			}
		}
		
		if ($statement === null)
		{
			$statement = $this->makeStatement($queryType);
		}
		if ($queryType == 'select')
		{
			$drClass = $this->dataMethodsClassName;
			$dr      = new $drClass($statement, $this->Con);
			$output  = $dr;
		}
		elseif ($this->isCollection())
		{
			$this->Con->multiQuery($statement->query());
			if ($this->hasEventListener($afterEvent))
			{
				$this->callAfterEventListener($afterEvent, $statement);
			}
			$output             = true;
			$this->lastInsertID = $this->Con->getLastInsertID();
			$this->lastFields   = $this->collection["values"][array_key_last($this->collection["values"])];
		}
		else
		{
			$output = $this->Con->realQuery($statement->query());
			if ($this->hasEventListener($afterEvent))
			{
				$this->callAfterEventListener($afterEvent, $statement);
			}
			$this->lastInsertID = $this->Con->getLastInsertID();
			$this->lastFields   = (object)["fields" => $statement->clauses(), "where" => $statement->whereClauses()];
			
		}
		
		$this->lastQuery     = $statement->query();
		$this->lastQueryType = $queryType;
		$this->collection    = [];
		
		$this->nullFields();
		$this->nullFieldsAfterAction = true;
		
		if ($queryType != 'select' and $this->loggerEnabled)
		{
			$ModelData            = new stdClass();
			$ModelData->extraData = $this->extraLogData;
			$this->makeLog($this->Schema::getTableName(), $queryType, $statement);
			$this->extraLogData  = [];
			$this->loggerEnabled = true;
		}
		
		return $output;
	}
	
	protected final function getWhereConditions(): array
	{
		if (!$this->Where->Clause->hasValues() and $this->Clause->hasValues())
		{
			$this->Where->Clause->setValues($this->Clause->getValues());
		}
		
		return $this->Where->Clause->getValues();
	}
	//endregion
	
	//region ################### evern literners
	public final function addEventListener(string $event, $listener)
	{
		if (!is_callable($listener) and !is_string($listener))
		{
			Poesis::error("Event listener must be either string or callable");
		}
		if (!in_array($event, ['beforeUpdate', 'afterUpdate', 'beforeInsert', 'afterInsert', 'beforeReplace', 'afterReplace', 'beforeDelete', 'afterDelete']))
		{
			Poesis::error("unknown event $event");
		}
		$this->eventListeners[$event][] = $listener;
	}
	
	public final function hasEventListener(string $event)
	{
		return (isset($this->eventListeners[$event]));
	}
	
	private final function callBeforeEventListener(string $event)
	{
		$output = true;
		foreach ($this->eventListeners[$event] as $listener)
		{
			if (is_array($listener))
			{
				$output = call_user_func_array($listener, []);
			}
			elseif (is_string($listener))
			{
				$output = $this->$listener();
			}
			else
			{
				$output = $listener();
			}
			if ($output === false)
			{
				return false;
			}
			elseif ($output === true)
			{
				//just ignore
			}
			else
			{
				Poesis::error("$event event lister must return bool", ['returned' => $output, '$listeenr' => $listener]);
			}
		}
		
		return $output;
	}
	
	private final function callAfterEventListener(string $event, Statement $statement)
	{
		foreach ($this->eventListeners[$event] as $listener)
		{
			if (is_array($listener))
			{
				call_user_func_array($listener, [$statement]);
			}
			elseif (is_string($listener))
			{
				$statement = $this->$listener(...[$statement]);
			}
			else
			{
				$listener(...[$statement]);
			}
		}
	}
	//endregion
	
	//region ################### other helpers
	/**
	 * Set a flag do not null column values after sql action
	 *
	 * @return Model
	 */
	public final function dontNullFields(): Model
	{
		$this->nullFieldsAfterAction = false;
		
		return $this;
	}
	
	/**
	 * Set a flag to null fields after save/update action
	 *
	 * @param bool $forceNull - force to null, no matter what, defaults to false
	 * @return Model
	 */
	public final function nullFields($forceNull = false): Model
	{
		if ($this->nullFieldsAfterAction == true or $forceNull == true)
		{
			$this->Clause->flush();
			$this->Where->Clause->flush();
			$this->rowParsers   = [];
			$this->__groupIndex = -1;
		}
		
		return $this;
	}
	
	/**
	 * Store data for multiple query
	 *
	 * @return $this
	 */
	public final function collect(): Model
	{
		$columns = $this->Clause->getColumns();
		if (!isset($this->collection["checkColumnFields"]))
		{
			$this->collection["checkColumnFields"] = $columns;
		}
		else
		{
			if ($columns != $this->collection["checkColumnFields"])
			{
				Poesis::error("column order/count must match first column count");
			}
		}
		if (!isset($this->collection["values"]))
		{
			$this->collection["values"] = [];
		}
		$statement = new Statement();
		$statement->whereClauses($this->Where->Clause->getValues());
		$this->Clause->checkForErrors();
		$statement->clauses($this->Clause->getValues());
		$this->collection["values"][] = $statement;
		$this->nullFields(true);
		
		return $this;
	}
	
	/**
	 * Get last executed sql query
	 *
	 * @return string
	 */
	public final function getLastQuery(): string
	{
		return $this->lastQuery;
	}
	
	public function addRowParser(callable $parser, array $arguments = []): Model
	{
		$this->rowParsers[] = (object)['parser' => $parser, 'arguments' => $arguments];
		
		return $this;
	}
	
	//endregion
	
	//region ################### data getters
	protected final function add(string $column, $value): Model
	{
		if ($value instanceof Field)
		{
			$field = $value;
		}
		else
		{
			$field = ComplexValue::simpleValue($value);
		}
		$field->setColumn($column);
		if ($this->__isCloned)
		{
			$this->Clause->add($this->__groupIndex, $field);
			
			return $this;
		}
		else
		{
			$this->__groupIndex++;
			$t             = clone $this;
			$t->__isCloned = true;
			$this->Clause->add($this->__groupIndex, $field);
			
			return $t;
		}
	}
	
	/**
	 * Get last updated primary column values
	 * If table has only one primary column and it is auto increment then int is returned
	 * If table has multiple primary fields then object containing primary column values is returned
	 *
	 * @return null|int
	 */
	public final function getLastSaveID()
	{
		if (!$this->Schema::hasAIColumn())
		{
			Poesis::error("table " . $this->Schema::getTableName() . " does not have AUTO_INCREMENT column");
		}
		if (in_array($this->lastQueryType, ['insert', 'replace']))
		{
			return $this->lastInsertID;
		}
		$primField = $this->Schema::getAIColumn();
		$Record    = $this->getLastObject($primField);
		if (is_object($Record))
		{
			return $Record->$primField;
		}
		
		return null;
	}
	
	/**
	 * Get mysql last row object by last inserterdID
	 *
	 * @param string|false|array $fields - get those fields
	 * @return object
	 */
	public final function getLastObject($fields = false)
	{
		return $this->getLastRecord(false, $fields);
	}
	
	/**
	 * Get last updated primary column values
	 * If table has only one primary column and it is auto increment then int is returned
	 * If table has multiple primary fields then object containing primary column values is returned
	 *
	 * @param bool $fields
	 * @return array|bool|int|mixed
	 */
	private final function getLastRecord($fields = false)
	{
		if ($this->lastQueryType == 'delete')
		{
			Poesis::error("Cannot get object on deletion");
		}
		$Db = $this->Schema::getClassObject();
		$Db->limit(1);
		if ($this->Schema::hasAIColumn())
		{
			$primaryField = $this->Schema::getAIColumn();
			$Db->orderBy("$primaryField DESC");
			
			if (in_array($this->lastQueryType, ['insert', 'replace']))
			{
				$Db->$primaryField($this->lastInsertID);
			}
			else //update
			{
				$Db->Clause->setValues($this->lastFields->where);
			}
			
			return $Db->select($fields)->getObject();
		}
		else
		{
			if (in_array($this->lastQueryType, ['insert', 'replace']))
			{
				$Db->Clause->setValues($this->lastFields->fields);
				
			}
			else //update
			{
				$Db->Clause->setValues($this->lastFields->where);
			}
			
			return $Db->select($fields)->getObject();
		}
	}
	
	/**
	 * Retrieve a new auto increment value
	 *
	 * @return int
	 */
	public final function getNextID()
	{
		$nextID = $this->Con->dr("SHOW TABLE STATUS LIKE '" . $this->Schema::getTableName() . "'")->getArray();
		
		return $nextID[0]['Auto_increment'];
	}
	
	/**
	 * Get next orderNr column
	 *
	 * @param string $orderNrField
	 * @return int
	 */
	public final function getNextOrderNr($orderNrField = "orderNr")
	{
		return $this->getNextMaxField($orderNrField);
	}
	
	/**
	 * Retrieve a new auto increment value
	 *
	 * @param string $maxField
	 * @return int
	 */
	public final function getNextMaxField(string $maxField)
	{
		$maxValue = (int)$this->Con->dr($this->getSelectQuery("max($maxField) AS curentMaxFieldValue"))->getFieldValue("curentMaxFieldValue", 0);
		$maxValue++;
		
		return $maxValue;
	}
	
	/**
	 * Counts mysql resource rows
	 *
	 * @return bool
	 */
	public final function hasRows()
	{
		return $this->count() > 0;
	}
	
	/**
	 * Counts mysql resource rows
	 *
	 * @return int
	 */
	public final function count()
	{
		$t = new $this();
		$t->Clause->setValues($this->Clause->getValues());
		$t->Where->Clause->setValues($this->Where->Clause->getValues());
		$sql = $t->getSelectQuery();
		
		//use that way cause of grouping https://stackoverflow.com/questions/16584549/counting-number-of-grouped-rows-in-mysql
		return intval($this->Con->dr("SELECT COUNT(*) as count FROM ($sql) AS c")->getFieldValue("count", 0));
	}
	
	public final function debug($fields = false): void
	{
		$this->select($fields)->debug();
	}
	//endregion
}

?>