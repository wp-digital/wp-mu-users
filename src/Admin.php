<?php

namespace Innocode\WPMUUsers;

/**
 * Class Admin
 *
 * @package Innocode\WPMUUsers
 */
final class Admin
{
    public static function register()
    {
        add_action( 'admin_init', [ __CLASS__, 'check_database_table' ] );
        add_action( 'admin_init', [ __CLASS__, 'check_duplicate_user_login' ] );
        add_action( 'add_admin_bar_menus', [ __CLASS__, 'bar_menus' ] );
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
        add_action( 'admin_print_styles-user-new.php', [ __CLASS__, 'hide_add_existing_user_form' ] );
        add_action( 'user_new_form', [ __CLASS__, 'remove_add_existing_user_form' ] );
    }

    public static function check_database_table()
    {
        if (
            ! wp_doing_ajax() &&
            ! Db::is_local_users_table_valid()
        ) {
            wp_die(
                sprintf(
                    __( 'Invalid users local database table: %s.', 'innocode-wp-mu-users' ),
                    Db::get( 'users' )
                )
            );
        }
    }

    public static function check_duplicate_user_login()
    {
        if ( ! wp_doing_ajax() ) {
            $user = wp_get_current_user();

            if (
                $user->ID > 0 &&
                $user->ID < MU_USERS_OFFSET
            ) {
                $count_duplicates = Db::suppress_query_filter( function ( $user_login ) {
                    global $wpdb;

                    $table = Db::get( 'users' );

                    return $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(ID) FROM $table WHERE user_login = '%s'",
                            $user_login
                        )
                    );
                }, [
                    $user->user_login,
                ] );

                if ( $count_duplicates ) {
                    wp_die(
                        sprintf(
                            __( 'Duplicate user login: %s.', 'innocode-wp-mu-users' ),
                            $user->user_login
                        )
                    );
                }
            }
        }
    }

    public static function bar_menus()
    {
        if ( get_current_user_id() >= MU_USERS_OFFSET ) {
            remove_action( 'admin_bar_menu', 'wp_admin_bar_my_sites_menu', 20 );
        }
    }

    public static function menu()
    {
        global $submenu;

        if (
            get_current_user_id() >= MU_USERS_OFFSET &&
            isset( $submenu['index.php'][5] )
        ) {
            unset( $submenu['index.php'][5] );
        }
    }

    public static function hide_add_existing_user_form()
    {
        if ( ! is_super_admin() ) {
            echo "<style>
#add-existing-user, #add-existing-user + p, form#adduser, #create-new-user { display: none !important; }
</style>\n";
        }
    }

    /**
     * @param string $type
     */
    public static function remove_add_existing_user_form( $type )
    {
        if ( ! is_super_admin() ) {
            switch ( $type ) {
                case 'add-existing-user':
                    echo "<script>
if (typeof jQuery !== 'undefined') jQuery('#add-existing-user, #add-existing-user + p, form#adduser').remove();
</script>";
                    break;
                case 'add-new-user':
                    echo "<script>
if (typeof jQuery !== 'undefined') jQuery('#create-new-user').remove();
</script>";
                    break;
            }
        }
    }
}
