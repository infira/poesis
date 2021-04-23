<?php
require_once "config.php";

use Infira\Poesis\ConnectionManager;


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
	\Infira\Poesis\Poesis::enableTID();
	$options = new \Infira\Poesis\modelGenerator\Options();
	$options->setDefaultModelExtendor('myCustomAbstractModelExtendor');
	$options->scanExtensions('extensions/');
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
