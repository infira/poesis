<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\Poesis;

/**
 * @uses  \Infira\Poesis\orm\Model
 * @uses  \Infira\Poesis\support\RepoTrait
 */
trait ModelSchema
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
	private function checkColumn(string $column): bool
	{
		if (!$this->dbSchema()->exists($index = $this->schemaIndex($column)) and ($this->TIDColumn and $column != 'TID' and Poesis::isTIDEnabled())) {//TODO do really need to check TID
			Poesis::error('Db column <strong>"' . $this->table . '.' . $column . '</strong>" does not exists');
		}
		
		return true;
	}
	
	/**
	 * Does structure has auto increment column
	 *
	 * @return bool
	 */
	protected function hasAIColumn(): bool
	{
		return !empty($this->aiColumn);
	}
	
	/**
	 * Get auto increment column name
	 *
	 * @return null|string
	 */
	protected function getAIColumn(): ?string
	{
		return $this->aiColumn;
	}
	
	/**
	 * Has transaction ID column
	 *
	 * @return bool
	 */
	protected function hasTIDColumn(): bool
	{
		return $this->TIDColumn !== null;
	}
	
	/**
	 * Has transaction ID column
	 *
	 * @return bool
	 */
	protected function isTIDEnabled(): bool
	{
		return Poesis::isTIDEnabled() and $this->TIDColumn !== null;
	}
	
	/**
	 * Get TID column name
	 *
	 * @return string
	 */
	protected function getTIDColumn(): ?string
	{
		return $this->TIDColumn;
	}
	
	/**
	 * Check is $column a primary column
	 *
	 * @param string $column
	 * @return bool
	 */
	public function isPrimaryColumn(string $column): bool
	{
		return in_array($column, $this->primaryColumns);
	}
	
	public function hasPrimaryColumns(): bool
	{
		return (count($this->primaryColumns) > 0);
	}
	
	/**
	 * Get table primary column names
	 *
	 * @return array
	 */
	public function getPrimaryColumns(): array
	{
		return $this->primaryColumns;
	}
	
	
}
