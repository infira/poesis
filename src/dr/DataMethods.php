<?php

namespace Infira\Poesis\dr;

use Infira\Poesis\Poesis;
use Infira\Utils\Variable;
use Infira\Poesis\Connection;

class DataMethods
{
	use \PoesisDataMethodsExtendor;
	
	/**
	 * @var \mysqli_result
	 */
	protected $res = null;
	protected $query;
	/**
	 * @var Connection
	 */
	protected $Con;
	
	private   $rowParsers      = [];
	protected $pointerLocation = false;
	
	const PASS_ROW_TO_OBJECT = 'PASS_ROW_TO_OBJECT';
	
	public function __call($name, $arguments)
	{
		Poesis::error('Call to undefined method ' . $name);
	}
	
	//region helpers
	
	/**
	 * @param string     $query - sql query for data retrieval
	 * @param Connection $Con
	 */
	protected function setDb(string $query, Connection &$Con)
	{
		$this->Con   = &$Con;
		$this->query = $query;
	}
	
	public function setRowParsers(array $callables): DataMethods
	{
		$this->rowParsers = $callables;
		
		return $this;
	}
	
	public function addRowParser(callable $parser, array $arguments = []): DataMethods
	{
		$this->rowParsers[] = (object)['parser' => $parser, 'arguments' => $arguments];
		
		return $this;
	}
	
	public function nullRowParser(): DataMethods
	{
		$this->rowParsers = [];
		
		return $this;
	}
	
	public function hasRowParser(): bool
	{
		return (bool)$this->rowParsers;
	}
	//endregion
	
	//region public methods
	
	/**
	 * Is the rsource have any rows
	 *
	 * @return bool
	 */
	public function hasRows(): bool
	{
		return ($this->count() > 0);
	}
	
	/**
	 * Count rows
	 *
	 * @return int
	 */
	public function count(): int
	{
		return $this->getRes()->num_rows;
	}
	
	/**
	 * Get records via fetch_row
	 *
	 * @return array|null
	 */
	public function getRows(): array
	{
		return $this->loop('fetch_row', null, null, true);
	}
	
	/**
	 * et records via fetch_row
	 *
	 * @return array|null
	 */
	public function getRow(): ?array
	{
		return $this->fetch('fetch_row');
	}
	
	/**
	 * Get single record via fetch_assoc
	 *
	 * @return array|null
	 */
	public function getArray(): ?array
	{
		return $this->fetch('fetch_assoc');
	}
	
	protected $__lft = 0;
	
	private function __countNestedTreeChildren(array $Node): array
	{
		$Node['lft'] = $this->__lft;
		$this->__lft++;
		if (!array_key_exists('__countChildren', $Node))
		{
			$Node['__countChildren'] = 0;
		}
		if (!array_key_exists('lft', $Node))
		{
			$Node['lft'] = 0;
		}
		if (!array_key_exists('rgt', $Node))
		{
			$Node['rgt'] = 0;
		}
		$Node['__countChildren'] += count($Node['subItems']);
		foreach ($Node['subItems'] as $id => $N)
		{
			$NewNode = $this->__countNestedTreeChildren($N);
			$this->__lft++;
			$Node['subItems'][$id]   = $NewNode;
			$Node['__countChildren'] += $NewNode['__countChildren'];
		}
		$subItemsSimpleArr = array_values($Node['subItems']);
		if ($Node['ID'] == 1)
		{
		}
		if (checkArray($subItemsSimpleArr))
		{
			$Node['rgt'] = $subItemsSimpleArr[count($subItemsSimpleArr) - 1]['rgt'] + 1;
		}
		else
		{
			$Node['rgt'] = $Node['lft'] + 1;
		}
		
		return $Node;
	}
	
