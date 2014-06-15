<?php
/*
Plugin Name: Better WordPress Polldaddy Polls
Plugin URI: http://betterwp.net/wordpress-plugins/bwp-polldaddy/
Description: Helps you add Polldaddy Polls to your WordPress website easily. This plugin focuses on the front end rather than the back end, i.e. you edit your polls on polldaddy.com and you choose how to show them on your website.
Version: 1.1.0
Text Domain: bwp-polldaddy
Domain Path: /languages/
Author: Khang Minh
Author URI: http://betterwp.net
License: GPLv3
*/

// In case someone integrates this plugin in a theme or calls this directly
if (class_exists('BWP_POLLDADDY') || !defined('ABSPATH'))
	return;

require_once dirname(__FILE__) . '/includes/class-bwp-polldaddy.php';
$bwp_polldaddy = new BWP_POLLDADDY();
