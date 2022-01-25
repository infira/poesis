<?php

namespace Infira\Poesis\orm;

use Infira\Poesis\Poesis;
use Infira\Poesis\support\Expression;
use Infira\Poesis\orm\node\{LogicalOperator, Field};
use Infira\Poesis\support\RepoTrait;

/**
 * @property \Infira\Poesis\orm\Model *
 */
class ModelColumn
{
	use RepoTrait;
	private $column;
	private $table;
	private $schemaIndex;
	private $columnFunctions = [];
	private $valueFunctions  = [];
	private $comparison      = null;
	private $expressions     = [];
	private $valueParser     = [];
	
	public function __construct(string $column, string $table,  string $connectionName)
	{
		$this->column          = $column;
		$this->table           = $table;
		$this->schemaIndex     = "$table.$column";
		$this->connectionName  = $connectionName;
		$this->columnFunctions = [];
	}
	
	public function __toString()
	{
		Poesis::error("You cant use $this->column as value");
	}
	
	public function getColumn(): string
	{
		return $this->column;
	}
	
	protected function add(Field $field): ModelColumn
	{
		$field->setConnectionName($this->connectionName);
		$field->setSchemaIndex($this->table, $this->column);
		$field->setColumn($this->column);
		foreach ($this->columnFunctions as $f) {
			$field->addColumnFunction($f[0], $f[1]);
		}
		foreach ($this->valueFunctions as $f) {
			$field->addValueFunction($f[0], $f[1]);
		}
		
		$this->columnFunctions = [];
		if ($this->comparison === '!=') {
			if ($field->isPredicateType('between,like,in')) {
				$field->setComparsion('NOT ' . strtoupper($field->getPredicateType()));
			}
			elseif ($field->isPredicateType('null')) {
				$field->setComparsion('NOT');
			}
			else {
				$field->setComparsion('!=');
			}
		}
		elseif ($this->comparison !== null) {
			$field->setComparsion($this->comparison);
		}
		if (isset($this->valueParser)) {
			$value = $field->getValue();
			foreach ($this->valueParser as $parser) {
				$value = call_user_func_array($parser->parser, array_merge([$value], $parser->arguments));
			}
			$field->setValue($value);
		}
		$this->expressions[] = $field;
		
		return $this;
	}
	
	public final function __call($method, $arguments)
	{
		if ($method == 'select') {
			return $this->Model->$method(...$arguments);
		}
		Poesis::error('You are tring to call uncallable method <B>"' . $method . '</B>" it doesn\'t exits in ' . get_class($this) . ' class');
	}
	
	/**
	 * Adds a value parset what is called just before add value to collection
	 * $callback($value)
	 *
	 * @param callable $parser
	 * @param array    $arguments
	 * @return \Infira\Poesis\orm\ModelColumn
	 */
	public function addValueParser(callable $parser, array $arguments = []): ModelColumn
	{
		$this->valueParser[] = (object)['parser' => $parser, 'arguments' => $arguments];
		
		return $this;
	}
	
	//region operators
	
	/**
	 * Add logical XOR operator to query
	 *
	 * @return $this
	 */
	public function xor(): ModelColumn
	{
		return $this->addOperator(new LogicalOperator("XOR", $this->column));
	}
	
	/**
	 * Add logical OR operator to query
	 *
	 * @return $this
	 */
	public function or(): ModelColumn
	{
		return $this->addOperator(new LogicalOperator("OR", $this->column));
	}
	
	/**
	 * Add logical AND operator to query
	 *
	 * @return $this
	 */
	public function and(): ModelColumn
	{
		return $this->addOperator(new LogicalOperator("AND", $this->column));
	}
	
	/**
	 * @param LogicalOperator $op
	 * @return $this
	 */
	public function addOperator(LogicalOperator $op): ModelColumn
	{
		$this->expressions[] = $op;
		
		return $this;
	}
	
	/**
	 * Set comparison =, !=, >, <, ≥, ≤, <>, …etc.
	 *
	 * @param string $comparison
	 * @return $this
	 */
	public function com(string $comparison): ModelColumn
	{
		$this->comparison = $comparison;
		
		return $this;
	}
	
