<?php

// If uninstall.php is not called by WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die( 'Cheatin&#8217; uh?' );
}

// Deletes the metadata for all users.
$users = get_users( array( 'fields' => 'ID' ) );

foreach ( $users as $user_id ) {
	delete_user_meta( $user_id, 'ua_gravatar' );
	delete_user_meta( $user_id, 'ua_attachment' );
	delete_user_meta( $user_id, 'ua_rating' );
}

// Deletes the `7wpua` directory.
$wp_upload = wp_upload_dir();
$wp_filesystem->delete( swp_ua_get_uploads_path(), true );