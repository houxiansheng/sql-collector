<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\ErrorLog;
use USQL\Library\SqlRestraint\Common\GlobalVar;

class Limit extends HandlerAbstract
{

    protected $module = 'limit';

    public function handler($index, array $fields)
    {
        $offset = intval($fields['offset']);
        $rowcount = intval($fields['rowcount']);
        if ($offset > 1000) {
            ErrorLog::writeLog('2-' . $this->module . '-offset-' . $offset);
        }
        if ($rowcount > 10000) {
            ErrorLog::writeLog('2-' . $this->module . '-rowcount-' . $rowcount);
        }
        return GlobalVar::$CHECK_SUCCESS;
    }
}