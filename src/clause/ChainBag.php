<?php

namespace Infira\Poesis\clause;

use Infira\Poesis\Poesis;

class ChainBag extends Bag
{
    /**
     * @param  ModelColumn|LogicalOperator|Field  ...$item
     * @return $this
     * @throws \Infira\Poesis\Error
     */
    public function addCondition(...$item)
    {
        foreach ($item as $i) {
            if ($i instanceof ModelColumn || $i instanceof LogicalOperator || $i instanceof Field) {
                $this->items[] = $i;
            }
            elseif (is_object($i)) {
                Poesis::error("unknown type('".get_class($i)."')");
            }
            else {
                Poesis::error("unknown type('".$i."')");
            }
        }
        return $this;
    }

    /**
     * @return LogicalOperator|ModelColumn|Field
     */
    public function at(int $key)
    {
        return $this->items[$key];
    }

    /**
     * @return array<array-key,LogicalOperator|ModelColumn|Field>
     */
    public function getConditions(): array
    {
        return $this->items;
    }

    /**
     * @return ModelColumn[]
     */
    public function getColumns(): array
    {
        return array_filter($this->items, static function ($item) {
            return $item instanceof ModelColumn;
        });
    }
}