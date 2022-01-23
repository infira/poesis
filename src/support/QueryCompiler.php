<?php

namespace Infira\Poesis\support;

use Infira\Poesis\orm\node\Field;
use Infira\Poesis\orm\node\LogicalOperator;
use Infira\Poesis\orm\statement\Statement;
use Infira\Poesis\orm\ModelColumn;
use Infira\Poesis\orm\node\{Clause};

class QueryCompiler
{
	const RAW_QUERY = "__raw_sql_query"; //TODO MIKS SEDA VAJA ON
	
	public static function select(Statement $statement, $selectColumns): string
	{
		$table = $statement->table();
		$order = $statement->orderBy();
		$limit = $statement->limit();
		$group = $statement->groupBy();
		
		$query = 'SELECT ';
		
		if ($selectColumns === '*' or $selectColumns === null) {
			$query .= '*';
		}
		elseif (is_string($selectColumns)) {
			$selectColumns = preg_split("/,(?![^()]*+\\))/", $selectColumns);
		}
		if (is_array($selectColumns)) {
			foreach ($selectColumns as $key => $column) {
				$selectColumns[$key] = self::fixName($column);
			}
			$query .= join(',', $selectColumns);
		}
		$query .= ' FROM ' . self::fixName($table);
		$query .= self::whereSql($statement->getSelectClauses());
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
		$query .= self::whereSql($statement->getSelectClauses());
		$query .= self::groupSql($statement->groupBy());
		$query .= self::orderSql($statement->orderBy());
		$query .= self::limitSql($statement->limit());
		
		return trim($query);
	}
	
	public static function update(Statement $mainStatement): string
	{
		$queryies = [];
		foreach ($mainStatement->getClauseCollections() as $clause) {
			$query = 'UPDATE ' . self::fixName($mainStatement->table()) . ' SET ';
			foreach ($clause->set->filterExpressions() as $field) {
				$part  = self::makeFieldPart($field, 'update');
				$query .= $part->field . $part->value . ', ';
			}
			$query = substr($query, 0, -2);// Remove the last comma
			
			$query .= self::whereSql([$clause->where]);
			$query .= self::groupSql($mainStatement->groupBy());
			$query .= self::orderSql($mainStatement->orderBy());
			$query .= self::limitSql($mainStatement->limit());
			
			$queryies[] = trim($query);
		}
		
		return join(';', $queryies);
	}
	
	//////////////////////////////helers
	
	private static function intoSql(Statement $statement, string $queryType): string
	{
		$columns = [];
		$values  = [];
		$itemKey = 0;
		foreach ($statement->getInsertClauses() as $clause) {
			$valueItems = [];
			foreach ($clause->filterExpressions() as $field) {
				if ($field instanceof LogicalOperator) {
					Poesis::error("Cannot use operator in edit/insetQuery");
				}
				$part         = self::makeFieldPart($field, $queryType);
				$columns[]    = $part->field;
				$valueItems[] = $part->value;
			}
			$values[] = '(' . join(',', $valueItems) . ')';
			$itemKey++;
		}
		
		return trim(strtoupper($queryType) . ' INTO ' . self::fixName($statement->table()) . ' (' . join(',', array_unique($columns)) . ') VALUES ' . join(', ', $values));
	}
	
