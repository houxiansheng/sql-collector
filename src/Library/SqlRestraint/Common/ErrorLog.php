<?php
namespace USQL\Library\SqlRestraint\Common;

class ErrorLog
{

    protected static $errMsg = [];

    public static function writeLog($errMsg)
    {
        self::$errMsg[] = $errMsg;
        self::$errMsg = array_unique(self::$errMsg);
    }

    public static function getLog()
    {
        sort(self::$errMsg);
        return self::$errMsg;
    }

    public static function destoryErrMsg()
    {
        self::$errMsg = [];
    }
}