<?php

namespace Infira\Poesis\clause;

use Infira\Poesis\Poesis;

class Clause
{
	/**
	 * @var ClauseBag $where
	 */
	private $where;
	/**
	 * @var ClauseBag $set
	 */
	private $set;
	private $collectionIndex;
	private $increaseOnNextAdd = false;
	
	public function __construct()
	{
		$this->flush();
	}
	
	public function increaseCollectionIndex()
	{
		//$this->collectionIndex++;
		$this->increaseOnNextAdd = true;
	}
	
	public function addWhre(int $index, ...$item)
	{
		if ($this->increaseOnNextAdd === true) {
			$this->increaseOnNextAdd = false;
			$this->collectionIndex++;
		}
		$this->where->bag($this->collectionIndex, 'collection-' . $this->collectionIndex)->bag($index, 'chain')->add(...$item);
	}
	
	public function addSet(int $index, ...$item)
	{
		if ($this->increaseOnNextAdd === true) {
			$this->increaseOnNextAdd = false;
			$this->collectionIndex++;
		}
		$this->set->bag($this->collectionIndex, 'collection-' . $this->collectionIndex)->bag($index, 'chain')->add(...$item);
	}
	
	public function getSelectBag(): ClauseBag
	{
		if (!$this->where->hasAny() and $this->set->hasAny()) {
			return $this->set;
		}
		
		return $this->where;
	}
	
	/**
	 * run throufg each collection
	 *
	 * @return void
	 */
	public function each(callable $cb)
	{
		for ($i = 0; $i <= $this->collectionIndex; $i++) {
			$cb($this->at($i));
		}
	}
	
	public function at(int $index = 0): CollectionBag
	{
		$col        = new CollectionBag();
		$col->set   = $this->set->at($index, new ClauseBag('empty'));
		$col->where = $this->where->at($index, new ClauseBag('empty'));
		
		return $col;
	}
	
	/**
	 * @param array $arr
	 * @return $this
	 */
	public function addSetFromArray(array $arr)
	{
		foreach (array_values($arr) as $i => $v) {
			$this->addSet($i, ...$v->getItems());
		}
		
		return $this;
	}
	
	/**
	 * @param array $arr
	 * @return $this
	 */
	public function addWhereFromArray(array $arr)
	{
		foreach (array_values($arr) as $i => $v) {
			$this->addWhre($i, ...$v->getItems());
		}
		
		return $this;
	}
	
	/**
	 * @return ModelColumn[]
	 */
	public function getColumns(): array
	{
		$output = [];
		foreach ($this->set->getItems() as $collectionBag) {
			foreach ($collectionBag->getItems() as $chainBag) {
				$output = array_merge($output, $chainBag->getItems());
			}
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
		Poesis::error("column('$column') does not exist");
	}
	
	/**
	 * @return ModelColumn[]
	 */
	public function getWhereColumns(): array
	{
		$output = [];
		foreach ($this->where->getItems() as $collectionBag) {
			foreach ($collectionBag->getItems() as $chainBag) {
				$output = array_merge($output, $chainBag->getItems());
			}
		}
		
		return $output;
	}
	
	public function hasWhereColumn(string $column): bool
	{
		foreach ($this->getWhereColumns() as $modelColumn) {
			if ($modelColumn->getColumn() === $column) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * get where column setted value
	 *
	 * @param string $column
	 * @throws \Infira\Poesis\Error
	 * @return mixed
	 */
	public function getWhereValue(string $column)
	{
		foreach ($this->getWhereColumns() as $modelColumn) {
			if ($modelColumn->getColumn() === $column) {
				return $modelColumn->first()->getValue();
			}
		}
		Poesis::error('column das not exist');
	}
	
	public function hasOne(): bool
	{
		$item = $this->at();
		if (!$item->set->hasAny()) {
			return false;
		}
		
		return $item->set->count() == 1;
	}
	
	public function hasAny(): bool
	{
		$item = $this->at();
		if (!$item->set->hasAny()) {
			return false;
		}
		
		return $item->set->hasAny();
	}
	
	public function hasMany(): bool
	{
		return $this->collectionIndex > 0;
	}
	
	public function flush()
	{
		$this->where           = new ClauseBag('where');
		$this->set             = new ClauseBag('set');
		$this->collectionIndex = 0;
	}
}
