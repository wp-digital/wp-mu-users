<?php

namespace Innocode\WPMUUsers;

/**
 * Class Url
 *
 * @package Innocode\WPMUUsers
 */
final class Url
{
    public static function register()
    {
        add_filter( 'network_site_url', [
            __CLASS__,
            'filter_network_site_url'
        ], 10, 3 );
        add_filter( 'user_admin_url', [
            __CLASS__,
            'filter_user_admin_url'
        ], 10, 2 );
    }

    /**
     * @param string      $url
     * @param string      $path
     * @param string|null $scheme
     *
     * @return string
     */
    public static function filter_network_site_url( $url, $path, $scheme )
    {
        if ( in_array( $scheme, [
            'login',
            'login_post',
        ] ) || in_array( $path, [
            '/wp-activate.php'
            ] )
        ) {

            return site_url( $path, $scheme );
        }

        return $url;
    }

    /**
     * @param string $url
     * @param string $path
     *
     * @return string
     */
    public static function filter_user_admin_url( $url, $path )
    {
        return admin_url( $path );
    }
}
