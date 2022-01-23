<?php

namespace Infira\Poesis\orm\statement;

use Infira\Poesis\orm\node\Clause;
use Infira\Poesis\Connection;
use Infira\Poesis\orm\node\ClauseCollection;

class Statement
{
	private $table      = '';
	private $model      = '';
	private $rowParsers = [];
	/**
	 * @var ClauseCollection[]
	 */
	protected $clauses   = [];
	private   $orderBy   = '';
	private   $groupBy   = '';
	private   $limit     = '';
	private   $query     = '';
	private   $queryType = '';
	private   $TID       = null;//unique 32characted transactionID, if null then it's not in use
	/**
	 * @var Connection
	 */
	protected $Con;
	
	public final function __construct(Connection &$Con)
	{
		$this->Con = &$Con;
	}
	
	public function TID(string $tid = null): ?string
	{
		if ($tid !== null) {
			$this->TID = $tid;
		}
		
		return $this->TID;
	}
	
	public function table(string $table = null): ?string
	{
		if ($table !== null) {
			$this->table = $table;
		}
		
		return $this->table;
	}
	
	public function model(string $model = null): ?string
	{
		if ($model !== null) {
			$this->model = $model;
		}
		
		return $this->model;
	}
	
	public function orderBy(string $orderBy = null): ?string
	{
		if ($orderBy !== null) {
			$this->orderBy = $orderBy;
		}
		
		return $this->orderBy;
	}
	
	public function groupBy(string $groupBy = null): ?string
	{
		if ($groupBy !== null) {
			$this->groupBy = $groupBy;
		}
		
		return $this->groupBy;
	}
	
	public function limit(string $limit = null): ?string
	{
		if ($limit !== null) {
			$this->limit = $limit;
		}
		
		return $this->limit;
	}
	
	public function queryType(string $queryType = null): ?string
	{
		if ($queryType !== null) {
			$this->queryType = $queryType;
		}
		
		return $this->queryType;
	}
	
	public function query(string $query = null): ?string
	{
		if ($query !== null) {
			$this->query = $query;
		}
		
		return $this->query;
	}
	
	/**
	 * @param callable[]|null $callables
	 * @return callable[]
	 */
	public final function rowParsers(array $callables = null): array
	{
		if ($callables !== null) {
			$this->rowParsers = $callables;
		}
		
		return $this->rowParsers;
	}
	
	public function addClauses(Clause $where, Clause $set)
	{
		$this->addCollection(new ClauseCollection($where, $set));
	}
	
	public function addCollection(ClauseCollection $collection)
	{
		$this->clauses[] = $collection;
	}
	
	public function getClauseCollections(): array
	{
		return $this->clauses;
	}
	
	/**
	 * @return Clause[]
	 */
	public function getInsertClauses(): array
	{
		$output = [];
		foreach ($this->clauses as $k => $clause) {
			$output[] = $clause->set;
		}
		
		return $output;
	}
	
	/**
	 * @return Clause[]
	 */
	public function getSelectClauses(): array
	{
		$output = [];
		foreach ($this->clauses as $clause) {
			if (!$clause->where->hasAny() and $clause->set->hasAny()) {
				$output[] = $clause->set;
			}
			else {
				$output[] = $clause->where;
			}
			
		}
		
		return $output;
	}
	
	public function isMultiquery(): bool
	{
		return count($this->clauses) > 1;
	}
}