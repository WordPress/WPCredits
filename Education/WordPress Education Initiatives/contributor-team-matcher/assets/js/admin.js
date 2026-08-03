( function () {
	'use strict';

	// -------------------------------------------------------------------------
	// Accordion
	// -------------------------------------------------------------------------

	function toggleItem( item, forceOpen ) {
		const body     = item.querySelector( '.ctm-item__body' );
		const toggle   = item.querySelector( '.ctm-item__toggle' );
		const isOpen   = item.classList.contains( 'is-open' );
		const open     = forceOpen !== undefined ? forceOpen : ! isOpen;

		item.classList.toggle( 'is-open', open );
		body.hidden = ! open;
		if ( toggle ) toggle.setAttribute( 'aria-expanded', String( open ) );
	}

	document.addEventListener( 'click', function ( e ) {
		const toggle = e.target.closest( '.ctm-item__toggle' );
		if ( toggle ) {
			toggleItem( toggle.closest( '.ctm-item' ) );
		}
	} );

	// Expand / Collapse All
	document.addEventListener( 'click', function ( e ) {
		if ( e.target.id === 'ctm-expand-all' ) {
			document.querySelectorAll( '.ctm-item' ).forEach( function ( item ) {
				toggleItem( item, true );
			} );
		}
		if ( e.target.id === 'ctm-collapse-all' ) {
			document.querySelectorAll( '.ctm-item' ).forEach( function ( item ) {
				toggleItem( item, false );
			} );
		}
	} );

	// -------------------------------------------------------------------------
	// Live-update accordion label when question text changes
	// -------------------------------------------------------------------------

	document.addEventListener( 'input', function ( e ) {
		if ( e.target.classList.contains( 'ctm-q-text-input' ) ) {
			const item  = e.target.closest( '.ctm-item' );
			const label = item && item.querySelector( '.ctm-item__label' );
			if ( label ) {
				label.textContent = e.target.value || 'New Question';
			}
		}
		if ( e.target.classList.contains( 'ctm-t-name-input' ) ) {
			const item  = e.target.closest( '.ctm-item' );
			const label = item && item.querySelector( '.ctm-item__label' );
			if ( label ) {
				label.textContent = e.target.value || 'New Team';
			}
		}
	} );

	// Update the accordion header badge when the answer-type radio changes
	document.addEventListener( 'change', function ( e ) {
		if ( ! e.target.classList.contains( 'ctm-type-radio' ) ) return;
		var item  = e.target.closest( '.ctm-item' );
		var badge = item && item.querySelector( '.ctm-type-badge' );
		if ( ! badge ) return;
		var isRadio = e.target.value === 'radio';
		badge.textContent = isRadio ? 'Single' : 'Multi';
		badge.className   = 'ctm-type-badge ctm-type-badge--' + e.target.value;
	} );

	// -------------------------------------------------------------------------
	// Remove question / team
	// -------------------------------------------------------------------------

	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '.ctm-item__remove' );
		if ( ! btn ) return;

		const item = btn.closest( '.ctm-item' );
		const type = item && item.dataset.type;
		const msg  = type === 'team'
			? 'Remove this team? This cannot be undone.'
			: 'Remove this question? This cannot be undone.';

		if ( window.confirm( msg ) ) {
			item.remove();
		}
	} );

	// -------------------------------------------------------------------------
	// Add question
	// -------------------------------------------------------------------------

	var addQuestionBtn = document.getElementById( 'ctm-add-question' );
	if ( addQuestionBtn ) {
		addQuestionBtn.addEventListener( 'click', function () {
			var list = document.getElementById( 'ctm-questions-list' );
			var idx  = parseInt( list.dataset.qCounter || '0', 10 );
			list.dataset.qCounter = idx + 1;

			var item = buildQuestionItem( idx );
			list.appendChild( item );
			toggleItem( item, true );
			item.querySelector( '.ctm-q-text-input' ).focus();
		} );
	}

	function buildQuestionItem( qIdx ) {
		var prefix = 'conttema_questions[' + qIdx + ']';
		var id     = 'q_new_' + qIdx;

		var el = document.createElement( 'div' );
		el.className = 'ctm-item';
		el.setAttribute( 'data-type', 'question' );
		el.innerHTML =
			'<div class="ctm-item__header">' +
				'<button type="button" class="ctm-item__toggle" aria-expanded="false">' +
					'<span class="ctm-item__icon dashicons dashicons-arrow-right-alt2"></span>' +
					'<span class="ctm-item__label">New Question</span>' +
					'<span class="ctm-type-badge ctm-type-badge--checkbox">Multi</span>' +
				'</button>' +
				'<button type="button" class="ctm-item__remove button-link-delete">Remove</button>' +
			'</div>' +
			'<div class="ctm-item__body" hidden>' +
				'<input type="hidden" name="' + esc( prefix ) + '[id]" value="' + esc( id ) + '">' +
				'<table class="form-table ctm-form-table">' +
					'<tr>' +
						'<th><label>Question Text</label></th>' +
						'<td><input type="text" name="' + esc( prefix ) + '[text]" value="" class="large-text ctm-q-text-input" required placeholder="Enter the question…"></td>' +
					'</tr>' +
					'<tr>' +
						'<th>Answer Type</th>' +
						'<td>' +
							'<div class="ctm-type-toggle" role="group" aria-label="Answer type">' +
								'<input type="radio" name="' + esc( prefix ) + '[type]" value="radio" id="ctm-q-type-r-' + qIdx + '" class="ctm-type-radio">' +
								'<label for="ctm-q-type-r-' + qIdx + '" class="ctm-type-btn">' +
									'<span class="dashicons dashicons-marker" aria-hidden="true"></span>' +
									'Single answer (radio)' +
								'</label>' +
								'<input type="radio" name="' + esc( prefix ) + '[type]" value="checkbox" id="ctm-q-type-c-' + qIdx + '" class="ctm-type-radio" checked>' +
								'<label for="ctm-q-type-c-' + qIdx + '" class="ctm-type-btn">' +
									'<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>' +
									'Multiple answers (checkbox, up to 3)' +
								'</label>' +
							'</div>' +
							'<p class="description">Single: contributors pick exactly one answer. Multiple: they can pick up to 3.</p>' +
						'</td>' +
					'</tr>' +
				'</table>' +
				'<div class="ctm-answers-header">' +
					'<strong>Answers</strong>' +
					'<span class="ctm-answers-hint">Tags are comma-separated keywords used to match contributors to teams.</span>' +
				'</div>' +
				'<div class="ctm-answers-col-headers">' +
					'<span class="col-text">Answer text</span>' +
					'<span class="col-tags">Tags (comma-separated)</span>' +
					'<span class="col-remove"></span>' +
				'</div>' +
				'<div class="ctm-answers-list" data-a-counter="0"></div>' +
				'<button type="button" class="ctm-add-answer button button-secondary">+ Add Answer</button>' +
			'</div>';

		return el;
	}

	// -------------------------------------------------------------------------
	// Add / remove answer
	// -------------------------------------------------------------------------

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.ctm-add-answer' );
		if ( ! btn ) return;

		var body       = btn.closest( '.ctm-item__body' );
		var list       = body.querySelector( '.ctm-answers-list' );
		var item       = btn.closest( '.ctm-item' );
		var qIdx       = getItemIndex( item, '#ctm-questions-list .ctm-item' );
		var aIdx       = parseInt( list.dataset.aCounter || '0', 10 );
		list.dataset.aCounter = aIdx + 1;

		var prefix = 'conttema_questions[' + qIdx + '][answers][' + aIdx + ']';
		var row    = buildAnswerRow( prefix, 'a_new_' + qIdx + '_' + aIdx );
		list.appendChild( row );
		row.querySelector( '.ctm-input-answer-text' ).focus();
	} );

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.ctm-remove-answer' );
		if ( ! btn ) return;
		btn.closest( '.ctm-answer-row' ).remove();
	} );

	function buildAnswerRow( prefix, id ) {
		var row = document.createElement( 'div' );
		row.className = 'ctm-answer-row';
		row.innerHTML =
			'<input type="hidden" name="' + esc( prefix ) + '[id]" value="' + esc( id ) + '">' +
			'<div class="ctm-answer-row__fields">' +
				'<input type="text" name="' + esc( prefix ) + '[text]" value="" class="ctm-input-answer-text" placeholder="Answer text…" required>' +
				'<input type="text" name="' + esc( prefix ) + '[tags]" value="" class="ctm-input-answer-tags" placeholder="tag1, tag2, tag3">' +
				'<button type="button" class="ctm-remove-answer button-link-delete" aria-label="Remove answer">×</button>' +
			'</div>';
		return row;
	}

	// -------------------------------------------------------------------------
	// Add team
	// -------------------------------------------------------------------------

	var addTeamBtn = document.getElementById( 'ctm-add-team' );
	if ( addTeamBtn ) {
		addTeamBtn.addEventListener( 'click', function () {
			var list = document.getElementById( 'ctm-teams-list' );
			var idx  = parseInt( list.dataset.tCounter || '0', 10 );
			list.dataset.tCounter = idx + 1;

			var item = buildTeamItem( idx );
			list.appendChild( item );
			toggleItem( item, true );
			item.querySelector( '.ctm-t-name-input' ).focus();
		} );
	}

	function buildTeamItem( tIdx ) {
		var prefix = 'conttema_teams[' + tIdx + ']';
		var id     = 'team_new_' + tIdx;

		var el = document.createElement( 'div' );
		el.className = 'ctm-item';
		el.setAttribute( 'data-type', 'team' );
		el.innerHTML =
			'<div class="ctm-item__header">' +
				'<button type="button" class="ctm-item__toggle" aria-expanded="false">' +
					'<span class="ctm-item__icon dashicons dashicons-arrow-right-alt2"></span>' +
					'<span class="ctm-item__label">New Team</span>' +
				'</button>' +
				'<button type="button" class="ctm-item__remove button-link-delete">Remove</button>' +
			'</div>' +
			'<div class="ctm-item__body" hidden>' +
				'<input type="hidden" name="' + esc( prefix ) + '[id]" value="' + esc( id ) + '">' +
				'<table class="form-table ctm-form-table">' +
					'<tr>' +
						'<th><label>Team Name</label></th>' +
						'<td><input type="text" name="' + esc( prefix ) + '[name]" value="" class="regular-text ctm-t-name-input" required placeholder="e.g. Core"></td>' +
					'</tr>' +
					'<tr>' +
						'<th><label>Icon (emoji)</label></th>' +
						'<td><input type="text" name="' + esc( prefix ) + '[icon]" value="" class="ctm-icon-input" placeholder="⚙️"></td>' +
					'</tr>' +
					'<tr>' +
						'<th><label>Team URL</label></th>' +
						'<td><input type="url" name="' + esc( prefix ) + '[url]" value="" class="large-text" placeholder="https://make.wordpress.org/…"></td>' +
					'</tr>' +
					'<tr>' +
						'<th><label>Description</label></th>' +
						'<td><textarea name="' + esc( prefix ) + '[description]" class="large-text" rows="3" placeholder="Short description of the team…"></textarea></td>' +
					'</tr>' +
				'</table>' +
				'<div class="ctm-tags-header">' +
					'<strong>Tag Weights</strong>' +
					'<span class="ctm-answers-hint">Higher weight (1–10) = stronger match for that tag.</span>' +
				'</div>' +
				'<div class="ctm-tags-list" data-tag-counter="0"></div>' +
				'<button type="button" class="ctm-add-tag button button-secondary">+ Add Tag Weight</button>' +
			'</div>';

		return el;
	}

	// -------------------------------------------------------------------------
	// Add / remove tag weight
	// -------------------------------------------------------------------------

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.ctm-add-tag' );
		if ( ! btn ) return;

		var body   = btn.closest( '.ctm-item__body' );
		var list   = body.querySelector( '.ctm-tags-list' );
		var item   = btn.closest( '.ctm-item' );
		var tIdx   = getItemIndex( item, '#ctm-teams-list .ctm-item' );
		var tagIdx = parseInt( list.dataset.tagCounter || '0', 10 );
		list.dataset.tagCounter = tagIdx + 1;

		var prefix = 'conttema_teams[' + tIdx + '][tags][' + tagIdx + ']';
		var row    = buildTagRow( prefix );
		list.appendChild( row );
		row.querySelector( '.ctm-input-tag-name' ).focus();
	} );

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.ctm-remove-tag' );
		if ( ! btn ) return;
		btn.closest( '.ctm-tag-row' ).remove();
	} );

	function buildTagRow( prefix ) {
		var row = document.createElement( 'div' );
		row.className = 'ctm-tag-row';
		row.innerHTML =
			'<input type="text" name="' + esc( prefix ) + '[tag]" value="" class="ctm-input-tag-name" placeholder="tagname">' +
			'<input type="number" name="' + esc( prefix ) + '[weight]" value="3" class="ctm-input-tag-weight" min="1" max="10">' +
			'<button type="button" class="ctm-remove-tag button-link-delete" aria-label="Remove tag">×</button>';
		return row;
	}

	// -------------------------------------------------------------------------
	// Reset to defaults (submit hidden form after confirm)
	// -------------------------------------------------------------------------

	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest( '.ctm-reset-link' );
		if ( ! link ) return;
		e.preventDefault();

		var which = link.dataset.form;
		if ( ! window.confirm( 'Reset all ' + which + ' to their built-in defaults? Your customisations will be lost.' ) ) {
			return;
		}
		var form = document.getElementById( 'ctm-reset-' + which + '-form' );
		if ( form ) form.submit();
	} );

	// -------------------------------------------------------------------------
	// Copy shortcode button
	// -------------------------------------------------------------------------

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.ctm-copy-btn' );
		if ( ! btn ) return;
		var text = btn.dataset.clipboardText;
		if ( ! text ) return;

		navigator.clipboard.writeText( text ).then( function () {
			var orig = btn.textContent;
			btn.textContent = 'Copied!';
			btn.classList.add( 'copied' );
			setTimeout( function () {
				btn.textContent = orig;
				btn.classList.remove( 'copied' );
			}, 2000 );
		} ).catch( function () {
			window.prompt( 'Copy this shortcode:', text );
		} );
	} );

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Get the position index of an item within a selector-matched set.
	 * Used to determine correct field name indices for newly-added nested items.
	 */
	function getItemIndex( item, listSelector ) {
		var all = Array.from( document.querySelectorAll( listSelector ) );
		return all.indexOf( item );
	}

	function esc( str ) {
		if ( typeof str !== 'string' ) return '';
		return str
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

} )();
