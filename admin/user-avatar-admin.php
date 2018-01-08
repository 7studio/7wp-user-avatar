<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die( 'Cheatin&#8217; uh?' );
}

/**
 * Enqueues dependencies for the admin page "Profile"
 * ('Your Profile' and 'Edit User' screen).
 *
 * @param string $hook_suffix The current admin page.
 */
function swp_ua_enqueue_dependencies( $hook_suffix ) {
	if ( $hook_suffix != 'profile.php' && $hook_suffix != 'user-edit.php' ) {
		return;
	}

	wp_enqueue_media();

	wp_enqueue_script( SWP_UA_HANDLE, SWP_UA_URL . '/admin/js/script.js', array(  'jquery' ), SWP_UA_VERSION, true );
	wp_localize_script( SWP_UA_HANDLE, '_SWP_UA_L10N', array(
		'remove'         => __( 'Remove', 'swp-ua' ),
		'select'         => __( 'Select', 'swp-ua' ),
		'select_image'   => __( 'Select Image', 'swp-ua' ),
		'change_image'   => __( 'Change Image', 'swp-ua' ),
		'profil_picture' => __( 'Profile Picture', 'swp-ua' )
	) );

	wp_enqueue_style( SWP_UA_HANDLE, SWP_UA_URL . '/admin/css/style.css', array(), SWP_UA_VERSION, 'all' );
}
add_action( 'admin_enqueue_scripts', 'swp_ua_enqueue_dependencies' );

/**
 * Sets user metadata and removes old avatar files if needed.
 *
 * @param int $user_id The user ID.
 */
function swp_ua_update_meta( $user_id ) {
    // check that the current user has the capability to edit the $user_id
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }

    // new attachment ID
    $ua_attachment = intval( $_POST['ua_attachment'] );

    // current attachment ID
    $old_ua_attachment = get_user_meta( $user_id, 'ua_attachment', true );
    $old_ua_attachment = intval( $old_ua_attachment);

    // delete old avatar files if needed.
    if ( isset( $_POST['ua_gravatar'] ) || ! $ua_attachment || $ua_attachment != $old_ua_attachment ) {
    	$hash = swp_ua_get_hash( $user_id, $old_ua_attachment );
    	$pattern = sprintf( '%s/%s-*x*.*', swp_ua_get_uploads_path(), $hash );
    	$files = glob( $pattern );

    	if ( $files && ! empty( $files ) ) {
    		foreach ( $files as $file ) {
    			wp_delete_file( $file );
    		}
    	}
    }

    // delete unused metadata if Gravatar is used.
    if ( isset( $_POST['ua_gravatar'] ) || ! $ua_attachment ) {
    	delete_user_meta( $user_id, 'ua_attachment' );
    	delete_user_meta( $user_id, 'ua_rating' );

    // update metadata according to the new attachment and rating.
    } else {
    	update_user_meta( $user_id, 'ua_attachment', $ua_attachment );
    	update_user_meta( $user_id, 'ua_rating', sanitize_textarea_field( $_POST['ua_rating'] ) );
    }

    update_user_meta( $user_id, 'ua_gravatar', isset( $_POST['ua_gravatar'] ) ? 'true' : 'false' );
}
add_action( 'personal_options_update', 'swp_ua_update_meta' );
add_action( 'edit_user_profile_update', 'swp_ua_update_meta' );

/**
 * Outputs new settings section into the admin page "Profile" to choose or not
 * to use Gravatar as user profile image.
 *
 * @param WP_User $profileuser The current WP_User object.
 */
