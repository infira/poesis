<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\orm\node\ValueNode;
use Infira\Utils\Variable;
use Infira\Poesis\Poesis;

class QueryCompiler
{
	const RAW_QUERY_FIELD = "__raw_sql_query_field";
	/**
	 * @var Model
	 */
	private $Orm;
	
	public function __construct(&$Obj)
	{
		$this->Orm = $Obj;
	}
	
	
	/**
	 * Generates insert into sql query
	 *
	 * @param bool $isCollection - bool to run multieple insersts in onequery
	 * @return string generated sql string
	 */
	public function insert(bool $isCollection = false): string
	{
		return 'INSERT ' . $this->intoSql($this->Orm->getTableName(), $isCollection);
	}
	
	/**
	 * Generates replace into sql query
	 *
	 * @param bool $isCollection - bool to run multieple insersts in onequery
	 * @return string generated sql string
	 */
	public function replace($isCollection = false)
	{
		return 'REPLACE ' . $this->intoSql($this->Orm->getTableName(), $isCollection);
	}
	
	
	/**
	 * Generates into sql
	 *
	 * @param string $table
	 * @param bool   $isCollection
	 * @return string
	 */
	private function intoSql(string $table, bool $isCollection = false): string
	{
		$query              = 'INTO ' . $this->table($table) . ' ';
		$fields             = '';
		$values             = '';
		$genFieldsValuesSQL = function ($fieldAndValues) use (&$table)
		{
			$output = (object)["fields" => [], "values" => []];
			foreach ($fieldAndValues as $fieldName => $nodes)
			{
				$output->fields[] = $this->fixField($fieldName);
				$output->values[] = $this->fixEditFieldValue($nodes[array_key_last($nodes)], $table);
			}
			
			return $output;
		};
		if ($isCollection)
		{
			$fieldAndValues = $this->Orm->getCollectionValues();
			$lastKey        = array_key_last($fieldAndValues);
			foreach ($fieldAndValues as $key => $rowFieldValues)
			{
				$i = $genFieldsValuesSQL($rowFieldValues->fields);
				if ($key == 0)
				{
					$fields .= "(" . join(",", $i->fields) . ")";
				}
				$values .= "\n" . "(" . join(",", $i->values) . ")";
				if ($key != $lastKey)
				{
					$values .= ",";
				}
			}
		}
		else
		{
			$i      = $genFieldsValuesSQL($this->Orm->Fields->getValues());
			$fields .= "(" . join(",", $i->fields) . ")";
			$values .= "(" . join(",", $i->values) . ")";
		}
		$query .= $fields . ' VALUES ' . $values;
		
		return $query;
	}
	
	/**
	 * Generates SQL delete query
	 *
	 * @return string delete query
	 */
	public function delete()
	{
		$table = $this->Orm->getTableName();
		
		$where = $this->whereSql($this->Orm->Where->Fields->getValues());
		$table = $this->table($table);
		$query = 'DELETE FROM ' . $table . ' ' . $where;
		$query .= $this->groupSql($this->Orm->getGroupBy());
		$query .= $this->orderSql($this->Orm->getOrderBy());
		$query .= $this->limitSql($this->Orm->getLimit());
		
		return $query;
	}
	
	/**
	 * Generates updates sql query
	 *
	 * @param bool $isCollection - bool to run multieple insersts in onequery
	 * @return string generated sql string
	 */
	public function update(bool $isCollection = false): string
	{
		$genUpdateQuery = function ($Data) use (&$table)
		{
			$table = $this->Orm->getTableName();
			$order = $this->Orm->getOrderBy();
			$limit = $this->Orm->getLimit();
			$group = $this->Orm->getGroupBy();
			
			$query = 'UPDATE ' . $this->table($table) . ' SET ';
			
			foreach ($Data->fields as $fieldName => $nodes)
			{
				$query .= $this->fixField($fieldName) . ' = ' . $this->fixEditFieldValue($nodes[array_key_last($nodes)], $table) . ',';
			}
			$query = substr($query, 0, -1);// Remove the last comma
			$query .= $this->whereSql($Data->where);
			$query .= $this->groupSql($group);
			$query .= $this->orderSql($order);
			$query .= $this->limitSql($limit);
			
			
			return $query;
		};
		
		if ($isCollection)
		{
			$query = '';
			foreach ($this->Orm->getCollectionValues() as $Collection)
			{
				$query .= $genUpdateQuery($Collection) . ';';
			}
			$query = substr($query, 0, -1);// Remove the last comma
			
			return $query;
		}
		else
		{
			return $genUpdateQuery((object)['fields' => $this->Orm->Fields->getValues(), 'where' => $this->Orm->Where->Fields->getValues()]);
		}
	}
	
