<?php

namespace App\Listeners;

use App\Events\ExampleEvent;
use Illuminate\Database\Events\QueryExecuted;
use USQL\SqlStandard;

class Laravel5
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

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
