<?php
namespace USQL\Library\SqlRestraint\Common;

class GlobalVar
{

    public static $CHECK_SUCCESS = 1;

    public static $CHECK_RECURION = 2;

    public static $CHECK_FAIL = 3;

    private static $sqlUId = '';

    public static $registerModule = [];

    public static function setSql($sql)
    {
        self::$sqlUId = md5($sql);
    }

    public static function getSqlUId()
    {
        return self::$sqlUId;
    }

    public static function registerModule()
    {
        if (self::$registerModule) {
            return;
        }
        self::$registerModule['SELECT']=['className'=> \USQL\Library\SqlRestraint\Module\Select::class,'object'=>''];
        self::$registerModule['DELETE']=['className'=> \USQL\Library\SqlRestraint\Module\Delete::class,'object'=>''];
        self::$registerModule['FROM']=['className'=> \USQL\Library\SqlRestraint\Module\From::class,'object'=>''];
        self::$registerModule['WHERE']=['className'=> \USQL\Library\SqlRestraint\Module\Where::class,'object'=>''];
        self::$registerModule['GROUP']=['className'=> \USQL\Library\SqlRestraint\Module\Group::class,'object'=>''];
        self::$registerModule['ORDER']=['className'=> \USQL\Library\SqlRestraint\Module\Order::class,'object'=>''];
        self::$registerModule['LIMIT']=['className'=> \USQL\Library\SqlRestraint\Module\Limit::class,'object'=>''];
        self::$registerModule['UPDATE']=['className'=> \USQL\Library\SqlRestraint\Module\Update::class,'object'=>''];
        self::$registerModule['SET']=['className'=> \USQL\Library\SqlRestraint\Module\Set::class,'object'=>''];
        self::$registerModule['UNHANDLED']=['className'=> \USQL\Library\SqlRestraint\Module\Unhandled::class,'object'=>''];
    }
}