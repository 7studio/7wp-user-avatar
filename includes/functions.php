<?php

/**
 * Gets the upload directory's path of custom avatars.
 *
 * @return string
 */
function swp_ua_get_uploads_path() {
	$wp_upload = wp_upload_dir();

	return path_join( $wp_upload['basedir'], SWP_UA_UPLOAD_DIRNAME );
}

/**
 * Gets the upload directory's url of custom avatars.
 *
 * @return string
 */
function swp_ua_get_uploads_url() {
	$wp_upload = wp_upload_dir();

	return path_join( $wp_upload['baseurl'], SWP_UA_UPLOAD_DIRNAME );
}

/**
 * Gets a single hash.
 *
 * @param int $user_id       The user ID.
 * @param int $attachment_id The attachment ID.
 * @return string
 */
function swp_ua_get_hash( $user_id, $attachment_id ) {
	return md5( 'ua-' . $user_id . '-' .  $attachment_id );
}