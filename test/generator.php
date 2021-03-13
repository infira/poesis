<?php
require_once "../src/extendors/PoesisConnectionExtendor.php";
require_once "../src/extendors/PoesisDataMethodsExtendor.php";
require_once "../src/extendors/PoesisModelExtendor.php";
require_once "models/PoesisModelShortcut.trait.php";


use Infira\Poesis\Poesis;
use Infira\Poesis\ConnectionManager;

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
	$options = new \Infira\Poesis\modelGenerator\Options();
	$gen     = new Infira\Poesis\modelGenerator\Generator(ConnectionManager::default());
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
