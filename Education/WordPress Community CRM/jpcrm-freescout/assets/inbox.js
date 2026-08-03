/**
 * Inbox interactions: sending replies, adding notes, opening tickets.
 */
( function () {
	'use strict';

	var config = window.jpcrmFsInbox || {};
	var strings = config.strings || {};

	/**
	 * Show a message in a feedback region.
	 *
	 * @param {HTMLElement} region  Feedback element.
	 * @param {string}      message Text to show.
	 * @param {string}      state   'error', 'success', or '' for neutral.
	 */
	function setFeedback( region, message, state ) {
		if ( ! region ) {
			return;
		}

		region.textContent = message;
		region.className = 'jpcrm-fs-reply-feedback' + ( state ? ' is-' + state : '' );
	}

	/**
	 * POST to admin-ajax.
	 *
	 * @param {Object} data Form fields.
	 * @return {Promise<Object>} Parsed JSON response.
	 */
	function post( data ) {
		var body = new URLSearchParams();

		Object.keys( data ).forEach( function ( key ) {
			body.append( key, data[ key ] );
		} );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	/**
	 * Toggle a group of buttons.
	 *
	 * @param {HTMLElement} container Wrapper element.
	 * @param {boolean}     disabled  Whether to disable.
	 */
	function setBusy( container, disabled ) {
		container.querySelectorAll( 'button[data-jpcrm-fs-action]' ).forEach( function ( button ) {
			button.disabled = disabled;
		} );
	}

	/**
	 * Wire up the reply box.
	 */
	function initReplyBox() {
		var box = document.querySelector( '.jpcrm-fs-reply' );

		if ( ! box ) {
			return;
		}

		var conversation = box.getAttribute( 'data-conversation' );
		var nonceField = box.querySelector( '#jpcrm_fs_reply_nonce' );
		var textarea = box.querySelector( '#jpcrm-fs-reply-text' );
		var closeAfter = box.querySelector( '#jpcrm-fs-close-after' );
		var feedback = box.querySelector( '.jpcrm-fs-reply-feedback' );

		box.querySelectorAll( 'button[data-jpcrm-fs-action]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var text = textarea.value.trim();

				if ( ! text ) {
					setFeedback( feedback, strings.empty, 'error' );
					textarea.focus();
					return;
				}

				setBusy( box, true );
				setFeedback( feedback, strings.sending, '' );

				post( {
					action: 'jpcrm_fs_reply',
					nonce: nonceField ? nonceField.value : '',
					conversation: conversation,
					type: button.getAttribute( 'data-jpcrm-fs-action' ) === 'note' ? 'note' : 'message',
					text: text,
					close: closeAfter && closeAfter.checked ? '1' : '0',
				} )
					.then( function ( response ) {
						if ( response && response.success ) {
							setFeedback( feedback, ( response.data && response.data.message ) || strings.sent, 'success' );
							textarea.value = '';
							// Reload so the new thread appears in history.
							window.setTimeout( function () {
								window.location.reload();
							}, 900 );
							return;
						}

						setBusy( box, false );
						setFeedback(
							feedback,
							( response && response.data && response.data.message ) || strings.failed,
							'error'
						);
					} )
					.catch( function () {
						setBusy( box, false );
						setFeedback( feedback, strings.failed, 'error' );
					} );
			} );
		} );
	}

	/**
	 * Wire up the new-ticket form.
	 */
	function initNewTicketForm() {
		var form = document.querySelector( '.jpcrm-fs-new-ticket' );

		if ( ! form ) {
			return;
		}

		var nonceField = form.querySelector( '#jpcrm_fs_new_ticket_nonce' );
		var email = form.querySelector( '#jpcrm-fs-new-email' );
		var subject = form.querySelector( '#jpcrm-fs-new-subject' );
		var bodyField = form.querySelector( '#jpcrm-fs-new-body' );
		var feedback = form.querySelector( '.jpcrm-fs-reply-feedback' );
		var button = form.querySelector( 'button[data-jpcrm-fs-action="create"]' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			if ( ! email.value.trim() || ! subject.value.trim() || ! bodyField.value.trim() ) {
				setFeedback( feedback, strings.required, 'error' );
				return;
			}

			setBusy( form, true );
			setFeedback( feedback, strings.sending, '' );

			post( {
				action: 'jpcrm_fs_create_ticket',
				nonce: nonceField ? nonceField.value : '',
				email: email.value.trim(),
				subject: subject.value.trim(),
				body: bodyField.value.trim(),
			} )
				.then( function ( response ) {
					if ( response && response.success ) {
						setFeedback( feedback, ( response.data && response.data.message ) || strings.created, 'success' );

						if ( response.data && response.data.redirect ) {
							window.location.href = response.data.redirect;
						}

						return;
					}

					setBusy( form, false );
					setFeedback(
						feedback,
						( response && response.data && response.data.message ) || strings.failed,
						'error'
					);
				} )
				.catch( function () {
					setBusy( form, false );
					setFeedback( feedback, strings.failed, 'error' );
				} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initReplyBox();
			initNewTicketForm();
		} );
	} else {
		initReplyBox();
		initNewTicketForm();
	}
} )();
