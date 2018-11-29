<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\ErrorLog;
use USQL\Library\SqlRestraint\Common\GlobalVar;

class Set extends HandlerAbstract
{

    protected $module = 'set';

    public function handler($index, array $fields, $parentModule = null)
    {
        $res = GlobalVar::$CHECK_SUCCESS;
        return $res;
    }
}