<?php

namespace Infira\Poesis\orm\node;

use Infira\Poesis\orm\Schema;
use Infira\Poesis\orm\ModelColumn;
use Infira\Poesis\Poesis;

class Clause
{
	/**
	 * @var ClauseGroup[]
	 */
	private $groupItems = [];
	
	/**
	 * @var Schema
	 */
	public  $Schema;
	private $connectionName;
	
	/**
	 * Clause constructor.
	 *
	 * @param string $schemaClassName
	 * @param string $connectionName - name for ConnectionManager instance
	 */
	public function __construct(string $schemaClassName, string $connectionName)
	{
		$this->Schema         = $schemaClassName;
		$this->connectionName = $connectionName;
	}
	
	public function makeGroup(): int
	{
		$class              = new ClauseGroup();
		$this->groupItems[] = &$class;
		
		return array_key_last($this->groupItems);
	}
	
	public function &at(int $key): ClauseGroup
	{
		return $this->groupItems[$key];
	}
	
	/**
	 * Get all columns setted values
	 *
	 * @return ClauseGroup[]
	 */
	public function getGroups(): array
	{
		return $this->groupItems;
	}
	
	public function setGroups(array $groups): self
	{
		$this->groupItems = array_values($groups);
		
		return $this;
	}
	
	/**
	 * @return ModelColumn[]
	 */
	public function getColumns(): array
	{
		$output = [];
		foreach ($this->groupItems as $group) {
			$output = array_merge($output, $group->getItems());
		}
		
		return $output;
	}
	
	public function hasColumn(string $column): bool
	{
		foreach ($this->getColumns() as $modelColumn) {
			if ($modelColumn->getColumn() === $column) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * get column setted value
	 *
	 * @param string $column
	 * @throws \Infira\Poesis\Error
	 * @return mixed
	 */
	public function getValue(string $column)
	{
		foreach ($this->getColumns() as $modelColumn) {
			if ($modelColumn->getColumn() === $column) {
				return $modelColumn->first()->getValue();
			}
		}
		Poesis::error('column das not exist');
	}
	
	/**
	 * @return Field[]
	 */
	public function filterExpressions(): array
	{
		$output = [];
		foreach ($this->groupItems as $group) {
			foreach ($group->getItems() as $item) {
				foreach ($item->getExpressions() as $field) {
					$output[] = $field;
				}
			}
		}
		
		return $output;
	}
	
	public function hasAny(): bool
	{
		return (bool)count($this->groupItems);
	}
	
	public function flush()
	{
		$this->groupItems = [];
	}
}