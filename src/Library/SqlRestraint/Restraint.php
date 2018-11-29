<?php
namespace USQL\Library\SqlRestraint;

use USQL\Library\SqlRestraint\Common\GlobalVar;

class Restraint
{

    protected $register = [];

    protected $status = true;

    protected $errMsg = [];

    public function __construct()
    {
        $this->register('SELECT', \USQL\Library\SqlRestraint\Module\Select::class);
        $this->register('DELETE', \USQL\Library\SqlRestraint\Module\Delete::class);
        $this->register('FROM', \USQL\Library\SqlRestraint\Module\From::class);
        $this->register('WHERE', \USQL\Library\SqlRestraint\Module\Where::class);
        $this->register('GROUP', \USQL\Library\SqlRestraint\Module\Group::class);
        $this->register('ORDER', \USQL\Library\SqlRestraint\Module\Order::class);
        $this->register('LIMIT', \USQL\Library\SqlRestraint\Module\Limit::class);
        $this->register('UPDATE', \USQL\Library\SqlRestraint\Module\Update::class);
        $this->register('SET', \USQL\Library\SqlRestraint\Module\Set::class);
    }

    protected function register($type, $className)
    {
        $this->register[$type] = [
            'className' => $className,
            'object' => ''
        ];
    }

    public function hander($parseArr)
    {
        foreach ($parseArr as $key => $val) {
            if ($this->getHandleObject($key)) {
                $this->recursion($key, $val);
            }
        }
        $err = \USQL\Library\SqlRestraint\Common\ErrorLog::getLog();
        \USQL\Library\SqlRestraint\Common\ErrorLog::destoryErrMsg();
        return $err;
    }

    protected function recursion($module, $content)
    {
        if ($module == 'LIMIT') {
            $res = $this->getHandleObject($module)->handler(0, $content);
            return;
        }
        if ($module == 'DELETE') {
            $res = $this->getHandleObject($module)->handler(0, $content);
            return;
        }
        foreach ($content as $key => $val) {
            if (is_numeric($key)) {
                $res = $this->getHandleObject($module)->handler($key, $val);
                if ($res == GlobalVar::$CHECK_RECURION) {
                    // 此时一般都遍历subTree
                    foreach ($val['sub_tree'] as $subKey => $subVal) {
                        $this->recursion($subKey, $subVal);
                    }
                } elseif ($res == GlobalVar::$CHECK_FAIL) {}
            } else {
                $this->recursion($key, $val);
            }
        }
    }

    protected function getHandleObject($module)
    {
        if (! isset($this->register[$module])) {
            $oldModule = $module;
            $module = 'UNHANDLED';
        }
        if (! is_object($this->register[$module]['object'])) {
            $oldModule = isset($oldModule) ? $oldModule : $module;
            $this->register[$module]['object'] = new $this->register[$module]['className']($oldModule);
        }
        return $this->register[$module]['object'];
    }
}