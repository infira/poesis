<?php

namespace Infira\Poesis\generator;

use Infira\Utils\File;
use Infira\Utils\Regex;
use Infira\Poesis\Connection;
use Infira\Utils\Dir;
use Infira\Poesis\Poesis;
use Infira\Utils\Variable;
use Infira\Poesis\ConnectionManager;

class ModelGenerator
{
	public  $dbTablesMethods = [];
	private $installFolder   = "";
	private $DbName;
	
	private $dbClassNamePrefix = '';
	/**
	 * @var \Infira\Poesis\Connection
	 */
	private $Db;
	
	//Options
	public $voidTables = [];
	
	function __construct($installFolder, \Infira\Poesis\Connection $DbConnection = null, string $classNamePrefix = '')
	{
		$this->installFolder     = $installFolder;
		$this->dbClassNamePrefix = $classNamePrefix;
		if (!$DbConnection)
		{
			$DbConnection = ConnectionManager::default();
		}
		$this->Db     = $DbConnection;
		$this->DbName = $this->Db->getDbName();
	}
	
	/**
	 * Set tables to be voided in generation
	 *
	 * @param array $tables
	 */
	public function setVoidTables(array $tables)
	{
		$this->voidTables = $tables;
	}
	
	/**
	 * PRivate method to construct a php class name by database table name
	 *
	 * @param string $tableName
	 * @return string
	 */
	private function constructClassName($tableName)
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
		
