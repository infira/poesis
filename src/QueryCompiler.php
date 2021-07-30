<?php

namespace Infira\Poesis;

use Infira\Poesis\orm\node\Field;
use Infira\Poesis\orm\node\Statement;
use Infira\Utils\Regex;
use Infira\Poesis\orm\node\LogicalOperator;

class QueryCompiler
{
	const RAW_QUERY = "__raw_sql_query";
	
	public static function select(Statement $statement, $selectColumns): string
	{
		$table = $statement->table();
		$order = $statement->orderBy();
		$limit = $statement->limit();
		$group = $statement->groupBy();
		
		$query = 'SELECT ';
		
		if ($selectColumns === '*' or $selectColumns === null)
		{
			$query .= '*';
		}
		elseif (is_string($selectColumns))
		{
			$selectColumns = preg_split("/,(?![^()]*+\\))/", $selectColumns);
		}
		if (checkArray($selectColumns))
		{
			foreach ($selectColumns as $key => $column)
			{
				$selectColumns[$key] = self::fixName($column);
			}
			$query .= join(',', $selectColumns);
		}
		$query .= ' FROM ' . self::fixName($table);
		$query .= self::whereSql($statement, 'select');
		$query .= self::groupSql($group);
		$query .= self::orderSql($order);
		$query .= self::limitSql($limit);
		
		return trim($query);
	}
	
	public static function insert(Statement $statement): string
	{
		return self::intoSql($statement, 'insert');
	}
	
	public static function replace(Statement $statement): string
	{
		return self::intoSql($statement, 'replace');
	}
	
	public static function delete(Statement $statement): string
	{
		$table = self::fixName($statement->table());
		$query = 'DELETE FROM ' . $table;
		$query .= self::whereSql($statement, 'delete');
		$query .= self::groupSql($statement->groupBy());
		$query .= self::orderSql($statement->orderBy());
		$query .= self::limitSql($statement->limit());
		
		return trim($query);
	}
	
	public static function update(Statement $mainStatement): string
	{
		$queryies = [];
		$mainStatement->each('update', function ($clause) use (&$queryies, &$mainStatement)
		{
			$query = 'UPDATE ' . self::fixName($mainStatement->table()) . ' SET ';
			foreach ($clause->set as $expressions)
			{
				/**
				 * @var Field $field
				 */
				foreach ($expressions as $field)
				{
					$query .= self::getFixedColumn($field) . self::makeOperatorValueQueryPart($field, 'update') . ', ';
				}
			}
			$query = substr($query, 0, -2);// Remove the last comma
			$where = self::whereSqlClausePart($clause->where);
			$query .= $where ? " WHERE $where" : '';
			$query .= self::groupSql($mainStatement->groupBy());
			$query .= self::orderSql($mainStatement->orderBy());
			$query .= self::limitSql($mainStatement->limit());
			
			
			$queryies[] = trim($query);
		});
		
		return join(';', $queryies);
	}
	
	//////////////////////////////helers
	
	private static function intoSql(Statement $statement, string $queryType): string
	{
		$columns = [];
		$values  = [];
		$itemKey = 0;
		$statement->each($queryType, function ($clause) use (&$values, &$columns, &$itemKey, $queryType)
		{
			$valueItems = [];
			$columns    = [];
			foreach ($clause->set as $expressions)
			{
				/**
				 * @var Field $field
				 */
				foreach ($expressions as $field)
				{
					if ($field instanceof LogicalOperator)
					{
						Poesis::error("Cannot use operator in edit/insetQuery");
					}
					$columns[]    = self::getFixedColumn($field);
					$valueItems[] = self::makeOperatorValueQueryPart($field, $queryType);
				}
			}
			$values[] = '(' . join(',', $valueItems) . ')';
			$itemKey++;
		});
		
		return trim(strtoupper($queryType) . ' INTO ' . self::fixName($statement->table()) . ' (' . join(',', $columns) . ') VALUES ' . join(', ', $values));
	}
	
	private static function makeOperatorValueQueryPart(Field $field, string $queryType): string
	{
		$op = $field->getOperator();
		if (!in_array($queryType, ['select', 'delete']))
		{
			if (!$field->isEditAllowed())
			{
				$pt = $field->getPredicateType();
				$field->alertFix("Field(%c%) can't use valueType($pt), operator($op), queryType($queryType) in edit query", ['value' => $field->__finalQueryPart[1]]);
			}
		}
		
		if ($field->isPredicateType('rawValue'))
		{
			$fixedValue = self::getValueWithSQLFunctions($field, $field->getValuePrefix() . $field->__finalQueryPart[1] . $field->getValueSuffix());
		}
		elseif ($field->isPredicateType('like'))
		{
			$fixedValue = self::makeValueQueryPart($field, 'string', $field->getValuePrefix() . $field->__finalQueryPart[1] . $field->getValueSuffix());
		}
		elseif ($field->isPredicateType('inDeCrease'))
		{
			$fixedValue = self::fixName($field->getColumn()) . ' ' . $op . ' ' . $field->__finalQueryPart[1];
		}
		elseif ($field->isPredicateType('between,in'))
		{
			$fv = $field->__finalQueryPart[1];
			array_walk($fv, function (&$item) use (&$field)
			{
				$fv   = $item[1];
				$ft   = $item[0];
				$item = self::makeValueQueryPart($field, $ft, $fv);
			});
			if ($field->isPredicateType('between'))
			{
				$fv = join(' AND ', $fv);
			}
			else
			{
				$fv = join(',', $fv);
			}
			$fixedValue = self::getValueWithSQLFunctions($field, $fv);
			$fixedValue = $field->getValuePrefix() . $fixedValue . $field->getValueSuffix();
		}
		else
		{
			$fv         = self::makeValueQueryPart($field, $field->__finalQueryPart[0], $field->__finalQueryPart[1]);
			$fixedValue = self::getValueWithSQLFunctions($field, $fv);
			$fixedValue = $field->getValuePrefix() . $fixedValue . $field->getValueSuffix();
		}
		
		if ($queryType == 'update' and !$field->isPredicateType('rawValue'))
		{
			$op = '=';
		}
		if ($queryType == 'insert' or $queryType == 'replace')
		{
			$op = '';
		}
		else
		{
			$op = $op ? ' ' . $op . ' ' : ' ';
		}
		
		return $op . $fixedValue;
	}
	
