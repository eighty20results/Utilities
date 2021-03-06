<?php
/*
 * *
 *   * Copyright (c) 2021. - Eighty / 20 Results by Wicked Strong Chicks.
 *   * ALL RIGHTS RESERVED
 *   *
 *   * This program is free software: you can redistribute it and/or modify
 *   * it under the terms of the GNU General Public License as published by
 *   * the Free Software Foundation, either version 3 of the License, or
 *   * (at your option) any later version.
 *   *
 *   * This program is distributed in the hope that it will be useful,
 *   * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   * GNU General Public License for more details.
 *   *
 *   * You should have received a copy of the GNU General Public License
 *   * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace E20R\Test\Unit;

use Codeception\Test\Unit;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Mockery;
use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

class UtilitiesTest extends Unit {

	use MockeryPHPUnitIntegration;

	/**
	 * The setup function for this Unit Test suite
	 *
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->loadFiles();
	}

	/**
	 * Teardown function for the Unit Tests
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Load source files for the Unit Test to execute
	 */
	public function loadFiles() {
		require_once __DIR__ . '/../../../inc/autoload.php';
		require_once __DIR__ . '/../../../src/utilities/class-utilities.php';
		require_once __DIR__ . '/../../../src/utilities/class-message.php';
	}

	/**
	 * Test the instantiation of the Utilities class
	 *
	 * @param bool $is_admin
	 * @param bool $has_action
	 *
	 * @dataProvider fixtures_constructor
	 */
	public function test_class_is_instantiated( $is_admin, $has_action ) {

		Functions\expect( 'plugins_url' )
			->andReturn( 'https://localhost:7254/wp-content/plugins/00-e20r-utilities' );

		Functions\expect( 'plugin_dir_path' )
			->andReturn( '/var/www/html/wp-content/plugins/00-e20r-utilities' );

		Functions\expect( 'get_current_blog_id' )
			->andReturn( 1 );

		$util_mock = $this->getMockBuilder( Utilities::class )->onlyMethods( array( 'is_admin', 'log' ) )->getMock();
		$util_mock->method( 'is_admin' )->willReturn( $is_admin );
		$util_mock->method( 'log' )->willReturn( null );

		Functions\when( 'has_action' )
			->justReturn( $has_action );

		$utils    = Utilities::get_instance();
		$messages = new Message();

		if ( $is_admin ) {
			Filters\has( 'pmpro_save_discount_code', array( $utils, 'clear_delay_cache' ) );
			Actions\has( 'pmpro_save_membership_level', array( $utils, 'clear_delay_cache' ) );
			Filters\has( 'http_request_args', array( $utils, 'set_ssl_validation_for_updates' ) );

			if ( ! has_action( 'admin_notices', array( $messages, 'display' ) ) ) {
				Actions\has( 'admin_notices', array( $messages, 'display' ) );
			}
		} else {
			// Filters should be set/defined if we think we're in the wp-admin backend
			Filters\has( 'woocommerce_update_cart_action_cart_updated', array( $messages, 'clear_notices' ) );
			Filters\has( 'pmpro_email_field_type', array( $messages, 'filter_passthrough' ) );
			Filters\has( 'pmpro_get_membership_levels_for_user', array( Message::class, 'filter_passthrough' ) );
			Actions\has( 'woocommerce_init', array( $messages, 'display' ) );
		}

	}

	/**
	 * Fixture for testing the Utilities constructor (filter/action checks)
	 * @return array
	 */
	public function fixtures_constructor() {
		return array(
			array( true, true ),
			array( true, false ),
			array( false, false ),
			array( false, true ),
		);
	}
	/**
	 * Tests the is_valid_date() function
	 *
	 * @param string $date
	 * @param bool $expected
	 *
	 * @dataProvider fixture_test_dates
	 */
	public function test_is_date( $date, $expected ) {
		$utils  = Utilities::get_instance();
		$result = $utils->is_valid_date( $date );

		self::assertEquals( $expected, $result );
	}

	/**
	 * Date provider for the is_valid_date() unit tests
	 *
	 * @return array[]
	 */
	public function fixture_test_dates(): array {
		return array(
			array( '2021-10-11', true ),
			array( '10-11-2020', true ),
			array( '31-12-2020', true ),
			array( '31-02-2020', true ),
			array( '30th Feb, 2020', true ),
			array( '29-Nov-2020', true ),
			array( '1st Jan, 2020', true ),
			array( 'nothing', false ),
			array( null, false ),
			array( false, false ),
		);
	}
	/**
	 * Test if the specified plugin is considered "active" by WordPress
	 *
	 * @param string $plugin_name
	 * @param string $function_name
	 * @param bool $expected
	 *
	 * @dataProvider pluginListData
	 */
	public function test_plugin_is_active( $plugin_name, $function_name, $is_admin, $expected ) {
		$utils  = Utilities::get_instance();
		$result = null;

		Functions\expect( 'is_admin' )
			->andReturn( $is_admin );
		Functions\expect( 'plugins_url' )
			->andReturn(
				sprintf( 'https://development.local:7254/wp-content/plugins/' )
			);

		try {
			Functions\expect( 'is_plugin_active' )
				->with( Mockery::contains( $plugin_name ) )
				->andReturn( $expected );
		} catch ( \Exception $e ) {
			echo 'Error: ' . $e->getMessage(); // phpcs:ignore
		}

		try {
			Functions\expect( 'is_plugin_active_for_network' )
				->with( Mockery::contains( $plugin_name ) )
				->andReturn( $expected );
		} catch ( \Exception $e ) {
			echo 'Error: ' . $e->getMessage(); // phpcs:ignore
		}

		$result = $utils->plugin_is_active( $plugin_name, $function_name );

		self::assertEquals( $expected, $result );
	}

	/**
	 * Data Provider for the plugin_is_active test function
	 *
	 * @return array[]
	 */
	public function pluginListData() {
		return array(
			// $plugin_name, $function_name, $is_admin, $expected
			array( 'plugin_file/something.php', 'my_function', false, false ),
			array( '00-e20r-utilities/class-loader.php', null, false, false ),
			array( '00-e20r-utilities/class-loader.php', null, true, true ),
			array( null, 'pmpro_getOption', false, false ),
			array( null, 'pmpro_getOption', true, false ),
			array( null, 'pmpro_not_a_function', false, false ),
			array( null, 'pmpro_not_a_function', true, false ),
			array( 'paid-memberships-pro/paid-memberships-pro.php', null, true, false ),
			array( 'paid-memberships-pro/paid-memberships-pro.php', null, false, false ),
		);
	}

}
