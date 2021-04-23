<?php

namespace Infira\Poesis\modelGenerator;

use Infira\Utils\File;
use Infira\Utils\Regex;
use Infira\Utils\Dir;
use Infira\Utils\Variable;
use Infira\Utils\Fix;
use Infira\Poesis\Poesis;
use Infira\Poesis\ConnectionManager;
use Infira\Poesis\Connection;
use Infira\Poesis\orm\Model;

class Generator
{
	const REMOVE_EMPTY_LINE = '[REMOVE_EMPTY_LINE]';
	public  $dbTablesMethods = '';
	private $DbName;
	
	/**
	 * @var \Infira\Poesis\Connection
	 */
	private $Con;
	
	/**
	 * @var Options
	 */
	private $Options;
	
	function __construct(Connection $Con = null, Options $Options = null)
	{
		if ($Con === null)
		{
			$Con = ConnectionManager::default();
		}
		$this->Con = $Con;
		
		
		if ($Options === null)
		{
			$Options = new Options();
		}
		
		$this->DbName  = $this->Con->getDbName();
		$this->Options = $Options;
	}
	
	/**
	 * PRivate method to construct a php class name by database table name
	 *
	 * @param string $tableName
	 * @return string
	 */
	private function constructClassName(string $tableName): string
	{
		
		// -|_
		if (Regex::getMatch('/-|_/', $tableName))
		{
			$ex        = preg_split('/-|_/', $tableName);
			$tableName = "";
			foreach ($ex as $part)
			{
				$tableName .= ucfirst($part);
			}
		}
		
		return $this->Options->getModelClassNamePrefix() . ucfirst($tableName);
	}
	
	private function makeFile(string $fileName, $content): string
	{
		File::delete($fileName);
		$newLines = [];
		foreach (explode("\n", $content) as $line)
		{
			if (strpos($line, self::REMOVE_EMPTY_LINE) === false)
			{
				$newLines[] = $line;
			}
		}
		File::create($fileName, join("\n", $newLines), "w+", 0777);
		
		return $fileName;
	}
	
