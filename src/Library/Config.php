<?php
namespace USQL\Library;

class Config
{

    public static $ini = null;

    static function get($str)
    {
        if (self::$ini === null) {
            self::$ini = include __DIR__ . "/../Config/Config.php";
        }
        $arr = explode('.', $str);
        $configVal = self::$ini;
        foreach ($arr as $val) {
            if (is_array($configVal) && array_key_exists($val, $configVal)) {
                $configVal = $configVal[$val];
            } else {
                return '';
            }
        }
        return $configVal;
    }
}