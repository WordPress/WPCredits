/**
 * Editor registration for the program index (theme 1.32.0): a container of Program cards.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * The index holds Program cards and nothing else; each card is edited where it is shown
 * (blocks/program-card/index.js). It is given no card of its own: an index with no inner blocks, a
 * 1.31 one among them, stays empty here until a card is added with its appender, and the page keeps
 * drawing the theme's programs for it; an empty card put in it would be saved, and the page would
 * then draw nothing. The chips are not drawn here: the server works them out from the cards when it
 * renders the page.
 *
 * The block is dynamic: it saves its cards alone, as block comments with no markup of their own, and
 * the server draws the index from them, so a later change to the index's markup never leaves the
 * editor saying the block holds unexpected content.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'wpcredits/program-index', {
		edit: function () {
			var blockProps = blockEditor.useBlockProps( { className: 'wpc-hub-index' } );
			var innerBlocksProps = blockEditor.useInnerBlocksProps(
				{ className: 'wpc-hub-programs' },
				{ allowedBlocks: [ 'wpcredits/program-card' ] }
			);

			return el(
				element.Fragment,
				null,
				el(
					blockEditor.InspectorControls,
					null,
					el(
						components.PanelBody,
						{ title: __( 'Program index', 'wpcredits-theme' ) },
						el( 'p', null, __( 'Each card is edited where it is shown: its name, what it is and its time on the card, and its status, audiences, slug, benefits and links in the card\'s settings.', 'wpcredits-theme' ) ),
						el( 'p', null, __( 'The filter chips and how many programs each shows are worked out on the page from the cards\' audiences. A card in development is shown on the page only while the hub shows its planned content.', 'wpcredits-theme' ) )
					)
				),
				el( 'div', blockProps, el( 'div', innerBlocksProps ) )
			);
		},

		// Dynamic: the cards are saved as blocks, and the server draws the index from them.
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n ) );
