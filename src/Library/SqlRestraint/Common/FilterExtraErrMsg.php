<?php
namespace USQL\Library\SqlRestraint\Common;

use USQL\Library\Config;

class FilterExtraErrMsg
{

    protected static $sql = [];

    public static function getMsg()
    {
        $errArr = \USQL\Library\SqlRestraint\Common\ErrorLog::getLog();
        \USQL\Library\SqlRestraint\Common\ErrorLog::destoryErrMsg();
        $doubleOperate = CommonTool::doubleOperate();
        // 过滤重复sql
        $preDepth = $preIndex = $preModule = $preType = $preMsg = $preLevel = null;
        $tmpErrMsg = [];
        foreach ($errArr as $depthTmp => $indexVal) {
            $depth = $depthTmp;
            ksort($indexVal);
            foreach ($indexVal as $indexTmp => $moduleVal) {
                foreach ($moduleVal as $moduleTmp => $typeVal) {
                    foreach ($typeVal as $typeTmp => $msgVal) {
                        if ($preMsg != null) {
                            $newMsg = strtoupper($preMsg) . ' ' . strtoupper($msgVal['msg']);
                            if (isset($doubleOperate[$newMsg])) {
                                $tmpErrMsg[] = $doubleOperate[$newMsg];
                                $preDepth = $preIndex = $preModule = $preType = $preMsg = $preLevel = null;
                                continue;
                            }elseif(strtoupper($msgVal['msg'])=='IN'){
                                continue;
                            } else {
                                $tmpErrMsg[] = [
                                    'module' => $preModule,
                                    'type' => $preType,
                                    'msg' => $preMsg,
                                    'level' => $preLevel
                                ];
                            }
                        }
                        $preDepth = $depthTmp;
                        $preIndex = $indexTmp;
                        $preModule = $moduleTmp;
                        $preType = $typeTmp;
                        $preMsg = $msgVal['msg'];
                        $preLevel = $msgVal['level'];
                    }
                }
            }
        }
        if(!is_null($preDepth)){
            $tmpErrMsg[] = [
                'module' => $preModule,
                'type' => $preType,
                'msg' => $preMsg,
                'level' => $preLevel
            ];
        }
        $newErrMsg=[];
        foreach ($tmpErrMsg as $single){
            $newErrMsg[]=$single['level'].'-'.$single['module'].'-'.$single['type'].'-'.$single['msg'];
        }
        return array_unique($newErrMsg);
    }
}