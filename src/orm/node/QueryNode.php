<?php

namespace Infira\Poesis\orm\node;

class QueryNode
{
	public $table            = '';
	public $selectFields     = null; //fields to use in SELECT $selectFields FROM, * - use to select all fields, otherwise it will be exploded by comma
	public $fields           = [];
	public $where            = [];
	public $orderBy          = '';
	public $groupBy          = '';
	public $limit            = '';
	public $isCollection     = false;
	public $collectionValues = [];
}

?>