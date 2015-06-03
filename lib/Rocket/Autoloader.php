<?php

namespace Rocket;

require_once dirname(__FILE__).'/../../vendors/composer/autoload.php';

class Autoloader
{
    /**
     * Registers Rocket\Autoloader as an SPL autoloader.
     */
    public static function register()
    {
        ini_set('unserialize_callback_func', 'spl_autoload_call');
        spl_autoload_register(array(new self(), 'autoload'));
    }

    /**
     * Handles autoloading of classes.
     *
     * @param string $class A class name.
     *
     * @return boolean Returns true if the class has been loaded
     */
    public static function autoload($class)
    {
        if (0 !== strpos($class, 'Rocket')) {
            return;
        }

        if (file_exists($file = dirname(__FILE__).'/../'.str_replace('\\', '/', $class).'.php')) {
            require $file;
        }
    }
}