	/**
	 * @param string|array $selectFields - fields to use in SELECT $selectFields FROM, * - use to select all fields, otherwise it will be exploded by comma
	 * @return string
	 */
	public function select($selectFields = null)
	{
		$table = $this->Orm->getTableName();
		$order = $this->Orm->getOrderBy();
		$limit = $this->Orm->getLimit();
		$group = $this->Orm->getGroupBy();
		
		$query = 'SELECT ';
		
		if ($selectFields == '*' or !$selectFields)
		{
			$query .= ' * ';
		}
		elseif (!is_array($selectFields))
		{
			$selectFields = preg_split("/,(?![^()]*+\\))/", $selectFields);
		}
		Poesis::addExtraErrorInfo("selectFields", $selectFields);
		if (checkArray($selectFields))
		{
			foreach ($selectFields as $key => $field)
			{
				$selectFields[$key] = $this->fixField($field);
			}
			$query .= join(',', $selectFields);
		}
		$query .= ' FROM ' . $this->table($table) . ' ';
		$query .= $this->whereSql($this->Orm->Where->Fields->getValues());
		$query .= $this->groupSql($group);
		$query .= $this->orderSql($order);
		$query .= $this->limitSql($limit);
		
		return $query;
	}
	
	//////////////////////////////helers
	
	/**
	 * @param ValueNode $Node
	 * @param string    $table
	 * @return mixed|string'
	 */
	private function fixEditFieldValue(ValueNode $Node, string $table)
	{
		$val       = $Node->get();
		$fieldName = $Node->getFieldName();
		if ($Node->isFunction("simpleValue"))
		{
			$fv = $Node->getFixedValue();
			if (is_numeric($fv) or $fv == 'current_timestamp()')
			{
				return $fv;
			}
			else
			{
				if ($fv === '__fdv_null()')
				{
					return "NULL";
				}
				
				return "'" . $fv . "'";
			}
		}
		elseif ($Node->isFunction("rawQuery"))
		{
			return $val;
		}
		elseif ($Node->isFunction("now"))
		{
			return "now()";
		}
		elseif ($Node->isFunction("null"))
		{
			return "NULL";
		}
		elseif ($Node->isFunction("compress"))
		{
			return "COMPRESS('" . $Node->getFixedValue() . "')";
		}
		elseif ($Node->isFunction("increase"))
		{
			return "$fieldName + " . intval($val);
		}
		elseif ($Node->isFunction("decrease"))
		{
			return "$fieldName - " . intval($val);
		}
		else
		{
			Poesis::error("method '" . $Node->getFunction() . "'' not implemented (field=$fieldName,tableName=$table)");
		}
	}
	
	/**
	 * Generate query WHERE part
	 *
	 * @param array $where
	 * @return string
	 */
	private function whereSql(array $where): string
	{
		if (!checkArray($where))
		{
			return "";
		}
		$queryComponents = [];
		$this->loopWhereItems($queryComponents, $where);
		
		return ' WHERE ' . join(' ', $queryComponents);
	}
	
