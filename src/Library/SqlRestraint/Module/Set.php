<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\ErrorLog;
use USQL\Library\SqlRestraint\Common\GlobalVar;

class Set extends HandlerAbstract
{

    protected $module = 'set';
    use Recursion;

    public function handler($index, array $fields, $parentModule = null, $depth = 0)
    {
        $res = GlobalVar::$CHECK_SUCCESS;
        return $res;
    }
}