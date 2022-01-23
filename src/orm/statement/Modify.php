<?php

namespace Infira\Poesis\orm\statement;

use Infira\Poesis\support\QueryCompiler;

class Modify extends Statement
{
	public function modify(string $queryType): bool
	{
		$query = QueryCompiler::$queryType($this);
		$this->query($query);
		$this->queryType($queryType);
		
		
		if ($this->isMultiquery()) {
			$success = (bool)$this->Con->multiQuery($query);
		}
		else {
			$success = $this->Con->realQuery($query);
		}
		
		return $success;
	}
	
	public function hasClauses(): bool
	{
		return (bool)$this->clauses;
	}
	
	/**
	 * Get update query
	 *
	 * @return string
	 */
	public final function getUpdateQuery(): string
	{
		return QueryCompiler::update($this);
	}
	
	/**
	 * Get insert query
	 *
	 * @return string
	 */
	public final function getInsertQuery(): string
	{
		return QueryCompiler::insert($this);
	}
	
	/**
	 * Get replace query
	 *
	 * @return string
	 */
	public final function getReplaceQuery(): string
	{
		return QueryCompiler::replace($this);
	}
	
	/**
	 * Get delete query
	 *
	 * @return string
	 */
	public final function getDeleteQuery(): string
	{
		return QueryCompiler::delete($this);
	}
	
}