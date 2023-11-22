<?php

namespace Infira\Poesis;

use Infira\Poesis\clause\{Clause, ClauseBag, ClauseCollection, Field, LogicalOperator, ModelColumn};
use Infira\Poesis\dr\DataMethods;
use Infira\Poesis\statement\{Modify, Select, Statement};
use Infira\Poesis\support\{ModelSchemaTrait, ModelStatementPrep, RepoTrait};
use Infira\Poesis\support\Expression;
use Infira\Poesis\support\Utils;
use stdClass;

/**
 * A class to provide simple db query functions, update,insert,delet, aso.
 *
 * @property static|Model $Where
 */
abstract class Model
{
    use ModelSchemaTrait;
    use RepoTrait;
    use ModelStatementPrep;

    //region options
    protected $table = null;
    protected $aiColumn = null;//auto increment column
    protected $TIDColumn = null;//auto increment column
    protected $primaryColumns = [];
    protected $columnClass = ModelColumn::class;
    protected $isView = false;
    protected $log = true;
    protected $connection = 'defaultConnection';
    //endregion

    private $haltReset = false;
    private $eventListeners = [];
    private $extraLogData = [];
    protected $rowParsers = [];
    private $success = false;//is editquery a success
    private $failMsg = '';
    protected $clauseValueParsers = [];//TODO Kas neid ei saaks panna otse clause sisse
    private $lastLogID;

    /**
     * Defines what order by set to sql query
     *
     * @var string
     */
    protected $___orderBy = '';

    /**
     * Defiens what to group by set to sql query
     *
     * @var string
     */
    private $___groupBy = '';

    /**
     * Defiens what order by set to sql query
     *
     * @var string
     */
    private $___limit = '';

    /**
     * Defines last inserted primary column value got by mysqli_insert_id();
     *
     * @var int
     */
    private $lastInsertID = false;

    /**
     * @var Statement
     */
    private $lastStatement;

    /**
     * @var Clause
     */
    public $Clause;

    public function __construct(array $options = [])
    {
        $connectionName = $this->connection;
        if (isset($options['connection']) and $options['connection'] instanceof Connection) {
            $connectionName = $options['connection']->getName();
        }
        $this->Clause = new Clause();
        $this->connectionName = $connectionName;
    }

    /**
     * @param $name
     * @param $value
     * @throws \Infira\Poesis\Error
     */
    final public function __set($name, $value)
    {
        if ($name == 'Where') {
            $this->Where = $value;
        }
        elseif ($this->hasColumn($name)) {
            $this->add2Clause($this->value2ModelColumn($name, $value));
        }
        else {
            Poesis::error("Cant set undefined variable '$name'");
        }
    }

    /**
     * Magic method __get()
     *
     * @param $name
     * @return ModelColumn|$this
     * @throws \Infira\Poesis\Error
     * @see https://www.php.net/manual/en/language.oop5.overloading.php#object.get
     */
    final public function &__get($name)
    {
        if ($name == 'Where') {
            $t = clone($this);
            $t->currentClauseType = 'where';
            $t->isChain = false;
            $t->chain = -1;

            $this->Where = $t;

            return $this->Where;
        }
        elseif ($this->hasColumn($name)) {
            $modelColumn = $this->makeModelColumn($name);
            $this->add2Clause($modelColumn);

            return $modelColumn;
        }
        Poesis::error("You are tring to get variable '$name' it doesn\'t exits in ".static::class.' class');
    }

    /**
     * @param $method
     * @param $arguments
     * @return $this
     * @throws \Infira\Poesis\Error
     */
    final public function __call($method, $arguments)
    {
        if ($this->hasColumn($method)) {
            return $this->add2Clause($this->value2ModelColumn($method, $arguments[0]));
        }
        Poesis::error("You are tring to call un callable method '$method' it doesn\'t exits in ".static::class.' class');
    }

    /**
     * @param $method
     * @param $arguments
     * @return $this
     * @throws \Infira\Poesis\Error
     */
    final public static function __callStatic($method, $arguments)
    {
        $model = new static();
        if ($model->hasColumn($method)) {
            return $model->add2Clause($model->value2ModelColumn($method, $arguments[0]));
        }
        Poesis::error("You are tring to call un callable method '$method' it doesn\'t exits in ".static::class.' class');
    }

    //region query constructors

    /**
     * Set a order flag to select sql query
     *
     * @param  string  $order
     * @return $this
     */
    final public function orderBy(string $order)
    {
        $this->___orderBy = $order;

        return $this;
    }

    /**
     * Set a broup BY flag to select sql query
     *
     * @param  string  $group
     * @return $this
     */
    final public function groupBy(string $group)
    {
        $this->___groupBy = $group;

        return $this;
    }

    /**
     * Get current order by
     *
     * @return string
     */
    final public function getOrderBy(): string
    {
        return $this->___orderBy;
    }

    /**
     * Get query group by
     *
     * @return string
     */
    final public function getGroupBy(): string
    {
        return $this->___groupBy;
    }

