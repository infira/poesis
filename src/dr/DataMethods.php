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
	
	public function getTree($parent = 0, $parentField = "parentID", $IDField = "ID", $subItemsName = "subItems")
	{
		$lookup = [];
		$index  = 0;
		$this->loop("fetch_assoc", function ($row) use (&$index, &$subItemsName, &$IDField, &$parentField, &$parent, &$lookup)
		{
			$row["index"] = $index;
			$index++;
			$row[$subItemsName] = [];
			if ($row[$parentField] >= $parent)
			{
				$lookup[$row[$IDField]] = $row;
			}
		}, null, false);
		$tree = [];
		foreach ($lookup as $id => $foo)
		{
			$item = &$lookup[$id];
			if (isset($lookup[$item[$parentField]]))
			{
				$lookup[$item[$parentField]][$subItemsName][$id] = &$item;
			}
			else
			{
				$tree[$id] = &$item;
			}
		}
		
		return $tree;
	}
	
	public function getNestedTree($parent = 0, $parentField = "parentID", $IDField = "ID", $subItemsName = "subItems")
	{
		$tree        = $this->tree($parent, $parentField, $IDField, $subItemsName);
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
	
	
	private function manipulateFieldAndValue($fieldName, $multiDimensional = false, $returnObjectArray = false, $addFieldValueToRow = false, $valueAs = false)
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
		$this->loop($loopF, function ($row) use (&$data, &$fieldName, &$multiDimensional, &$returnObjectArray, &$addFieldValueToRow, &$valueAs)
		{
			if ($valueAs)
			{
				$current = &$data;
				foreach (Variable::toArray($fieldName) as $f)
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
						$value = ($addFieldValueToRow) ? $row->$fieldName : $row;
					}
					else
					{
						$value = ($addFieldValueToRow) ? $row[$fieldName] : $row;
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
					$data[] = $row->$fieldName;
				}
				else
				{
					$data[] = $row[$fieldName];
				}
			}
		}, null, false);
		
		return $data;
	}
	
	/**
	 * Get field values into array
	 *
	 * @param string $fieldName
	 * @return array
	 */
	public function getFieldValues($fieldName)
	{
		return $this->manipulateFieldAndValue($fieldName, false, false, true, false);
	}
	
	public function getDistinctedFieldValues($fieldName)
	{
		return array_values($this->manipulateFieldAndValue($fieldName, false, false, true, true));
	}
	
	/**
	 * Get data as [[$keyField1=>$valueField1],[$keyField2=>$valueField2]]
	 * old = putFieldToKeyValue
	 *
	 * @param string $keyField
	 * @param string $valueField
	 * @return array|mixed
	 */
	public function getFieldPair(string $keyField, string $valueField)
	{
		return $this->manipulateFieldAndValue($keyField, false, false, true, $valueField);
	}
	
	/**
	 * get data as [ [$keyField1 => [$keyField2 => $valueField]] ]
	 * old = getMultiFieldNameToArraKey
	 *
	 * @param string $keyFields           - one or multiple field names, sepearated by comma
	 * @param bool   $returnAsObjectArray does the row is arrat or std class
	 * @return array|mixed
	 */
	public function getMultiFieldPair($keyFields, $valueField, $returnAsObjectArray = false)
	{
		return $this->manipulateFieldAndValue($keyFields, true, $returnAsObjectArray, true, $valueField);
	}
	
	/**
	 * get data as [[$keyField1 => $row], [$keyField2 => $row]....]
	 * old = putFieldToArrayKey
	 *
	 * @param string $keyField
	 * @param bool   $returnAsObjectArray does the row is arrat or std class
	 * @return array|mixed
	 */
	public function getValueAsKey(string $keyField, bool $returnAsObjectArray = false)
	{
		return $this->manipulateFieldAndValue($keyField, false, $returnAsObjectArray, false, true);
	}
	
	/**
	 * get data as [ [$keyField1 => [$keyField2 => $row]] ]
	 * old = putFieldToMultiDimArrayKey
	 *
	 * @param string $keyFields           - one or multiple field names, sepearated by comma
	 * @param bool   $returnAsObjectArray does the row is arrat or std class
	 * @return array|mixed
	 */
	public function getMultiValueAsKey(string $keyFields, bool $returnAsObjectArray = false)
	{
		return $this->manipulateFieldAndValue($keyFields, true, $returnAsObjectArray, false, true);
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
	 * Get field ID value
	 *
	 * @param mixed $returnOnNotFound
	 * @return mixed
	 */
	public function getID($returnOnNotFound = false)
	{
		return $this->getFieldValue("ID", $returnOnNotFound);
	}
	
	/**
	 * Gets a one field value
	 *
	 * @param string $fieldName
	 * @return mixed
	 */
	public function getFieldValue($fieldName, $returnOnNotFound = false)
	{
		$val = $this->fetch("fetch_object");
		if (is_object($val) and isset($val->$fieldName))
		{
			return $val->$fieldName;
		}
		else
		{
			return $returnOnNotFound;
		}
	}
	
	/**
	 * Implode field values to one string
	 *
	 * @param string $fields
	 * @param string $splitter
	 * @param string $returnOnNotFound
	 * @return string
	 */
	public function implode($fields, $splitter = ",", $returnOnNotFound = "")
	{
		$fields = Variable::toArray($fields);
		$data   = "";
		$this->loop("fetch_assoc", function ($row) use (&$fields, &$data, &$splitter)
		{
			foreach ($fields as $f)
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
	
	public function eachReturn($callback = null, $scope = null)
	{
		return $this->loop("fetch_object", $callback, $scope, true);
	}
}

?>