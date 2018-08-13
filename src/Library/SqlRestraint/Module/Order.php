<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\GlobalVar;

class Order extends HandlerAbstract
{

    protected $module = 'order';

    public function handler($index, array $fields)
    {
        return GlobalVar::$CHECK_SUCCESS;
    }
}