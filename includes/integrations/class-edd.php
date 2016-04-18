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

class WRM_EDD extends WP_Review_Me {

	/**
	 * URL of the EDD shop
	 *
	 * @since 1.0
	 * @var string
	 */
	protected $edd_url;

	/**
	 * The discount code settings
	 *
	 * @since 1.0
	 * @var array
	 */
	protected $discount;

	/**
	 * The discount code generated
	 *
	 * @since 1.0
	 * @var string
	 */
	protected $code;

	/**
	 * The confirmation e-mail data
	 *
	 * @since 1.0
	 * @var array
	 */
	protected $email;

	public function __construct( $args, $email = array() ) {

		$this->edd_url  = isset( $args['edd_url'] ) ? esc_url( $args['edd_url'] ) : '';
		$this->discount = isset( $args['edd_discount'] ) && is_array( $args['edd_discount'] ) ? wp_parse_args( $args['edd_discount'], $this->discount_defaults() ) : $this->discount_defaults();
		$this->email    = $email;

		// Call parent constructor
		parent::__construct( $args );

		// Register our discount action
		add_action( 'wrm_after_notice_dismissed', array( $this, 'query_discount_ajax' ), 10, 2 );

	}

	/**
	 * Get the EDD discount default options
	 *
	 * @since 1.0
	 * @return array
	 */
	private function discount_defaults() {

		$defaults = array(
			'type'     => 'percentage',
			'amount'   => 20,
			'validity' => 30, // In days
		);

		return $defaults;

	}

	/**
	 * Trigger the EDD discount query via Ajax
	 *
	 * @since 1.0
	 *
	 * @param string $link_id Unique ID of the link clicked to generate the discount
	 *
	 * @return void
	 */
	public function query_discount_ajax( $link_id ) {

		// Not this notice. Abort.
		if ( $link_id !== $this->link_id ) {
			echo 'not this instance job';

			return;
		}

		echo $this->query_discount();

	}

	/**
	 * Send the HTTP query to the EDD shop
	 *
	 * @since 1.0
	 * @return string|true
	 */
	protected function query_discount() {

		$endpoint = esc_url( add_query_arg( 'wrm_action', 'discount', $this->edd_url ) );
		$data     = array( 'wrm_email' => get_bloginfo( 'admin_email' ), 'wrm_discount' => $this->discount ); // Wrap our vars to avoid post names issues

		$response = wp_remote_post( $endpoint, array(
			'method'      => 'POST',
			'timeout'     => 20,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => true,
			'body'        => $data,
			'user-agent'  => 'WRM/' . $this->version . '; ' . get_bloginfo( 'url' )
		) );

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		if ( 200 != wp_remote_retrieve_response_code( $response ) ) {
			return wp_remote_retrieve_response_message( $response );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( false === $body ) {
			return __( 'Unknown error', 'wp-review-me' );
		}

		if ( 'success' === $body->result ) {
			$this->code = trim( $body->message );
			$this->email_code();
			return $this->code;
		}

		return $body->message;

	}

	/**
	 * Get the e-mail data
	 *
	 * @since 1.0
	 * @return array
	 */
	protected function get_email_data() {

		$body = '<p>Hi,</p><p>From our entire team, many thanks for your review.</p><p>While you just spent a few minutes writing it, your testimonial will be a big help to us.</p><p>Many users don\'t realize it but reviews are a key part of the promotion work for our product. This allows us to increase our user base, and thus get more support for improving our product.</p><p>As a thank you from us, please find hereafter your discount code for a {{amount}} discount valid until {{expiration}}:</p><p>Your discount code: <strong>{{code}}</strong></p><p>Enjoy the product!</p>';

		if ( isset( $this->email['body'] ) ) {
			$body = $this->email['body'];
		}

		$tags = array( 'code', 'amount', 'expiration' );

		foreach ( $tags as $tag ) {

			$find = '{{' . $tag . '}}';

			switch ( $tag ) {

				case 'code':
					$body = str_replace( $find, $this->code, $body );
					break;

				case 'amount':

					$amount = $this->discount['amount'];

					if ( 'percentage' === $this->discount['type'] ) {
						$amount = $amount . '%';
					}

					$body = str_replace( $find, $amount, $body );
					break;

				case 'expiration':
					$expiration = date( get_option( 'date_format' ), time() + ( (int) $this->discount['validity'] * 86400 ) );
					$body       = str_replace( $find, $expiration, $body );
					break;

			}

		}

		$email['body']       = $body;
		$email['subject']    = isset( $this->email['subject'] ) ? sanitize_text_field( $this->email['subject'] ) : esc_html__( 'Your discount code', 'wp-review-me' );
		$email['from_name']  = isset( $this->email['from_name'] ) ? sanitize_text_field( $this->email['from_name'] ) : get_bloginfo( 'name' );
		$email['from_email'] = isset( $this->email['from_email'] ) ? sanitize_text_field( $this->email['from_email'] ) : get_bloginfo( 'admin_email' );

		return apply_filters( 'wrm_edd_email', $email );

	}

	/**
	 * E-mail the discount code to the reviewer
	 *
	 * @since 1.0
	 * @return bool
	 */
	protected function email_code() {

		$email      = $this->get_email_data();
		$from_name  = $email['from_name'];
		$from_email = $email['from_email'];
		$headers    = array(
			"MIME-Version: 1.0",
			"Content-type: text/html; charset=utf-8",
			"From: $from_name <$from_email>",
		);

		return wp_mail( get_bloginfo( 'admin_email' ), $email['subject'], $email['body'], $headers );

	}

	/**
	 * Get the review prompt message
	 *
	 * @since 1.0
	 * @return string
	 */
	protected function get_message() {

		$message = $this->message;
		$link    = $this->get_review_link_tag();
		$message = $message . ' ' . $link;

		return wp_kses_post( $message );

	}

}