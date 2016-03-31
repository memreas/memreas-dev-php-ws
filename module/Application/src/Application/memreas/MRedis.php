<?php

class M
{
    /**
     * Construct won't be called inside this class and is uncallable from
     * the outside. This prevents instantiating this class.
     * This is by purpose, because we want a static class.
     */
    private function __construct() {}
    private static $greeting = 'Hello';
    private static $initialized = false;

    private static function initialize()
    {
        if (self::$initialized)
            return;

        self::$greeting .= ' There!';
        self::$initialized = true;
    }

    public static function greet()
    {
        self::initialize();
        echo self::$greeting;
    }
}

Hello::greet(); // Hello There!


?>