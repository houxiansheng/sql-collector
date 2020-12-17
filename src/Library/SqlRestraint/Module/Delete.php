<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\ErrorLog;
use USQL\Library\SqlRestraint\Common\GlobalVar;

class Delete extends HandlerAbstract
{

    protected $module = 'delete';
    use Recursion;

    public function handler($index, array $fields, $parentModule = null, $depth = 0)
    {
        // ErrorLog::writeLog($depth.'-'.$index.'-8-delete');
        ErrorLog::writeLogV2($depth, $index, 'module', 'crud', 'delete', 8);
        $res = GlobalVar::$CHECK_SUCCESS;
        return $res;
    }
}