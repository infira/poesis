<?php

namespace Infira\Poesis\dr;

use Infira\Poesis\Connection;

class DataCacheRetrieval extends DataMethods
{
	/**
	 * @param string     $query - sql query for data retrieval
	 * @param Connection $Con
	 */
	public function __construct(string $query, Connection &$Con)
	{
		$this->setDb($query, $Con);
	}
}

?>