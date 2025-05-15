<?php

namespace Empathy\MdModule;
use Empathy\MVC\Controller\CustomController;


class MdController extends CustomController
{
    protected $module;
    protected $package;
    protected $mdFile;
    protected $webFile;

    public function default_event()
    {
        $contents = array();
        $class_arr = explode('\\', get_class($this));
        $class = $class_arr[sizeof($class_arr)-1];

        $this->assign('md', MdModule::init($class));
        $this->assign('config', MdModule::getConfig());

        $web_file = MdModule::getWebFile();
        $this->webFile = $web_file;

        $this->assign('web_file', $web_file);
        $this->assign('index', MdModule::getIndex());
        $this->assign('file', MdModule::getFile());

        try {
            $contents = MdModule::getContents();
        } catch(\Exception $e) {
            //
        }

        $this->assign('adoc_mode', MdModule::getAdocMode());

        $this->assign('comments_enabled', MdModule::getComments());

        $this->module = $_GET['module'];
        $this->package = MdModule::getPackage();
        $this->assign('package', $this->package);

        $web_base_arr = explode('/', preg_replace('/^(\/)/', '', $web_file));
        $web_base_arr = array_filter($web_base_arr, function($v, $k) use ($web_base_arr) {
           return (
             (
                 $web_base_arr[sizeof($web_base_arr) - 2] !== 'docs' ||
                 (($k === 0 || $v !== 'docs') &&
                 !($v === 'docs' && isset($web_bar_arr[$k + 1]) && $web_base_arr[$k + 1] === 'docs'))
             )
           );
        }, ARRAY_FILTER_USE_BOTH);
        $file = array_pop($web_base_arr);
        $this->assign('web_base', implode('/', $web_base_arr));

        $this->mdFile = $file;
        $this->assign('md_file', $file);

        foreach ($contents as &$item) {
            $item['active'] = strpos($item['file'], $file) !== false;
        }
        $this->assign('contents', $contents);
    }
}

