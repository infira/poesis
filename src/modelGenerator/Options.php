<?php

namespace Infira\Poesis\modelGenerator;


use Infira\Utils\Dir;
use Infira\Poesis\Poesis;
use Infira\Utils\Regex;
use Infira\Utils\File;

class Options
{
	public $shortcutNamespace = '';
	public $shortcutExtendor  = '';
	public $shortutTraitName  = 'PoesisModelShortcut';
	
	public $modelImports         = [];
	public $generalModelExtendor = 'Model';
	public $generalModelImports  = [];
	public $modelExtendors       = [];
	
	public  $classNameSuffix    = '';
	public  $fileExtension      = 'php';
	public  $traitFileExtension = 'trait.php';
	private $modelTraits        = [];
	
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
	
	public function scanModelTraitFolder(string $path)
	{
		if (!is_dir($path))
		{
			alert("scan model trait folder must be correct path($path)");
		}
		foreach (Dir::getContents($path) as $trait)
		{
			$trait = str_replace(['.trait.php', '.php'], '', $trait);
			if (substr($trait, -9) == 'Extension')
			{
				$model = substr($trait, 0, -9);
				$this->addModelTrait($model, $trait);
			}
		}
	}
	
	public function addModelTrait(string $model, string $trait)
	{
		if (!array_key_exists($model, $this->modelTraits))
		{
			$this->modelTraits[$model] = [];
		}
		$this->modelTraits[$model][] = $trait;
	}
	
	public function setModelTraits(string $model, array $traits)
	{
		$this->modelTraits[$model] = $traits;
	}
	
	public function getModelTraits(string $model): array
	{
		if (!array_key_exists($model, $this->modelTraits))
		{
			return [];
		}
		
		return $this->modelTraits[$model];
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
	
	public function scanModelExtensionFolder(string $path)
	{
		if (!is_dir($path))
		{
			alert("scan model extendor folder must be correct path($path)");
		}
		foreach (Dir::getContents($path) as $fn)
		{
			$extendor = str_replace('.php', '', $fn);
			if (substr($extendor, -5) == 'Model')
			{
				$model       = substr($extendor, 0, -5);
				$fileContent = File::getContent($path . $fn);
				//$con = Regex::getMatches('/<?php(.*)?class/ms', $fileContent);
				$imports = [];
				if (Regex::isMatch('/namespace (.+)?;/m', $fileContent))
				{
					$matches = [];
					preg_match_all('/namespace (.+)?;/m', $fileContent, $matches);
					$imports[] = $matches[1][0] . '\\' . $extendor;
				}
				else
				{
					$extendor = '\\' . $extendor;
				}
				$this->addModelExtendor($model, $extendor, $imports);
			}
		}
	}
	
	public function getModelExtendor(string $model): string
	{
		if (array_key_exists($model, $this->modelExtendors))
		{
			return $this->modelExtendors[$model];
		}
		
		return $this->generalModelExtendor;
	}
	
	public function setGeneralModelExtendor(string $extendor, array $imports = [])
	{
		if ($extendor == 'Model')
		{
			Poesis::error('Cant use Model as extendor, its built in');
		}
		$this->generalModelExtendor = $extendor;
		$this->generalModelImports  = $imports;
	}
	
	public function addModelExtendor(string $model, string $extendor, array $imports = [])
	{
		if ($extendor == 'Model')
		{
			Poesis::error('Cant use Model as extendor, its built in');
		}
		$this->modelExtendors[$model] = $extendor;
		$this->modelImports[$model]   = $imports;
	}
	
	public function getModelImports(string $model): array
	{
		if ($this->getModelExtendor($model) === 'Model')
		{
			return ['\Infira\Poesis\orm\Model'];
		}
		elseif (array_key_exists($model, $this->modelImports))
		{
			return $this->modelImports[$model];
		}
		
		return $this->generalModelImports;
	}
	
}

?>