( function( window, $, undefined ) {
	if ( typeof $ !== 'undefined' ) {

		// Extends the state to crop an image.
		var UserAvatarCropper = wp.media.controller.Cropper.extend( {
			doCrop: function( attachment ) {
				return wp.ajax.post( 'crop-image', {
					wp_customize: 'off',
					nonce: attachment.get( 'nonces' ).edit,
					id: attachment.get( 'id' ),
					context: 'user-avatar',
					cropDetails: attachment.get( 'cropDetails' )
				} );
			}
		} );



		// Hides the old part of the WordPress UI.
		var $old_elem = $( 'tr.user-profile-picture' );

		$old_elem.addClass( 'ui-helper-hidden-accessible' );



		// Starts to handle the new one.
		var state = '';
		var frame;
		var $preview = $( '.swp-ua-preview' );
		var $rating = $( '.swp-ua-rating' );
		var $checkbox = $( '#ua_gravatar' );
		var $input = $preview.find( 'input[name="ua_attachment"]' );
		var $attachment = $preview.find( '.image' );
		var $imgs = $attachment.find( 'img' );
		var $desc = $preview.find( '.description' );
		var $actions = $preview.find( '.actions' );
		var $remove = $preview.find( '.remove-button' );
		var $upload = $preview.find( '.upload-button' );

		/**
		 * Returns a set of options, computed from the attached image data and
		 * theme-specific data, to be fed to the imgAreaSelect plugin in
		 * `UserAvatarCropper`.
		 *
		 * @param {wp.media.model.Attachment} attachment
		 * @param {wp.media.controller.Cropper} controller
		 * @returns {Object} Options
		 */
		function calculateImageSelectOptions( attachment, controller ) {
			var realWidth  = attachment.get( 'width' );
			var realHeight = attachment.get( 'height' );
			var xInit = 192;
			var yInit = 192;
			var x1 = 0;
			var y1 = 0;

			if ( parseFloat( ( realWidth / realHeight ).toFixed( 1 ) ) > 1 ) {
				yInit = realHeight;
				xInit = yInit;
			} else {
				xInit = realWidth;
				yInit = xInit;
			}

			x1 = ( realWidth - xInit ) / 2;
			y1 = ( realHeight - yInit ) / 2;

			return {
				aspectRatio: '1:1',
				resizable: false,
				keys: true,
				instance: true,
				persistent: true,
				imageWidth: realWidth,
				imageHeight: realHeight,
				minWidth: xInit,
				x1: x1,
				y1: y1,
				x2: xInit + x1,
				y2: yInit + y1
			};
		};

		/**
		 * Returns whether the image must be cropped, based on required dimensions.
		 *
		 * @param {int}  rqdW
		 * @param {int}  rqdH
		 * @param {int}  imgW
		 * @param {int}  imgH
		 * @return {bool}
		 */
		function mustBeCropped( rqdW, rqdH, imgW, imgH ) {
			// Same dimensions
			if ( rqdW === imgW && rqdH === imgH ) {
				return false;
			}

			// Too small
			if ( imgW <= rqdW || imgH <= rqdH ) {
				return false;
			}

			// 1:1 ratio
			if ( parseFloat( ( imgW / imgH ).toFixed( 1 ) ) === 1 ) {
				return false;
			}

			return true;
		}

		/**
		 * Creates a media modal select frame.
		 */
		function initFrame() {
			// Configure the media modal
	        frame = wp.media.frames.userAvatar = wp.media( {
	            button: {
	                text: _SWP_UA_L10N.select,
	                close: false
	            },
	            states: [
	                new wp.media.controller.Library( {
	                    title: _SWP_UA_L10N.profil_picture,
	                    library: wp.media.query( { type: 'image' } ),
	                    multiple: false,
	                    date: false,
	                    display: false,
	                    displaySettings: false,
	                    priority: 20,
	                    suggestedWidth: 192,
	                    suggestedHeight: 192
	                } ),
					new UserAvatarCropper( {
						imgSelectOptions: calculateImageSelectOptions
					} )
	            ]
	        } );

	        frame.on( 'open', function() {
	            var selection = frame.state().get( 'selection' );
	            var attachment_id = $input.val();

	            if ( attachment_id ) {
	                selection.add( wp.media.attachment( attachment_id ) );
	            }
	        } );

	        // When an image is selected, runs a callback.
	        frame.on( 'select', function() {
	            // Get media attachment details from the frame state
	            var attachment = frame.state().get( 'selection' ).first().toJSON();

	            if ( ! mustBeCropped( 192, 192, attachment.width, attachment.height ) ) {
					setAttachment( attachment );
					frame.close();

				} else {
					frame.setState( 'cropper' );
				}
	        });

	        frame.on( 'cropped', function( croppedImage ) {
	        	setAttachment( croppedImage );
	        } );
	    }

		/**
		 *
		 *
		 */
		function setState() {
			var n = $checkbox[0].checked ? 'gravatar' : 'user-avatar';

			if ( state != n ) {
				state = n;
				change();
			}
		}

		/**
		 *
		 *
		 */
		function change() {
			$preview.attr( 'data-type', state );

			if ( state == 'user-avatar' ) {
				setAttachment( $input.val() );
				$rating.removeClass( 'hide-if-js' );

			} else {
				$rating.addClass( 'hide-if-js' );
			}
		}

		/**
		 *
		 *
		 */
		function setAttachment( attachment ) {
			// No custom avatar
			if ( attachment === undefined || attachment === '' ) {
				$input.val( '' );
				$remove.attr( 'disabled', 'disabled' );
				$upload.html( _SWP_UA_L10N.select_image );
				$imgs.filter( ':not([src*="gravatar.com"]), [src*="gravatar.com/avatar/00000000000000000000000000000000"]' ).attr( 'src', '' ).removeAttr( 'srcset' );

			// Default custom avatar (onload)
			} else if ( parseInt( attachment, 10 ) ) {
				$remove.removeAttr( 'disabled' );
				$upload.html( _SWP_UA_L10N.change_image );

			// Change custom avatar
			} else {
				$input.val( attachment.id );
				$remove.removeAttr( 'disabled' );
				$upload.html( _SWP_UA_L10N.change_image );
				$imgs.filter( ':not([src*="gravatar.com"]), [src*="gravatar.com/avatar/00000000000000000000000000000000"]' ).attr( 'src', attachment.url );
			}

		}
		setState();
		initFrame();

		$checkbox.on( 'click.ua', setState );

		$upload.on( 'click.ua', function( event ) {
			if ( frame ) {
				initFrame();

                frame.setState( 'library' ).open();

                return;
            }
		} );

		$remove.on( 'click.ua', function( event ) {
			setAttachment( undefined );
		} );
	}
} )( window, window.jQuery );