	private static function makeFieldPart(Field $field, string $queryType): \stdClass
	{
		$fixedValue = trim($field->getFixedValue());
		
		if (!in_array($queryType, ['select', 'delete']) and !$field->isEditAllowed()) {
			$pt         = $field->getPredicateType();
			$comparison = $field->getComparison();
			$field->alertFix("Field(%c%) can't use valueType($pt), comparison($comparison), queryType($queryType) in edit query", ['value' => $fixedValue]);
		}
		if ($field->getValueFunctions()) {
			$fixedValue = trim(self::makeFunctionString($field->getValueFunctions(), $fixedValue));
		}
		
		$output        = new \stdClass();
		$output->field = $field->getFinalColumn();
		$comparison    = $field->getComparison();
		if ($comparison == '' and $field->isPredicateType('rawValue') and $output->field) {
			$comparison = ' = ';
			if (in_array($queryType, ['insert', 'replace'])) {
				$comparison = '';
			}
			elseif ($queryType == 'select') {
				$comparison = ' ';
			}
		}
		else {
			if ($queryType == 'update' and !$field->isPredicateType('rawValue')) {
				$comparison = '=';
			}
			elseif ($queryType == 'insert' or $queryType == 'replace') {
				$comparison = '';
			}
			$comparison = $comparison ? ' ' . $comparison . ' ' : '';
		}
		
		if ($field->isPredicateType('rawValue') and !$output->field) {
			$output->field = '';
		}
		else {
			$output->field = trim(self::makeFunctionString($field->getColumnFunctions(), self::fixColumnName($output->field)));
		}
		$output->value = $comparison . $fixedValue;
		
		return $output;
	}
	
	public static function fixColumnName(string $column, array $allowedValues = []): string
	{
		return self::fixName($column);
	}
	
	private static function fixName(string $name, array $allowedValues = []): string
	{
		$name = trim($name);
		if ($name === "*") {
			return $name;
		}
		elseif ($allowedValues) {
			foreach ($allowedValues as $allowedValue) {
				if ($allowedValue === '\numeric\\' and is_numeric($name)) {
					return $name;
				}
				if ($allowedValue === $name) {
					return $name;
				}
			}
			
			return self::fixName($name);
		}
		elseif (preg_match('/.+ as .+/i', $name)) {
			preg_match('/(.+) as (.+)/i', $name, $matches);
			
			return self::fixName($matches[1], ['\numeric\\', 'null', 'false', 'true', "''", '""']) . ' AS ' . self::fixName($matches[2]);
		}
		//quickfix, TODO: later will upgrade whole compiler to https://latitude.shadowhand.com/
		elseif (preg_match('/DATE_FORMAT.*\((.+?),(.+?)\)/m', $name, $matches)) {
			return preg_replace('/DATE_FORMAT.*\((.+?),(.+?)\)/m', 'DATE_FORMAT(' . $matches[1] . ',$2)', $name);
		}
		elseif (strpos($name, '(') and strpos($name, ')')) {
			preg_match('/\(.*\)/i', $name, $matches);
			$brackets             = $matches[0];
			$betweenBrackets      = substr($brackets, 1, -1);
			$fixedBetweenBrackets = self::fixName($betweenBrackets);
			
			return str_replace($betweenBrackets, $fixedBetweenBrackets, $name);
		}
		elseif (strpos($name, '.')) {
			$ex = explode('.', $name);
			
			return self::fixName($ex[0]) . '.' . self::fixName($ex[1]);
		}
		$name    = str_replace("'", '`', $name);
		$pattern = '[\p{L}_][\p{L}\p{N}@$#_]{0,127}';
		if (preg_match('/^`' . $pattern . '`$/m', $name)) {
			return $name;
		}
		
		if (!preg_match('/^[\p{L}_][\p{L}\p{N}@$#_]{0,127}$/m', $name)) {
			Poesis::error('unallowed characters in name', ['name' => $name, 'more info' => 'https://stackoverflow.com/questions/30151800/regular-expression-for-validating-sql-server-table-name']);
		}
		
		return '`' . $name . '`';
	}
	
	private static function makeFunctionString(array $functions, $functionValue): string
	{
		if ($functions) {
			$column = $functionValue;
			foreach (array_reverse($functions) as $item) {
				$function  = strtoupper($item[0]);
				$arguments = $item[1];
				if (is_array($arguments) and $arguments) {
					array_walk($arguments, function (&$item)
					{
						if (!is_int($item)) {
							$item = "'" . $item . "'";
						}
					});
					$arguments = ',' . join(',', $arguments);
				}
				else {
					$arguments = '';
				}
				$column = "$function($column$arguments)";
			}
			
			return $column;
		}
		else {
			return $functionValue;
		}
	}
	
