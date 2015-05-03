<?php

namespace Empathy\MdModule;

define('PERLBIN', '/opt/local/bin/perl');
define('MD', '/opt/local/bin/Markdown.pl');



class MdModule
{
    private static $config;
    private static $class;
    private static $web_file;
    private static $index;
    private static $file;

    public static function getConfig()
    {
        return self::$config;
    }
    
    public static function init($class)
    {
        self::$class = $class;
        if (isset($_GET['file']) && $_GET['file'] != '') {
            $md = substr($_GET['file'], strlen(self::$class)+1);
        } elseif (isset($_GET['md'])) {
            $md = $_GET['md'];
        } else {
            $md = 'README.md';
        }
        $root_dir = DOC_ROOT.'/md';
     
        self::$file = $root_dir.'/'.$md;
        self::$web_file = '/'.self::$class.'/'.$md;
        self::$index = false;


        # auth stuff
        if (!is_dir(self::$file)) {
            $md_dir = dirname(realpath(self::$file));
        } else {
            $md_dir = self::$file;
            self::$index = true;
        }

        self::doAuth($md_dir);
        $exec = PERLBIN.' '.MD.' '.self::$file;
        

        if (self::$index == false) {
            if (!file_exists(self::$file)) {
                die('Source file not found.');
            }
            $output = self::processFile(self::$file);
        } else {

            if (file_exists(self::$file.'/README.md')) {
                header('Location: http://'.WEB_ROOT.PUBLIC_DIR.self::$web_file.'README.md');
                exit();
            }

            $output = scandir($md_dir);

            foreach ($output as $index => $value) {
                if (strpos($value, '.md') == false) {
                    unset($output[$index]);
                } else {
                    $link = $output[$index];
                    $link = '<p><a href="./'.$link.'">'.$link.'</a></p>';
                    $output[$index] = $link;
                }
            }

        }

        if (isset($_GET['raw']) && $_GET['raw'] == 'true') { 
            header('Content-type: text/plain');
            echo file_get_contents(self::$file);
            exit();
        }

        return implode("\n", $output);
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


    private static function processFile($file)
    {
        $exec = PERLBIN.' '.MD.' '.$file;
        $output = array();
        exec($exec, $output);

        return $output;
    }


    public static function doAuth($md_dir)
    {
        $config_file = 'config.json';
        if (file_exists($md_dir.'/'.$config_file)) {

            $realm = 'Restricted docs area';

            self::$config = json_decode(file_get_contents($md_dir.'/'.$config_file), true);

            if (isset(self::$config['redirect'])) {
                $loc = 'http://'.WEB_ROOT.PUBLIC_DIR.'/'.self::$class.'/'.self::$config['redirect'];
                header('Location: '.$loc);                
                exit();
            }
            elseif (isset(self::$config['auth'])) {
                $pass_data = self::$config['auth'];
                $valid_passwords = array ($pass_data['user'] => $pass_data['password']);
                $valid_users = array_keys($valid_passwords);
                $user = $_SERVER['PHP_AUTH_USER'];
                $pass = $_SERVER['PHP_AUTH_PW'];
                $validated = (in_array($user, $valid_users)) && ($pass == $valid_passwords[$user]);
                if (!$validated) {
                  header('WWW-Authenticate: Basic realm="'.$realm.'"');
                  header('HTTP/1.0 401 Unauthorized');
                  die ("Not authorized");
                }
            }
        }
    }
}
