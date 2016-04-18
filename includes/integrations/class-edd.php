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
	 * Unique link ID
	 *
	 * @since 1.0
	 * @var string
	 */
	protected $link_id;

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

	public function __construct( $args ) {

		$this->edd_url  = isset( $args['edd_url'] ) ? esc_url( $args['edd_url'] ) : '';
		$this->discount = isset( $args['edd_discount'] ) && is_array( $args['edd_discount'] ) ? wp_parse_args( $args['edd_discount'], $this->discount_defaults() ) : $this->discount_defaults();

		// Call parent constructor
		parent::__construct( $args );

		// Register our hooks
		add_action( 'admin_footer', array( $this, 'script' ) );
		add_action( 'wp_ajax_wrm_edd_discount', array( $this, 'query_discount_ajax' ) );
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
	 * Echo the JS script in the admin footer
	 *
	 * @since 1.0
	 * @return void
	 */
	public function script() { ?>

<script>
		jQuery(document).ready(function($) {
			$('#<?php echo $this->link_id; ?>').on('click', eddDiscountCode);
			function eddDiscountCode() {

				var data = {
					action: 'wrm_edd_discount',
					id: '<?php echo $this->link_id; ?>'
				};

				jQuery.ajax({
					type:'POST',
					url: ajaxurl,
					data: data,
					success:function( data ){
						console.log(data);
					}
				});

			}
		});
</script>

	<?php }

	/**
	 * Trigger the EDD discount query via Ajax
	 *
	 * @since 1.0
	 * @return void
	 */
	public function query_discount_ajax() {

		if ( empty( $_POST ) ) {
			echo 'missing POST';
			die();
		}

		if ( ! isset( $_POST['id'] ) ) {
			echo 'missing ID';
			die();
		}

		$id = sanitize_text_field( $_POST['id'] );

		if ( $id !== $this->link_id ) {
			echo 'not this instance job';
			die();
		}

		if ( '' === $this->edd_url ) {
			echo 'no shop URL';
			die();
		}

		echo $this->query_discount();
		die();

	}

	/**
	 * Send the HTTP query to the EDD shop
	 *
	 * @since 1.0
	 * @return string|true
	 */
	private function query_discount() {

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
			return $body->message;
		}

		return $body->message;

	}

	/**
	 * E-mail the discount code to the reviewer
	 *
	 * @since 1.0
	 * @return bool
	 */
	protected function email_code() {
		// E-mail the $this->code
	}

	/**
	 * Get the review prompt message
	 *
	 * @since 1.0
	 * @return string
	 */
	protected function get_message() {

		// Generate link ID
		// Can't generate in this constructor because $this->get_message() is called in the parent constructor.
		$this->link_id = 'wrm-review-edd-' . $this->key;

		$message = $this->message;
		$link    = $this->get_review_link();
		$message = $message . " <a href='$link' target='_blank' id='$this->link_id'>$this->link_label</a>";

		return wp_kses_post( $message );

	}

}