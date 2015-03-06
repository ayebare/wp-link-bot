<?php

/**
 * Plugin Name: wp-link-bot
 * Plugin URI: https://github.com/ayebare/wp-link-bot/tree/master
 * Description: Plugin that displays all wordpress url test cases for the site
 * Author: Ayebare
 * Version: 1.1
 * Author URI: https://profiles.wordpress.org/brooksx/
 */
define('WLB_VERSION', '1.0');
define('WLB_ROOT', dirname(__FILE__));

//@todo class autoloader
require_once(WLB_ROOT . '/classes/WlbLinkBot.php');
require_once(WLB_ROOT . '/classes/WlbRewrite_Rules.class.php');


new Rewrite_Rules();
new classLink_Bot();
