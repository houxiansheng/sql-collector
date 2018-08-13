<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\ErrorLog;
use USQL\Library\SqlRestraint\Common\GlobalVar;

class Where extends HandlerAbstract
{

    protected $module = 'where';

    protected function aggregateFun($index, $fields)
    {
        ErrorLog::writeLog('3-' . $this->module . '-fun-' . $fields['base_expr']);
        return GlobalVar::$CHECK_SUCCESS;
    }
}