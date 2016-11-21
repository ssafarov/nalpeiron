<?php

if (!trait_exists('MVC\Singleton')) {
    echo 'Please activate MVC-plugin';

    return;
}

spl_autoload_register('nalpeironAutoloader');
function nalpeironAutoloader($class)
{
    if (substr($class, 0, 10) == 'Nalpeiron\\') {
        $file = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 10)) . '.php';
        if (file_exists($file)) {
            require_once($file);
        } elseif (WP_DEBUG) {
            throw new Exception("File $file not found");
        }
    }
}