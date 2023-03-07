<?php

namespace Infira\Poesis\statement;

use Infira\Poesis\support\QueryCompiler;

class Modify extends Statement
{
    public function modify(string $queryType): bool
    {
        $query = QueryCompiler::$queryType($this);
        $this->query($query);
        $this->queryType($queryType);


        if ($this->isMultiquery()) {
            $success = (bool)$this->connection()->multiQuery($query);
        }
        else {
            $success = $this->connection()->realQuery($query);
        }

        return $success;
    }

    /**
     * Get update query
     *
     * @return string
     */
    final public function getUpdateQuery(): string
    {
        return QueryCompiler::update($this);
    }

    /**
     * Get insert query
     *
     * @return string
     */
    final public function getInsertQuery(): string
    {
        return QueryCompiler::insert($this);
    }

    /**
     * Get replace query
     *
     * @return string
     */
    final public function getReplaceQuery(): string
    {
        return QueryCompiler::replace($this);
    }

    /**
     * Get delete query
     *
     * @return string
     */
    final public function getDeleteQuery(): string
    {
        return QueryCompiler::delete($this);
    }

}