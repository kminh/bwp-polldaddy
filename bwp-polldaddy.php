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

// in case someone integrates this plugin in a theme or calling this directly
global $bwp_polldaddy;

if ((isset($bwp_polldaddy) && $bwp_polldaddy instanceof BWP_Polldaddy) || !defined('ABSPATH'))
	return;

// require libs manually if PHP version is lower than 5.3.2
// @todo remove this when WordPress drops support for PHP version < 5.3.2
if (version_compare(PHP_VERSION, '5.3.2', '<'))
{
	require_once dirname(__FILE__) . '/autoload.php';
}
else
{
	// load dependencies using composer autoload
	require_once dirname(__FILE__) . '/vendor/autoload.php';
}

$bwp_polldaddy = new BWP_Polldaddy(array(
	'title'       => 'Better WordPress Polldaddy Polls',
	'version'     => '1.2.0',
	'php_version' => '5.2.0',
	'domain'      => 'bwp-polldaddy'
));
