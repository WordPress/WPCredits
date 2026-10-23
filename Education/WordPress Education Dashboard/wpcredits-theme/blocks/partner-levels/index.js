/**
 * Editor registration for the partnership levels (theme 1.32.1): the Partner levels block.
 *
 * Hand-written against the `wp.*` globals rather than built from JSX, so the theme has no build step.
 * The cards are drawn as the page draws them, a list of Partner level blocks and nothing else, each
 * edited where it is shown (blocks/partner-level/index.js); a new list starts with one empty level.
 * The Levels at a glance table and the sponsor application read the levels from this block.
 *
 * The block is dynamic: it saves its levels alone, as block comments with no markup of their own, and
 * the server draws the cards from them, so a later change to their markup never leaves the editor
 * saying the block holds unexpected content.
 */
( function ( blocks, element, blockEditor ) {
	'use strict';

	var el = element.createElement;

	blocks.registerBlockType( 'wpcredits/partner-levels', {
		edit: function () {
			var blockProps = blockEditor.useBlockProps( { className: 'wpc-partners-levels__grid' } );
			var innerBlocksProps = blockEditor.useInnerBlocksProps( blockProps, {
				allowedBlocks: [ 'wpcredits/partner-level' ],
				template: [ [ 'wpcredits/partner-level' ] ],
			} );

			return el( 'ul', innerBlocksProps );
		},

		// Dynamic: the levels are saved as blocks, and the server draws the cards from them.
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		},
	} );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor ) );
