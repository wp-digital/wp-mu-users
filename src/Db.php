<?php

namespace Innocode\WPMUUsers;

use Innocode\WPMUUsers\Filters;
use PhpMyAdmin\SqlParser;
use WP_CLI\SearchReplacer;

/**
 * Class Db
 *
 * @package Innocode\WPMUUsers
 */
final class Db
{
    /**
     * @var array
     */
    private static $_tables = [
        'users'    => 'users',
        'usermeta' => 'usermeta',
    ];
    /**
     * @var array
     */
    private static $_global_tables = [
        'global_users'    => 'users',
        'global_usermeta' => 'usermeta',
    ];

    /**
     * @param string $name
     *
     * @return array|string|null
     */
    public static function get( $name )
    {
        global $wpdb;

        if ( isset( static::$_tables[ $name ] ) ) {
            $table = static::$_tables[ $name ];

            return "{$wpdb->prefix}{$table}";
        }

        if ( isset( static::$_global_tables[ $name ] ) ) {
            $table = static::$_global_tables[ $name ];

            return "{$wpdb->base_prefix}{$table}";
        }

        if ( $name == 'tables' ) {
            return [
                static::get( 'users' ),
                static::get( 'usermeta' ),
            ];
        }

        if ( $name == 'global_tables' ) {
            return [
                static::get( 'global_users' ),
                static::get( 'global_usermeta' ),
            ];
        }

        return null;
    }

    public static function register()
    {
        static::init();
        static::switch_cache_groups();

        add_filter( 'query', [ __CLASS__, 'filter_query' ] );
        add_filter( 'wpmu_drop_tables', [ __CLASS__, 'filter_site_tables' ], 10, 2 );

        add_action( 'switch_blog', [ __CLASS__, 'init' ] );
    }

    public static function init()
    {
        static::maybe_create_tables();
        static::switch_tables();
    }

    public static function maybe_create_tables()
    {
        if ( static::should_create_tables() ) {
            static::create_tables();
        }
    }

