<?php
/**
 * WP Review Me
 *
 * @package   WP Review Me/Integrations
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

class WRM_WordPress extends WP_Review_Me {

	/**
	 * WRM_WordPress constructor
	 *
	 * @since 1.0
	 *
	 * @param array $args
	 */
	public function __construct( $args ) {
		parent::__construct( $args );
	}

	/**
	 * Get the review prompt message
	 *
	 * @since 1.0
	 * @return string
	 */
	protected function get_message() {

		$message = $this->message;
		$link    = $this->get_review_link();
		$message = $message . " <a href='$link' target='_blank'>$this->link_label</a>";

		return wp_kses_post( $message );

	}

}