/**
 * Mentor Report Card - triage groups, counts, search.
 *
 * Progressive enhancement over what has already been rendered. Every value used here is
 * either in the DOM already or was handed over in `wpcpmTriage`; nothing is fetched, nothing
 * is written, and no student appears who was not on the page to begin with. With this file
 * blocked the page is still the list, styled: each student is a native <details> that opens
 * on its own, and Expand all and printing still work.
 *
 * **This lived in wpcredits-theme until plugin 1.64.0.** It was the one piece of the Mentor
 * Report Card that a theme carried, which meant a theme switch took the triage and the search
 * with it. It reads only the DOM and its own localized data, so it had no theme dependencies
 * to unpick - it simply belonged here.
 */
( function () {
	'use strict';

	var data = window.wpcpmTriage;

	if ( ! data || ! data.students ) {
		return;
	}

	var STUDENTS = data.students;
	var GROUPS = data.groups || [];
	var TEXT = data.i18n || {};
	var ICONS = data.icons || {};
	var ENDING_SOON = ( data.windows && data.windows.endingSoon ) || 60;
	var STALE_NOTE = ( data.windows && data.windows.staleNote ) || 30;
	var DAY = 86400000;

	document.addEventListener( 'DOMContentLoaded', function () {
		var root = document.querySelector( '.wpcpm-dashboard' );

		// The notice variants - logged out, wrong role - have no list to group.
		if ( ! root || root.classList.contains( 'wpcpm-dashboard--notice' ) ) {
			return;
		}

		// The plugin splits students into "Currently mentoring" and "Past students",
		// each with its own `.wpcpm-mentees` list. Only the current one is triaged:
		// a student who has graduated is not waiting on a call. Taking the first
		// list found would grab the past one whenever a mentor has no current
		// students, and file every graduate under "need a call".
		var list = root.querySelector( '.wpcpm-group:not(.wpcpm-group--past) .wpcpm-mentees' );

		// Plugin versions before that split render one ungrouped list.
		if ( ! list && ! root.querySelector( '.wpcpm-group' ) ) {
			list = root.querySelector( '.wpcpm-mentees' );
		}

		// A mentor with no current students gets a paragraph where the list would be,
		// so there is nothing to triage. The band and the toolbar are still built:
		// they are the page's furniture, not the list's, and a card that loses its
		// header and search box when a list happens to be empty reads as broken
		// rather than as empty. It also keeps "Last updated…" inside the band, where
		// it is inset with everything else.
		var rows = list ? collect( root, list ) : [];

		// A stale copy of this script in a theme would otherwise build a second band and a
		// second search box over the first. The class the enhancement already sets is the
		// cheapest thing to test for.
		if ( root.classList.contains( 'wpc-dash-enhanced' ) ) {
			return;
		}

		root.classList.add( 'wpc-dash-enhanced' );

		rows.forEach( decorate );

		// Past students are still rows: they get the same columns, the same report-form
		// button up in the bar, and the search runs over them. They are only kept out of
		// the triage groups and their counts - someone who has finished is not a chore to
		// work through.
		var past = collectPast( root, list );

		past.rows.forEach( decorate );

		var counts = rows.length ? groupInto( list, rows ) : {};
		var band = buildBand( root, counts );
		var view = buildToolbar( root, list, rows.length + past.rows.length );

		wire( root, rows, past, band, view );

		if ( rows.length ) {
			note( list );
		}
	} );

	/**
	 * Read one row per student card, pairing it with its data.
	 *
	 * @param {Element} root Dashboard root.
	 * @param {Element} list The list of student cards.
	 * @return {Object[]} One entry per card, in DOM order.
	 */
	function collect( root, list ) {
		var byId = {};

		Object.keys( STUDENTS ).forEach( function ( record ) {
			byId[ 'wpcpm-student-' + record ] = record;
		} );

		return Array.prototype.slice
			.call( list.querySelectorAll( '.wpcpm-mentee' ) )
			.map( function ( el ) {
				var record = byId[ el.id ] || el.id.replace( /^wpcpm-student-/, '' );
				var info = STUDENTS[ record ] || {};
				var last = lastNoteAge( el );
				var notes = typeof info.notes === 'number' ? info.notes : countNotes( el );

				var row = {
					el: el,
					record: record,
					info: info,
					notes: notes,
					lastNoteDays: last,
					daysLeft: daysUntil( info.end ),
				};

				row.group = classify( row );

				return row;
			} );
	}

	/**
	 * The past students' section, as rows plus the parts a search has to move.
	 *
	 * @param {Element}  root Dashboard root.
	 * @param {?Element} list The current students' list, to be sure of not reading it twice.
	 * @return {Object} Rows and the section's own furniture.
	 */
	function collectPast( root, list ) {
		var section = root.querySelector( '.wpcpm-group--past' );
		var pastList = section ? section.querySelector( '.wpcpm-mentees' ) : null;

		if ( ! pastList || pastList === list ) {
			return { rows: [], section: null };
		}

		var count = section.querySelector( '.wpcpm-group__count' );
		var disclosure = section.querySelector( '.wpcpm-group__disclosure' );

		return {
			rows: collect( root, pastList ),
			section: section,
			disclosure: disclosure,
			count: count,
			// The total to put back once the search is cleared, and whether the section
			// was open to begin with - the plugin opens it when a note was just saved
			// against a past student, and a search must not close it behind them.
			label: count ? count.textContent : '',
			wasOpen: disclosure ? disclosure.open : false,
		};
	}

	/**
	 * Which pile a student belongs in.
	 *
	 * First match wins, in the order the groups were declared: someone who has not
	 * been spoken to is filed under "need a call" even if their internship is also
	 * ending soon, because the call is the thing that has to happen first.
	 *
	 * @param {Object} row Row.
	 * @return {string} Group key.
	 */
	function classify( row ) {
		var stale;

		// Mentoring has finished, so there is no call to schedule and no note
		// overdue. Belt and braces: past students are in a list this script does
		// not read, but a mixed list must not turn graduates into chores.
		if ( row.info && row.info.isPast ) {
			return 'ok';
		}

		if ( 0 === row.notes ) {
			stale = true;
		} else if ( null === row.lastNoteDays ) {
			// Notes exist but their dates are not on the page - a viewer who may not
			// read them, for instance. Unknown is not the same as overdue.
			stale = false;
		} else {
			stale = row.lastNoteDays > STALE_NOTE;
		}

		if ( stale ) {
			return 'call';
		}

		if ( null !== row.daysLeft && row.daysLeft <= ENDING_SOON ) {
			return 'ending';
		}

		return 'ok';
	}

	/**
	 * Whole days between the site's today and a `Y-m-d` date.
	 *
	 * Compared in UTC against the date the server called today, so a mentor in
	 * another timezone sees the same grouping as the program manager.
	 *
	 * @param {string} date End date.
	 * @return {?number} Negative once the date has passed; null when unknown.
	 */
	function daysUntil( date ) {
		if ( ! date || ! data.today ) {
			return null;
		}

		var target = Date.parse( date + 'T00:00:00Z' );
		var today = Date.parse( data.today + 'T00:00:00Z' );

		if ( isNaN( target ) || isNaN( today ) ) {
			return null;
		}

		return Math.round( ( target - today ) / DAY );
	}

	/**
	 * How long ago the most recent note on a card was written.
	 *
	 * Read from the `datetime` attributes the plugin already prints, which is the
	 * one machine-readable date in the rendered card.
	 *
	 * @param {Element} el Student card.
	 * @return {?number} Days, or null when the card carries no dated note.
	 */
	function lastNoteAge( el ) {
		var stamps = Array.prototype.slice
			.call( el.querySelectorAll( '.wpcpm-note__meta time[datetime]' ) )
			.map( function ( time ) {
				return Date.parse( time.getAttribute( 'datetime' ) );
			} )
			.filter( function ( value ) {
				return ! isNaN( value );
			} );

		if ( ! stamps.length ) {
			return null;
		}

		var newest = Math.max.apply( null, stamps );
		var today = Date.parse( data.today + 'T00:00:00Z' );

		if ( isNaN( today ) ) {
			return null;
		}

		return Math.max( 0, Math.round( ( today - newest ) / DAY ) );
	}

	/**
	 * Notes on a card, when the payload did not say.
	 *
	 * @param {Element} el Student card.
	 * @return {number}
	 */
	function countNotes( el ) {
		return el.querySelectorAll( '.wpcpm-notes__list .wpcpm-note' ).length;
	}

	/**
	 * Give one card its columns.
	 *
	 * The plugin renders the institution, the end date and the note count as one
	 * run-on line, which is right for a list of six and unreadable at sixty. The
	 * same three facts are split into columns here and the original line is hidden
	 * by the stylesheet - nothing is added and nothing is dropped.
	 *
	 * @param {Object} row Row.
	 */
	function decorate( row ) {
		var identity = row.el.querySelector( '.wpcpm-mentee__identity' );

		row.el.setAttribute( 'data-wpc-group', row.group );

		if ( row.info.search ) {
			row.el.setAttribute( 'data-wpc-search', row.info.search );
		}

		if ( ! identity ) {
			return;
		}

		row.where = span( 'wpc-row__where', where( row.info ) );
		identity.appendChild( row.where );

		identity.appendChild(
			span(
				'wpc-row__until',
				row.info.endLabel ? format( TEXT.until, row.info.endLabel ) : ''
			)
		);

		identity.appendChild( flag( row ) );

		var action = actionFor( row );

		if ( action ) {
			identity.appendChild( action );
		}
	}

	/**
	 * "Institution · Team", with whichever half exists.
	 *
	 * @param {Object} info Student data.
	 * @return {string}
	 */
	function where( info ) {
		return [ info.institution, info.team ]
			.filter( function ( value ) {
				return value;
			} )
			.join( ' · ' );
	}

	/**
	 * The status chip: how overdue a call is, or how little time is left.
	 *
	 * @param {Object} row Row.
	 * @return {Element}
	 */
	function flag( row ) {
		var el = span( 'wpc-row__flag', '' );

		if ( 'call' === row.group ) {
			el.classList.add( 'is-overdue' );
			el.textContent = 0 === row.notes ? TEXT.noNotes : relativeNote( row );

			return el;
		}

		if ( 'ending' === row.group ) {
			el.classList.add( 'is-ending' );
			el.textContent = row.daysLeft < 0
				? TEXT.endedAlready
				: format( TEXT.daysLeft, String( row.daysLeft ) );

			return el;
		}

		el.classList.add( 'is-quiet' );
		el.textContent = relativeNote( row );

		return el;
	}

	/**
	 * The most recent note's date, short.
	 *
	 * Taken from the `datetime` attribute rather than the printed text, because
	 * the plugin prints the site's full date *and* time - "June 12, 2026 10:34 am"
	 * - which in an 88px column wraps to two lines and makes every row a different
	 * height. The full stamp is still on the note itself, one click away.
	 *
	 * @param {Object} row Row.
	 * @return {string}
	 */
	function relativeNote( row ) {
		var time = row.el.querySelector( '.wpcpm-note__meta time[datetime]' );

		if ( ! time ) {
			return '';
		}

		var when = new Date( time.getAttribute( 'datetime' ) );

		if ( isNaN( when.getTime() ) ) {
			return time.textContent.trim();
		}

		try {
			return when.toLocaleDateString( document.documentElement.lang || undefined, {
				day: 'numeric',
				month: 'short',
			} );
		} catch ( error ) {
			return time.textContent.trim();
		}
	}

	/**
	 * The row's right-hand action.
	 *
	 * **The report form link is left where the plugin puts it.** It used to be hoisted out of
	 * the card body into the row, back when it opened the student's Airtable form - one link,
	 * reachable without opening the card. Since plugin 1.55.0 it opens that student's report
	 * *on this page*, at the foot of their own card, which is where the answer appears; a
	 * control in the row that scrolls you into the card below it is a worse version of the
	 * disclosure triangle already there.
	 *
	 * So the only row action left is the one for a student who needs a call: it opens the card
	 * and puts the cursor in the note field - the thing the mentor came to do.
	 *
	 * Rows nobody has to act on get nothing: the disclosure triangle already says the row
	 * opens, and a second control that does the same is noise.
	 *
	 * @param {Object} row Row.
	 * @return {?Element}
	 */
	function actionFor( row ) {
		var field = row.el.querySelector( '.wpcpm-notes__input' );

		if ( ! field || 'call' !== row.group ) {
			return null;
		}

		var button = document.createElement( 'button' );

		button.type = 'button';
		button.className = 'wpc-row__action wpc-row__action--note';
		button.textContent = TEXT.addNote;

		if ( row.info.name ) {
			button.setAttribute( 'aria-label', format( TEXT.noteFor, row.info.name ) );
		}

		button.addEventListener( 'click', function ( event ) {
			stop( event );
			open( row.el );
			field.focus();
		} );

		return button;
	}

	/**
	 * Sort the cards into their groups and put a header above each.
	 *
	 * Within a group the soonest internship end comes first, which is the order a
	 * mentor works through a list in.
	 *
	 * @param {Element}  list The list.
	 * @param {Object[]} rows Rows.
	 * @return {Object} Group key => count.
	 */
	function groupInto( list, rows ) {
		var counts = {};
		var fragment = document.createDocumentFragment();

		GROUPS.forEach( function ( group ) {
			var members = rows
				.filter( function ( row ) {
					return row.group === group.key;
				} )
				.sort( byEndDate );

			counts[ group.key ] = members.length;

			if ( ! members.length ) {
				return;
			}

			var header = document.createElement( 'div' );

			header.className = 'wpc-group wpc-group--' + group.key;
			header.setAttribute( 'data-wpc-group', group.key );
			header.appendChild( span( 'wpc-group__label', group.label ) );
			header.appendChild( span( 'wpc-group__count', String( members.length ) ) );

			fragment.appendChild( header );

			members.forEach( function ( row ) {
				row.header = header;
				fragment.appendChild( row.el );
			} );
		} );

		list.appendChild( fragment );

		return counts;
	}

	/**
	 * Soonest end date first; unknown dates last, then by name.
	 *
	 * @param {Object} a First row.
	 * @param {Object} b Second row.
	 * @return {number}
	 */
	function byEndDate( a, b ) {
		if ( null === a.daysLeft && null === b.daysLeft ) {
			return ( a.info.name || '' ).localeCompare( b.info.name || '' );
		}

		if ( null === a.daysLeft ) {
			return 1;
		}

		if ( null === b.daysLeft ) {
			return -1;
		}

		return a.daysLeft - b.daysLeft;
	}

	/**
	 * Gather the page title, the plugin's mentor header and the triage counts into
	 * one band, and fold "Last updated…" into the identity line where it belongs.
	 *
	 * @param {Element} root   Dashboard root.
	 * @param {Object}  counts Group key => count.
	 * @return {Element} The triage strip.
	 */
	function buildBand( root, counts ) {
		var header = root.querySelector( '.wpcpm-dashboard__mentor' );
		var band = document.createElement( 'div' );

		band.className = 'wpc-dash__band';

		if ( header ) {
			header.parentNode.insertBefore( band, header );
			band.appendChild( header );
		} else {
			root.insertBefore( band, root.firstChild );
		}

		var identity = root.querySelector( '.wpcpm-dashboard__mentor-identity' );

		// The page title is *not* moved in here, though it once was. It sits above the card
		// as "Mentor Report Card", matching the student page, and the mentor's name is the
		// identity line beneath it - two different things, and folding one into the other
		// made the page's own title read as a label on the mentor.
		var updated = root.querySelector( '.wpcpm-dashboard__updated' );

		if ( identity && updated ) {
			identity.appendChild( updated );
		}

		var triage = document.createElement( 'div' );

		triage.className = 'wpc-triage';

		GROUPS.forEach( function ( group ) {
			var count = counts[ group.key ] || 0;

			if ( ! count ) {
				return;
			}

			var button = document.createElement( 'button' );

			button.type = 'button';
			button.className = 'wpc-triage__item wpc-triage__item--' + group.key;
			button.setAttribute( 'data-wpc-filter', group.key );
			button.setAttribute( 'aria-pressed', 'false' );
			button.appendChild( span( 'wpc-triage__count', String( count ) ) );
			button.appendChild( span( 'wpc-triage__label', triageLabel( group.key ) ) );

			triage.appendChild( button );
		} );

		band.appendChild( triage );

		return triage;
	}

	/**
	 * The lower-case wording under a triage count.
	 *
	 * @param {string} key Group key.
	 * @return {string}
	 */
	function triageLabel( key ) {
		if ( 'call' === key ) {
			return TEXT.needCall;
		}

		if ( 'ending' === key ) {
			return TEXT.endingSoon;
		}

		return TEXT.onTrack;
	}

	/**
	 * The toolbar: a search field, a live match count, and the plugin's own bulk
	 * buttons moved up beside them.
	 *
	 * @param {Element} root  Dashboard root.
	 * @param {Element} list  The list.
	 * @param {number}  total How many students the search can reach, past ones included.
	 * @return {Object} The toolbar's parts.
	 */
	function buildToolbar( root, list, total ) {
		var bar = document.createElement( 'div' );

		bar.className = 'wpc-dash__toolbar';

		var field = document.createElement( 'div' );

		field.className = 'wpc-search';
		field.innerHTML = ICONS.search || '';

		var input = document.createElement( 'input' );

		input.type = 'search';
		input.className = 'wpc-search__input';
		input.id = 'wpc-student-search';
		input.placeholder = TEXT.searchHint || '';
		input.setAttribute( 'aria-label', TEXT.searchLabel || '' );
		input.autocomplete = 'off';

		var clear = document.createElement( 'button' );

		clear.type = 'button';
		clear.className = 'wpc-search__clear';
		clear.innerHTML = ICONS.close || '';
		clear.setAttribute( 'aria-label', TEXT.clearSearch || '' );
		clear.hidden = true;

		field.appendChild( input );
		field.appendChild( clear );
		bar.appendChild( field );

		var status = document.createElement( 'p' );

		status.className = 'wpc-dash__count';
		status.setAttribute( 'role', 'status' );
		bar.appendChild( status );

		var bulk = root.querySelector( '[data-wpcpm-bulk]' );

		if ( bulk ) {
			bar.appendChild( bulk );
		}

		// Above the group headings, directly under the band - not inside the current
		// group, which is where anchoring on the list itself would put it. That also
		// means the toolbar has somewhere to go when there is no current list at all.
		var anchor = root.querySelector( '.wpcpm-group' )
			|| list
			|| root.querySelector( '.wpcpm-dashboard__empty' );

		if ( anchor && anchor.parentNode ) {
			anchor.parentNode.insertBefore( bar, anchor );
		} else {
			root.appendChild( bar );
		}

		var hint = document.createElement( 'p' );

		hint.className = 'wpc-dash__hint';
		hint.textContent = TEXT.collapsedHint || '';
		hint.hidden = true;
		bar.parentNode.insertBefore( hint, bar.nextSibling );

		return {
			input: input,
			clear: clear,
			status: status,
			hint: hint,
			total: total,
		};
	}

	/**
	 * Hook up the triage filters and the search box.
	 *
	 * @param {Element}  root Dashboard root.
	 * @param {Object[]} rows Rows.
	 * @param {Object}   past The past students' section.
	 * @param {Element}  band The triage strip.
	 * @param {Object}   view Toolbar parts.
	 */
	function wire( root, rows, past, band, view ) {
		var state = { filter: '', query: '' };

		band.addEventListener( 'click', function ( event ) {
			var button = event.target.closest
				? event.target.closest( '.wpc-triage__item' )
				: null;

			if ( ! button ) {
				return;
			}

			var key = button.getAttribute( 'data-wpc-filter' );

			// Clicking the active count clears it, so the strip is a toggle rather
			// than a trap with no way back to the whole list.
			state.filter = state.filter === key ? '' : key;

			Array.prototype.forEach.call( band.children, function ( item ) {
				var pressed = item.getAttribute( 'data-wpc-filter' ) === state.filter;

				item.setAttribute( 'aria-pressed', pressed ? 'true' : 'false' );
				item.classList.toggle( 'is-active', pressed );
			} );

			apply( root, rows, past, view, state );
		} );

		var timer = null;

		view.input.addEventListener( 'input', function () {
			window.clearTimeout( timer );

			timer = window.setTimeout( function () {
				state.query = view.input.value.trim().toLowerCase();
				view.clear.hidden = '' === state.query;
				apply( root, rows, past, view, state );
			}, 120 );
		} );

		view.clear.addEventListener( 'click', function () {
			view.input.value = '';
			state.query = '';
			view.clear.hidden = true;
			apply( root, rows, past, view, state );
			view.input.focus();
		} );

		// Escape clears the search from inside the field, which is where anyone
		// who has just mistyped a name already is.
		view.input.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && '' !== view.input.value ) {
				event.preventDefault();
				view.clear.click();
			}
		} );
	}

	/**
	 * Show the rows that match the current filter and search, and retitle the
	 * group headers with how many of each survived.
	 *
	 * @param {Element}  root  Dashboard root.
	 * @param {Object[]} rows  Rows.
	 * @param {Object}   past  The past students' section.
	 * @param {Object}   view  Toolbar parts.
	 * @param {Object}   state Current filter and query.
	 */
	function apply( root, rows, past, view, state ) {
		var matched = {};
		var total = 0;

		rows.forEach( function ( row ) {
			var visible = ( '' === state.filter || row.group === state.filter )
				&& matches( row, state.query );

			row.el.hidden = ! visible;

			if ( visible ) {
				matched[ row.group ] = ( matched[ row.group ] || 0 ) + 1;
				total += 1;
			}

			highlight( row, state.query );
		} );

		GROUPS.forEach( function ( group ) {
			var header = headerFor( rows, group.key );

			if ( ! header ) {
				return;
			}

			var count = matched[ group.key ] || 0;

			header.hidden = 0 === count;

			var label = header.querySelector( '.wpc-group__count' );

			if ( ! label ) {
				return;
			}

			label.textContent = '' === state.query
				? String( count )
				: format( TEXT.groupMatches, String( count ) );
		} );

		total += applyPast( past, state );

		root.classList.toggle( 'is-filtered', '' !== state.filter || '' !== state.query );

		view.hint.hidden = '' === state.query;

		if ( '' === state.query ) {
			view.status.textContent = '';

			return;
		}

		view.status.textContent = 0 === total
			? TEXT.noMatches
			: format( TEXT.matchCount, String( total ), String( view.total ) );
	}

	/**
	 * Run the same search over the past students, and open their section on a hit.
	 *
	 * A match hidden inside a closed disclosure reads as no match at all, which is what
	 * the count beside the search box used to say: searching for someone a mentor
	 * finished with last term answered "No students match that search."
	 *
	 * The triage filters are a different matter - "Need a call" and "Ending soon" name
	 * states only a current student can be in - so the section drops out of a filtered
	 * view entirely rather than sitting under it unfiltered.
	 *
	 * @param {Object} past  The past students' section.
	 * @param {Object} state Current filter and query.
	 * @return {number} How many matched.
	 */
	function applyPast( past, state ) {
		if ( ! past.section ) {
			return 0;
		}

		var matched = 0;

		past.rows.forEach( function ( row ) {
			var visible = '' === state.filter && matches( row, state.query );

			row.el.hidden = ! visible;

			if ( visible ) {
				matched += 1;
			}

			highlight( row, state.query );
		} );

		past.section.hidden = 0 === matched;

		if ( past.count ) {
			past.count.textContent = '' === state.query
				? past.label
				: format( TEXT.groupMatches, String( matched ) );
		}

		// Put back the way it was found once the search is cleared, rather than left
		// standing open over a list nobody asked to see.
		if ( past.disclosure ) {
			past.disclosure.open = '' === state.query ? past.wasOpen : matched > 0;
		}

		return matched;
	}

	/**
	 * Whether a row matches the query.
	 *
	 * Falls back to the card's own text when the payload carried no haystack, so a
	 * student the theme knows nothing about is still findable.
	 *
	 * @param {Object} row   Row.
	 * @param {string} query Lower-case query.
	 * @return {boolean}
	 */
	function matches( row, query ) {
		if ( '' === query ) {
			return true;
		}

		var haystack = row.el.getAttribute( 'data-wpc-search' );

		if ( ! haystack ) {
			haystack = row.el.textContent.toLowerCase();
		}

		return haystack.indexOf( query ) > -1;
	}

	/**
	 * Mark the query inside the institution column.
	 *
	 * Only that column, and only in a span this script created: rewriting the
	 * plugin's own markup to paint a highlight would be a poor trade.
	 *
	 * @param {Object} row   Row.
	 * @param {string} query Lower-case query.
	 */
	function highlight( row, query ) {
		if ( ! row.where ) {
			return;
		}

		var text = where( row.info );

		if ( '' === query ) {
			row.where.textContent = text;

			return;
		}

		var at = text.toLowerCase().indexOf( query );

		if ( at < 0 ) {
			row.where.textContent = text;

			return;
		}

		row.where.textContent = '';
		row.where.appendChild( document.createTextNode( text.slice( 0, at ) ) );

		var mark = document.createElement( 'mark' );

		mark.textContent = text.slice( at, at + query.length );
		row.where.appendChild( mark );
		row.where.appendChild( document.createTextNode( text.slice( at + query.length ) ) );
	}

	/**
	 * The header element for a group.
	 *
	 * @param {Object[]} rows Rows.
	 * @param {string}   key  Group key.
	 * @return {?Element}
	 */
	function headerFor( rows, key ) {
		for ( var i = 0; i < rows.length; i++ ) {
			if ( rows[ i ].group === key && rows[ i ].header ) {
				return rows[ i ].header;
			}
		}

		return null;
	}

	/**
	 * Say how the list is ordered, once, under it.
	 *
	 * @param {Element} list The list.
	 */
	function note( list ) {
		if ( ! TEXT.ordering ) {
			return;
		}

		var el = document.createElement( 'p' );

		el.className = 'wpc-dash__ordering';
		el.textContent = TEXT.ordering;
		list.parentNode.insertBefore( el, list.nextSibling );
	}

	/**
	 * Open a student's disclosure.
	 *
	 * @param {Element} el Student card.
	 */
	function open( el ) {
		var details = el.querySelector( '.wpcpm-mentee__disclosure' );

		if ( details ) {
			details.open = true;
		}
	}

	/**
	 * Keep a click on a control inside <summary> from toggling the card.
	 *
	 * @param {Event} event Click event.
	 */
	function stop( event ) {
		event.stopPropagation();
	}

	/**
	 * A span with a class and some text.
	 *
	 * @param {string} className Class.
	 * @param {string} text      Text content.
	 * @return {Element}
	 */
	function span( className, text ) {
		var el = document.createElement( 'span' );

		el.className = className;
		el.textContent = text || '';

		return el;
	}

	/**
	 * Fill `%s` / `%1$s` placeholders in a translated string.
	 *
	 * @param {string} template Translated string.
	 * @return {string}
	 */
	function format( template ) {
		var args = Array.prototype.slice.call( arguments, 1 );

		if ( ! template ) {
			return args.join( ' ' );
		}

		var index = 0;

		return template.replace( /%(\d+\$)?s/g, function ( match, position ) {
			if ( position ) {
				return args[ parseInt( position, 10 ) - 1 ];
			}

			return args[ index++ ];
		} );
	}
}() );
