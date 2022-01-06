<?php

namespace Infira\Poesis\orm;

interface ModelContract
{
	/**
	 * Select data from database
	 *
	 * @param string|array $columns - columns to use in SELECT $columns FROM USE null OR *[string] - used to select all columns, string will be exploded by ,
	 * @return \Infira\Poesis\dr\DataMethods
	 */
	public function select($columns = null);
}