	private static function makeValueQueryPart(Field $field, string $fixType, $value): string
	{
		if ($fixType == 'column' or $field->isPredicateType('compareColumn'))
		{
			return self::fixName($value);
		}
		if ($fixType == 'expression')
		{
			return $value;
		}
		elseif ($fixType == 'string')
		{
			return "'" . ConnectionManager::get($field->getConnectionName())->escape($value) . "'";
		}
		
		return ConnectionManager::get($field->getConnectionName())->escape($value);
	}
	
	private static function fixName(string $name, array $allowedValues = []): string
	{
		$name = trim($name);
		if ($name === "*")
		{
			return $name;
		}
		elseif ($allowedValues)
		{
			foreach ($allowedValues as $allowedValue)
			{
				if ($allowedValue === '\numeric\\' and is_numeric($name))
				{
					return $name;
				}
				if ($allowedValue === $name)
				{
					return $name;
				}
			}
			
			return self::fixName($name);
		}
		elseif (preg_match('/.+ as .+/i', $name))
		{
			preg_match('/(.+) as (.+)/i', $name, $matches);
			
			return self::fixName($matches[1], ['\numeric\\', 'null', 'false', 'true', "''", '""']) . ' AS ' . self::fixName($matches[2]);
		}
		elseif (strpos($name, '(') and strpos($name, ')'))
		{
			$brackets             = Regex::getMatch('/\(.*\)/i', $name);
			$betweenBrackets      = substr($brackets, 1, -1);
			$fixedBetweenBrackets = self::fixName($betweenBrackets);
			
			return str_replace($betweenBrackets, $fixedBetweenBrackets, $name);
		}
		elseif (strpos($name, '.'))
		{
			$ex = explode('.', $name);
			
			return self::fixName($ex[0]) . '.' . self::fixName($ex[1]);
		}
		$name    = str_replace("'", '`', $name);
		$pattern = '[\p{L}_][\p{L}\p{N}@$#_]{0,127}';
		if (preg_match('/^`' . $pattern . '`$/m', $name))
		{
			return $name;
		}
		
		if (!preg_match('/^[\p{L}_][\p{L}\p{N}@$#_]{0,127}$/m', $name))
		{
			Poesis::error('unallowed characters in name', ['name' => $name, 'more info' => 'https://stackoverflow.com/questions/30151800/regular-expression-for-validating-sql-server-table-name']);
		}
		
		return '`' . $name . '`';
	}
	
	private static function makeFunctionString(array $functions, $functionValue): string
	{
		if ($functions)
		{
			$column = $functionValue;
			foreach ($functions as $item)
			{
				$function  = strtoupper($item[0]);
				$arguments = $item[1];
				if (checkArray($arguments))
				{
					array_walk($arguments, function (&$item)
					{
						if (!is_int($item))
						{
							$item = "'" . $item . "'";
						}
					});
					$arguments = ',' . join(',', $arguments);
				}
				else
				{
					$arguments = '';
				}
				$column = "$function($column$arguments)";
			}
			
			return $column;
		}
		else
		{
			return $functionValue;
		}
	}
	
	private static function getValueWithSQLFunctions(Field $field, $value): string
	{
		return trim(self::makeFunctionString($field->getValueFunctions(), $value));
	}
	
	private static function getFixedColumn(Field $field): string
	{
		return trim(self::makeFunctionString($field->getColumnFunctions(), self::fixName($field->getFinalColumn())));
	}
	
	private static function whereSql(Statement $statement, string $queryType): string
	{
		$where          = [];
		$statementCount = 0;
		$query          = '';
		$statement->each($queryType, function ($clause) use (&$where, &$statementCount)
		{
			$statementCount++;
			if ($whereItem = self::whereSqlClausePart($clause->where))
			{
				$where[] = $whereItem;
			}
		});
		if ($statementCount > 1)
		{
			$where = array_map(function ($item)
			{
				return "($item)";
			}, $where);
		}
		$query .= count($where) > 0 ? ' WHERE ' . join(' OR ', $where) : '';
		
		return $query;
	}
	
	private static function whereSqlClausePart(array $where): string
	{
		if (!checkArray($where))
		{
			return '';
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
						$queryComponents[] = $field->getValue();
					}
					else
					{
						$queryComponents[] = self::getFixedColumn($field) . self::makeOperatorValueQueryPart($field, 'select');;
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
				$nextGroupIndex = $groupIndex + 1;
				if (isset($where[$nextGroupIndex]) AND count($where[$nextGroupIndex]) == 1 and $where[$nextGroupIndex][0] instanceof LogicalOperator)
				{
					$queryComponents[] = $where[$nextGroupIndex][0]->get();
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
		
		return trim(join(' ', $queryComponents));
	}
	
	private static function orderSql($order): string
	{
		$query = '';
		if (trim($order))
		{
			$query .= ' ORDER BY ' . $order;
		}
		
		return $query;
	}
	
	private static function limitSql($limit): string
	{
		$query = "";
		if ($limit = trim($limit))
		{
			$query = ' LIMIT ' . $limit;
		}
		
		return $query;
	}
	
	private static function groupSql($group): string
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
