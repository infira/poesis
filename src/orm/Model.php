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
	private $lastQuery = '';
	/**
	 * @var Statement
	 */
	private $lastStatement;
	
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
	
	/**
	 * @var Schema
	 */
	public $Schema;
	
	/**
	 * @var Clause
	 */
	protected $Clause;
	/**
	 * @var Clause
	 */
	protected $WhereClause;
	private   $collection           = [];// For multiqueries
	private   $eventListeners       = [];
	private   $voidTablesToLog      = [];
	private   $loggerEnabled        = true;
	private   $extraLogData         = [];
	protected $rowParsers           = [];
	protected $dataMethodsClassName = '\Infira\Poesis\dr\DataMethods';
	private   $TID                  = null;
	private   $success              = false;//is editquery a success
	private   $failedNotes          = '';
	private   $clauseType           = 'normal';
	private   $origin;
	
	public function __construct(array $options = [])
	{
		if (isset($options['connection']) and $options['connection'] instanceof Connection)
		{
			$this->Con = $options['connection'];
		}
		elseif (isset($options['connection']) and $options['connection'] == 'defaultPoesisDbConnection' or !isset($options['connection']))
		{
			$this->Con = ConnectionManager::default();
		}
		else
		{
			$this->Con = ConnectionManager::get($options['connection']);
		}
		if (!array_key_exists('isGenerator', $options))
		{
			$this->Clause      = new Clause($this->Schema, $this->Con->getName());
			$this->WhereClause = new Clause($this->Schema, $this->Con->getName());
		}
		if (Poesis::isTIDEnabled())//make new trasnaction ID
		{
			$this->TID = md5(uniqid('', true) . microtime(true));
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
		if ($name == 'Where')
		{
			if ($this->__isCloned)
			{
				$m = $this->model();
				$m->Clause->setValues($this->Clause->getValues());
				$m->__isCloned   = false;
				$m->__groupIndex = -1;
			}
			else
			{
				$m         = $this->model();
				$m->origin = $this;
			}
			$m->clauseType = 'where';
			$this->Where   = $m;
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
	public final function limit(string $p1, string $p2 = ''): Model
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
	 * @return Model
	 */
	public final function and(): Model
	{
		return $this->addOperator('AND');
	}
	
	/**
	 * Add XOR operator to query
	 *
	 * @return Model
	 */
	public final function xor(): Model
	{
		return $this->addOperator('XOR');
	}
	
	/**
	 * Add logical OR operator to query
	 *
	 * @return Model
	 */
	public final function or(): Model
	{
		return $this->addOperator('OR');
	}
	
	private final function addOperator(string $op): Model
	{
		if (!$this->__isCloned)
		{
			$this->__groupIndex++;
		}
		$this->__clause()->addOperator($this->__groupIndex, new LogicalOperator($op));
		
		return $this;
	}
	
	/**
	 * Add raw sql to final query
	 *
	 * @param string $query
	 * @return Model
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
	 * @param array|string $voidColumns
	 * @param array        $overWrite
	 * @return Model
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
	 * @return \Infira\Poesis\dr\DataMethods
	 */
	protected function select($columns = null)
	{
		$drClass   = $this->dataMethodsClassName;
		$statement = $this->makeStatement('select', $columns);
		$r         = new $drClass($statement->query(), $this->Con);
		$r->setRowParsers($statement->rowParsers());
		$this->nullFields();
		
		return $r;
	}
	
	/**
	 * Runs a sql replace query width setted values
	 *
	 * @return Model
	 */
	public final function replace(): Model
	{
		return $this->doEdit('replace');
	}
	
	/**
	 * Runs a sql insert query width setted values
	 *
	 * @return Model
	 */
	public final function insert(): Model
	{
		return $this->doEdit('insert');
	}
	
	/**
	 * Runs a sql update query width setted values
	 *
	 * @return Model
	 */
	public final function update(): Model
	{
		return $this->doEdit('update');
	}
	
	/**
	 * Runs a sql delete query with setted values
	 *
	 * @return Model
	 */
	public final function delete(): Model
	{
		if ($this->isCollection())
		{
			Poesis::error('Can\'t delete collection');
		}
		
		return $this->doEdit('delete');
	}
	
	/**
	 * Duplicate values by Where
	 *
	 * @param array|null $overwrite
	 * @param array      $voidColumns - void columns on duplicate
	 * @return Model
	 */
	public final function duplicate(array $overwrite = [], array $voidColumns = []): Model
	{
		$this->dontNullFields();
		$DbCurrent        = $this->model();
		$modelExtraFields = null;
		if ($this->WhereClause->hasValues() and $this->Clause->hasValues())
		{
			$modelExtraFields = $this->Clause->getValues();
			$DbCurrent->Clause->setValues($this->WhereClause->getValues());
		}
		elseif (!$this->WhereClause->hasValues() and $this->Clause->hasValues())
		{
			$DbCurrent->map($this->Clause->getValues());
		}
		
		$aiColumn = $this->Schema::hasAIColumn() ? $this->Schema::getAIColumn() : null;
		$DbCurrent->select()->each(function ($CurrentRow) use (&$DbNew, $voidColumns, $modelExtraFields, &$overwrite, $aiColumn)
		{
			$DbNew = $this->model();
			if ($modelExtraFields)
			{
				foreach ($modelExtraFields as $group)
				{
					foreach ($group as $Node)
					{
						$f              = $Node->getColumn();
						$CurrentRow->$f = $Node;
					}
				}
			}
			foreach ($overwrite as $f => $v)
			{
				$CurrentRow->$f = $v;
			}
			if ($aiColumn and property_exists($CurrentRow, $aiColumn))
			{
				unset($CurrentRow->$aiColumn);
			}
			$DbNew->map($CurrentRow, $voidColumns);
			$DbNew->insert();
		});
		
		return $DbNew;
	}
	
	/**
	 * Truncate table
	 */
	public final function truncate()
	{
		$this->Con->realQuery('TRUNCATE TABLE ' . $this->Schema::getTableName());
	}
	
	/**
	 * Execute update or insert, chekcs the databae via primary keys,TID and then if records exosts it will perform a update
	 *
	 * @param null $mapData
	 * @return Model|string
	 */
	public final function save($mapData = null)
	{
		return $this->doAutoSave($mapData, false);
	}
	
	/**
	 * If Where have values it performs update, otherwise update
	 */
	public function savew(): Model
	{
		if ($this->WhereClause->hasValues())
		{
			return $this->update();
		}
		else
		{
			return $this->insert();
		}
	}
	
	/**
	 * Execute update or insert
	 *
	 * @param null $mapData
	 * @param bool $returnQuery - return output as sql query
	 * @return Model|string
	 */
	private final function doAutoSave($mapData = null, bool $returnQuery = false)
	{
		if ($this->isCollection())
		{
			Poesis::error('autosave does not work on collections');
		}
		if ($mapData)
		{
			$this->map($mapData);
		}
		if ($this->Clause->hasValues() and !$this->WhereClause->hasValues()) //no where is detected then has to decide based primary columns whatever insert or update
		{
			if ($this->Schema::hasPrimaryColumns())
			{
				$settedValues = $this->Clause->getValues();
				$CheckWhere   = $this->model();
				$values       = $this->Clause->getValues();
				$c            = count($values);
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
						$this->WhereClause->setValues($CheckWhere->Clause->getValues());
						if ($returnQuery)
						{
							return $this->getUpdateQuery();
						}
						$this->update();
					}
					else
					{
						$this->Clause->setValues($settedValues);
						if ($returnQuery)
						{
							return $this->getInsertQuery();
						}
						$this->insert();
					}
				}
				else
				{
					if ($returnQuery)
					{
						return $this->getInsertQuery();
					}
					$this->insert();
				}
			}
			else
			{
				if ($returnQuery)
				{
					return $this->getInsertQuery();
				}
				$this->insert();
			}
		}
		else //update
		{
			if ($this->hasRows())
			{
				if ($returnQuery)
				{
					return $this->getUpdateQuery();
				}
				$this->update();
			}
			else
			{
				$cloned = $this->model();
				$cloned->Clause->setValues(array_merge($this->WhereClause->getValues(), $this->Clause->getValues()));
				if ($returnQuery)
				{
					return $cloned->getInsertQuery();
				}
				$cloned->insert();
			}
		}
		
		return $this;
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
	 * Void logging for current data transaction
	 *
	 * @return Model
	 */
	public final function voidLog(): Model
	{
		$this->loggerEnabled = false;
		
		return $this;
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
	
	private final function makeLog(string $queryType, Statement $statement): void
	{
		if (!$this->loggerEnabled)
		{
			return;
		}
		if (!Poesis::isLoggerEnabled())
		{
			return;
		}
		$dataModelName = Poesis::getLogDataModel();
		$modelName     = Poesis::getLogModel();
		$table         = $this->Schema::getTableName();
		
		/**
		 * @var \TDbLog $dbLog
		 */
		$dbLog = new $modelName;
		/**
		 * @var \TDbLogData $dbData
		 */
		$dbData = new $dataModelName();
		
		if (in_array($this->Schema::getFullTableName(), [$dbLog->Schema::getFullTableName(), $dbData->Schema::getFullTableName()]))
		{
			return;
		}
		if ($this->isCollection())
		{
			$logStatements = $this->collection['values'];
		}
		else
		{
			$logStatements = [$statement];
		}
		
		foreach ($logStatements as $logStatementRow)
		{
			$setCaluses   = $logStatementRow->clauses();
			$whereClauses = $logStatementRow->whereClauses();
			if (!Poesis::isLogEnabled($table, $setCaluses, $whereClauses))
			{
				return;
			}
			
			$LogData               = new stdClass();
			$LogData->setClauses   = [];
			$LogData->whereClauses = [];
			foreach ($setCaluses as $groupItems)
			{
				foreach ($groupItems as $Node)
				{
					$column = $Node->getColumn();
					if ((Poesis::isTIDEnabled() and $column != 'TID') and (Poesis::isUUIDEnabled() and $column != 'UUID') or !Poesis::isTIDEnabled() or !Poesis::isUUIDEnabled())
					{
						$LogData->setClauses[$column] = $Node->getValue();
					}
				}
			}
			foreach ($whereClauses as $groupIndex => $groupItems)
			{
				foreach ($groupItems as $valueIndex => $Node)
				{
					$column = $Node->getColumn();
					if ((Poesis::isTIDEnabled() and $column != 'TID') and (Poesis::isUUIDEnabled() and $column != 'UUID') or !Poesis::isTIDEnabled() or !Poesis::isUUIDEnabled())
					{
						$LogData->whereClauses[$groupIndex][$valueIndex][$column] = $Node->getValue();
					}
				}
			}
			$LogData->extra     = $this->extraLogData;
			$LogData->trace     = getTrace();
			$LogData->time      = date('d.m.Y H:i:s');
			$LogData->phpInput  = file_get_contents('php://input');
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
					if (Regex::isMatch('/__allCacheKeys/', $key))
					{
						unset($LogData->SESSION[$key]);
						break;
					}
				}
			}
			$LogData->SERVER = [];
			$voidFields      = ['HTTP_COOKIE', 'SERVER_SIGNATURE'];
			foreach ($_SERVER as $f => $val)
			{
				if (!in_array($f, $voidFields) and strpos($f, 'SSL') === false and strpos($f, 'REDIRECT') === false or in_array($f, ['REDIRECT_URL', 'REDIRECT_QUERY_STRING']))
				{
					$LogData->SERVER[$f] = $_SERVER[$f];
				}
			}
			//debug(['$LogData' => $LogData]);
			
			$dbData->data->compress(json_encode($LogData));
			$dbData->userID(Poesis::getLogUserID());
			$dbData->eventName($queryType);
			$dbData->tableName($this->Schema::getTableName());
			if ($this->Schema::hasAIColumn())
			{
				$whereColIsUsed = [$this->Schema::getAIColumn()];
			}
			elseif (Poesis::isUUIDEnabled() and $this->Schema::hasUUIDColumn())
			{
				$whereColIsUsed = ['UUID'];
			}
			elseif ($this->Schema::hasPrimaryColumns())
			{
				$whereColIsUsed = $this->Schema::getPrimaryColumns();
			}
			else
			{
				$whereColIsUsed = [];
				foreach ($whereClauses as $groupItems)
				{
					foreach ($groupItems as $Node)
					{
						$whereColIsUsed[] = $Node->getColumn();
					}
				}
			}
			$dbData->rowIDCols(join(',', $whereColIsUsed));
			$dbData->url((isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : Http::getCurrentUrl());
			$dbData->ip(getUserIP());
			$logDataID = $dbData->insert()->getLastSaveID();
			
			if ($queryType !== 'delete')
			{
				$cloned = $this->model();
				if ($whereClauses)
				{
					$cloned->Clause->setValues($whereClauses);
				}
				else
				{
					if (Poesis::isTIDEnabled() and $this->Schema::hasTIDColumn())
					{
						$cloned->add('TID', $this->TID);
					}
					else
					{
						$cloned->Clause->setValues($setCaluses);
					}
				}
				$cloned->select($whereColIsUsed)->each(function ($whereRow) use (&$dbLog, &$logDataID)
				{
					$dbLog->dataID($logDataID);
					$dbLog->rowIDColValues(join(',', (array)$whereRow));
					$dbLog->collect();
				});
				$dbLog->insert();
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
		$Statement = new Statement($this->TID);
		if (in_array($queryType, ['insert', 'replace'], true) and $this->WhereClause->hasValues())
		{
			Poesis::error('->Where cannot have values during insert/replace query');
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
		$Statement->model($this->Schema::getModelName());
		
		$columns = $columns === null ? '*' : $columns;
		$Statement->columns($columns);
		$Statement->orderBy($this->getOrderBy());
		$Statement->limit($this->getLimit());
		$Statement->groupBy($this->getGroupBy());
		if ($this->isCollection())
		{
			$Statement->setCollectionData($this->collection['values']);
		}
		
		if (in_array($queryType, ['select', 'delete']))
		{
			$Statement->whereClauses($this->getWhereConditions());
		}
		else
		{
			$Statement->whereClauses($this->WhereClause->getValues());
			$this->Clause->checkEditErrors();
			if (Poesis::isTIDEnabled() and $this->Schema::hasTIDColumn())
			{
				$this->add('TID', $Statement->TID());
			}
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
	 * @param string $queryType - update,insert,replace
	 * @return Model
	 */
	private final function doEdit(string $queryType): Model
	{
		if ($this->Schema::isView() and $queryType !== 'select')
		{
			Poesis::error('Can\'t save into view :' . $this->Schema::getTableName());
		}
		
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
				$this->success = true;
				
				return $this;
			}
		}
		
		$statement = $this->makeStatement($queryType);
		
		$this->lastQueryType = $queryType;
		$this->lastStatement = clone $statement;
		if (in_array($queryType, ['insert', 'replace', 'update']) and !checkArray($statement->clauses()) and !checkArray($statement->whereClauses()) and !$statement->isCollection())
		{
			$this->success     = false;
			$this->failedNotes = 'nothing to execute';
		}
		else
		{
			if ($this->isCollection())
			{
				$success = (bool)$this->Con->multiQuery($statement->query());
			}
			else
			{
				$success = (bool)$this->Con->realQuery($statement->query());
			}
			if ($this->hasEventListener($afterEvent))
			{
				$this->callAfterEventListener($afterEvent, $statement);
			}
			$this->lastInsertID = $this->Con->getLastInsertID();
			$this->lastQuery    = $statement->query();
			
			
			if ($this->loggerEnabled)
			{
				$ModelData            = new stdClass();
				$ModelData->extraData = $this->extraLogData;
				$this->makeLog($queryType, $statement);
				$this->extraLogData  = [];
				$this->loggerEnabled = true;
			}
			$this->success = $success;
		}
		
		$this->collection = [];
		$this->nullFields();
		$this->nullFieldsAfterAction = true;
		
		return $this;
	}
	
	/**
	 * Did last mysqli_query was successcful
	 *
	 * @return bool
	 */
	public function isSucces(): bool
	{
		return $this->success;
	}
	
	/**
	 * Get last query error notes
	 *
	 * @return string
	 */
	public function getErrorInfo(): string
	{
		return $this->failedNotes;
	}
	
	protected final function getWhereConditions(): array
	{
		if (!$this->WhereClause->hasValues() and $this->Clause->hasValues())
		{
			$this->WhereClause->setValues($this->Clause->getValues());
		}
		
		return $this->WhereClause->getValues();
	}
	
	/**
	 * @param array $options
	 * return Model
	 */
	private function model(array $options = []): Model
	{
		$options['connection'] = &$this->Con;
		
		return $this->Schema::makeModel($options);
	}
	
	
	public function __clause(): Clause
	{
		$cl = $this->clauseType == 'normal' ? 'Clause' : 'WhereClause';
		$th = $this->origin ?: $this;
		
		return $th->$cl;
	}
	//endregion
	
	//region ################### evern literners
	public final function addEventListener(string $event, $listener)
	{
		if (!is_callable($listener) and !is_string($listener))
		{
			Poesis::error('Event listener must be either string or callable');
		}
		if (!in_array($event, ['beforeUpdate', 'afterUpdate', 'beforeInsert', 'afterInsert', 'beforeReplace', 'afterReplace', 'beforeDelete', 'afterDelete']))
		{
			Poesis::error("unknown event $event");
		}
		$this->eventListeners[$event][] = $listener;
	}
	
	public final function hasEventListener(string $event): bool
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
	public final function nullFields(bool $forceNull = false): Model
	{
		if ($this->nullFieldsAfterAction == true or $forceNull == true)
		{
			$this->Clause->flush();
			$this->WhereClause->flush();
			$this->rowParsers   = [];
			$this->__groupIndex = -1;
		}
		
		return $this;
	}
	
	/**
	 * Store data for multiple query
	 *
	 * @return Model
	 */
	public final function collect(): Model
	{
		$columns = $this->Clause->getColumns();
		if (!isset($this->collection['checkColumnFields']))
		{
			$this->collection['checkColumnFields'] = $columns;
		}
		else
		{
			if ($columns != $this->collection['checkColumnFields'])
			{
				Poesis::error('column order/count must match first column count');
			}
		}
		if (!isset($this->collection['values']))
		{
			$this->collection['values'] = [];
		}
		$statement = new Statement($this->TID);
		$statement->whereClauses($this->WhereClause->getValues());
		$this->Clause->checkEditErrors();
		if (Poesis::isTIDEnabled() and $this->Schema::hasTIDColumn())
		{
			$this->add('TID', $this->TID);
		}
		$statement->clauses($this->Clause->getValues());
		$this->collection['values'][] = $statement;
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
	protected final function add(string $column, $value, int $preDefinedGroupIndex = null): Model
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
			$this->__clause()->add($this->__groupIndex, $field);
			
			return $this;
		}
		else
		{
			$this->__groupIndex++;
			$t             = clone $this;
			$t->__isCloned = true;
			$this->__clause()->add($this->__groupIndex, $field);
			
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
			Poesis::error('table ' . $this->Schema::getTableName() . ' does not have AUTO_INCREMENT column');
		}
		if (in_array($this->lastQueryType, ['insert', 'replace']))
		{
			return $this->lastInsertID;
		}
		$primField = $this->Schema::getAIColumn();
		
		return $this->getLastRecordModel()->select($primField)->getValue($primField, 0);
	}
	
	/**
	 * get last edited record
	 *
	 * @param null $columns
	 * @return stdClass|null
	 */
	public final function getLastRecord($columns = null): ?stdClass
	{
		return $this->getLastRecordModel()->limit(1)->select($columns)->getObject();
	}
	
	/**
	 * Get last edited db model
	 * If table has only one primary column and it is auto increment then int is returned
	 * If table has multiple primary fields then object containing primary column values is returned
	 *
	 * @return Model
	 */
	public final function getLastRecordModel(): Model
	{
		if ($this->lastQueryType == 'delete')
		{
			Poesis::error('Cannot get object on deletion');
		}
		$db = $this->model();
		if (Poesis::isTIDEnabled() and $this->Schema::hasTIDColumn())
		{
			$db->raw("TID = '" . $this->lastStatement->TID() . "'");
		}
		elseif ($this->Schema::hasAIColumn())
		{
			$primaryField = $this->Schema::getAIColumn();
			$db->orderBy("$primaryField DESC");
			
			if (in_array($this->lastQueryType, ['insert', 'replace']))
			{
				$db->$primaryField($this->lastInsertID);
			}
			else //update
			{
				$db->Clause->setValues($this->lastStatement->getLastClauses()->where);
			}
		}
		else
		{
			if (in_array($this->lastQueryType, ['insert', 'replace']))
			{
				$db->Clause->setValues($this->lastStatement->getLastClauses()->fields);
			}
			else //update
			{
				$db->Clause->setValues($this->lastStatement->getLastClauses()->where);
			}
		}
		
		return $db;
	}
	
	/**
	 * Retrieve a new auto increment value
	 *
	 * @return int
	 */
	public final function getNextID(): int
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
	public final function getNextOrderNr(string $orderNrField = 'orderNr'): int
	{
		return $this->getNextMaxField($orderNrField);
	}
	
	/**
	 * Retrieve a new auto increment value
	 *
	 * @param string $maxField
	 * @return int
	 */
	public final function getNextMaxField(string $maxField): int
	{
		$maxValue = (int)$this->Con->dr($this->getSelectQuery("max($maxField) AS curentMaxFieldValue"))->getValue('curentMaxFieldValue', 0);
		$maxValue++;
		
		return $maxValue;
	}
	
	/**
	 * Counts mysql resource rows
	 *
	 * @return bool
	 */
	public final function hasRows(): bool
	{
		return $this->count() > 0;
	}
	
	/**
	 * Counts mysql resource rows
	 *
	 * @return int
	 */
	public final function count(): int
	{
		$t = $this->model();
		$t->Clause->setValues($this->Clause->getValues());
		$t->WhereClause->setValues($this->WhereClause->getValues());
		$sql = $t->getSelectQuery();
		
		//use that way cause of grouping https://stackoverflow.com/questions/16584549/counting-number-of-grouped-rows-in-mysql
		return intval($this->Con->dr("SELECT COUNT(*) as count FROM ($sql) AS c")->getValue('count', 0));
	}
	
	public final function debug($fields = false): void
	{
		$this->select($fields)->debug();
	}
	//endregion
}

?>