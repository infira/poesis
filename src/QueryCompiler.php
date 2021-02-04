<?php

namespace Infira\Poesis;

use Infira\Poesis\orm\node\Field;
use Infira\Poesis\orm\node\Statement;
use Infira\Utils\Regex;
use Infira\Poesis\orm\node\LogicalOperator;

class QueryCompiler
{
	const RAW_QUERY = "__raw_sql_query";
	
	public static function select(Statement $statement): string
	{
		$table         = $statement->table();
		$order         = $statement->orderBy();
		$limit         = $statement->limit();
		$group         = $statement->groupBy();
		$selectColumns = $statement->columns();
		
		$query = 'SELECT ';
		
		if ($selectColumns == '*' or !$selectColumns)
		{
			$query .= '*';
		}
		elseif (!is_array($selectColumns))
		{
			$selectColumns = preg_split("/,(?![^()]*+\\))/", $selectColumns);
		}
		if (checkArray($selectColumns))
		{
			foreach ($selectColumns as $key => $column)
			{
				$selectColumns[$key] = self::fixColumn_Table($column);
			}
			$query .= join(',', $selectColumns);
		}
		$query .= ' FROM ' . self::fixColumn_Table($table);
		$query .= ' ' . self::whereSql($statement->whereClauses());
		$query .= self::groupSql($group);
		$query .= self::orderSql($order);
		$query .= self::limitSql($limit);
		
		return $query;
	}
	
	public static function insert(Statement $statement): string
	{
		return 'INSERT ' . self::intoSql($statement);
	}
	
	public static function replace(Statement $statement)
	{
		return 'REPLACE ' . self::intoSql($statement);
	}
	
	public static function delete(Statement $statement)
	{
		$table = self::fixColumn_Table($statement->table());
		$query = 'DELETE FROM ' . $table . ' ' . self::whereSql($statement->whereClauses());
		$query .= self::groupSql($statement->groupBy());
		$query .= self::orderSql($statement->orderBy());
		$query .= self::limitSql($statement->limit());
		
		return trim($query);
	}
	
	public static function update(Statement $mainStatement): string
	{
		$genUpdateQuery = function (Statement $statement) use (&$mainStatement)
		{
			$query = 'UPDATE ' . self::fixColumn_Table($mainStatement->table()) . ' SET ';
			foreach ($statement->clauses() as $groupIndex => $groupItems)
			{
				/**
				 * @var Field $field
				 */
				foreach ($groupItems as $field)
				{
					$query .= $field->getColumnForFinalQuery(false) . ' = ' . self::fixEditColumnValue($field) . ', ';
				}
			}
			$query = substr($query, 0, -2);// Remove the last comma
			$query .= ' ' . self::whereSql($statement->whereClauses());
			$query .= self::groupSql($mainStatement->groupBy());
			$query .= self::orderSql($mainStatement->orderBy());
			$query .= self::limitSql($mainStatement->limit());
			
			
			return trim($query);
		};
		
		if ($mainStatement->isCollection())
		{
			$query = '';
			foreach ($mainStatement->getCollectionData() as $collectionStatement)
			{
				$query .= $genUpdateQuery($collectionStatement) . ';';
			}
			$query = substr($query, 0, -1);// Remove the last comma
			
			return $query;
		}
		else
		{
			return $genUpdateQuery($mainStatement);
		}
	}
	
	//////////////////////////////helers
	
	/**
	 * Generates into sql
	 *
	 * @param Statement $statement
	 * @throws \Infira\Poesis\Error
	 * @return string
	 */
	private static function intoSql(Statement $statement): string
	{
		$query                     = 'INTO ' . self::fixColumn_Table($statement->table()) . ' ';
		$columns                   = '';
		$values                    = '';
		$genColumnsValuesQueryPart = function ($columnValues) use (&$statement)
		{
			$Output = (object)["columns" => [], "values" => []];
			foreach ($columnValues as $groupIndex => $values)
			{
				/**
				 * @var Field $Node
				 */
				foreach ($values as $node)
				{
					if ($node instanceof LogicalOperator)
					{
						Poesis::error("Cannot use operator in edit/insetQuery");
					}
					$Output->columns[] = $node->getColumnForFinalQuery(false);
					$Output->values[]  = self::fixEditColumnValue($node);
				}
			}
			
			return $Output;
		};
		if ($statement->isCollection())
		{
			$collections = $statement->getCollectionData();
			$lastKey     = array_key_last($collections);
			/**
			 * @var Statement $collectionStatement
			 */
			foreach ($collections as $collectionKey => $collectionStatement)
			{
				$i = $genColumnsValuesQueryPart($collectionStatement->clauses());
				if ($collectionKey == 0)
				{
					$columns .= "(" . join(",", $i->columns) . ")";
				}
				$values .= "\n" . "(" . join(",", $i->values) . ")";
				if ($collectionKey != $lastKey)
				{
					$values .= ", ";
				}
			}
		}
		else
		{
			
			$i       = $genColumnsValuesQueryPart($statement->clauses());
			$columns .= "(" . join(",", $i->columns) . ")";
			$values  .= "(" . join(",", $i->values) . ")";
		}
		$query .= $columns . ' VALUES ' . $values;
		
		return trim($query);
	}
	
