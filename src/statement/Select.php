<?php

namespace Infira\Poesis\statement;

use Infira\Poesis\dr\DataMethods;
use Infira\Poesis\Poesis;
use Infira\Poesis\support\QueryCompiler;

class Select extends Statement
{
    public function select($columns, ?string $dataDatMethods)
    {
        /**
         * @var DataMethods $dm
         */
        $con = $this->connection();
        $dm = new $dataDatMethods();
        $dm->setQuery($this->getSelectQuery($columns));
        $dm->setConnection($con);
        $dm->setRowParsers($this->rowParsers());

        return $dm;
    }

    public function getSelectQuery($columns = null): string
    {
        //I wish all the PHP in the world is already on PHP8 for method typeCasting
        if (!is_string($columns) and !is_array($columns) and $columns !== null) {
            Poesis::error('columns must be either string,array or null');
        }
        if ($columns !== null and !$columns) {
            Poesis::error('Define select columns', ['providedColumns' => $columns]);
        }
        $query = QueryCompiler::select($this, $columns);
        $this->query($query);
        $this->queryType('select');

        return $query;
    }

}