<?php

namespace Infira\Poesis\modelGenerator;


use Infira\Utils\Dir;
use Infira\Poesis\Poesis;
use Infira\Utils\Regex;
use Infira\Utils\File;

class Options
{
	private $shortcutNamespace              = '';
	private $shortcutSubTraits              = [];
	private $shortutTraitName               = 'PoesisModelShortcut';
	private $shortcutTraitFileNameExtension = 'trait.php';
	
	private $modelNamespace             = '';
	private $modelImports               = [];
	private $defaultModelExtendor       = '\Infira\Poesis\orm\Model';
	private $modelExtendors             = [];
	private $modelTraits                = [];
	private $modelClassNamePrefix       = 'T';
	private $modelFileNameExtension     = 'php';
	private $modelDefaultConnectionName = 'defaultPoesisDbConnection';
	
	private $makeNodes           = [];
	private $defaultNodeExtendor = '\Infira\Poesis\orm\Node';
	private $moodelNodeExtendors = [];
	private $modelNodeTraits     = [];
	
	private $makeModelDataMethods            = [];
	private $defaultModelDataMethodsExtendor = '\Infira\Poesis\dr\DataMethods';
	private $modelDataMethodsExtendors       = [];
	private $modelDataMethodsTraits          = [];
	
	private $modelColumnClass = '\Infira\Poesis\orm\ModelColumn';
	
	
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
	
