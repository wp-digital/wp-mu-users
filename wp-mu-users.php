<?php
/**
 * Plugin Name: MU Users
 * Description: Creates separate users database tables for each site in WordPress network.
 * Version: 1.2.0
 * Plugin URI: https://github.com/innocode-digital/wp-mu-users
 * Author: Innocode
 * Author URI: https://innocode.com/
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 */

require_once __DIR__ . '/vendor/autoload.php';

use Innocode\WPMUUsers;

if ( get_current_blog_id() > 1 ) {
    defined( 'MU_USERS_OFFSET' ) || define( 'MU_USERS_OFFSET', 10000 );

    WPMUUsers\Db::register();
    WPMUUsers\Users::register();
    WPMUUsers\Url::register();
    WPMUUsers\Admin::register();
}
