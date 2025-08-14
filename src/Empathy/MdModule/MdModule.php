<?php

namespace Empathy\MdModule;

use Michelf\Markdown;
use Empathy\MVC\Config;
use Empathy\MVC\DI;
use Empathy\MVC\RequestException;

class MdModule
{
    private $md;
    private $config;
    private $class;
    private $web_file;
    private $index;
    private $file;
    private $adoc_mode = false;
    private $comments = false;
    private $md_dir;
    private $redirect = "";

    private $package = '';

    public function getConfig()
    {
        return $this->config;
    }


    public function init($class)
    {
        $this->adoc_mode = (defined('ELIB_MD_ADOC_MODE') && ELIB_MD_ADOC_MODE);
        $this->comments = (defined('ELIB_MD_COMMENTS') && ELIB_MD_COMMENTS);

        $ext = !$this->adoc_mode ? 'md' : 'adoc';
        $defaultIndex = DI::getContainer()->get('MdDefaultIndex');
        $defaultFile = "$defaultIndex.$ext";

        $this->config = array();
        $this->class = $class;
        if (isset($_GET['file']) && $_GET['file'] != '') {
            $this->md = substr($_GET['file'], strlen($this->class) + 1);
        } elseif (isset($_GET['md'])) {
            $this->md = $_GET['md'];
        } else {
            $this->md = $defaultFile;
        }

        $root_dir = Config::get('DOC_ROOT') . "/$ext";

        $this->file = $root_dir . '/' . $this->md;
        $this->web_file = '/' . $this->class . '/' . $this->md;
        $this->index = false;

        # auth stuff
        if (!is_dir($this->file)) {
            $md_dir = dirname(realpath($this->file));
        } else {
            $md_dir = $this->file;
            $this->index = true;
        }

        if (preg_match('/\/$/', $md_dir)) {
            $md_dir = substr($md_dir, 0, -1);
        }

        $this->md_dir = $md_dir;

        $this->loadConfig($this->md_dir);

        if (isset($this->config['redirect'])) {
            $this->doRedirect('/' . $this->class . '/' . $this->config['redirect']);
        }

        $this->doAuth($this->md_dir);

        if ($this->index == false) {
            if (!file_exists($this->file)) {
                throw new RequestException('Not found', RequestException::NOT_FOUND);
            }

            $output = $this->processFile($this->file, $this->adoc_mode);
        } else {
            $proto = (\Empathy\MVC\Util\Misc::isSecure()) ? 'https' : 'http';
            $web_file = rtrim($this->web_file, '/') . '/';

            if (file_exists($this->file . "/$defaultFile")) {
                $this->doRedirect($web_file . $defaultFile);
            }

            $output = scandir($this->md_dir);

            foreach ($output as $index => $value) {
                if (strpos($value, ".$ext") == false) {
                    unset($output[$index]);
                } else {
                    $link = $output[$index];
                    $link = '<p><a href="./' . $link . '">' . $link . '</a></p>';
                    $output[$index] = $link;
                }
            }
            $output = implode("\n", $output);
        }

        if (isset($_GET['raw']) && $_GET['raw'] == 'true') {
            header('Content-type: text/plain');
            echo file_get_contents($this->file);
            exit();
        }

        if (preg_match('/\//', $this->md)) {
            $packageArr = explode('/', $this->md);
            if (is_array($packageArr) && count($packageArr)) {
                $this->package = $packageArr[0];
            }
        }

        $this->debugLog();

        return $output;
    }

    public function debugLog()
    {
        $log =
            DI::getContainer()->get('LoggingOn')
                ? DI::getContainer()->get('Log')
                : false;
        if ($log !== false) {
            $log->debug(json_encode([
                'md_dir' => $this->md_dir,
                'md' => $this->md,
                'config' => $this->config,
                'class' => $this->class,
                'web_file' => $this->web_file,
                'index' => $this->index,
                'file' => $this->file,
                'adoc_mode' => $this->adoc_mode,
                'comments' => $this->comments,
                'package' => $this->package
            ]));
        }
    }

    public function getMd()
    {
        return $this->md;
    }


    public function getIndex()
    {
        return $this->index;
    }


    public function getWebFile()
    {
        return $this->web_file;
    }


    public function getFile()
    {
        return $this->file;
    }


    private function processFile($file, $adoc_mode)
    {
        if ($adoc_mode) {
            return file_get_contents($file);
        } else {
            if (DI::getContainer()->get('CacheEnabled')) {
                return DI::getContainer()->get('Cache')->cachedCallback('markdown_' . $file, [$this->class, 'transform'], [$file]);
            } else {
                return $this->transform($file);
            }
        }
    }

    public function transform($file)
    {
        return Markdown::defaultTransform(file_get_contents($file));
    }

    private function doRedirect($redirect)
    {
        $proto = (\Empathy\MVC\Util\Misc::isSecure()) ? 'https' : 'http';
        $this->redirect = $redirect;
        if (DI::getContainer()->get('MdRedirects')) {
            $loc = $proto . '://' . Config::get('WEB_ROOT') . Config::get('PUBLIC_DIR') . $redirect;
            header('Location: ' . $loc);
            exit();
        }
    }

    public function getContents()
    {
        if (!isset($this->config['contents'])) {
            throw new \Exception('No contents defined.');
        }
        return $this->config['contents'];
    }


    private function loadConfig($md_dir)
    {
        $docsPrefix = '';
        $key = -1;
        $dirArr = explode('/', $md_dir);
        if ($dirArr[sizeof($dirArr) - 1] !== 'md') {
            $key = array_search('docs', $dirArr);
            if ($dirArr[sizeof($dirArr) - 1] == 'docs') {
                array_pop($dirArr);
                $docsPrefix = '/docs';
            } elseif (!$key) {
                $docsPrefix = '/docs';
            }
        }
        $md_dir = implode('/', $dirArr) . $docsPrefix;
        $config_file = $md_dir . '/config.json';

        if (file_exists($config_file)) {
            $this->config = json_decode(file_get_contents($config_file), true);
        }
    }

    public function doAuth($md_dir)
    {
        $realm = 'Restricted docs area';
        $validated = false;

        if (isset($this->config['auth'])) {

            if (isset($_SERVER['PHP_AUTH_USER'])) {

                $pass_data = $this->config['auth'];
                $valid_passwords = array($pass_data['user'] => $pass_data['password']);
                $valid_users = array_keys($valid_passwords);
                $user = $_SERVER['PHP_AUTH_USER'];
                $pass = $_SERVER['PHP_AUTH_PW'];
                $validated = (in_array($user, $valid_users)) && ($pass == $valid_passwords[$user]);
            }
            if (!$validated) {
                header('WWW-Authenticate: Basic realm="' . $realm . '"');
                header('HTTP/1.0 401 Unauthorized');
                die ("Not authorized");
            }
        }
    }

    public function getAdocMode()
    {
        return $this->adoc_mode;
    }

    public function getComments()
    {
        return $this->comments;
    }

    public function getPackage()
    {
        return $this->package;
    }

    public function getRedirect()
    {
        return $this->redirect;
    }
}
