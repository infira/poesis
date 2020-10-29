<?php

namespace Infira\Poesis\modelGenerator;


class Options
{
	public $shortcutNamespace = '';
	public $shortcutExtendor  = '';
	public $shortutTraitName  = 'PoesisModelShortcut';
	public $classNameSuffix   = '';
	
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