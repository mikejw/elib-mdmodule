<?php

use Empathy\MdModule\MdModule;

return [
    'MdModule' => function (\DI\Container $c) {
        return new MdModule();
    },
    'MdRedirects' => true,
    'MdDefaultIndex' => 'README'
];