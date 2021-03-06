<?php

namespace Infira\Poesis\dr;

use Infira\Poesis\Connection;

class DataRetrieval extends DataMethods
{
	use DataGetResult;
	
	/**
	 * @param string     $query - sql query for data retrieval
	 * @param Connection $Con
	 */
	public function __construct(string $query, Connection &$Con)
	{
		$this->setDb($query, $Con);
	}
	
	
	/**
	 * use cache
	 *
	 * @param string|null $key     - cache key
	 * @param string      $adapter - mem,sess,redis,auto
	 * @return DataCacher
	 */
	public function cache(string $key = null, $adapter = "auto")
	{
		return new DataCacher($this->query, $adapter, $key, $this->Con);
	}
}

?>