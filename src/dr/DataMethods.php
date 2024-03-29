<?php

namespace Infira\Poesis\dr;

use Infira\Utils\Variable;
use Infira\Poesis\Connection;

class DataMethods extends DataMethodsFinal
{
	/**
	 * @param string     $query - sql query
	 * @param Connection $Con
	 */
	public function __construct(string $query, Connection &$Con)
	{
		$this->setQuery($query);
		$this->setConnection($Con);
	}
	
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
		if ($subItemsSimpleArr)
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
	
	private function manipulateColumnAndValue(string $column, bool $multiDim = false, bool $getObjects = false, string $getColValue = null, bool $valueAs = false): array
	{
		$data  = [];
		$loopF = $getObjects ? 'fetch_object' : 'fetch_assoc';
		$this->loop($loopF, null, function ($row) use (&$data, &$column, &$multiDim, &$getObjects, &$getColValue, &$valueAs)
		{
			if ($valueAs)
			{
				$current = &$data;
				foreach (Variable::toArray($column) as $f)
				{
					$f       = $getObjects ? $row->$f : $row[$f];
					$f       = (string)($f);
					$current = &$current[$f];
				}
				if ($getObjects)
				{
					$value = ($getColValue) ? $row->$getColValue : $row;
				}
				else
				{
					$value = ($getColValue) ? $row[$getColValue] : $row;
				}
				
				if ($multiDim)
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
				$data[] = $getObjects ? $row->$column : $row[$column];
			}
		}, false);
		
		return $data;
	}
	
	/**
	 * collects single columns values
	 *
	 * @param string $column
	 * @return array
	 */
	public function getValues(string $column): array
	{
		return $this->manipulateColumnAndValue($column);
	}
	
	public function getDistinctValues(string $column): array
	{
		return array_unique($this->getValues($column));
	}
	
	/**
	 * get data as [ [$keyColumn1 => [$keyColumn2 => [$keyColumn.... => $valueColumn]]] ]
	 * old = putFieldToKeyValue
	 *
	 * @param string $keyColumns - one or multiple column names, separated by comma
	 * @param string $valueColumn
	 * @return array
	 */
	public function getColumnPair(string $keyColumns, string $valueColumn): array
	{
		return $this->manipulateColumnAndValue($keyColumns, false, false, $valueColumn, true);
	}
	
	/**
	 * get data as  [$keyColumn1 => [$keyColumn2 => $row]]
	 * old = putFieldToArrayKey
	 *
	 * @param string $keyColumns          - sepearate multiple columns by comma
	 * @param bool   $returnAsObjectArray does the row is arrat or std class
	 * @return array
	 */
	public function getValueAsKey(string $keyColumns, bool $returnAsObjectArray = false): array
	{
		return $this->manipulateColumnAndValue($keyColumns, false, $returnAsObjectArray, false, true);
	}
	
	/**
	 * get data as [$keyColumn1 => [$keyColumn2 => [$row1, $row2, $row.....]]]
	 *
	 * @param string $keyColumns - sepearate multiple columns by comma
	 * @param bool   $returnAsObjectArray
	 * @return array
	 */
	public function getValueAsKeyMultiDimensional(string $keyColumns, bool $returnAsObjectArray = false): array
	{
		return $this->manipulateColumnAndValue($keyColumns, true, $returnAsObjectArray, false, true);
	}
	
	/**
	 * @param string $IDColun - defaults to ID
	 * @return array
	 */
	public function getIDS(string $IDColun = 'ID'): array
	{
		return $this->getValues($IDColun);
	}
	
	/**
	 * Get column ID value
	 *
	 * @param mixed $returnOnNotFound
	 * @return int|null
	 */
	public function getID($returnOnNotFound = null): ?int
	{
		return $this->getValue('ID', $returnOnNotFound);
	}
	
	/**
	 * Gets a one column value
	 *
	 * @param string $column
	 * @return string|null
	 */
	public function getValue(string $column, $returnOnNotFound = null): ?string
	{
		$val = $this->getObject();
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
	 * Implode column values to one string
	 *
	 * @param string|array $columns
	 * @param string       $splitter
	 * @param mixed        $returnOnNotFound
	 * @return string|null
	 */
	public function implode($columns, string $splitter = ',', $returnOnNotFound = ''): ?string
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
	 * Implode column values to one string
	 *
	 * @param string|array $columns
	 * @param string       $splitter
	 * @return array
	 */
	public function implodeRows($columns, string $splitter = ','): array
	{
		$columns = Variable::toArray($columns);
		$data    = [];
		$this->loop('fetch_assoc', null, function ($row) use (&$columns, &$data, &$splitter)
		{
			$im = '';
			foreach ($columns as $f)
			{
				$im .= $row[$f] . $splitter;
			}
			$data[] = substr($im, 0, (strlen($splitter) * -1));
			
		}, false);
		
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
	
	
}