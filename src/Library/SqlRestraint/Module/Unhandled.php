<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\ErrorLog;
use USQL\Library\SqlRestraint\Common\GlobalVar;

class Unhandled extends HandlerAbstract
{

    protected $module = null;
    use Recursion;

    public function handler($index, array $fields, $parentModule = null, $depth = 0)
    {
        // ErrorLog::writeLog($depth.'-'.$index.'-0-'.$this->module);
        ErrorLog::writeLogV2($depth, $index, $this->module, 'non', 'non', 0);
        $res = GlobalVar::$CHECK_SUCCESS;
        return $res;
    }
}