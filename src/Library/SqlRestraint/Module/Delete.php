<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\ErrorLog;
use USQL\Library\SqlRestraint\Common\GlobalVar;

class Delete extends HandlerAbstract
{

    protected $module = 'delete';

    public function handler($index, array $fields, $parentModule = null)
    {
        ErrorLog::writeLog('8-delete');
        $res = GlobalVar::$CHECK_SUCCESS;
        return $res;
    }
}