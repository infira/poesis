<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\orm\node\ValueNode;
use Infira\Utils\Variable;
use Infira\Poesis\Poesis;
use Infira\Utils\Regex;
use Infira\Error\Node;
use Infira\Poesis\orm\node\OperatorNode;
use Infira\Utils\Is;
use Infira\Utils\Fix;
use Infira\Poesis\orm\node\FixedValueNode;

class QueryCompiler
{
	const RAW_QUERY_FIELD = "__raw_sql_query_field";
	/**
	 * @var Model
	 */
	private $Orm;
	
	public function __construct(&$Obj)
	{
		$this->Orm = &$Obj;
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
			$Output = (object)["fields" => [], "values" => []];
			foreach ($fieldAndValues as $groupIndex => $values)
			{
				foreach ($values as $Node)
				{
					addExtraErrorInfo('$Node', $Node);
					if ($Node->isOperator())
					{
						Poesis::error("Cannot use operator in edit/insetQuery");
					}
					$Output->fields[] = self::fixField($Node->getFieldName());
					$Output->values[] = $this->fixEditFieldValue($Node, $table);
				}
			}
			
			return $Output;
		};
		if ($isCollection)
		{
			$collections = $this->Orm->getCollectionValues();
			$lastKey     = array_key_last($collections);
			foreach ($collections as $collectionKey => $collectionData)
			{
				$i = $genFieldsValuesSQL($collectionData->fields);
				if ($collectionKey == 0)
				{
					$fields .= "(" . join(",", $i->fields) . ")";
				}
				$values .= "\n" . "(" . join(",", $i->values) . ")";
				if ($collectionKey != $lastKey)
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
		
		return trim($query);
	}
	
	/**
	 * Generates SQL delete query
	 *
	 * @return string delete query
	 */
	public function delete()
	{
		$table = $this->Orm->getTableName();
		
		$table = $this->table($table);
		$query = 'DELETE FROM ' . $table . ' ' . $this->whereSql($this->Orm->Where->Fields->getValues());
		$query .= $this->groupSql($this->Orm->getGroupBy());
		$query .= $this->orderSql($this->Orm->getOrderBy());
		$query .= $this->limitSql($this->Orm->getLimit());
		
		return trim($query);
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
			foreach ($Data->fields as $groupIndex => $groupItems)
			{
				foreach ($groupItems as $Node)
				{
					$query .= self::fixField($Node->getFieldName()) . ' = ' . $this->fixEditFieldValue($Node, $table) . ', ';
				}
			}
			$query = substr($query, 0, -2);// Remove the last comma
			$query .= ' ' . $this->whereSql($Data->where);
			$query .= $this->groupSql($group);
			$query .= $this->orderSql($order);
			$query .= $this->limitSql($limit);
			
			
			return trim($query);
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
				$selectFields[$key] = self::fixField($field);
			}
			$query .= join(',', $selectFields);
		}
		$query .= ' FROM ' . $this->table($table);
		$query .= ' ' . $this->whereSql($this->Orm->Where->Fields->getValues());
		$query .= $this->groupSql($group);
		$query .= $this->orderSql($order);
		$query .= $this->limitSql($limit);
		
