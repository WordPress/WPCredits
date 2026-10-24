/**
 * Editor registration for the comparison table (theme 1.32.1): the Levels at a glance block.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * The table is drawn as the page draws it, from the Partner levels block this editor holds, read from
 * the editor's own store, so an edit of a level shows in the table at once: a column for each level,
 * headed by its name and short amount, a row for each thing a level includes, Yes from that level up.
 * Those words are the levels', edited there. A level hidden from its block's menu is left out, and a
 * Partner levels block hidden, or in a block that is hidden, is taken for none, as the page draws them
 * as nothing; one hidden at some window sizes only is kept. Where the editor holds more than one levels
 * block shown, the table is drawn from the one the server reads: the template's own first, then one in
 * a synced pattern or a template part it refers to (theme 1.32.2). With no Partner levels block shown
 * in this editor, a short note where the table would be says so, and what the page shows: the server
 * draws the table from the Partners template in use wherever the block is placed, so on the page the
 * table compares that template's levels, and is left out while it shows none. With levels of which
 * none includes anything, a note says that.
 *
 * The table's own words are the head word, edited in place, and the two words its cells say, in the
 * block's settings; each is the translated default until another is typed, as the server draws it. A
 * field's value is what is typed, and the default its placeholder, so an emptied field stays empty.
 *
 * The block is dynamic: it saves nothing but its attributes, and the server draws the table
 * (render.php) from the levels of the Partners template in use.
 */
