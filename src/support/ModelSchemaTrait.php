<?php

namespace Infira\Poesis\support;

use Infira\Poesis\Poesis;

/**
 * @uses  \Infira\Poesis\orm\Model
 * @uses  \Infira\Poesis\support\RepoTrait
 */
trait ModelSchemaTrait
{
	private function schemaIndex(string $column): string
	{
		return "$this->table.$column";
	}
	
	/**
	 * Alerts when $column does not exist
	 *
	 * @param string $column
	 * @return bool
	 */
	private function validateColumn(string $column): bool
	{
		if ($this->TIDColumn and $column == $this->TIDColumn and Poesis::isTIDEnabled()) { //TODO do really need to check TID
			return true;
		}
		if (!$this->columnExists($column)) {
			Poesis::error('Db column <strong>"' . $this->table . '.' . $column . '</strong>" does not exists');
		}
		
		return true;
	}
	
	final public function hasColumn(string $column): bool
	{
		return $this->dbSchema()->exists($this->schemaIndex($column));
	}
	
	/**
	 * Does structure has auto increment column
	 *
	 * @return bool
	 */
	final public function hasAIColumn(): bool
	{
		return !empty($this->aiColumn);
	}
	
	/**
	 * Get auto increment column name
	 *
	 * @return null|string
	 */
	final public function getAIColumn(): ?string
	{
		return $this->aiColumn;
	}
	
	/**
	 * Has transaction ID column
	 *
	 * @return bool
	 */
	final public function hasTIDColumn(): bool
	{
		return $this->TIDColumn !== null;
	}
	
	/**
	 * Has transaction ID column
	 *
	 * @return bool
	 */
	final public function isTIDEnabled(): bool
	{
		return Poesis::isTIDEnabled() and $this->TIDColumn !== null;
	}
	
	/**
	 * Get TID column name
	 *
	 * @return string
	 */
	final public function getTIDColumn(): ?string
	{
		return $this->TIDColumn;
	}
	
	/**
	 * Check is $column a primary column
	 *
	 * @param string $column
	 * @return bool
	 */
	final public function isPrimaryColumn(string $column): bool
	{
		return in_array($column, $this->primaryColumns);
	}
	
	final public function hasPrimaryColumns(): bool
	{
		return (count($this->primaryColumns) > 0);
	}
	
	/**
	 * Get table primary column names
	 *
	 * @return array
	 */
	final public function getPrimaryColumns(): array
	{
		return $this->primaryColumns;
	}
	
	
}
