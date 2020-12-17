<?php
namespace USQL\Library\SqlRestraint\Abstracts;

use USQL\Library\SqlRestraint\Common\ErrorLog;
use USQL\Library\SqlRestraint\Common\CommonTool;
use USQL\Library\SqlRestraint\Common\GlobalVar;
use USQL\Library\Config;

abstract class HandlerAbstract
{

    protected $module = null;

    public function __construct($module = null)
    {
        $this->module = $this->module ? $this->module : strtolower($module);
    }

    public function handler($index, array $fields, $parentModule = null, $depth = 0)
    {
        $exprType = isset($fields['expr_type']) ? $fields['expr_type'] : '';
        switch ($exprType) {
            case 'subquery': // 存在子查询，返回继续遍历
                $res = GlobalVar::$CHECK_SUCCESS;
                $this->recursion($fields['sub_tree'], $depth);
                break;
            case 'match-arguments':
                $res = $this->matchArguments($index, $fields, $depth);
                break;
            case 'match-mode':
                $res = GlobalVar::$CHECK_SUCCESS;
                break;
            case 'colref': // 列名
                $res = $this->colRef($index, $fields, $parentModule, $depth);
                break;
            case 'reserved': // 保留字段
                $res = GlobalVar::$CHECK_SUCCESS;
                break;
            case 'const': // 常量
                $res = GlobalVar::$CHECK_SUCCESS;
                break;
            case 'expression': // 表达式
                $res = $this->expression($index, $fields, $depth);
                break;
            case 'aggregate_function':
                $res = $this->aggregateFun($index, $fields, $depth);
                break;
            case 'function':
                $res = $this->functions($index, $fields, $depth);
                break;
            case 'operator': // 操作符
                $res = $this->operator($index, $fields, $depth);
                break;
            case 'table': // 表名
                $res = $this->table($index, $fields, $depth);
                break;
            case 'bracket_expression':
                $res = $this->bracketExpression($index, $fields, $depth);
                break;
            case 'in-list':
                $res = $this->inList($index, $fields, $depth);
                break;
            case 'table_expression':
                $res = $this->table($index, $fields, $depth);
                break;
            default:
                $res = GlobalVar::$CHECK_SUCCESS;
                // ErrorLog::writeLog($depth.'-'.$index.'-0-' . $this->module . '-' . $fields['expr_type']);
                ErrorLog::writeLogV2($depth, $index, $this->module, 'unanalysis', $exprType, 0);
                break;
        }
        return $res;
    }

    protected function matchArguments($index, $fields, $depth = 0)
    {
        if (isset($fields['sub_tree']) && $fields['sub_tree']) {
            foreach ($fields['sub_tree'] as $key => $val) {
                $this->handler($key, $val, null, ($depth + 1));
            }
        }
    }

    protected function expression($index, $fields, $depth = 0)
    {
        if (isset($fields['sub_tree']) && $fields['sub_tree']) {
            foreach ($fields['sub_tree'] as $key => $val) {
                $this->handler($key, $val, null, ($depth + 1));
            }
        }
        return GlobalVar::$CHECK_SUCCESS;
    }

    protected function colRef($index, $fields, $parentModule = null, $depth = 0)
    {
        // 别称定义
        if (isset($fields['alias']) && $fields['alias'] && CommonTool::keyWord($fields['alias']['no_quotes'])) {
            // ErrorLog::writeLog($depth.'-'.$index.'-2-' . $this->module . '-alias-' . $fields['alias']['no_quotes']);
            // ErrorLog::writeLogV2($depth, $index,$this->module,'alias','*',$fields['alias']['no_quotes'],2);
        }
        if (isset($fields['base_expr']) && $fields['base_expr'] && $fields['base_expr'] == '*') {
            if (isset($parentModule['expr_type']) && $parentModule['expr_type'] == 'aggregate_function' && $parentModule['base_expr'] == 'count') {} else {
                // ErrorLog::writeLog($depth.'-'.$index.'-2-' . $this->module . '-*');
                ErrorLog::writeLogV2($depth, $index, $this->module, 'fields', '*', 2);
            }
        }
    }

