<?php

namespace Infira\Poesis\support;

use Infira\Poesis\clause\{Field, ModelColumn};

/**
 * @uses  \Infira\Poesis\Model
 */
trait ModelStatementPrep
{
    private $chain = -1;
    private $isChain = false;
    private $currentClauseType = 'set';

    /**
     * @param $clauseItem
     * @return $this
     */
    protected final function add2Clause($clauseItem)
    {
        if (!$this->isChain) {
            $this->chain++;
            $t = clone($this);
            $t->isChain = true;
        }
        else {
            $t = $this;
        }

        if ($this->currentClauseType == 'where') {
            $this->Clause->addWhre($this->chain, $clauseItem);
        }
        else {
            $this->Clause->addSet($this->chain, $clauseItem);
        }

        return $t;
    }

    protected function value2ModelColumn(string $column, $value): ModelColumn
    {
        if ($value instanceof ModelColumn) {
            $modelColumn = $value;
        }
        else {
            $modelColumn = $this->makeModelColumn($column);
            if ($value instanceof Field) {
                $method = 'setExpression';
            }
            else {
                $method = 'value';
            }
            $modelColumn->$method($value);
        }


        return $modelColumn;
    }

}
