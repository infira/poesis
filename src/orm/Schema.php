<?php

namespace Infira\Poesis\orm;

use Infira\Utils\Variable;
use Infira\Poesis\Poesis;

/**
 * A class to provide simple db query functions, update,insert,delete, aso.
 */
class Schema
{
	protected $fields;
	protected $tableName;
	protected $className;
	protected $primaryFields;
	protected $aiField;
	protected $isView;
	protected $fieldStructure;
	
	/**
	 * Get alla the fields
	 *
	 * @return array
	 */
	public function getFields(): array
	{
		return $this->fields;
	}
	
	/**
	 * Get table databse table name
	 *
	 * @return string
	 */
	public function getTableName()
	{
		return $this->tableName;
	}
	
	/**
	 * Get table databse class name
	 *
	 * @return string
	 */
	public function getClassName()
	{
		return $this->className;
	}
	
	/**
	 * Get table databse class object
	 *
	 * @return Model
	 */
	public function getClassObject()
	{
		$className = $this->className;
		
		return new $className();
	}
	
	/**
	 * Has any primary fields
	 *
	 * @return string
	 */
	public function hasPrimaryFields()
	{
		return (count($this->primaryFields) > 0);
	}
	
	/**
	 * Get table primary field name
	 *
	 * @return string
	 */
	public function getPrimaryFields()
	{
		return $this->primaryFields;
	}
	
	/**
	 * Does structure has auto increment field
	 *
	 * @return bool
	 */
	public function hasAIField()
	{
		return (empty($this->aiField)) ? false : true;
	}
	
	/**
	 * Get auto increment field name
	 *
	 * @return bool|string
	 */
	public function getAIField()
	{
		return $this->aiField;
	}
	
	/**
	 * Check if the tabele is view
	 *
	 * @return bool
	 */
	public function isView()
	{
		return $this->isView;
	}
	
	
	public function getTypes()
	{
		return $this->data->fieldInfo;
	}
	
	/**
	 * Get field info
	 *
	 * @param string $field
	 * @return array
	 */
	public function getFieldStructure(string $field): array
	{
		return $this->fieldStructure[$field];
	}
	
	/**
	 * Get field info
	 *
	 * @param string $field
	 * @param string $type
	 * @return mixed
	 */
	public function getFieldStructureEntity(string $field, string $type)
	{
		return $this->getFieldStructure($field)[$type];
	}
	
	/**
	 * Get field $typeName
	 *
	 * @param string $field
	 * @return string
	 */
	public function getType(string $field): string
	{
		return Variable::toLower($this->getFieldStructureEntity($field, "type"));
	}
	
	/**
	 * Get field length
	 *
	 * @param string $field
	 * @return int|array
	 */
	public function getLength(string $field)
	{
		return $this->getFieldStructureEntity($field, "length");
	}
	
	/**
	 * Get number decimal precision
	 *
	 * @param string $field
	 * @return int
	 */
	public function getRoundPrecision(string $field)
	{
		return $this->getLength($field)['p'];
	}
	
	
	/**
	 * Round to correct length
	 *
	 * @param string $field
	 * @param mixed  $value
	 * @return mixed
	 */
	public function round(string $field, $value)
	{
		$p = $this->getFieldStructureEntity($field, "length")['p'];
		
		return round($value, $p);
	}
	
	/**
	 * Get field default value
	 *
	 * @param string $field
	 * @return mixed
	 */
	public function getDefaultValue(string $field)
	{
		return $this->getFieldStructureEntity($field, "default");
	}
	
	/**
	 * Get field $typeName
	 *
	 * @param string $field
	 * @return array
	 */
	public function getAllowedValues(string $field): array
	{
		return $this->getFieldStructureEntity($field, "allowedValues");
	}
	
	/**
	 * Is $field null value allowed
	 *
	 * @param string $field
	 * @return bool
	 */
	public function isNullAllowed(string $field): bool
	{
		return $this->getFieldStructureEntity($field, "isNull");
	}
	
	/**
	 * Is $field unsigned
	 *
	 * @param string $field
	 * @return bool
	 */
	public function isSigned(string $field): bool
	{
		return $this->getFieldStructureEntity($field, "signed");
	}
	
	/**
	 * Is $field a auto increment field
	 *
	 * @param string $field
	 * @return bool
	 */
	public function isAI(string $field): bool
	{
		if ($this->fieldExists($field))
		{
			return false;
		}
		
		return $this->getFieldStructureEntity($field, "isAI");
	}
	
	/**
	 * Get table primari field name
	 *
	 * @return ArrayListNode
	 */
	public function getFieldNamesArrayListNode()
	{
		$arr = [];
		foreach ($this->getFields() as $fieldName)
		{
			$arr[$fieldName] = "";
		}
		
		return new ArrayListNode($arr);
	}
	
	
	/**
	 * Get table primari field name
	 *
	 * @return \stdClass
	 */
	public function getFieldNamesObject($defaultValues = [])
	{
		$obj = new \stdClass();
		foreach ($this->getFields() as $fieldName)
		{
			$obj->$fieldName = "";
			if (isset($defaultValues[$fieldName]))
			{
				$obj->$fieldName = $defaultValues[$fieldName];
			}
		}
		
		return $obj;
	}
	
	/**
	 * Alerts when fields does not exist
	 *
	 * @param string $field
	 */
	public function checkField(string $field): bool
	{
		if (!$this->isRawField($field) and !in_array($field, $this->fields))
		{
			Poesis::error('DbOrm field <B>"' . $this->getTableName() . '.' . $field . '</B>" does not exists');
		}
		
		return true;
	}
	
	
	/**
	 * Check if the field exits in table class
	 *
	 * @param string $field
	 * @return bool
	 */
	public function fieldExists(string $field): bool
	{
		if ($this->isRawField($field))
		{
			return true;
		}
		
		return in_array($field, $this->fields);
	}
	
	/**
	 * Check is field raw query field
	 *
	 * @param string $field
	 * @return bool
	 */
	public function isRawField(string $field): bool
	{
		return (substr($field, 0, 21) == QueryCompiler::RAW_QUERY_FIELD);
	}
	
	
	/**
	 * Get table databse with $field name init
	 *
	 * @param string $field
	 * @return string
	 */
	public function getTableField(string $field): string
	{
		return $this->tableName . "." . $field;
	}
}

?>