    /**
     * @return bool
     */
    public static function should_create_tables()
    {
        global $wpdb;

        $table = static::get( 'users' );

        return ! get_option( 'user_table_created' ) &&
            $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) != $table;

    }

    public static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        /**
         * @see wp_get_db_schema()
         */
        $max_index_length = 191;

        $users_table_name = static::get( 'users' );
        $usermeta_table_name = static::get( 'usermeta' );

        $users_table = "CREATE TABLE $users_table_name (
	ID bigint(20) unsigned NOT NULL auto_increment,
	user_login varchar(60) NOT NULL default '',
	user_pass varchar(255) NOT NULL default '',
	user_nicename varchar(50) NOT NULL default '',
	user_email varchar(100) NOT NULL default '',
	user_url varchar(100) NOT NULL default '',
	user_registered datetime NOT NULL default '0000-00-00 00:00:00',
	user_activation_key varchar(255) NOT NULL default '',
	user_status int(11) NOT NULL default '0',
	display_name varchar(250) NOT NULL default '',
	spam tinyint(2) NOT NULL default '0',
	deleted tinyint(2) NOT NULL default '0',
	PRIMARY KEY  (ID),
	KEY user_login_key (user_login),
	KEY user_nicename (user_nicename),
	KEY user_email (user_email)
) auto_increment=" . MU_USERS_OFFSET . " $charset_collate;\n";

        $usermeta_table = "CREATE TABLE $usermeta_table_name (
	umeta_id bigint(20) unsigned NOT NULL auto_increment,
	user_id bigint(20) unsigned NOT NULL default '0',
	meta_key varchar(255) default NULL,
	meta_value longtext,
	PRIMARY KEY  (umeta_id),
	KEY user_id (user_id),
	KEY meta_key (meta_key($max_index_length))
) $charset_collate;\n";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        dbDelta( $users_table );
        dbDelta( $usermeta_table );

        update_option( 'user_table_created', 1 );
    }

    public static function switch_tables()
    {
        global $wpdb;

        $wpdb->users = static::get( 'users' );
        $wpdb->usermeta = static::get( 'usermeta' );

        $wpdb->tables[] = 'users';
        $wpdb->tables[] = 'usermeta';

        foreach ( [
            'users',
            'usermeta',
        ] as $table ) {
            if ( false !== ( $index = array_search(
                $table, $wpdb->global_tables
            ) ) ) {
                unset( $wpdb->global_tables[ $index ] );
            }
        }
    }

    public static function switch_cache_groups()
    {
        global $wp_object_cache;

        $wp_object_cache->global_groups['users'] = 0;
        $wp_object_cache->global_groups['userlogins'] = 0;
        $wp_object_cache->global_groups['usermeta'] = 0;
        $wp_object_cache->global_groups['user_meta'] = 0;
        $wp_object_cache->global_groups['useremail'] = 0;
        $wp_object_cache->global_groups['userslugs'] = 0;
    }

    /**
     * Should be called before adding 'query' filter
     *
     * @return bool
     */
    public static function is_local_users_table_valid()
    {
        $id = static::suppress_query_filter( function () {
            global $wpdb;

            $table = static::get( 'users' );

            return $wpdb->get_var( "SELECT ID from $table ORDER BY ID ASC LIMIT 1" );
        } );

        return is_null( $id ) || $id >= MU_USERS_OFFSET;
    }

    /**
     * @param string $table
     *
     * @return bool
     */
    public static function is_local_users_table( $table )
    {
        return in_array(
            $table,
            static::get( 'tables' )
        );
    }

    public static function replace_tables( $query )
    {
        $search_replacer = new SearchReplacer(
            static::get( 'users' ),
            static::get( 'global_users' )
        );
        $query = $search_replacer->run( $query );
        $search_replacer = new SearchReplacer(
            static::get( 'usermeta' ),
            static::get( 'global_usermeta' )
        );
        $query = $search_replacer->run( $query );

        return $query;
    }

    /**
     * @param string $query
     *
     * @return string
     */
    public static function filter_query( $query )
    {
        $parsed = new SqlParser\Parser( $query );

        if ( isset( $parsed->statements[0] ) ) {
            $statement = $parsed->statements[0];
            $statement = static::_filter_statement( $statement );
            $query = $statement->build();
        }

        return $query;
    }

    /**
     * @param callable $callback
     * @param array    $args
     *
     * @return mixed
     */
    public static function suppress_query_filter( callable $callback, array $args = [] )
    {
        remove_filter( 'query', [ __CLASS__, 'filter_query' ] );
        $result = call_user_func_array( $callback, $args );
        add_filter( 'query', [ __CLASS__, 'filter_query' ] );

        return $result;
    }

    /**
     * @param $tables
     * @param $site_id
     *
     * @return array
     */
    public static function filter_site_tables( $tables, $site_id )
    {
        if ( $site_id <= 1 ) {
            return $tables;
        }

        return array_merge( $tables, static::get( 'tables' ) );
    }

    /**
     * @param $statement
     *
     * @return mixed
     */
    private static function _filter_statement( $statement )
    {
        $statement_name = Helpers::get_class_short_name( $statement );

        switch ( $statement_name ) {
            case 'InsertStatement':
                $filter = new Filters\Insert();
                $statement = static::_run_filter( $filter, $statement );
                break;
            case 'SelectStatement':
                $filter = new Filters\Select();
                $statement = static::_run_filter( $filter, $statement );
                break;
            case 'UpdateStatement':
                $filter = new Filters\Update();
                $statement = static::_run_filter( $filter, $statement );
                break;
        }

        return $statement;
    }

    /**
     * @param Filters\AbstractFilter $filter
     * @param                        $statement
     *
     * @return mixed
     */
    private static function _run_filter( Filters\AbstractFilter $filter, $statement )
    {
        $filter->statement = $statement;
        $filter->run();

        return $filter->statement;
    }
}
