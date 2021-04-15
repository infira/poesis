<?php

namespace Infira\Poesis\dr;

use Infira\Poesis\Cache;
use Infira\Poesis\Poesis;

/**
 * @mixin \Infira\Poesis\dr\DataMethods
 */
class DataCacher
{
	private   $driver;
	private   $ecid = "";           //extraCacheID
	private   $ttl  = 0;            //time to live
	protected $query;
	protected $Con;
	
	public function __construct($query, string $driver, string $ecid = null, &$Con)
	{
		$this->query = $query;
		if (!$driver or $driver === 'auto')
		{
			$driver = Cache::getDefaultDriver();
		}
		if (!Cache::isInitialized())
		{
			Cache::init();
		}
		$this->driver = $driver;
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
	public function ttl(int $ttl): DataCacher
	{
		$this->ttl = $ttl;
		
		return $this;
	}
	
	public function __call($name, $arguments)
	{
		if (in_array($name, ['debug', 'each']))
		{
			Poesis::error("Cant use method($name) in cache");
		}
		
		return Cache::di($this->driver, "databaseCache")->once([$this->query, $name, $this->ecid], function () use ($name, $arguments)
		{
			$Getter = new DataCacheRetrieval($this->query, $this->Con);
			
			return $Getter->$name(...$arguments);
		});
	}
}

?>