<?php
namespace USQL\Library\SqlRestraint\Common;

class HistorySql
{

    protected static $sql = [];

    public static function write($sql)
    {
        if ($sql && count(self::$sql) < 50 && ! in_array($sql, self::$sql)) {
            self::$sql[] = $sql;
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