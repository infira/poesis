<?php

namespace Infira\Poesis\clause;

class ClauseBag
{
    private $name;
    private $items = [];
    public $position = 0;

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

    public function add(...$item)
    {
        foreach ($item as $i) {
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

    public function hasAny(): bool
    {
        return $this->count() > 0;
    }

    public function getItems(): array
    {
        return array_values($this->items);
    }

    public function exists(int $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function lastKey(): int
    {
        return array_key_last($this->items);
    }

    public function at(int $key, $default = null)
    {
        return $this->items[$key] ?? $default;
    }

    public function filterExpressions(): array
    {
        $output = [];
        foreach ($this->items as $chain) {
            foreach ($chain->getItems() as $item) {
                foreach ($item->getExpressions() as $field) {
                    $output[] = $field;
                }
            }
        }

        return $output;
    }

    public function bag(int $key, string $bagName)
    {
        if (!$this->exists($key)) {
            $this->items[$key] = new ClauseBag($bagName);
        }

        return $this->items[$key];
    }
}