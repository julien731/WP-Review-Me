<?php
/**
 * WP Review Me EDD Bridge
 *
 * This bridge plugin handles the discount creation triggered by WP Review Me
 *
 * LICENSE: This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License as published by the Free Software Foundation; either version 3 of the License, or (at
 * your option) any later version. This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details. You should have received a copy of the GNU General Public License along
 * with this program. If not, see <http://opensource.org/licenses/gpl-license.php>
 *
 * @package   WP Review Me EDD Bridge
 * @author    Julien Liabeuf <julien@liabeuf.fr>
 * @version   1.0
 * @license   GPL-2.0+
 * @link      https://julienliabeuf.com
 * @copyright 2016 Julien Liabeuf
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'WRM_EDD_Bridge' ) ):

	/**
	 * Main WRM EDD class
	 *
	 * This class is the one and only instance of the plugin. It is used
	 * to load the core and all its components.
	 *
	 * @since 3.2.5
	 */
	final class WRM_EDD_Bridge {

		/**
		 * Minimum version of WordPress required ot run the plugin
		 *
		 * @since 1.0
		 * @var string
		 */
		public $wordpress_version_required = '3.8';

		/**
		 * Required version of PHP.
		 *
		 * Follow WordPress latest requirements and require
		 * PHP version 5.2 at least.
		 *
		 * @since 1.0
		 * @var string
		 */
		public $php_version_required = '5.2';

		/**
		 * Email of the user
		 *
		 * @since 1.0
		 * @var string
		 */
		protected $email;

		/**
		 * Discount code
		 *
		 * @since 1.0
		 * @var string
		 */
		protected $code;

		/**
		 * Throw error on object clone
		 *
		 * The whole idea of the singleton design pattern is that there is a single
		 * object therefore, we don't want the object to be cloned.
		 *
		 * @since 3.2.5
		 * @return void
		 */
		public function __clone() {
			// Cloning instances of the class is forbidden
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'awesome-support' ), '3.2.5' );
		}

		/**
		 * Disable unserializing of the class
		 *
		 * @since 3.2.5
		 * @return void
		 */
		public function __wakeup() {
			// Unserializing instances of the class is forbidden
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'awesome-support' ), '3.2.5' );
		}

		public function __construct() {
			add_action( 'init', array( $this, 'init' ) );
		}

		/**
		 * Initialize the bridge
		 *
		 * @since 1.0
		 * @return bool|void
		 */
		public function init() {

			// Some work to do?
			if ( ! empty( $_GET ) && isset( $_GET['wrm_action'] ) ) {

				switch ( $_GET['wrm_action'] ) {

					case 'discount':
						$this->discount();
						break;
				}

			}

		}

		/**
		 * Run some checks and generate the discount if it's all good
		 *
		 * @since 1.0
		 * @return void
		 */
		protected function discount() {

			$result = array( 'result' => 'error' );

			// Make sure the WordPress version is recent enough
			if ( ! $this->is_version_compatible() ) {
				$result['message'] = 'WordPress version incompatible';
				echo json_encode( $result );
				die();
			}

			// Make sure we have a version of PHP that's not too old
			if ( ! $this->is_php_version_enough() ) {
				$result['message'] = 'PHP version incompatible';
				echo json_encode( $result );
				die();
			}

			// Check if EDD is here
			if ( ! function_exists( 'edd_store_discount' ) ) {
				$result['message'] = 'EDD not active';
				echo json_encode( $result );
				die();
			}

			// Make sure we have an e-mail
			if ( ! isset( $_POST['wrm_email'] ) ) {
				$result['message'] = 'Email missing';
				echo json_encode( $result );
				die();
			}

			$this->email = $_POST['wrm_email'];
			$this->code  = md5( $this->email );

			if ( ! $this->is_code_unique() ) {
				$result['message'] = 'Code already claimed';
				echo json_encode( $result );
				die();
			}

			$message           = $this->insert_discount();
			$result['result']  = $message ? 'success' : 'error';
			$result['message'] = $message ? $this->code : 'unknown error';

			echo json_encode( $result );
			die();

		}

		/**
		 * Check if the core version is compatible with this.
		 *
		 * @since  3.3
		 * @return boolean
		 */
		private function is_version_compatible() {

			if ( empty( $this->wordpress_version_required ) ) {
				return true;
			}

			if ( version_compare( get_bloginfo( 'version' ), $this->wordpress_version_required, '<' ) ) {
				return false;
			}

			return true;

		}

		/**
		 * Check if the version of PHP is compatible with this addon.
		 *
		 * @since  3.3
		 * @return boolean
		 */
		private function is_php_version_enough() {

			/**
			 * No version set, we assume everything is fine.
			 */
			if ( empty( $this->php_version_required ) ) {
				return true;
			}

			if ( version_compare( phpversion(), $this->php_version_required, '<' ) ) {
				return false;
			}

			return true;

		}

		protected function is_code_unique() {
			return ! is_null( $this->code ) && edd_get_discount_by_code( $this->code ) ? false : true;
		}

		/**
		 * Insert the discount code.
		 *
		 * @since  0.1.0
		 * @return integer Discount ID
		 */
		protected function insert_discount() {

			$type     = isset( $_POST['wrm_discount_type'] ) ? $_POST['wrm_discount_type'] : 'percent';
			$amount   = isset( $_POST['wrm_discount_amount'] ) ? (int) $_POST['wrm_discount_amount'] : 20;
			$validity = isset( $_POST['wrm_discount_validity'] ) ? (int) $_POST['wrm_discount_validity'] : 30;

			$details = array(
				'code'              => $this->code,
				'name'              => sprintf( 'Discount for a review %s', $this->email ),
				'status'            => 'active',
				'uses'              => 0,
				'max'               => 1,
				'amount'            => $amount,
				'start'             => date( 'Y-m-d' ),
				'expiration'        => date( 'Y-m-d', strtotime( date( 'Y-m-d' ) . "+ $validity days" ) ),
				'type'              => $type,
				'min_price'         => 0,
				'products'          => array(),
				'product_condition' => 'any',
				'excluded_products' => array(),
				'not_global'        => true,
				'use_once'          => true,
			);

			return edd_store_discount( $details );

		}

	}

endif;

new WRM_EDD_Bridge();