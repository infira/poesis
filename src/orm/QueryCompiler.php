<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\orm\node\ValueNode;
use Infira\Poesis\Poesis;
use Infira\Poesis\orm\node\QueryNode;
use Infira\Utils\Variable;
use Infira\Utils\Regex;

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
					$Output->fields[] = $node->getQuotedField();
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
					$query .= $node->getQuotedField() . ' = ' . $this->fixEditFieldValue($node, $queryNode->table) . ', ';
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
			$query .= '*';
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
		$this->fixNodeValue($node);
		addExtraErrorInfo('$node', $node);
		if ($node->isType('simpleValue'))
		{
			return self::getQuotedValue($node->getFinalValue(), $node->getFinalType());
		}
		elseif ($node->isType("rawValue"))
		{
			return str_replace('IS NULL', 'NULL', $node->getFinalValue());
		}
		elseif ($node->isType("inDeCrease"))
		{
			return $node->getQuotedField() . $node->getOperator() . intval($node->getFinalValue());
		}
		else
		{
			addExtraErrorInfo('node', $node);
			Poesis::error("Unimplemented query type");
		}
	}
	
	/**
	 * @param ValueNode $node
	 * @return ValueNode|array
	 */
	private function fixNodeValue(ValueNode $node)
	{
		$nodeValue = $node->get();
		if (is_array($nodeValue))
		{
			array_walk($nodeValue, function (&$item) use ($node)
			{
				$fv   = $node->fixValueByType($item);
				$item = self::getQuotedValue($this->escape($fv[1]), $fv[0]);
			});
			$node->setFinalValue($nodeValue);
		}
		else
		{
			$node->fixValue($nodeValue);
			$node->setFinalValue($this->escape($node->getFinalValue()));
		}
	}
	
	public static function getQuotedValue($value, string $type)
	{
		if (!is_string($value) and !is_numeric($value) and $type != 'expression')
		{
			Poesis::error('value must string or number', ['value' => $value]);
		}
		
		if (in_array($type, ['expression', 'function', 'numeric']))
		{
			return $value;
		}
		elseif ($type == 'string')
		{
			return "'" . $value . "'";
		}
		else
		{
			Poesis::error('Unknown type', ['type' => $type]);
		}
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
		$lastGroupIndex  = array_key_last($where);
		foreach ($where as $groupIndex => $groupItems)
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
						$this->fixNodeValue($node);
						$queryComponents[] = $node->get();
					}
					else
					{
						$fixedField = $node->getFieldNameWithFunction();
						$this->fixNodeValue($node);
						addExtraErrorInfo('$node', $node);
						
						if ($node->isType('between'))
						{
							$queryCondition = $fixedField . " BETWEEN " . $node->getFinalValueAt(0) . " AND " . $node->getFinalValueAt(1);
						}
						elseif ($node->isType('betweenFields'))
						{
							$queryCondition = $fixedField . ' BETWEEN ' . self::fixField($node->getAt(0)) . ' AND ' . self::fixField($node->getAt(1));
						}
						elseif ($node->isType('simpleValue'))
						{
							$qv = self::getQuotedValue($node->getFinalValue(), $node->getFinalType());
							addExtraErrorInfo('$node->getQuotedValue()', $qv);
							$queryCondition = $fixedField . ' ' . $node->getOperator() . ' ' . $qv;
						}
						elseif ($node->isType('compareField'))
						{
							$queryCondition = $fixedField . ' ' . $node->getOperator() . ' ' . self::fixField($node->get());
						}
						elseif ($node->isType('rawValue'))
						{
							$queryCondition = $fixedField . ' ' . $node->getOperator() . ' ' . $node->get();
						}
						elseif ($node->isType('like'))
						{
							$b              = $node->getValueSuffix();
							$e              = $node->getValuePrefix();
							$queryCondition = $fixedField . " " . $node->getOperator() . " '" . $b . $node->getFinalValue() . $e . "'";
						}
						elseif ($node->isType('in'))
						{
							$fixedValue = $node->getFinalValue();
							if (is_array($fixedValue))
							{
								$fixedValue = join(',', $fixedValue);
							}
							$queryCondition = $fixedField . " " . $node->getOperator() . " (" . $fixedValue . ')';
						}
						else
						{
							addExtraErrorInfo('node', $node);
							Poesis::error("Unimplemented query type");
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
				$nextGroup = $where[$groupIndex + 1];
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
		$queryComponentsString = trim(join(' ', $queryComponents));
		
		return 'WHERE ' . $queryComponentsString;
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
