<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\dr\DataRetrieval;
use stdClass;
use ArrayObject;
use Infira\Poesis\Poesis;
use Infira\Utils\Regex;
use Infira\Utils\Session;
use Infira\Utils\Http;
use Infira\Utils\Variable;
use Infira\Poesis\Connection;
use Infira\Poesis\ConnectionManager;
use Infira\Utils\ClassFarm;

/**
 * A class to provide simple db query functions, update,insert,delet, aso.
 *
 * @property FieldCollection $Fields
 * @property Schema          $Schema
 * @property Model           $Where
 * @property QueryCompiler   $QueryCompiler
 */
class Model
{
	use \PoesisModelExtendor;
	
	protected $_className;
	protected $_schemaClassName;
	
	/**
	 * Set flag to return fieldMethods as sequnce instead of Infira\Poesis\Field
	 *
	 * @var bool
	 */
	protected $fieldSequence = false;
	
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
	 * public $Schema;
	 */
	
	// For multiqueries
	private $collection = [];
	
	public function __construct(Connection $Con = null)
	{
		$this->lastFields = new stdClass();
		$this->initExtension();
		if (is_null($Con))
		{
			$this->Con = ConnectionManager::default();
		}
	}
	
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
		elseif ($name == "Fields")
		{
			$this->Fields = new FieldCollection($this);
		}
		elseif ($name == "Where")
		{
			$this->Where = new $this();
			//$this->Where = $this->Where->Fields;
		}
		elseif ($name == "Schema")
		{
			$c           = $this->_schemaClassName;
			$this->$name = ClassFarm::instance("PoesisModelSchema$c", $c);//make sure that schema is built once
		}
		elseif ($this->Schema->checkField($name))
		{
			return $this->Fields->getField($name);
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
		if (in_array($name, ['Where', 'Fields', 'QueryCompiler', 'Schema']))
		{
			$this->$name = $value;
		}
		elseif ($this->Schema->checkField($name))
		{
			$this->$name->set($value);
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
	
	/**
	 * Get current model db table name
	 *
	 * @return string
	 */
	public final function getTableName(): string
	{
		return $this->Schema->getTableName();
	}
	
	public final function setAsFieldSequence()
	{
		$this->fieldSequence = true;
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
		$this->Fields->addOperator("or");
		
		return $this;
	}
	
	/**
	 * Add Logical AND operator to query
	 *
	 * @return $this
	 */
	public final function and()
	{
		$this->Fields->addOperator("and");
		
		return $this;
	}
	
	/**
	 * Add XOR operator to query
	 *
	 * @return $this
	 */
	public final function xor()
	{
		$this->Fields->addOperator("xor");
		
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
			$this->RowParser = false;
		}
		
		return $this;
	}
	
	//################################################################# END OF flags
	
	
	//################################################################# START OF field and where setters
	/**
	 * Alias to FieldCollection->set
	 *
	 * @param string $field
	 * @param mixed  $value
	 * @return Model
	 */
	public final function set(string $field, $value): Model
	{
		$this->Fields->$field->set($value);
		
		return $this;
	}
	
	/**
	 * Set were cluasel
	 *
	 * @param string $field
	 * @param mixed  $value
	 * @return Model
	 */
	public final function where(string $field, $value): Model
	{
		$this->Where->$field->set($value);
		
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
		$this->Fields->map($fields, $voidFields, $overWrite);
		
		return $this;
	}
	
	
	/**
	 * Add raw sql to final query
	 *
	 * @param $query
	 * @return $this
	 */
	public final function raw($query)
	{
		$field = QueryCompiler::RAW_QUERY_FIELD;
		$this->Fields->$field->raw($query);
		
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
				$this->Where->set("ID", $fields["ID"]);
			}
		}
		