	private static function fixEditColumnValue(Field $field)
	{
		$field->fix('edit');
		if ($field->isPredicateType('simpleValue'))
		{
			return $field->getFinalValue();
		}
		elseif ($field->isPredicateType("rawValue,strictRawValue"))
		{
			return str_replace('IS NULL', 'NULL', $field->getFinalValue());
		}
		elseif ($field->isPredicateType("inDeCrease"))
		{
			return $field->getColumnForFinalQuery(false) . $field->getOperator() . $field->getFinalValue();
		}
		else
		{
			Poesis::error("Unimplemented query type", ['$field' => $field]);
		}
	}
	
	/**
	 * Fix sql query column
	 *
	 * @param string $name
	 * @return mixed
	 */
	public static function fixColumn_Table(string $name): string
	{
		$name = trim($name);
		if ($name == "*")
		{
			$output = "*";
		}
		elseif (preg_match('/[\\w.` ]* as [\\w.`" ]*/i', $name))
		{
			$ex     = preg_split('/as /i', $name);
			$output = self::fixColumn_Table($ex[0]) . ' AS ' . self::fixColumn_Table($ex[1], '"');
		}
		elseif (strpos($name, '(') and strpos($name, ')'))
		{
			$matches = Regex::getMatches('/\((\w|\.)+\)/i', $name); //\((\w|\.)+\)
			if (checkArray($matches))
			{
				foreach ($matches as $match)
				{
					$name = str_replace($match, "(" . self::fixColumn_Table(str_replace(["(", ")"], "", $match)) . ")", $name);
				}
			}
			$output = $name;
		}
		elseif (strpos($name, '.'))
		{
			$ex     = explode('.', $name);
			$output = self::fixColumn_Table($ex[0]) . '.' . self::fixColumn_Table($ex[1]);
		}
		else
		{
			$name = trim($name);
			if (!preg_match('/`[\\w]*`/i', $name))
			{
				$output = '`' . $name . '`';
			}
			else
			{
				$output = $name;
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
	private static function whereSql(array $where): string
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
			if ($ci > 1 or ($ci == 1 and !$groupItems[0] instanceof LogicalOperator))
			{
				$madeIntoForeach = true;
				$lastNodeIndex   = array_key_last($groupItems);
				/**
				 * @var Field $field
				 */
				foreach ($groupItems as $nodeIndex => $field)
				{
					$opIsSetted = false;
					
					if ($field instanceof LogicalOperator)
					{
						$opIsSetted        = true;
						$queryComponents[] = $field->get();
					}
					elseif ($field->getColumn() === QueryCompiler::RAW_QUERY)
					{
						$field->fix('select');
						$queryComponents[] = $field->getValue();
					}
					else
					{
						$fixedColumn = trim($field->getColumnForFinalQuery(true));
						$field->fix('select');
						if ($field->isPredicateType('between'))
						{
							$queryCondition = $fixedColumn . ' BETWEEN ' . $field->getFinalValueAt(0) . " AND " . $field->getFinalValueAt(1);
						}
						elseif ($field->isPredicateType('betweenColumns'))
						{
							$queryCondition = $fixedColumn . ' BETWEEN ' . self::fixColumn_Table($field->getAt(0)) . ' AND ' . self::fixColumn_Table($field->getAt(1));
						}
						elseif ($field->isPredicateType('simpleValue,like,rawValue,strictRawValue'))
						{
							$op             = $field->getOperator();
							$op             = $op ? ' ' . $op . ' ' : ' ';
							$queryCondition = $fixedColumn . $op . $field->getFinalValue();
						}
						elseif ($field->isPredicateType('compareColumn'))
						{
							$op             = $field->getOperator();
							$op             = $op ? ' ' . $op . ' ' : ' ';
							$queryCondition = $fixedColumn . $op . self::fixColumn_Table($field->getValue());
						}
						elseif ($field->isPredicateType('in'))
						{
							$fixedValue = $field->getFinalValue();
							if (is_array($fixedValue))
							{
								$fixedValue = join(',', $fixedValue);
							}
							$op             = $field->getOperator();
							$op             = $op ? ' ' . $op . ' ' : ' ';
							$queryCondition = $fixedColumn . $op . "(" . $fixedValue . ')';
						}
						else
						{
							Poesis::error("Unimplemented query type");
						}
						$queryComponents[] = $queryCondition;
					}
					
					if ($nodeIndex != $lastNodeIndex)
					{
						$nextNode = $groupItems[$nodeIndex + 1];
						if (!$opIsSetted and !$nextNode instanceof LogicalOperator)
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
				if (count($nextGroup) == 1 and $nextGroup[0] instanceof LogicalOperator)
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
}

?>