	private function makeTableClassFiles(string $installPath): array
	{
		$collectedFiles    = [];
		$model             = new Model(['isGenerator' => true]);
		$notAllowedColumns = get_class_methods($model);
		
		$tables = $this->Con->query("SHOW FULL TABLES");
		if ($tables)
		{
			$tablesData = [];
			while ($Row = $tables->fetch_object())
			{
				$columnName = "Tables_in_" . $this->DbName;
				$tableName  = $Row->$columnName;
				if ($this->Options->_isTableOk($tableName))
				{
					unset($Row->$columnName);
					unset($dbName);
					$colunmnsRes = $this->Con->query("SHOW FULL COLUMNS FROM`" . $tableName . '`');
					
					if (!isset($tablesData[$tableName]))
					{
						$Table                  = $Row;
						$Table->columns         = [];
						$tablesData[$tableName] = $Table;
					}
					
					while ($columnInfo = $colunmnsRes->fetch_array(MYSQLI_ASSOC))
					{
						$tablesData[$tableName]->columns[$columnInfo['Field']] = $columnInfo;
						if (in_array($columnInfo['Field'], $notAllowedColumns))
						{
							Poesis::error('Column <strong>' . $tableName . '.' . $columnInfo['Field'] . '</strong> is system reserverd');
						}
					}
				}
			}
			
			foreach ($tablesData as $tableName => $Table)
			{
				$className = $this->constructClassName($tableName);
				
				$templateVars              = [];
				$templateVars["tableName"] = $tableName;
				$templateVars["className"] = $className;
				
				$templateVars["isView"]    = ($Table->Table_type == "VIEW") ? "true" : "false";
				$templateVars["aiColumn"]  = 'null';
				$templateVars["TIDColumn"] = 'false';
				if ($tableName == 'all_fields_dup')
				{
					debug($Table->columns);
				}
				if (Poesis::isTIDEnabled() and isset($Table->columns['TID']))
				{
					$templateVars["TIDColumn"] = 'true';
				}
				
				$templateVars["autoAssistProperty"] = self::REMOVE_EMPTY_LINE;
				$templateVars["nodeProperties"]     = '';
				$templateVars["columnMethods"]      = '';
				$templateVars["primaryColumns"]     = '[]';
				
				$templateVars["modelTraits"] = self::REMOVE_EMPTY_LINE;
				$modelTraits                 = $this->Options->getModelTraits($className);
				if ($modelTraits)
				{
					foreach ($modelTraits as $key => $extendor)
					{
						$modelTraits[$key] = "use $extendor;";
					}
					$templateVars["modelTraits"] = join("\n", $modelTraits);
				}
				
				$primaryColumns = [];
				$this->Con->dr("SHOW INDEX FROM `$tableName` WHERE Key_name = 'PRIMARY'")->each(function ($Index) use (&$primaryColumns)
				{
					$primaryColumns[] = "'" . $Index->Column_name . "'";
				});
				if (checkArray($primaryColumns))
				{
					$templateVars["primaryColumns"] = "[" . join(",", $primaryColumns) . "]";
				}
				$templateVars["columnTypes"]   = '';
				$templateVars["columnNames"]   = '';
				$templateVars['modelExtendor'] = $this->Options->getModelExtendor($className);
				
				$newClassName = '\\' . $className;
				if ($this->Options->getModelNamespace())
				{
					$mns = $this->Options->getModelNamespace();
					if (substr($mns, -1) != '\\')
					{
						$mns .= '\\';
					}
					$newClassName = '\\' . $mns . $className;
				}
				$this->dbTablesMethods .= '
	/**
	 * Method to return ' . $newClassName . ' class
	 * @param array $options = []
	 * @return ' . $newClassName . '|$this
	 */
	public static function ' . $className . '(array $options = [])
	{
		return new ' . $newClassName . '($options);
	}
				' . "\n";
				
				$isLast             = false;
				$count              = count($Table->columns);
				$key                = -1;
				$columnCommentParam = [];
				$tableColumns       = [];
				
				foreach ($Table->columns as $Column)
				{
					$columnName = $Column['Field'];
					$key++;
					if (($key + 1) == $count)
					{
						$isLast = true;
					}
					$columnParmType                   = preg_replace('/\\(.*\\)/', '', $Column['Type']);
					$rep                              = [" unsigned" => ""];
					$columnParmType                   = str_replace(array_keys($rep), array_values($rep), $columnParmType);
					$pos                              = [];
					$pos['int']                       = 'int';
					$pos['decimal,float,double,real'] = 'float';
					$found                            = false;
					foreach ($pos as $needles => $final)
					{
						foreach (Variable::toArray($needles) as $needle)
						{
							if (strpos(Variable::toLower($columnParmType), $needle) !== false)
							{
								$columnParmType = $final;
								$found          = true;
								break;
							}
						}
						if ($found)
						{
							break;
						}
					}
					if (!$found)
					{
						$columnParmType = 'string';
					}
					$rep["varchar"]     = "string";
					$rep["char"]        = "string";
					$rep["longtext"]    = "string";
					$rep["mediumtext"]  = "string";
					$rep["text"]        = "string";
					$rep["bigint"]      = "integer";
					$rep["tinyint"]     = "integer";
					$rep["int"]         = "integer";
					$rep["integereger"] = "integer";
					$rep["timestamp"]   = "string|integer";
					$rep["enum"]        = "string";
					$rep["serial"]      = "string";
					$rep["decimal"]     = "float";
					$rep["datetime"]    = "string";
					$rep["date"]        = "string";
					$Column["Comment"]  = $Column['Type'];
					$Desc               = (isset($Column["Comment"]) && $Column["Comment"]) ? ' - ' . $Column["Comment"] . '' : '';
					
					if (!Poesis::isTIDEnabled() or (Poesis::isTIDEnabled() and $columnName != 'TID'))
					{
						$templateVars["autoAssistProperty"] .= '
 * @property ModelColumn $' . $columnName . ' ' . $columnParmType . $Desc;
						
						
						$templateVars["columnMethods"] .= '
	/**
	 * Set value for ' . $columnName . '
	 * @param ' . $columnParmType . '|object $' . $columnParmType . ' - ' . $Column['Type'] . '
	 * @return $this
	 */
	public function ' . $columnName . '($' . $columnParmType . '): ' . $className . '
	{
		return $this->add(\'' . $columnName . '\', $' . $columnParmType . ');
	}

';
					}
					
					$templateVars["nodeProperties"] .= '
    public $' . $columnName . ';';
					
					
					$columnCommentParam[$columnName] = '* @param ' . $columnParmType . ' $' . $columnName;
					$templateVars["columnNames"]     .= "'" . $columnName . "'" . ((!$isLast) ? ',' : '');
					$tableColumns[$columnName]       = true;
					
					$type = Variable::toLower(preg_replace('/\(.*\)/m', '', $Column['Type']));
					$type = strtolower(trim(str_replace("unsigned", "", $type)));
					
					$isInt    = (strpos($type, "int") !== false);
					$isNumber = (in_array($type, ["decimal", "float", "real", "double"]));
					
					$allowedValues = '';
					$length        = "null";
					if (strpos($Column['Type'], "enum") !== false)
					{
						$allowedValues = str_replace(["enum", "(", ")"], "", $Column['Type']);
					}
					else
					{
						if (strpos($Column['Type'], "("))
						{
							$length = str_replace(['(', ',', ')'], ['', '.', ''], Regex::getMatch('/\((.*)\)/m', $Column['Type']));
							if ($isNumber)
							{
								$ex     = explode(".", $length);
								$length = '[\'d\'=>' . $ex[0] . ',\'p\'=>' . $ex[1] . ',\'fd\'=>' . ($ex[0] - $ex[1]) . ']';
							}
						}
					}
					
					$isAi   = ($Column["Extra"] == "auto_increment") ? true : false;
					$isNull = ($Column["Null"] == "YES") ? true : false;
					
					if ($isAi)
					{
						$default = "''";
					}
					elseif ($isInt or $isNumber)
					{
						$default = ($Column['Default'] === null) ? 'Poesis::NONE' : addslashes($Column['Default']);
					}
					else
					{
						if ($Column['Default'] === null and $isNull)
						{
							$default = 'NULL';
						}
						elseif ($Column['Default'] === null)
						{
							$default = 'Poesis::NONE';
						}
						elseif ($Column['Default'] == "''")
						{
							$default = "''";
						}
						else
						{
							$default = "'" . addslashes($Column['Default']) . "'";
						}
						
					}
					if (in_array($type, ['timestamp', 'date', 'datetime']))
					{
						$length = intval($length);
					}
					
					$vars                        = [];
					$vars["fn"]                  = $columnName;
					$vars["t"]                   = $type;
					$vars["sig"]                 = (strpos(strtolower($Column['Type']), "unsigned") !== false) ? "FALSE" : "TRUE";
					$vars["len"]                 = $length;
					$vars["def"]                 = $default;
					$vars["aw"]                  = $allowedValues;
					$vars["in"]                  = ($isNull) ? "TRUE" : "FALSE";
					$vars["isAi"]                = ($isAi) ? "TRUE" : "FALSE";//isAuto Increment
					$templateVars["columnTypes"] .= '
		' . Variable::assign($vars, 'self::$columnStructure[' . "'%fn%'] = ['type'=>'%t%','signed'=>%sig%,'length'=>%len%,'default'=>%def%,'allowedValues'=>[%aw%],'isNull'=>%in%,'isAI'=>%isAi%];");
					
					if ($Column["Extra"] == "auto_increment")
					{
						$templateVars["aiColumn"] = "'" . $columnName . "'";
					}
				} //EOF each columns
				
				//make index methods
				$indexes = [];
				$this->Con->dr("SHOW INDEX FROM `$tableName`")->each(function ($Index) use (&$indexes)
				{
					$indexes[$Index->Key_name][] = $Index;
				});
				$indexMethods = array_filter($indexes, function ($var)
				{
					if (count($var) > 1)
					{
						return $var;
					}
				});
				foreach ($indexMethods as $indexName => $columns)
				{
					$columnComment   = [];
					$columnArguments = [];
					$columnCalles    = [];
					foreach ($columns as $Col)
					{
						$columnComment[]   = $Col->Column_name;
						$columnArguments[] = '$' . $Col->Column_name;
						$columnCalles[]    = '
		$this->add(\'' . $Col->Column_name . '\', $' . $Col->Column_name . ');';
					}
					$templateVars["columnMethods"] .= '
	/**
	 * Set value for ' . join(', ', $columnComment) . " index";
					foreach ($columns as $Col)
					{
						$templateVars["columnMethods"] .= '
	 ' . $columnCommentParam[$Col->Column_name];
					}
					$templateVars["columnMethods"] .= '
	 * @return ModelColumn|$this
	 */
	public function ' . $indexName . '_index(' . join(', ', $columnArguments) . ')
	{   ' . join('', $columnCalles) . '
	    return $this;
	}
';
				}
				
				$max = 0;
				foreach (explode("\n", $templateVars['columnTypes']) as $line)
				{
					$line = trim($line);
					$max  = max($max, strlen(substr($line, 0, strpos($line, '=') + 1)));
				}
				foreach (explode("\n", $templateVars['columnTypes']) as $line)
				{
					$line = trim($line);
					if ($line)
					{
						$b                           = substr($line, 0, strpos($line, '=') + 1);
						$len                         = strlen($b);
						$f                           = str_replace('=', str_pad(" ", ($max - $len) + 1) . '=', $b);
						$templateVars['columnTypes'] = str_replace($b, $f, $templateVars['columnTypes']);
					}
				}
				$templateVars['columnTypes'] = ltrim($templateVars['columnTypes']);
				
				$modelImports = [];
				foreach ($this->Options->getModelImports($className) as $ik => $name)
				{
					$modelImports[$ik] = "use $name;";
				}
				$templateVars['modelImports']   = $modelImports ? join("\n", $modelImports) : self::REMOVE_EMPTY_LINE;
				$templateVars['modelNamespace'] = self::REMOVE_EMPTY_LINE;
				if ($this->Options->getModelNamespace())
				{
					$templateVars['modelNamespace'] = 'namespace ' . $this->Options->getModelNamespace() . ';';
				}
				
				$templateVars['node']                       = self::REMOVE_EMPTY_LINE;
				$templateVars['modelReturnDataMethodsName'] = $this->Options->getModelDataMethodsExtendor($templateVars['className']);
				
				if ($makeOptions = $this->Options->getModelMakeNode($className))
				{
					$templateVars['nodeClassName'] = $this->Options->getModelNamespace() ? $this->Options->getModelNamespace() . '\\' . $className . 'Node' : $className . 'DataNode';
					$templateVars['nodeExtendor']  = $this->Options->getModelNodeExtendor($className);
					$templateVars["nodeTraits"]    = self::REMOVE_EMPTY_LINE;
					
					$nodeTraits = $this->Options->getModelNodeTraits($className);
					if ($nodeTraits)
					{
						foreach ($nodeTraits as $key => $extendor)
						{
							$nodeTraits[$key] = "use $extendor;";
						}
						$templateVars["nodeTraits"] = join("\n", $nodeTraits);
					}
					$templateVars['node'] .= str_repeat("\n", 3) . $this->getContent("ModelNodeTemplate.txt", $templateVars);
				}
				
				$templateVars['dataMethods']                                                    = $this->getModelDataMethodsClassContent($templateVars);
				$templateVars['modelDefaultConnectionName']                                     = $this->Options->getModelDefaultConnectionName();
				$collectedFiles[$className . '.' . $this->Options->getModelFileNameExtension()] = $this->getContent("ModelTemplate.txt", $templateVars);
			}
		}
		//actually collect files
		Dir::flush($installPath, [$this->getShortcutTraitFileName(), 'dummy.txt']);
		$makedFiles = [];
		foreach ($collectedFiles as $file => $content)
		{
			$makedFiles[] = $this->makeFile($installPath . $file, $content);
		}
		
		return $makedFiles;
	}
	
	private function getModelDataMethodsClassContent(array &$vars): string
	{
		$vars['dataMethodsExtendor'] = $this->Options->getModelDataMethodsExtendor($vars['className']);
		
		if (!$makeOptions = $this->Options->getModelMakeDataMethods($vars['className']))// and $vars['dataMethodsExtendor'] == '\Infira\Poesis\dr\ModelDataMethods')
		{
			return self::REMOVE_EMPTY_LINE;
		}
		$vars['createNodeClassArguments'] = '$constructorArguments';
		if ($makeOptions['createNodeConstructorParams'])
		{
			$vars['createNodeClassArguments'] = 'array_merge([' . join(',', $makeOptions['createNodeConstructorParams']) . '],$constructorArguments)';
		}
		
		$vars['dataMethodsClassName'] = $vars['modelReturnDataMethodsName'] = $vars['className'] . 'DataMethods';
		$vars['createNodeClassName']  = $makeOptions['createNodeClassName'] ? $makeOptions['createNodeClassName'] : '\\' . $vars['nodeClassName'];
		$vars['dataMethodsTraits']    = self::REMOVE_EMPTY_LINE;
		if ($trTraits = $this->Options->getModelDataMethodsTraits($vars['className']))
		{
			foreach ($trTraits as $key => $extendor)
			{
				$trTraits[$key] = "use $extendor;";
			}
			$vars["dataMethodsTraits"] = join("\n", $trTraits);
		}
		
		return str_repeat("\n", 3) . $this->getContent("ModelDataMethodsTemplate.txt", $vars);
	}
	
	/**
	 * @param string $installPath - where to install models
	 * @return array - array of maked model files
	 */
	public function generate(string $installPath): array
	{
		$installPath = Fix::dirPath($installPath);
		if (!is_dir($installPath))
		{
			Poesis::error('Install path not found');
		}
		if (!is_writable($installPath))
		{
			Poesis::error('Install path not iwritable');
		}
		$makedFiles = $this->makeTableClassFiles($installPath);;
		$vars         = [];
		$vars['body'] = '';
		if ($traits = $this->Options->getShortcutTraits())
		{
			foreach ($traits as $trait)
			{
				$vars['body'] .= 'use ' . $trait . ';' . NL;
			}
		}
		$vars['body']              .= $this->dbTablesMethods;
		$vars['shortcutName']      = $this->Options->getShortutTraitName();
		$vars['useNamespace']      = '';
		$vars['shortcutNamespace'] = self::REMOVE_EMPTY_LINE;
		if ($this->Options->getShortcutNamespace())
		{
			$vars['shortcutNamespace'] = 'namespace ' . $this->Options->getShortcutNamespace() . ';';
		}
		$tempalte     = Variable::assign($vars, $this->getContent("ModelShortcut_Template.txt"));
		$makedFiles[] = $this->makeFile($installPath . $this->getShortcutTraitFileName(), $tempalte);
		
		return $makedFiles;
	}
	
	private function getContent($file, $vars = null)
	{
		$file = realpath(dirname(__FILE__)) . "/" . $file;
		if (!file_exists($file))
		{
			Poesis::error("Installer $file not found");
		}
		else
		{
			$con = File::getContent($file);
			if ($vars)
			{
				return Variable::assign($vars, $con);
			}
			
			return $con;
		}
	}
	
	private function getShortcutTraitFileName(): string
	{
		return $this->Options->getShortutTraitName() . '.' . $this->Options->getShortcutTraitFileNameExtension();
	}
}

?>