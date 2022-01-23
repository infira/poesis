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
	
	/**
	 * Clause constructor.
	 *
	 * @param string $schemaClassName
	 * @param string $connectionName - name for ConnectionManager instance
	 */
	public function __construct(Clause $where, Clause $set)
	{
		$this->where = clone $where;
		$this->set   = clone $set;
	}
}