<?php
namespace USQL\Library\SqlRestraint;

use USQL\Library\SqlRestraint\Common\GlobalVar;
use USQL\Library\SqlRestraint\Common\FilterExtraErrMsg;
use USQL\Library\SqlRestraint\Module\Recursion;

class Restraint
{

    protected $status = true;
    use Recursion;

    public function __construct()
    {}

    public function hander($sql, $parseArr)
    {
        $depth = 1;
        GlobalVar::setSql($sql);
        GlobalVar::registerModule();
        
        $this->recursion($parseArr,$depth);
        $err = FilterExtraErrMsg::getMsg();
        return $err;
    }
}