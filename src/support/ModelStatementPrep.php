<?php

namespace Infira\Poesis\support;

use Infira\Poesis\clause\{ModelColumn, Field};

/**
 * @uses  \Infira\Poesis\Model
 */
trait ModelStatementPrep
{
	private $chain             = -1;
	private $isChain           = false;
	private $currentClauseType = 'set';
	
	protected final function add2Clause($clauseItem): self
	{
		if (!$this->isChain) {
			$this->chain++;
			$t          = clone($this);
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