	/**
	 * Set not value
	 *
	 * @param mixed $value
	 * @return $this
	 */
	public function not($value = Poesis::UNDEFINED): ModelColumn
	{
		$this->com('!=');
		if ($value !== Poesis::UNDEFINED) {
			$this->value($value);
		}
		
		return $this;
	}
	
	//endregion
	
	public function value($value): ModelColumn
	{
		return $this->add(Expression::simpleValue($value));
	}
	
	public function setExpression(Field $field): ModelColumn
	{
		return $this->add($field);
	}
	
	public function notValue($value): ModelColumn
	{
		return $this->add(Expression::not($value));
	}
	
	//region raw values
	public function raw(string $rawValue): ModelColumn
	{
		return $this->add(Expression::raw($rawValue));
	}
	
	public function query(string $query): ModelColumn
	{
		return $this->add(Expression::query($query));
	}
	
	public function variable(string $varName): ModelColumn
	{
		return $this->add(Expression::variable($varName));
	}
	
	public function null(): ModelColumn
	{
		return $this->add(Expression::null());
	}
	
	public function column(string $column): ModelColumn
	{
		return $this->add(Expression::column($column));
	}
	
	public function now(): ModelColumn
	{
		return $this->add(Expression::now());
	}
	//endregion
	
	//region select,delete complex value EDIT IS NOT ALLOWED
	
	public function notNull(): ModelColumn
	{
		return $this->add(Expression::notNull());
	}
	
	public function notColumn(string $column): ModelColumn
	{
		return $this->add(Expression::notColumn($column));
	}
	
	public function in($values): ModelColumn
	{
		return $this->add(Expression::in($values));
	}
	
	public function notIn($values): ModelColumn
	{
		return $this->add(Expression::notIn($values));
	}
	
	public function inSubQuery(string $query): ModelColumn
	{
		return $this->add(Expression::inSubQuery($query));
	}
	
	public function notInSubQuery(string $query): ModelColumn
	{
		return $this->add(Expression::notInSubQuery($query));
	}
	
	public function biggerEq($value): ModelColumn
	{
		return $this->add(Expression::biggerEq($value));
	}
	
	public function smallerEq($value): ModelColumn
	{
		return $this->add(Expression::smallerEq($value));
	}
	
	public function bigger($value): ModelColumn
	{
		return $this->add(Expression::bigger($value));
	}
	
	public function smaller($value): ModelColumn
	{
		return $this->add(Expression::smaller($value));
		
	}
	
	public function notEmpty(): ModelColumn
	{
		return $this->add(Expression::notEmpty());
	}
	
	public function empty(): ModelColumn
	{
		return $this->add(Expression::empty());
	}
	
	public function between($value1, $value2): ModelColumn
	{
		return $this->add(Expression::between($value1, $value2));
	}
	
	public function notBetween($value1, $value2): ModelColumn
	{
		return $this->add(Expression::notBetween($value1, $value2));
	}
	
	public function betweenColumns(string $column1, string $column2): ModelColumn
	{
		return $this->add(Expression::betweenColumns($column1, $column2));
	}
	
	public function notBetweenColumns(string $column1, string $column2): ModelColumn
	{
		return $this->add(Expression::notBetweenColumns($column1, $column2));
	}
	
	public function like($value): ModelColumn
	{
		return $this->add(Expression::like($value));
	}
	
	public function likeP($value): ModelColumn
	{
		return $this->add(Expression::likeP($value));
	}
	
	public function notLike($value): ModelColumn
	{
		return $this->add(Expression::notLike($value));
	}
	
	public function notLikeP($value): ModelColumn
	{
		return $this->add(Expression::notLikeP($value));
	}
	
	public function rlike($value): ModelColumn
	{
		return $this->add(Expression::rlike($value));
	}
	
	public function notRlike($value): ModelColumn
	{
		return $this->add(Expression::rlike($value));
	}
	//endregion
	
	//region value modifiers
	public function md5($value): ModelColumn
	{
		return $this->add(Expression::md5($value));
	}
	
	public function password($value): ModelColumn
	{
		return $this->add(Expression::password($value));
	}
	
	public function compress($value): ModelColumn
	{
		return $this->add(Expression::compress($value));
	}
	
	public function increase($by): ModelColumn
	{
		return $this->add(Expression::increase($by));
	}
	
	public function decrease($by): ModelColumn
	{
		return $this->add(Expression::decrease($by));
	}
	