    protected function aggregateFun($index, $fields, $depth = 0)
    {
        if (isset($fields['alias']) && $fields['alias'] && CommonTool::keyWord($fields['alias']['no_quotes'])) {
            // ErrorLog::writeLog($depth.'-'.$index.'-2-' . $this->module . '-alias-' . $fields['alias']['no_quotes']);
            // ErrorLog::writeLogV2($depth, $index,$this->module,'alias',$fields['alias']['no_quotes'],2);
        }
        // 判断下函数是否禁用
        if (CommonTool::math($fields['base_expr'])) {
            // ErrorLog::writeLog($depth.'-'.$index.'-1-' . $this->module . '-fun-' . $fields['base_expr']);
            ErrorLog::writeLogV2($depth, $index, $this->module, 'fun', $fields['base_expr'], 1);
        }
        if (isset($fields['sub_tree']) && $fields['sub_tree']) {
            foreach ($fields['sub_tree'] as $key => $val) {
                $this->handler($key, $val, $fields, ($depth + 1));
            }
        }
        return GlobalVar::$CHECK_SUCCESS;
    }

    protected function functions($index, $fields, $depth = 0)
    {
        if ($this->module == 'where') { // where下禁用一切函数,暂不考虑左侧还是右侧
                                        // ErrorLog::writeLog($depth.'-'.$index.'-3-' . $this->module . '-fun-' . $fields['base_expr']);
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
        } else {
            if (CommonTool::math($fields['base_expr'])) {
                // ErrorLog::writeLog($depth.'-'.$index.'-1-' . $this->module . '-fun-' . $fields['base_expr']);
                ErrorLog::writeLogV2($depth, $index, $this->module, 'fun', $fields['base_expr'], 1);
            }
        }
        if (isset($fields['sub_tree']) && $fields['sub_tree']) {
            foreach ($fields['sub_tree'] as $key => $val) {
                $this->handler($key, $val, null, ($depth + 1));
            }
        }
        return GlobalVar::$CHECK_SUCCESS;
    }

    protected function table($index, $fields, $depth = 0)
    {
        if ($fields['expr_type'] == 'table_expression') {
            // 同为同一级，depth不再累加
            foreach ($fields['sub_tree'] as $key => $val) {
                $this->handler($key, $val, null, $depth);
            }
            return GlobalVar::$CHECK_SUCCESS;
        }
        // if (isset($fields['ref_clause']) && $fields['ref_clause']) {
        // $depth++;
        // foreach ($fields['ref_clause'] as $key => $single) {
        // $this->handler($key, $single, null,$depth);
        // }
        // }
        if (isset($fields['table']) && $fields['table']) {
            $moduleKey = 'table_' . $depth;
            $count = CommonTool::counter($moduleKey, $fields['table']);
            if ($count >= 3) {
                // ErrorLog::writeLog($depth.'-'.$index.'-9-' . $this->module . '-join-max');
                ErrorLog::writeLogV2($depth, $index, $this->module, 'join', 'max', 9);
            }
        }
        
        if (isset($fields['alias']) && $fields['alias'] && CommonTool::keyWord($fields['alias']['no_quotes'])) {
            // ErrorLog::writeLog($depth.'-'.$index.'-2-' . $this->module . '-alias-' . $fields['alias']['no_quotes']);
            // ErrorLog::writeLogV2($depth, $index,$this->module,'alias',$fields['alias']['no_quotes'],2);
        }
        return GlobalVar::$CHECK_SUCCESS;
    }

    protected function operator($index, $fields, $depth = 0)
    {
        $tmp = [
            'not',
            '<>',
            '!=',
            '!<',
            '!>',
            'like'
        ];
        if(strtolower($fields['base_expr']) == 'like'){
            ErrorLog::writeLogV2($depth, $index, $this->module, 'operator', $fields['base_expr'], 5);
        }elseif (in_array(strtolower($fields['base_expr']), $tmp)) {
            // ErrorLog::writeLog($depth.'-'.$index.'-4-' . $this->module . '-operator-' . $fields['base_expr']);
            ErrorLog::writeLogV2($depth, $index, $this->module, 'operator', $fields['base_expr'], 4);
        }
        return GlobalVar::$CHECK_SUCCESS;
    }

    protected function inList($index, $fields, $depth = 0)
    {
        $maxNum = Config::get('sql.list_max');
        if (isset($fields['sub_tree']) && $fields['sub_tree'] && count($fields['sub_tree']) > $maxNum) {
            // ErrorLog::writeLog($depth.'-'.$index.'-6-' . $this->module . '-in-list-max');
            ErrorLog::writeLogV2($depth, $index, $this->module, 'operate', 'in-max', 6);
        }
        return GlobalVar::$CHECK_SUCCESS;
    }

    protected function bracketExpression($index, $fields, $depth = 0)
    {
        if (isset($fields['sub_tree']) && $fields['sub_tree']) {
            // $depth ++;
            foreach ($fields['sub_tree'] as $key => $val) {
                $this->handler($key, $val, null, $depth);
            }
        }
        return GlobalVar::$CHECK_SUCCESS;
    }

    abstract protected function recursion($query, $depth = 0);

    abstract protected function getHandleObject($module);
}