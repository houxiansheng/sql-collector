<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\ErrorLog;
use USQL\Library\SqlRestraint\Common\GlobalVar;
use USQL\Library\Config;

class Limit extends HandlerAbstract
{

    protected $module = 'limit';

    public function handler($index, array $fields, $parentModule = null)
    {
        $limitArr =explode('offset', $fields['rowcount']);
        if(count($limitArr)>1){
            $fields['rowcount']=intval($limitArr[0]);
            $fields['offset']=intval($limitArr[1]);
        }
        $offset = intval($fields['offset']);
        $rowcount = intval($fields['rowcount']);
        $offSet=Config::get('sql.off_set');
        $rowCount=Config::get('sql.row_count');
        if ($offset > $offSet) {
            ErrorLog::writeLog('7-' . $this->module . '-offset-' . $offset);
        }
        if ($rowcount > $rowCount) {
            ErrorLog::writeLog('7-' . $this->module . '-rowcount-' . $rowcount);
        }
        return GlobalVar::$CHECK_SUCCESS;
    }
}