<?php

namespace Empathy\MdModule;

use Michelf\Markdown;
use Empathy\MVC\Config;
use Empathy\MVC\DI;
use Empathy\MVC\RequestException;

class MdModule
{
    private static $md;
    private static $config;
    private static $class;
    private static $web_file;
    private static $index;
    private static $file;
    private static $adoc_mode = false;
    private static $comments = false;
    private static $md_dir;

    private static $package = '';

    public static function getConfig()
    {
        return self::$config;
    }


    public static function init($class)
    {
        self::$adoc_mode = (defined('ELIB_MD_ADOC_MODE') && ELIB_MD_ADOC_MODE);
        self::$comments = (defined('ELIB_MD_COMMENTS') && ELIB_MD_COMMENTS);

        $ext = !self::$adoc_mode ? 'md' : 'adoc';

        self::$config = array();
        self::$class = $class;
        if (isset($_GET['file']) && $_GET['file'] != '') {
            self::$md = substr($_GET['file'], strlen(self::$class) + 1);
        } elseif (isset($_GET['md'])) {
            self::$md = $_GET['md'];
        } else {
            self::$md = "README.$ext";
        }

        $root_dir = Config::get('DOC_ROOT') . "/$ext";

        self::$file = $root_dir . '/' . self::$md;
        self::$web_file = '/' . self::$class . '/' . self::$md;
        self::$index = false;

        # auth stuff
        if (!is_dir(self::$file)) {
            $md_dir = dirname(realpath(self::$file));
        } else {
            $md_dir = self::$file;
            self::$index = true;
        }

        if (preg_match('/\/$/', $md_dir)) {
            $md_dir = substr($md_dir, 0, -1);
        }

        self::$md_dir = $md_dir;

        self::loadConfig(self::$md_dir);

        self::doRedirect();

        self::doAuth(self::$md_dir);

        if (self::$index == false) {
            if (!file_exists(self::$file)) {
                throw new RequestException('Not found', RequestException::NOT_FOUND);
            }

            $output = self::processFile(self::$file, self::$adoc_mode);
        } else {
            $proto = (\Empathy\MVC\Util\Misc::isSecure()) ? 'https' : 'http';
            $web_file = rtrim(self::$web_file, '/') . '/';

            if (file_exists(self::$file . "/README.$ext")) {
                header('Location: ' . $proto . '://' . Config::get('WEB_ROOT') . Config::get('PUBLIC_DIR') . $web_file . "README.$ext");
                exit();
            }

            $output = scandir(self::$md_dir);

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
            echo file_get_contents(self::$file);
            exit();
        }

        if (preg_match('/\//', self::$md)) {
            $packageArr = explode('/', self::$md);
            if (is_array($packageArr) && count($packageArr)) {
                self::$package = $packageArr[0];
            }
        }

        self::debugLog();

        return $output;
    }

    public static function debugLog()
    {
        $log =
            DI::getContainer()->get('LoggingOn')
                ? DI::getContainer()->get('Log')
                : false;
        if ($log !== false) {
            $log->debug(json_encode([
                'md_dir' => self::$md_dir,
                'md' => self::$md,
                'config' => self::$config,
                'class' => self::$class,
                'web_file' => self::$web_file,
                'index' => self::$index,
                'file' => self::$file,
                'adoc_mode' => self::$adoc_mode,
                'comments' => self::$comments,
                'package' => self::$package
            ]));
        }
    }

    public static function getMd()
    {
        return self::$md;
    }


    public static function getIndex()
    {
        return self::$index;
    }


    public static function getWebFile()
    {
        return self::$web_file;
    }


    public static function getFile()
    {
        return self::$file;
    }


    private static function processFile($file, $adoc_mode)
    {
        if ($adoc_mode) {
            return file_get_contents($file);
        } else {
            if (DI::getContainer()->get('CacheEnabled')) {
                return DI::getContainer()->get('Cache')->cachedCallback('markdown_' . $file, [self::class, 'transform'], [$file]);
            } else {
                return self::transform($file);
            }
        }
    }

    public static function transform($file)
    {
        return Markdown::defaultTransform(file_get_contents($file));
    }


    private static function doRedirect()
    {
        $proto = (\Empathy\MVC\Util\Misc::isSecure()) ? 'https' : 'http';

        if (isset(self::$config['redirect'])) {
            $loc = $proto . '://' . Config::get('WEB_ROOT') . Config::get('PUBLIC_DIR') . '/' . self::$class . '/' . self::$config['redirect'];
            header('Location: ' . $loc);
            exit();
        }
    }


    public static function getContents()
    {
        if (!isset(self::$config['contents'])) {
            throw new \Exception('No contents defined.');
        }

        return self::$config['contents'];
    }


    private static function loadConfig($md_dir)
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
            self::$config = json_decode(file_get_contents($config_file), true);
        }
    }

    public static function doAuth($md_dir)
    {
        $realm = 'Restricted docs area';
        $validated = false;

        if (isset(self::$config['auth'])) {

            if (isset($_SERVER['PHP_AUTH_USER'])) {

                $pass_data = self::$config['auth'];
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

    public static function getAdocMode()
    {
        return self::$adoc_mode;
    }

    public static function getComments()
    {
        return self::$comments;
    }

    public static function getPackage()
    {
        return self::$package;
    }
}
