<?php

namespace Infira\Poesis\clause;

class CollectionBag
{
    /**
     * @var ClauseBag;
     */
    public $where;
    /**
     * @var ClauseBag;
     */
    public $set;

    public function getSelectBag(): ClauseBag
    {
        if (!$this->where->hasAny() and $this->set->hasAny()) {
            return $this->set;
        }

        return $this->where;
    }
}