    /**
     * Set a limit flag to select sql query
     * if ($p1 AND !$p1) then query will be .... LIMIT $p1 else $p1 will ac as start $p2 will act as limit $p1, $p2
     *
     * @param  int  $p1
     * @param  int|null  $p2
     * @return $this
     */
    final public function limit(int $p1, int $p2 = null)
    {
        if ($p2 !== null) {
            $this->___limit = "$p2 OFFSET $p1";
        }
        else {
            $this->___limit = $p1;
        }

        return $this;
    }

    /**
     * Get query limit
     *
     * @return string
     */
    final public function getLimit(): string
    {
        return $this->___limit;
    }

    /**
     * Add Logical AND operator to query
     *
     * @return $this
     */
    final public function and()
    {
        return $this->addOperator('AND');
    }

    /**
     * Add XOR operator to query
     *
     * @return $this
     */
    final public function xor()
    {
        return $this->addOperator('XOR');
    }

    /**
     * Add logical OR operator to query
     *
     * @return $this
     */
    final public function or()
    {
        return $this->addOperator('OR');
    }

    private function addOperator(string $op)
    {
        return $this->add2Clause(new LogicalOperator($op));
    }

    /**
     * Add raw sql to final query
     *
     * @param  string  $query
     * @return $this
     */
    final public function raw(string $query)
    {
        return $this->add2Clause(Expression::raw($query));
    }

    /**
     * Set were cluasel
     *
     * @param  string  $column
     * @param  mixed  $value
     * @return $this
     */
    final public function where(string $column, $value)
    {
        $this->Where->add2Clause($this->value2ModelColumn($column, $value));

        return $this;
    }

    /**
     * Map columns
     *
     * @param  array|object  $data
     * @param  array|string  $voidColumns
     * @param  array  $overWrite
     * @return $this
     */
    final public function map(array $data, $voidColumns = [], array $overWrite = [])
    {
        $data = array_merge($data, $overWrite);
        $voidColumns = Utils::toArray($voidColumns);
        foreach ($data as $f => $value) {
            if (!in_array($f, $voidColumns) and $this->hasColumn($f)) {
                $this->add2Clause($this->value2ModelColumn($f, $value));
            }
        }

        return $this;
    }

    private function makeSelectStatement(): Select
    {
        return $this->makeStatement(new Select($this->connectionName), 'select');
    }

    private function makeModifyStatement(string $queryType): Modify
    {
        return $this->makeStatement(new Modify($this->connectionName), $queryType);
    }

    private function makeStatement(Statement $statement, string $queryType): Statement
    {
        $TID = null;
        if ($this->isTIDEnabled()) {
            $tidColumnName = $this->getTIDColumn();
            $TID = md5(uniqid('', true).microtime(true));
            $this->$tidColumnName($TID);
        }
        if ($TID) {
            $statement->setTID($TID);
        }
        $statement->setTable($this->table);
        $statement->setOrderBy($this->getOrderBy());
        $statement->setLimit($this->getLimit());
        $statement->setGroupBy($this->getGroupBy());
        $statement->setRowParsers($this->rowParsers);
        $statement->setClause(clone($this->Clause));
        $this->lastStatement = &$statement;

        return $statement;
    }
    //endregion

    //region data transactions


    /**
     * Runs a sql replace query width setted values
     *
     * @return $this
     */
    final public function replace()
    {
        return $this->doEdit('replace');
    }

    /**
     * Runs a sql insert query width setted values
     *
     * @return $this
     */
    final public function insert()
    {
        return $this->doEdit('insert');
    }

    /**
     * Runs a sql update query width setted values
     *
     * @return $this
     */
    final public function update()
    {
        return $this->doEdit('update');
    }

    /**
     * Runs a sql delete query with setted values
     *
     * @return $this
     */
    final public function delete()
    {
        return $this->doEdit('delete');
    }

    /**
     * Duplicate values by Where
     *
     * @param  array|null  $overwrite
     * @param  array  $voidColumns  - void columns on duplicate
     * @return $this
     */
    final public function duplicate(array $overwrite = [], array $voidColumns = [])
    {
        return $this->doDuplicate($overwrite, $voidColumns, false);
    }

    /**
     * Truncate table
     */
    final public function truncate()
    {
        $this->connection()->realQuery('TRUNCATE TABLE '.$this->table);
    }

    /**
     * Execute update or insert, chekcs the databae via primary keys,TID and then if records exosts it will perform an update
     *
     * @param  null  $mapData
     * @return $this|string
     */
    final public function save($mapData = null)
    {
        return $this->doAutoSave($mapData, false);
    }

