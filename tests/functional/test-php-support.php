<?php

/**
 * @author Khang Minh <contact@betterwp.net>
 */
class BWP_Polldaddy_PHP_Support_Functional_Test extends BWP_Framework_PHPUnit_WP_Legacy_Functional_TestCase
{
	public function test_can_initiate_all_classes()
	{
		$class_maps = include dirname(dirname(dirname(__FILE__))) . '/vendor/composer/autoload_classmap.php';

		foreach ($class_maps as $class_name => $class_file) {
			if (stripos($class_name, 'bwp_polldaddy') === false) {
				continue;
			}

			require_once $class_file;
		}

		$this->assertTrue(true);
	}

	public function get_plugins()
	{
		$root_dir = dirname(dirname(dirname(__FILE__)));

		return array(
            $root_dir . '/bwp-polldaddy.php' => 'bwp-polldaddy/bwp-polldaddy.php'
		);
	}

	public function test_can_bootstrap_plugin()
	{
		$this->bootstrap_plugin();

		$this->assertTrue(true);
	}
}