	public function getTree(int $parent = 0, string $parentColumn = 'parentID', string $IDColun = 'ID', string $subItemsName = 'subItems'): array
	{
		$lookup = [];
		$index  = 0;
		$this->loop('fetch_assoc', null, function ($row) use (&$index, &$subItemsName, &$IDColun, &$parentColumn, &$parent, &$lookup)
		{
			$row['index'] = $index;
			$index++;
			$row[$subItemsName] = [];
			if ($row[$parentColumn] >= $parent)
			{
				$lookup[$row[$IDColun]] = $row;
			}
		}, false);
		$tree = [];
		foreach ($lookup as $id => $foo)
		{
			$item = &$lookup[$id];
			if (isset($lookup[$item[$parentColumn]]))
			{
				$lookup[$item[$parentColumn]][$subItemsName][$id] = &$item;
			}
			else
			{
				$tree[$id] = &$item;
			}
		}
		
		return $tree;
	}
	
	public function getNestedTree(int $parent = 0, string $parentColumn = 'parentID', string $IDColumn = 'ID', string $subItemsName = 'subItems'): array
	{
		$tree        = $this->getTree($parent, $parentColumn, $IDColumn, $subItemsName);
		$this->__lft = 1;
		foreach ($tree as $id => $Node)
		{
			$tree[$id] = $this->__countNestedTreeChildren($Node);
			$this->__lft++;
		}
		
		return $tree;
	}
	
	/**
	 * get json encoded string
	 *
	 * @param string $single - fetch single row via fetch_object
	 * @return string
	 */
	public function getJson($single = false): string
	{
		if ($this->hasRows())
		{
			if ($single)
			{
				$data = $this->getObject();
			}
			else
			{
				$data = $this->getObjects();
			}
			
			return json_encode($data);
		}
		
		return '';
	}
	
	/**
	 * get objct via fetch_object
	 *
	 * @param string $class                - what class to construct on mysqli->fetch_obect
	 * @param array  $constructorArguments arguments to pass __construct of $class
	 * @return object|null
	 */
	public function getObject(string $class = '\stdClass', array $constructorArguments = []): ?object
	{
		return $this->fetchObject($class, $constructorArguments);
	}
	
	/**
	 * get records via fetch_object
	 *
	 * @param string $class
	 * @param array  $constructorArguments
	 * @return array
	 */
	public function getObjects(string $class = '\stdClass', array $constructorArguments = []): array
	{
		return $this->loop('fetch_object', [$class, $constructorArguments], null, true);
	}
	
	/**
	 * Get records via fetch_all
	 *
	 * @return array
	 */
	public function getArrays(): array
	{
		if ($this->hasRowParser())
		{
			return $this->loop('fetch_assoc', null, null, true);
		}
		else
		{
			return $this->getRes()->fetch_all(MYSQLI_ASSOC);
		}
	}
	
	private function manipulateColumnAndValue(string $column, bool $multiDimensional = false, bool $returnObjectArray = false, bool $addFieldValueToRow = false, bool $valueAs = false): array
	{
		$data = [];
		if ($returnObjectArray == true)
		{
			$loopF = 'fetch_object';
		}
		else
		{
			$loopF = 'fetch_assoc';
		}
		$this->loop($loopF, null, function ($row) use (&$data, &$column, &$multiDimensional, &$returnObjectArray, &$addFieldValueToRow, &$valueAs)
		{
			if ($valueAs)
			{
				$current = &$data;
				foreach (Variable::toArray($column) as $f)
				{
					if ($returnObjectArray)
					{
						$f = $row->$f;
					}
					else
					{
						$f = $row[$f];
					}
					$f       = Variable::toString($f);
					$current = &$current[$f];
				}
				if ($valueAs === true)
				{
					if ($returnObjectArray)
					{
						$value = ($addFieldValueToRow) ? $row->$column : $row;
					}
					else
					{
						$value = ($addFieldValueToRow) ? $row[$column] : $row;
					}
					
				}
				else
				{
					if ($returnObjectArray)
					{
						$value = $row->$valueAs;
					}
					else
					{
						$value = $row[$valueAs];
					}
				}
				
				if ($multiDimensional)
				{
					$current[] = $value;
				}
				else
				{
					$current = $value;
				}
			}
			else
			{
				if ($returnObjectArray == true)
				{
					$data[] = $row->$column;
				}
				else
				{
					$data[] = $row[$column];
				}
			}
		}, false);
		
		return $data;
	}
	
	/**
	 * Get field values into array
	 *
	 * @param string $column
	 * @return array
	 */
	public function getFieldValues(string $column): array
	{
		return $this->manipulateColumnAndValue($column, false, false, true, false);
	}
	