    /**
     * Execute update or insert
     *
     * @param  array|null  $mapData
     * @param  bool  $returnQuery  - return output as sql query
     * @return $this|string
     */
    private function doAutoSave(?array $mapData, bool $returnQuery)
    {
        if ($this->Clause->hasMany()) {
            Poesis::error('Cant have collection on autosave');
        }
        if (!$this->Clause->hasAny()) {
            Poesis::error('Clause is empy');
        }

        if ($mapData) {
            $this->map($mapData);
        }
        $collectionBag = $this->Clause->at();
        if ($collectionBag->where->hasAny()) {
            if ($returnQuery) {
                return $this->getUpdateQuery();
            }

            return $this->update();
        }
        if ($this->hasPrimaryColumns()) {
            $whereModel = $this->model();

            $groups = $collectionBag->set->getChains();
            foreach ($groups as $groupIndex => $group) {
                if ($group->hasMany()) {
                    Poesis::error('Cant have multime items in group on autoSave', ['$group' => $group->getConditions()]);
                }
                /**
                 * @var ModelColumn $modelColumn
                 */
                $modelColumn = $group->at(0);
                $f = $modelColumn->getColumn();
                if ($this->isPrimaryColumn($f)) {
                    $whereModel->add2Clause($modelColumn);
                    unset($groups[$groupIndex]);
                }
            }
            if ($whereModel->Clause->hasAny()) {
                $editModel = $this->model();
                foreach ($this->eventListeners as $event => $listeners) {
                    foreach ($listeners as $listener) {
                        $editModel->on($event, $listener['listener'], $listener['group']);
                    }
                }
                $hasRows = $whereModel->haltReset()->hasRows();
                if ($hasRows) {
                    $editModel->Clause->addSetFromArray($groups);
                    $editModel->Clause->addWhereFromArray($whereModel->Clause->at()->set->getChains());
                    if ($returnQuery) {
                        $this->reset();

                        return $editModel->getUpdateQuery();
                    }
                    $this->reset();
                    $editModel->update();
                }
                else {
                    $editModel->Clause->addSetFromArray($collectionBag->set->getChains());
                    if ($returnQuery) {
                        $this->reset();

                        return $editModel->getInsertQuery();
                    }
                    $this->reset();
                    $editModel->insert();
                }

                return $editModel;
            }
        }
        if ($returnQuery) {
            return $this->getInsertQuery();
        }

        return $this->insert();
    }

    /**
     * Duplicate values by Where
     *
     * @param  array|null  $overwrite
     * @param  array  $voidColumns  - void columns on duplicate
     * @return $this|string
     */
    final public function doDuplicate(array $overwrite = [], array $voidColumns = [], bool $returnQuery = false)
    {
        if ($this->Clause->hasMany()) {
            Poesis::error('Collection duplicate not implemented');
        }
        if (!$this->Clause->hasAny()) {
            Poesis::error('Clause is empy');
        }
        /*
        $this->Clause->each(function ($collection)
        {
            debug($collection);
            exit;
        });
        */
        $collection = $this->Clause->at();
        $selectModel = $this->model();
        $modelOverwrite = [];

        if ($collection->where->hasAny() and $collection->set->hasAny()) {
            $modelOverwrite = $collection->set->getChains();
            $selectModel->Clause->addSetFromArray($collection->where->getChains());
        }
        elseif (!$collection->where->hasAny() and $collection->set->hasAny()) {
            $selectModel->Clause->addSetFromArray($collection->set->getChains());
        }

        $dr = $selectModel->select();
        if (!$dr->hasRows()) {
            return $returnQuery ? null : $this->model()->setFailed('nothing to duplicate');
        }
        $aiColumn = $this->hasAIColumn() ? $this->getAIColumn() : null;
        $dbNew = $this->model();
        foreach ($this->eventListeners as $event => $listeners) {
            foreach ($listeners as $listener) {
                $dbNew->on($event, $listener['listener'], $listener['group']);
            }
        }
        $dr->each(function ($CurrentRow) use (&$dbNew, $voidColumns, $modelOverwrite, &$overwrite, $aiColumn) {
            foreach ($modelOverwrite as $group) {
                if ($group->count() > 1) {
                    Poesis::error('Cant have multime items in group on autoSave');
                }
                foreach ($group->getConditions() as $Node) {
                    $f = $Node->getColumn();
                    $CurrentRow->$f = $Node;
                }
            }
            foreach ($overwrite as $f => $v) {
                $CurrentRow->$f = $v;
            }
            if ($aiColumn and property_exists($CurrentRow, $aiColumn)) {
                unset($CurrentRow->$aiColumn);
            }
            $dbNew->map((array)$CurrentRow, $voidColumns);
            $dbNew->collect();
        });
        if ($returnQuery) {
            return $dbNew->getInsertQuery();
        }

        return $dbNew;
    }

