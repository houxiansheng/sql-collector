<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\ErrorLog;
use USQL\Library\SqlRestraint\Common\GlobalVar;

class Unhandled extends HandlerAbstract
{

    protected $module = null;

    public function handler($index, array $fields, $parentModule = null)
    {
        ErrorLog::writeLog('0-'.$this->module);
        $res = GlobalVar::$CHECK_SUCCESS;
        return $res;
    }
}