	public function getDistinctedFieldValues(string $column): array
	{
		return array_values($this->manipulateColumnAndValue($column, false, false, true, true));
	}
	
	/**
	 * Get data as [[$keyColumn1=>$valueColum1],[$keyColumn2=>$valueColum2]]
	 * old = putFieldToKeyValue
	 *
	 * @param string $keyColumn
	 * @param string $valueColumn
	 * @return array
	 */
	public function getFieldPair(string $keyColumn, string $valueColumn): array
	{
		return $this->manipulateColumnAndValue($keyColumn, false, false, true, $valueColumn);
	}
	
	/**
	 * get data as [ [$keyColumn1 => [$keyColumn2 => $valueColumn]] ]
	 * old = getMultiFieldNameToArraKey
	 *
	 * @param string $keyColumns          - one or multiple column names, sepearated by comma
	 * @param string $valueColumn
	 * @param bool   $returnAsObjectArray does the row is arrat or std class
	 * @return array
	 */
	public function getMultiFieldPair(string $keyColumns, string $valueColumn, $returnAsObjectArray = false): array
	{
		return $this->manipulateColumnAndValue($keyColumns, true, $returnAsObjectArray, true, $valueColumn);
	}
	
	/**
	 * get data as [[$keyColumn => $row], [$keyColumn => $row]....]
	 * old = putFieldToArrayKey
	 *
	 * @param string $keyColumn
	 * @param bool   $returnAsObjectArray does the row is arrat or std class
	 * @return array
	 */
	public function getValueAsKey(string $keyColumn, bool $returnAsObjectArray = false): array
	{
		return $this->manipulateColumnAndValue($keyColumn, false, $returnAsObjectArray, false, true);
	}
	
	/**
	 * get data as [ [$keyColumn1 => [$keyColumn2 => $row]] ]
	 * old = putFieldToMultiDimArrayKey
	 *
	 * @param string $keyColumns          - one or multiple column names, sepearated by comma
	 * @param bool   $returnAsObjectArray does the row is arrat or std class
	 * @return array
	 */
	public function getMultiValueAsKey(string $keyColumns, bool $returnAsObjectArray = false): array
	{
		return $this->manipulateColumnAndValue($keyColumns, true, $returnAsObjectArray, false, true);
	}
	
	/**
	 * Get ID values to array
	 *
	 * @return array
	 */
	public function getIDS(): array
	{
		return $this->getFieldValues('ID');
	}
	
	/**
	 * Get column ID value
	 *
	 * @param mixed $returnOnNotFound
	 * @return int|null
	 */
	public function getID($returnOnNotFound = null): ?int
	{
		return $this->getFieldValue('ID', $returnOnNotFound);
	}
	
	/**
	 * Gets a one column value
	 *
	 * @param string $column
	 * @return string|null
	 */
	public function getFieldValue(string $column, $returnOnNotFound = null): ?string
	{
		$val = $this->fetchObject();
		if (is_object($val))
		{
			return $val->$column;
		}
		else
		{
			return $returnOnNotFound;
		}
	}
	
	/**
	 * Implode field values to one string
	 *
	 * @param string $columns
	 * @param string $splitter
	 * @param mixed  $returnOnNotFound
	 * @return string|null
	 */
	public function implode(string $columns, string $splitter = ',', $returnOnNotFound = ''): ?string
	{
		$columns = Variable::toArray($columns);
		$data    = '';
		$this->loop('fetch_assoc', null, function ($row) use (&$columns, &$data, &$splitter)
		{
			foreach ($columns as $f)
			{
				$data .= $row[$f] . $splitter;
			}
		}, false);
		
		if ($data === '')
		{
			$data = $returnOnNotFound;
		}
		else
		{
			$data = substr($data, 0, (strlen($splitter) * -1));
		}
		
		return $data;
	}
	
	/**
	 * Loop each row with callback
	 *
	 * @param callable|null $callback
	 */
	public function each(callable $callback = null)
	{
		$this->loop('fetch_object', null, $callback, false);
	}
	
