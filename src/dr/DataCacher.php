<?php

namespace Infira\Poesis\dr;

use Infira\Poesis\Cache;

/**
 * @mixin DataGetResult
 */
class DataCacher
{
	private   $method;
	private   $driver;
	private   $ecid = "";           //extraCacheID
	private   $ttl  = 0;            //time to live
	protected $query;
	protected $Con;
	
	public function __construct($query, $adapter, string $ecid = null, &$Con)
	{
		$this->query = $query;
		if (!$adapter or $adapter === 'auto')
		{
			$adapter = Cache::getDefaultDriver();
		}
		$this->driver = $adapter;
		$this->ecid   = $ecid;
		$this->query  = $query;
		$this->Con    = &$Con;
	}
	
	/**
	 * Set cache time to live
	 *
	 * @param int $ttl
	 * @return $this
	 */
	public function ttl(int $ttl)
	{
		$this->ttl = $ttl;
		
		return $this;
	}
	
	public function __call($name, $arguments)
	{
		if (in_array($name, ['debug', 'each']))
		{
			alert("Cant use method($name) in cache");
		}
		
		return Cache::di($this->driver, "databaseCache")->once([$this->query, $this->method, $this->ecid], function () use ($name, $arguments)
		{
			$Getter = new DataCacheRetrieval($this->query, $this->Con);
			
			return $Getter->$name(...$arguments);
		});
	}
}

?>