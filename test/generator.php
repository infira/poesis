<?php
require_once "config.php";

use Infira\Poesis\Poesis;
use Infira\Poesis\ConnectionManager;


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
	$options = new \Infira\Poesis\modelGenerator\Options();
	$options->setGeneralModelExtendor('myCustomAbstractModelExtendor');
	
	$gen = new Infira\Poesis\modelGenerator\Generator(ConnectionManager::default(), $options);
	debug($gen->generate('models/'));
}
catch (\Infira\Error\Error $e)
{
	echo $e->getMessage();
}
catch (Throwable $e)
{
	echo $Handler->catch($e);
}
