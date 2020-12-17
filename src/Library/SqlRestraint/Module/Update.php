<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\ErrorLog;
use USQL\Library\SqlRestraint\Common\GlobalVar;

class Update extends HandlerAbstract
{

    protected $module = 'update';
    use Recursion;

    public function handler($index, array $fields, $parentModule = null, $depth = 0)
    {
        $res = GlobalVar::$CHECK_SUCCESS;
        return $res;
    }
}