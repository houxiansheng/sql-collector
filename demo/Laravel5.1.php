<?php
namespace App\Listeners;

use USQL\SqlStandard;

class SqlQueryListener extends Listener
{

    //
    public function __construct()
    {}

    public function handle($query, $bindings, $time, $name)
    {
        if(isset($_SERVER['SITE_ENV']) && $_SERVER['SITE_ENV'] == 'testing'){
            //库名需要手动录入
            $dbName='xin_insurance';
            //sql对应参数
            $bindings=json_encode($bindings);
            //sql消耗时间
            $takeTime=0;
            //sql执行时间
            $execTime=time();
            $projectName='financialapi.youxinjinrong.com';
            SqlStandard::instance()->collect($dbName, $query, $bindings, $takeTime, $execTime, $projectName);
        }
    }
}
