<?php

namespace Infira\Poesis\orm\node;

class Statement
{
	private $table          = '';
	private $model          = '';
	private $columns        = '';
	private $clauses        = []; //array of Clause items
	private $whereClauses   = []; //array of Clause items
	private $orderBy        = '';
	private $groupBy        = '';
	private $limit          = '';
	private $isCollection   = false;
	private $collectionData = [];
	private $query          = '';
	private $rowParsers     = [];
	private $TID            = null;//unique 32characted transactionID, if null then its not in use
	
	public function __construct(?string $TID) {
		$this->TID = $TID;
	}
	
	public function TID(string $tid = null): ?string
	{
		if ($tid !== null)
		{
			$this->TID = $tid;
		}
		
		return $this->TID;
	}
	
	public function whereClauses(array $items = null): ?array
	{
		if ($items === null)
		{
			return $this->whereClauses;
		}
		$this->whereClauses = $items;
		
		return null;
	}
	
	public function clauses(array $items = null): ?array
	{
		if ($items === null)
		{
			return $this->clauses;
		}
		$this->clauses = $items;
		
		return null;
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
	
	public function columns($columns = null)
	{
		if ($columns !== null)
		{
			$this->columns = $columns;
		}
		
		return $this->columns;
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
	
	public function rowParsers(array $callables = null): array
	{
		if ($callables !== null)
		{
			$this->rowParsers = $callables;
		}
		
		return $this->rowParsers;
	}
	
	public function setToCollection(array $data)
	{
		$this->isCollection   = true;
		$this->collectionData = $data;
	}
	
	public function isCollection(): bool
	{
		return $this->isCollection;
	}
	
	public function getCollectionData(): array
	{
		return $this->collectionData;
	}
}

?>