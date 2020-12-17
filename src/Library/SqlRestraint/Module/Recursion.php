<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Common\GlobalVar;

trait Recursion
{
    protected function recursion($query,$depth=0)
    {
        foreach ($query as $module=>$content){
            if ($module == 'EXPLAIN') {//存在explain默认不触犯规范
                break;
            }
            if ($module == 'LIMIT') {
                $res = $this->getHandleObject($module)->handler(0, $content, null, $depth);
                continue;
            }
            if ($module == 'DELETE') {
                $res = $this->getHandleObject($module)->handler(0, $content, null, $depth);
                continue;
            }
            if($module == 'TRUNCATE'){
                //暂不处理
                continue ;
            }
            if(!is_array($content)){
                continue;
            }
            foreach ($content as $key => $val) {
                if(!is_array($val)){
                    continue;
                }
                $res = $this->getHandleObject($module)->handler($key, $val, null, $depth);
            }
        }
    }

    protected function getHandleObject($module)
    {
        if (! isset(GlobalVar::$registerModule[$module])) {
            $oldModule = $module;
            $module = 'UNHANDLED';
        }
        if (! is_object(GlobalVar::$registerModule[$module]['object'])) {
            $oldModule = isset($oldModule) ? $oldModule : $module;
            GlobalVar::$registerModule[$module]['object'] = new GlobalVar::$registerModule[$module]['className']($oldModule);
        }
        return GlobalVar::$registerModule[$module]['object'];
    }
}