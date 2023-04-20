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

        $web_file = MdModule::getWebFile();

        $this->assign('web_file', $web_file);
        $this->assign('index', MdModule::getIndex());
        $this->assign('file', MdModule::getFile());

        try {
            $this->assign('contents', MdModule::getContents());
        } catch(\Exception $e) {
            //
        }

        $this->assign('adoc_mode', MdModule::getAdocMode());

        $this->assign('comments_enabled', MdModule::getComments());

        $web_base_arr = explode('/', preg_replace('/^(\/)/', '', $web_file));
        $file = array_pop($web_base_arr);
        $this->assign('web_base', implode('/', $web_base_arr));;
        $this->assign('md_file', $file);
    }
}

