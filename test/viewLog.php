<?php
require_once "config.php";
require_once "fineDiff.php";

use Infira\Poesis\Poesis;
use Infira\Poesis\ConnectionManager;
use Infira\Error\Handler;
use Infira\Utils\Http;
use Infira\Utils\Date;
use Infira\Utils\Variable;

class Db extends ConnectionManager
{
	use PoesisModelShortcut;
}

$config                         = [];
$config['errorLevel']           = -1;
$config['debugBacktraceOption'] = DEBUG_BACKTRACE_IGNORE_ARGS;
$Handler                        = new Handler($config);

try
{
	requireDirFiles("extensions/");
	requireDirFiles("models/");
	Prof()->startTimer("starter");
	Poesis::enableLogger();
	
	$modelName = Poesis::getLogModel();
	/**
	 * @var \TDbLog $db
	 */
	$db  = new $modelName;
	$dir = (in_array(Variable::toLower(Http::getGET("dir", 'asc')), ["asc", "desc"])) ? Http::getGET("dir") : "asc";
	$db->orderBy("ID $dir");
	
	if (Http::existsGET("ID"))
	{
		$ID = Http::getGET("ID", null);
		if ($ID == 'last')
		{
			$db->limit(1);
			$db->orderBy('ID DESC');
		}
		else
		{
			$db->ID($ID);
		}
	}
	elseif (Http::existsGET("dataID"))
	{
		$db->tableName(Http::getGET("dataID"));
	}
	elseif (Http::existsGET("tableName"))
	{
		$db->tableName(Http::getGET("tableName"));
	}
	if (Http::existsGET("tableRowID"))
	{
		$db->tableRowID(Http::getGET("tableRowID"));
	}
	if (Http::existsGET("eventName"))
	{
		$db->eventName(Http::getGET("eventName"));
	}
	if (Http::existsGET("userID"))
	{
		$db->userID(Http::getGET("userID"));
	}
	if (Http::existsGET("url"))
	{
		$db->url(Http::getGET("url"));
	}
	if (Http::existsGET("date"))
	{
		if (Http::existsGET("dateOp"))
		{
			switch (Http::getGET("dateOp"))
			{
				case ">":
					$db->ts->biggerEq(Http::getGET("date"));
				break;
				case "<":
					$db->ts->smaller(Http::getGET("date"));
				break;
			}
		}
		else
		{
			$db->ts(Http::getGET("date"));
		}
	}
	elseif (Http::existsGET("dateFrom") and Http::existsGET("dateTo"))
	{
		$db->ts->between(Http::getGET("dateFrom"), Http::getGET("dateTo"));
	}
	$limit = (Http::existsGET("limit")) ? Http::getGET("limit") : 50;
	$db->limit($limit);
	$query = "ID,ts,uncompress(data) AS data,userID,eventName,tableName,rowIDCols,url,ip";
	$dr    = $db->select($query);
	debug(['logQuery' => [
		'getParams' => Http::getGET(),
		'query'     => $dr->getQuery(),
	]]);
	$list = $dr->eachCollect(function ($log)
	{
		$log->data     = json_decode($log->data);
		$log->ts       = Date::from($log->ts)->toDMY();
		$contitionsMet = true;
		
		if (Http::existsGET("setField"))
		{
			$contitionsMet = false;
			$clause        = $log->data->setClauses;
			$fieldName     = Http::getGET("setField");
			if (Http::existsGET("setValue"))
			{
				if (isset($clause->$fieldName))
				{
					if ($clause->$fieldName == Http::getGET("setValue"))
					{
						$contitionsMet = true;
					}
				}
			}
			else
			{
				if (isset($clause->$fieldName))
				{
					$contitionsMet = true;
				}
			}
		}
		
		if (Http::existsGET("whereField"))
		{
			$contitionsMet = false;
			$fieldName     = Http::getGET("whereField");
			foreach ($log->data->whereClauses as $clause)
			{
				if (Http::existsGET("whereValue"))
				{
					if (isset($clause->$fieldName))
					{
						if ($clause->$fieldName == Http::getGET("whereValue"))
						{
							$contitionsMet = true;
							break;
						}
					}
				}
				else
				{
					if (isset($clause->$fieldName))
					{
						$contitionsMet = true;
						break;
					}
				}
			}
		}
		if (!$contitionsMet)
		{
			return Poesis::VOID;
		}
		
		return $log;
	});
	
	$show = function ($pos, $row) use (&$list)
	{
		$get        = Http::getGET();
		$get["pos"] = $pos;
		
		$makeLink = function ($parts)
		{
			$link = ['viewLog.php?'];
			foreach ($parts as $key => $value)
			{
				$link[] = $key . '=' . $value;
			}
			
			return join('&', $link);
		};
		
		if (isset($list[($pos - 1)]))
		{
			$lget        = $get;
			$lget["pos"] -= 1;
			echo '<a href="' . $makeLink($lget) . '">Prev</a> ';
		}
		if (isset($list[($pos + 1)]))
		{
			$lget        = $get;
			$lget["pos"] += 1;
			echo '<a href="' . $makeLink($lget) . '">Next</a> ';
		}
		debug($row);
	};
	
	$pos = (Http::existsGET("pos")) ? Http::getGET("pos") : 0;
	
	if (Http::existsGET("debugAll"))
	{
		debug($list);
		exit;
	}
	else
	{
		if (isset($list[$pos]))
		{
			$show($pos, $list[$pos]);
		}
	}
}
catch (\Infira\Error\Error $e)
{
	echo $e->getHTMLTable();
}
catch (Throwable $e)
{
	echo $Handler->catch($e)->getHTMLTable();
}