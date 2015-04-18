<?php

namespace Empathy\MdModule;

//define('PERLBIN', '/opt/local/bin/perl');
//define('MD', '/opt/local/bin/Markdown.pl');

define('PERLBIN', '');
define('MD', 'Markdown.pl');



class MdModule
{
    
    public static function init($class)
    {

        if (isset($_GET['file']) && $_GET['file'] != '') {
            $md = substr($_GET['file'], strlen($class)+1);
        } elseif (isset($_GET['md'])) {
            $md = $_GET['md'];
        } else {
            $md = 'README.md';
            //$md = 'README_BIZ.md';
            //$md = 'README_BIZ.md';
            //$md = 'fixtures.md';
            //$md = 'mba/MBA_REVIEW.md';

        }

        $root_dir = DOC_ROOT.'/md';
        //$root_dir = '/var/www/passports-lost-stolen';
        //$root_dir = '/var/www/bullpup';
        //$readme = 'building.md';


        $file = $root_dir.'/'.$md;
        $web_file = "/$class/".$md;
        $index = false;


        # auth stuff
        if (!is_dir($file)) {
            $md_dir = dirname(realpath($file));
        } else {
            $md_dir = $file;
            $index = true;
        }

        //echo $file;

        self::doAuth($md_dir);

        //$exec = PERLBIN.' '.MD.' '.$file;
        $exec = 'markdown '.$file;


        if ($index == false) {
            

            if (!file_exists($file)) {
                die('Source file not found.');
            }
            $output = self::processFile($file);
        } else {

            
            if (file_exists($file.'/README.md')) {
                header('Location: http://'.WEB_ROOT.PUBLIC_DIR.$web_file.'README.md');
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
            echo file_get_contents($file);
            exit();
        }

        return implode("\n", $output);
        // self::printHeader($file);
        // echo implode("\n", $output);
        // self::printFooter($web_file, $index);
    }


    private static function processFile($file)
    {
        $exec = PERLBIN.' '.MD.' '.$file;
        $output = array();
        exec($exec, $output);
        return $output;
    }


    public static function printHeader($file)
    {
        echo <<<EOT
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<title>$file</title>
<link rel="stylesheet" href="/notes/md.css" type="text/css" media="all" />
<meta name="viewport" content="initial-scale=1.0, width=device-width" />
</head>
<body>
<div id="page">
<img width=290 src="https://pbs.twimg.com/profile_images/1776294323/new_logo_glow_noise_b.jpg" alt="" />
<p>&nbsp;</p>
EOT;
    }

    public static function printFooter($md, $index)
    {
        if ($index) {
        $foot = <<<EOT
<p id="top"><a href="#">#top</a></p>
</div>
</body>
</html>
EOT;
    } else {
    $foot = <<<EOT
<p id="top"><a href="$md?raw=true">View source</a> - <a href="#">#top</a></p>
</div>
</body>
</html>
EOT;
        }
        echo $foot;
    }

    public static function doAuth($md_dir)
    {
        if (file_exists($md_dir.'/pass.json')) {
            $realm = 'Restricted docs area';
            $pass_data = json_decode(file_get_contents($md_dir.'/pass.json'), true);
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
