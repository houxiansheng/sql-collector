<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\GlobalVar;

class Order extends HandlerAbstract
{

    protected $module = 'order';
    use Recursion;

    public function handler($index, array $fields, $parentModule = null, $depth = 0)
    {
        return GlobalVar::$CHECK_SUCCESS;
    }
}