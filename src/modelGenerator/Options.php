<?php

namespace Infira\Poesis\modelGenerator;


use Infira\Utils\Dir;

class Options
{
	public  $shortcutNamespace = '';
	public  $shortcutExtendor  = '';
	public  $shortutTraitName  = 'PoesisModelShortcut';
	public  $classNameSuffix   = '';
	private $modelExtendors    = [];
	
	/**
	 * @var \Closure
	 */
	private $isTableOk;
	
	
	public function __construct()
	{
		$this->setTableFilterer(function ($tableName)
		{
			return true;
		});
	}
	
	public function setModelExtendorsFolder(string $path)
	{
		if (!is_dir($path))
		{
			alert("Model extendor must be correct path($path)");
		}
		foreach (Dir::getContents($path) as $extendor)
		{
			$extendor  = str_replace(['.trait.php', '.php'], '', $extendor);
			$modelName = substr($extendor, 0, -9);
			$this->addModelExtendor($modelName, $extendor);
		}
	}
	
	public function addModelExtendor(string $modelName, string $extendor)
	{
		if (!$this->modelHasExtendors($modelName))
		{
			$this->modelExtendors[$modelName] = [];
		}
		$this->modelExtendors[$modelName][] = $extendor;
	}
	
	public function setModelExtendors(string $modelName, array $extendors)
	{
		$this->modelExtendors[$modelName] = $extendors;
	}
	
	public function modelHasExtendors(string $modelName): bool
	{
		return isset($this->modelExtendors[$modelName]);
	}
	
	public function godelHasExtendors(string $modelName): array
	{
		return $this->modelExtendors[$modelName];
	}
	
	public function setTableFilterer(callable $function)
	{
		$this->isTableOk = $function;
	}
	
	public function _isTableOk($tableName): bool
	{
		$method = $this->isTableOk;
		
		return $method($tableName);
	}
	
	
}

?>