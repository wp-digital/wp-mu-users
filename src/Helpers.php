<?php

namespace Innocode\WPMUUsers;

/**
 * Class Helpers
 *
 * @package Innocode\WPMUUsers
 */
final class Helpers
{
    /**
     * @param $class
     *
     * @return string
     */
    public static function get_class_short_name( $class )
    {
        $short_name = '';

        try {
            $reflection_class = new \ReflectionClass( $class );
            $short_name = $reflection_class->getShortName();
        } catch ( \ReflectionException $exception ) {}

        return $short_name;
    }
}