( function ( blocks, element, blockEditor, components, i18n, data ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var _x = i18n._x;

	function tidy( list ) {
		return ( list || [] )
			.map( function ( item ) {
				return String( item ).trim();
			} )
			.filter( function ( item ) {
				return '' !== item;
			} );
	}

	// Whether a text as rich text keeps it says nothing, as the server judges it: no character but
	// white space once its tags are taken out.
	function blank( value ) {
		return '' === String( value || '' ).replace( /<[^>]*>/g, '' ).replace( /&nbsp;|&#160;|\s/g, '' );
	}

	// One of the table's words as typed, or '' while nothing is, or only spaces, which the server takes
	// for none: the value of its field, the translated default the field's placeholder, so deleting the
	// last character leaves the field empty rather than bringing the default back into it.
	function typed( value ) {
		return '' === String( value || '' ).trim() ? '' : String( value );
	}

	// One of the table's words as the server draws it: as typed, or the translated default while nothing
	// is.
	function word( value, fallback ) {
		return typed( value ) || fallback;
	}

	// Whether the page draws a block as nothing, as the server judges it: hidden from the block's menu,
	// which saves `blockVisibility: false` in its metadata. A block hidden at some window sizes only is
	// drawn, and shown at the others.
	function hidden( attributes ) {
		return !! attributes && !! attributes.metadata && false === attributes.metadata.blockVisibility;
	}

	// The blocks that draw other content in their place, which the server's reader follows only after the
	// template's own blocks: a synced pattern and a template part. The editor holds what each draws as its
	// inner blocks. A pattern block (`core/pattern`) the editor replaces with the pattern's blocks as it
	// loads them, so here they are among the template's own.
	var REFERENCES = [ 'core/block', 'core/template-part' ];

	// Whether a levels block met later in the markup is read before one met earlier, as the server reads
	// them: each is given by the references it sits in, the outermost first. Past the references the two
	// share, the server reads the blocks that sit there before what a further reference draws, so the
	// later one is read first only when it sits there and the earlier one is in a further reference.
	function readFirst( later, earlier ) {
		var at = 0;

		while ( at < later.length && at < earlier.length && later[ at ] === earlier[ at ] ) {
			at++;
		}

		return at === later.length && at < earlier.length;
	}

	// The Partner levels block the server's reader names, of those the editor holds: of the levels blocks
	// the page draws (not hidden, nor in a block that is hidden), the first among the template's own
	// blocks, at any depth, and only when there is none, one in what they refer to, each reference in the
	// order of the markup, its own blocks again first. Null when the page draws none.
	function drawnLevels( store ) {
		var named = null;
		var within = null;

		store.getBlocksByName( 'wpcredits/partner-levels' ).forEach( function ( clientId ) {
			var parents = store.getBlockParents( clientId );
			var references;

			if ( hidden( store.getBlockAttributes( clientId ) ) || parents.some( function ( parent ) {
				return hidden( store.getBlockAttributes( parent ) );
			} ) ) {
				return;
			}

			references = parents.filter( function ( parent ) {
				return -1 !== REFERENCES.indexOf( store.getBlockName( parent ) );
			} );

			if ( null === named || readFirst( references, within ) ) {
				named = clientId;
				within = references;
			}
		} );

		return named;
	}

	// The levels the table compares, as the server reads them from the Partner levels block: its
	// Partner level blocks in their order, a level with no name left out, and a hidden one.
	function levelsOf( held ) {
		return ( held || [] )
			.filter( function ( block ) {
				return 'wpcredits/partner-level' === block.name && ! blank( block.attributes.name ) && ! hidden( block.attributes );
			} )
			.map( function ( block ) {
				return {
					name: block.attributes.name,
					short: String( block.attributes.short || '' ).trim(),
					items: tidy( block.attributes.items ),
				};
			} );
	}

	// A row for each thing each level includes, the lowest level's first, with the level that lists it.
	function rowsOf( levels ) {
		var rows = [];

		levels.forEach( function ( level, from ) {
			level.items.forEach( function ( item ) {
				rows.push( { item: item, from: from } );
			} );
		} );

		return rows;
	}

	blocks.registerBlockType( 'wpcredits/partner-glance', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = blockEditor.useBlockProps( { className: 'wpc-partners-glance__scroll' } );

			// The levels of the Partner levels block the server's reader names (`drawnLevels()`), or null while
			// the template holds no such block that the page draws: a levels block hidden, or in a block that
			// is hidden, is drawn as nothing, as the server has it. The store answers with the same list
			// until a level changes, so the table is drawn again only then.
			var held = data.useSelect( function ( select ) {
				var store = select( 'core/block-editor' );
				var named = drawnLevels( store );

				return null !== named ? store.getBlocks( named ) : null;
			}, [] );
			var levels = levelsOf( held );
			var rows = rowsOf( levels );
			var labelWord = __( 'Recognition', 'wpcredits-theme' );
			var yesWord = _x( 'Yes', 'a partnership level has this recognition', 'wpcredits-theme' );
			var noWord = _x( 'No', 'a partnership level does not have this recognition', 'wpcredits-theme' );
			var yes = word( attributes.yes, yesWord );
			var no = word( attributes.no, noWord );
			var shown;

			function set( key ) {
				return function ( value ) {
					var change = {};

					change[ key ] = value;
					setAttributes( change );
				};
			}

			if ( null === held ) {
				// The server draws the table from the Partners template in use wherever the block is placed, so
				// the note says what the page shows, here and on any other page.
				shown = el( 'p', { className: 'wpc-partners-glance__note' }, __( 'This editor shows no Partner levels block to draw the table from. On the page, the table compares the levels of the Partners template in use, and is left out while that template shows none.', 'wpcredits-theme' ) );
			} else if ( ! rows.length ) {
				shown = el( 'p', { className: 'wpc-partners-glance__note' }, __( 'No level includes anything yet. What each Partner level includes, in its settings, makes the table\'s rows.', 'wpcredits-theme' ) );
			} else {
				shown = el(
					'table',
					{ className: 'wpc-partners-glance__table' },
					el(
						'thead',
						null,
						el(
							'tr',
							null,
							el(
								'th',
								{ scope: 'col' },
								el( blockEditor.PlainText, {
									value: typed( attributes.label ),
									placeholder: labelWord,
									onChange: set( 'label' ),
									'aria-label': __( 'The table\'s head word', 'wpcredits-theme' ),
								} )
							),
							levels.map( function ( level, at ) {
								return el(
									'th',
									{ key: at, scope: 'col' },
									el( blockEditor.RichText.Content, { tagName: 'span', className: 'wpc-partners-glance__level', value: level.name } ),
									level.short ? ' ' : null,
									level.short ? el( 'span', { className: 'wpc-partners-glance__amount' }, level.short ) : null
								);
							} )
						)
					),
					el(
						'tbody',
						null,
						rows.map( function ( row, index ) {
							return el(
								'tr',
								{ key: index },
								el( 'th', { scope: 'row' }, row.item ),
								levels.map( function ( level, at ) {
									return at >= row.from ?
										el( 'td', { key: at, className: 'wpc-partners-glance__yes' }, yes ) :
										el( 'td', { key: at, className: 'wpc-partners-glance__no' }, no );
								} )
							);
						} )
					)
				);
			}

			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Levels at a glance', 'wpcredits-theme' ) },
						el( 'p', null, __( 'The table is drawn from the Partner levels of the Partners template in use: a level\'s name, short amount and what it includes are edited there.', 'wpcredits-theme' ) ),
						el( components.TextControl, {
							label: __( 'The word for a level that has it', 'wpcredits-theme' ),
							value: typed( attributes.yes ),
							placeholder: yesWord,
							onChange: set( 'yes' ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						el( components.TextControl, {
							label: __( 'The word for a level that does not', 'wpcredits-theme' ),
							value: typed( attributes.no ),
							placeholder: noWord,
							onChange: set( 'no' ),
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} )
					)
				),
				el( 'div', blockProps, shown )
			);
		},

		// Dynamic: nothing is saved but the attributes, and the server draws the table.
		save: function () {
			return null;
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n, window.wp.data ) );
