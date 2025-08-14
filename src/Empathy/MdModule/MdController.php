<?php

namespace Empathy\MdModule;

use Empathy\MVC\Controller\CustomController;
use Empathy\MVC\DI;


class MdController extends CustomController
{
    protected $module;
    protected $package;
    protected $mdFile;
    protected $webFile;
    protected $docTitle;

    public function default_event()
    {
        $contents = array();
        $class_arr = explode('\\', get_class($this));
        $class = $class_arr[sizeof($class_arr)-1];

        $md = DI::getContainer()->get('MdModule');

        $this->assign('md', $md->init($class));
        $this->assign('config', $md->getConfig());

        if (($redirect = $md->getRedirect())) {
            $this->assign('redirect', $redirect);
            return true;
        }

        $web_file = $md->getWebFile();
        $this->webFile = $web_file;

        $this->assign('web_file', $web_file);
        $this->assign('index', $md->getIndex());
        $this->assign('file', $md->getFile());

        try {
            $contents = $md->getContents();
        } catch(\Exception $e) {
            //
        }

        $this->assign('adoc_mode', $md->getAdocMode());

        $this->assign('comments_enabled', $md->getComments());

        $this->module = $_GET['module'];
        $this->package = $md->getPackage();
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
            if ($item['active']) {
                $this->docTitle = $item['title'];
            }
        }
        $this->assign('contents', $contents);
    }
}

