<?php

namespace Infira\Poesis\orm\node;

use Infira\Poesis\Poesis;

class Statement
{
	private $table     = '';
	private $model     = '';
	private $clauses   = [];
	private $orderBy   = '';
	private $groupBy   = '';
	private $limit     = '';
	private $query     = '';
	private $queryType = '';
	private $TID       = null;//unique 32characted transactionID, if null then it's not in use
	
	public function TID(string $tid = null): ?string
	{
		if ($tid !== null)
		{
			$this->TID = $tid;
		}
		
		return $this->TID;
	}
	
	public function table(string $table = null): ?string
	{
		if ($table !== null)
		{
			$this->table = $table;
		}
		
		return $this->table;
	}
	
	public function model(string $model = null): ?string
	{
		if ($model !== null)
		{
			$this->model = $model;
		}
		
		return $this->model;
	}
	
	public function orderBy(string $orderBy = null): ?string
	{
		if ($orderBy !== null)
		{
			$this->orderBy = $orderBy;
		}
		
		return $this->orderBy;
	}
	
	public function groupBy(string $groupBy = null): ?string
	{
		if ($groupBy !== null)
		{
			$this->groupBy = $groupBy;
		}
		
		return $this->groupBy;
	}
	
	public function limit(string $limit = null): ?string
	{
		if ($limit !== null)
		{
			$this->limit = $limit;
		}
		
		return $this->limit;
	}
	
	public function query(string $query = null): ?string
	{
		if ($query !== null)
		{
			$this->query = $query;
		}
		
		return $this->query;
	}
	
	public function queryType(string $query = null): ?string
	{
		if ($query !== null)
		{
			$this->queryType = $query;
		}
		
		return $this->queryType;
	}
	
	public function getClauses(): array
	{
		return $this->clauses;
	}
	
	public function collect(array $whereClause = null, array $clause = null, array $columns = null)
	{
		$this->clauses[] = (object)['where' => $whereClause, 'set' => $clause, 'columns' => $columns];
	}
	
	public function replace(array $whereClause = null, array $clause = null, array $columns = null)
	{
		$this->clauses = [(object)['where' => $whereClause, 'set' => $clause, 'columns' => $columns]];
	}
	
	public function each(string $queryType, callable $callable)
	{
		$lastColumns = null;
		$lastIndex   = array_key_last($this->clauses);
		foreach ($this->clauses as $index => $clause)
		{
			$clause->isLast = $index == $lastIndex;
			if (in_array($queryType, ['select', 'delete']) and !$clause->where and $clause->set)
			{
				$clause->where = $clause->set;
			}
			
			if ($queryType == 'update')
			{
				if (!$clause->where and !$clause->set)
				{
					Poesis::error('Stament has no clauses setted', ['clause' => $clause]);
				}
				if ($clause->where and !$clause->set)
				{
					Poesis::error('Update state does not have any columns setted', ['clause' => $clause]);
				}
			}
			elseif (in_array($queryType, ['insert', 'replace']) and $clause->where)
			{
				Poesis::error('Where cannot have values on insert/replace query', ['clause' => $clause]);
			}
			elseif (!in_array($queryType, ['select', 'selectModifed', 'delete']))
			{
				if ($lastColumns !== null and $lastColumns != $clause->columns)
				{
					Poesis::addExtraErrorInfo('last columns', $lastColumns);
					Poesis::addExtraErrorInfo('current columns', $clause->columns);
					Poesis::error('collection must be in same order and values as last one');
				}
				$lastColumns = $clause->columns;
				$addedFields = [];
				foreach ($clause->set as $expressions)
				{
					/**
					 * @var Field $field
					 */
					foreach ($expressions as $field)
					{
						if ($field->isOperator())
						{
							Poesis::error('Cant have operator in edit query');
						}
						$field = $field->getFinalColumn();
						if (isset($addedFields[$field]))
						{
							Poesis::error("$field specified twice", ['clauses' => $clause->set]);
						}
						$addedFields[$field] = true;
					}
				}
			}
			$callable($clause);
		}
	}
	
	public function isMultiquery(): bool
	{
		return count($this->clauses) > 1;
	}
}

?>