    /**
     * @param  string  $queryType  - update,insert,replace
     * @return $this
     * @throws \Infira\Poesis\Error
     */
    private function doEdit(string $queryType)
    {
        if ($queryType == 'update') {
            $beforeEvent = 'beforeUpdate';
            $afterEvent = 'afterUpdate';
        }
        elseif ($queryType == 'insert') {
            $beforeEvent = 'beforeInsert';
            $afterEvent = 'afterInsert';
        }
        elseif ($queryType == 'replace') {
            $beforeEvent = 'beforeReplace';
            $afterEvent = 'afterReplace';
        }
        else//if ($queryType == 'delete')
        {
            $beforeEvent = 'beforeDelete';
            $afterEvent = 'afterDelete';
        }
        if ($this->hasEventListener($beforeEvent)) {
            if ($res = $this->callBeforeEventListener($beforeEvent, $queryType) === false) {
                $this->success = false;

                return $this;
            }
        }
        if ($this->isView and $queryType !== 'select') {
            Poesis::error('Can\'t save into view :'.$this->table);
        }
        $statement = $this->makeModifyStatement($queryType);
        if (!$statement->getClause()->hasAny()) {
            $this->failMsg = 'no clauses set';
            $this->success = false;

            return $this;
        }
        $r = $statement->modify($queryType);
        if (!$r) {
            $this->success = false;
        }
        if ($this->hasEventListener($afterEvent)) {
            $this->callAfterEventListener($afterEvent, $queryType);
        }
        $this->resumeEvents();
        $this->lastInsertID = $this->connection()->getLastInsertID();

        if ($this->log and Poesis::isLoggerEnabled()) {
            $ModelData = new stdClass();
            $ModelData->extraData = $this->extraLogData;
            $this->makeLog($queryType, $statement);
            $this->extraLogData = [];
            $this->log = true;
        }
        $this->reset();

        return $this;
    }

    /**
     * Select data from database
     *
     * @param  string|array  $columns  - columns to use in SELECT $columns FROM USE null OR *[string] - used to select all columns, string will be exploded by ,
     * @return DataMethods
     */
    public function select($columns = null)
    {
        return $this->doSelect($columns, DataMethods::class);
    }

    protected final function doSelect($columns, ?string $dataDatMethodsClass)
    {
        $dm = $this->makeSelectStatement()->select($columns, $dataDatMethodsClass);
        $dm->onAfterQuery(function () {
            $this->reset();
        });

        return $dm;
    }
    //endregion

    //region query generators
    /**
     * Get select query
     *
     * @param  string|array  $columns  - columns to use in SELECT $columns FROM
     *                              USE null OR *[string] - used to select all columns, string will be exploded by ,
     * @return string
     */
    final public function getSelectQuery($columns = null): string
    {
        $query = $this->makeSelectStatement()->getSelectQuery($columns);
        $this->reset();

        return $query;
    }

    /**
     * Duplicate values by Where
     *
     * @param  array|null  $overwrite
     * @param  array  $voidColumns  - void columns on duplicate
     * @return string
     */
    final public function getDuplicateQuery(array $overwrite = [], array $voidColumns = []): ?string
    {
        return $this->doDuplicate($overwrite, $voidColumns, true);
    }

    /**
     * Get save query (update or insert)
     *
     * @param  array|null  $mapData
     * @return string
     */
    final public function getSaveQuery(array $mapData = null): string
    {
        return $this->doAutoSave($mapData, true);
    }

    /**
     * Get update query
     *
     * @return string
     */
    final public function getUpdateQuery(): string
    {
        $query = $this->makeModifyStatement('update')->getUpdateQuery();
        $this->reset();

        return $query;
    }

    /**
     * Get insert query
     *
     * @return string
     */
    final public function getInsertQuery(): string
    {
        $query = $this->makeModifyStatement('insert')->getInsertQuery();
        $this->reset();

        return $query;
    }

    /**
     * Get replace query
     *
     * @return string
     */
    final public function getReplaceQuery(): string
    {
        $query = $this->makeModifyStatement('replace')->getReplaceQuery();
        $this->reset();

        return $query;
    }

    /**
     * Get delete query
     *
     * @return string
     */
    final public function getDeleteQuery(): string
    {
        $query = $this->makeModifyStatement('delete')->getDeleteQuery();
        $this->reset();

        return $query;
    }

    /**
     * Debug current sql query
     *
     * @param  bool|string|array  $columns  - false means *
     */
    final public function debugQuery($columns = null): void
    {
        debug($this->getSelectQuery($columns));
    }
    //endregion

    //region logging
    /**
     * Void logging for current data transaction
     *
     * @return $this
     */
    final public function voidLog()
    {
        $this->log = false;

        return $this;
    }

    /**
     * Add extra log data to go along with current data transaction
     *
     * @param  string  $name  - data key name
     * @param  mixed  $data
     * @return void
     */
    final public function addLogData(string $name, $data): void
    {
        $this->extraLogData[$name] = $data;
    }

    /**
     * Overwritable method void log on specific data transactions
     *
     * @param  Clause  $setClauses
     * @param  Clause  $whereClauses
     * @return bool
     */
    public function isLogActive(Clause $setClauses, Clause $whereClauses): bool
    {
        return true;
    }

