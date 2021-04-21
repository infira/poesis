<?php

namespace Infira\Poesis\dr;

use Infira\Poesis\orm\node\Statement;
use Infira\Poesis\Connection;

class ModelDataMethods extends DataMethods
{
	public final function __construct(Statement &$statement, Connection &$Con)
	{
		parent::__construct($statement->query(), $Con);
		$this->setStatement($statement);
		$this->setRowParsers($statement->rowParsers());
	}
}

?>