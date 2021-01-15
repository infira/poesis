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
use Infira\Poesis\orm\node\QueryNode;

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
	
	
	public function insert(QueryNode $queryNode): string
	{
		return 'INSERT ' . $this->intoSql($queryNode);
	}
	
	public function replace(QueryNode $queryNode)
	{
		return 'REPLACE ' . $this->intoSql($queryNode);
	}
	
	
	/**
	 * Generates into sql
	 *
	 * @return string
	 */
	private function intoSql(QueryNode $queryNode): string
	{
		$query              = 'INTO ' . $this->table($queryNode->table) . ' ';
		$fields             = '';
		$values             = '';
		$genFieldsValuesSQL = function ($fieldAndValues) use (&$queryNode)
		{
			$Output = (object)["fields" => [], "values" => []];
			foreach ($fieldAndValues as $groupIndex => $values)
			{
				/**
				 * @var ValueNode $Node
				 */
				foreach ($values as $node)
				{
					if ($node->isOperator())
					{
						Poesis::error("Cannot use operator in edit/insetQuery");
					}
					$Output->fields[] = self::fixField($node->getActualField());
					$Output->values[] = $this->fixEditFieldValue($node, $queryNode->table);
				}
			}
			
			return $Output;
		};
		if ($queryNode->isCollection)
		{
			$collections = $queryNode->collectionValues;
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
			
			$i      = $genFieldsValuesSQL($queryNode->fields);
			$fields .= "(" . join(",", $i->fields) . ")";
			$values .= "(" . join(",", $i->values) . ")";
		}
		$query .= $fields . ' VALUES ' . $values;
		
		return trim($query);
	}
	
	public function delete(QueryNode $queryNode)
	{
		$table = $this->table($queryNode->table);
		$query = 'DELETE FROM ' . $table . ' ' . $this->whereSql($queryNode->where);
		$query .= $this->groupSql($queryNode->groupBy);
		$query .= $this->orderSql($queryNode->orderBy);
		$query .= $this->limitSql($queryNode->limit);
		
		return trim($query);
	}
	
	public function update(QueryNode $queryNode): string
	{
		$genUpdateQuery = function ($Data) use (&$queryNode)
		{
			$query = 'UPDATE ' . $this->table($queryNode->table) . ' SET ';
			foreach ($Data->fields as $groupIndex => $groupItems)
			{
				/**
				 * @var ValueNode $node
				 */
				foreach ($groupItems as $node)
				{
					$query .= self::fixField($node->getActualField()) . ' = ' . $this->fixEditFieldValue($node, $queryNode->table) . ', ';
				}
			}
			$query = substr($query, 0, -2);// Remove the last comma
			$query .= ' ' . $this->whereSql($Data->where);
			$query .= $this->groupSql($queryNode->groupBy);
			$query .= $this->orderSql($queryNode->orderBy);
			$query .= $this->limitSql($queryNode->limit);
			
			
			return trim($query);
		};
		
		if ($queryNode->isCollection)
		{
			$query = '';
			foreach ($queryNode->collectionValues as $Collection)
			{
				$query .= $genUpdateQuery($Collection) . ';';
			}
			$query = substr($query, 0, -1);// Remove the last comma
			
			return $query;
		}
		else
		{
			return $genUpdateQuery((object)['fields' => $queryNode->fields, 'where' => $queryNode->where]);
		}
	}
	
	public function select(QueryNode $queryNode)
	{
		$table        = $queryNode->table;
		$order        = $queryNode->orderBy;
		$limit        = $queryNode->limit;
		$group        = $queryNode->groupBy;
		$selectFields = $queryNode->selectFields;
		
		$query = 'SELECT ';
		
		if ($selectFields == '*' or !$selectFields)
		{
			$query .= ' * ';
		}
		elseif (!is_array($selectFields))
		{
			$selectFields = preg_split("/,(?![^()]*+\\))/", $selectFields);
		}
		if (checkArray($selectFields))
		{
			foreach ($selectFields as $key => $field)
			{
				$selectFields[$key] = self::fixField($field);
			}
			$query .= join(',', $selectFields);
		}
		$query .= ' FROM ' . $this->table($table);
		$query .= ' ' . $this->whereSql($queryNode->where);
		$query .= $this->groupSql($group);
		$query .= $this->orderSql($order);
		$query .= $this->limitSql($limit);
		
		return $query;
	}
	
	//////////////////////////////helers
	
	private function fixEditFieldValue(ValueNode $node, string $table)
	{
		$val       = $node->get();
		$fieldName = $node->getActualField();
		if ($node->isFunction("simpleValue"))
		{
			return $this->fixSelectFieldValue($node)->get();
		}
		elseif ($node->isFunction("rawQuery"))
		{
			return $val;
		}
		elseif ($node->isFunction("now"))
		{
			return "now()";
		}
		elseif ($node->isFunction("null"))
		{
			return "NULL";
		}
		elseif ($node->isFunction("compress"))
		{
			return "COMPRESS(" . $this->fixSelectFieldValue($node)->get() . ")";
		}
		elseif ($node->isFunction("increase"))
		{
			return "$fieldName + " . intval($val);
		}
		elseif ($node->isFunction("decrease"))
		{
			return "$fieldName - " . intval($val);
		}
		else
		{
			Poesis::error("method '" . $node->getFunction() . "'' not implemented (field=$fieldName,tableName=$table)");
		}
	}
	
	/**
	 * @param ValueNode $node
	 * @param bool      $addQuotes
	 * @return FixedValueNode|array
	 */
	private function fixSelectFieldValue(ValueNode $node, bool $addQuotes = false)
	{
		$fixedValue = $node->getFixedValue();
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
				/**
				 * @var ValueNode $node
				 */
				foreach ($groupItems as $nodeIndex => $node)
				{
					$opIsSetted = false;
					
					if ($node->isOperator())
					{
						$opIsSetted        = true;
						$queryComponents[] = $node->get();
					}
					elseif ($this->Orm->Schema::isRawField($node->getField()))
					{
						$queryComponents[] = $this->fixSelectFieldValue($node)->value();
					}
					else
					{
						$fixedField = $node->getFieldNameWithFunction();
						if ($node->isFieldLower())
						{
							$fixedField = 'LOWER(' . $fixedField . ')';
						}
						$origValue = $node->get();
						
						$sqlFunctionLower = Variable::toLower($node->getFunction());
						
						if (in_array($sqlFunctionLower, ['>', '<', '<=', '>=', '<>']))
						{
							$queryCondition = $fixedField . ' ' . strtoupper($node->getFunction()) . ' ' . $this->fixSelectFieldValue($node)->get();
						}
						elseif ($node->isFunction('notempty'))
						{
							$queryCondition = "TRIM(IFNULL($fixedField,'')) <> ''";
						}
						elseif ($node->isFunction('empty'))
						{
							$queryCondition = "(TRIM($fixedField) = '' OR $fixedField IS NULL)";
						}
						elseif ($node->isFunction('between'))
						{
							$fixedValue     = $this->fixSelectFieldValue($node);
							$queryCondition = $fixedField . " BETWEEN " . $fixedValue[0]->get() . " AND " . $fixedValue[1]->get();
						}
						elseif ($node->isFunction('notbetween'))
						{
							$fixedValue     = $this->fixSelectFieldValue($node);
							$queryCondition = $fixedField . " NOT BETWEEN " . $fixedValue[0]->get() . " AND " . $fixedValue[1]->get();
						}
						elseif ($node->isFunction('betweenfields'))
						{
							$queryCondition = $fixedField . ' BETWEEN ' . self::fixField($origValue[0]) . ' AND ' . self::fixField($origValue[1]);
						}
						elseif ($node->isFunction('sqlvar'))
						{
							$queryCondition = $fixedField . ' = @' . preg_replace("/[^a-zA-Z0-9_-]/", '', $origValue);//remove all NON letters and numbers, AND _ AND -
						}
						elseif ($node->isFunction('now'))
						{
							$queryCondition = $fixedField . ' ' . $this->operator($origValue) . ' ' . $this->fixSelectFieldValue($node)->get();
						}
						elseif ($node->isFunction('str'))
						{
							$fixedValue = $this->fixSelectFieldValue($node);
							if ($node->isFieldLower())
							{
								$fixedValue->value(Variable::toLower($fixedValue->value()));
							}
							$queryCondition = $fixedField . ' = ' . $fixedValue->get();
						}
						elseif ($node->isFunction('rawQuery'))
						{
							$queryCondition = $fixedField . " " . $origValue;
						}
						elseif ($node->isFunction('md5field'))
						{
							$fixedValue = $this->fixSelectFieldValue($node);
							if ($node->isMD5Value())
							{
								$fixedValue->value(md5($fixedValue->value()));
							}
							$queryCondition = 'MD5(' . $fixedField . ') = ' . $fixedValue->get();
						}
						elseif ($node->isFunction('datefield'))
						{
							$queryCondition = 'DATE(' . $fixedField . ') =' . $this->fixSelectFieldValue($node)->get();
						}
						elseif ($node->isFunction('notfield'))
						{
							$queryCondition = $fixedField . " != " . self::fixField($origValue);
						}
						elseif ($node->isFunction('field'))
						{
							$queryCondition = $fixedField . " = " . self::fixField($origValue);
						}
						elseif ($node->isFunction('not'))
						{
							$queryCondition = $fixedField . ' != ' . $this->fixSelectFieldValue($node)->get();
						}
						elseif (in_array($sqlFunctionLower, ['like', 'notlike']))
						{
							$b          = $node->isLeftP() ? "%" : "";
							$e          = $node->isRightP() ? "%" : "";
							$fixedValue = $this->fixSelectFieldValue($node);
							if ($node->isFieldLower())
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
						elseif ($node->isFunction('notnull'))
						{
							$queryCondition = $fixedField . ' IS NOT NULL';
						}
						elseif (in_array($sqlFunctionLower, ['in', 'not in', 'notin', 'insubquery', 'notinsubquery']))
						{
							$f = strtoupper($node->getFunction());
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
								$fvArr = $this->fixSelectFieldValue($node);
								array_walk($fvArr, function (&$item)
								{
									$item = $item->get();
								});
								$fixedValue = join(',', $fvArr);
							}
							$queryCondition = $fixedField . ' ' . strtoUpper($f) . ' (' . $fixedValue . ')';
						}
						elseif ($node->isFunction('simplevalue'))
						{
							$queryCondition = $fixedField . " = " . $this->fixSelectFieldValue($node)->get();
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
