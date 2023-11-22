<?php

namespace Infira\Poesis\dr;

use Infira\Poesis\Connection;
use Infira\Poesis\Poesis;
use Infira\Poesis\support\Utils;
use stdClass;

class DataMethods
{
    use DataDeprecatedMethods;

    private $rowParsers = [];
    private $afterQuery = [];

    /**
     * @var \mysqli_result
     */
    protected $res = null;
    protected $query;
    /**
     * @var Connection
     */
    protected $Con;
    protected $pointerLocation = false;
    const PASS_ROW_TO_OBJECT = 'PASS_ROW_TO_OBJECT';

    public function setQuery(string $query): static
    {
        $this->query = $query;
        return $this;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @param int $nr
     * @return $this
     */
    public function seek(int $nr): static
    {
        if (is_object($this->res)) {
            if ($this->hasRows()) {
                $this->res->data_seek($nr);
            }
        }

        return $this;
    }

    public function setConnection(Connection &$Con): static
    {
        $this->Con = &$Con;
        return $this;
    }

    public function __call($name, $arguments)
    {
        Poesis::error('Call to undefined method '.$name);
    }

    //region public methods

    /**
     * Is the rsource have any rows
     *
     * @return bool
     */
    public function hasRows(): bool
    {
        return ($this->count() > 0);
    }

    /**
     * Count rows
     *
     * @return int
     */
    public function count(): int
    {
        return $this->getRes()->num_rows;
    }

    /**
     * Get records via fetch_row
     *
     * @param callable<array>|null $callback
     * @return array
     */
    public function getRows(callable $callback = null): array
    {
        return $this->loop('fetch_row', null, $callback, true);
    }

    /**
     * et records via fetch_row
     * @param callable<array>|null $callback
     * @return array|null
     */
    public function getRow(callable $callback = null): ?array
    {
        return $this->fetch('fetch_row', [], $callback);
    }

    /**
     * Get single record via fetch_assoc
     * @param callable<array>|null $callback
     * @return array|null
     */
    public function getArray(callable $callback = null): ?array
    {
        return $this->fetch('fetch_assoc', [], $callback);
    }

    /**
     * Get records via fetch_all
     * @param callable<array>|null $callback
     * @return array
     */
    public function getArrays(callable $callback = null): array
    {
        return $this->loop('fetch_assoc', null, $callback, true);
    }

    /**
     * get object via fetch_object
     *
     * @param string $class - what class to construct on mysqli->fetch_obect
     * @param array $constructorArguments arguments to pass __construct of $class
     * @return object|null
     */
    public function getObject(string $class = '\stdClass', array $constructorArguments = []): ?object
    {
        return $this->fetchObject($class, $constructorArguments);
    }

    /**
     * get records via fetch_object
     *
     * @param string $class
     * @param array $constructorArguments
     * @return array
     */
    public function getObjects(string $class = '\stdClass', array $constructorArguments = []): array
    {
        return $this->loop('fetch_object', [$class, $constructorArguments], null, true);
    }

    /**
     * collects single columns values
     *
     * @param string $column
     * @return array
     */
    public function getValues(string $column): array
    {
        return $this->loop(
            'fetch_assoc',
            null,
            fn($row) => $row[$column],
            true
        );
    }

    public function getDistinctValues(string $column): array
    {
        return array_unique($this->getValues($column));
    }

    /**
     * @param string $column - defaults to ID
     * @return array
     */
    public function ids(string $column = 'ID'): array
    {
        return $this->getValues($column);
    }

    /**
     * Get column ID value
     *
     * @param string $column
     * @param mixed $default
     * @return int|null
     */
    public function id(string $column = 'ID', $default = null): ?int
    {
        return $this->value($column, $default);
    }

    /**
     * Gets a one column value
     *
     * @param string $column
     * @param null $default
     * @return string|null
     */
    public function value(string $column, $default = null): ?string
    {
        $val = $this->getObject();
        if (is_object($val)) {
            return $val->$column;
        }
        return $default;
    }

    public function map(string|array|callable $key, string|DataMethodsOptions $value = null): array
    {
        if (is_string($key) && $this->isClassLike($key)) {
            return $this->mapCallable(fn($row) => new $key($row));
        }
        if (is_callable($key)) {
            return $this->mapCallable($key);
        }

        if (is_string($key)) {
            return $this->mapWithKeys(fn($row) => [$row->$key => $this->rowMapper($row, $value)]);
        }

        if (is_array($key) and count($key) == 2 && $this->isClassLike($key[0])) {
            [$class, $method] = $key;
            $obj = new $class();
            return $this->mapCallable([$obj, $method]);
        }

        if (is_array($key)) {
            return $this->mapToDictionary($key, $value);
        }
        return $this->loop('fetch_object', null, $callback, true);
    }

    /**
     * Collect rows with row callback
     *
     * @param callable<stdClass> $callback
     * @return stdClass[]
     */
    public function mapCallable(callable $callback): array
    {
        return $this->loop('fetch_object', null, $callback, true);
    }

    /**
     * Run an associative map over each of the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @template TMapWithKeysKey of array-key
     * @template TMapWithKeysValue
     *
     * @param callable(stdClass): array<TMapWithKeysKey, TMapWithKeysValue> $callback
     * @return array<TMapWithKeysKey, TMapWithKeysValue>
     */
    public function mapWithKeys(callable $callback): array
    {
        $result = [];
        $this->each(function ($row) use (&$result, $callback) {
            $assoc = $callback($row);
            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        });

        return $result;
    }

    /**
     * get data as [ [$keyColumn1 => [$keyColumn2 => [$keyColumn.... => $valueColumn]]] ]
     * old = putFieldToKeyValue
     *
     * @param array $columns - one or multiple column names, separated by comma
     * @param string|DataMethodsOptions|null $value
     * @return array
     */
    public function mapToDictionary(array $columns, string|DataMethodsOptions $value = null): array
    {
        $result = [];
        $this->each(
            function ($row) use ($columns, $value, &$result) {
                $value = $this->rowMapper($row, $value);
                $currentArray = &$result;
                foreach ($columns as $column) {
                    if ($column === '*') {
                        $currentArray[] = [];
                        $key = key($currentArray);
                    }
                    else {
                        $key = $row->$column;
                        // Create the nested structure if it doesn't exist
                        if (!isset($currentArray[$key])) {
                            $currentArray[$key] = [];
                        }
                    }
                    // Update the reference to the nested array
                    $currentArray = &$currentArray[$key];
                }

                $currentArray = $value;
            }
        );
        return $result;
    }

    /**
     * Run a grouping map over the items.
     *
     * The callback should return an associative array with a single key/value pair.
     *
     * @template TMapToGroupsKey of array-key
     * @template TMapToGroupsValue
     *
     * @param callable(stdClass): array<TMapToGroupsKey, TMapToGroupsValue> $callback
     * @return static<TMapToGroupsKey, static<int, TMapToGroupsValue>>
     */
    public function mapToGroups(callable $callback)
    {
        $result = [];
        $this->each(
            function ($row) use ($callback, &$result) {
                debug($row);
                exit;
                $current = &$result;
                foreach ($keys as $col) {
                    $f = $returnAsObjectArray ? $row->$col : $row[$col];
                    $f = (string)($f);
                    $current = &$current[$f];
                }
                $current = $row;
            }
        );
        return $result;
    }

    /**
     * Implode column values to one string
     *
     * @param string|array $columns
     * @param string $splitter
     * @param mixed $default
     * @return string|null
     */
    public function implode(string|array $columns, string $splitter = ',', $default = ''): ?string
    {
        $columns = Utils::toArray($columns);
        $data = '';
        $this->loop(
            'fetch_assoc',
            null,
            function ($row) use (&$columns, &$data, &$splitter) {
                foreach ($columns as $f) {
                    $data .= $row[$f].$splitter;
                }
            },
            false
        );

        if ($data === '') {
            return $default;
        }
        return substr($data, 0, (strlen($splitter) * -1));
    }

    /**
     * Implode column values to one string
     *
     * @param string|array $columns
     * @param string $splitter
     * @return array
     */
    public function implodeRows(string|array $columns, string $splitter = ','): array
    {
        $columns = Utils::toArray($columns);
        $data = [];
        $this->loop('fetch_assoc', null, function ($row) use (&$columns, &$data, &$splitter) {
            $im = '';
            foreach ($columns as $f) {
                $im .= $row[$f].$splitter;
            }
            $data[] = substr($im, 0, (strlen($splitter) * -1));
        }, false);

        return $data;
    }

    /**
     * Loop each row with callback
     *
     * @param callable|null $callback
     */
    public function each(callable $callback = null)
    {
        $this->loop('fetch_object', null, $callback, false);
    }

    public function debug(): void
    {
        if ($this->count() > 1) {
            debug($this->getObjects());
        }
        else {
            debug($this->getObject());
        }
    }

    /**
     * @param callable $callable
     * @return $this
     */
    public function onAfterQuery(callable $callable)
    {
        $this->afterQuery[] = $callable;

        return $this;
    }
    //endregion


    //region fetchers


    public function getRes(int $setPointer = null): \mysqli_result
    {
        if ($this->res === null) {
            $this->res = $this->Con->query($this->query);
            foreach ($this->afterQuery as $aq) {
                call_user_func_array($aq, [$this->query, $this->res]);
            }
        }
        if ($setPointer !== null) {
            $this->seek($setPointer);
        }

        return $this->res;
    }

    protected function loop(string $fetchMethod, ?array $fetchArguments, ?callable $callback, ?bool $collectRows): ?array
    {
        if ($collectRows) {
            $data = [];
        }

        $res = $this->getRes();
        if ($this->hasRows()) {
            $pointer = 0;
            do {
                $createClass = false;
                $createClassArguments = [];
                $passRowArgumentKey = false;
                if ($fetchMethod == 'fetch_object' and $fetchArguments != null) {
                    $class = $fetchArguments[0];
                    $createClassArguments = $fetchArguments[1];
                    if ($createClassArguments) {
                        $passRowArgumentKey = array_search(self::PASS_ROW_TO_OBJECT, $createClassArguments);
                        if ($passRowArgumentKey !== false) {
                            $fRow = $res->fetch_object();
                            $createClass = $class;
                        }
                        else {
                            $fRow = $res->fetch_object($class, $createClassArguments);
                        }
                    }
                    else {
                        $fRow = $res->fetch_object($class);
                    }
                }
                else if ($fetchArguments !== null) {
                    $fRow = $res->$fetchMethod(...$fetchArguments);
                }
                else {
                    $fRow = $res->$fetchMethod();
                }
                $row = null;
                if ($fRow !== null) {
                    $row = $this->parseRow($fRow);
                    if ($createClass) {
                        if ($passRowArgumentKey !== false) {
                            $createClassArguments[$passRowArgumentKey] = $row;
                        }
                        $row = new $createClass(...$createClassArguments);
                    }
                    if ($callback) {
                        $row = call_user_func_array($callback, [$row]);
                    }
                    if ($row === Poesis::BREAK) {
                        break;
                    }
                    if ($row === Poesis::CONTINUE) {
                        continue;
                    }
                    $pointer++;
                    if ($collectRows) {
                        if ($row === null) {
                            Poesis::error('Looper must return result');
                        }
                        if ($row !== Poesis::VOID) {
                            $data[] = $row;
                        }
                    }
                }
            }
            while ($fRow);
            $this->pointerLocation = $pointer;
        }
        if ($collectRows) {
            return $data;
        }

        return null;
    }

    protected function fetch(string $fetchMethod, array $fetchArguments = [], ?callable $callback = null): ?array
    {
        return $this->parseRow($this->getRes()->$fetchMethod(...$fetchArguments), $callback);
    }

    protected function fetchObject(string $class = '\stdClass', array $constructorArguments = []): ?object
    {
        $res = $this->getRes();
        if (!$constructorArguments) {
            return $this->parseRow($res->fetch_object($class));
        }

        $passRowArgumentKey = array_search(self::PASS_ROW_TO_OBJECT, $constructorArguments);
        if ($passRowArgumentKey === false) {
            return $this->parseRow($res->fetch_object($class, $constructorArguments));
        }


        $constructorArguments[$passRowArgumentKey] = $this->parseRow($res->fetch_object());

        return new $class(...$constructorArguments);
    }

    //endregion

    //region other helpers
    /**
     * @param array $callables
     * @return $this
     */
    public function setRowParsers(array $callables): static
    {
        $this->rowParsers = $callables;

        return $this;
    }

    /**
     * @param callable $parser
     * @param array $arguments
     * @return $this
     */
    public function addRowParser(callable $parser, array $arguments = []): static
    {
        $this->rowParsers[] = (object)['parser' => $parser, 'arguments' => $arguments];

        return $this;
    }

    /**
     * @return $this
     */
    public function nullRowParser()
    {
        $this->rowParsers = [];

        return $this;
    }

    public function hasRowParser(): bool
    {
        return (bool)$this->rowParsers;
    }

    protected function parseRow($row, ?callable $callback = null)
    {
        if ($row === null) {
            return null;
        }
        if ($this->hasRowParser()) {
            foreach ($this->rowParsers as $parserItem) {
                $row = call_user_func_array(
                    $parserItem->parser,
                    array_merge([$row], $parserItem->arguments)
                );
            }
        }

        if ($callback) {
            $row = call_user_func_array($callback, [$row]);
        }


        return $row;
    }

    protected function rowMapper(\stdClass $row, string|DataMethodsOptions $value = null): mixed
    {
        if (is_string($value)) {
            return $row->$value;
        }
        if ($value === null || $value === DataMethodsOptions::FETCH_OBJECT) {
            return $row;
        }
        return (array)$row;
    }

    protected function isClassLike($class): bool
    {
        if (is_string($class) && class_exists($class)) {
            return true;
        }
        return false;
    }

    private function __countNestedTreeChildren(array $node, int &$lft): array
    {
        $node['lft'] = $lft;
        $lft++;
        if (!array_key_exists('__countChildren', $node)) {
            $node['__countChildren'] = 0;
        }
        if (!array_key_exists('lft', $node)) {
            $node['lft'] = 0;
        }
        $node['__countChildren'] += count($node['subItems']);
        foreach ($node['subItems'] as $id => $N) {
            $NewNode = $this->__countNestedTreeChildren($N, $lft);
            $lft++;
            $node['subItems'][$id] = $NewNode;
            $node['__countChildren'] += $NewNode['__countChildren'];
        }
        if ($subItemsSimpleArr = array_values($node['subItems'])) {
            $node['rgt'] = $subItemsSimpleArr[count($subItemsSimpleArr) - 1]['rgt'] + 1;
        }
        else {
            $node['rgt'] = $node['lft'] + 1;
        }

        return $node;
    }
    //endregion

    //region cache
    //	/**
    //	 * use cache
    //	 *
    //	 * @param string|null $key    - cache key
    //	 * @param string      $driver - mem,sess,redis,rm,auto
    //	 * @return DataCacher
    //	 */
    //	public function cache(string $key = null, $driver = "auto"): DataCacher
    //	{
    //		$key = $key ? $this->query . $key : $this->query;
    //
    //		return new DataCacher($this, $driver, $key);
    //	}
    //
    //	/**
    //	 * use session cache
    //	 *
    //	 * @param string|null $key - cache key
    //	 * @return DataCacher
    //	 */
    //	public function cacheSession(string $key = null): DataCacher
    //	{
    //		return $this->cache($key, 'sess');
    //	}
    //
    //	/**
    //	 * use memcached cache
    //	 *
    //	 * @param string|null $key - cache key
    //	 * @return DataCacher
    //	 */
    //	public function cacheMem(string $key = null): DataCacher
    //	{
    //		return $this->cache($key, 'mem');
    //	}
    //
    //	/**
    //	 * use redis cache
    //	 *
    //	 * @param string|null $key - cache key
    //	 * @return DataCacher
    //	 */
    //	public function cacheRedis(string $key = null): DataCacher
    //	{
    //		return $this->cache($key, 'redis');
    //	}
    //
    //	/**
    //	 * use runtime memory cache
    //	 *
    //	 * @param string|null $key - cache key
    //	 * @return DataCacher
    //	 */
    //	public function cacheRm(string $key = null): DataCacher
    //	{
    //		return $this->cache($key, 'rm');
    //	}
    //	//endregion

}