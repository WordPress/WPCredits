/*
 * Runs in the harness's page (bin/ui/layout/page.mjs). run.mjs evaluates this file and then calls
 * window.wpcpmMeasure(), which opens every details and answers, per case and width:
 *
 *   { name, w, faults: [ 'OVERFLOW <text> by <px>', 'CELL <text> (<scroll> > <client>)', 'H-SCROLL <px>' ], tables: [ <form> ] }
 *
 * OVERFLOW: an element whose right edge passes the content edge of its nearest .wpcpm-ui-fold__body,
 * .wpcpm-ui-panel or .wpcpm-frame__main by more than 0.5px (only the outermost one of a run is named).
 * CELL: an element whose scrollWidth passes its clientWidth by more than 1; an element that scrolls on
 * purpose (overflow-x auto or scroll) is excepted only when it carries data-wpcpm-scrolls, and a form control
 * (input, textarea, select) is never asked, since its own text scrolls inside its box: it is measured as a box; and
 * what is hidden on purpose (hiddenBox, below) and placed out of the flow inside an element does not widen it: the
 * element is read again without it, unless something inside it is seen (a child that sets visibility visible again). The
 * parts' row link (`.wpcpm-ui-fold--row .wpcpm-btn`: at most 1px each way, overflow hidden, its word kept for assistive
 * technology and its hit area its ::after stretched over the row, which is positioned, while the link itself is static)
 * is not a CELL either (rowLink, below).
 * H-SCROLL: the main area itself scrolls sideways.
 * tables: the form of each table.wpcpm-ui-table: `table`, `one column` or `lines a+b`.
 */
