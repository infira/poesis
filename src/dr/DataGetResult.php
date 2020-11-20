<?php

namespace Infira\Poesis\dr;

use Infira\Poesis\Poesis;
use Infira\Poesis\Connection;

/**
 * @mixin DataRetrieval
 * @mixin DataCacher
 */
trait DataGetResult
{
	/**
	 * @var mysqli_result
	 */
	protected $res = null;
	protected $query;
	/**
	 * @var Connection
	 */
	protected $Con;
	
	protected $rowParserCallback  = false;
	protected $rowParserArguments = [];
	protected $rowParserScope     = false;
	protected $pointerLocation    = false;
	
	public function setRowParser($parser, $class = false, $arguments = [])
	{
		$this->rowParserCallback  = $parser;
		$this->rowParserScope     = $class;
		$this->rowParserArguments = (!is_array($arguments)) ? [] : $arguments;
		
		return $this;
	}
	
	public function nullRowParser()
	{
		$this->rowParserCallback  = false;
		$this->rowParserScope     = false;
		$this->rowParserArguments = [];
		
		return $this;
	}
	
	
	/**
	 * @param string     $query - sql query for data retrieval
	 * @param Connection $Con
	 */
	protected function setDb(string $query, Connection &$Con)
	{
		$this->Con   = &$Con;
		$this->query = $query;
	}
	
	/**
	 * @param int $setPointer
	 * @return \mysqli_result
	 */
	public function getRes(int $setPointer = null)
	{
		if ($this->res === null)
		{
			$this->res = $this->Con->query($this->query);
		}
		if ($setPointer !== null)
		{
			$this->seek($setPointer);
		}
		
		return $this->res;
	}
	
	private function __looper($fetchMethod, $callback, object $scope = null, $collectRows = false)
	{
		if ($collectRows)
		{
			$data = [];
		}
		/**
		 * @var mysqli_result $res
		 */
		$res = $this->getRes();
		if ($this->hasRows())
		{
			$pointer = 0;
			do
			{
				if ($fetchMethod == "fetch_array.MYSQLI_ASSOC")
				{
					$dbRow = $res->fetch_array(MYSQLI_ASSOC);
				}
				else
				{
					$dbRow = $res->$fetchMethod();
				}
				$row = null;
				if ($dbRow !== null)
				{
					$row = $this->parseRow($dbRow);
					if ($row !== Poesis::SKIP)
					{
						if ($callback)
						{
							$row   = callback($callback, $scope, [$row]);
						}
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
						$data[] = $row;
					}
				}
			}
			while ($dbRow);
			$this->setPointerLocation($pointer);
		}
		if ($collectRows)
		{
			return $data;
		}
	}
	
	protected function loop($fetchMethod, $callback = null, $scope = null)
	{
		return $this->__looper($fetchMethod, $callback, $scope, true);
	}
	
	protected function collectRows($fetchMethod, $callback = null, $scope = null)
	{
		return $this->__looper($fetchMethod, $callback, $scope, true);
	}
	
	protected function fetch($fetchMethod)
	{
		return $this->parseRow($this->getRes()->$fetchMethod());
	}
	
	protected function parseRow($row, $arguments = [])
	{
		if ($this->rowParserCallback === false)
		{
			return $row;
		}
		else
		{
			return callback($this->rowParserCallback, $this->rowParserScope, array_merge([$row], $this->rowParserArguments, $arguments));
		}
	}
	
	protected function setPointerLocation($location = 0)
	{
		$this->pointerLocation = $location;
		
		return $this;
	}
	
	
	public function seek($nr)
	{
		if (is_object($this->res))
		{
			if ($this->hasRows())
			{
				$this->res->data_seek(intval($nr));
			}
		}
		
		return $this;
	}
	
	public function each($callback = null, $scope = null)
	{
		return $this->loop("fetch_object", $callback, $scope);
	}
	
	public function debug()
	{
		if ($this->count() > 1)
		{
			debug($this->objectRows());
		}
		else
		{
			debug($this->object());
		}
	}
}

?>