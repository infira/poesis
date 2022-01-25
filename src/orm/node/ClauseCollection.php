<?php

namespace Infira\Poesis\orm\node;

class ClauseCollection
{
	/**
	 * @var Clause
	 */
	public $where;
	/**
	 * @var Clause
	 */
	public $set;
	
	public function __construct(Clause $where, Clause $set)
	{
		$this->where = clone $where;
		$this->set   = clone $set;
	}
}