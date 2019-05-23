<?php

namespace Innocode\WPMUUsers;

/**
 * Class Users
 *
 * @package Innocode\WPMUUsers
 */
final class Users
{
    public static function register()
    {
        add_filter( 'map_meta_cap', [ __CLASS__, 'filter_capabilities' ], 10, 4 );
        add_filter(
            'enable_edit_any_user_configuration',
            [ __CLASS__, 'filter_any_configuration_edit' ]
        );
        add_filter(
            'user_row_actions',
            [ __CLASS__, 'filter_users_list_row_actions' ]
        );
        add_filter(
            'site_option_site_admins',
            [ __CLASS__, 'filter_super_admins' ]
        );

        add_action( 'wpmu_activate_user', [ __CLASS__, 'activated' ] );
        add_action( 'remove_user_from_blog', [ __CLASS__, 'remove' ], 10, 2 );
    }

    /**
     * @param string[] $caps    Array of the user's capabilities.
     * @param string   $cap     Capability name.
     * @param int      $user_id The user ID.
     * @param array    $args    Adds the context to the cap. Typically the object ID.
     *
     * @return string[]
     */
    public static function filter_capabilities( $caps, $cap, $user_id, $args )
    {
        if ( ! is_user_logged_in() ) {
            return $caps;
        }

        $user = get_userdata( $user_id );

        if ( ! in_array( 'administrator', $user->roles ) ) {
            return $caps;
        }

        switch ( $cap ) {
            case 'edit_user':
            case 'edit_users':
                if ( false === ( $key = array_search( 'do_not_allow', $caps ) ) ) {
                    break;
                }

                if ( $cap == 'edit_users' ) {
                    $caps[ $key ] = $cap;

                    break;
                }

                if (
                    $cap == 'edit_user' &&
                    $args[0] >= MU_USERS_OFFSET
                ) {
                    $caps[ $key ] = 'edit_users';

                    break;
                }

                break;
            case 'remove_user':
            case 'remove_users':
                if ( false === ( $key = array_search( 'remove_users', $caps ) ) ) {
                    break;
                }

                if ( $cap == 'remove_users' ) {
                    $caps[ $key ] = $cap;

                    break;
                }

                if (
                    $cap == 'remove_user' &&
                    $args[0] < MU_USERS_OFFSET
                ) {
                    $caps[ $key ] = 'do_not_allow';

                    break;
                }

                break;
            case 'delete_user':
            case 'delete_users':
                if ( false === ( $key = array_search( 'do_not_allow', $caps ) ) ) {
                    break;
                }

                if ( $cap == 'delete_users' ) {
                    $caps[ $key ] = $cap;

                    break;
                }

                if (
                    $cap == 'delete_user' &&
                    $args[0] >= MU_USERS_OFFSET
                ) {
                    $caps[ $key ] = 'delete_users';

                    break;
                }

                break;
            case 'promote_user':
            case 'promote_users':
                if ( false === ( $key = array_search( 'promote_users', $caps ) ) ) {
                    break;
                }

                if (
                    ! is_super_admin( $user_id ) &&
                    $cap == 'promote_user' &&
                    $args[0] < MU_USERS_OFFSET
                ) {
                    $caps[ $key ] = 'do_not_allow';

                    break;
                }

                break;
            case 'export_others_personal_data':
            case 'erase_others_personal_data':
            case 'manage_privacy_options':
                if ( false === ( $key = array_search( 'manage_network', $caps ) ) ) {
                    break;
                }

                $caps[ $key ] = 'manage_options';

                break;
            case 'switch_themes':
                if ( false === ( $key = array_search( 'switch_themes', $caps ) ) ) {
                    break;
                }

                if ( ! is_super_admin( $user_id ) ) {
                    $caps[ $key ] = 'do_not_allow';
                }

                break;
            case 'delete_site':
                if ( false === ( $key = array_search( 'manage_options', $caps ) ) ) {
                    break;
                }

                if ( ! is_super_admin( $user_id ) ) {
                    $caps[ $key ] = 'do_not_allow';
                }

                break;
        }

        return $caps;
    }

    /**
     * @return bool
     */
    public static function filter_any_configuration_edit()
    {
        global $user_id;

        return $user_id >= MU_USERS_OFFSET;
    }

    /**
     * @param array $actions
     *
     * @return mixed
     */
    public static function filter_users_list_row_actions( $actions )
    {
        if ( ! isset( $actions['remove'] ) ) {
            return $actions;
        }

        $filtered_actions = [];

        foreach ( $actions as $action => $html ) {
            if ( $action == 'remove' ) {
                $action = 'delete';
            }

            $filtered_actions[ $action ] = $html;
        }

        return $filtered_actions;
    }

    /**
     * @param false|array $super_admins
     *
     * @return false|array array
     */
    public static function filter_super_admins( $super_admins )
    {
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();

            if (
                $user->ID >= MU_USERS_OFFSET &&
                in_array( $user->user_login, $super_admins )
            ) {
                $super_admins = array_diff( $super_admins, [
                    $user->user_login,
                ] );
                $notification_key = sanitize_key( "duplicate_user_login_$user->user_login" );

                if ( false === get_transient( $notification_key ) ) {
                    Notifications::duplicate_user_login_warning( $user->user_login );
                    set_transient( $notification_key, 1, WEEK_IN_SECONDS );
                }
            }
        }

        return $super_admins;
    }

    /**
     * @param int $user_id
     */
    public static function activated( $user_id )
    {
        global $wpdb;

        if ( $user_id < MU_USERS_OFFSET ) {
            return;
        }

        $user = get_user_by( 'id', $user_id );
        $wpdb->delete( $wpdb->signups, [ 'user_login' => $user->user_login ] );
    }

    /**
     * @param int $user_id
     * @param int $blog_id
     */
    public static function remove( $user_id, $blog_id )
    {
        if ( $user_id < MU_USERS_OFFSET || $blog_id <= 1 ) {
            return;
        }

        if ( ! function_exists( 'wp_delete_user' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        if ( ! function_exists( 'wpmu_delete_user' ) ) {
            require_once ABSPATH . 'wp-admin/includes/ms.php';
        }

        remove_filter( 'remove_user_from_blog', [ __CLASS__, 'remove' ], 10 );

        wp_delete_user( $user_id, get_current_user_id() );
        wpmu_delete_user( $user_id );

        add_filter( 'remove_user_from_blog', [ __CLASS__, 'remove' ], 10, 2 );
    }
}
