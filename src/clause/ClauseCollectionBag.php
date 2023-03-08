<?php

namespace Infira\Poesis\clause;

class ClauseCollectionBag extends Bag
{
    /**
     * @return ClauseBag[]
     */
    public function getClauses(): array
    {
        return $this->items;
    }

    /**
     * @return ModelColumn[]
     */
    public function getColumns(): array
    {
        $output = [];
        foreach ($this->getClauses() as $collectionBag) {
            array_push($output, ...$collectionBag->getColumns());
        }

        return $output;
    }

    public function addCollection(ClauseBag ...$item): void
    {
        $this->addItems(...$item);
    }

    public function at(int $key): ClauseBag
    {
        return $this->getAt($key, new ClauseBag('empty'));
    }

    public function collect(int $key): ClauseBag
    {
        if (!$this->exists($key)) {
            $this->items[$key] = new ClauseBag("collection-$key");
        }

        return $this->items[$key];
    }
}