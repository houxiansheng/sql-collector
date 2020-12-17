<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\ErrorLog;
use USQL\Library\SqlRestraint\Common\GlobalVar;

class Where extends HandlerAbstract
{

    protected $module = 'where';
    use Recursion;

    protected function aggregateFun($index, $fields, $parentModule = null, $depth = 0)
    {
        $exist = false;
        if (isset($fields['sub_tree'])) {
            foreach ($fields['sub_tree'] as $single) {
                if (isset($single['expr_type']) && $single['expr_type'] == 'colref' && $single['base_expr'] != '?') {
                    $exist = true;
                }
            }
        }
        if($exist){
            ErrorLog::writeLogV2($depth, $index, $this->module, 'fun', $fields['base_expr'], 3);
        }
        // ErrorLog::writeLog($depth . '-' . $index . '-3-' . $this->module . '-fun-' . $fields['base_expr']);
        return GlobalVar::$CHECK_SUCCESS;
    }
}