<?php

namespace Infira\Poesis\modelGenerator;

use Infira\Utils\File;
use Infira\Utils\Regex;
use Infira\Utils\Dir;
use Infira\Utils\Variable;
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
				$templateVars["TIDColumn"] = 'null';
				if (Poesis::isTIDEnabled() and isset($Table->columns[$this->Options->getModelTIDColumnName($className)]))
				{
					$templateVars["TIDColumn"] = "'" . $this->Options->getModelTIDColumnName($className) . "'";
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
				if ($primaryColumns)
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
					$type       = Variable::toLower(preg_replace('/\(.*\)/m', '', $Column['Type']));
					$type       = strtolower(trim(str_replace("unsigned", "", $type)));
					$key++;
					if (($key + 1) == $count)
					{
						$isLast = true;
					}
					
					$rep               = [];
					$rep["varchar"]    = "string";
					$rep["char"]       = "string";
					$rep["tinytext"]   = "string";
					$rep["mediumtext"] = "string";
					$rep["text"]       = "string";
					$rep["longtext"]   = "string";
					
					$rep["smallint"]  = "integer";
					$rep["tinyint"]   = "integer";
					$rep["mediumint"] = "integer";
					$rep["int"]       = "integer";
					$rep["bigint"]    = "integer";
					
					$rep["year"]      = "integer";
					$rep["timestamp"] = "integer|string";
					$rep["enum"]      = "string";
					$rep["set"]       = "string|array";
					$rep["serial"]    = "string";
					$rep["datetime"]  = "string";
					$rep["date"]      = "string";
					$rep["float"]     = "float";
					$rep["decimal"]   = "float";
					$rep["double"]    = "float";
					$rep["real"]      = "float";
					if (!isset($rep[$type]))
					{
						$commentTypes = 'mixed';
					}
					else
					{
						$commentTypes = $rep[$type];
					}
					$commentTypes .= '|Field';
					
					$columnParmType         = explode('|', $commentTypes)[0];
					$pos                    = [];
					$pos['int']             = 'int';
					$pos['decimal,float,,'] = 'float';
					$Column["Comment"]      = $Column['Type'];
					$Desc                   = (isset($Column["Comment"]) && $Column["Comment"]) ? ' - ' . $Column["Comment"] . '' : '';
					
					$templateVars["autoAssistProperty"] .= '
 * @property %modelColumnClassLastName% $' . $columnName . ' ' . $columnParmType . $Desc;
					
					
					$templateVars["columnMethods"] .= '
	/**
	 * Set value for ' . $columnName . '
	 * @param ' . $commentTypes . ' $' . $columnParmType . ' - ' . $Column['Type'] . '
	 * @return ' . $className . '
	 */
	public function ' . $columnName . '($' . $columnParmType . '): ' . $className . '
	{
		return $this->add(\'' . $columnName . '\', $' . $columnParmType . ');
	}';
					
					$templateVars["nodeProperties"] .= '
    public $' . $columnName . ';';
					
					
					$columnCommentParam[$columnName] = '* @param ' . $columnParmType . ' $' . $columnName;
					$templateVars["columnNames"]     .= "'" . $columnName . "'" . ((!$isLast) ? ',' : '');
					$tableColumns[$columnName]       = true;
					
					
					$isInt    = (strpos($type, "int") !== false);
					$isNumber = (in_array($type, ["decimal", "float", "real", "double"]));
					
					$allowedValues = '';
					$length        = "null";
					if (strpos($Column['Type'], "enum") !== false)
					{
						$allowedValues = str_replace(["enum", "(", ")"], "", $Column['Type']);
					}
					elseif (strpos($Column['Type'], "set") !== false)
					{
						$allowedValues = str_replace(["set", "(", ")"], "", $Column['Type']);
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
					
					$isAi   = $Column["Extra"] == "auto_increment";
					$isNull = $Column["Null"] == "YES";
					
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
					return count($var) > 1;
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
	 * @return $className
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
				
				$templateVars['node']             = self::REMOVE_EMPTY_LINE;
				$templateVars['dataMethodsClass'] = $this->Options->getModelDataMethodsExtendor($templateVars['className']);
				
				if ($this->Options->getModelMakeNode($className))
				{
					$templateVars['nodeClassName'] = $this->Options->getModelNamespace() ? $this->Options->getModelNamespace() . '\\' . $className . 'Node' : $className . 'DataNode';
					
					$templateVars['nodeExtendor'] = $this->Options->getModelNodeExtendor($className);
					$templateVars["nodeTraits"]   = self::REMOVE_EMPTY_LINE;
					
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
				
				$templateVars['dataMethods']                = $this->getModelDataMethodsClassContent($templateVars);
				$templateVars['modelDefaultConnectionName'] = $this->Options->getModelDefaultConnectionName();
				$templateVars['modelNewClass']              = $this->Options->getModelNamespace() ? '\\' . $this->Options->getModelNamespace() . '\\' . $className : '\\' . $className;
				$templateVars['dbName']                     = $this->DbName;
				$templateVars['modelColumnClassName']       = $this->Options->getModelColumnClass();
				$templateVars['loggerEnabled']              = $this->Options->isModelLogEnabled($className) ? 'true' : 'false';
				$templateVars['useModelColumnClass']        = $templateVars['modelColumnClassName'][0] == '\\' ? substr($templateVars['modelColumnClassName'], 1) : $templateVars['modelColumnClassName'];
				$ex                                         = explode('\\', $templateVars['modelColumnClassName']);
				$templateVars['modelColumnClassLastName']   = end($ex);
				
				$collectedFiles[$className . '.' . $this->Options->getModelFileNameExtension()] = $this->getContent("ModelTemplate.txt", $templateVars);
			}
		}
		//actually collect files
		Dir::flushExcept($installPath, [$this->getShortcutTraitFileName(), 'dummy.txt']);
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
		
		if (!$makeOptions = $this->Options->getModelMakeDataMethods($vars['className']))
		{
			return self::REMOVE_EMPTY_LINE;
		}
		$vars['createNodeClassArguments'] = '$constructorArguments';
		if ($makeOptions['createNodeConstructorParams'])
		{
			$vars['createNodeClassArguments'] = 'array_merge([' . join(',', $makeOptions['createNodeConstructorParams']) . '],$constructorArguments)';
		}
		
		$vars['dataMethodsClassName'] = $vars['dataMethodsClass'] = $vars['className'] . 'DataMethods';
		$vars['createNodeClassName']  = $makeOptions['createNodeClassName'] ?: '\\' . $vars['nodeClassName'];
		$vars['dataMethodsTraits']    = self::REMOVE_EMPTY_LINE;
		if ($trTraits = $this->Options->getModelDataMethodsTraits($vars['className']))
		{
			foreach ($trTraits as $key => $extendor)
			{
				$trTraits[$key] = "use $extendor;";
			}
			$vars["dataMethodsTraits"] = join("\n", $trTraits);
		}
		
		return str_repeat("\n", 1) . $this->getContent("ModelDataMethodsTemplate.txt", $vars);
	}
	
	/**
	 * @param string $installPath - where to install models
	 * @return array - array of maked model files
	 */
	public function generate(string $installPath): array
	{
		$installPath = Dir::fixPath($installPath);
		if (!is_dir($installPath))
		{
			Poesis::error('Install path not found');
		}
		if (!is_writable($installPath))
		{
			Poesis::error('Install path not iwritable');
		}
		$makedFiles   = $this->makeTableClassFiles($installPath);
		$vars         = [];
		$vars['body'] = '';
		if ($traits = $this->Options->getShortcutTraits())
		{
			foreach ($traits as $trait)
			{
				$vars['body'] .= 'use ' . $trait . ';' . "\n";
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
	
	private function getContent($file, $vars = null): string
	{
		$file = realpath(dirname(__FILE__)) . "/" . $file;
		if (!file_exists($file))
		{
			Poesis::error("Installer $file not found");
		}
		$con = File::getContent($file);
		if ($vars)
		{
			return Variable::assign($vars, $con);
		}
		
		return $con;
	}
	
	private function getShortcutTraitFileName(): string
	{
		return $this->Options->getShortutTraitName() . '.' . $this->Options->getShortcutTraitFileNameExtension();
	}
}