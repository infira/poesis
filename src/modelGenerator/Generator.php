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
	public  $dbTablesMethods = '';
	private $installFolder;
	private $DbName;
	
	/**
	 * @var \Infira\Poesis\Connection
	 */
	private $Con;
	
	/**
	 * @var Options
	 */
	private $Options;
	
	function __construct($installFolder, Connection $Con = null, Options $Options = null)
	{
		$this->installFolder = Fix::dirPath($installFolder);
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
		$Output            = new \stdClass();
		$Output->files     = [];
		$Output->error     = '';
		$model             = new Model(null, '::modelGenerator');
		$notAllowedMethods = get_class_methods($model);
		
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
						//debug($tableName);
						$tablesData[$tableName]->columns[] = $columnInfo;
						if (in_array($columnInfo['Field'], $notAllowedMethods))
						{
							$Output->error = 'Column <strong>' . $tableName . '.' . $columnInfo['Field'] . '</strong> is system reserverd';
							
							return $Output;
						}
					}
				}
			}
			
			if (!checkArray($tablesData))
			{
				return 'No table to generate';
			}
			foreach ($tablesData as $tableName => $Table)
			{
				$className = Poesis::getModelClassNameFirstLetter() . $this->Options->classNameSuffix . $this->constructClassName($tableName);
				
				
				$templateVars              = [];
				$templateVars["tableName"] = $tableName;
				$templateVars["className"] = $className;
				
				$templateVars["isView"]   = ($Table->Table_type == "VIEW") ? "true" : "false";
				$templateVars["aiColumn"] = Poesis::UNDEFINED;
				
				$templateVars["autoAssistProperty"]          = '';
				$templateVars["columnMethods"]               = '';
				$templateVars["primaryColumns"]              = '[]';
				$templateVars["constructorParameter"]        = [];
				$templateVars["constructorParameterComment"] = [];
				
				$templateVars["modelExtendors"] = [];
				if ($this->Options->modelHasExtendors($className))
				{
					foreach ($this->Options->godelHasExtendors($className) as $extendor)
					{
						$templateVars["modelExtendors"][] = "use $extendor;";
					}
				}
				$templateVars["modelExtendors"] = join("\n", $templateVars["modelExtendors"]);
				
				$templateVars["constructorParameterSetIf"] = '';
				$primaryColumns                            = [];
				$this->Con->dr("SHOW INDEX FROM `$tableName` WHERE Key_name = 'PRIMARY'")->each(function ($Index) use (&$primaryColumns)
				{
					$primaryColumns[] = "'" . $Index->Column_name . "'";
				});
				if (checkArray($primaryColumns))
				{
					$templateVars["primaryColumns"] = "[" . join(",", $primaryColumns) . "]";
				}
				$templateVars["columnTypes"] = '';
				$templateVars["columnNames"] = '';
				
				$newClassName = '\\' . $className;
				if ($this->Options->shortcutNamespace)
				{
					$newClassName = $this->Options->shortcutNamespace . $className;
				}
				$this->dbTablesMethods .= '
	/**
	 * Method to return ' . $newClassName . ' class
	 * @return ' . $newClassName . '|$this
	 */
	public static function ' . $className . '()
	{
		return new ' . $newClassName . '();
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
					
					if ($Column["Extra"] == "auto_increment" and $templateVars["aiColumn"] === Poesis::UNDEFINED)
					{
						$templateVars["aiColumn"] = "'" . $columnName . "'";
					}
					
					if ($isAi)
					{
						$templateVars["constructorParameter"][]        = 'int $' . $columnName . ' = null';
						$templateVars["constructorParameterComment"][] = sprintf('@param int|null $%s - set primary column as where if $ID > 0', $columnName);
						$templateVars["constructorParameterComment"][] = "\n     * @throws Error";
						
						//constructorParameterSetIf
						$templateVars["constructorParameterSetIf"] .= Variable::assign(["column" => $columnName], '
		if ($%column% !== NULL)
		{
			if ($%column% > 0)
			{
				$this->Where->%column%($ID);
			}
		}
		');
					
					}
				} //EOF each columns
				
				$templateVars["constructorParameter"][]        = 'array $options = []';
				$templateVars["constructorParameterComment"][] = '@param array $options = []';
				
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
				
				if ($templateVars["aiColumn"] === Poesis::UNDEFINED)
				{
					$templateVars["aiColumn"] = "null";
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
				$templateVars['columnTypes']                 = ltrim($templateVars['columnTypes']);
				$templateVars['constructorParameter']        = join(", ", $templateVars['constructorParameter']);
				$templateVars['constructorParameterComment'] = join("\n", $templateVars['constructorParameterComment']);
				
				$templateVars['schema']                                          = $this->getContent("ModelSchemaTemplate.txt", $templateVars);
				$Output->files[$className . '.' . $this->Options->fileExtension] = $this->getContent("ModelTemplate.txt", $templateVars);
			}
		}
		
		return $Output;
	}
	
	public function generate()
	{
		$Make   = $this->makeTableClassFiles();
		$output = '';
		if ($Make->error)
		{
			exit('<font style=";color:red">' . $Make->error . '</font>');
		}
		else
		{
			Dir::flush($this->installFolder, ['PoesisModelShortcut' . $this->Options->classNameSuffix . '.' . $this->Options->fileExtension]);
			foreach ($Make->files as $file => $content)
			{
				$output .= $this->makeFile($file, $content);
			}
		}
		
		$vars         = [];
		$vars['body'] = '';
		if ($this->Options->shortcutExtendor)
		{
			$vars['body'] .= 'use ' . $this->Options->shortcutExtendor . ';' . NL;
		}
		$vars['body']         .= $this->dbTablesMethods;
		$vars['shortcutName'] = $this->Options->shortutTraitName;
		$vars['useNamespace'] = '';
		if ($this->Options->shortcutNamespace)
		{
			$vars['useNamespace'] = 'use ' . $this->Options->shortcutNamespace . ';';
		}
		$tempalte = Variable::assign($vars, $this->getContent("ModelShortcut_Template.txt"));
		$output   .= $this->makeFile($this->Options->shortutTraitName . '.' . $this->Options->traitFileExtension, $tempalte);
		
		return $output;
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
}

?>