	public function json($value): ModelColumn
	{
		return $this->add(Expression::json($value));
	}
	
	public function serialize($value): ModelColumn
	{
		return $this->add(Expression::serialize($value));
	}
	
	public function time($time): ModelColumn
	{
		return $this->add(Expression::time($time));
	}
	
	public function date($date): ModelColumn
	{
		return $this->add(Expression::date($date));
	}
	
	public function dateTime($dateTime): ModelColumn
	{
		return $this->add(Expression::dateTime($dateTime));
	}
	
	public function timestamp($timestamp): ModelColumn
	{
		return $this->add(Expression::timestamp($timestamp));
	}
	
	public function int($value = 0): ModelColumn
	{
		return $this->add(Expression::int($value));
	}
	
	public function float($value = 0): ModelColumn
	{
		return $this->add(Expression::float($value));
	}
	
	/**
	 * Trim value before seting
	 *
	 * @param string $value
	 * @return $this
	 */
	public function trim($value): ModelColumn
	{
		return $this->value(trim($value));
	}
	
	public function boolInt($value): ModelColumn
	{
		return $this->add(Expression::boolInt($value));
	}
	
	/**
	 * Round value to column specified decimal points
	 *
	 * @param $value
	 * @return \Infira\Poesis\orm\ModelColumn
	 */
	public function round($value): ModelColumn
	{
		return $this->value($this->dbSchema()->round($this->schemaIndex, $value));
	}
	
	/**
	 * Cut value to column specified length
	 *
	 * @param $value
	 * @return \Infira\Poesis\orm\ModelColumn
	 */
	public function substr($value): ModelColumn
	{
		return $this->value(substr($value, 0, $this->dbSchema()->getLength($this->schemaIndex)));
	}
	
	/**
	 * Will fix value according to db column type
	 *
	 * @param mixed $value
	 * @return \Infira\Poesis\orm\ModelColumn
	 */
	public function auto($value): ModelColumn
	{
		$type = $this->dbSchema()->getType($this->schemaIndex);
		if (preg_match('/int/i', $type)) {
			return $this->int($value);
		}
		elseif (in_array($type, ['float', 'double', 'real', 'decimal'])) {
			return $this->float($value);
		}
		elseif (preg_match('/datetime/i', $type)) {
			return $this->dateTime($value);
		}
		elseif (preg_match('/timestamp/i', $type)) {
			return $this->timestamp($value);
		}
		elseif (preg_match('/date/i', $type)) {
			return $this->date($value);
		}
		elseif (preg_match('/time/i', $type)) {
			return $this->time($value);
		}
		
		return $this->value($value);
	}
	//endregion
	
	/**
	 * Add SQL functions to column
	 *
	 * @param string $function
	 * @param mixed  ...$argument
	 * @return $this
	 */
	public function columnFunction(string $function, ...$argument): ModelColumn
	{
		$this->columnFunctions[] = [$function, $argument];
		
		return $this;
	}
	
	/**
	 * Shortut for columnFunction
	 *
	 * @param string $function
	 * @param mixed  ...$argument
	 * @return $this
	 */
	public function colf(string $function, ...$argument): ModelColumn
	{
		return $this->columnFunction($function, ...$argument);
	}
	
	/**
	 * Add SQL function to value
	 *
	 * @param string $function
	 * @param mixed  ...$argument
	 * @return $this
	 */
	public function valueFunction(string $function, ...$argument): ModelColumn
	{
		$this->valueFunctions[] = [$function, $argument];
		
		return $this;
	}
	
	/**
	 * Shortut for valueFunction
	 *
	 * @param string $function
	 * @param array  $arguments
	 * @return $this
	 */
	public function volf(string $function, ...$argument): ModelColumn
	{
		return $this->valueFunction($function, ...$argument);
	}
	
	public function exists(int $key): bool
	{
		return isset($this->expressions[$key]);
	}
	
	public function getAt(int $key): ?Field
	{
		if (!$this->exists($key)) {
			return null;
		}
		
		return $this->expressions[$key];
	}
	
	public function first(): ?Field
	{
		return $this->getAt(0);
	}
	
	/**
	 * @return Field[]
	 */
	public function getExpressions(): array
	{
		return $this->expressions;
	}
}