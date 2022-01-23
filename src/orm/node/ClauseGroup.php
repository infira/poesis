<?php

namespace Infira\Poesis\orm\node;

class ClauseGroup
{
	private $items = [];
	
	public function add(...$item)
	{
		foreach ($item as $i)
		{
			$this->items[] = $i;
		}
	}
	
	public function count(): int
	{
		return count($this->items);
	}
	
	public function hasMany(): bool
	{
		return $this->count() > 1;
	}
	
	public function getItems(): array
	{
		return $this->items;
	}
	
	public function at(int $key)
	{
		return $this->items[$key];
	}
}