	/**
	 * Alias to collect
	 *
	 * @param callable|null $callback
	 * @see \Infira\Poesis\dr\DataMethods..collect()
	 * @return array
	 */
	public function eachCollect(callable $callback = null): array
	{
		return $this->collect($callback);
	}
	
	/**
	 * Collect rows with row callback
	 *
	 * @param callable|null $callback
	 * @return array - array stdClasses
	 */
	public function collect(callable $callback = null): array
	{
		return $this->loop('fetch_object', null, $callback, true);
	}
	
	public function seek($nr): DataMethods
	{
		if (is_object($this->res))
		{
			if ($this->hasRows())
			{
				$this->res->data_seek(intval($nr));
			}
		}
		
		return $this;
	}
	
	public function debug()
	{
		if ($this->count() > 1)
		{
			debug($this->getObjects());
		}
		else
		{
			debug($this->getObject());
		}
	}
	//endregion
	
	
	/**
	 * @param int $setPointer
	 * @return \mysqli_result
	 */
	public function getRes(int $setPointer = null): \mysqli_result
	{
		if ($this->res === null)
		{
			$this->res = $this->Con->query($this->query);
		}
		if ($setPointer !== null)
		{
			$this->seek($setPointer);
		}
		
		return $this->res;
	}
	
	/**
	 * @param string        $fetchMethod
	 * @param array|null    $fetchArguments
	 * @param callable|null $callback
	 * @param bool|null     $collectRows
	 * @return array
	 */
	protected function loop(string $fetchMethod, ?array $fetchArguments, ?callable $callback, ?bool $collectRows)
	{
		if ($collectRows)
		{
			$data = [];
		}
		
		$res = $this->getRes();
		if ($this->hasRows())
		{
			$pointer = 0;
			do
			{
				$createClass = false;
				if ($fetchMethod == 'fetch_object' and $fetchArguments != null)
				{
					$fetchClass = $fetchArguments[0];
					if ($fetchClass === '\stdClass' || $fetchClass == 'stdClass')
					{
						$fRow = $res->$fetchMethod($fetchClass);
					}
					else
					{
						$constructArguments = $fetchArguments[1];
						if (array_values($constructArguments)[0] == self::PASS_ROW_TO_OBJECT)
						{
							$fRow        = $res->fetch_object();
							$createClass = $fetchClass;
						}
						else
						{
							$fRow = $res->fetch_object($fetchClass, $constructArguments);
						}
					}
				}
				elseif ($fetchArguments !== null)
				{
					$fRow = $res->$fetchMethod(...$fetchArguments);
				}
				else
				{
					$fRow = $res->$fetchMethod();
				}
				$row = null;
				if ($fRow !== null)
				{
					if ($createClass)
					{
						$row = $this->parseRow(new $fetchClass($fRow));
					}
					else
					{
						$row = $this->parseRow($fRow);
					}
					if ($callback)
					{
						$row = call_user_func_array($callback, [$row]);
					}
					if ($row === Poesis::BREAK)
					{
						break;
					}
					if ($row === Poesis::CONTINUE)
					{
						continue;
					}
					$pointer++;
					if ($collectRows)
					{
						if ($row === null)
						{
							Poesis::error('Looper must return result');
						}
						$data[] = $row;
					}
				}
			}
			while ($fRow);
			$this->pointerLocation = $pointer;
		}
		if ($collectRows)
		{
			return $data;
		}
	}
	
	protected function fetch(string $fetchMethod, array $fetchArguments = [])
	{
		return $this->parseRow($this->getRes()->$fetchMethod(...$fetchArguments));
	}
	
	protected function fetchObject(string $class = '\stdClass', array $constructorArguments = null): ?object
	{
		if ($class == '\stdClass' || $class == 'stdClass')
		{
			return $this->parseRow($this->getRes()->fetch_object($class));
		}
		else
		{
			return $this->parseRow($this->getRes()->fetch_object($class, $constructorArguments));
		}
	}
	
	protected function parseRow($row)
	{
		if ($row === null)
		{
			return null;
		}
		if ($this->hasRowParser())
		{
			foreach ($this->rowParsers as $parserItem)
			{
				$row = call_user_func_array($parserItem->parser, array_merge([$row], $parserItem->arguments));
			}
		}
		
		return $row;
	}
}

?>