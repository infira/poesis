<?php

namespace Infira\Poesis\clause;

abstract class Bag
{
    protected $name;
    protected $items = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function __debugInfo()
    {
        return [
            'name' => $this->name,
            'items' => $this->items,
        ];
    }

    public function flush()
    {
        $this->items = [];
    }

    protected function addItems(...$item)
    {
        foreach ($item as $i) {
            $this->items[] = $i;
        }
    }

    /**
     * @return array
     * @deprecated
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function hasMany(): bool
    {
        return $this->count() > 1;
    }

    public function hasAny(): bool
    {
        return $this->count() > 0;
    }


    public function exists(int $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function lastKey(): int
    {
        return array_key_last($this->items);
    }

    protected function getAt(int $key, $default = null)
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * @param  string|ModelColumn  $column
     * @return bool
     */
    public function hasColumn($column): bool
    {
        if ($column instanceof ModelColumn) {
            $column = $column->getColumn();
        }
        foreach ($this->getColumns() as $modelColumn) {
            if ($modelColumn->getColumn() === $column) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string[]|ModelColumn[]|Bag  $needle
     * @return bool
     */
    public function hasOneOfColumn($needle): bool
    {
        if ($needle instanceof self) {
            $needle = $needle->getColumns();
        }
        foreach ($needle as $col) {
            if ($this->hasColumn($col)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return ModelColumn[]
     */
    abstract public function getColumns(): array;
}