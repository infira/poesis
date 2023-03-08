<?php

namespace Infira\Poesis\clause;

class ClauseCollection
{
    /**
     * @var ClauseBag;
     */
    public $where;
    /**
     * @var ClauseBag;
     */
    public $set;

    public function __construct(ClauseBag $where, ClauseBag $set)
    {
        $this->where = $where;
        $this->set = $set;
    }

    public function getSelectClause(): ClauseBag
    {
        if (!$this->where->hasAny() && $this->set->hasAny()) {
            return $this->set;
        }

        return $this->where;
    }
}