		return $this;
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
		$fields       = $this->Fields->getValues();
		$whereFields  = $this->Where->Fields->getValues();
		$settedValues = (object)['fields' => $fields, 'where' => $whereFields];
		
		
		$fields = array_keys($fields);
		if (!isset($this->collection["fields"]))
		{
			$this->collection["fields"] = $fields;
		}
		else
		{
			if ($fields != $this->collection["fields"])
			{
				Poesis::error("field order/count must match first field count");
			}
		}
		if (!isset($this->collection["values"]))
		{
			$this->collection["values"] = [];
		}
		$this->collection["values"][] = $settedValues;
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
		if (!$this->Schema->hasAIField())
		{
			Poesis::error("table " . $this->getTableName() . " does not have AUTO_INCREMENT field");
		}
		if (in_array($this->lastQueryType, ['insert', 'replace']))
		{
			return $this->lastInsertID;
		}
		$primField = $this->Schema->getAIField();
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
		$Db = $this->Schema->getClassObject();
		$Db->limit(1);
		if ($this->Schema->hasAIField())
		{
			$primaryField = $this->Schema->getAIField();
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
		$nextID = $this->Con->dr("SHOW TABLE STATUS LIKE '" . $this->Schema->getTableName() . "'")->getArray();
		
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
		$className = $this->Schema->getClassName();
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
		$voidFields          = $this->Schema->getPrimaryFields();
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
		$this->Con->realQuery("TRUNCATE TABLE " . $this->getTableName());
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
			if ($this->Schema->hasPrimaryFields())
			{
				$settedValues = $this->Fields->getValues();
				$className    = $this->Schema->getClassName();
				/**
				 * @var Model $CheckWhere
				 */
				$CheckWhere  = new $className();
				$unsetFields = [];
				foreach ($this->Schema->getPrimaryFields() as $pf)
				{
					if ($this->Fields->isFieldSetted($pf))
					{
						$CheckWhere->Fields->set($pf, $this->Fields->getFieldValues($pf));
						$unsetFields[] = $pf;
					}
				}
				if ($CheckWhere->Fields->hasValues())
				{
					$CheckWhere->dontNullFields();
					$hasRows = $CheckWhere->hasRows();
					if ($hasRows)
					{
						foreach ($unsetFields as $f)
						{
							unset($settedValues[$f]);
						}
						$this->Fields->replace($settedValues);
						$this->Where->Fields->replace($CheckWhere->Fields->getValues());
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
						$this->Fields->replace($settedValues);
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
	 * @return stdClass
	 */
	private final function compile(string $queryType, $selectFields = '*'): stdClass
	{
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
		
		$Output            = new stdClass();
		$Output->RowParser = false;
		if (in_array($queryType, ['select', 'delete']))
		{
			if (!$this->Where->Fields->hasValues() and $this->Fields->hasValues())
			{
				$this->Where->Fields->replace($this->Fields->getValues());
			}
			$Output->fields = [];
			$Output->where  = $this->Where->Fields->getValues();
		}
		else
		{
			$Output->fields = $this->Fields->getValues();
			$Output->where  = $this->Where->Fields->getValues();
		}
		
		//debug("where", $this->Where->Fields->getValues());
		//debug("Fields", $this->Fields->getValues());
		if ($queryType == 'select')
		{
			if ($this->RowParser !== false)
			{
				$Output->RowParser = $this->RowParser;
			}
			$query = $this->QueryCompiler->select($selectFields);
		}
		elseif ($queryType == 'update')
		{
			$query = $this->QueryCompiler->update($this->isCollection());
		}
		elseif ($queryType == 'delete')
		{
			$query = $this->QueryCompiler->delete();
		}
		elseif ($queryType == 'insert' or $queryType == 'replace')
		{
			$query = $this->QueryCompiler->$queryType($this->isCollection());
		}
		$Output->query = $query;
		$this->nullFields();
		$this->nullFieldsAfterAction = true;
		
		
		return $Output;
	}
	
	
	/**
	 * Construct SQL query
	 *
	 * @param string   $queryType - update,insert,replace,select
	 * @param stdClass $Compiled
	 * @return mixed
	 */
	private final function execute(string $queryType, $Compiled)
	{
		if ($this->Schema->isView() and $queryType !== 'select')
		{
			Poesis::error('Can\'t save into view :' . $this->getTableName());
		}
		
		if ($queryType == 'select')
		{
			$Dr = $this->Con->dr($Compiled->query);
			if ($Compiled->RowParser !== false)
			{
				$Dr->setRowParser($Compiled->RowParser->rowParserCallback, $Compiled->RowParser->rowParserScope, $Compiled->RowParser->rowParserArguments);
			}
			$output = $Dr;
		}
		elseif ($this->isCollection())
		{
			$this->Con->multiQuery($Compiled->query);
			$output             = true;
			$this->lastInsertID = $this->Con->getLastInsertID();
			$this->lastFields   = $this->collection["values"][array_key_last($this->collection["values"])];
		}
		else
		{
			$output             = $this->Con->realQuery($Compiled->query);
			$this->lastInsertID = $this->Con->getLastInsertID();
			$this->lastFields   = (object)["fields" => $Compiled->fields, "where" => $Compiled->where];
			
		}
		
		$this->lastQuery     = $Compiled->query;
		$this->lastQueryType = $queryType;
		$this->collection    = [];
		
		if ($queryType != 'select')
		{
			$this->makeLog($queryType, $Compiled, $this->isCollection());
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
	
	/**
	 * Debug current data
	 *
	 * @param bool|string|array $fields - false means *
	 */
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
	 * @param string   $queryType
	 * @param stdClass $LogData
	 * @param bool     $isCollect
	 */
	public function makeLog(string $queryType, stdClass $LogData, bool $isCollect = false): void
	{
		if (!Poesis::isLoggerEnabled())
		{
			return;
		}
		if (!$this->loggerEnabled)
		{
			return;
		}
		
		$tableName = $this->getTableName();
		if (!in_array($tableName, $this->voidTablesToLog))
		{
			$fields = [];
			$where  = [];
			if ($isCollect)
			{
				$fields = $LogData->fields;
			}
			else
			{
				if (checkArray($LogData->fields))
				{
					foreach ($LogData->fields as $fieldName => $nodes)
					{
						$fields[$fieldName] = $nodes[array_key_last($nodes)]->get();
					}
				}
				$loopWhereItems = function (array $fields, array $items) use (&$loopWhereItems)
				{
					$fieldKey = 0;
					foreach ($items as $fieldName => $item)
					{
						foreach ($item as $key => $Node)
						{
							if ($Node->isGroup())
							{
								$loopWhereItems($fields, [$fieldName => $Node->get()]);
							}
							else
							{
								$fields[$Node->getFieldName()] = $Node->get();
							}
						}
						$fieldKey++;
					}
					
					return $fields;
				};
				
				if (checkArray($LogData->where))
				{
					$where = $loopWhereItems([], $LogData->where);
				}
			}
			$LogData         = new stdClass();
			$LogData->fields = $fields;
			$LogData->where  = $where;
			$oWhere          = new ArrayObject($where);
			$oFields         = new ArrayObject($fields);
			$ok              = Poesis::isLogOkForTableFields($tableName, $oFields, $oWhere);
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
					if ($this->Schema->hasAIField())
					{
						$lastID = $this->getLastSaveID();
					}
					else
					{
						/*
						$primFields = $this->Schema->getPrimaryFields();
						if (checkArray($primFields))
						{
							$LastRecord = $this->getLastRecord($primFields);
						}
						debug($primFields);
						debug($LastRecord);
						exit;
						*/
					}
				}
				$Db->data->compress(json_encode($LogData));
				$Db->tableName($tableName);
				$Db->tableRowID($lastID);
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