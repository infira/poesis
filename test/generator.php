<?php

use Infira\Poesis\Poesis;

require_once "../vendor/infira/errorhandler/src/Error.php";
require_once "config.php";

Poesis::useInfiraErrorHadler();
$config                         = [];
$config['errorLevel']           = -1;
$config['beforeThrow']          = function (\Infira\Error\Node $Node)
{
	//var_dump($Node->getVars());
};
$config['debugBacktraceOption'] = 0;
$Handler                        = new \Infira\Error\Handler($config);

try
{
	$gen = new Infira\Poesis\generator\ModelGenerator("models/");
	$gen->generate();
}
catch (\Infira\Error\Error $e)
{
	echo $e->getMessage();
}
catch (Throwable $e)
{
	echo $Handler->catch($e);
}
