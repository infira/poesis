<?php
require_once __DIR__ . '/Autoloader.php';
\Infira\Poesis\Autoloader::setDataGettersExtendorPath(__DIR__ . '/extendors/PoesisDataMethodsExtendor.php');
\Infira\Poesis\Autoloader::setConnectionExtendorPath(__DIR__ . '/extendors/PoesisConnectionExtendor.php');
\Infira\Poesis\Autoloader::setModelExtendorPath(__DIR__ . '/extendors/PoesisModelExtendor.php');
spl_autoload_register(['Infira\Poesis\Autoloader', 'loader'], true);