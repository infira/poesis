<?php

namespace Infira\Poesis\statement;

use Infira\Poesis\clause\Clause;
use Infira\Poesis\support\RepoTrait;

class Statement
{
    use RepoTrait;

    private $table = '';
    private $rowParsers = [];
    /**
     * @var Clause
     */
    private $clause;
    private $orderBy = '';
    private $groupBy = '';
    private $limit = '';
    private $query = '';
    private $queryType = '';
    private $TID = null;//unique 32characted transactionID, if null then it's not in use

    final public function __construct(string $connectionName)
    {
        $this->connectionName = $connectionName;
    }

    public function getTID(): ?string
    {
        return $this->TID;
    }

    public function setTID(string $tid = null): self
    {
        $this->TID = $tid;

        return $this;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function setTable(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    public function getOrderBy(): ?string
    {
        return $this->orderBy;
    }

    public function setOrderBy(string $orderBy): self
    {
        $this->orderBy = $orderBy;

        return $this;
    }

    public function getGroupBy(): ?string
    {
        return $this->groupBy;
    }

    public function setGroupBy(string $groupBy = null): self
    {
        $this->groupBy = $groupBy;

        return $this;
    }

    public function getLimit(): ?string
    {
        return $this->limit;
    }

    public function setLimit(string $limit = null): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function getQueryType(): ?string
    {
        return $this->queryType;
    }

    public function isQuery(string ...$type): bool
    {
        return in_array($this->queryType, $type);
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }

    public function setQuery(string $query, string $type): self
    {
        $this->query = $query;
        $this->queryType = $type;

        return $this;
    }

    /**
     * @return callable[]
     */
    final public function getRowParsers(): array
    {
        return $this->rowParsers;
    }

    /**
     * @param  callable[]|null  $callables
     * @return callable[]
     */
    final public function setRowParsers(array $callables = null): self
    {
        $this->rowParsers = $callables;

        return $this;
    }


    public function getClause(): Clause
    {
        return $this->clause;
    }

    public function setClause(Clause $clause): self
    {
        $this->clause = $clause;

        return $this;
    }

    public function isMultiquery(): bool
    {
        return $this->clause->hasMany();
    }
}