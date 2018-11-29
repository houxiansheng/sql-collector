<?php
namespace USQL\Library\SqlRestraint\Module;

use USQL\Library\SqlRestraint\Abstracts\HandlerAbstract;
use USQL\Library\SqlRestraint\Common\GlobalVar;

class Group extends HandlerAbstract
{

    protected $module = 'group';

    public function handler($index, array $fields, $parentModule = null)
    {
        return GlobalVar::$CHECK_SUCCESS;
    }
}