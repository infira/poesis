<?php

namespace Infira\Poesis\dr;

use Infira\Poesis\Connection;

class DataRetrieval extends DataMethods
{
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
	 * @param string|null $key    - cache key
	 * @param string      $driver - mem,sess,redis,rm,auto
	 * @return DataCacher
	 */
	public function cache(string $key = null, $driver = "auto"): DataCacher
	{
		return new DataCacher($this->query, $driver, $key, $this->Con);
	}
	
	/**
	 * use session cache
	 *
	 * @param string|null $key - cache key
	 * @return DataCacher
	 */
	public function cacheSession(string $key = null): DataCacher
	{
		return $this->cache($key, 'sess');
	}
	
	/**
	 * use memcached cache
	 *
	 * @param string|null $key - cache key
	 * @return DataCacher
	 */
	public function cacheMem(string $key = null): DataCacher
	{
		return $this->cache($key, 'mem');
	}
	
	/**
	 * use redis cache
	 *
	 * @param string|null $key - cache key
	 * @return DataCacher
	 */
	public function cacheRedis(string $key = null): DataCacher
	{
		return $this->cache($key, 'redis');
	}
	
	/**
	 * use runtime memory cache
	 *
	 * @param string|null $key - cache key
	 * @return DataCacher
	 */
	public function cacheRm(string $key = null): DataCacher
	{
		return $this->cache($key, 'rm');
	}
}

?>