    private function makeLog(string $queryType, Statement $statement): void
    {
        $logModelName = Poesis::getLogModel();
        /**
         * @var \TDbLog $dbLog
         */
        $dbLog = new $logModelName();
        $dbLog->voidLog();

        debug($this->getLastQuery());

        //debug($this->getAffectedRecordModel()->getSelectQuery());exit;

        $statement->getClause()->each(function ($clause) use (&$dbLog, $queryType, &$statement) {
            if (!$this->isLogActive($clause->set, $clause->where)) {
                return;
            }
            $LogData = new stdClass();
            $LogData->setClauses = [];
            $LogData->whereClauses = [];
            $LogData->extra = $this->extraLogData;
            $LogData->trace = Utils::getBacktrace();
            $LogData->time = date('d.m.Y H:i:s');
            $LogData->phpInput = file_get_contents('php://input');
            $LogData->POST = $_POST ?? [];
            $LogData->GET = $_GET ?? [];
            $LogData->SessionID = null;
            $LogData->SESSION = null;
            if (session_status() == PHP_SESSION_ACTIVE) {
                $LogData->SessionID = session_id();
                $LogData->SESSION = $_SESSION;
                foreach ($LogData->SESSION as $key => $val) {
                    if (preg_match('/__allCacheKeys/', $key)) {
                        unset($LogData->SESSION[$key]);
                        break;
                    }
                }
            }
            $LogData->SERVER = [];
            $voidFields = ['HTTP_COOKIE', 'SERVER_SIGNATURE'];
            foreach ($_SERVER as $f => $val) {
                if (!in_array($f, $voidFields) and strpos($f, 'SSL') === false and strpos($f, 'REDIRECT') === false or in_array($f, ['REDIRECT_URL', 'REDIRECT_QUERY_STRING'])) {
                    $LogData->SERVER[$f] = $val;
                }
            }

            $TIDColumnName = $this->getTIDColumn();
            $TIDEnabled = $this->isTIDEnabled();

            foreach ($clause->set->filterModelColumns() as $modelColumn) {
                foreach ($modelColumn->getExpressions() as $expression) {
                    $column = $modelColumn->getColumn();
                    if (($TIDEnabled and $column != $TIDColumnName) or !$TIDEnabled) {
                        $LogData->setClauses[$column] = $expression->getValue();
                    }
                }
            }
            foreach ($clause->where as $groupIndex => $predicates) {
                foreach ($predicates as $valueIndex => $Node) {
                    $column = $Node->getColumn();
                    if (($TIDEnabled and $column != $TIDColumnName) or !$TIDEnabled) {
                        $LogData->whereClauses[$groupIndex][$valueIndex][$column] = $Node->getValue();
                    }
                }
            }

            $dbLog->data->compress(json_encode($LogData));
            $dbLog->userID(Poesis::getLogUserID());
            $dbLog->eventName($queryType);
            $dbLog->tableName($this->table);

            if ($this->hasAIColumn()) {
                $rowIDCols = [$this->getAIColumn()];
            }
            elseif ($this->hasPrimaryColumns()) {
                $rowIDCols = $this->getPrimaryColumns();
            }
            else {
                $rowIDCols = [];
                foreach ($clause->where as $groupItems) {
                    foreach ($groupItems as $Node) {
                        $rowIDCols[] = $Node->getColumn();
                    }
                }
            }
            if (isset($_SERVER['HTTP_HOST'])) {
                $dbLog->url(Utils::getCurrentUrl());
            }
            $dbLog->ip(Utils::getIP());

            if ($queryType !== 'delete') {
                $dbModifed = $this->model();
                if ($this->isTIDEnabled()) {
                    $dbModifed->add2Clause($this->value2ModelColumn('TID', $this->lastStatement->getTID()));
                }
                elseif ($queryType == 'update') {
                    foreach ($clause->where as $predicates) {
                        foreach ($predicates as $whereField) {
                            $addField = $whereField;
                            foreach ($clause->set as $expressions) {
                                foreach ($expressions as $setField) {
                                    /*
                                     * it means in update where clause has been changes
                                     * $db = new TAllFields();
                                     * $db->varchar("newValue");
                                     * $db->Where->varchar('oldValue');
                                     * $db->collect();
                                     */
                                    if ($setField->getColumn() == $whereField->getColumn()) {
                                        $addField = $setField;
                                        break;
                                    }
                                }
                            }
                            $dbModifed->WhereClause->add(1, $addField);
                        }
                    }
                }
                elseif ($this->hasAIColumn() and $clause->isLast)//one row were inserted
                {
                    $aiColimn = $this->getAIColumn();
                    $dbModifed->$aiColimn($this->lastInsertID);
                }
                else //insert, replace
                {
                    if ($this->hasPrimaryColumns()) {
                        $uniqueColumns = $this->getPrimaryColumns();
                    }
                    elseif ($this->hasAIColumn()) {
                        $uniqueColumns = [$this->getAIColumn()];
                    }
                    else {
                        $uniqueColumns = null;//can't identify row
                    }

                    if ($uniqueColumns) {
                        foreach ($clause->set as $expressions) {
                            foreach ($expressions as $setField) {
                                if (in_array($setField->getColumn(), $uniqueColumns)) {
                                    $dbModifed->WhereClause->add(1, $setField);
                                }
                            }
                        }
                    }
                }
                if (count($dbModifed->getWhereClausePredicates()) > 0) {
                    $dbLog->rowIDColValues($dbModifed->select($rowIDCols)->implode(join(',', $rowIDCols)));
                    $dbLog->rowIDCols(join(',', $rowIDCols));
                }
            }
            $dbLog->insert();
            $this->lastLogID = $dbLog->getLastSaveID();
        });
    }