		return $query;
	}
	
	//////////////////////////////helers
	
	private function fixEditFieldValue(ValueNode $Node, string $table)
	{
		$val       = $Node->get();
		$fieldName = $Node->getFieldName();
		if ($Node->isFunction("simpleValue"))
		{
			return $this->fixSelectFieldValue($Node)->get();
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
			return "COMPRESS(" . $this->fixSelectFieldValue($Node)->get() . ")";
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
	 * @param ValueNode $Node
	 * @param bool      $addQuotes
	 * @return FixedValueNode|array
	 */
	private function fixSelectFieldValue(ValueNode $Node, bool $addQuotes = false)
	{
		$fixedValue = $Node->getFixedValue();
		if (is_array($fixedValue))
		{
			if ($addQuotes)
			{
				foreach ($fixedValue as $k => $fv)
				{
					$fv->value($this->escape($fv->value()));
					$fixedValue[$k] = $fv->get();
				}
			}
			else
			{
				array_walk($fixedValue, function (&$item)
				{
					$item->value($this->escape($item->value()));
				});
			}
		}
		else
		{
			$fixedValue->value($this->escape($fixedValue->value()));
		}
		
		return $fixedValue;
	}
	
	private function escape($value)
	{
		if (strpos($value, '[MSQL-ESCAPE]') !== false)
		{
			$matches = [];
			preg_match_all('/\[MSQL-ESCAPE\](.*)\[\/MSQL-ESCAPE\]/ms', $value, $matches);
			$value = preg_replace('/\[MSQL-ESCAPE\](.*)\[\/MSQL-ESCAPE\]/ms', $this->Orm->Con->escape($matches[1][0]), $value);
		}
		
		return $value;
	}
	
	/**
	 * Fix sql query field
	 *
	 * @param string $field
	 * @param bool   $fixFieldMethodOption_voidAsMatch
	 * @return mixed
	 */
	public static function fixField($field, $fixFieldMethodOption_voidAsMatch = false)
	{
		$field = trim($field);
		if ($field == "*")
		{
			$output = "*";
		}
		elseif (preg_match('/[\\w.` ]* as [\\w.`" ]*/i', $field) and $fixFieldMethodOption_voidAsMatch == false)
		{
			$ex     = preg_split('/as /i', $field);
			$output = self::fixField($ex[0]) . ' AS ' . self::fixField($ex[1], '"');
		}
		elseif (strpos($field, '(') and strpos($field, ')'))
		{
			$matches = Regex::getMatches('/\((\w|\.)+\)/i', $field); //\((\w|\.)+\)
			if (checkArray($matches))
			{
				foreach ($matches as $match)
				{
					$field = str_replace($match, "(" . self::fixField(str_replace(["(", ")"], "", $match)) . ")", $field);
				}
			}
			$output = $field;
		}
		elseif (strpos($field, '.'))
		{
			$ex     = explode('.', $field);
			$output = self::fixField($ex[0]) . '.' . self::fixField($ex[1]);
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
		$queryComponentsString = trim(join(' ', $queryComponents));
		
		return 'WHERE ' . $queryComponentsString;
	}
	
	private function loopWhereItems(array &$queryComponents, array $items)
	{
		//debug($items);
		$lastGroupIndex = array_key_last($items);
		foreach ($items as $groupIndex => $groupItems)
		{
			$ci = count($groupItems);
			if ($ci > 1)
			{
				$queryComponents[] = '(';
			}
			$madeIntoForeach = false;
			if ($ci > 1 or ($ci == 1 and !$groupItems[0]->isOperator()))
			{
				$madeIntoForeach = true;
				$lastNodeIndex   = array_key_last($groupItems);
				foreach ($groupItems as $nodeIndex => $Node)
				{
					$opIsSetted = false;
					
					if ($Node->isOperator())
					{
						$opIsSetted        = true;
						$queryComponents[] = $Node->get();
					}
					elseif ($this->Orm->Schema::isRawField($Node->getFieldName()))
					{
						$queryComponents[] = $this->fixSelectFieldValue($Node)->value();
					}
					else
					{
						$fixedField = $Node->getFieldNameWithFunction();
						if ($Node->isFieldLower())
						{
							$fixedField = 'LOWER(' . $fixedField . ')';
						}
						$origValue = $Node->get();
						
						$sqlFunctionLower = Variable::toLower($Node->getFunction());
						
						if (in_array($sqlFunctionLower, ['>', '<', '<=', '>=', '<>']))
						{
							$queryCondition = $fixedField . ' ' . strtoupper($Node->getFunction()) . ' ' . $this->fixSelectFieldValue($Node)->get();
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
							$fixedValue     = $this->fixSelectFieldValue($Node);
							$queryCondition = $fixedField . " BETWEEN " . $fixedValue[0]->get() . " AND " . $fixedValue[1]->get();
						}
						elseif ($Node->isFunction('notbetween'))
						{
							$fixedValue     = $this->fixSelectFieldValue($Node);
							$queryCondition = $fixedField . " NOT BETWEEN " . $fixedValue[0]->get() . " AND " . $fixedValue[1]->get();
						}
						elseif ($Node->isFunction('betweenfields'))
						{
							$queryCondition = $fixedField . ' BETWEEN ' . self::fixField($origValue[0]) . ' AND ' . self::fixField($origValue[1]);
						}
						elseif ($Node->isFunction('sqlvar'))
						{
							$queryCondition = $fixedField . ' = @' . preg_replace("/[^a-zA-Z0-9_-]/", '', $origValue);//remove all NON letters and numbers, AND _ AND -
						}
						elseif ($Node->isFunction('now'))
						{
							$queryCondition = $fixedField . ' ' . $this->operator($origValue) . ' ' . $this->fixSelectFieldValue($Node)->get();
						}
						elseif ($Node->isFunction('str'))
						{
							$fixedValue = $this->fixSelectFieldValue($Node);
							if ($Node->isFieldLower())
							{
								$fixedValue->value(Variable::toLower($fixedValue->value()));
							}
							$queryCondition = $fixedField . ' = ' . $fixedValue->get();
						}
						elseif ($Node->isFunction('rawQuery'))
						{
							$queryCondition = $fixedField . " " . $origValue;
						}
						elseif ($Node->isFunction('md5field'))
						{
							$fixedValue = $this->fixSelectFieldValue($Node);
							if ($Node->isMD5Value())
							{
								$fixedValue->value(md5($fixedValue->value()));
							}
							$queryCondition = 'MD5(' . $fixedField . ') = ' . $fixedValue->get();
						}
						elseif ($Node->isFunction('datefield'))
						{
							$queryCondition = 'DATE(' . $fixedField . ') =' . $this->fixSelectFieldValue($Node)->get();
						}
						elseif ($Node->isFunction('notfield'))
						{
							$queryCondition = $fixedField . " != " . self::fixField($origValue);
						}
						elseif ($Node->isFunction('field'))
						{
							$queryCondition = $fixedField . " = " . self::fixField($origValue);
						}
						elseif ($Node->isFunction('not'))
						{
							$queryCondition = $fixedField . ' != ' . $this->fixSelectFieldValue($Node)->get();
						}
						elseif (in_array($sqlFunctionLower, ['like', 'notlike']))
						{
							$b          = $Node->isLeftP() ? "%" : "";
							$e          = $Node->isRightP() ? "%" : "";
							$fixedValue = $this->fixSelectFieldValue($Node);
							if ($Node->isFieldLower())
							{
								$fixedValue->value(Variable::toLower($fixedValue->value()));
							}
							$op             = ($sqlFunctionLower == "like") ? "LIKE" : "NOT LIKE";
							$queryCondition = $fixedField . " " . $op . " '" . $b . $fixedValue->value() . $e . "'";
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
								$fvArr = $this->fixSelectFieldValue($Node);
								array_walk($fvArr, function (&$item)
								{
									$item = $item->get();
								});
								$fixedValue = join(',', $fvArr);
							}
							$queryCondition = $fixedField . ' ' . strtoUpper($f) . ' (' . $fixedValue . ')';
						}
						elseif ($Node->isFunction('simplevalue'))
						{
							$queryCondition = $fixedField . " = " . $this->fixSelectFieldValue($Node)->get();
						}
						else
						{
							Poesis::error("SqlFunction $sqlFunctionLower not found");
						}
						$queryComponents[] = $queryCondition;
					}
					
					if ($nodeIndex != $lastNodeIndex)
					{
						$nextNode = $groupItems[$nodeIndex + 1];
						if (!$opIsSetted and !$nextNode->isOperator())
						{
							$queryComponents[] = 'AND';
						}
					}
				}
			}
			if ($ci > 1)
			{
				$queryComponents[] = ')';
			}
			if ($groupIndex != $lastGroupIndex)
			{
				$nextGroup = $items[$groupIndex + 1];
				if (count($nextGroup) == 1 and $nextGroup[0]->isOperator())
				{
					$queryComponents[] = $nextGroup[0]->get();
				}
				else
				{
					if ($madeIntoForeach)
					{
						$queryComponents[] = 'AND';
					}
				}
			}
		}
		//debug($queryComponents);exit;
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
					return $output;
					break;
				}
			}
		}
		else
		{
			return '=';
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
	 * Fix table name string
	 *
	 * @param string $table
	 * @return string
	 */
	private function table($table)
	{
		return self::fixField($table, true);
	}
}

?>
