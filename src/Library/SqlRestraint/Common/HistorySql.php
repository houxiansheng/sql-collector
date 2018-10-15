<?php
namespace USQL\Library\SqlRestraint\Common;

class HistorySql
{

    protected static $sql = [];

    public static function write($sql)
    {
        if (count(self::$sql) < 50 && isset($sql['db']) && isset($sql['query'])) {
            $uniq = md5($sql['db'] . $sql['query']);
            if (! in_array($sql, self::$sql)) {
                $sql['count'] = 1;
                self::$sql[$uniq] = $sql;
            } elseif ($sql) {
                self::$sql[$uniq]['count'] ++;
            }
        }
    }

    public static function get()
    {
        return self::$sql;
    }

    public static function destory()
    {
        self::$sql = [];
    }
}