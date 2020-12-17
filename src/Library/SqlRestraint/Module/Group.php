<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\GlobalVar;

class Group extends HandlerAbstract
{

    protected $module = 'group';
    use Recursion;

    public function handler($index, array $fields, $parentModule = null, $depth = 0)
    {
        return GlobalVar::$CHECK_SUCCESS;
    }
}