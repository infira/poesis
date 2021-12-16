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
use Infira\Utils\Globals;
use Infira\Utils\Date;


/**
 * A class to provide simple db query functions, update,insert,delet, aso.
 *
 * @property Model $Where
 */
class Model
{
	public    $__groupIndex         = -1;
	public    $__isCloned           = false;
	private   $haltReset            = false;
	protected $WhereClause;
	private   $eventListeners       = [];
	protected $loggerEnabled        = true;
	private   $extraLogData         = [];
	protected $rowParsers           = [];
	protected $dataMethodsClassName = '\Infira\Poesis\dr\DataMethods';
	protected $modelColumnClassName = '\Infira\Poesis\orm\ModelColumn';
	private   $success              = false;//is editquery a success
	private   $failMsg              = '';
	private   $clauseType           = 'normal';
	private   $origin;
	private   $lastLogID;
	
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
	 * Defines last inserted primary column value got by mysqli_insert_id();
	 *
	 * @var int
	 */
	private $lastInsertID = false;
	
	/**
	 * @var Statement
	 */
	protected $statement;
	
	/**
	 * @var Statement
	 */
	private $lastStatement;
	
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
	}
	
	/**
	 * Magic method __get()
	 *
	 * @param $name
	 * @see https://www.php.net/manual/en/language.oop5.overloading.php#object.get
	 * @return mixed
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
			$cn = $this->modelColumnClassName;
			
			return new $cn($this, $name);
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
		if ($name == 'Where')
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
	
	//region query constructors
	
	/**
	 * Set a order flag to select sql query
	 *
	 * @param string $order
	 * @return $this
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
	 * @return $this
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
	 * if ($p1 AND !$p1) then query will be .... LIMIT $p1 else $p1 will ac as start $p2 will act as limit $p1, $p2
	 *
	 * @param int      $p1
	 * @param int|null $p2
	 * @return $this
	 */
	public final function limit(int $p1, int $p2 = null): Model
	{
		if ($p2 !== null)
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
	public final function and(): Model
	{
		return $this->addOperator('AND');
	}
	
	/**
	 * Add XOR operator to query
	 *
	 * @return $this
	 */
	public final function xor(): Model
	{
		return $this->addOperator('XOR');
	}
	
	/**
	 * Add logical OR operator to query
	 *
	 * @return $this
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
	 * @return $this
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
	 * @return $this
	 */
	public final function map($columns, $voidColumns = [], array $overWrite = []): Model
	{
		$columns     = array_merge(Variable::toArray($columns), Variable::toArray($overWrite));
		$voidColumns = Variable::toArray($voidColumns);
		if (is_array($columns))
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
	
	//region data transactions
	/**
	 * Select data from database
	 *
	 * @param string|array $columns   - columns to use in SELECT $columns FROM
	 *                                USE null OR *[string] - used to select all columns, string will be exploded by ,
	 * @return \Infira\Poesis\dr\DataMethods
	 */
	protected function select($columns = null)
	{
		return $this->doSelect($columns, false);
	}
	
	/**
	 * Runs a sql replace query width setted values
	 *
	 * @return $this
	 */
	public final function replace(): Model
	{
		return $this->doEdit('replace', false);
	}
	
	/**
	 * Runs a sql insert query width setted values
	 *
	 * @return $this
	 */
	public final function insert(): Model
	{
		return $this->doEdit('insert', false);
	}
	
	/**
	 * Runs a sql update query width setted values
	 *
	 * @return $this
	 */
	public final function update(): Model
	{
		return $this->doEdit('update', false);
	}
	
	/**
	 * Runs a sql delete query with setted values
	 *
	 * @return $this
	 */
	public final function delete(): Model
	{
		return $this->doEdit('delete', false);
	}
	
	/**
	 * Duplicate values by Where
	 *
	 * @param array|null $overwrite
	 * @param array      $voidColumns - void columns on duplicate
	 * @return $this
	 */
	public final function duplicate(array $overwrite = [], array $voidColumns = []): Model
	{
		return $this->doDuplicate($overwrite, $voidColumns, false);
	}
	
	/**
	 * Truncate table
	 */
	public final function truncate()
	{
		$this->Con->realQuery('TRUNCATE TABLE ' . $this->Schema::getTableName());
	}
	
	/**
	 * Execute update or insert, chekcs the databae via primary keys,TID and then if records exosts it will perform an update
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
	 * @param array|null $mapData
	 * @param bool       $returnQuery - return output as sql query
	 * @return $this|string
	 */
	private final function doAutoSave(?array $mapData, bool $returnQuery)
	{
		if ($mapData)
		{
			$this->map($mapData);
		}
		if (!$this->Clause->hasValues() and $this->WhereClause->hasValues())
		{
			Poesis::error('Only where is setted, set some editable clauses');
		}
		if ($this->WhereClause->hasValues())
		{
			if ($returnQuery)
			{
				return $this->getUpdateQuery();
			}
			$this->update();
			
		}
		else
		{
			if ($this->Schema::hasPrimaryColumns())
			{
				$whereModel = $this->model();
				$clauses    = $this->Clause->getValues();
				foreach ($clauses as $groupIndex => $groupItems)
				{
					if (count($groupItems) > 1)
					{
						Poesis::error('Cant have multime items in group on autoSave');
					}
					$Node = $groupItems[0];
					$f    = $Node->getColumn();
					if ($this->Schema::isPrimaryColumn($f))
					{
						$whereModel->add($f, $Node);
						unset($clauses[$groupIndex]);
					}
				}
				if ($whereModel->Clause->hasValues())
				{
					$editModel = $this->model();
					foreach ($this->eventListeners as $event => $listeners)
					{
						foreach ($listeners as $listener)
						{
							$editModel->on($event, $listener['listener'], $listener['group']);
						}
					}
					if ($whereModel->haltReset()->hasRows())
					{
						$editModel->Clause->setValues($clauses);
						$editModel->WhereClause->setValues($whereModel->Clause->getValues());
						if ($returnQuery)
						{
							return $editModel->getUpdateQuery();
						}
						$editModel->update();
					}
					else
					{
						$editModel->Clause->setValues($this->Clause->getValues());
						if ($returnQuery)
						{
							return $editModel->getInsertQuery();
						}
						$editModel->insert();
					}
					
					return $editModel;
				}
			}
			if ($returnQuery)
			{
				return $this->getInsertQuery();
			}
			$this->insert();
			
		}
		
		return $this;
	}
	
	/**
	 * Duplicate values by Where
	 *
	 * @param array|null $overwrite
	 * @param array      $voidColumns - void columns on duplicate
	 * @return $this|string
	 */
	public final function doDuplicate(array $overwrite = [], array $voidColumns = [], bool $returnQuery = false)
	{
		$selectModel    = $this->model();
		$modelOverwrite = [];
		if (!$this->WhereClause->hasValues() and !$this->Clause->hasValues())
		{
			Poesis::error('Cant duplicate empty');
		}
		
		if ($this->WhereClause->hasValues() and $this->Clause->hasValues())
		{
			$modelOverwrite = $this->Clause->getValues();
			$selectModel->Clause->setValues($this->WhereClause->getValues());
		}
		elseif (!$this->WhereClause->hasValues() and $this->Clause->hasValues())
		{
			$selectModel->Clause->setValues($this->Clause->getValues());
		}
		
		$dr = $selectModel->select();
		
		if (!$dr->hasRows())
		{
			return $returnQuery ? null : $this->model()->setFailed('nothing to duplicate');
		}
		$aiColumn = $this->Schema::hasAIColumn() ? $this->Schema::getAIColumn() : null;
		$dbNew    = $this->model();
		foreach ($this->eventListeners as $event => $listeners)
		{
			foreach ($listeners as $listener)
			{
				$dbNew->on($event, $listener['listener'], $listener['group']);
			}
		}
		$dr->each(function ($CurrentRow) use (&$dbNew, $voidColumns, $modelOverwrite, &$overwrite, $aiColumn)
		{
			foreach ($modelOverwrite as $groupItems)
			{
				if (count($groupItems) > 1)
				{
					Poesis::error('Cant have multime items in group on autoSave');
				}
				foreach ($groupItems as $Node)
				{
					$f              = $Node->getColumn();
					$CurrentRow->$f = $Node;
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
			$dbNew->map($CurrentRow, $voidColumns);
			$dbNew->collect();
		});
		if ($returnQuery)
		{
			return $dbNew->getInsertQuery();
		}
		
		return $dbNew;
	}
	
	/**
	 * @param string $queryType - update,insert,replace
	 * @return $this|string
	 */
	private final function doEdit(string $queryType, bool $returnQuery = false)
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
		elseif ($queryType == 'replace')
		{
			$beforeEvent = 'beforeReplace';
			$afterEvent  = 'afterReplace';
		}
		else//if ($queryType == 'delete')
		{
			$beforeEvent = 'beforeDelete';
			$afterEvent  = 'afterDelete';
			$this->checkClauseBothValues();
		}
		$this->__groupIndex = -1;
		$this->__isCloned   = false;
		$this->clauseType   = 'normal';
		if ($this->hasEventListener($beforeEvent) and !$returnQuery)
		{
			if ($this->callBeforeEventListener($beforeEvent) === false)
			{
				$this->success = false;
				
				return $this;
			}
		}
		
		$TID = null;
		if ($this->Schema::isTIDEnabled())
		{
			$tidColumnName = $this->Schema::getTIDColumn();
			$TID           = md5(uniqid('', true) . microtime(true));
			$this->$tidColumnName($TID);
		}
		$this->makeStatement();
		$this->statement->TID($TID);
		$query = QueryCompiler::$queryType($this->statement);
		if ($returnQuery)
		{
			$this->reset(true);
			
			return $query;
		}
		$this->statement->queryType($queryType);
		$this->statement->query($query);
		$this->lastStatement = clone $this->statement;
		
		if (!$this->statement)
		{
			$this->setFailed('nothing to execute');
		}
		if (!$this->statement->hasClauses())
		{
			$this->setFailed('nothing to execute');
		}
		else
		{
			if ($this->statement->isMultiquery())
			{
				$success = (bool)$this->Con->multiQuery($query);
			}
			else
			{
				$success = $this->Con->realQuery($query);
			}
			if ($this->hasEventListener($afterEvent))
			{
				$this->callAfterEventListener($afterEvent);
			}
			$this->resumeEvents();
			$this->lastInsertID = $this->Con->getLastInsertID();
			
			if ($this->loggerEnabled and Poesis::isLoggerEnabled())
			{
				$ModelData            = new stdClass();
				$ModelData->extraData = $this->extraLogData;
				$this->makeLog($queryType);
				$this->extraLogData  = [];
				$this->loggerEnabled = true;
			}
			$this->success = $success;
		}
		$this->reset(true);
		
		return $this;
	}
	
	private function doSelect($columns = null, bool $returnQuery = false)
	{
		//I wish all the PHP in the world is already on PHP8 for method typeCasting
		if (!is_string($columns) and !is_array($columns) and $columns !== null)
		{
			Poesis::error('columns must be either string,array or null');
		}
		if ($columns !== null and !$columns)
		{
			Poesis::error('Define select columns', ['providedColumns' => $columns]);
		}
		$this->checkClauseBothValues();
		$this->makeStatement();
		$this->statement->queryType("select");
		$query = QueryCompiler::select($this->statement, $columns);
		$this->statement->query($query);
		$this->lastStatement = clone $this->statement;
		if ($returnQuery)
		{
			$this->reset(true);
			
			return $query;
		}
		$drClass = $this->dataMethodsClassName;
		$r       = new $drClass($query, $this->Con);
		$r->setRowParsers($this->rowParsers);
		$r->onAfterQuery(function ()
		{
			$this->reset(true);
		});
		
		return $r;
	}
	//endregion
	
	//region query generators
	/**
	 * Get select query
	 *
	 * @param string|array $columns - columns to use in SELECT $columns FROM
	 *                              USE null OR *[string] - used to select all columns, string will be exploded by ,
	 * @return string
	 */
	public final function getSelectQuery($columns = null): string
	{
		return $this->doSelect($columns, true);
	}
	
	/**
	 * Duplicate values by Where
	 *
	 * @param array|null $overwrite
	 * @param array      $voidColumns - void columns on duplicate
	 * @return string
	 */
	public final function getDuplicateQuery(array $overwrite = [], array $voidColumns = []): ?string
	{
		return $this->doDuplicate($overwrite, $voidColumns, true);
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
		return $this->doEdit('update', true);
	}
	
	/**
	 * Get insert query
	 *
	 * @return string
	 */
	public final function getInsertQuery(): string
	{
		return $this->doEdit('insert', true);
	}
	
	/**
	 * Get replace query
	 *
	 * @return string
	 */
	public final function getReplaceQuery(): string
	{
		return $this->doEdit('replace', true);
	}
	
	/**
	 * Get delete query
	 *
	 * @return string
	 */
	public final function getDeleteQuery(): string
	{
		return $this->doEdit('delete', true);
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
	
	//region logging
	/**
	 * Void logging for current data transaction
	 *
	 * @return $this
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
	
	/**
	 * Overwritable method void log on specific data transactions
	 *
	 * @param array $setClauses
	 * @param array $whereClauses
	 * @return bool
	 */
	public function isLogActive(array $setClauses, array $whereClauses): bool
	{
		return true;
	}
	
	private final function makeLog(string $queryType): void
	{
		$logModelName = Poesis::getLogModel();
		/**
		 * @var \TDbLog $dbLog
		 */
		$dbLog = new $logModelName();
		$dbLog->voidLog();
		
		foreach ($this->statement->getClauses() as $clause)
		{
			if (!$this->isLogActive($clause->set, $clause->where))
			{
				return;
			}
			$LogData               = new stdClass();
			$LogData->setClauses   = [];
			$LogData->whereClauses = [];
			$LogData->extra        = $this->extraLogData;
			$LogData->trace        = Globals::getTrace();
			$LogData->time         = date('d.m.Y H:i:s');
			$LogData->phpInput     = file_get_contents('php://input');
			$LogData->POST         = Http::getPOST();
			$LogData->GET          = Http::getGET();
			$LogData->SessionID    = null;
			$LogData->SESSION      = null;
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
					$LogData->SERVER[$f] = $val;
				}
			}
			
			$TIDColumnName = $this->Schema::getTIDColumn();
			$TIDEnabled    = $this->Schema::isTIDEnabled();
			foreach ($clause->set as $expressions)
			{
				foreach ($expressions as $Node)
				{
					$column = $Node->getColumn();
					if (($TIDEnabled and $column != $TIDColumnName) or !$TIDEnabled)
					{
						$LogData->setClauses[$column] = $Node->getValue();
					}
				}
			}
			foreach ($clause->where as $groupIndex => $predicates)
			{
				foreach ($predicates as $valueIndex => $Node)
				{
					$column = $Node->getColumn();
					if (($TIDEnabled and $column != $TIDColumnName) or !$TIDEnabled)
					{
						$LogData->whereClauses[$groupIndex][$valueIndex][$column] = $Node->getValue();
					}
				}
			}
			
			$dbLog->data->compress(json_encode($LogData));
			$dbLog->userID(Poesis::getLogUserID());
			$dbLog->eventName($queryType);
			$dbLog->tableName($this->Schema::getTableName());
			
			if ($this->Schema::hasAIColumn())
			{
				$rowIDCols = [$this->Schema::getAIColumn()];
			}
			elseif ($this->Schema::hasPrimaryColumns())
			{
				$rowIDCols = $this->Schema::getPrimaryColumns();
			}
			else
			{
				$rowIDCols = [];
				foreach ($clause->where as $groupItems)
				{
					foreach ($groupItems as $Node)
					{
						$rowIDCols[] = $Node->getColumn();
					}
				}
			}
			if (isset($_SERVER['HTTP_HOST']))
			{
				$dbLog->url((isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : Http::getCurrentUrl());
			}
			$dbLog->ip(Http::getIP());
			
			if ($queryType !== 'delete')
			{
				$dbModifed = $this->model();
				if ($this->Schema::isTIDEnabled())
				{
					$dbModifed->add('TID', $this->lastStatement->TID());
				}
				elseif ($queryType == 'update')
				{
					foreach ($clause->where as $predicates)
					{
						foreach ($predicates as $whereField)
						{
							$addField = $whereField;
							foreach ($clause->set as $expressions)
							{
								foreach ($expressions as $setField)
								{
									/*
									 * it means in update where clause has been changes
									 * $db = new TAllFields();
									 * $db->varchar("newValue");
									 * $db->Where->varchar('oldValue');
									 * $db->collect();
									 */
									if ($setField->getColumn() == $whereField->getColumn())
									{
										$addField = $setField;
										break;
									}
								}
							}
							$dbModifed->WhereClause->add(1, $addField);
						}
					}
				}
				elseif ($this->Schema::hasAIColumn() and $clause->isLast)//one row were inserted
				{
					$aiColimn = $this->Schema::getAIColumn();
					$dbModifed->$aiColimn($this->lastInsertID);
				}
				else //insert, replace
				{
					if ($this->Schema::hasPrimaryColumns())
					{
						$uniqueColumns = $this->Schema::getPrimaryColumns();
					}
					elseif ($this->Schema::hasAIColumn())
					{
						$uniqueColumns = [$this->Schema::getAIColumn()];
					}
					else
					{
						$uniqueColumns = null;//can't identify row
					}
					
					if ($uniqueColumns)
					{
						foreach ($clause->set as $expressions)
						{
							foreach ($expressions as $setField)
							{
								if (in_array($setField->getColumn(), $uniqueColumns))
								{
									$dbModifed->WhereClause->add(1, $setField);
								}
							}
						}
					}
				}
				if (count($dbModifed->getWhereClausePredicates()) > 0)
				{
					$dbLog->rowIDColValues($dbModifed->select($rowIDCols)->implode(join(',', $rowIDCols)));
					$dbLog->rowIDCols(join(',', $rowIDCols));
				}
			}
			$dbLog->insert();
			$this->lastLogID = $dbLog->getLastSaveID();
		}
	}
	
	//endregion
	
	//region events
	/**
	 * Suspend/resume event listener
	 *
	 * @param bool        $toggle
	 * @param string|null $event if null, then all possible events will be toggled
	 *                           possible events beforeSave(insert,replace,update), afterSave(insert,replace,update), beforeUpdate, afterUpdate, beforeInsert, afterInsert, beforeReplace, afterReplace, beforeDelete, afterDelete
	 * @param string|null $group - toggle events in $group
	 * @return $this
	 */
	private final function toggleEvent(bool $toggle, string $event = null, string $group = null): Model
	{
		if ($event === null)
		{
			$this->toggleEvent($toggle, 'beforeSave', $group);
			$this->toggleEvent($toggle, 'afterSave', $group);
			$this->toggleEvent($toggle, 'beforeDelete', $group);
			$this->toggleEvent($toggle, 'afterDelete', $group);
			
			return $this;
		}
		elseif ($event === 'beforeSave')
		{
			$this->toggleEvent($toggle, 'beforeUpdate', $group);
			$this->toggleEvent($toggle, 'beforeInsert', $group);
			$this->toggleEvent($toggle, 'beforeReplace', $group);
			
			return $this;
		}
		elseif ($event === 'afterSave')
		{
			$this->toggleEvent($toggle, 'afterUpdate', $group);
			$this->toggleEvent($toggle, 'afterInsert', $group);
			$this->toggleEvent($toggle, 'afterReplace', $group);
			
			return $this;
		}
		$this->validateEvent($event);
		
		if (isset($this->eventListeners[$event]))
		{
			foreach ($this->eventListeners[$event] as $evKey => $evConfig)
			{
				if ($evConfig['group'] === $group or $group === null)
				{
					$this->eventListeners[$event][$evKey]['suspended'] = $toggle;
				}
			}
		}
		
		return $this;
	}
	
	/**
	 * Suspend events
	 *
	 * @param string      $event - possible events beforeSave(insert,replace,update), afterSave(insert,replace,update), beforeUpdate, afterUpdate, beforeInsert, afterInsert, beforeReplace, afterReplace, beforeDelete, afterDelete
	 * @param string|null $group - suspend events in $group
	 * @return $this
	 */
	public final function suspendEvent(string $event, string $group = null): Model
	{
		return $this->toggleEvent(true, $event, $group);
	}
	
	/**
	 * Suspend events
	 *
	 * @param string|null $group - suspend events in $group
	 * @return $this
	 */
	public final function suspendEvents(string $group = null): Model
	{
		return $this->toggleEvent(true, null, $group);
	}
	
	/**
	 * Resume events
	 *
	 * @param string      $event - possible events beforeSave(insert,replace,update), afterSave(insert,replace,update), beforeUpdate, afterUpdate, beforeInsert, afterInsert, beforeReplace, afterReplace, beforeDelete, afterDelete
	 * @param string|null $group - resume events in $group
	 * @return $this
	 */
	public final function resumeEvent(string $event, string $group = null): Model
	{
		return $this->toggleEvent(false, $event, $group);
	}
	
	/**
	 * Resume events
	 *
	 * @param string|null $group - resume events in $group
	 * @return $this
	 */
	public final function resumeEvents(string $group = null): Model
	{
		return $this->toggleEvent(false, null, $group);
	}
	
	/**
	 * Add event listener
	 *
	 * @param string|null     $event - if null all following events will be added
	 *                               possible events beforeSave(insert,replace,update), afterSave(insert,replace,update), beforeUpdate, afterUpdate, beforeInsert, afterInsert, beforeReplace, afterReplace, beforeDelete, afterDelete
	 * @param string|callable $listener
	 * @param string|null     $group - group event
	 * @return $this
	 */
	public final function on(?string $event, $listener, string $group = null): Model
	{
		if ($event === null)
		{
			$this->on('beforeSave', $listener, $group);
			$this->on('afterSave', $listener, $group);
			$this->on('beforeDelete', $listener, $group);
			$this->on('afterDelete', $listener, $group);
			
			return $this;
		}
		if ($event === 'beforeSave')
		{
			$this->on('beforeUpdate', $listener, $group);
			$this->on('beforeInsert', $listener, $group);
			$this->on('beforeReplace', $listener, $group);
			
			return $this;
		}
		elseif ($event === 'afterSave')
		{
			$this->on('afterUpdate', $listener, $group);
			$this->on('afterInsert', $listener, $group);
			$this->on('afterReplace', $listener, $group);
			
			return $this;
		}
		
		if (!is_callable($listener) and !is_string($listener))
		{
			Poesis::error('Event listener must be either string or callable');
		}
		
		$this->validateEvent($event);
		$this->eventListeners[$event][] = ['suspended' => false, 'listener' => $listener, 'group' => $group];
		
		return $this;
	}
	
	private final function hasEventListener(string $event): bool
	{
		return isset($this->eventListeners[$event]);
	}
	
	private final function callBeforeEventListener(string $event)
	{
		$output = true;
		foreach ($this->eventListeners[$event] as $evConf)
		{
			if ($evConf['suspended'])
			{
				continue;
			}
			$listener = $evConf['listener'];
			
			if (is_array($listener))
			{
				$output = call_user_func_array($listener, [$event]);
			}
			elseif (is_string($listener))
			{
				$output = $this->$listener($event);
			}
			else
			{
				$output = $listener($event);
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
				Poesis::error("$event event lister must return bool", ['returned' => $output, '$listenr' => $listener]);
			}
		}
		
		return $output;
	}
	
	private final function callAfterEventListener(string $event)
	{
		foreach ($this->eventListeners[$event] as $evConf)
		{
			if ($evConf['suspended'])
			{
				continue;
			}
			$listener = $evConf['listener'];
			
			if (is_array($listener))
			{
				call_user_func_array($listener, []);
			}
			elseif (is_string($listener))
			{
				$this->$listener();
			}
			else
			{
				$listener();
			}
		}
	}
	
	private function validateEvent(string $event)
	{
		if (!in_array($event, ['beforeUpdate', 'afterUpdate', 'beforeInsert', 'afterInsert', 'beforeReplace', 'afterReplace', 'beforeDelete', 'afterDelete']))
		{
			Poesis::error("unknown event $event");
		}
	}
	//endregion
	
	//region model data manipulators
	/**
	 * Will be used on fetching data, thil will be passed on to DataMethods
	 *
	 * @param callable $parser
	 * @param array    $arguments
	 * @return $this
	 */
	public function addRowParser(callable $parser, array $arguments = []): Model
	{
		$this->rowParsers[] = (object)['parser' => $parser, 'arguments' => $arguments];
		
		return $this;
	}
	
	/**
	 * Will be used as soon as Clause receive column value
	 *
	 * @param string   $column
	 * @param callable $callable
	 * @param array    $arguments
	 * @return $this
	 */
	public function addClauseColumnValueParser(string $column, callable $callable, array $arguments = []): Model
	{
		$this->Clause->addValueParser($column, $callable, $arguments);
		
		return $this;
	}
	
	/**
	 * Will be used as soon as WhereClause receive column value
	 *
	 * @param string   $column
	 * @param callable $callable
	 * @param array    $arguments
	 * @return $this
	 */
	public function addWhereClauseColumnValueParser(string $column, callable $callable, array $arguments = []): Model
	{
		$this->WhereClause->addValueParser($column, $callable, $arguments);
		
		return $this;
	}
	
	/**
	 * Will convert integer to integer, (float,double,real,decimal) to float, and so on
	 * In case of interger type will
	 *
	 * @param string $column
	 * @param        $value
	 * @return float|int|mixed
	 */
	public function fixValueByColumnType(string $column, $value)
	{
		$type     = $this->Schema::getType($column);
		$coreType = $this->Schema::getCoreType($column);
		if ($coreType == 'int')
		{
			return intval($value);
		}
		elseif ($coreType == 'float')
		{
			return floatval($value);
		}
		elseif ($type == 'date')
		{
			return Date::from($value)->toSqlDate();
		}
		elseif (in_array($type, ['datetime', 'timestamp']))
		{
			return Date::from($value)->toSqlDateTime();
		}
		
		return $value;
	}
	//endregion
	
	//region other helpers
	public final function haltReset(): Model
	{
		$this->haltReset = true;
		
		return $this;
	}
	
	/**
	 * reset all flags
	 *
	 * @param bool $resetStatement
	 * @return $this
	 */
	public final function reset(bool $resetStatement = false): Model
	{
		if (!$this->haltReset)
		{
			$this->Clause->flush();
			$this->WhereClause->flush();
			$this->rowParsers   = [];
			$this->__groupIndex = -1;
			$this->__isCloned   = false;
			$this->clauseType   = 'normal';
			if ($resetStatement)
			{
				$this->statement = null;
			}
		}
		$this->haltReset = false;
		
		return $this;
	}
	
	/**
	 * Store data for multiple query
	 *
	 * @return $this
	 */
	public final function collect(): Model
	{
		$this->makeStatement(true);
		$this->reset();
		
		return $this;
	}
	
	/**
	 * Get last executed sql query
	 *
	 * @return string
	 */
	public final function getLastQuery(): string
	{
		return $this->lastStatement->query();
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
		return $this->failMsg;
	}
	
	/**
	 * Makes new model object
	 *
	 * @param array $options
	 * return Model
	 */
	public function model(array $options = []): Model
	{
		if (!isset($options['connection']))
		{
			$options['connection'] = &$this->Con;
		}
		
		return $this->Schema::makeModel($options);
	}
	
	public function __clause(): Clause
	{
		$cl = $this->clauseType == 'normal' ? 'Clause' : 'WhereClause';
		$th = $this->origin ?: $this;
		
		return $th->$cl;
	}
	
	private final function makeStatement(bool $isCollect = false)
	{
		if (!$this->statement)
		{
			$this->statement = new Statement();
			$this->statement->table($this->Schema::getTableName());
			$this->statement->model($this->Schema::getModelName());
		}
		$this->statement->orderBy($this->getOrderBy());
		$this->statement->limit($this->getLimit());
		$this->statement->groupBy($this->getGroupBy());
		
		if (!$this->WhereClause->hasValues() and !$this->Clause->getValues())
		{
			return;
		}
		
		if ($isCollect)
		{
			$this->statement->collect($this->WhereClause->getValues(), $this->Clause->getValues(), $this->Clause->getColumns());
		}
		else
		{
			$this->statement->replace($this->WhereClause->getValues(), $this->Clause->getValues(), $this->Clause->getColumns());
		}
	}
	
	/**
	 * @return $this
	 */
	
	/**
	 * @param string $msg
	 * @return $this
	 */
	private final function setFailed(string $msg): Model
	{
		$this->success = false;
		$this->failMsg = $msg;
		
		return $this;
	}
	
	/**
	 * Get claueses for where
	 *
	 * @return array
	 */
	public function getWhereClausePredicates(): array
	{
		if ($this->WhereClause->hasValues())
		{
			return $this->WhereClause->getValues();
		}
		else
		{
			return $this->Clause->getValues();
		}
	}
	
	private function checkClauseBothValues()
	{
		if ($this->Clause->hasValues() and $this->WhereClause->hasValues())
		{
			Poesis::error('WhereClause and Clause both cant have values at the same time');
		}
	}
	
	//endregion
	
	//region data getters
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
	 * If table has only one primary column, and it is auto increment then int is returned
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
		if (in_array($this->lastStatement->queryType(), ['insert', 'replace']))
		{
			return $this->lastInsertID;
		}
		$primField = $this->Schema::getAIColumn();
		
		return $this->getAffectedRecordModel()->select($primField)->getValue($primField, 0);
	}
	
	public final function getLastLogID(): ?int
	{
		return $this->lastLogID;
	}
	
	/**
	 * get last edited affected row
	 *
	 * @param null $columns
	 * @return stdClass|null
	 */
	public final function getAffectedRecord($columns = null): ?stdClass
	{
		return $this->getAffectedRecordModel()->limit(1)->select($columns)->getObject();
	}
	
	/**
	 * Get last edited affected rows model
	 * If table has only one primary column, and it is auto increment then int is returned
	 * If table has multiple primary fields then object containing primary column values is returned
	 *
	 * @return $this
	 */
	public final function getAffectedRecordModel(): Model
	{
		$queryType = $this->lastStatement->queryType();
		if (in_array($queryType, ['delete', 'select']))
		{
			Poesis::error("Cannot get object on $queryType");
		}
		$db = $this->model();
		$ok = false;
		if ($this->Schema::isTIDEnabled())
		{
			$tidColumnName = $this->Schema::getTIDColumn();
			$db->Where->$tidColumnName($this->lastStatement->TID());
			$ok = true;
		}
		elseif ($queryType == 'update') //get last modifed rows via updated clauses
		{
			$index = 0;
			$this->lastStatement->each("selectModifed", function ($clause) use (&$db, &$index)
			{
				foreach ($clause->where as $predicates)
				{
					foreach ($predicates as $whereField)
					{
						$addField = $whereField;
						foreach ($clause->set as $expressions)
						{
							foreach ($expressions as $setField)
							{
								/*
								 * it means in update where clause has been changes
								 * $db = new TAllFields();
								 * $db->varchar("newValue");
								 * $db->Where->varchar('oldValue');
								 * $db->collect();
								 */
								if ($setField->getColumn() == $whereField->getColumn())
								{
									$addField = $setField;
									break;
								}
							}
						}
						$db->WhereClause->add($index, $addField);
					}
				}
				$index++;
				if (!$clause->isLast)
				{
					$db->WhereClause->addOperator($index, new LogicalOperator('OR'));
					$index++;
				}
			});
			$ok = $db->WhereClause->hasValues();
		}
		elseif ($this->Schema::hasAIColumn() and count($this->lastStatement->getClauses()) == 1)//one row were inserted
		{
			$aiColimn = $this->Schema::getAIColumn();
			$db->Where->$aiColimn($this->lastInsertID);
			$ok = true;
		}
		else //insert,replace
		{
			$uniqueColumns = [];
			if ($this->Schema::hasPrimaryColumns())
			{
				$uniqueColumns = $this->Schema::getPrimaryColumns();
			}
			elseif ($this->Schema::hasAIColumn())
			{
				$uniqueColumns = [$this->Schema::getAIColumn()];
			}
			else
			{
				Poesis::error('cant fetch last modifed record, no unique identifer detected');
			}
			
			$index = 0;
			$this->lastStatement->each('selectModifed', function ($clause) use (&$uniqueColumns, &$db, &$index)
			{
				foreach ($clause->set as $expressions)
				{
					foreach ($expressions as $setField)
					{
						if (in_array($setField->getColumn(), $uniqueColumns))
						{
							$db->WhereClause->add($index, $setField);
						}
					}
				}
				$index++;
				if (!$clause->isLast)
				{
					$db->WhereClause->addOperator($index, new LogicalOperator('OR'));
					$index++;
				}
			});
			$ok = $db->WhereClause->hasValues();
		}
		if (!$ok)
		{
			Poesis::error('cant fetch last modifed record, no unique identifer detected');
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
		$db       = $this->model();
		$maxValue = (int)$this->Con->dr($db->getSelectQuery("max($maxField) AS curentMaxFieldValue"))->getValue('curentMaxFieldValue', 0);
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
		$this->checkClauseBothValues();
		$t->WhereClause->setValues($this->getWhereClausePredicates());
		$query               = $t->getSelectQuery();
		$this->lastStatement = $t->lastStatement;
		$query               = "SELECT COUNT(*) as count FROM ($query) AS c";
		$this->lastStatement->query($query);
		$this->reset(true);
		
		//https://stackoverflow.com/questions/16584549/counting-number-of-grouped-rows-in-mysql
		return intval($this->Con->dr($query)->getValue('count', 0));
	}
	
	public final function debug($fields = false): void
	{
		$this->select($fields)->debug();
	}
	//endregion
}