<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\dr\DataRetrieval;
use stdClass;
use ArrayObject;
use Infira\Poesis\Poesis;
use Infira\Utils\Regex;
use Infira\Utils\Session;
use Infira\Utils\Http;
use Infira\Poesis\Connection;
use Infira\Poesis\ConnectionManager;
use Infira\Fookie\facade\Variable;
use Infira\Poesis\orm\node\FieldNode;
use Infira\Poesis\orm\node\QueryNode;
use MongoDB\Driver\Query;

/**
 * A class to provide simple db query functions, update,insert,delet, aso.
 *
 * @property Model         $Where
 * @property QueryCompiler $QueryCompiler
 */
class Model
{
	use \PoesisModelExtendor;
	
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
	 * Defines last inserted primary field value getted by mysqli_insert_id();
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
	
	/**
	 * @var Schema
	 */
	public $Schema;
	
	/**
	 * @var FieldCollection
	 */
	public $Fields;
	
	// For multiqueries
	private $collection = [];
	
	public function __construct(Connection $Con = null, string $schemaName)
	{
		$this->lastFields = new stdClass();
		if (is_null($Con))
		{
			$this->Con = ConnectionManager::default();
		}
		$this->Schema = $schemaName;
		$this->Schema::construct();
		$this->Fields = new FieldCollection($this->Schema);
		$this->initExtension();
	}
	
	public function initExtension() { }
	
