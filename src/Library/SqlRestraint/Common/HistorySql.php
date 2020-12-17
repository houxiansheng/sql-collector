<?php
namespace USQL\Library\SqlRestraint\Common;

use USQL\Library\Config;

class HistorySql
{

    protected static $sql = [];

    public static function write($sql)
    {
        $max = Config::get('sql.max_num');
        if (count(self::$sql) < $max) {
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