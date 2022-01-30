<?php

namespace Infira\Poesis\orm\statement;

use Infira\Poesis\orm\node\Clause;
use Infira\Poesis\support\RepoTrait;

class Statement
{
	use RepoTrait;
	
	private $table      = '';
	private $rowParsers = [];
	/**
	 * @var Clause[]
	 */
	private $clause;
	private $orderBy   = '';
	private $groupBy   = '';
	private $limit     = '';
	private $query     = '';
	private $queryType = '';
	private $TID       = null;//unique 32characted transactionID, if null then it's not in use
	
	public final function __construct(string $connectionName)
	{
		$this->connectionName = $connectionName;
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
	
	public function clause(Clause $query = null): ?Clause
	{
		if ($query !== null) {
			$this->clause = $query;
		}
		
		return $this->clause;
	}
	
	public function isMultiquery(): bool
	{
		return $this->clause()->hasMany();
	}
}