	/**
	 * Magic method __get()
	 *[
	 *
	 * @param $name
	 * @see https://www.php.net/manual/en/language.oop5.overloading.php#object.get
	 * @return Field
	 */
	public final function __get($name)
	{
		if ($name == "QueryCompiler")
		{
			$this->QueryCompiler = new QueryCompiler($this);
		}
		elseif ($name == "Where")
		{
			$this->Where = new $this();
			//$this->Where = $this->Where->Fields;
		}
		elseif ($this->Schema::checkField($name))
		{
			if (!$this->__isCloned)
			{
				$this->__increaseGroupIndex();
			}
			
			return new Field($this, $name);
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
		if (in_array($name, ['Where', 'QueryCompiler']))
		{
			$this->$name = $value;
		}
		elseif ($this->Schema::checkField($name))
		{
			$this->$name->add($value);
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
	
	public function __increaseGroupIndex()
	{
		$this->__groupIndex++;
	}
	
	//################################################################# END OF setters and getters
	
	
	//################################################################# START OF flags
	
	/**
	 * Set a order flag to select sql query
	 *
	 * @param string $order
	 * @return Model
	 */
	public final function order(string $order): Model
	{
		$this->___orderBy = $order;
		
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
	 * Set a broup BY flag to select sql query
	 *
	 * @param string $group
	 * @return Model
	 */
	public final function group(string $group): Model
	{
		$this->___groupBy = $group;
		
		return $this;
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
	 * Add logical OR operator to query
	 *
	 * @return $this
	 */
	public final function or()
	{
		return $this->addOperator('OR');
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
	
	private final function addOperator(string $op)
	{
		if (!$this->__isCloned)
		{
			$this->__increaseGroupIndex();
		}
		$this->Fields->addOperator($this->__groupIndex, $op);
		
		return $this;
	}
	
	/**
	 * Set a flag do not null field values after sql action
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
	public function nullFields($forceNull = false): Model
	{
		if ($this->nullFieldsAfterAction == true or $forceNull == true)
		{
			$this->Fields->nullFields();
			$this->Where->Fields->nullFields();
			$this->RowParser    = false;
			$this->__groupIndex = -1;
		}
		
		return $this;
	}
	
	//################################################################# END OF flags
	
	
	//################################################################# START OF field and where setters
	
	/**
	 * Set were cluasel
	 *
	 * @param string $field
	 * @param mixed  $value
	 * @return Model
	 */
	public final function where(string $field, $value): Model
	{
		$this->Where->$field->add($value);
		
		return $this;
	}
	
	/**
	 * Map fields
	 *
	 * @param array|object $fields
	 * @param array        $voidFields
	 * @param array        $overWrite
	 * @return $this
	 */
	public final function map($fields, $voidFields = [], array $overWrite = [])
	{
		$fields     = array_merge(Variable::toArray($fields), Variable::toArray($overWrite));
		$voidFields = Variable::toArray($voidFields);
		if (checkArray($fields))
		{
			foreach ($fields as $f => $value)
			{
				if (!in_array($f, $voidFields) and $this->Schema::fieldExists($f))
				{
					addExtraErrorInfo('$f$f$f', $f);
					addExtraErrorInfo('$value$value$value', $value);
					$this->add($f, $value);
				}
			}
		}
		
		return $this;
	}
	
	
	/**
	 * Map Where ID
	 *
	 * @param array $fields
	 * @return $this
	 */
	public final function mapID($fields)
	{
		$fields = new ArrayObject($fields);
		if (isset($fields["ID"]))
		{
			if (intval($fields["ID"]) > 0)
			{
				$this->Where->ID->add($fields["ID"]);
			}
		}
		
		return $this;
	}
	
	public function add(string $field, $value)
	{
		$fieldNode = new FieldNode($field);
		if ($this->__isCloned)
		{
			$this->Fields->add($this->__groupIndex, $fieldNode, $value);
			
			return $this;
		}
		else
		{
			$this->__increaseGroupIndex();
			$t             = clone $this;
			$t->__isCloned = true;
			$this->Fields->add($this->__groupIndex, $fieldNode, $value);
			
			return $t;
		}
	}
	
	/**
	 * Add raw sql to final query
	 *
	 * @param $query
	 * @return $this
	 */
	public final function raw($query)
	{
		return $this->add(QueryCompiler::RAW_QUERY_FIELD, $query);
	}
	
	private function isCollection(): bool
	{
		return checkArray($this->collection);
	}
	
	public function getCollectionValues()
	{
		return $this->collection['values'];
	}
	
	/**
	 * Store data for multiple query
	 *
	 * @return $this
	 */
	public final function collect()
	{
		$fields = $this->Fields->getFields();
		if (!isset($this->collection["checkFields"]))
		{
			$this->collection["checkFields"] = $fields;
		}
		else
		{
			if ($fields != $this->collection["checkFields"])
			{
				Poesis::error("field order/count must match first field count");
			}
		}
		if (!isset($this->collection["values"]))
		{
			$this->collection["values"] = [];
		}
		$this->collection["values"][] = (object)['fields' => $this->Fields->getValues(), 'where' => $this->Where->Fields->getValues()];
		$this->nullFields(true);
		
		return $this;
	}
	
	//################################################################# END OF setters
	
	
	//################################################################# START OF data getters
	
	/**
	 * Get last executed sql query
	 *
	 * @return string
	 */
	public final function getLastQuery(): string
	{
		return $this->lastQuery;
	}
	
	/**
	 * Get last updated primary field values
	 * If table has only one primary field and it is auto increment then int is returned
	 * If table has multiple primary fields then object containing primary field values is returned
	 *
	 * @return null|int
	 */
	public final function getLastSaveID()
	{
		if (!$this->Schema::hasAIField())
		{
			Poesis::error("table " . $this->Schema::getTableName() . " does not have AUTO_INCREMENT field");
		}
		if (in_array($this->lastQueryType, ['insert', 'replace']))
		{
			return $this->lastInsertID;
		}
		$primField = $this->Schema::getAIField();
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
	 * Get last updated primary field values
	 * If table has only one primary field and it is auto increment then int is returned
	 * If table has multiple primary fields then object containing primary field values is returned
	 *
	 * @param bool $fields
	 * @return array|bool|int|mixed
	 */
	private function getLastRecord($fields = false)
	{
		if ($this->lastQueryType == 'delete')
		{
			Poesis::error("Cannot get object on deletion");
		}
		$Db = $this->Schema::getClassObject();
		$Db->limit(1);
		if ($this->Schema::hasAIField())
		{
			$primaryField = $this->Schema::getAIField();
			$Db->order("$primaryField DESC");
			
			if (in_array($this->lastQueryType, ['insert', 'replace']))
			{
				$Db->$primaryField($this->lastInsertID);
			}
			else //update
			{
				$Db->Fields->setValues($this->lastFields->where);
			}
			
			return $Db->select($fields)->getObject();
		}
		else
		{
			if (in_array($this->lastQueryType, ['insert', 'replace']))
			{
				$Db->Fields->setValues($this->lastFields->fields);
				
			}
			else //update
			{
				$Db->Fields->setValues($this->lastFields->where);
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
	 * Get next orderNr field
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
		$maxValue = (int)$this->Con->dr($this->getSelectQuery("max($maxField) AS curentMaxFieldValue"))->getFieldValue("curentMaxFieldValue");
		$maxValue++;
		
		return $maxValue;
	}
	
	/**
	 * Counts mysql resource rows
	 *
	 * @return int
	 */
	public final function count()
	{
		$t = new $this();
		$t->Fields->setValues($this->Fields->getValues());
		$t->Where->Fields->setValues($this->Where->Fields->getValues());
		$sql = $t->getSelectQuery();
		
		//use that way cause of grouping https://stackoverflow.com/questions/16584549/counting-number-of-grouped-rows-in-mysql
		return intval($this->Con->dr("SELECT COUNT(*) as count FROM ($sql) AS c")->getFieldValue("count", 0));
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
	
	
	//################################################################# END OF data getters
	
	
	//################################################################# START OF Insert,update,delete,truncate functions
	
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
		if ($this->Where->Fields->hasValues() and $this->Fields->hasValues())
		{
			$DbCurrent->map($this->Where->Fields->getValues());
		}
		elseif (!$this->Where->Fields->hasValues() and $this->Fields->hasValues())
		{
			$DbCurrent->map($this->Fields->getValues());
		}
		
		$DbNew               = new $className;
		$voidFields          = $this->Schema::getPrimaryFields();
		$extraFieldsIsSetted = $this->Where->Fields->hasValues();
		$DbCurrent->select()->each(function ($CurrentRow) use (&$DbNew, $voidFields, $extraFieldsIsSetted, &$overwrite)
		{
			$DbNew->map($CurrentRow, $voidFields);
			if ($extraFieldsIsSetted)
			{
				$DbNew->map($this->Fields->getValues(), $voidFields);
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
	function truncate()
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
	public final function doAutoSave($mapData = null, bool $returnQuery = false)
	{
		/*
		if (!$this->Fields->canAutosave())
		{
			addExtraErrorInfo('fields', $this->Fields->getValues());
			Poesis::error("Multiple items per field is addedd");
		}
		*/
		
		if ($this->isCollection())
		{
			Poesis::error("autosave does not work on collections");
		}
		if ($mapData)
		{
			$this->map($mapData);
		}
		if ($this->Fields->hasValues() and !$this->Where->Fields->hasValues()) //no where is detected then has to decide based primary fields whatever insert or update
		{
			if ($this->Schema::hasPrimaryFields())
			{
				$className    = $this->Schema::getClassName();
				$settedValues = $this->Fields->getValues();
				/**
				 * @var Model $CheckWhere
				 */
				$CheckWhere = new $className();
				$values     = $this->Fields->getValues();
				$c          = count($values);
				if ($c > 1)
				{
					foreach ($values as $groupIndex => $groupItems)
					{
						if (count($groupItems) > 1)
						{
							alert('Cant have multime items in group on autoSave');
						}
						$Node = $groupItems[0];
						$f    = $Node->getField();
						if ($this->Schema::isPrimaryField($f))
						{
							$CheckWhere->$f->add($Node);
							unset($values[$groupIndex]);
						}
					}
				}
				else
				{
					$newValues = [];
					foreach ($values[0] as $Node)
					{
						$f = $Node->getField();
						if ($this->Schema::isPrimaryField($f))
						{
							$CheckWhere->$f->add($Node);
						}
						else
						{
							$newValues[] = $Node;
						}
					}
					$values = [$newValues];
				}
				if ($CheckWhere->Fields->hasValues())
				{
					$CheckWhere->dontNullFields();
					if ($CheckWhere->hasRows())
					{
						$this->Fields->setValues($values);
						$this->Where->Fields->setValues($CheckWhere->Fields->getValues());
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
						$this->Fields->setValues($settedValues);
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
		}
		else //update
		{
			$cloned = clone $this;
			$cloned->dontNullFields();
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
		
		return $this;
	}
	
	/**
	 * Runs a sql replace query width setted values
	 *
	 * @return bool - success
	 */
	public final function replace(): bool
	{
		return $this->execute('replace', $this->compile('replace'));
	}
	
	/**
	 * Runs a sql insert query width setted values
	 *
	 * @return bool - success
	 */
	public final function insert(): bool
	{
		return $this->execute('insert', $this->compile('insert'));
	}
	
	/**
	 * Runs a sql update query width setted values
	 *
	 * @return bool - success
	 */
	public final function update(): bool
	{
		return $this->execute('update', $this->compile('update'));
	}
	
	/**
	 * Runs a sql delete query with setted values
	 *
	 * @return bool - success
	 */
	public final function delete(): bool
	{
		$continue = true;
		if (method_exists($this, 'beforeDelete'))
		{
			$continue = $this->beforeDelete();
		}
		if ($continue === false)
		{
			return false;
		}
		if ($this->isCollection())
		{
			Poesis::error('Can\'t delete collection');
		}
		
		return $this->execute('delete', $this->compile('delete'));
	}
	
	/**
	 * Construct SQL query
	 *
	 * @param string       $queryType    - update,insert,replace,select
	 * @param string|array $selectFields - fields to use in SELECT $selectFields FROM, * - use to select all fields, otherwise it will be exploded by comma
	 * @return QueryNode
	 */
	private final function compile(string $queryType, $selectFields = '*'): QueryNode
	{
		$QueryNode = new QueryNode();
		if (in_array($queryType, ['insert', 'replace'], true) and $this->Where->Fields->hasValues())
		{
			Poesis::error("->Where cannot have values during insert/replace query");
		}
		
		$query = false;
		if (!in_array($queryType, ['select', 'insert', 'replace', 'delete', 'update']))
		{
			Poesis::error("Unknown query type $queryType");
		}
		if ($this->isCollection() and in_array($queryType, ['select', 'delete']))
		{
			Poesis::error(strtoupper($queryType) . ' querytype is not implemented in case of collection');
		}
		
		$QueryNode->table        = $this->Schema::getTableName();
		$QueryNode->selectFields = $selectFields;
		$QueryNode->isCollection = $this->isCollection();
		$QueryNode->orderBy      = $this->getOrderBy();
		$QueryNode->limit        = $this->getLimit();
		$QueryNode->groupBy      = $this->getGroupBy();
		if ($this->isCollection())
		{
			$QueryNode->collectionValues = $this->getCollectionValues();
		}
		
		if (in_array($queryType, ['select', 'delete']))
		{
			if (!$this->Where->Fields->hasValues() and $this->Fields->hasValues())
			{
				$this->Where->Fields->setValues($this->Fields->getValues());
			}
			$QueryNode->fields = [];
			$QueryNode->where  = $this->Where->Fields->getValues();
		}
		else
		{
			$QueryNode->fields = $this->Fields->getValues();
			$QueryNode->where  = $this->Where->Fields->getValues();
		}
		
		if ($queryType == 'select')
		{
			if ($this->RowParser !== false)
			{
				$QueryNode->RowParser = $this->RowParser;
			}
			$query = $this->QueryCompiler->select($QueryNode);
		}
		elseif ($queryType == 'update')
		{
			$query = $this->QueryCompiler->update($QueryNode);
		}
		elseif ($queryType == 'delete')
		{
			$query = $this->QueryCompiler->delete($QueryNode);
		}
		elseif ($queryType == 'insert' or $queryType == 'replace')
		{
			$query = $this->QueryCompiler->$queryType($QueryNode);
		}
		$QueryNode->query = $query;
		$this->nullFields();
		$this->nullFieldsAfterAction = true;
		
		
		return $QueryNode;
	}
	
	
	/**
	 * Construct SQL query
	 *
	 * @param string    $queryType - update,insert,replace,select
	 * @param QueryNode $queryNode
	 * @return mixed
	 */
	private final function execute(string $queryType, QueryNode $queryNode)
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
			$continue = true;
			if (method_exists($this, $beforeEvent))
			{
				$continue = $this->$beforeEvent();
			}
			if ($continue === false)
			{
				return false;
			}
		}
		
		if ($queryType == 'select')
		{
			$Dr = $this->Con->dr($queryNode->query);
			if ($queryNode->RowParser !== null)
			{
				$Dr->setRowParser($queryNode->RowParser->rowParserCallback, $queryNode->RowParser->rowParserScope, $queryNode->RowParser->rowParserArguments);
			}
			$output = $Dr;
		}
		elseif ($this->isCollection())
		{
			$this->Con->multiQuery($queryNode->query);
			if (method_exists($this, $afterEvent))
			{
				$this->$afterEvent($queryNode);
			}
			$output             = true;
			$this->lastInsertID = $this->Con->getLastInsertID();
			$this->lastFields   = $this->collection["values"][array_key_last($this->collection["values"])];
		}
		else
		{
			$output = $this->Con->realQuery($queryNode->query);
			if (method_exists($this, $afterEvent))
			{
				$this->$afterEvent($queryNode);
			}
			$this->lastInsertID = $this->Con->getLastInsertID();
			$this->lastFields   = (object)["fields" => $queryNode->fields, "where" => $queryNode->where];
			
		}
		
		$this->lastQuery     = $queryNode->query;
		$this->lastQueryType = $queryType;
		$this->collection    = [];
		
		if ($queryType != 'select')
		{
			$this->makeLog($queryType, $queryNode, $this->isCollection());
		}
		
		
		return $output;
	}
	
	
	//################################################################# ENF OF Insert,update,delete,truncate functions
	
	
	//################################################################# START OF other helpers
	/**
	 * Get select query
	 *
	 * @param string|array $selectFields - fields to use in SELECT $selectFields FROM, * - use to select all fields, otherwise it will be exploded by comma
	 * @return string
	 */
	public final function getSelectQuery($selectFields = null): string
	{
		return $this->compile('select', $selectFields)->query;
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
		return $this->compile('update')->query;
	}
	
	/**
	 * Get insert query
	 *
	 * @return string
	 */
	public final function getInsertQuery(): string
	{
		return $this->compile('insert')->query;
	}
	
	/**
	 * Get replace query
	 *
	 * @return string
	 */
	public final function getReplaceQuery(): string
	{
		return $this->compile('replace')->query;
	}
	
	/**
	 * Get delete query
	 *
	 * @return string
	 */
	public final function getDeleteQuery(): string
	{
		return $this->compile('delete')->query;
	}
	//################################################################# END OF other helpers
	
	
	//################################################################# START OF hepers
	private $RowParser = false;
	
	public final function setRowParser($parser, $class = false, $arguments = [])
	{
		$this->RowParser                     = new stdClass();
		$this->RowParser->rowParserCallback  = $parser;
		$this->RowParser->rowParserScope     = $class;
		$this->RowParser->rowParserArguments = (!is_array($arguments)) ? [] : $arguments;
		
		return $this;
	}
	
	/**
	 * Select data from database
	 *
	 * @param string|array $fields - fields to use in SELECT $fields FROM, * - use to select all fields, otherwise it will be exploded by comma
	 * @return DataRetrieval
	 */
	public final function select($fields = null): DataRetrieval
	{
		return $this->execute('select', $this->compile('select', $fields));
	}
	
	public final function debug($fields = false): void
	{
		$this->select($fields)->debug();
	}
	
	/**
	 * Debug current sql query
	 *
	 * @param bool|string|array $fields - false means *
	 */
	public final function debugQuery($fields = false): void
	{
		debug($this->getSelectQuery($fields));
	}
	
	//############################################# Lgger
	
	
	public $voidTablesToLog = [];
	
	private $loggerEnabled = true;
	
	private $extraLogData = [];
	
	/**
	 * Void logs for current model transactions <br>
	 * If Poesis::isLoggerEnabled() == false, then it doesnt matter
	 *
	 * @return void
	 */
	public final function voidLog()
	{
		$this->loggerEnabled = false;
	}
	
	/**
	 * Add extra log data
	 *
	 * @param string $name - data key name
	 * @param mixed  $data
	 * @return void
	 */
	public function addLogData(string $name, $data): void
	{
		$this->extraLogData[$name] = $data;
	}
	
	/**
	 * @param string    $queryType
	 * @param QueryNode $queryNode
	 * @param bool      $isCollect
	 * @throws \Infira\Poesis\PoesisError
	 * @throws \Infira\Utils\Error
	 */
	public function makeLog(string $queryType, QueryNode $queryNode, bool $isCollect = false): void
	{
		if (!Poesis::isLoggerEnabled())
		{
			return;
		}
		if (!$this->loggerEnabled)
		{
			return;
		}
		$tableName = $this->Schema::getTableName();
		if (!in_array($tableName, $this->voidTablesToLog))
		{
			if ($isCollect)
			{
				Poesis::error("collection is not implemented");
			}
			else
			{
				if (checkArray($queryNode->fields))
				{
					foreach ($queryNode->fields as $groupIndex => $groupItems)
					{
						foreach ($groupItems as $valueIndex => $Node)
						{
							$queryNode->fields[$groupIndex][$valueIndex] = $Node->get();
						}
					}
				}
				if (checkArray($queryNode->where))
				{
					foreach ($queryNode->where as $groupIndex => $groupItems)
					{
						foreach ($groupItems as $valueIndex => $Node)
						{
							$where[$groupIndex][$valueIndex] = $Node->get();
						}
					}
				}
			}
			$LogData         = new stdClass();
			$LogData->fields = $queryNode->fields;
			$LogData->where  = $queryNode->where;
			$ok              = Poesis::isLogOkForTableFields($tableName, $queryNode->fields, $queryNode->where);
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
					if ($this->Schema::hasAIField())
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
				$Db->tableName($tableName);
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
}

?>