    //endregion

    //region events
    /**
     * Suspend/resume event listener
     *
     * @param  bool  $toggle
     * @param  string|null  $event  if null, then all possible events will be toggled
     *                           possible events beforeSave(insert,replace,update), afterSave(insert,replace,update), beforeUpdate, afterUpdate, beforeInsert, afterInsert, beforeReplace, afterReplace, beforeDelete, afterDelete
     * @param  string|null  $group  - toggle events in $group
     * @return $this
     */
    private function toggleEvent(bool $toggle, string $event = null, string $group = null)
    {
        if ($event === null) {
            $this->toggleEvent($toggle, 'beforeSave', $group);
            $this->toggleEvent($toggle, 'afterSave', $group);
            $this->toggleEvent($toggle, 'beforeDelete', $group);
            $this->toggleEvent($toggle, 'afterDelete', $group);

            return $this;
        }
        elseif ($event === 'beforeSave') {
            $this->toggleEvent($toggle, 'beforeUpdate', $group);
            $this->toggleEvent($toggle, 'beforeInsert', $group);
            $this->toggleEvent($toggle, 'beforeReplace', $group);

            return $this;
        }
        elseif ($event === 'afterSave') {
            $this->toggleEvent($toggle, 'afterUpdate', $group);
            $this->toggleEvent($toggle, 'afterInsert', $group);
            $this->toggleEvent($toggle, 'afterReplace', $group);

            return $this;
        }
        $this->validateEvent($event);

        if (isset($this->eventListeners[$event])) {
            foreach ($this->eventListeners[$event] as $evKey => $evConfig) {
                if ($evConfig['group'] === $group or $group === null) {
                    $this->eventListeners[$event][$evKey]['suspended'] = $toggle;
                }
            }
        }

        return $this;
    }

    /**
     * Suspend events
     *
     * @param  string  $event  - possible events beforeSave(insert,replace,update), afterSave(insert,replace,update), beforeUpdate, afterUpdate, beforeInsert, afterInsert, beforeReplace, afterReplace, beforeDelete, afterDelete
     * @param  string|null  $group  - suspend events in $group
     * @return $this
     */
    final public function suspendEvent(string $event, string $group = null)
    {
        return $this->toggleEvent(true, $event, $group);
    }

    /**
     * Suspend events
     *
     * @param  string|null  $group  - suspend events in $group
     * @return $this
     */
    final public function suspendEvents(string $group = null)
    {
        return $this->toggleEvent(true, null, $group);
    }

    /**
     * Resume events
     *
     * @param  string  $event  - possible events beforeSave(insert,replace,update), afterSave(insert,replace,update), beforeUpdate, afterUpdate, beforeInsert, afterInsert, beforeReplace, afterReplace, beforeDelete, afterDelete
     * @param  string|null  $group  - resume events in $group
     * @return $this
     */
    final public function resumeEvent(string $event, string $group = null)
    {
        return $this->toggleEvent(false, $event, $group);
    }

    /**
     * Resume events
     *
     * @param  string|null  $group  - resume events in $group
     * @return $this
     */
    final public function resumeEvents(string $group = null)
    {
        return $this->toggleEvent(false, null, $group);
    }

    /**
     * Add event listener
     *
     * @param  string|array|null  $event  - if null all following events will be added
     *                                 possible events beforeSave(insert,replace,update), afterSave(insert,replace,update), beforeUpdate, afterUpdate, beforeInsert, afterInsert, beforeReplace, afterReplace, beforeDelete, afterDelete
     * @param  string|callable  $listener
     * @param  string|null  $group  - group event
     * @return $this
     */
    final public function on($event, $listener, string $group = null)
    {
        if ($event === null) {
            $this->on('beforeSave', $listener, $group);
            $this->on('afterSave', $listener, $group);
            $this->on('beforeDelete', $listener, $group);
            $this->on('afterDelete', $listener, $group);

            return $this;
        }
        foreach ((array)$event as $ev) {
            if ($ev === 'beforeSave') {
                $this->on('beforeUpdate', $listener, $group);
                $this->on('beforeInsert', $listener, $group);
                $this->on('beforeReplace', $listener, $group);
            }
            elseif ($ev === 'afterSave') {
                $this->on('afterUpdate', $listener, $group);
                $this->on('afterInsert', $listener, $group);
                $this->on('afterReplace', $listener, $group);
            }
            else {
                if (!is_callable($listener) and !is_string($listener)) {
                    Poesis::error('Event listener must be either string or callable');
                }

                $this->validateEvent($ev);
                $this->eventListeners[$ev][] = ['suspended' => false, 'listener' => $listener, 'group' => $group];
            }
        }

        return $this;
    }

    private function hasEventListener(string $event): bool
    {
        return isset($this->eventListeners[$event]);
    }

