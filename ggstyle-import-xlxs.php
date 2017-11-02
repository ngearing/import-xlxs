<?php
/**
 * Plugin Name: Ggstyle Import xlxs
 * Description: Gg
 * Author: ng
 * Version: 0.0.1
 */
if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * Add Admin Menu
 */
add_action('admin_menu', 'gix_options_page');
function gix_options_page()
{
	$page = add_submenu_page(
		'tools.php',                // $parent_slug
		'Import xlxs',              // $page_title
		'Ggstyle Import xlxs',      // $menu_title
		'manage_options',           // $capability
		'gix',                      // $menu_slug
		'gix_options_page_html'     // $function
	);

	add_action('admin_print_scripts-' . $page, 'gix_admin_scripts');
}

/**
 * Load scripts
 */
function gix_admin_scripts() {
	wp_enqueue_style('gix-admin-css', plugin_dir_url(__FILE__).'/assets/css/admin-css.css');

	wp_enqueue_script('jquery');

	wp_enqueue_script(
		'xlsx',
		plugin_dir_url(__FILE__) . '/assets/js/xlsx.full.min.js',
		[ 'jquery' ],
		'',
		true
	);
	wp_register_script(
		'xlsx-init',
		plugin_dir_url(__FILE__) . '/assets/js/xlsx-init.js',
		[ 'jquery' ],
		'',
		true
	);
	wp_localize_script(
		'xlsx-init',
		'wp',
		[
			'ajaxurl' => admin_url('admin-ajax.php'),
			'wp_nonce'  => wp_create_nonce('ajax-nonce'),
		]
	);
	wp_enqueue_script('xlsx-init');
}
 
/**
 * top level menu:
 * callback functions
 */
function gix_options_page_html()
{
	// check user capabilities
	if (! current_user_can('manage_options')) {
		return;
	}
 
	// add error/update messages
 
	// check if the user have submitted the settings
	// wordpress will add the "settings-updated" $_GET parameter to the url
	if (isset($_GET['settings-updated'])) {
		// add settings saved message with the class of "updated"
		add_settings_error('gix_messages', 'gix_message', __('Settings Saved', 'gix'), 'updated');
	}
 
	// show error/update messages
	settings_errors('gix_messages'); ?>
	<div class="wrap">
	<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
	<form action="options.php" method="post">
	<?php
	// output security fields for the registered setting "gix"
	settings_fields('gix');
	// output setting sections and their fields
	// (sections are registered for "gix", each field is registered to a specific section)
	do_settings_sections('gix');
	// output save settings button
	submit_button('Save Settings'); ?>
	</form>
	</div>
<?php
}

/**
 * custom option and settings
 */
function gix_settings_init()
{
	// register a new setting for "gix" page
	register_setting('gix', 'gix_options');
 
	// register a new section in the "gix" page
	add_settings_section(
		'gix_section_developers',
		__('The Matrix has you.', 'gix'),
		'gix_section_developers_cb',
		'gix'
	);
 
	// register a new field in the "gix_section_developers" section, inside the "gix" page
	add_settings_field(
		'gix_field_pill', // as of WP 4.6 this value is used only internally
		// use $args' label_for to populate the id inside the callback
		__('Pill', 'gix'),
		'gix_field_pill_cb',
		'gix',
		'gix_section_developers',
		[
			'label_for' => 'gix_field_pill',
			'class' => 'gix_row',
			'gix_custom_data' => 'custom',
		]
	);
}
 
/**
 * register our gix_settings_init to the admin_init action hook
 */
add_action('admin_init', 'gix_settings_init');
 
/**
 * custom option and settings:
 * callback functions
 */
 
// developers section cb
 
// section callbacks can accept an $args parameter, which is an array.
// $args have the following keys defined: title, id, callback.
// the values are defined at the add_settings_section() function.
function gix_section_developers_cb($args)
{
?>
	<p id="<?php echo esc_attr($args['id']); ?>"><?php esc_html_e('Follow the white rabbit.', 'gix'); ?></p>
<?php
}
 
// pill field cb
 
// field callbacks can accept an $args parameter, which is an array.
// $args is defined at the add_settings_field() function.
// wordpress has magic interaction with the following keys: label_for, class.
// the "label_for" key value is used for the "for" attribute of the <label>.
// the "class" key value is used for the "class" attribute of the <tr> containing the field.
// you can add custom key value pairs to be used inside your callbacks.
function gix_field_pill_cb($args)
{
	// get the value of the setting we've registered with register_setting()
	$options = get_option('gix_options');
	// output the field
?>
	<label for="select-post-type">Select post_type to import to:</label>
	<select id="select-post-type" name="select-post-type">
		<?php
		foreach (get_post_types(['public'=>true]) as $post_type) :
			echo "<option>$post_type</option>";
		endforeach; ?>
	</select>

	<div id="drop">Drop a spreadsheet file here to see sheet data</div>
	<input type="file" name="xlfile" id="xlf"> ... or click here to select a file

	<div class="soldier-list"></div>

	<pre id="out"></pre>
<?php
}


/**
 * [gix_create_posts description]
 *
 * @return void
 */