function rect( e ) { return e.getBoundingClientRect(); }
// Hidden on purpose: display none, visibility hidden, the hidden attribute, and the clip pattern the parts use
// (absolutely positioned, overflow hidden, one pixel or less each way; or a clip or clip-path that leaves
// nothing). A box that is merely squeezed to nothing by a bad layout is not hidden on purpose.
function hiddenBox( b ) {
	var cs = getComputedStyle( b );
	if ( 'none' === cs.display || 'hidden' === cs.visibility || b.hasAttribute( 'hidden' ) ) { return true; }
	if ( 'contents' === cs.display ) { return false; }
	var r = rect( b );
	if ( 'absolute' === cs.position && 'visible' !== cs.overflowX && r.width <= 1 && r.height <= 1 ) { return true; }
	if ( 'absolute' === cs.position && /^rect\(\s*0(px)?,\s*0(px)?,\s*0(px)?,\s*0(px)?\)$/.test( cs.clip ) ) { return true; }
	if ( /^inset\(\s*50%/.test( cs.clipPath ) ) { return true; }
	return false;
}
window.wpcpmOpenPanels = function () {
	var n = 0;
	document.querySelectorAll( '.h-case' ).forEach( function ( c ) {
		var hidden = c.querySelectorAll( '.wpcpm-ui-panel[hidden]' );
		hidden.forEach( function ( p ) { p.removeAttribute( 'hidden' ); } );
		if ( hidden.length ) { c.setAttribute( 'data-h-opened', '' ); n++; }
	} );
	return n;
};
window.wpcpmMeasure = function ( onlyOpened, ignore ) {
	// The frame's side menu is never measured; neither is anything the run was told to ignore.
	// An ignored part is taken out of the page (display none), so what it holds widens nothing around it.
	if ( ignore && ignore.length && ! document.getElementById( 'h-ignore' ) ) {
		var st = document.createElement( 'style' );
		st.id = 'h-ignore';
		st.textContent = ignore.join( ', ' ) + '{display:none!important}';
		document.head.appendChild( st );
	}
	var SKIP = [ '.wpcpm-frame__menu' ].concat( ignore || [] ).join( ', ' );
	document.querySelectorAll( 'details' ).forEach( function ( d ) { d.open = true; } );
	var BOX = '.wpcpm-ui-fold__body, .wpcpm-ui-panel, .wpcpm-frame__main';

	function txt( e ) { return ( e.textContent || '' ).replace( /\s+/g, ' ' ).trim().slice( 0, 40 ) || ( '<' + e.tagName.toLowerCase() + ( e.className && typeof e.className === 'string' ? '.' + e.className.split( ' ' )[ 0 ] : '' ) + '>' ); }
	function contentRight( box ) {
		var r = rect( box ), cs = getComputedStyle( box );
		return r.right - parseFloat( cs.paddingRight ) - parseFloat( cs.borderRightWidth );
	}
	function skipped( e ) {
		if ( e.closest( '.screen-reader-text' ) || e.closest( SKIP ) ) { return true; }
		for ( var p = e; p && p.nodeType === 1; p = p.parentElement ) {
			if ( p.classList && p.classList.contains( 'h-case' ) ) { break; }
			if ( 'contents' === getComputedStyle( p ).display && p === e ) { return true; }
			if ( hiddenBox( p ) ) { return true; }
		}
		return false;
	}
	function scrollsOnPurpose( e ) {
		var cs = getComputedStyle( e );
		return /auto|scroll/.test( cs.overflowX );
	}
	// What is hidden on purpose and out of the flow does not widen its cell (20 Nov 2026, the cohort timeline's month
	// tooltips): an element whose scrollWidth passes its clientWidth is read again with every descendant hiddenBox() calls
	// hidden that is absolutely or fixed placed taken out of the layout (display none), and each is put back exactly as it
	// was. A hidden box in the flow holds its room and stays. True when the element still passes.
	function seenInside( d ) {
		return Array.prototype.some.call( d.querySelectorAll( '*' ), function ( x ) { var r = rect( x ); return r.width > 0 && r.height > 0 && ! hiddenBox( x ) && 'hidden' !== getComputedStyle( x ).visibility && ! x.closest( '[hidden]' ) && ! hiddenUnder( x, d ); } );
	}
	// Whether something between a descendant and the wrapper hides it in a way visibility visible cannot undo: display none,
	// the hidden attribute or the clip pattern (visibility hidden is undone by a visible child, so it does not count).
	function hiddenUnder( x, d ) {
		for ( var a = x.parentElement; a && a !== d; a = a.parentElement ) {
			var cs = getComputedStyle( a );
			if ( 'none' === cs.display || a.hasAttribute( 'hidden' ) || ( 'hidden' !== cs.visibility && hiddenBox( a ) ) ) { return true; }
		}
		var w = getComputedStyle( d );
		return 'none' === w.display || d.hasAttribute( 'hidden' ) || ( 'hidden' !== w.visibility && hiddenBox( d ) );
	}
	function widerWithoutHidden( e ) {
		var hid = [];
		// A hidden subtree goes out only when nothing in it is seen: a descendant that sets visibility visible again, with a
		// box, is seen by a person, and its wrapper stays (1.123.104, round 6).
		e.querySelectorAll( '*' ).forEach( function ( d ) { if ( /^(absolute|fixed)$/.test( getComputedStyle( d ).position ) && hiddenBox( d ) && ! seenInside( d ) ) { hid.push( d ); } } );
		if ( ! hid.length ) { return true; }
		var was = hid.map( function ( d ) { return [ d.style.getPropertyValue( 'display' ), d.style.getPropertyPriority( 'display' ) ]; } );
		try {
			hid.forEach( function ( d ) { d.style.setProperty( 'display', 'none', 'important' ); } );
			return e.scrollWidth > e.clientWidth + 1;
		} finally {
			hid.forEach( function ( d, i ) {
				if ( '' === was[ i ][ 0 ] ) { d.style.removeProperty( 'display' ); } else { d.style.setProperty( 'display', was[ i ][ 0 ], was[ i ][ 1 ] ); }
			} );
		}
	}
	// The parts' row link (20 Nov 2026, parts.css `.wpcpm-ui-fold--row .wpcpm-btn` under a main area of 800px): a box at
	// most 1px each way with its overflow hidden, whose word stays there for assistive technology, and whose hit area is
	// its ::after, absolutely placed with content and stretched over the row (inset 0 on all four sides). Not hidden (its
	// hit area is real), so only the CELL check passes it; any other 1px box with its overflow hidden is still a CELL, and
	// so is the row link of a row that is not positioned (its ::after would stretch over a larger ancestor).
	function rowLink( e ) {
		var row = e.closest( '.wpcpm-ui-fold--row' );
		if ( ! e.matches( '.wpcpm-ui-fold--row .wpcpm-btn' ) || ! row || 'static' === getComputedStyle( row ).position ) { return false; }
		var r = rect( e ), cs = getComputedStyle( e ), after = getComputedStyle( e, '::after' );
		var stretched = [ after.top, after.right, after.bottom, after.left ].every( function ( v ) { return '0px' === v; } );
		// The link itself static, so its ::after is laid out against the positioned row and not its own 1px box (1.123.104,
		// round 6).
		return r.width <= 1 && r.height <= 1 && 'hidden' === cs.overflowX && 'static' === cs.position && 'absolute' === after.position && 'none' !== after.content && 'normal' !== after.content && stretched;
	}
	function insideScroller( e, stop ) {
		for ( var p = e.parentElement; p && p !== stop; p = p.parentElement ) {
			if ( p.hasAttribute( 'data-wpcpm-scrolls' ) && scrollsOnPurpose( p ) ) { return true; }
		}
		return false;
	}
	function form( table ) {
		var rows = table.querySelectorAll( 'tbody tr' );
		if ( ! rows.length ) { return 'table'; }
		var cs = getComputedStyle( rows[ 0 ] );
		if ( 'table-row' === cs.display ) { return 'table'; }
		var n = cs.gridTemplateColumns.split( ' ' ).filter( Boolean ).length;
		if ( n === 1 ) { return 'one column'; }
		var tops = {};
		rows[ 0 ].querySelectorAll( 'td' ).forEach( function ( td ) {
			if ( 'none' === getComputedStyle( td ).display ) { return; }
			var k = Math.round( rect( td ).top );
			tops[ k ] = ( tops[ k ] || 0 ) + 1;
		} );
		return 'lines ' + Object.keys( tops ).sort( function ( a, b ) { return a - b; } ).map( function ( k ) { return tops[ k ]; } ).join( '+' );
	}

	var res = [];
	document.querySelectorAll( '.h-case' ).forEach( function ( c ) {
		if ( onlyOpened && ! c.hasAttribute( 'data-h-opened' ) ) { return; }
		var out = { name: c.dataset.name, w: c.dataset.w, faults: [], tables: [] };
		var seen = {};
		function add( kind, el, text ) { var k = kind + '|' + text; if ( seen[ k ] ) { return; } seen[ k ] = true; out.faults.push( text ); }
		var main = c.querySelector( '.wpcpm-frame__main' );
		var reported = new Set();
		// Only the main area's own boxes: the frame and the page's wrappers around it are the harness's drawing of
		// the page, sized by its window, not a case's markup.
		( main || c ).querySelectorAll( '*' ).forEach( function ( el ) {
			if ( el === main || skipped( el ) ) { return; }
			var r = rect( el );
			var box = el.parentElement ? el.parentElement.closest( BOX ) : null;
			if ( box && r.width > 0 && r.height > 0 && ! insideScroller( el, box ) ) {
				var over = r.right - contentRight( box );
				var up = el.parentElement;
				var parentHit = false;
				while ( up && up !== box ) { if ( reported.has( up ) ) { parentHit = true; } up = up.parentElement; }
				if ( over > 0.5 && ! parentHit ) {
					reported.add( el );
					add( 'OVERFLOW', el, 'OVERFLOW ' + txt( el ) + ' by ' + over.toFixed( 1 ) );
				}
			}
			// A form control's own text scrolls inside its box, as a text box does: an input, a text area or a select is
			// measured as a box only (OVERFLOW and H-SCROLL), never by the text it holds (Institution Dashboard, Task 16).
			if ( ! /^(INPUT|TEXTAREA|SELECT)$/.test( el.tagName ) && el.scrollWidth > el.clientWidth + 1 && ! ( scrollsOnPurpose( el ) && el.hasAttribute( 'data-wpcpm-scrolls' ) ) && ! rowLink( el ) && widerWithoutHidden( el ) ) {
				add( 'CELL', el, 'CELL ' + txt( el ) + ' (' + el.scrollWidth + ' > ' + el.clientWidth + ')' );
			}
		} );
		if ( main ) {
			var mcs = getComputedStyle( main );
			var inner = rect( main ).width - parseFloat( mcs.paddingLeft ) - parseFloat( mcs.paddingRight ) - parseFloat( mcs.borderLeftWidth ) - parseFloat( mcs.borderRightWidth );
			if ( Math.abs( inner - parseFloat( c.dataset.w ) ) > 1 ) { out.badWidth = inner; }
		} else { out.badWidth = -1; }
		if ( main && main.scrollWidth > main.clientWidth ) {
			// Only when something that is measured reaches past the main area (not the menu or an ignored part).
			var mr = rect( main ), limit = mr.left + main.clientWidth + 0.5, past = false;
			main.querySelectorAll( '*' ).forEach( function ( x ) {
				if ( past || x === main || skipped( x ) ) { return; }
				var xr = rect( x );
				if ( xr.width > 0 && xr.right > limit ) { past = true; }
			} );
			if ( past ) { out.faults.push( 'H-SCROLL ' + ( main.scrollWidth - main.clientWidth ) ); }
		}
		c.querySelectorAll( 'table.wpcpm-ui-table' ).forEach( function ( t ) { out.tables.push( form( t ) ); } );
		res.push( out );
	} );
	return res;
};

/*
 * The census (Task 27b): window.wpcpmCensus( onlyOpened, ignore ) answers, per case,
 *
 *   { name, w, gaps: [ { c, u, l, gap, a, b } ], edges: [ { c, k, at, words, of } ], zeroEdges: <count> }
 *
 * Containers (CONTAINERS): the parts that stack blocks, as the parts print them: the page's root, a view head's
 * copy, a fold's body, a panel, a block (section or article), the Decide area, the error summary, a form, a
 * fieldset, a choice group's options, an option's text (its words and hint), a field (label, box, hint), an item, an
 * item header's text column, a list row (and a checklist item). Nothing inside the printed report
 * (.wpcpm-report-doc, its own sheet), a table, the head's Viewing as row, the figures band or the frame's menu is a
 * container.
 *
 * Blocks: a container's children in normal flow (not absolute, fixed or floated), with what is hidden on purpose
 * left out by the harness's own rule (hiddenBox, above, and .screen-reader-text); a display:contents child gives
 * its children; a run of text and inline elements is one block, "line of text"; a box with no height is left out.
 *
 * Neighbours: each block is paired with the block of the container drawn nearest above it (its bottom at most 2px
 * under the block's top) that shares part of its column (their left to right spans overlap); blocks side by side in
 * one row have no such pair. A run of text is measured by its line boxes (its glyphs' rectangles grown by the
 * half-leading of the container's line height), to within 0.5px, since Chrome draws the glyphs on a whole pixel. `gap` is the lower block's border box top less the upper one's
 * bottom, in CSS pixels, to 0.1px.
 *
 * Kinds (kindOf): from the parts' classes first, then the tag; a box that is none of them and draws nothing of its
 * own (no border, no padding, no background) is named by the block it shows at that side: its first block as the
 * lower one, its last as the upper one.
 *
 * Edges: each block that starts its row (no sibling beside it at its start side) against the container's content
 * edge (left, or right in a right-to-left case); then every legend's first line against the first label or option
 * of its fieldset; then every label's, legend's and option's wrapped lines against its first line. Only offsets
 * over 0.5px are listed; zeroEdges counts the rest.
 */
window.wpcpmCensus = function ( onlyOpened, ignore ) {
	var SKIP = [ '.wpcpm-frame__menu', '.screen-reader-text' ].concat( ignore || [] ).join( ', ' );
	document.querySelectorAll( 'details' ).forEach( function ( d ) { d.open = true; } );
	var CONTAINERS = [
		[ '.wpcpm-dashboard', 'view root' ],
		[ '.wpcpm-head__copy', 'view head' ],
		[ '.wpcpm-ui-fold__body', 'fold body' ],
		// A run of inner folds as a list (Task 28, W6): its folds are measured against each other as a bare run's are.
		[ '.wpcpm-ui-folds', 'fold list' ],
		[ '.wpcpm-ui-panel', 'panel' ],
		[ '.wpcpm-ui-decide', 'decide area' ],
		[ '.wpcpm-errors', 'error summary' ],
		[ 'fieldset', 'field set' ],
		[ '.wpcpm-ui-choices__options, .wpcpm-ui-choices__grid', 'options' ],
		[ '.wpcpm-ui-choice__text', 'option' ],
		[ '.wpcpm-ui-field', 'field' ],
		[ '.wpcpm-ui-item', 'item' ],
		[ '.wpcpm-ui-item__lead', 'item header' ],
		[ '.wpcpm-ui-list__row, .wpcpm-ui-checklist__item', 'list row' ],
		[ 'form', 'form' ],
		[ 'section, article, .wpcpm-ui-block', 'block' ],
	];
	// The figures band draws its hairlines with a 1px gap over a line-colored grid (dashboard-frame.css): its tiles'
	// 1px apart is the drawing of a line, not a gap.
	var NOT_CONTAINER = '.wpcpm-report-doc, table, .wpcpm-ui-viewing, .wpcpm-frame__menu, .wpcpm-figures';
	var KINDS = [
		[ '.wpcpm-head', 'view head' ], [ '.wpcpm-head__lede', 'lede' ], [ '.wpcpm-head__eyebrow', 'eyebrow' ], [ '.wpcpm-head__title', 'view title' ],
		[ '.wpcpm-head__facts', 'facts line' ], [ '.wpcpm-head__actions', 'action row' ], [ '.wpcpm-ui-head-row', 'head row' ], [ '.wpcpm-ui-viewing', 'viewing as' ],
		[ '.wpcpm-errors', 'error summary' ],
		[ '.wpcpm-ui-fold--inner', 'inner fold' ], [ '.wpcpm-ui-fold-card, .wpcpm-ui-fold', 'card fold' ],
		[ '.wpcpm-ui-decide', 'decide area' ], [ '.wpcpm-ui-panel', 'panel' ],
		[ '.wpcpm-ui-field__hint, .wpcpm-ui-choice__hint, .wpcpm-report-hint', 'hint' ],
		[ '.wpcpm-field__problem', 'field error' ],
		[ '[class*="__message"], p.is-error, p.is-success, p.is-info, .wpcpm-notice', 'message' ],
		[ '.wpcpm-ui-intro--muted, .wpcpm-overview__quiet, .wpcpm-ui-list__note, .wpcpm-ui-list__empty, .wpcpm-dashboard__updated, .wpcpm-roster__read', 'muted line' ],
		[ '.wpcpm-ui-field__label, .wpcpm-ui-viewing__label', 'label' ],
		[ 'legend', 'legend' ],
		[ '.wpcpm-ui-choices--alone', 'tick' ],
		[ '.wpcpm-ui-choices--set', 'field set' ],
		[ '.wpcpm-ui-choices__options, .wpcpm-ui-choices__grid', 'options' ],
		[ '.wpcpm-ui-choices', 'choice group' ],
		[ '.wpcpm-ui-choice', 'option' ],
		[ '.wpcpm-ui-choice__words', 'option words' ],
		[ '.wpcpm-ui-file', 'file box' ],
		[ '.wpcpm-ui-file__row', 'file row' ],
		[ '.wpcpm-ui-field', 'field' ],
		[ '.wpcpm-ui-actions, .wpcpm-ui-list__act', 'action row' ],
		[ '.wpcpm-btn, button, input[type="submit"]', 'button' ],
		[ '.wpcpm-ui-field__control, input, select, textarea', 'box' ],
		[ 'table', 'table' ],
		[ '.wpcpm-ui-list__row, .wpcpm-ui-checklist__item', 'list row' ],
		[ '.wpcpm-ui-list__line', 'list line' ],
		[ '.wpcpm-ui-list__more', 'more line' ],
		[ 'ul, ol', 'list' ],
		[ '.wpcpm-ui-item__head', 'item header' ], [ '.wpcpm-ui-item__title', 'item title' ], [ '.wpcpm-ui-item', 'item' ],
		[ '.wpcpm-ui-item__facts', 'facts line' ],
		[ '.wpcpm-ui-h, h1, h2, h3, h4, h5, h6', 'heading' ],
		[ 'hr', 'hairline' ],
		[ 'blockquote', 'quote' ],
		[ '.wpcpm-ui-doc', 'document' ],
		[ '.wpcpm-figures', 'figures' ], [ '.wpcpm-figure', 'figure' ],
		[ '.wpcpm-ui-intro', 'paragraph' ],
		[ '.wpcpm-ui-note', 'note' ],
		[ 'p', 'paragraph' ],
		[ 'img, svg', 'image' ],
	];
	function words( s ) { return String( s || '' ).replace( /\s+/g, ' ' ).trim().slice( 0, 48 ); }
	function tagOf( e ) { return '<' + e.tagName.toLowerCase() + ( e.classList.length ? '.' + e.classList[ 0 ] : '' ) + '>'; }
	function off( e ) { return !! ( e.closest( SKIP ) || hiddenBox( e ) ); }
	function drawsNothing( e ) {
		var cs = getComputedStyle( e );
		var sides = [ 'Top', 'Right', 'Bottom', 'Left' ];
		for ( var i = 0; i < 4; i++ ) {
			if ( parseFloat( cs[ 'border' + sides[ i ] + 'Width' ] ) > 0 || parseFloat( cs[ 'padding' + sides[ i ] ] ) > 0 ) { return false; }
		}
		return 'none' === cs.backgroundImage && /^(transparent|rgba\(0, 0, 0, 0\))$/.test( cs.backgroundColor );
	}
	// The blocks a container stacks, in its order: { el, nodes, r }, where el is null for a run of text.
	function blocks( C ) {
		var out = [];
		var run = null;
		function flush() {
			if ( ! run ) { return; }
			var range = document.createRange();
			range.setStartBefore( run[ 0 ] );
			range.setEndAfter( run[ run.length - 1 ] );
			var rs = [].slice.call( range.getClientRects() ).filter( function ( x ) { return x.width > 0 && x.height > 0; } );
			if ( rs.length ) {
				var r = { top: Infinity, bottom: -Infinity, left: Infinity, right: -Infinity };
				rs.forEach( function ( x ) { r.top = Math.min( r.top, x.top ); r.bottom = Math.max( r.bottom, x.bottom ); r.left = Math.min( r.left, x.left ); r.right = Math.max( r.right, x.right ); } );
				r.first = rs[ 0 ];
				// A text's rectangles are its glyphs' content area, not its line box: grown by the half-leading of the
				// container's line height on each side, the run is measured by its line boxes, as a paragraph is.
				var ccs = getComputedStyle( C );
				var lh = 'normal' === ccs.lineHeight ? 1.2 * parseFloat( ccs.fontSize ) : parseFloat( ccs.lineHeight );
				var tops = rs.filter( function ( x ) { return x.top === r.top; } )[ 0 ], bots = rs.filter( function ( x ) { return x.bottom === r.bottom; } )[ 0 ];
				r.top -= Math.max( 0, ( lh - tops.height ) / 2 );
				r.bottom += Math.max( 0, ( lh - bots.height ) / 2 );
				var only = run.filter( function ( n ) { return 1 === n.nodeType || n.textContent.trim(); } );
				out.push( { el: 1 === only.length && 1 === only[ 0 ].nodeType ? only[ 0 ] : null, nodes: run, r: r, text: run.map( function ( n ) { return n.textContent; } ).join( '' ) } );
			}
			run = null;
		}
		function walk( P ) {
			[].forEach.call( P.childNodes, function ( n ) {
				if ( 3 === n.nodeType ) { if ( n.textContent.trim() ) { ( run = run || [] ).push( n ); } return; }
				if ( 1 !== n.nodeType || /^(SCRIPT|STYLE|TEMPLATE|BR)$/.test( n.tagName ) || off( n ) ) { return; }
				var cs = getComputedStyle( n );
				if ( 'contents' === cs.display ) { walk( n ); return; }
				if ( /^(absolute|fixed)$/.test( cs.position ) || 'none' !== cs.float ) { return; }
				if ( 'inline' === cs.display ) { ( run = run || [] ).push( n ); return; }
				flush();
				var r = rect( n );
				if ( r.height <= 0 ) { return; }
				out.push( { el: n, r: r } );
			} );
		}
		walk( C );
		flush();
		return out;
	}
	function kindOf( e, side, depth ) {
		for ( var i = 0; i < KINDS.length; i++ ) {
			if ( e.matches( KINDS[ i ][ 0 ] ) ) {
				var k = KINDS[ i ][ 1 ];
				if ( 'heading' === k && e.closest( '.wpcpm-ui-fold__body, .wpcpm-ui-panel, .wpcpm-ui-block' ) ) { k = 'in-card heading'; }
				if ( 'facts line' === k && e.querySelector( '.wpcpm-status' ) ) { k = 'tag line'; }
				if ( 'paragraph' === k && e.querySelector( '.wpcpm-status' ) && ! ( e.textContent || '' ).replace( ( e.querySelector( '.wpcpm-status' ).textContent || '' ), '' ).trim() ) { k = 'tag line'; }
				return { k: k, el: e };
			}
		}
		// A box that draws nothing of its own is named by what it shows at that side.
		if ( ( depth || 0 ) < 6 && drawsNothing( e ) ) {
			var inner = blocks( e );
			if ( inner.length ) {
				var b = inner[ 'upper' === side ? inner.length - 1 : 0 ];
				return b.el ? kindOf( b.el, side, ( depth || 0 ) + 1 ) : { k: 'line of text', el: null, text: b.text };
			}
		}
		return { k: 'block ' + tagOf( e ), el: e };
	}
	function name( b, side ) {
		if ( ! b.el ) { return { k: 'line of text', w: words( b.text ) }; }
		var k = kindOf( b.el, side, 0 );
		return { k: k.k, w: words( k.el ? k.el.textContent : k.text ) || tagOf( b.el ) };
	}
	function contentEdges( C ) {
		var r = rect( C ), cs = getComputedStyle( C );
		return { left: r.left + parseFloat( cs.borderLeftWidth ) + parseFloat( cs.paddingLeft ), right: r.right - parseFloat( cs.borderRightWidth ) - parseFloat( cs.paddingRight ) };
	}
	function lines( el ) {
		var range = document.createRange();
		range.selectNodeContents( el );
		var rs = [].slice.call( range.getClientRects() ).filter( function ( x ) { return x.width > 0 && x.height > 0; } ).sort( function ( a, b ) { return a.top - b.top; } );
		var ls = [];
		rs.forEach( function ( x ) {
			var mid = ( x.top + x.bottom ) / 2;
			var l = ls.filter( function ( y ) { return mid >= y.top && mid <= y.bottom; } )[ 0 ];
			if ( l ) { l.left = Math.min( l.left, x.left ); l.right = Math.max( l.right, x.right ); } else { ls.push( { top: x.top, bottom: x.bottom, left: x.left, right: x.right } ); }
		} );
		return ls;
	}
	var round = function ( x ) { return Math.round( x * 10 ) / 10; };

	var res = [];
	document.querySelectorAll( '.h-case' ).forEach( function ( c ) {
		if ( onlyOpened && ! c.hasAttribute( 'data-h-opened' ) ) { return; }
		var out = { name: c.dataset.name, w: c.dataset.w, gaps: [], edges: [], zeroEdges: 0 };
		var main = c.querySelector( '.wpcpm-frame__main' ) || c;
		function edge( e ) { if ( Math.abs( e.at ) > 0.5 ) { out.edges.push( e ); } else { out.zeroEdges++; } }
		main.querySelectorAll( '*' ).forEach( function ( C ) {
			var ck = null;
			for ( var i = 0; i < CONTAINERS.length; i++ ) { if ( C.matches( CONTAINERS[ i ][ 0 ] ) ) { ck = CONTAINERS[ i ][ 1 ]; break; } }
			if ( ! ck || C.closest( NOT_CONTAINER ) || off( C ) ) { return; }
			for ( var p = C.parentElement; p && p !== main; p = p.parentElement ) { if ( hiddenBox( p ) ) { return; } }
			if ( 'fold body' === ck && C.parentElement && C.parentElement.matches( '.wpcpm-ui-fold--inner' ) ) { ck = 'inner fold body'; }
			var rtl = 'rtl' === getComputedStyle( C ).direction;
			var bs = blocks( C );
			var ce = contentEdges( C );
			bs.forEach( function ( B, j ) {
				// Its upper neighbour: the block drawn nearest above it, in its column (by where it is drawn, not by its
				// place in the markup: a head's Viewing as line comes after the title it follows in the markup).
				var A = null;
				for ( var i = 0; i < bs.length; i++ ) {
					var X = bs[ i ];
					if ( X === B ) { continue; }
					var cols = Math.min( X.r.right, B.r.right ) - Math.max( X.r.left, B.r.left );
					if ( cols > 0 && X.r.bottom <= B.r.top + 2 && ( ! A || X.r.bottom > A.r.bottom ) ) { A = X; }
				}
				if ( A ) {
					var u = name( A, 'upper' ), l = name( B, 'lower' );
					out.gaps.push( { c: ck, u: u.k, l: l.k, gap: round( B.r.top - A.r.bottom ), a: u.w, b: l.w } );
				}
				// Its edge, when it starts its row.
				var beside = bs.some( function ( X ) {
					if ( X === B ) { return false; }
					var rows = Math.min( X.r.bottom, B.r.bottom ) - Math.max( X.r.top, B.r.top );
					return rows > 2 && ( rtl ? X.r.left >= B.r.right - 0.5 : X.r.right <= B.r.left + 0.5 );
				} );
				if ( ! beside ) {
					var at = B.el ? B.r : B.r.first;
					var n = name( B, 'lower' );
					edge( { c: ck, k: n.k, at: round( rtl ? ce.right - at.right : at.left - ce.left ), words: n.w, of: 'content edge' } );
				}
			} );
		} );
		// A legend's first line against its fieldset's first label or option.
		main.querySelectorAll( 'legend' ).forEach( function ( lg ) {
			if ( off( lg ) || lg.closest( NOT_CONTAINER ) ) { return; }
			var fs = lg.parentElement;
			var first = [].filter.call( fs.querySelectorAll( 'label, .wpcpm-ui-choice' ), function ( x ) { return ! lg.contains( x ) && ! off( x ) && rect( x ).width > 0; } )[ 0 ];
			var ls = lines( lg );
			if ( ! first || ! ls.length ) { return; }
			var rtl = 'rtl' === getComputedStyle( fs ).direction;
			var fr = rect( first );
			edge( { c: 'field set', k: 'legend', at: round( rtl ? fr.right - ls[ 0 ].right : ls[ 0 ].left - fr.left ), words: words( lg.textContent ), of: first.matches( '.wpcpm-ui-choice' ) ? 'first option' : 'first label' } );
		} );
		// A label's wrapped lines against its first line.
		main.querySelectorAll( '.wpcpm-ui-field__label, legend, .wpcpm-ui-choice__words, .wpcpm-ui-viewing__label' ).forEach( function ( lb ) {
			if ( off( lb ) || lb.closest( '.wpcpm-report-doc, .wpcpm-frame__menu' ) ) { return; }
			var ls = lines( lb );
			var rtl = 'rtl' === getComputedStyle( lb ).direction;
			var k = lb.matches( 'legend' ) ? 'legend' : lb.matches( '.wpcpm-ui-choice__words' ) ? 'option words' : 'label';
			for ( var i = 1; i < ls.length; i++ ) {
				edge( { c: k, k: k + ' line ' + ( i + 1 ), at: round( rtl ? ls[ 0 ].right - ls[ i ].right : ls[ i ].left - ls[ 0 ].left ), words: words( lb.textContent ), of: 'first line' } );
			}
		} );
		res.push( out );
	} );
	return res;
};