function swp_ua_output_settings( $profileuser ) {
	$metadata = get_user_meta( $profileuser->ID );
	$default_gravatar_url = get_avatar_url( '00000000000000000000000000000000@md5.gravatar.com', array(
		'force_default' => true,
		'size'          => SWP_UA_DISPLAY_SIZE * 2
	) );

	$ua_attachment = isset( $metadata['ua_attachment'] ) ? $metadata['ua_attachment'][0] : '';
	$ua_rating = isset( $metadata['ua_rating'] ) ? $metadata['ua_rating'][0] : 'G';
?>
	<h2><?php _e( 'Avatar', 'swp-ua' ); ?></h2>
	<table class="form-table">
		<tr class="swp-ua-gravatar">
			<th scope="row"><?php _e( 'Gravatar', 'swp-ua' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span><?php _e( 'Gravatar', 'swp-ua' ); ?></span></legend>
					<label for="ua_gravatar">
						<input name="ua_gravatar"
						       type="checkbox"
						       id="ua_gravatar"
						       value="1"
						       <?php checked( $metadata['ua_gravatar'][0], 'true' ); ?>
						>
						<?php echo sprintf( __( 'Uses the <a href="%s">Gravatar</a> free service.', 'swp-ua' ), __( 'https://en.gravatar.com/', 'swp-ua' ) ); ?>
					</label>
					<br>
				</fieldset>
			</td>
		</tr>
		<tr class="swp-ua-picture">
			<th scope="row"><?php _e( 'Profile Picture', 'swp-ua' ); ?></th>
			<td>
				<div class="swp-ua-preview">
					<input class="storage" type="hidden" name="ua_attachment" value="<?php echo $ua_attachment; ?>">
					<div class="image"
						 style="background-image:url(<?php echo $default_gravatar_url; ?>)"
					><?php
						add_filter( 'pre_option_ua_show_avatar', '__return_true', 100 );

						if ( ! empty( $ua_attachment ) ) {
							$gravatar = get_avatar( $profileuser->ID, 96, '', '', array( 'force_gravatar' => true )  );

						} else {
							$gravatar = '<img class="avatar avatar-96 photo" src="" alt="">';
						}

						echo $gravatar;
						echo get_avatar( $profileuser->ID );

						remove_filter( 'pre_option_ua_show_avatar', '__return_true', 100 );
				  ?></div>
					<p class="description"><?php
						if ( IS_PROFILE_PAGE ) {
							$description = sprintf(
								__( 'You can change your profile picture on <a href="%s">Gravatar</a>.', 'swp-ua' ),
								__( 'https://en.gravatar.com/', 'swp-ua' )
							);
						} else {
							$description = '';
						}

						/**
						 * Filters the user profile picture description displayed under the Gravatar.
						 *
						 * @param string  $description The description that will be printed.
						 * @param WP_User $profileuser The current WP_User object.
						 */
						echo apply_filters( 'user_profile_picture_description', $description, $profileuser );
					?></p>
					<div class="actions hide-if-no-js">
						<button class="button remove-button" disabled type="button"><?php _e( 'Remove', 'swp-ua' ); ?></button>
						<button class="button upload-button" type="button"><?php _e( 'Select image', 'swp-ua' ); ?></button>
					</div>
				</div>
			</td>
		</tr>
		<tr class="swp-ua-rating">
			<th scope="row"><?php _e( 'Rating', 'swp-ua' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span><?php _e( 'Rating', 'swp-ua' ); ?></span></legend>
					<?php
					$ratings = array(
						'G'  => __( 'G &#8212; Suitable for all audiences', 'swp-ua' ), /* translators: Content suitability rating: https://en.wikipedia.org/wiki/Motion_Picture_Association_of_America_film_rating_system */
						'PG' => __( 'PG &#8212; Possibly offensive, usually for audiences 13 and above', 'swp-ua' ), /* translators: Content suitability rating: https://en.wikipedia.org/wiki/Motion_Picture_Association_of_America_film_rating_system */
						'R'  => __( 'R &#8212; Intended for adult audiences above 17', 'swp-ua' ), /* translators: Content suitability rating: https://en.wikipedia.org/wiki/Motion_Picture_Association_of_America_film_rating_system */
						'X'  => __( 'X &#8212; Even more mature than above', 'swp-ua' ) /* translators: Content suitability rating: https://en.wikipedia.org/wiki/Motion_Picture_Association_of_America_film_rating_system */
					);
					?>

					<?php foreach ( $ratings as $key => $rating ) : ?>
					<label><input type="radio" name="ua_rating" value="<?php echo esc_attr( $key ); ?>"<?php checked( $key, $ua_rating ); ?>><?php echo $rating; ?></label><br>
					<?php endforeach; ?>
				</fieldset>
			</td>
		<tr>
	</table>
<?php
}
add_action( 'edit_user_profile', 'swp_ua_output_settings', 1 );
add_action( 'show_user_profile', 'swp_ua_output_settings', 1 );

/**
 * Adds custom media display state for items in the Media list table which are
 * used as a user avatar.
 *
 * @param array   $media_states An array of media states. Default 'Header Image',
 *                              'Background Image', 'Site Icon', 'Logo'.
 * @param WP_Post $post         The current attachment object.
 * @return array
 */
function swp_ua_display_media_states( $media_states, $post ) {
	global $wpdb;

    $ua_attachments = $wpdb->get_col( "SELECT meta_value FROM $wpdb->usermeta WHERE meta_key = 'ua_attachment'" );

    if ( in_array( $post->ID, $ua_attachments ) ) {
        $media_states[] = __( 'User Avatar', 'swp-ua' );
    }

    return $media_states;
}
add_filter( 'display_media_states', 'swp_ua_display_media_states', 10, 2 );

/**
 * Breaks the link between attachment and custom user avatar when
 * the attachment is deleted.
 * All generated images will be deleted at the same time.
 *
 * @param int $post_id Attachment ID.
 */
function swp_ua_delete_avatar( $post_id ) {
	$users = get_users( array(
		'meta_key' => 'ua_attachment',
		'fields' => 'ID'
	) );

	foreach ( $users as $user_id ) {
		$hash = swp_ua_get_hash( $user_id, $post_id );
    	$pattern = sprintf( '%s/%s-*x*.*', swp_ua_get_uploads_path(), $hash );
    	$files = glob( $pattern );

    	if ( $files && ! empty( $files ) ) {
    		foreach ( $files as $file ) {
    			wp_delete_file( $file );
    		}

			delete_user_meta( $user_id, 'ua_attachment' );
    		update_user_meta( $user_id, 'ua_rating', 'G' );
    	}
	}
}
add_action( 'delete_attachment', 'swp_ua_delete_avatar' );