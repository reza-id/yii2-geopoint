<?php

namespace rezaid\geopoint;

use Yii;
use yii\db\Expression;
use yii\db\ActiveRecord as YiiActiveRecord;
use yii\base\InvalidCallException;

class ActiveRecord extends YiiActiveRecord {
    
    public $_d;
    protected $_saved = [];

    public static function find()    {
        return Yii::createObject(ActiveQuery::className(), [get_called_class()]);
    }

    public static function isPoint($column)   {
        return $column ? ($column->dbType == 'point') : false;
    }

    protected function toDB($val) {
        $driver = $this->db->driverName;
        $exp = $val;

        if ($driver === 'mysql') {
            $pnt = str_replace(',', ' ', $val);
            $exp = new Expression("ST_PointFromText('POINT($pnt)')");
        }

        return $exp;
    }

    protected function toAttr($exp) {
        $driver = $this->db->driverName;
        $val = $exp;        
        
        if (preg_match('#\((.*?)\)#', $exp, $matches)) {
            $val = $matches[1];
            if ($driver === 'mysql')
                $val = str_replace(' ', ',', $val);
        }

        return $val;
    }

    public function beforeSave($insert)    {
        $r = parent::beforeSave($insert);
        if ($r) {
            $scheme = static::getTableSchema();
            foreach ($scheme->columns as $column)   {
                if (static::isPoint($column))   {
                    $field = $column->name;
                    $attr = $this->getAttribute($field);

                    if ($attr)  {
                        $this->_saved[$field] = $attr;
                        $exp = $this->toDB($attr);
                        $this->setAttribute($field, $exp);
                    }
                }
            }
        }
        return $r;
    }

    public function afterSave($insert, $changedAttributes)    {
        foreach ($this->_saved as $field => $attr)
            $this->setAttribute($field, $attr);
        parent::afterSave($insert, $changedAttributes);
    }

    public function afterFind()    {
        parent::afterFind();

        $scheme = static::getTableSchema();
        foreach ($scheme->columns as $column)   {
            if (static::isPoint($column))   {
                $field = $column->name;
                $attr = $this->getAttribute($field);
                if ($attr)  {
                    if (YII_DEBUG && preg_match( '/[\\x80-\\xff]+/' , $attr ))   {
                        throw new InvalidCallException('Spatial attribute not converted.');
                    }

                    $text = $this->toAttr($attr);
                    $this->setAttribute($field, $text);
                }
            }
        }
    }

    public function getDistance()
    {
        return $this->_d;
    }
}