		return ucfirst($tableName);
	}
	
	private function makeFile($fileName, $content)
	{
		$fn = $this->installFolder . $fileName;
		File::delete($fn);
		File::create($this->installFolder . $fileName, $content, "w+", 0777);
		
		return "Maked file $fn <br />";
	}
	
	/**
	 * Make databse tablse class handles
	 */
	private function makeTableClassFiles()
	{
		$Output          = new \stdClass();
		$Output->str     = "";
		$Output->isError = false;
		$tables          = $this->Db->query("SHOW FULL TABLES");
		if ($tables)
		{
			while ($Row = $tables->fetch_object())
			{
				$fieldName = "Tables_in_" . $this->DbName;
				$tableName = $Row->$fieldName;
				unset($Row->$fieldName);
				unset($fieldName);
				$fields = $this->Db->query("SHOW FULL COLUMNS FROM`" . $tableName . '`');
				
				if (!isset($tablesData[$tableName]))
				{
					$Table                  = $Row;
					$Table->fields          = [];
					$tablesData[$tableName] = $Table;
				}
				
				$fieldsArr = [];
				while ($fieldInfo = $fields->fetch_array(MYSQLI_ASSOC))
				{
					$tablesData[$tableName]->fields[] = $fieldInfo;
					if (in_array($fieldInfo['Field'], ["add", "delete", "set", "count"]))
					{
						$Output->isError = true;
						$Output->str     = "cant 't have <strong>" . $fieldInfo['Field'] . '</strong> as table(<strong>' . $tableName . '</strong>) field name, it\' system reserverd';
						
						return $Output;
					}
				}
			}
			
			foreach ($tablesData as $tableName => $Table)
			{
				if (!in_array($tableName, $this->voidTables))
				{
					$className = Poesis::getModelClassNameFirstLetter() . $this->dbClassNamePrefix . $this->constructClassName($tableName);
					
					
					$templateVars              = [];
					$templateVars["tableName"] = $tableName;
					$templateVars["className"] = $className;
					
					//$templateVars["createdField"] = "false";
					//$templateVars["modifiedField"] = "false";
					//$templateVars["transactionField"] = "false";
					
					
					$templateVars["isView"]  = ($Table->Table_type == "VIEW") ? "true" : "false";
					$templateVars["aiField"] = Poesis::UNDEFINED;
					
					$templateVars["autoAssistProperty"]          = "";
					$templateVars["fieldMethods"]                = "";
					$templateVars["primaryFields"]               = '[]';
					$templateVars["constructorParameter"]        = "";
					$templateVars["constructorParameterComment"] = "";
					$templateVars["constructorParameterSetIf"]   = "";
					$primFields                                  = [];
					$this->Db->dr("SHOW INDEX FROM `$tableName` WHERE Key_name = 'PRIMARY'")->each(function ($Index) use (&$primFields)
					{
						$primFields[] = "'" . $Index->Column_name . "'";
					});
					if (checkArray($primFields))
					{
						$templateVars["primaryFields"] = "[" . join(",", $primFields) . "]";
					}
					$templateVars["fieldTypesString"] = "";
					$templateVars["fieldNames"]       = "";
					
					if (!isset($this->dbTablesMethods["app"]))
					{
						$this->dbTablesMethods["app"]    = "";
						$this->dbTablesMethods["client"] = "";
					}
					$dbTablesMethodsKey = "app";
					if (substr($tableName, 0, 2) === "c_" or substr($tableName, 0, 4) === "c_v_" or substr($tableName, 0, 4) === "v_c_")
					{
						$dbTablesMethodsKey = "client";
					}
					$newClassName = '\\' . $className;
					if (Poesis::getModelShortcutUseNamespace())
					{
						$newClassName = Poesis::getModelShortcutUseNamespace() . $className;
					}
					$this->dbTablesMethods[$dbTablesMethodsKey] .= '
	/**
	 * Method to return ' . $newClassName . ' class
	 * @return ' . $newClassName . '|$this
	 */
	public static function ' . $className . '()
	{
		$output = new ' . $newClassName . '();
		$output->setAsFieldSequence();
		return $output;
	}
				' . "\n";
					
					$isLast             = false;
					$count              = count($Table->fields);
					$key                = -1;
					$fieldsCommentParam = [];
					$tableFields        = [];
					foreach ($Table->fields as $Field)
					{
						$key++;
						if (($key + 1) == $count)
						{
							$isLast = true;
						}
						$commentType        = preg_replace('/\\(.*\\)/', '', $Field['Type']);
						$rep                = [" unsigned" => ""];
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
						$commentType        = str_replace(array_keys($rep), array_values($rep), $commentType);
						$Desc               = (isset($Field["Comment"]) && $Field["Comment"]) ? '"' . $Field["Comment"] . '" : ' : "Field";
						
						$templateVars["autoAssistProperty"] .= '
 * @property Field $' . $Field['Field'] . ' ' . $commentType . ' - ' . $Desc;
						
						
						$templateVars["fieldMethods"] .= '
	/**
	 * Set value for ' . $Field['Field'] . '
	 * @param ' . $commentType . ' $value
	 * @return Field|$this
	 */
	public function ' . $Field['Field'] . '($value)
	{
		if ($this->fieldSequence)
		{
			$this->Fields->getField(\'' . $Field['Field'] . '\')->set($value);
			return $this;
		}
	    return $this->Fields->getField(\'' . $Field['Field'] . '\')->set($value);
	}

';
						
						$fieldsCommentParam[$Field['Field']] = '* @param ' . $commentType . ' $' . $Field['Field'];
						$templateVars["fieldNames"]          .= "'" . $Field['Field'] . "'" . ((!$isLast) ? ',' : '');
						$tableFields[$Field['Field']]        = true;
						
						$type = Variable::toLower(preg_replace('/\(.*\)/m', '', $Field['Type']));
						$type = strtolower(trim(str_replace("unsigned", "", $type)));
						
						$isInt    = (strpos($type, "int") !== false);
						$isNumber = (in_array($type, ["decimal", "float", "real", "double"]));
						
						$allowedValues = "";
						$length        = "null";
						if (strpos($Field['Type'], "enum") !== false)
						{
							$allowedValues = str_replace(["enum", "(", ")"], "", $Field['Type']);
						}
						else
						{
							if (strpos($Field['Type'], "("))
							{
								$length = str_replace(['(', ',', ')'], ['', '.', ''], Regex::getMatch('/\((.*)\)/m', $Field['Type']));
								if ($isNumber)
								{
									$ex     = explode(".", $length);
									$length = '[\'d\'=>' . $ex[0] . ',\'p\'=>' . $ex[1] . ',\'fd\'=>' . ($ex[0] - $ex[1]) . ']';
								}
							}
						}
						
						$isAi   = ($Field["Extra"] == "auto_increment") ? true : false;
						$isNull = ($Field["Null"] == "YES") ? true : false;
						
						if ($isAi)
						{
							$default = "''";
						}
						elseif ($isInt or $isNumber)
						{
							$default = ($Field['Default'] === null) ? 'Poesis::NONE' : $Field['Default'];
						}
						else
						{
							if ($Field['Default'] === null and $isNull)
							{
								$default = 'NULL';
							}
							elseif ($Field['Default'] === null)
							{
								$default = 'Poesis::NONE';
							}
							elseif ($Field['Default'] == "''")
							{
								$default = "''";
							}
							else
							{
								$default = "'" . $Field['Default'] . "'";
							}
							
						}
						if (in_array($type, ['timestamp', 'date', 'datetime']))
						{
							$length = intval($length);
						}
						
						$vars                             = [];
						$vars["fn"]                       = $Field['Field'];
						$vars["t"]                        = $type;
						$vars["sig"]                      = (strpos(strtolower($Field['Type']), "unsigned") !== false) ? "FALSE" : "TRUE";
						$vars["len"]                      = $length;
						$vars["def"]                      = $default;
						$vars["aw"]                       = $allowedValues;
						$vars["in"]                       = ($isNull) ? "TRUE" : "FALSE";
						$vars["isAi"]                     = ($isAi) ? "TRUE" : "FALSE";//isAuto Increment
						$templateVars["fieldTypesString"] .= '
		' . Variable::assign($vars, '$this->fieldStructure[' . "'%fn%'] = ['type'=>'%t%','signed'=>%sig%,'length'=>%len%,'default'=>%def%,'allowedValues'=>[%aw%],'isNull'=>%in%,'isAI'=>%isAi%];");
						
						if ($Field["Extra"] == "auto_increment" and $templateVars["aiField"] === Poesis::UNDEFINED)
						{
							$templateVars["aiField"] = "'" . $Field['Field'] . "'";
						}
						
						if ($isAi)
						{
							$templateVars["constructorParameter"]        .= '$' . $Field['Field'] . ' = 0';
							$templateVars["constructorParameterComment"] .= sprintf('
	/**
	 * %s constructor.
	 * @param int $%s - set primary field as where if $ID > 0
	 */', $className, $Field['Field']);
							
							//constructorParameterSetIf
							$templateVars["constructorParameterSetIf"] .= Variable::assign(["field" => $Field["Field"]], '
		if (is_int($%field%) or is_numeric($%field%))
		{
			if ($%field% > 0)
			{
				$this->Where->%field%($ID);
			}
		}
		else
		{
			Poesis::error(\'Cannot set %field% value no other than int\');
		}');
							
						}
						
						
						/*
						//transaction field
						if ($Field["Field"] == "TUID" AND $Field["Type"] == "char(36)" AND $Field["Collation"] == "ascii_general_ci")
						{
							if ($templateVars["transactionField"] !== "false" AND $templateVars["transactionField"] != $Field["Field"])
							{
								Poesis::error("you cannot have multiple TUID fields");
							}
							else
							{
								$templateVars["transactionField"] = $Field["Field"];
							}
						}
						
						
						//created field
						if ($Field["Type"] == "timestamp(6)" AND $Field["Default"] == "CURRENT_TIMESTAMP(6)" AND $Field["Extra"] == "")
						{
							if ($templateVars["createdField"] !== "false" AND $templateVars["createdField"] != $Field["Field"])
							{
								Poesis::error("you cannot have multiple created fields");
							}
							else
							{
								$templateVars["createdField"] = $Field["Field"];
							}
						}
						
						//modified field
						if ($Field["Type"] == "timestamp(6)" AND $Field["Default"] == "CURRENT_TIMESTAMP(6)" AND $Field["Extra"] == "on update CURRENT_TIMESTAMP(6)")
						{
							if ($templateVars["modifiedField"] !== "false" AND $templateVars["modifiedField"] != $Field["Field"])
							{
								Poesis::error("you cannot have multiple modified fields");
							}
							else
							{
								$templateVars["modifiedField"] = $Field["Field"];
							}
						}
						*/
						
						
					}; //EOF each fields
					
					//make index methods
					$indexes = [];
					$this->Db->dr("SHOW INDEX FROM `$tableName`")->each(function ($Index) use (&$indexes)
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
						$fieldsComment  = [];
						$fieldArguments = [];
						$fieldCallers   = [];
						foreach ($columns as $Col)
						{
							$fieldsComment[]  = $Col->Column_name;
							$fieldArguments[] = '$' . $Col->Column_name;
							$fieldCallers[]   = '
		$this->Fields->getField(\'' . $Col->Column_name . '\')->set($' . $Col->Column_name . ');';
						}
						$templateVars["fieldMethods"] .= '
	/**
	 * Set value for ' . join(', ', $fieldsComment);
						foreach ($columns as $Col)
						{
							$templateVars["fieldMethods"] .= '
	 ' . $fieldsCommentParam[$Col->Column_name];
						}
						$templateVars["fieldMethods"] .= '
	 * @return Field|$this
	 */
	public function ' . $indexName . '_index(' . join(', ', $fieldArguments) . ')
	{   ' . join('', $fieldCallers) . '
	    return $this;
	}
';
					}
					
					if ($templateVars["aiField"] === Poesis::UNDEFINED)
					{
						$templateVars["aiField"] = "false";
					}
					$max = 0;
					foreach (explode("\n", $templateVars['fieldTypesString']) as $line)
					{
						$line = trim($line);
						$max  = max($max, strlen(substr($line, 0, strpos($line, '=') + 1)));
					}
					foreach (explode("\n", $templateVars['fieldTypesString']) as $line)
					{
						$line = trim($line);
						if ($line)
						{
							$b                                = substr($line, 0, strpos($line, '=') + 1);
							$len                              = strlen($b);
							$f                                = str_replace('=', str_pad(" ", ($max - $len) + 1) . '=', $b);
							$templateVars['fieldTypesString'] = str_replace($b, $f, $templateVars['fieldTypesString']);
						}
					}
					$templateVars['fieldTypesString'] = ltrim($templateVars['fieldTypesString']);
					
					$templateVars['schema'] = $this->getContent("ModelSchemaTemplate.txt", $templateVars);
					//$this->makeFile($className . '.schema.php', $this->getContent("ModelSchemaTemplate.txt", $templateVars));
					$Output->str .= $this->makeFile($className . '.class.php', $this->getContent("ModelTemplate.txt", $templateVars));
				}
			}
		}
		
		return $Output;
	}
	
	public function generate()
	{
		Dir::flush($this->installFolder, ['PoesisModelShortcut' . $this->dbClassNamePrefix . '.class.php', 'TTemp.class.php', 'TSession.class.php', 'TTableCache.class.php', 'modelExtensions']);
		$output = $this->makeTableClassFiles();
		if ($output->isError)
		{
			exit('<font style=";color:red">' . $output->str . '</font>');
		}
		$output = $output->str;
		//ShortCut class
		
		$vars = [];
		
		$vars['methods']      = $this->dbTablesMethods["app"];
		$vars['suffix']       = $this->dbClassNamePrefix;
		$vars['useNamespace'] = '';
		if (Poesis::getModelShortcutUseNamespace())
		{
			$vars['useNamespace'] = 'use ' . Poesis::getModelShortcutUseNamespace() . ';';
		}
		$tempalte = Variable::assign($vars, $this->getContent("ModelShortcut_Template.txt"));
		$output   .= $this->makeFile("PoesisModelShortcut" . $this->dbClassNamePrefix . ".trait.php", $tempalte);
		
		return $output;
	}
	
	private function getContent($file, $vars = false)
	{
		$file = realpath(dirname(__FILE__)) . "/" . $file;
		if (!file_exists($file))
		{
			Poesis::error("Installer $file not found");
		}
		else
		{
			return Variable::assign($vars, File::getContent($file));
		}
	}
}

?>