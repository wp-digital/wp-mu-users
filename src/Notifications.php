<?php

namespace Innocode\WPMUUsers;

/**
 * Class Notifications
 *
 * @package Innocode\WPMUUsers
 */
final class Notifications
{
    /**
     * @param string $user_login
     */
    public static function duplicate_user_login_warning( $user_login )
    {
        $site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
        wp_mail(
            get_site_option( 'admin_email' ),
            sprintf( __( '[%s][WARNING] Duplicate user login', 'innocode-wp-mu-users' ), $site_name ),
            sprintf(
                __(
                    'User login: %1$s
Site: %2$s
Network: %3$s'
                ),
                $user_login,
                get_site_url(),
                network_site_url()
            )
        );
    }
}
