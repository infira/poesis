<?php

namespace Infira\Poesis\dr;

use Infira\Poesis\support\Utils;

/**
 * @mixin DataMethods
 */
trait DataDeprecatedMethods
{
    /**
     * @param string $idColumn - defaults to ID
     * @return array
     * @deprecated use $this->ids() instead
     */
    public function getIDS(string $idColumn = 'ID'): array
    {
        return $this->ids($idColumn);
    }

    /**
     * Get column ID value
     *
     * @param mixed|null $default
     * @return int|null
     * @deprecated use $this->id() instead
     */
    public function getID(mixed $default = null): ?int
    {
        return $this->id('ID', $default);
    }

    /**
     * Gets a one column value
     *
     * @param string $column
     * @param null $default
     * @return string|null
     * @deprecated use $this->value() instead
     */
    public function getValue(string $column, $default = null): ?string
    {
        return $this->value($column, $default);
    }

    /**
     * Alias to collect
     *
     * @param callable|null $callback
     * @return array
     * @deprecated use $this->map() instead
     */
    public function eachCollect(callable $callback = null): array
    {
        return $this->map($callback);
    }

    /**
     * Collect rows with row callback
     *
     * @param callable|null $callback
     * @return array - array stdClasses
     * @deprecated use $this->map() instead
     */
    public function collect(callable $callback = null): array
    {
        return $this->map($callback);
    }

    /**
     * get json encoded string
     *
     * @param bool $singleRowInput - fetch single row via fetch_object
     * @return string
     * @link https://php.net/manual/en/function.json-encode.php
     * @deprecated use json_encode($dr->....())
     */
    public function getJson(bool $singleRowInput = false): string
    {
        return json_encode($singleRowInput ? $this->getObject() : $this->getObjects());
    }

    /**
     * @param int $parent
     * @param string $parentColumn
     * @param string $idColumn
     * @param string $subItemsName
     * @return array
     * @deprecated use $this->tree() instead
     */
    public function getTree(int $parent = 0, string $parentColumn = 'parentID', string $idColumn = 'ID', string $subItemsName = 'subItems'): array
    {
        $lookup = [];
        $index = 0;
        $this->loop('fetch_assoc', null, function ($row) use (&$index, &$subItemsName, &$idColumn, &$parentColumn, &$parent, &$lookup) {
            $row['index'] = $index;
            $index++;
            $row[$subItemsName] = [];
            if ($row[$parentColumn] >= $parent) {
                $lookup[$row[$idColumn]] = $row;
            }
        }, false);
        $tree = [];
        foreach ($lookup as $id => $foo) {
            $item = &$lookup[$id];
            if (isset($lookup[$item[$parentColumn]])) {
                $lookup[$item[$parentColumn]][$subItemsName][$id] = &$item;
            }
            else {
                $tree[$id] = &$item;
            }
        }

        return $tree;
    }

    /**
     * @param int $parent
     * @param string $parentColumn
     * @param string $idColumn
     * @param string $subItemsName
     * @return array
     * @deprecated use $this->nestedTree() instead
     */
    public function getNestedTree(int $parent = 0, string $parentColumn = 'parentID', string $idColumn = 'ID', string $subItemsName = 'subItems'): array
    {
        $tree = $this->getTree($parent, $parentColumn, $idColumn, $subItemsName);
        $lft = 1;
        foreach ($tree as $id => $Node) {
            $tree[$id] = $this->__countNestedTreeChildren($Node, $lft);
            $lft++;
        }

        return $tree;
    }

    /**
     * get data as [ [$keyColumn1 => [$keyColumn2 => [$keyColumn.... => $valueColumn]]] ]
     * old = putFieldToKeyValue
     *
     * @param string $keyColumns - one or multiple column names, separated by comma
     * @param string $valueColumn
     * @return array
     * @deprecated use $this->mapIndex() instead
     */
    public function getColumnPair(string $keyColumns, string $valueColumn): array
    {
        $result = [];
        $this->loop(
            'fetch_assoc',
            null,
            function ($row) use ($keyColumns, $valueColumn, &$result) {
                $current = &$result;
                foreach (Utils::toArray($keyColumns) as $col) {
                    $f = $row[$col];
                    $f = (string)($f);
                    $current = &$current[$f];
                }
                $current = $row[$valueColumn];
            },
            false
        );
        return $result;
    }

    /**
     * get data as  [$keyColumn1 => [$keyColumn2 => $row]]
     * old = putFieldToArrayKey
     *
     * @param string $columns - sepearate multiple columns by comma
     * @param bool $returnAsObjectArray does the row is arrat or std class
     * @return array
     * @deprecated use $this->mapIndex() instead
     */
    public function getValueAsKey(string $columns, bool $returnAsObjectArray = false): array
    {
        $result = [];
        $this->loop(
            $returnAsObjectArray ? 'fetch_object' : 'fetch_assoc',
            null,
            function ($row) use ($columns, &$result, $returnAsObjectArray) {
                $current = &$result;
                foreach (Utils::toArray($columns) as $col) {
                    $f = $returnAsObjectArray ? $row->$col : $row[$col];
                    $f = (string)($f);
                    $current = &$current[$f];
                }
                $current = $row;
            },
            false
        );
        return $result;
    }

    /**
     * get data as [$keyColumn1 => [$keyColumn2 => [$row1, $row2, $row.....]]]
     *
     * @param string $columns - sepearate multiple columns by comma
     * @param bool $returnAsObjectArray
     * @return array
     * @deprecated use $this->mapIndex() instead
     */
    public function getValueAsKeyMultiDimensional(string $columns, bool $returnAsObjectArray = false): array
    {
        $result = [];
        $this->loop(
            $returnAsObjectArray ? 'fetch_object' : 'fetch_assoc',
            null,
            function ($row) use ($columns, &$result, $returnAsObjectArray) {
                $current = &$result;
                foreach (Utils::toArray($columns) as $col) {
                    $f = $returnAsObjectArray ? $row->$col : $row[$col];
                    $f = (string)($f);
                    $current = &$current[$f];
                }
                $current[] = $row;
            },
            false
        );
        return $result;
    }
}