function gix_create_posts() {
	if ( ! isset( $_POST['_wp_nonce'] ) && ! wp_verify_nonce( $_POST['_wp_nonce'] ) ) { // Input var okay.
		wp_die( 'Fail wp_nonce verify.' );
	}

	$xlsx = json_decode( wp_unslash( $_POST['posts'] ) ); // Input var okay.
	if ( json_last_error() ) {
		wp_die( json_last_error_msg() ); // WPCS: XSS OK.
	}

	foreach ( $xlsx as $sheet ) :
		if ( is_array( $sheet ) ) :
			$count = 0;
			foreach ( $sheet as $row ) :

				$post_id = gix_check_for_post( $row->full_name );
				echo "<pre>".print_r($post_id, true)."</pre>";

				$postarr = [
					'post_type'     => 'soldier',
					'post_id'       => $post_id,
					'post_title'    => $row->full_name,
					'post_status'   => 'publish',
					// 'post_content'  => $row->full_name,
					'tax_input'     => [
						'soldier_battle'    => $row->battle,
						'soldier_burial'    => $row->place_of_burial_or_memorial___awm_honour_roll,
						'soldier_unit'      => $row->last_unit,
					],
					'meta_input'    => [
						'full_name'         => $row->full_name,
						'service_number'    => $row->serv_no,
						'date_enlisted'     => gix_convert_date( $row->date_enlisted ),
						'age'               => $row->age,
						'occupation'        => $row->occupation,
						'initial_unit'      => $row->initial_unit,
						'final_rank'        => $row->final_rank,
						'date_of_death'     => gix_convert_date( $row->date_of_death ),
						'country'           => $row->country,
						'cause_of_death'    => $row->cause_of_death,
						'citations'         => $row->citations,
						'notes'             => $row->notes_on_injuries_and_deaths,
						'other_notes'       => $row->other_notes,
					],
				];

				$insert = wp_insert_post( $postarr, true );

				if ( class_exists( 'ACF' ) ) {
					foreach ( $postrr['meta_input'] as $selector => $value ) {
						update_field( $selector, $value, $insert );
					}
				}

				if ( ! is_wp_error( $insert ) ) {
					echo '<pre>' . print_r( $postarr['post_title'], true ) . '</pre>'; // WPCS: XSS okay.
				} else {
					echo '<pre>' . print_r( $insert, true ) . '</pre>'; // WPCS: XSS okay.
				}

				$images = [
					'portrait'  => get_home_path() . "images/Individual photographs/{$row->full_name}.jpg",
					'headstone' => get_home_path() . "images/Graves of Individuals/{$row->full_name}.jpg",
				];

				gix_insert_images( $images, $insert );

			endforeach;
		endif;
	endforeach;

	wp_die( 'End of the line' );
}
add_action( 'wp_ajax_gix_create_posts', 'gix_create_posts' );
add_action( 'wp_ajax_nopriv_gix_create_posts', 'gix_create_posts' );

/**
 * [gix_check_for_post description]
 *
 * @param  [type] $post_name [description].
 * @return [type]            [description].
 */
function gix_check_for_post( $post_name ) {
	global $wpdb;

	$results = $wpdb->get_var(
		$wpdb->prepare(
			"
			SELECT 	ID 
			FROM 	$wpdb->posts 
			WHERE 	post_title = %s
			",
			$post_name
		)
	);

	return $results ?: 0;
}

/**
 * [MediaFileAlreadyExists description]
 *
 * @param [type] $filename [description].
 */
function MediaFileAlreadyExists( $filename ) {
	global $wpdb;

	$results = $wpdb->get_var(
		$wpdb->prepare(
			"
			SELECT 	COUNT(*) 
			FROM 	$wpdb->postmeta
			WHERE 	meta_value 
			LIKE 	'%/%s'
			",
			$filename
		)
	);

	return $results;
}

/**
 * [gix_insert_images description]
 *
 * @param  [type] $img_arr [description].
 * @param  [type] $parent  [description].
 * @return [type]          [description].
 */
function gix_insert_images( $img_arr, $parent = 0 ) {
	clearstatcache();
	if ( ! is_array( $img_path ) ) {
		return;
	}

	foreach ( $img_arr as $field_name => $img_path ) {
		if ( ! file_exists( $img_path ) ) {
			continue;
		}

		// https://wordpress.stackexchange.com/questions/34730/uploading-images-to-media-library-via-wp-handle-sideload-fails.
		$media_arr = [
			'name' => basename( $img_path ),
			'type' => wp_check_filetype( $img_path ),
			'tmp_name' => $img_path,
			'error' => 0,
			'size' => filesize( $img_path ),
		];

		$attachment_id = media_handle_sideload( $media_arr, $parent );

		if ( ! is_wp_error( $attachment_id ) ) {
			update_field( $field_name, $attachment_id, $parent );
			echo "Inserting image: $field_name - $attachment_id"; // WPCS: XSS okay.
		} else {
			echo '<pre>' . print_r( $attachment_id, true ) . '</pre>'; // WPCS: XSS okay.
		}
	}
}

/**
 * [gix_convert_date description]
 *
 * @param  [type] $date [description].
 * @return [type]       [description]
 */
function gix_convert_date( $date ) {
	$newdate = preg_replace( '/(\W)/m', ' ', $date );
	return date( 'd/M/Y', strtotime( $newdate ) );
}
