# sql检测器
## 1、功能及简单说明
 - 收集sql信息，并统一远端管理。
 - web统计分析后台：http://sqltool.ceshi.youxinjinrong.com
 - 目前仅限在优信测试环境中使用。

## 2、安装
### 2.1 修改composer.json
- 1、增加composer包服务器

```json
"repositories" : [{
	"type" : "composer",
	"url" : "http://packages.xin.com"
}],

```
 - 2、配置绕过https校验规则

```json
    
    #命令行调整 单个项目
    composer config secure-http false
    
    #命令行调整全局调整
    composer config -d secure-http false
    
    #手动增加配置
    "config" : {
        "secure-http" : false
    }

```

### 2.2、执行安装操作

```json
   #安装指定版本（必须添加dev-master）
	composer require "uxin/sql-collector" dev-master
```

## 3、使用
### 3.1 方法介绍
- 收集方法
```php

/**
 * 收集项目信息
 *1、同一进程多次调用该方法，内部逻辑仅把参数存放到变量中，未请求远端接口
 *2、同一进程中最多收集100条记录。
 *3、代码销毁时，在析构函数中，批量上传sql信息
 *
 * @param string $dbName 库名（必传）
 * @param string $query SQL语句（必传）（如select id from table_a where id=? 格式，如果不能拆分参数可以写整个sql语句）
 * @param string $bindings（必传） $query对应的绑定信息json格式,如果没有填写{}
 * @param int $takeTime（必传） 执行sql消耗时间（单位：毫秒）
 * @param int $execTime（必传） 程序执行时间（时间戳）
 * @param string $projectName（必传） 项目名称
 * @return boolean
 */
 SqlStandard::instance()->collect($dbName, $query, $bindings, $takeTime, $execTime, $projectName);
```
### 3.2 项目接入
   ##### 3.2.1 Laravel5.1版本
   - 第一步：在bootstrap/app.php中注册类
```php
$app->register(App\Providers\EventServiceProvider::class);
```
   - 第二步：在app/Providers\EventServiceProvider.php添加监听事件
```php
protected $listen = [
      'illuminate.*'=>[
           'App\Listeners\SqlQueryListener'
      ]
];
```   
   - 第三步：创建app/Listeners/SqlQueryListener.php，实现sql收集
```php
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
            $pname='financialapi.youxinjinrong.com';
            SqlStandard::instance()->collect($dbName, $query, $bindings, $takeTime, $execTime, $projectName);
        }
    }
}
```
##### 3.2.2 Laravel5.2+版本
   - 第一步：在bootstrap/app.php中注册类
```php
$app->register(App\Providers\EventServiceProvider::class);
```
   - 第二步：文件app/Providers/EventServiceProvider.php中添加
```php
protected $listen = [
     'Illuminate\Database\Events\QueryExecuted' => [
          'App\Listeners\SqlQueryListener'
     ]
];
```
   - 第三步：创建app/Listeners/SqlQueryListener.php，实现sql收集
```php
<?php

namespace App\Listeners;

use App\Events\ExampleEvent;
use Illuminate\Database\Events\QueryExecuted;
use USQL\SqlStandard;

class SqlQueryListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {}

    /**
     * Handle the event.
     *
     * @param  ExampleEvent  $event
     * @return void
     */
    public function handle(QueryExecuted  $event)
    {
        if(isset($_SERVER['SITE_ENV']) && $_SERVER['SITE_ENV'] == 'testing'){
            $dbName=$event->connection->getConfig('database');
            $query=$event->sql;
            $bindings=json_encode($event->bindings);
            $takeTime=$event->time;
            $execTime=time();
            $projectName='lifeapi.uxincredit.com';
            SqlStandard::instance()->collect($dbName, $query, $bindings, $takeTime, $execTime, $projectName);
        }
    }
}

```