	private function loopWhereItems(array &$queryComponents, array $items)
	{
		$fieldKey = 0;
		foreach ($items as $fieldName => $item)
		{
			foreach ($item as $key => $Node)
			{
				if ($Node->isGroup())
				{
					$queryComponents[] = "(";
					$this->loopWhereItems($queryComponents, [$fieldName => $Node->get()]);
					$queryComponents[] = ")";
				}
				elseif ($this->Orm->Schema->isRawField($Node->getFieldName()))
				{
					$queryComponents[] = $Node->getFixedValue();
				}
				else
				{
					$fixedField = $this->fixField($Node->getFieldName());
					if ($Node->isFieldLower())
					{
						$fixedField = 'LOWER(' . $fixedField . ')';
					}
					$origValue = $Node->get();
					
					$sqlFunctionLower = Variable::toLower($Node->getFunction());
					
					if (in_array($sqlFunctionLower, ['>', '<', '<=', '>=', '<>']))
					{
						$queryCondition = $fixedField . ' ' . strtoupper($Node->getFunction()) . ' ' . $Node->getFixedValue();
					}
					elseif ($Node->isFunction('notempty'))
					{
						$queryCondition = "TRIM(IFNULL($fixedField,'')) <> ''";
					}
					elseif ($Node->isFunction('empty'))
					{
						$queryCondition = "(TRIM($fixedField) = '' OR $fixedField IS NULL)";
					}
					
					elseif ($Node->isFunction('between'))
					{
						$fixedValue     = $Node->getFixedValue();
						$queryCondition = $fixedField . " BETWEEN '" . $fixedValue[0] . "' AND '" . $fixedValue[1] . "'";
					}
					
					elseif ($Node->isFunction('notbetween'))
					{
						$fixedValue     = $Node->getFixedValue();
						$queryCondition = $fixedField . " NOT BETWEEN '" . $fixedValue[0] . "' AND '" . $fixedValue[1] . "'";
					}
					
					elseif ($Node->isFunction('betweenfields'))
					{
						$queryCondition = $fixedField . ' BETWEEN ' . $this->fixField($origValue[0]) . ' AND ' . $this->fixField($origValue[1]);
					}
					
					elseif ($Node->isFunction('sqlvar'))
					{
						$queryCondition = $fixedField . ' = @' . preg_replace("/[^a-zA-Z0-9_-]/", '', $origValue);//remove all NON letters and numbers, AND _ AND -
					}
					
					elseif ($Node->isFunction('now'))
					{
						$queryCondition = $fixedField . ' ' . $this->operator($origValue) . ' ' . $Node->getFixedValue();
					}
					
					elseif ($Node->isFunction('str'))
					{
						$fixedValue = $Node->getFixedValue();
						if ($Node->isFieldLower())
						{
							$fixedValue = Variable::toLower($fixedValue);
						}
						$queryCondition = $fixedField . ' = ' . $fixedValue;
					}
					
					elseif ($Node->isFunction('rawQuery'))
					{
						$queryCondition = $fixedField . " " . $origValue;
					}
					
					elseif ($Node->isFunction('md5field'))
					{
						$fixedValue = $Node->getFixedValue();
						if ($Node->isMD5Value())
						{
							$fixedValue = md5($fixedValue);
						}
						$queryCondition = 'MD5(' . $fixedField . ') = ' . $fixedValue;
					}
					
					elseif ($Node->isFunction('datefield'))
					{
						$queryCondition = 'DATE(' . $fixedField . ') =' . $Node->getFixedValue();
					}
					
					elseif ($Node->isFunction('notfield'))
					{
						$queryCondition = $fixedField . " != " . $this->fixField($origValue);
					}
					
					elseif ($Node->isFunction('field'))
					{
						$queryCondition = $fixedField . " = " . $this->fixField($origValue);
					}
					
					elseif ($Node->isFunction('not'))
					{
						$queryCondition = $fixedField . ' != ' . $Node->getFixedValue();
					}
					elseif (in_array($sqlFunctionLower, ['like', 'notlike']))
					{
						$b          = $Node->isLeftP() ? "%" : "";
						$e          = $Node->isRightP() ? "%" : "";
						$fixedValue = $Node->getFixedValue();
						if ($Node->isFieldLower())
						{
							$fixedValue = Variable::toLower($fixedValue);
						}
						$op             = ($sqlFunctionLower == "like") ? "LIKE" : "NOT LIKE";
						$queryCondition = $fixedField . " " . $op . " '" . $b . $fixedValue . $e . "'";
					}
					elseif (in_array($sqlFunctionLower, ['isnull', 'null']))
					{
						$queryCondition = $fixedField . ' IS NULL';
					}
					elseif ($Node->isFunction('notnull'))
					{
						$queryCondition = $fixedField . ' IS NOT NULL';
					}
					elseif (in_array($sqlFunctionLower, ['in', 'not in', 'notin', 'insubquery', 'notinsubquery']))
					{
						$f = strtoupper($Node->getFunction());
						if ($sqlFunctionLower == 'insubquery')
						{
							$f          = 'IN';
							$fixedValue = $origValue;
						}
						elseif ($sqlFunctionLower == 'notinsubquery')
						{
							$f          = 'NOT IN';
							$fixedValue = $origValue;
						}
						else
						{
							$fixedValue = $Node->getFixedValue();
							array_walk($fixedValue, function (&$val, $key)
							{
								$val = "'" . $val . "'";
							});
							$fixedValue = join(',', $fixedValue);
						}
						$queryCondition = $fixedField . ' ' . strtoUpper($f) . ' (' . $fixedValue . ')';
					}
					elseif ($Node->isFunction('simplevalue'))
					{
						$fixedValue = $Node->getFixedValue();
						if (!is_string($fixedValue) and !is_numeric($fixedValue))
						{
							Poesis::addExtraErrorInfo('$fixedValue not string', $fixedValue);
						}
						if (is_numeric($fixedValue))
						{
							$fixedValue = " = " . $fixedValue;
						}
						else
						{
							$fixedValue = " = '" . $fixedValue . "'";
						}
						$queryCondition = $fixedField . $fixedValue;
					}
					else
					{
						Poesis::error("SqlFunction $sqlFunctionLower not found");
					}
					
					if ($key == 0 and $fieldKey == 0)
					{
						$queryComponents[] = $queryCondition;
					}
					else
					{
						$queryComponents[] = strtoupper($Node->Op->get());
						$queryComponents[] = $queryCondition;
					}
				}
			}
			$fieldKey++;
		}
	}
	