	/**
	 * @param Clause[] $clauses
	 * @return string
	 */
	private static function whereSql(array $clauses): string
	{
		$whereParts = [];
		foreach ($clauses as $clause) {
			if ($part = self::whereSqlParts($clause)) {
				$whereParts[] = $part;
			}
		}
		if (!$whereParts) {
			return '';
		}
		if (count($whereParts) > 1) {
			array_walk($whereParts, function (&$item)
			{
				$item = "($item)";
			});
		}
		
		return ' WHERE ' . join(' OR ', $whereParts);
	}
	
	private static function whereSqlParts(Clause $clause): string
	{
		if (!$clause->hasAny()) {
			return '';
		}
		$compiledClauses = [];
		$groups          = $clause->getGroups();
		$lastGroupIndex  = array_key_last($groups);
		$lastGroupType   = null;
		foreach ($groups as $groupIndex => $group) {
			$groupHasMany       = false;
			$compiledGroupItems = [];
			$lastGroupItemsType = null;
			foreach ($group->getItems() as $itemKey => $item) {
				if ($itemKey == 0 and $item instanceof LogicalOperator) {
					$compiledClauses[] = $item->get();
					$lastGroupType     = 'op';
					continue;
				}
				
				if ($lastGroupItemsType and $lastGroupItemsType !== 'op') {
					$compiledGroupItems[] = ($item instanceof LogicalOperator) ? $item->get() : 'AND';
					$lastGroupItemsType   = 'op';
				}
				if ($item instanceof ModelColumn) {
					$expressions         = $item->getExpressions();
					$ci                  = count($expressions);
					$lastExpressionIndex = array_key_last($expressions);
					$expressionOpAdded   = false;
					$lastExpressionType  = null;
					foreach ($expressions as $expressionIndex => $expression) {
						if ($lastExpressionType and $lastExpressionType !== 'op') {
							$compiledGroupItems[] = ($expression instanceof LogicalOperator) ? $expression->get() : 'AND';
							$lastExpressionType   = 'op';
						}
						if ($expression instanceof Field) {
							$part                 = self::makeFieldPart($expression, 'select');
							$compiledGroupItems[] = $part->field . $part->value;
							$lastExpressionType   = 'expression';
						}
					}
					$lastGroupItemsType = 'expression';
				}
				elseif ($item instanceof Field) {
					$part                 = self::makeFieldPart($item, 'select');
					$compiledGroupItems[] = $part->field . $part->value;
					$lastExpressionType   = 'expression';
				}
			}
			if ($lastGroupType !== 'op' and $groupIndex > 0) {
				$compiledClauses[] = 'AND';
				$lastGroupType     = 'op';
			}
			
			if (count($compiledGroupItems) > 1) {
				$compiledClauses[] = '( ' . join(' ', $compiledGroupItems) . ' )';
				$lastGroupType     = 'items';
			}
			else {
				$compiledClauses = array_merge_recursive($compiledClauses, $compiledGroupItems);
				$lastGroupType   = 'items';
			}
		}
		if (!$compiledClauses) {
			return '';
		}
		
		return join(' ', $compiledClauses);
	}
	
	private static function orderSql($order): string
	{
		$query = '';
		if (trim($order)) {
			$query .= ' ORDER BY ' . $order;
		}
		
		return $query;
	}
	
	private static function limitSql($limit): string
	{
		$query = "";
		if ($limit = trim($limit)) {
			$query = ' LIMIT ' . $limit;
		}
		
		return $query;
	}
	
	private static function groupSql($group): string
	{
		$query = "";
		if ($group = trim($group)) {
			$query = ' GROUP BY ' . $group;
		}
		
		return $query;
	}
}