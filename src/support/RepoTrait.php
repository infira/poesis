<?php

namespace Infira\Poesis\support;


use Infira\Poesis\DbSchema;
use Infira\Poesis\Connection;

trait RepoTrait
{
	private static $data           = [];
	protected      $connectionName = 'defaultConnection';
	
	final protected function dbSchema(): DbSchema
	{
		return Repository::dbSchema($this->connectionName);
	}
	
	final protected function connection(): Connection
	{
		return Repository::connection($this->connectionName);
	}
}