	private function operator($str)
	{
		$str    = trim($str);
		$output = '';
		if (strlen($str) > 0 and in_array($str{0}, ['<', '=', '>']))
		{
			for ($i = 0; $i <= strlen($str); $i++)
			{
				if (in_array($str{$i}, ['<', '=', '>']))
				{
					$output .= $str{$i};
				}
				else
				{
					return ' ' . $output . ' ';
					break;
				}
			}
		}
		else
		{
			return ' = ';
		}
	}
	
	
	private static function orderSql($order)
	{
		$query = '';
		if (trim($order))
		{
			$query .= ' ORDER BY ' . $order;
		}
		
		return $query;
	}
	
	private static function limitSql($limit)
	{
		$query = "";
		if ($limit = trim($limit))
		{
			$query = ' LIMIT ' . $limit;
		}
		
		return $query;
	}
	
	private static function groupSql($group)
	{
		$query = "";
		if ($group = trim($group))
		{
			$query = ' GROUP BY ' . $group;
		}
		
		return $query;
	}
	
	/**
	 * Fix sql query field
	 *
	 * @param string $field
	 * @param bool   $fixFieldMethodOption_voidAsMatch
	 * @return mixed
	 */
	private function fixField($field, $fixFieldMethodOption_voidAsMatch = false)
	{
		$field = trim($field);
		if ($field == "*")
		{
			$output = "*";
		}
		elseif (preg_match('/[\\w.` ]* as [\\w.`" ]*/i', $field) and $fixFieldMethodOption_voidAsMatch == false)
		{
			$ex     = preg_split('/as /i', $field);
			$output = $this->fixField($ex[0]) . ' AS ' . $this->fixField($ex[1], '"');
		}
		elseif (strpos($field, '(') and strpos($field, ')'))
		{
			$matches = Regex::getMatches('/\((\w|\.)+\)/i', $field); //\((\w|\.)+\)
			if (checkArray($matches))
			{
				foreach ($matches as $match)
				{
					$field = str_replace($match, "(" . $this->fixField(str_replace(["(", ")"], "", $match)) . ")", $field);
				}
			}
			$output = $field;
		}
		elseif (strpos($field, '.'))
		{
			$ex     = explode('.', $field);
			$output = $this->fixField($ex[0]) . '.' . $this->fixField($ex[1]);
		}
		else
		{
			$field = trim($field);
			if (!preg_match('/`[\\w]*`/i', $field))
			{
				$output = '`' . $field . '`';
			}
			else
			{
				$output = $field;
			}
		}
		
		return $output;
	}
	
	/**
	 * Fix table name string
	 *
	 * @param string $table
	 * @return string
	 */
	private function table($table)
	{
		return $this->fixField($table, true);
	}
}

