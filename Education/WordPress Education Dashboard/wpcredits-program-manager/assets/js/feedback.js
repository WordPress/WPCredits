/**
 * Feedback surveys — the follow-up questions that only apply to some answers.
 *
 * Two questions are worth asking only when the answer above them was poor: what slowed you down,
 * after a low "how easy was it to get started", and what is making the hours hard to reach, after
 * an unsure or a no. Asked of everybody they come back mostly blank.
 *
 * **They are visible until this runs, not hidden until this runs.** A conditional question hidden
 * by CSS would be missing outright for anyone whose JavaScript did not load, and a question nobody
 * can see is worse than one shown to somebody it does not apply to — who simply leaves it empty.
 *
 * The rules live in the markup, as `data-wpcpm-when` on the field. PHP owns which answers count as
 * poor; this file only reads what it is told, so a rule changed in one place cannot go out of step
 * with a copy of it here.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var fields = Array.prototype.slice.call( document.querySelectorAll( '[data-wpcpm-when]' ) );

		if ( ! fields.length ) {
			return;
		}

		/**
		 * What has been answered for one field key, within one form.
		 *
		 * @param {Element} form The form or read-only container.
		 * @param {string}  key  Field key.
		 * @return {string} The answer, or an empty string.
		 */
		function answer( form, key ) {
			var checked = form.querySelector( 'input[name="feedback[' + key + ']"]:checked' );

			if ( checked ) {
				return checked.value;
			}

			var select = form.querySelector( '[data-wpcpm-field="' + key + '"]' );

			// The rating fieldset also carries `data-wpcpm-field`, so a scale with nothing chosen
			// lands here rather than on the branch above; it has no value of its own.
			return ( select && 'undefined' !== typeof select.value ) ? select.value : '';
		}

		/**
		 * Whether a field's rules are satisfied.
		 *
		 * Any rule is enough: "what slowed you down" is asked when getting started was hard *or*
		 * when choosing a project was unclear, and somebody who says both should be asked once.
		 *
		 * @param {Element} form  The form or read-only container.
		 * @param {Array}   rules Parsed rules.
		 * @return {boolean} Whether to show the field.
		 */
		function matches( form, rules ) {
			return rules.some( function ( rule ) {
				return rule.values.indexOf( answer( form, rule.field ) ) !== -1;
			} );
		}

		fields.forEach( function ( field ) {
			var form = field.closest( '.wpcpm-feedback__form' );
			var rules;

			if ( ! form ) {
				return;
			}

			try {
				rules = JSON.parse( field.getAttribute( 'data-wpcpm-when' ) );
			} catch ( e ) {
				// An unreadable rule leaves the question on screen, which is the safe way to fail.
				return;
			}

			if ( ! Array.isArray( rules ) || ! rules.length ) {
				return;
			}

			/**
			 * Show or hide, unless it already has an answer.
			 *
			 * A student who answered the follow-up and then changed the answer above it would
			 * otherwise watch their words disappear — and, since a hidden field still posts,
			 * would have no way to tell that what they wrote is still being sent.
			 */
			function update() {
				var textarea = field.querySelector( 'textarea' );
				var written = textarea && '' !== textarea.value.trim();

				field.hidden = ! written && ! matches( form, rules );
			}

			update();

			// One listener per form rather than per trigger: the answer that decides this is a
			// radio in one case and a select in another, and `change` bubbles from both.
			form.addEventListener( 'change', update );
		} );
	} );
}() );