	public function __set(string $name, $value)
	{
		Poesis::error("unknown property $name");
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
	
	public function addShortcutTrait(string $trait)
	{
		$this->shortcutSubTraits[] = $trait;
	}
	
	public function getShortcutTraits(): array
	{
		return $this->shortcutSubTraits;
	}
	
	public function scanExtensions(string $path)
	{
		if (!is_dir($path))
		{
			Poesis::error("scan model extendor folder must be correct path($path)");
		}
		foreach (Dir::getContents($path) as $fn)
		{
			$extension = str_replace('.php', '', $fn);
			if ($model = $this->findFileExtension($path, $fn, 'Model'))
			{
				$this->setModelExtendor($model->model, $model->extension);
			}
			elseif (substr($extension, -9) == 'Extension')
			{
				$model = substr($extension, 0, -9);
				$this->addModelTrait($model, $extension);
			}
			elseif ($dm = $this->findFileExtension($path, $fn, 'DataMethods'))
			{
				$this->setModelDataMethodsExtendor($dm->model, $dm->extension);
			}
			elseif ($dm = $this->findFileExtension($path, $fn, 'Node'))
			{
				$this->setModelNodeExtendor($dm->model, $dm->extension);
			}
		}
	}
	
	private function findFileExtension($path, $file, $type): ?\stdClass
	{
		$extension = str_replace('.php', '', $file);
		$len       = strlen($type) * -1;
		if (substr($extension, $len) == $type)
		{
			$model       = substr($extension, 0, $len);
			$fileContent = File::getContent($path . $file);
			//$con = Regex::getMatches('/<?php(.*)?class/ms', $fileContent);
			if (Regex::isMatch('/namespace (.+)?;/m', $fileContent))
			{
				$matches = [];
				preg_match_all('/namespace (.+)?;/m', $fileContent, $matches);
				$this->addModelImport($model, '\\' . $matches[1][0] . '\\' . $extension);
			}
			else
			{
				$extension = '\\' . $extension;
			}
			
			return (object)['model' => $model, 'extension' => $extension];
		}
		
		return null;
	}
	
	//region model optons
	public function setDefaultModelExtendor(string $extendor)
	{
		$this->defaultModelExtendor = $extendor;
	}
	
	public function setModelExtendor(string $model, string $extendor)
	{
		$this->modelExtendors[$model] = $extendor;
	}
	
	public function getModelExtendor(string $model): string
	{
		if (array_key_exists($model, $this->modelExtendors))
		{
			return $this->modelExtendors[$model];
		}
		
		return $this->defaultModelExtendor;
	}
	
	public function addModelTrait(string $model, string $trait)
	{
		if (!array_key_exists($model, $this->modelTraits))
		{
			$this->modelTraits[$model] = [];
		}
		$this->modelTraits[$model][] = $trait;
	}
	
	public function getModelTraits(string $model): array
	{
		if (!array_key_exists($model, $this->modelTraits))
		{
			return [];
		}
		
		return $this->modelTraits[$model];
	}
	
	public function addModelImport(string $model, string $import)
	{
		if (!array_key_exists($model, $this->modelTraits))
		{
			$this->modelImports[$model] = [];
		}
		$this->modelImports[$model][] = $import;
	}
	
	public function getModelImports(string $model): array
	{
		$imports = [];
		if (array_key_exists($model, $this->modelImports))
		{
			$imports = $this->modelImports[$model];
		}
		
		return $imports;
	}
	
	public function getModelNamespace(): string
	{
		return $this->modelNamespace;
	}
	
	public function setModelNamespace(string $modelNamespace): void
	{
		$this->modelNamespace = $modelNamespace;
	}
	
	public function getModelClassNamePrefix(): string
	{
		return $this->modelClassNamePrefix;
	}
	
	public function setModelClassNamePrefix(string $modelClassNamePrefix): void
	{
		$this->modelClassNamePrefix = $modelClassNamePrefix;
	}
	
	public function getModelFileNameExtension(): string
	{
		return $this->modelFileNameExtension;
	}
	
	public function setModelFileNameExtension(string $modelFileNameExtension): void
	{
		$this->modelFileNameExtension = $modelFileNameExtension;
	}
	
	public function getModelDefaultConnectionName(): string
	{
		return $this->modelDefaultConnectionName;
	}
	
	public function setModelDefaultConnectionName(string $modelDefaultConnectionName): void
	{
		$this->modelDefaultConnectionName = $modelDefaultConnectionName;
	}
	
	//endregion
	
	//region model data methods
	public function setDefaultModelDataMethodsExtendor(string $extendor)
	{
		$this->defaultModelDataMethodsExtendor = $extendor;
	}
	
	public function setModelDataMethodsExtendor(string $model, string $extendor)
	{
		$this->modelDataMethodsExtendors[$model] = $extendor;
	}
	
	public function getModelDataMethodsExtendor(string $model): string
	{
		if (array_key_exists($model, $this->modelDataMethodsExtendors))
		{
			return $this->modelDataMethodsExtendors[$model];
		}
		
		return $this->defaultModelDataMethodsExtendor;
	}
	
	public function addModelDataMethodsTrait(string $model, string $trait)
	{
		if (!array_key_exists($model, $this->modelDataMethodsTraits))
		{
			$this->modelDataMethodsTraits[$model] = [];
		}
		$this->modelDataMethodsTraits[$model][] = $trait;
	}
	
	public function getModelDataMethodsTraits(string $model): array
	{
		if (!array_key_exists($model, $this->modelDataMethodsTraits))
		{
			return [];
		}
		
		return $this->modelDataMethodsTraits[$model];
	}
	
	public function setModelMakeDataMethods(string $model, string $createNodeClassName = null, array $createNodeConstructorParams = [])
	{
		$this->makeModelDataMethods[$model] = ['createNodeClassName' => $createNodeClassName, 'createNodeConstructorParams' => $createNodeConstructorParams];
	}
	
	public function getModelMakeDataMethods(string $model): ?array
	{
		if (isset($this->makeModelDataMethods[$model]))
		{
			return $this->makeModelDataMethods[$model];
		}
		
		return null;
	}
	
	//endregion
	
	//region node optons
	public function setDefaultModelNodeExtendor(string $extendor)
	{
		$this->defaultNodeExtendor = $extendor;
	}
	
	public function setModelNodeExtendor(string $model, string $extendor)
	{
		$this->moodelNodeExtendors[$model] = $extendor;
	}
	
	public function getModelNodeExtendor(string $model): string
	{
		if (array_key_exists($model, $this->moodelNodeExtendors))
		{
			return $this->moodelNodeExtendors[$model];
		}
		
		return $this->defaultNodeExtendor;
	}
	
	public function setModelMakeNode(string $model, string $createNodeClassName = null)
	{
		$this->makeNodes[$model] = ['createNodeClassName' => $createNodeClassName];
	}
	
	public function getModelMakeNode(string $model): ?array
	{
		if (isset($this->makeNodes[$model]))
		{
			return $this->makeNodes[$model];
		}
		
		return null;
	}
	
	public function addModelNodeTrait(string $model, string $trait)
	{
		if (!array_key_exists($model, $this->modelNodeTraits))
		{
			$this->modelNodeTraits[$model] = [];
		}
		$this->modelNodeTraits[$model][] = $trait;
	}
	
	public function getModelNodeTraits(string $model): array
	{
		if (!array_key_exists($model, $this->modelNodeTraits))
		{
			return [];
		}
		
		return $this->modelNodeTraits[$model];
	}
	
	//endregion
	
	//region shortcut options
	public function getShortutTraitName(): string
	{
		return $this->shortutTraitName;
	}
	
	public function setShortutTraitName(string $shortutTraitName): void
	{
		$this->shortutTraitName = $shortutTraitName;
	}
	
	public function getShortcutNamespace(): string
	{
		return $this->shortcutNamespace;
	}
	
	public function setShortcutNamespace(string $shortcutNamespace): void
	{
		$this->shortcutNamespace = $shortcutNamespace;
	}
	
	public function getShortcutTraitFileNameExtension(): string
	{
		return $this->shortcutTraitFileNameExtension;
	}
	
	public function setShortcutTraitFileNameExtension(string $shortcutTraitFileNameExtension): void
	{
		$this->shortcutTraitFileNameExtension = $shortcutTraitFileNameExtension;
	}
	//endregion
	
	//region model column options
	public function setModelColumnClass(string $class)
	{
		$this->modelColumnClass = $class;
	}
	
	public function getModelColumnClass(): string
	{
		return $this->modelColumnClass;
	}
}

?>