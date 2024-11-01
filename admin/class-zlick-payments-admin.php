<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Zlick Pzyments
 * @subpackage Zlick Pzyments/admin
 * @author     Saad Waseem<saad.waseem@shahruh.com>
 */
class Zlick_Payments_Admin
{
	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Holds the values to be used in the fields callbacks
	 */
	private $options = [];


	/**
	 * Holds price plans
	 */
	private $price_plans = [];


	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct($plugin_name, $version)
	{
		$this->price_plans = fetch_price_plans();
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		add_action('admin_menu', [$this, 'zlick_payments_config']);
		add_action('admin_init', [$this, 'zlick_settings_init']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
		add_action('add_meta_boxes', [$this, 'zp_add_custom_fields_meta_box']);
		add_action('save_post', [$this, 'zp_save_zlick_fields_meta']);
		add_action( 'category_edit_form_fields', [$this, 'zp_category_fields_meta_box'] );
		add_action( 'edited_category', [$this, 'zp_save_category_price_plan_field'] );
		add_action( 'category_add_form_fields', [$this, 'zp_add_category_fields_meta_box'] );
		add_action( 'created_category', [$this, 'zp_save_category_price_plan_field'] );

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{
		wp_register_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/zlick-payments-admin.css', array(), $this->version, 'all');
		wp_enqueue_style($this->plugin_name);
	}
	/**
	 * Adds data on admin menu page.
	 *
	 * @return void
	 */
	function zlick_payments_config()
	{
		add_options_page(
			__('Zlick Payments Configuration', $this->plugin_name),
			__('Zlick Payments', $this->plugin_name),
			'manage_options',
			'zlick-payments-plugin',
			array($this, 'zlick_payments_page')
		);
	}

	/**
	 * setup zlick payment options
	 *
	 * @return void
	 */
	function zlick_settings_init()
	{ // whitelist options
		//register options
		register_setting('zlick-payments', 'zlick_payments_settings', array($this, 'zp_validate_options'));

		//register zlick payments admin section
		add_settings_section(
			'zlick_admin_settings_section',
			__('Zlick Payments Configuration', $this->plugin_name),
			array($this, 'print_section_info'),
			'zlick-payments-plugin'
		);

		// Client Token
		add_settings_field(
			'zp_client_token',
			__('Publisher ID', 'zlick-payments'),
			array($this, 'field_client_token_render'),
			'zlick-payments-plugin',
			'zlick_admin_settings_section'
		);
		// Client Secret
		add_settings_field(
			'zp_client_secret',
			__('API Key', 'zlick-payments'),
			array($this, 'field_client_secret_render'),
			'zlick-payments-plugin',
			'zlick_admin_settings_section'
		);
		// Previewable paragraphs
		add_settings_field(
			'zp_previewable_paras',
			__('Preview Paragraphs', 'zlick-payments'),
			array($this, 'field_previewable_paras_render'),
			'zlick-payments-plugin',
			'zlick_admin_settings_section'
		);

		// Previewable paragraphs
		add_settings_field(
			'zp_search_engine_bypass',
			__('Allow Search Engine Indexing', 'zlick-payments'),
			array($this, 'field_search_engine_bypass'),
			'zlick-payments-plugin',
			'zlick_admin_settings_section'
		);
	}

	/**
	 * Options page callback
	 */
	public function zlick_payments_page()
	{
		// Set class / check user capabilities
		if (!current_user_can('manage_options')) {
			return;
		}

		if (isset($_GET['zlick-payments-plugin'])) {
			// add settings saved message with the class of "updated"
			add_settings_error('zlick_payments_settings', 'zlick_payments', __('Settings Saved', $this->plugin_name), 'updated');
		}

		// show error/update messages
		settings_errors('zlick_payments_settings');
		$saved_options = get_option('zlick_payments_settings');

		if (empty($saved_options)) { //Before First Installation
			$this->options = array(
				'zp_client_token' => '',
				'zp_client_secret' => '',
				'zp_previewable_paras' => '',
			);
		} else {
			$this->options = $saved_options;
		}
?>
		<div class="wrap">
			<h1><?php __('Zlick Payments Configuration', $this->plugin_name) ?></h1>
			<form method="post" action="options.php">
				<?php
				// This prints out all hidden setting fields
				settings_fields('zlick-payments');
				do_settings_sections('zlick-payments-plugin');
				submit_button();
				?>
				<p>
				<b>Next step</b>: Mark your premium content in the article editor (Zlick settings field below the content).
				</p>
			</form>
		</div>
	<?php
	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $post_data Contains all settings fields as array keys
	 */
	public function zp_validate_options($post_data)
	{
		$configurations = array(
			'zp_client_token' => '',
			'zp_client_secret' => '',
			'zp_previewable_paras' => ZLICK_PREVIEWABLE_CONTENT_PARA_DEFAULT,
			'zp_search_engine_bypass' => true
		);

		if (isset($post_data['zp_client_token'])) {
			$configurations['zp_client_token'] = sanitize_text_field(wp_unslash($post_data['zp_client_token']));
		}

		if (isset($post_data['zp_client_secret'])) {
			$configurations['zp_client_secret'] = sanitize_text_field(wp_unslash($post_data['zp_client_secret']));
		}

		if (isset($post_data['zp_previewable_paras'])) {
			$configurations['zp_previewable_paras'] =  sanitize_text_field($post_data['zp_previewable_paras']);
		}

		if (isset($post_data['zp_search_engine_bypass'])) {
			$configurations['zp_search_engine_bypass'] =  true;
		} else {
			$configurations['zp_search_engine_bypass'] =  false;
		}

		$saved_options = get_option('zlick_payments_settings');

        $token_was_set = (isset($saved_options['zp_client_token']) && $saved_options['zp_client_token'] != "") ? true : false;
        $secret_was_set = (isset($saved_options['zp_client_secret']) && $saved_options['zp_client_secret'] != "") ? true : false;
        $token_is_set = (isset($post_data['zp_client_token']) && $post_data['zp_client_token'] != "") ? true : false;
        $secret_is_set = (isset($post_data['zp_client_secret']) && $post_data['zp_client_secret'] != "") ? true : false;

		if ($token_was_set && $secret_was_set) {
			return $configurations;
		}

		if (($token_is_set && !$secret_is_set) || ($secret_is_set && !$token_is_set)){
			return $configurations;
		}

		if ((!$token_was_set && $token_is_set) || (!$secret_was_set && $secret_is_set)){
			post_saving_keys_event($configurations['zp_client_token'], $configurations['zp_client_secret']);
		}

		return $configurations;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info()
	{
		printf(
			'<p>Thanks for choosing <a target="_blank" href="https://zlickpaywall.com">Zlick Paywall</a> solution to monetize your platform. Get your pair of keys from our admin portal <a target="_blank" href="https://portal.zlickpaywall.com">portal.zlickpaywall.com</a> and start collecting payments! (<a target="_blank" href="https://www.zlickpaywall.com/instructions">Click me</a> for full guide) </p> '
		);
	}

	/**
	 * Render Client token field markup
	 */
	public function field_client_token_render()
	{
		printf(
			'<input type="text" name="zlick_payments_settings[zp_client_token]" id="zp_client_token" value="%s" size="40">',
			$this->options['zp_client_token']
		);
	}

	/**
	 * Render client secret field markup
	 */
	public function field_client_secret_render()
	{
		printf(
			'<input type="text" name="zlick_payments_settings[zp_client_secret]" id="zp_client_secret" value="%s" size="40">',
			$this->options['zp_client_secret']
		);
	}

	/**
	 * Render previewable paras field markup
	 */
	public function field_previewable_paras_render()
	{
		printf(
			'<input type="number" name="zlick_payments_settings[zp_previewable_paras]" id="zp_previewable_paras" value="%s" size="10">',
			$this->options['zp_previewable_paras']
		);
		printf(
			'<p>This is the default number of paragraphs shown before the paywall in all articles. <br/> If needed, you can manually change the position of the paywall for each article in the article editor.</p>'
		);
	}

	public function field_search_engine_bypass()
	{
		$checked = (isset($this->options['zp_search_engine_bypass']) && $this->options['zp_search_engine_bypass']) ? "checked" : "";
		printf(
			'<input name="zlick_payments_settings[zp_search_engine_bypass]" type="checkbox" id="zp_search_engine_bypass" value="bypass" %s />',
			$checked
		);
	}

	/**
	 * @param $post_id
	 *
	 * @return mixed
	 */
	function zp_save_zlick_fields_meta($post_id)
	{

		// verify nonce
		if (!isset($_POST['zp_meta_box_nonce']) || !wp_verify_nonce($_POST['zp_meta_box_nonce'], basename(__FILE__))) {
			return $post_id;
		}
		// check autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return $post_id;
		}
		$price_plans_options = $this->price_plans;
		// check permissions
		if (get_post_type($post_id) === ZLICK_POST_TYPE_POST or get_post_type($post_id) === 'page') {
			$meta_values = get_post_meta( $post_id, '_zp_price_plans' );
			if ( isset( $_POST['zp_price_plans'] ) && is_array( $_POST['zp_price_plans'] ) ) {
				foreach ( $_POST['zp_price_plans'] as $new_pp ) {
					if ( !in_array( $new_pp, $meta_values ) )
					   add_post_meta( $post_id, '_zp_price_plans', $new_pp, false );
			   }

			   foreach ( $price_plans_options as $old_pp ) {
				   $pp_value = strval($old_pp->id);
				   if ( !in_array( $pp_value, $_POST['zp_price_plans'] ) && in_array( $pp_value, $meta_values ) )
					   delete_post_meta( $post_id, '_zp_price_plans', $pp_value );
			   }
			}
			elseif ( !empty( $meta_values ) ) {
				delete_post_meta( $post_id, '_zp_price_plans' );
				if (count($price_plans_options) > 0){
					update_post_meta($post_id, 'zp_is_paid', false);
				}
			}
			$meta_values = get_post_meta( $post_id, '_zp_price_plans' );
			$client_token = zlickpay_get_options_data('zp_client_token');
			$client_secret = zlickpay_get_options_data('zp_client_secret');

			$headers = array(
					'client-token' => $client_token,
					'client-secret' => $client_secret
			);
			$args = array(
					'headers' => $headers,
			);
			if (isset($_POST['zp_is_paid']) or !empty($meta_values)) {
				post_article_paid_event($post_id);
				update_post_meta($post_id, 'zp_is_paid', sanitize_text_field("paid"));

				$response =  wp_remote_get( 'https://portal.zlickpaywall.com/api/articles/publish/' . $post_id, $args );
			} else {
				update_post_meta($post_id, 'zp_is_paid', false);
				$response =  wp_remote_get( 'https://portal.zlickpaywall.com/api/articles/unpublish/' . $post_id, $args );
			}
		}
	}

	/**
	 * Adds Zlick Payments custom fields.
	 */
	function zp_add_custom_fields_meta_box()
	{
		add_meta_box(
			'zlick_payments_fields',
			'Zlick Payment Fields',
			array($this, 'zp_show_custom_fields_meta_box'),
			ZLICK_POST_TYPE_POST,
			'normal',
			'high'
		);
		add_meta_box(
			'zlick_payments_fields',
			'Zlick Payment Fields',
			array($this, 'zp_show_custom_fields_meta_box'),
			'page',
			'normal',
			'high'
		);
	}

	/**
	 * Show Zlick Category Custom Fields.
	 */
	function zp_category_fields_meta_box($tag)
	{
		$zp_price_plans = get_term_meta( $tag->term_id, '_zp_price_plans', false ); //get_post_meta( $post_id, '_zp_price_plans' );

		?> <input type="hidden" name="zp_meta_box_nonce" value="<?php echo wp_create_nonce(basename(__FILE__)); ?>"> <?php

		// Price Plan Edit Page View on Category Edit
		if (count($this->price_plans) > 0){

			$img_url = esc_url(plugins_url('public/images/Zlick-logo-white-1.png', dirname(__FILE__)))
			?>
			<div class="zp-category-box">
				<img loading="lazy" src="<?php echo $img_url; ?>">

				<p>Select monetization plan to start collecting revenue on articles of this category</p>

				<ul class="postbox zp-selection-container">
			<?php

			foreach ($this->price_plans as $price_plan) {
				$pp_value = strval($price_plan->id);
				?>
					<li>
						<label class="zp-category-selector-label">
							<input type="checkbox" name="zp_price_plans[]" value="<?php echo $pp_value; ?>" <?php checked( in_array( $pp_value, $zp_price_plans ), true ); ?> />
							<?php _e( strval($price_plan->name), $pp_value ); ?>
						</label>
					</li>
				<?php
			}

				?>
				</ul>
				<br/>
			</div>

			<?php
		}

	}

	function zp_add_category_fields_meta_box($tag)
	{
		$zp_price_plans = array();

		?> <input type="hidden" name="zp_meta_box_nonce" value="<?php echo wp_create_nonce(basename(__FILE__)); ?>"> <?php


		if (count($this->price_plans) > 0){

			$img_url = esc_url(plugins_url('public/images/Zlick-logo-white-1.png', dirname(__FILE__)))
			?>
			<div class="zp-category-box zp-category-box-sm zp-category-margin">
				<img loading="lazy" src="<?php echo $img_url; ?>">

				<p>Select monetization plan to start collecting revenue on articles of this category. You can also choose them later.</p>

				<ul class="postbox zp-selection-container">
			<?php

			foreach ($this->price_plans as $price_plan) {
				$pp_value = strval($price_plan->id);
				?>
					<li>
						<label class="zp-category-selector-label">
							<input type="checkbox" name="zp_price_plans[]" value="<?php echo $pp_value; ?>" <?php checked( in_array( $pp_value, $zp_price_plans ), true ); ?> />
							<?php _e( strval($price_plan->name), $pp_value ); ?>
						</label>
					</li>
				<?php
			}

				?>
				</ul>
				<br/>
			</div>

			<?php
		}
	}

	function zp_save_category_price_plan_field( $term_id ) {

		$price_plans_options = $this->price_plans;

		$meta_values = get_term_meta( $term_id, '_zp_price_plans' );
		if ( isset( $_POST['zp_price_plans'] ) && is_array( $_POST['zp_price_plans'] ) ) {
			foreach ( $_POST['zp_price_plans'] as $new_pp ) {
				if ( !in_array( $new_pp, $meta_values ) )
					add_term_meta( $term_id, '_zp_price_plans', $new_pp, false );
			}

			foreach ( $price_plans_options as $old_pp ) {
				$pp_value = strval($old_pp->id);
				if ( !in_array( $pp_value, $_POST['zp_price_plans'] ) && in_array( $pp_value, $meta_values ) )
					delete_term_meta( $term_id, '_zp_price_plans', $pp_value );
			}
		}
		elseif ( !empty( $meta_values ) ) {
			delete_term_meta( $term_id, '_zp_price_plans' );
		}
	}

	/**
	 * Show Zlick Custom Fields.
	 */
	function zp_show_custom_fields_meta_box()
	{
		global $post;
		$custom = get_post_custom($post->ID);
		$zlick_payments_settings_link = get_admin_url() . "options-general.php?page=zlick-payments-plugin";

		?> <input type="hidden" name="zp_meta_box_nonce" value="<?php echo wp_create_nonce(basename(__FILE__)); ?>"> <?php

		// Price Plan Edit Page View 1/2
		if (count($this->price_plans) == 0){
			?>

				<p>
					<label for="zp_is_paid">Enable paywall for this article </label>
					<input name="zp_is_paid" type="checkbox" id="zp_is_paid" value="paid" <?php echo (@$custom["zp_is_paid"][0] == "paid") ? "checked" : "" ?>>
				</p>
				<p>
					The default position of the paywall widget is set in the Zlick <a href='<?php echo $zlick_payments_settings_link ?>'>settings</a> page.
				</p>
				<p>
					<i>Optional</i>: Enter <strong>&lt;!--zlick-paywall--&gt;</strong> anywhere in the HTML editor to manually choose the position of the widget for this specific article.
				</p>

			<?php
		}

		// Price Plan Edit Page View 2/2
		if (count($this->price_plans) > 0){

			$selected_price_plans_via_category = get_valid_category_price_plan_ids_on_post($post->ID);
			if(count($selected_price_plans_via_category) > 0) {
				?>
					<p>
						This article belongs to a monetized category and is paid via category based pricing.
					</p>

					OR select full site plan that gives access to all of the content
				<?php
			}
			?>

			<p>
				<label for="zp_is_paid">Full Site Plan</label>
				<input name="zp_is_paid" type="checkbox" id="zp_is_paid" value="paid" <?php echo (@$custom["zp_is_paid"][0] == "paid") ? "checked" : "" ?>>
			</p>

			<p>
			The default position of the paywall widget is set in the Zlick <a href='<?php echo $zlick_payments_settings_link ?>'>settings</a> page.
			</p>
			<p>
				<i>Optional</i>: Enter <strong>&lt;!--zlick-paywall--&gt;</strong> anywhere in the HTML editor to manually choose the position of the widget for this specific article.
			</p>
			<?php
		}


	}
}

if (is_admin()) {
	$zlick_payments_admin = new Zlick_Payments_Admin(ZLICK_PLUGIN_NAME, ZLICK_PLUGIN_VERSION);
}
