<?php

namespace rezaid\geopoint;

use yii\db\ActiveQuery as YiiActiveQuery;

class ActiveQuery extends YiiActiveQuery {

    public function nearest($from, $attribute, $radius = 100, $unit='km')    {
        $lenPerDegree = 111.045;    // km per degree latitude; for miles, use 69.0
        if ($unit=='mil') $lenPerDegree = 69.0;

        $from = explode(',', $from);
        if (! is_array($from)) return $this;

        $lat = trim($from[0]);
        $lng = trim($from[1]);

        /** @var \yii\db\ActiveRecord $modelCls */
        $modelCls = $this->modelClass;

        if ($modelCls::getDb()->driverName === 'mysql') {
            $subQuery = $this->create($this)->from($modelCls::tableName())
                ->select(['*', '_d' => "($lenPerDegree * ST_Distance($attribute, ST_PointFromText(:point)))"])
                ->params([':point' => "POINT($lat $lng)"]);
        } else if ($modelCls::getDb()->driverName === 'pgsql') {
            $subQuery = $this->create($this)->from($modelCls::tableName())
                ->select(['*', '_d' => "($lenPerDegree * ($attribute <-> POINT(:lt,:lg)))"])
                ->params([':lg' => $lng, ':lt' => $lat]);
        }

        $this->from([$subQuery])
            ->andWhere([ '<', '_d', $radius ])
            ->orderBy([
                '_d' => SORT_ASC
            ]);

        $this->limit = null;
        $this->offset = null;
        $this->distinct = null;
        $this->groupBy = null;
        $this->join = null;
        $this->union = null;

        return $this;
    }

    protected $_skipPrep = false;

    protected function queryScalar($selectExpression, $db)  {
        $this->_skipPrep = true;
        $r = parent::queryScalar($selectExpression, $db);
        $this->_skipPrep = false;
        return $r;
    }

    public function prepare($builder)    {        
        /** @var ActiveRecord $modelClass */
        $modelClass = $this->modelClass;
        if ($modelClass::getDb()->driverName === 'pgsql') return parent::prepare($builder);

        if (! $this->_skipPrep) {   // skip in case of queryScalar; it's not needed, and we get an SQL error (duplicate column names)
            if (empty($this->select))   {
                $this->select('*');
                $this->allColumns();
            }
            else   {
                $schema = $modelClass::getTableSchema();
                foreach ($this->select as $field) {
                    if ($field == '*')  {
                        $this->allColumns();
                    }
                    else {
                        $column = $schema->getColumn($field);
                        if (ActiveRecord::isPoint($column)) {
                            $this->addSelect(["ST_AsText($field) AS $field"]);
                        }
                    }
                }
            }
        }
        return parent::prepare($builder);
    }

    protected function allColumns() {
        /** @var ActiveRecord $modelClass */
        $modelClass = $this->modelClass;
        $schema = $modelClass::getTableSchema();
        foreach ($schema->columns as $column)   {
            if (ActiveRecord::isPoint($column)) {
                $field = $column->name;
                $this->addSelect(["ST_AsText($field) AS $field"]);
            }
        }
    }
}