    private function callBeforeEventListener(string $event, string $queryType)
    {
        $output = true;
        foreach ($this->eventListeners[$event] as $evConf) {
            if ($evConf['suspended']) {
                continue;
            }
            $listener = $evConf['listener'];

            if (is_array($listener)) {
                $output = call_user_func_array($listener, [$queryType, $event]);
            }
            elseif (is_string($listener)) {
                $output = $this->$listener($queryType, $event);
            }
            else {
                $output = $listener($queryType, $event);
            }
            if ($output === false) {
                return false;
            }
            elseif ($output === true) {
                //just ignore
            }
            else {
                Poesis::error("$event event lister must return bool", ['returned' => $output, '$listenr' => $listener]);
            }
        }

        return $output;
    }

    private function callAfterEventListener(string $event, string $queryType)
    {
        foreach ($this->eventListeners[$event] as $evConf) {
            if ($evConf['suspended']) {
                continue;
            }
            $listener = $evConf['listener'];

            if (is_array($listener)) {
                call_user_func_array($listener, [$queryType, $event]);
            }
            elseif (is_string($listener)) {
                $this->$listener($queryType, $event);
            }
            else {
                $listener($queryType, $event);
            }
        }
    }

    private function validateEvent(string $event)
    {
        if (!in_array($event, ['beforeUpdate', 'afterUpdate', 'beforeInsert', 'afterInsert', 'beforeReplace', 'afterReplace', 'beforeDelete', 'afterDelete'])) {
            Poesis::error("unknown event $event");
        }
    }
    //endregion

    //region model data manipulators
    /**
     * Will be used on fetching data, thil will be passed on to DataMethods
     *
     * @param  callable  $parser
     * @param  array  $arguments
     * @return $this
     */
    public function addRowParser(callable $parser, array $arguments = [])
    {
        $this->rowParsers[] = (object)['parser' => $parser, 'arguments' => $arguments];

        return $this;
    }

    /**
     * Will be used as soon as Clause receive column value
     *
     * @param  string  $column
     * @param  callable  $callable
     * @param  array  $arguments
     * @return $this
     */
    public function addClauseColumnValueParser(string $column, callable $callable, array $arguments = [])
    {
        $this->clauseValueParsers['set'][$column] = (object)['parser' => $callable, 'arguments' => $arguments];

        return $this;
    }

    /**
     * Will be used as soon as WhereClause receive column value
     *
     * @param  string  $column
     * @param  callable  $callable
     * @param  array  $arguments
     * @return $this
     */
    public function addWhereClauseColumnValueParser(string $column, callable $callable, array $arguments = [])
    {
        $this->clauseValueParsers['where'][$column] = (object)['parser' => $callable, 'arguments' => $arguments];

        return $this;
    }

    //endregionh

    //region other helpers
    final public function haltReset()
    {
        $this->haltReset = true;

        return $this;
    }

    final public function reset()
    {
        if (!$this->haltReset) {
            $this->Clause->flush();
            $this->rowParsers = [];
        }
        $this->haltReset = false;

        return $this;
    }

    /**
     * Store data for multiple query
     *
     * @return $this
     */
    final public function collect()
    {
        $this->Clause->increaseCollectionIndex();
        $this->rowParsers = [];

        return $this;
    }

    /**
     * Get last executed sql query
     *
     * @return string
     */
    final public function getLastQuery(): string
    {
        return $this->lastStatement->getQuery();
    }

    /**
     * Did last mysqli_query was successcful
     *
     * @return bool
     */
    public function isSucces(): bool
    {
        return $this->success;
    }

    /**
     * Get last query error notes
     *
     * @return string
     */
    public function getErrorInfo(): string
    {
        return $this->failMsg;
    }

    /**
     * Clone self
     *
     * return $this
     */
    public function clone(array $options = [])
    {
        $cloned = $this->model($options);
        $this->Clause->each(function (ClauseCollection $clause) use (&$cloned) {
            $cloned->Clause->addSetFromArray($clause->set->getChains());
            $cloned->Clause->addWhereFromArray($clause->where->getChains());
            $cloned->collect();
        });

        return $cloned;
    }

    /**
     * Makes new model object
     *
     * @param  array  $options
     * return $this
     */
    public function model(array $options = [])
    {
        return new static($options);
    }

    /**
     * @param  string  $column
     * @return ModelColumn
     */
    private function makeModelColumn(string $column): ModelColumn
    {
        /**
         * @var ModelColumn $cn
         */
        $cn = $this->columnClass;
        $modelColumn = new $cn($column, $this->table, $this->connectionName);
        if (isset($this->clauseValueParsers[$this->currentClauseType][$column])) {
            $p = $this->clauseValueParsers[$this->currentClauseType][$column];
            $modelColumn->addValueParser($p->parser, $p->arguments);
        }

        return $modelColumn;
    }

    //endregion

    //region data getters

