<?php

namespace Infira\Poesis\clause;

use Infira\Poesis\Poesis;

class Clause
{
    /**
     * @var ClauseCollectionBag $where
     */
    private $where;
    /**
     * @var ClauseCollectionBag $set
     */
    private $set;
    private $collectionIndex;
    private $increaseOnNextAdd = false;

    public function __construct()
    {
        $this->flush();
    }

    public function increaseCollectionIndex(): void
    {
        $this->increaseOnNextAdd = true;
    }

    /**
     * @param  int  $index
     * @param  ChainBag|ModelColumn[]|LogicalOperator[]|Field[]|ModelColumn|LogicalOperator|Field  $conditions
     * @return void
     */
    public function addWhere(int $index, $conditions): void
    {
        $this->collect($index, $conditions, 'where');
    }

    /**
     * @param  int  $index
     * @param  ChainBag|ModelColumn[]|LogicalOperator[]|Field[]|ModelColumn|LogicalOperator|Field  $conditions
     * @return void
     */
    public function addSet(int $index, $conditions): void
    {
        $this->collect($index, $conditions, 'set');
    }

    /**
     * @param  int  $index
     * @param  ChainBag|array<array-key,ModelColumn|LogicalOperator|Field>  $conditions
     * @return void
     */
    private function collect(int $index, $conditions, string $clause): void
    {
        if ($conditions instanceof ChainBag) {
            $conditions = $conditions->getConditions();
        }

        if (!is_array($conditions)) {
            Poesis::error('Must be array of conditions');
        }

        if ($this->increaseOnNextAdd === true) {
            $this->increaseOnNextAdd = false;
            $this->collectionIndex++;
        }
        ($clause === 'where' ? $this->where : $this->set)
            ->collect($this->collectionIndex)
            ->chain($index)
            ->addCondition(...array_values($conditions));
    }

    public function getSelectBag(): ClauseCollectionBag
    {
        if (!$this->where->hasAny() && $this->set->hasAny()) {
            return $this->set;
        }

        return $this->where;
    }

    /**
     * run through each collection
     *
     * @param  (callable(ClauseCollection): bool)  $cb
     * @return void
     */
    public function each(callable $cb): void
    {
        for ($i = 0; $i <= $this->collectionIndex; $i++) {
            $cb($this->at($i));
        }
    }

    public function at(int $index = 0): ClauseCollection
    {
        return new ClauseCollection($this->where->at($index), $this->set->at($index));
    }

    /**
     * @param  ChainBag[]  ...$chains
     * @return $this
     */
    public function addSetFromArray(array ...$chains): Clause
    {
        foreach (array_values($chains) as $k => $conditions) {
            if ($k > 0) {
                $this->increaseCollectionIndex();
            }
            foreach (array_values($conditions) as $i => $v) {
                $this->addSet($i, $v->getConditions());
            }
        }

        return $this;
    }

    /**
     * @param  ChainBag[]  ...$chains
     * @return $this
     */
    public function addWhereFromArray(array ...$chains): Clause
    {
        foreach (array_values($chains) as $k => $conditions) {
            if ($k > 0) {
                $this->increaseCollectionIndex();
            }
            foreach (array_values($conditions) as $i => $v) {
                $this->addWhere($i, $v->getConditions());
            }
        }

        return $this;
    }

    /**
     * @return ModelColumn[]
     */
    public function getColumns(): array
    {
        return $this->set->getColumns();
    }

    /**
     * @param  string|ModelColumn  $column
     * @return bool
     */
    public function hasColumn($column): bool
    {
        return $this->set->hasColumn($column);
    }

    /**
     * get column setted value
     *
     * @param  string  $column
     * @return mixed
     * @throws \Infira\Poesis\Error
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
        return $this->where->getColumns();
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
     * @param  string  $column
     * @return mixed
     * @throws \Infira\Poesis\Error
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
        $this->where = new ClauseCollectionBag('where');
        $this->set = new ClauseCollectionBag('set');
        $this->collectionIndex = 0;
    }
}
