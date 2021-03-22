<?php

namespace Infira\Poesis\dr;

use Infira\Poesis\Poesis;
use Infira\Utils\Variable;

/**
 * Class DataMethods
 *
 * @mixin \Infira\Poesis\dr\DataGetResult
 */
class DataMethods
{
	use \PoesisDataMethodsExtendor;
	
	public function __call($name, $arguments)
	{
		Poesis::error('undefined methods');
	}
	
	//############ public methods
	
	/**
	 * Is the rsource have any rows
	 *
	 * @return bool
	 */
	public function hasRows()
	{
		if ($this->count() > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * Count rows
	 *
	 * @return int
	 */
	public function count()
	{
		return $this->getRes()->num_rows;
	}
	
	
	/**
	 * Get records via fetch_assoc
	 *
	 * @return array
	 */
	/**
	 * Get records via fetch_row
	 *
	 * @return array
	 */
	public function getRows()
	{
		return $this->collectRows("fetch_row");
	}
	
	/**
	 * Get records via fetch_row
	 *
	 * @return array
	 */
	public function getRow()
	{
		return $this->fetch("fetch_row");
	}
	
	/**
	 * Get single record via fetch_assoc
	 *
	 * @return array
	 */
	public function getArray()
	{
		return $this->fetch("fetch_assoc");
	}
	
	protected $__lft = 0;
	
	private function __countNestedTreeChildren($Node)
	{
		$Node["lft"] = $this->__lft;
		$this->__lft++;
		if (!array_key_exists("__countChildren", $Node))
		{
			$Node["__countChildren"] = 0;
		}
		if (!array_key_exists("lft", $Node))
		{
			$Node["lft"] = 0;
		}
		if (!array_key_exists("rgt", $Node))
		{
			$Node["rgt"] = 0;
		}
		$Node["__countChildren"] += count($Node["subItems"]);
		foreach ($Node["subItems"] as $id => $N)
		{
			$NewNode = $this->__countNestedTreeChildren($N);
			$this->__lft++;
			$Node["subItems"][$id]   = $NewNode;
			$Node["__countChildren"] += $NewNode["__countChildren"];
		}
		$subItemsSimpleArr = array_values($Node["subItems"]);
		if ($Node["ID"] == 1)
		{
		}
		if (checkArray($subItemsSimpleArr))
		{
			$Node["rgt"] = $subItemsSimpleArr[count($subItemsSimpleArr) - 1]["rgt"] + 1;
		}
		else
		{
			$Node["rgt"] = $Node["lft"] + 1;
		}
		
		return $Node;
	}
	
	public function getTree($parent = 0, $parentColumn = "parentID", $IDColun = "ID", $subItemsName = "subItems")
	{
		$lookup = [];
		$index  = 0;
		$this->loop("fetch_assoc", function ($row) use (&$index, &$subItemsName, &$IDColun, &$parentColumn, &$parent, &$lookup)
		{
			$row["index"] = $index;
			$index++;
			$row[$subItemsName] = [];
			if ($row[$parentColumn] >= $parent)
			{
				$lookup[$row[$IDColun]] = $row;
			}
		}, null, false);
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
	
	public function getNestedTree($parent = 0, $parentColumn = "parentID", $IDColumn = "ID", $subItemsName = "subItems")
	{
		$tree        = $this->tree($parent, $parentColumn, $IDColumn, $subItemsName);
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
	 * @param string $single - fetch single row via fetc_object
	 * @return string
	 */
	public function getJson($single = false)
	{
		if ($this->hasRows())
		{
			if ($single)
			{
				$data = $this->fetch("fetch_object");
			}
			else
			{
				$data = $this->collectRows("fetch_object");
			}
			
			return json_encode($data);
		}
		
		return "";
	}
	
	/**
	 * get objct via fetch_object
	 *
	 * @return \stdClass
	 */
	public function getObject()
	{
		return $this->fetch("fetch_object");
	}
	
	/**
	 * get records via fetch_object
	 * old = getObjectArray
	 *
	 * @return mixed
	 */
	public function getObjects()
	{
		return $this->collectRows("fetch_object");
	}
	
	/**
	 * Get records via fetch_all
	 *
	 * @return array
	 */
	public function getArrays()
	{
		if ($this->rowParserCallback !== false)
		{
			return $this->collectRows("fetch_assoc");
		}
		else
		{
			return $this->getRes()->fetch_all(MYSQLI_ASSOC);
		}
	}
	
	
	private function manipulateColumnAndValue($column, $multiDimensional = false, $returnObjectArray = false, $addFieldValueToRow = false, $valueAs = false)
	{
		$data = [];
		if ($returnObjectArray == true)
		{
			$loopF = "fetch_object";
		}
		else
		{
			$loopF = "fetch_assoc";
		}
		$this->loop($loopF, function ($row) use (&$data, &$column, &$multiDimensional, &$returnObjectArray, &$addFieldValueToRow, &$valueAs)
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
		}, null, false);
		
		return $data;
	}
	
	/**
	 * Get field values into array
	 *
	 * @param string $column
	 * @return array
	 */
	public function getFieldValues($column)
	{
		return $this->manipulateColumnAndValue($column, false, false, true, false);
	}
	
	public function getDistinctedFieldValues($column)
	{
		return array_values($this->manipulateColumnAndValue($column, false, false, true, true));
	}
	
	/**
	 * Get data as [[$keyColumn1=>$valueColum1],[$keyColumn2=>$valueColum2]]
	 * old = putFieldToKeyValue
	 *
	 * @param string $keyColumn
	 * @param string $valueColumn
	 * @return array|mixed
	 */
	public function getFieldPair(string $keyColumn, string $valueColumn)
	{
		return $this->manipulateColumnAndValue($keyColumn, false, false, true, $valueColumn);
	}
	
	/**
	 * get data as [ [$keyColumn1 => [$keyColumn2 => $valueColumn]] ]
	 * old = getMultiFieldNameToArraKey
	 *
	 * @param string|array $keyColumns          - one or multiple column names, sepearated by comma
	 * @param string|array $valueColumn
	 * @param bool         $returnAsObjectArray does the row is arrat or std class
	 * @return array|mixed
	 */
	public function getMultiFieldPair($keyColumns, $valueColumn, $returnAsObjectArray = false)
	{
		return $this->manipulateColumnAndValue($keyColumns, true, $returnAsObjectArray, true, $valueColumn);
	}
	
	/**
	 * get data as [[$keyColumn => $row], [$keyColumn => $row]....]
	 * old = putFieldToArrayKey
	 *
	 * @param string $keyColumn
	 * @param bool   $returnAsObjectArray does the row is arrat or std class
	 * @return array|mixed
	 */
	public function getValueAsKey(string $keyColumn, bool $returnAsObjectArray = false)
	{
		return $this->manipulateColumnAndValue($keyColumn, false, $returnAsObjectArray, false, true);
	}
	
	/**
	 * get data as [ [$keyColumn1 => [$keyColumn2 => $row]] ]
	 * old = putFieldToMultiDimArrayKey
	 *
	 * @param string $keyColumns          - one or multiple column names, sepearated by comma
	 * @param bool   $returnAsObjectArray does the row is arrat or std class
	 * @return array|mixed
	 */
	public function getMultiValueAsKey(string $keyColumns, bool $returnAsObjectArray = false)
	{
		return $this->manipulateColumnAndValue($keyColumns, true, $returnAsObjectArray, false, true);
	}
	
	/**
	 * Get ID values to array
	 *
	 * @return array
	 */
	public function getIDS()
	{
		return $this->getFieldValues("ID");
	}
	
	/**
	 * Get column ID value
	 *
	 * @param mixed $returnOnNotFound
	 * @return mixed
	 */
	public function getID($returnOnNotFound = false)
	{
		return $this->getFieldValue("ID", $returnOnNotFound);
	}
	
	/**
	 * Gets a one column value
	 *
	 * @param string $column
	 * @return mixed
	 */
	public function getFieldValue($column, $returnOnNotFound = false)
	{
		$val = $this->fetch("fetch_object");
		if (is_object($val) and isset($val->$column))
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
	 * @param string $returnOnNotFound
	 * @return string
	 */
	public function implode($columns, $splitter = ",", $returnOnNotFound = "")
	{
		$columns = Variable::toArray($columns);
		$data    = "";
		$this->loop("fetch_assoc", function ($row) use (&$columns, &$data, &$splitter)
		{
			foreach ($columns as $f)
			{
				$data .= $row[$f] . $splitter;
			}
		});
		
		if ($data === "")
		{
			$data = $returnOnNotFound;
		}
		else
		{
			$data = substr($data, 0, (strlen($splitter) * -1));
		}
		
		return $data;
	}
	
	
	public function collect($callback = null, $scope = null)
	{
		return $this->collectRows("fetch_object", $callback, $scope);
	}
	
	/**
	 * get array row as class
	 *
	 * @param string $className - className to create object with
	 * @return object
	 */
	public function getRowAsClass(string $className)
	{
		if (empty($className))
		{
			Poesis::error("Class namme cannot be empty");
		}
		
		return new $className($this->getObject());
	}
	
	/**
	 * get all rows as class
	 *
	 * @param string $className - className to create object with
	 * @return object
	 */
	public function getAllAsClass(string $className)
	{
		if (empty($className))
		{
			Poesis::error("Class namme cannot be empty");
		}
		
		return new $className($this->fetchAll());
	}
	
	public function each($callback = null, $scope = null)
	{
		return $this->loop("fetch_object", $callback, $scope, false);
	}
	
	public function eachCollect($callback = null, $scope = null)
	{
		return $this->loop("fetch_object", $callback, $scope, true);
	}
}

?>