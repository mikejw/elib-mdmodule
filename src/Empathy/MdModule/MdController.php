<?php

namespace Empathy\MdModule;
use Empathy\MVC\Controller\CustomController;


class MdController extends CustomController
{

    public function default_event()
    {        
        $class_arr = explode('\\', get_class($this));
        $class = $class_arr[sizeof($class_arr)-1];
        $this->assign('md', MdModule::init($class));

        $this->assign('config', MdModule::getConfig());


        //return false;
    }
}
