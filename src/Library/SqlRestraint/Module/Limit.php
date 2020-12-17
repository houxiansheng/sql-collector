<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\ErrorLog;
use USQL\Library\SqlRestraint\Common\GlobalVar;
use USQL\Library\Config;

class Limit extends HandlerAbstract
{

    protected $module = 'limit';
    use Recursion;

    public function handler($index, array $fields, $parentModule = null, $depth = 0)
    {
        $limitArr = explode('offset', $fields['rowcount']);
        if (count($limitArr) > 1) {
            $fields['rowcount'] = intval($limitArr[0]);
            $fields['offset'] = intval($limitArr[1]);
        }
        $offset = intval(trim($fields['offset'],'\'|"'));
        $rowcount = intval(trim($fields['rowcount'],'\'|"'));
        $offSetConfig = Config::get('sql.off_set');
        $rowCountConfig = Config::get('sql.row_count');
        if ($offset > $offSetConfig) {
            // ErrorLog::writeLog($depth.'-'.$index.'-7-' . $this->module . '-offset-' . $offset);
            ErrorLog::writeLogV2($depth, $index, $this->module, 'offset', $offset, 7);
        }
        if ($rowcount > $rowCountConfig) {
            // ErrorLog::writeLog($depth.'-'.$index.'-7-' . $this->module . '-rowcount-' . $rowcount);
            ErrorLog::writeLogV2($depth, $index, $this->module, 'rowcount', $rowcount, 7);
        }
        return GlobalVar::$CHECK_SUCCESS;
    }
}