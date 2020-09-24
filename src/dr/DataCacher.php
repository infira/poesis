<?php

namespace Infira\Poesis\dr;

use Infira\Poesis\PoesisCache;

/**
 * @mixin DataGetters
 */
class DataCacher
{
	private $query;
	private $method;
	private $driver;
	private $ecid = "";//extraCacheID
	private $ttl  = 0; //time to live
	
	protected $Db;
	
	public function __construct($query, $adapter, $ecid, &$Db)
	{
		$this->query = $query;
		if (!$adapter or $adapter === 'auto')
		{
			$adapter = PoesisCache::getDefaultDriver();
		}
		$this->driver = $adapter;
		$this->ecid   = $ecid;
		$this->Db     = &$Db;
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
		$this->method = $name;
		
		return PoesisCache::di($this->driver, "databaseCache")->once([$this->query, $this->method, $this->ecid], function () use ($name, $arguments)
		{
			$Getter = new DataGetters($this->query, $this->Db);
			
			return $Getter->$name(...$arguments);
		});
	}
}

?>