    /**
     * Get last updated primary column values
     * If table has only one primary column, and it is auto increment then int is returned
     * If table has multiple primary fields then object containing primary column values is returned
     *
     * @return null|int
     */
    final public function getLastSaveID()
    {
        if (!$this->hasAIColumn()) {
            Poesis::error('table '.$this->table.' does not have AUTO_INCREMENT column');
        }
        if ($this->lastStatement->isQuery('insert', 'replace')) {
            return $this->lastInsertID;
        }
        $primField = $this->getAIColumn();

        $ID = $this->getAffectedRecordModel()->select($primField)->getValue($primField);
        return $ID === null ? null : (int)$ID;
    }

    final public function getLastLogID(): ?int
    {
        return $this->lastLogID;
    }

    /**
     * get last edited affected row
     *
     * @param  null  $columns
     * @return stdClass|null
     */
    final public function getAffectedRecord($columns = null): ?stdClass
    {
        return $this->getAffectedRecordModel()->limit(1)->select($columns)->getObject();
    }

    /**
     * Get last edited affected rows model
     * If table has only one primary column, and it is auto increment then int is returned
     * If table has multiple primary fields then object containing primary column values is returned
     *
     * @return $this
     */
    final public function getAffectedRecordModel()
    {
        $statement = $this->lastStatement;
        if ($statement->isQuery('delete', 'select')) {
            $queryType = $statement->getQueryType();
            Poesis::error("Cannot get object on $queryType");
        }
        $clause = $statement->getClause();
        $db = $this->model();
        if ($this->isTIDEnabled()) {
            $tidColumnName = $this->getTIDColumn();
            $db->Where->$tidColumnName($statement->getTID());
        }
        elseif ($this->hasAIColumn() && $statement->isQuery('insert', 'replace') && $clause->hasOne())//one row were inserted
        {
            $aiColumn = $this->getAIColumn();
            $db->Where->$aiColumn($this->lastInsertID);
        }
//        elseif ($this->hasAIColumn() && $clause->hasWhereColumn($this->getAIColumn())) {
//            $clause->each(function (ClauseCollection $collection) use (&$db) {
//                $db->Clause->addSetFromArray($collection->where->getChains());
//                $db->collect();
//            });
//        }
        else//if ($queryType == 'update') //get last modifed rows via updated clauses
        {
            $clause->each(function (ClauseCollection $collection) use (&$db) {
                if ($collection->set->hasAny() && $collection->where->hasAny()) {
                    if ($collection->set->hasOneOfColumn($collection->where)) {
                        $chain = (new ClauseBag('where'))->chain();
                        $columnsExpressions = [];
                        foreach ($collection->where->getChains() as $whereChain) {
                            foreach ($whereChain->getConditions() as $item) {
                                if ($item instanceof LogicalOperator) {
                                    continue;
                                }
                                if ($item instanceof Field) {
                                    $whereChain->addCondition($item, new LogicalOperator('OR'));
                                    continue;
                                }
                                $columnsExpressions[$item->getColumn()] = $item;
                            }
                        }
                        foreach ($collection->set->getColumns() as $column) {
                            $columnsExpressions[$column->getColumn()] = $column;
                        }
                        $k = 0;
                        foreach ($columnsExpressions as $column) {
                            foreach ($column->getExpressions() as $expression) {
                                if ($k > 0) {
                                    $chain->addCondition(new LogicalOperator('OR'));
                                }
                                $chain->addCondition($expression);
                                $k++;
                            }
                        }

                        $db->Clause->addSetFromArray([$chain]);
                    }
                    else {
                        $db->Clause->addSetFromArray($collection->where->getChains());
                    }
                }
                else {
                    $db->Clause->addSetFromArray($collection->getSelectClause()->getChains());
                }

                $db->collect();
            });
        }

        return $db;
    }

    /**
     * Retrieve a new auto increment value
     *
     * @return int
     */
    final public function getNextID(): int
    {
        $nextID = $this->connection()->dr("SHOW TABLE STATUS LIKE '".$this->table."'")->getArray();

        return $nextID[0]['Auto_increment'];
    }

    /**
     * Get next orderNr column
     *
     * @param  string  $orderNrField
     * @return int
     */
    final public function getNextOrderNr(string $orderNrField = 'orderNr'): int
    {
        return $this->getNextMaxField($orderNrField);
    }

    /**
     * Retrieve a new auto increment value
     *
     * @param  string  $maxField
     * @return int
     */
    final public function getNextMaxField(string $maxField): int
    {
        $maxValue = (int)$this->connection()->dr($this->clone()->getSelectQuery("max($maxField) AS curentMaxFieldValue"))->getValue('curentMaxFieldValue', 0);
        $maxValue++;

        return $maxValue;
    }

    /**
     * Counts mysql resource rows
     *
     * @return bool
     */
    final public function hasRows(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Counts mysql resource rows
     *
     * @return int
     */
    final public function count(): int
    {
        $query = $this->getSelectQuery();
        $query = "SELECT COUNT(*) as count FROM ($query) AS c";
        $this->lastStatement->setQuery($query, 'select');

        //https://stackoverflow.com/questions/16584549/counting-number-of-grouped-rows-in-mysql
        return intval($this->connection()->dr($query)->getValue('count', 0));
    }

    final public function debug($fields = false): void
    {
        $this->select($fields)->debug();
    }
    //endregion
}