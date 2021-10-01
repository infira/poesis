<?php

namespace Infira\Poesis\dr;

use Infira\Poesis\Connection;
use Infira\Poesis\Poesis;

abstract class DataMethodsFinal
{
	private $rowParsers = [];
	private $afterQuery = [];
	
	/**
	 * @var \mysqli_result
	 */
	protected $res = null;
	protected $query;
	/**
	 * @var Connection
	 */
	protected $Con;
	protected $pointerLocation = false;
	const PASS_ROW_TO_OBJECT = 'PASS_ROW_TO_OBJECT';
	
	public function __call($name, $arguments)
	{
		Poesis::error('Call to undefined method ' . $name);
	}
	
	protected final function setConnection(Connection &$Con)
	{
		$this->Con = &$Con;
	}
	
	protected final function setQuery(string $query)
	{
		$this->query = $query;
	}
	
	public function getQuery(): string
	{
		return $this->query;
	}
	
	public final function setRowParsers(array $callables): DataMethodsFinal
	{
		$this->rowParsers = $callables;
		
		return $this;
	}
	
	public final function addRowParser(callable $parser, array $arguments = []): DataMethodsFinal
	{
		$this->rowParsers[] = (object)['parser' => $parser, 'arguments' => $arguments];
		
		return $this;
	}
	
	public final function nullRowParser(): DataMethodsFinal
	{
		$this->rowParsers = [];
		
		return $this;
	}
	
	public final function hasRowParser(): bool
	{
		return (bool)$this->rowParsers;
	}
	
	protected final function parseRow($row)
	{
		if ($row === null)
		{
			return null;
		}
		if ($this->hasRowParser())
		{
			foreach ($this->rowParsers as $parserItem)
			{
				$row = call_user_func_array($parserItem->parser, array_merge([$row], $parserItem->arguments));
			}
		}
		
		return $row;
	}
	
	public final function onAfterQuery(callable $callable): DataMethodsFinal
	{
		$this->afterQuery[] = $callable;
		
		return $this;
	}
	
	public final function seek(int $nr): DataMethodsFinal
	{
		if (is_object($this->res))
		{
			if ($this->hasRows())
			{
				$this->res->data_seek($nr);
			}
		}
		
		return $this;
	}
	
	/**
	 * @param int|null $setPointer
	 * @return \mysqli_result
	 */
	public final function getRes(int $setPointer = null): \mysqli_result
	{
		if ($this->res === null)
		{
			$this->res = $this->Con->query($this->query);
			foreach ($this->afterQuery as $aq)
			{
				call_user_func_array($aq, [$this->query, $this->res]);
			}
		}
		if ($setPointer !== null)
		{
			$this->seek($setPointer);
		}
		
		return $this->res;
	}
	
	/**
	 * @param string        $fetchMethod
	 * @param array|null    $fetchArguments
	 * @param callable|null $callback
	 * @param bool|null     $collectRows
	 * @return array|null
	 */
	protected final function loop(string $fetchMethod, ?array $fetchArguments, ?callable $callback, ?bool $collectRows): ?array
	{
		if ($collectRows)
		{
			$data = [];
		}
		
		$res = $this->getRes();
		if ($this->hasRows())
		{
			$pointer = 0;
			do
			{
				$createClass          = false;
				$createClassArguments = [];
				$passRowArgumentKey   = false;
				if ($fetchMethod == 'fetch_object' and $fetchArguments != null)
				{
					$class                = $fetchArguments[0];
					$createClassArguments = $fetchArguments[1];
					if ($createClassArguments)
					{
						$passRowArgumentKey = array_search(self::PASS_ROW_TO_OBJECT, $createClassArguments);
						if ($passRowArgumentKey !== false)
						{
							$fRow        = $res->fetch_object();
							$createClass = $class;
						}
						else
						{
							$fRow = $res->fetch_object($class, $createClassArguments);
						}
					}
					else
					{
						$fRow = $res->fetch_object($class);
					}
				}
				elseif ($fetchArguments !== null)
				{
					$fRow = $res->$fetchMethod(...$fetchArguments);
				}
				else
				{
					$fRow = $res->$fetchMethod();
				}
				$row = null;
				if ($fRow !== null)
				{
					$row = $this->parseRow($fRow);
					if ($createClass)
					{
						if ($passRowArgumentKey !== false)
						{
							$createClassArguments[$passRowArgumentKey] = $row;
						}
						$row = new $createClass(...$createClassArguments);
					}
					if ($callback)
					{
						$row = call_user_func_array($callback, [$row]);
					}
					if ($row === Poesis::BREAK)
					{
						break;
					}
					if ($row === Poesis::CONTINUE)
					{
						continue;
					}
					$pointer++;
					if ($collectRows)
					{
						if ($row === null)
						{
							Poesis::error('Looper must return result');
						}
						if ($row !== Poesis::VOID)
						{
							$data[] = $row;
						}
					}
				}
			}
			while ($fRow);
			$this->pointerLocation = $pointer;
		}
		if ($collectRows)
		{
			return $data;
		}
		
		return null;
	}
	
	protected final function fetch(string $fetchMethod, array $fetchArguments = [])
	{
		return $this->parseRow($this->getRes()->$fetchMethod(...$fetchArguments));
	}
	
	protected final function fetchObject(string $class = '\stdClass', array $constructorArguments = []): ?object
	{
		$res = $this->getRes();
		if ($constructorArguments)
		{
			$passRowArgumentKey = array_search(self::PASS_ROW_TO_OBJECT, $constructorArguments);
			if ($passRowArgumentKey !== false)
			{
				$fRow                                      = $this->parseRow($res->fetch_object());
				$constructorArguments[$passRowArgumentKey] = $fRow;
				
				return new $class(...$constructorArguments);
			}
			else
			{
				return $this->parseRow($res->fetch_object($class, $constructorArguments));
			}
		}
		else
		{
			return $this->parseRow($res->fetch_object($class));
		}
	}
	
	//region cache
	
	/**
	 * use cache
	 *
	 * @param string|null $key    - cache key
	 * @param string      $driver - mem,sess,redis,rm,auto
	 * @return DataCacher
	 */
	public final function cache(string $key = null, $driver = "auto"): DataCacher
	{
		$key = $key ? $this->query . $key : $this->query;
		
		return new DataCacher($this, $driver, $key, $this->Con);
	}
	
	/**
	 * use session cache
	 *
	 * @param string|null $key - cache key
	 * @return DataCacher
	 */
	public final function cacheSession(string $key = null): DataCacher
	{
		return $this->cache($key, 'sess');
	}
	
	/**
	 * use memcached cache
	 *
	 * @param string|null $key - cache key
	 * @return DataCacher
	 */
	public final function cacheMem(string $key = null): DataCacher
	{
		return $this->cache($key, 'mem');
	}
	
	/**
	 * use redis cache
	 *
	 * @param string|null $key - cache key
	 * @return DataCacher
	 */
	public final function cacheRedis(string $key = null): DataCacher
	{
		return $this->cache($key, 'redis');
	}
	
	/**
	 * use runtime memory cache
	 *
	 * @param string|null $key - cache key
	 * @return DataCacher
	 */
	public final function cacheRm(string $key = null): DataCacher
	{
		return $this->cache($key, 'rm');
	}
	//
}