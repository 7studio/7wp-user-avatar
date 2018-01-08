<?php

/**
 * Plugin Name:       7wp User Avatar
 * Plugin URI:        https://github.com/7studio/7wp-user-avatar/
 * Description:       Adds an avatar upload field to user profiles. Generates requested sizes on demand, just like Gravatar!
 * Version:           0.0.1
 * Author:            Xavier Zalawa
 * Author URI:        http://7studio.fr/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       swp-ua
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die( 'Cheatin&#8217; uh?' );
}

define( 'SWP_UA_HANDLE', '7wp-user-avatar' );
define( 'SWP_UA_VERSION', '0.0.1' );

define( 'SWP_UA_BASENAME', plugin_basename( __FILE__ ) );
define( 'SWP_UA_URL', plugins_url( '', __FILE__ ) );
define( 'SWP_UA_DIR', dirname( __FILE__ ) );

define( 'SWP_UA_DISPLAY_SIZE', 96 );
define( 'SWP_UA_UPLOAD_DIRNAME', '7wp-ua' );

require_once SWP_UA_DIR . '/includes/functions.php';

/**
 * Lets add a simple metadata for each users.
 */
function swp_ua_activate() {
	$users = get_users( array( 'fields' => 'ID' ) );

	foreach ( $users as $user_id ) {
		update_user_meta( $user_id, 'ua_gravatar', 'true' );
	}
}
register_activation_hook( __FILE__, 'swp_ua_activate' );

/**
 * Forces to use Gravatar for all users who used a custom avatar.
 */
function swp_ua_deactivate() {
	$users = get_users( array(
		'meta_query' => array(
			'key'     => 'ua_gravatar'
			'value'   => 'true',
			'compare' => '='
		),
		'fields'     => 'ID'
	) );

	foreach ( $users as $user_id ) {
		update_user_meta( $user_id, 'ua_gravatar', 'true' );
	}
}
register_deactivation_hook( __FILE__, 'swp_ua_deactivate' );

/**
 * Init plugin.
 */
function swp_ua_init() {
	// Loads plugin translations.
	load_plugin_textdomain( 'swp-ua', false, dirname( SWP_UA_BASENAME ) . '/languages' );

	// Administration.
	if ( is_admin() ) {
		require_once SWP_UA_DIR . '/admin/user-avatar-admin.php';
	}
}
add_action( 'plugins_loaded', 'swp_ua_init' );

/**
 * Retrieves the avatar URL according to the user settings (Gravatar or custom image).
 *
 * You can get the gravatar URL even if the user has set a custom image
 * by passing the entry `force_gravatar`.
 *
 * @param array $args        Arguments passed to get_avatar_data(), after processing.
 * @param mixed $id_or_email The Gravatar to retrieve. Accepts a user_id, gravatar md5 hash,
 *                           user email, WP_User object, WP_Post object, or WP_Comment object.
 * @return array
 */
function swp_ua_handle_avatar_data( $args, $id_or_email ) {
	global $pagenow;

	// Let WP displays all defaults Gravatar into the options page.
	if ( is_admin() && $pagenow == 'options-discussion.php' && has_filter( 'pre_option_show_avatars' ) ) {
		return $args;
	}

	// Return gravatar URL in all cases.
	if ( isset( $args['force_gravatar'] ) && $args['force_gravatar'] ) {
		return $args;
	}

	$user = false;

	// user ID
	if ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'ID', $id_or_email );

	// user email
	} elseif ( is_string( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );

	// WP_User object
	} elseif ( $id_or_email instanceof WP_User ) {
		$user = $id_or_email;
	}

	// Does not try to find custom avatar if we ask for a not WP user.
	if ( ! $user ) {
		return $args;
	}

	// Check rating except inside profile page.
	if ( ! is_admin() || ! has_filter( 'pre_option_ua_show_avatar' ) ) {
		$ua_rating = get_user_meta( $user->ID, 'ua_rating', true );
		$s_rating = get_option( 'avatar_rating' );

		if ( ! empty( $ua_rating ) && 'G' != $ua_rating ) {
			$ratings = array( 'G', 'PG', 'R', 'X' );
			$s_rating_weight = array_search( $s_rating, $ratings );
			$ua_rating_weight = array_search( $ua_rating, $ratings );

			if ( false !== $ua_rating_weight && $ua_rating_weight > $s_rating_weight ) {
				$args['url'] = get_avatar_url( '00000000000000000000000000000000@md5.gravatar.com', $args );
				$args['found_avatar'] = 1;

				return $args;
			}
		}
	}

	$ua_gravatar = get_user_meta( $user->ID, 'ua_gravatar', true );
	$ua_attachment = get_user_meta( $user->ID, 'ua_attachment', true );

	// Don't need custom image? Stop here.
	if ( filter_var( $ua_gravatar, FILTER_VALIDATE_BOOLEAN ) || $ua_gravatar === '' ) {
		return $args;
	}

	// Attachment is in the trash?
	$ua_attachment_status = get_post_status( $ua_attachment );
	if ( ! $ua_attachment_status || $ua_attachment_status == 'trash' ) {
		$args['url'] = get_avatar_url( '00000000000000000000000000000000@md5.gravatar.com', $args );
		$args['found_avatar'] = 1;

		return $args;
	}

	$metadata = wp_get_attachment_metadata( $ua_attachment );
	$attachment = image_get_intermediate_size( $ua_attachment, array( $args['size'], 0 ) );

	// Custom avatar from media.
	if ( $metadata && $attachment ) {
		$hash = swp_ua_get_hash( $user->ID, $ua_attachment );
		$wp_upload = wp_upload_dir();

		$filetype = wp_check_filetype( $metadata['file'] );
		$ua_file = sprintf( '%1$s/%2$s-%3$sx%3$s.%4$s', swp_ua_get_uploads_path(), $hash, $args['size'], $filetype['ext'] );

		// Create avatar image on demand.
    	if ( ! file_exists( $ua_file ) ) {
    		$file = path_join( $wp_upload['basedir'], $attachment['path'] );

	        $image = wp_get_image_editor( $file );
	        $image->resize( $args['size'], $args['size'], true );
	        $image->set_quality( 90 );
	        $image->save( $ua_file );
    	}

		$args['url'] = str_replace( swp_ua_get_uploads_path(), swp_ua_get_uploads_url(), $ua_file );
		$args['found_avatar'] = 1;

	// Default empty gravatar.
	} else {
		$args['url'] = get_avatar_url( '00000000000000000000000000000000@md5.gravatar.com', $args );
		$args['found_avatar'] = 1;
	}

	return $args;
}
add_filter( 'pre_get_avatar_data', 'swp_ua_handle_avatar_data', PHP_INT_MAX, 2 );
