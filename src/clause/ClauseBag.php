<?php

namespace Infira\Poesis\clause;

class ClauseBag extends Bag
{
    public function filterExpressions(): array
    {
        $output = [];
        foreach ($this->getChains() as $chain) {
            foreach ($chain->getConditions() as $item) {
                if ($item instanceof LogicalOperator) {
                    continue;
                }
                if ($item instanceof Field) {
                    $output[] = $item;
                    continue;
                }
                foreach ($item->getExpressions() as $field) {
                    $output[] = $field;
                }
            }
        }

        return $output;
    }

    /**
     * @return ModelColumn[]
     */
    public function getColumns(): array
    {
        $columns = [];
        foreach ($this->getChains() as $chain) {
            array_push($columns, ...$chain->getColumns());
        }

        return $columns;
    }

    /**
     * @return ChainBag[]
     */
    public function getChains(): array
    {
        return array_values($this->items);
    }

    public function chain(int $key = 0): ChainBag
    {
        if (!$this->exists($key)) {
            $this->items[$key] = new ChainBag("chain-$key");
        }

        return $this->items[$key];
    }
}