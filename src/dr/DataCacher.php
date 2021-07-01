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
	protected $methods;
	protected $Con;
	
	public function __construct(DataMethods &$Methods, string $driver, string $ecid, &$Con)
	{
		$this->methods = $Methods;
		if (!$driver or $driver === 'auto')
		{
			$driver = Cache::getDefaultDriver();
		}
		if (!Cache::isInitialized())
		{
			Cache::init();
		}
		$this->driver  = $driver;
		$this->ecid    = $ecid;
		$this->methods = $Methods;
		$this->Con     = &$Con;
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
	
	public function __call($methodName, $arguments)
	{
		if ($methodName == 'each')
		{
			$data = Cache::di($this->driver, "databaseCache")->once([$methodName, $this->ecid], function () use ($methodName, $arguments)
			{
				return $this->methods->getObjects();
			});
			foreach ($data as $row)
			{
				$caller = $arguments[0];
				$caller($row);
			}
		}
		else
		{
			if (in_array($methodName, ['debug', 'each']))
			{
				Poesis::error("Cant use method($methodName) in cache");
			}
			
			return Cache::di($this->driver, "databaseCache")->once([$methodName, $this->ecid], function () use ($methodName, $arguments)
			{
				return $this->methods->$methodName(...$arguments);
			});
		}
	}
}

?>