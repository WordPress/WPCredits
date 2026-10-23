'use strict';
/*
 * The Partner levels blocks' editor scripts, run in node on a stand-in for the block editor
 * (bin/test-partners-js.php, theme 1.32.1).
 *
 *   node bin/js/partners-editor-harness.js <theme root>  < cases.json
 *
 * The cases come on stdin, as bin/test-partners-js.php renders them with the theme's own PHP: levels as
 * the Partner level blocks hold them, and the table the server draws for a Partners template holding
 * those levels, its head and its rows as words. Each scenario runs a block's `edit` on a stand-in of the
 * `wp.*` globals: elements are plain objects, `wp.data.useSelect` reads a stand-in of the editor's
 * store, holding the levels a scenario puts in it, and `wp.i18n` answers from a scenario's
 * translations. Prints `ok   <scenario>` or `FAIL <scenario>` with what was expected and what happened,
 * then `N scenarios, M differ`, and exits 1 when any differs.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const ROOT = path.resolve( process.argv[ 2 ] || path.join( __dirname, '..', '..' ) );
const CASES = JSON.parse( fs.readFileSync( 0, 'utf8' ) );

let ran = 0;
let failed = 0;

function scenario( name, want, build ) {
	let got;

	try {
		got = build();
	} catch ( error ) {
		got = 'threw: ' + error.message;
	}

	const ok = JSON.stringify( got ) === JSON.stringify( want );

	ran++;
	failed += ok ? 0 : 1;
	console.log( ( ok ? 'ok   ' : 'FAIL ' ) + name );

	if ( ! ok ) {
		console.log( '       exp: ' + JSON.stringify( want ) + '  got: ' + JSON.stringify( got ) );
	}
}

/*
 * The stand-in editor. A component is a named function, never called: an element of one keeps its
 * props, and the checks read them, as the editor would hand them to it.
 */
function component( name ) {
	const made = function () {};

	Object.defineProperty( made, 'name', { value: name } );

	return made;
}

const RichText = component( 'RichText' );
RichText.Content = component( 'RichTextContent' );

/**
 * Load the three editor scripts on a fresh stand-in.
 *
 * @param {Object} store        The editor's store: `held`, client ID => the blocks a block holds,
 *                              `names`, block name => the client IDs of the blocks of that name,
 *                              `attributes`, client ID => a block's attributes, and `parents`, client
 *                              ID => the client IDs of the blocks it sits in, the outermost first.
 * @param {Object} translations Text, or context, \u0004 and text => its translation.
 * @return {Object} The blocks registered, by name, and what the levels asked of its inner blocks.
 */
function editor( store, translations ) {
	const registered = {};
	const inner = {};
	const wp = {
		blocks: {
			registerBlockType: ( name, settings ) => {
				registered[ name ] = settings;
			},
		},
		element: {
			createElement: ( type, props, ...children ) => ( { type, props: props || {}, children: children.flat( Infinity ).filter( ( child ) => null !== child && undefined !== child && false !== child ) } ),
			Fragment: component( 'Fragment' ),
		},
		blockEditor: {
			RichText,
			PlainText: component( 'PlainText' ),
			InspectorControls: component( 'InspectorControls' ),
			InnerBlocks: { Content: component( 'InnerBlocksContent' ) },
			useBlockProps: ( props ) => Object.assign( { 'data-block': true }, props ),
			useInnerBlocksProps: ( props, options ) => {
				Object.assign( inner, options );

				return Object.assign( { 'data-inner-blocks': true }, props );
			},
		},
		components: {
			PanelBody: component( 'PanelBody' ),
			TextControl: component( 'TextControl' ),
			TextareaControl: component( 'TextareaControl' ),
		},
		i18n: {
			__: ( text ) => translations[ text ] || text,
			_x: ( text, context ) => translations[ context + '\u0004' + text ] || text,
		},
		data: {
			useSelect: ( map ) => map( ( name ) => ( 'core/block-editor' === name ? {
				getBlocksByName: ( blockName ) => store.names[ blockName ] || [],
				getBlocks: ( clientId ) => store.held[ clientId ] || [],
				getBlockAttributes: ( clientId ) => ( store.attributes || {} )[ clientId ] || null,
				getBlockParents: ( clientId ) => ( store.parents || {} )[ clientId ] || [],
			} : null ) ),
		},
	};
	const context = vm.createContext( { window: { wp } } );

	[ 'partner-levels', 'partner-level', 'partner-glance' ].forEach( ( block ) => {
		vm.runInContext( fs.readFileSync( path.join( ROOT, 'blocks', block, 'index.js' ), 'utf8' ), context );
	} );

	return { registered, inner };
}

/**
 * The store of an editor whose template holds a Partner levels block with these levels, or none: the
 * levels block with its own attributes, if given, and in blocks with the attributes given, the outermost
 * first, a group or a synced pattern; with several lists of levels, a levels block for each, in order.
 */
function holding( levels, own, around ) {
	if ( null === levels ) {
		return { names: {}, held: {}, attributes: {}, parents: {} };
	}

	const each = Array.isArray( levels[ 0 ] ) ? levels : [ levels ];
	const store = { names: { 'wpcredits/partner-levels': [] }, held: {}, attributes: {}, parents: {} };

	( around || [] ).forEach( ( attributes, at ) => {
		store.attributes[ 'around-' + at ] = attributes;
	} );

	each.forEach( ( list, at ) => {
		const id = 'levels-' + ( at + 1 );

		store.names[ 'wpcredits/partner-levels' ].push( id );
		store.held[ id ] = list.map( ( attributes ) => ( { name: 'wpcredits/partner-level', attributes } ) );
		store.attributes[ id ] = ( Array.isArray( own ) ? own[ at ] : own ) || {};
		store.parents[ id ] = ( around || [] ).map( ( attributes, up ) => 'around-' + up );
	} );

	return store;
}

/** Every element under a tree, in order, that a test picks, the sidebar's left out unless asked for. */
function all( node, pick, sidebar ) {
	if ( ! node || 'object' !== typeof node ) {
		return [];
	}

	if ( ! sidebar && 'function' === typeof node.type && 'InspectorControls' === node.type.name ) {
		return [];
	}

	return ( pick( node ) ? [ node ] : [] ).concat( ...node.children.map( ( child ) => all( child, pick, sidebar ) ) );
}

function named( name ) {
	return ( node ) => 'function' === typeof node.type && name === node.type.name;
}

function tag( name ) {
	return ( node ) => name === node.type;
}

/**
 * The words of an element as the editor shows them: a rich text's and a plain text's value among them,
 * and an empty plain text's placeholder, which its field shows while nothing is typed.
 */
function words( node ) {
	if ( 'string' === typeof node || 'number' === typeof node ) {
		return String( node );
	}

	if ( 'function' === typeof node.type && [ 'RichText', 'RichTextContent', 'PlainText' ].includes( node.type.name ) ) {
		const shown = node.props.value || ( 'PlainText' === node.type.name ? node.props.placeholder : '' );

		return String( shown || '' ).replace( /<[^>]*>/g, '' ).replace( /&amp;/g, '&' );
	}

	return node.children.map( words ).join( '' );
}

function said( node ) {
	return words( node ).replace( /\s+/g, ' ' ).trim();
}

/** The table an edit draws: its head's words and each row's, as bin/test-partners-js.php reads the server's. */
function table( tree ) {
	const drawn = all( tree, tag( 'table' ) )[ 0 ];

	return drawn ? {
		head: all( all( drawn, tag( 'thead' ) )[ 0 ], tag( 'th' ) ).map( said ),
		rows: all( all( drawn, tag( 'tbody' ) )[ 0 ], tag( 'tr' ) ).map( ( row ) => row.children.map( said ) ),
	} : null;
}

function glance( levels, attributes, translations ) {
	return glanceOn( holding( levels ), attributes, translations );
}

function glanceOn( store, attributes, translations ) {
	return editor( store, translations || {} ).registered[ 'wpcredits/partner-glance' ].edit( { attributes: attributes || {}, setAttributes: () => {} } );
}

function notes( tree ) {
	return all( tree, ( node ) => 'p' === node.type && 'wpc-partners-glance__note' === node.props.className ).length;
}

/* ---- the table ------------------------------------------------------------------------------ */

scenario( 'the table in the editor is the server\'s for today\'s five levels: the same head, the same fifteen rows, the same Yes and No', CASES.today.drawn, () => table( glance( CASES.today.levels ) ) );

scenario( 'an edit of the levels shows in the table at once, as the server draws it: a renamed level, an item added, a short amount taken out, a level with no name left out', CASES.edited.drawn, () => table( glance( CASES.edited.levels ) ) );

scenario( 'with no Partner levels block in the template, a note where the table would be and no table', [ null, 1 ], () => {
	const tree = glance( null );

	return [ table( tree ), notes( tree ) ];
} );

scenario( 'the note says what the page shows, wherever the block is placed: the table of the Partners template in use, which the server draws on another page too, or nothing while that template shows no levels', [ true, null, 'This editor shows no Partner levels block to draw the table from. On the page, the table compares the levels of the Partners template in use, and is left out while that template shows none.' ], () => {
	const tree = glance( null );

	return [ null !== CASES.elsewhere.drawn && CASES.elsewhere.drawn.head.length === CASES.today.drawn.head.length, CASES.elsewhere.none, all( tree, ( node ) => 'p' === node.type && 'wpc-partners-glance__note' === node.props.className ).map( said )[ 0 ] ];
} );

scenario( 'the block\'s settings name the levels the table is drawn from as the Partners template\'s, true on any page it is placed on', [ 'The table is drawn from the Partner levels of the Partners template in use: a level\'s name, short amount and what it includes are edited there.' ], () => {
	const sidebar = all( glance( CASES.today.levels ), named( 'InspectorControls' ), true )[ 0 ];

	return all( sidebar, tag( 'p' ), true ).map( said );
} );

scenario( 'with the levels emptied, or no level including anything, a note and no table, as the server draws nothing', [ null, 1, null, 1 ], () => {
	const emptied = glance( [] );
	const bare = glance( [ { name: 'Low' }, { name: 'High', items: [ '  ' ] } ] );

	return [ table( emptied ), notes( emptied ), table( bare ), notes( bare ) ];
} );

/* ---- hidden blocks (core's block visibility) -------------------------------------------------- */

const HIDDEN = { metadata: { blockVisibility: false } };

scenario( 'a level hidden from its block\'s menu is left out of the table, as the server leaves it out: four levels in the head, and the rows of their items alone', [ 5, CASES.hidden.drawn ], () => [ CASES.hidden.drawn.head.length, table( glance( CASES.hidden.levels ) ) ] );

scenario( 'a level hidden on phones only stays in the table, as the page still shows its card at the other sizes', [ 6, CASES.onPhones.drawn ], () => [ CASES.onPhones.drawn.head.length, table( glance( CASES.onPhones.levels ) ) ] );

scenario( 'a Partner levels block hidden, or in a group that is hidden, is no levels block: a note and no table, as the server draws none', [ null, null, 1, null, null, 1 ], () => {
	const own = glanceOn( holding( CASES.today.levels, HIDDEN ) );
	const inGroup = glanceOn( holding( CASES.today.levels, {}, [ {}, HIDDEN, {} ] ) );

	return [ CASES.levelsHidden.drawn, table( own ), notes( own ), CASES.groupHidden.drawn, table( inGroup ), notes( inGroup ) ];
} );

scenario( 'a levels block shown at only some window sizes, or in a group that is, is the table\'s source still', [ CASES.today.drawn, CASES.today.drawn ], () => [
	table( glanceOn( holding( CASES.today.levels, { metadata: { blockVisibility: { viewport: { mobile: false } } } } ) ) ),
	table( glanceOn( holding( CASES.today.levels, {}, [ { metadata: { blockVisibility: { viewport: { desktop: false } } } } ] ) ) ),
] );

scenario( 'with a hidden Partner levels block before one that is shown, the table is drawn from the one shown', CASES.today.drawn, () => table( glanceOn( holding( [ [ { name: 'Hidden', items: [ 'Not on the page' ] } ], CASES.today.levels ], [ HIDDEN, {} ] ) ) ) );

scenario( 'the head word and the two cell words, with none typed, are the translated strings the server prints, the head word as its field\'s placeholder', CASES.translated.drawn, () => table( glance( CASES.today.levels, {}, CASES.translated.translations ) ) );

/** The three words' fields: each one's value and placeholder, the head word's first. */
function fields( tree ) {
	return all( tree, named( 'PlainText' ) ).concat( all( tree, named( 'TextControl' ), true ) ).map( ( node ) => [ node.props.value, node.props.placeholder ] );
}

scenario( 'with none typed, each of the three words\' fields is empty, the translated string its placeholder, not its value', [ [ '', 'Anerkennung' ], [ '', 'Ja' ], [ '', 'Nein' ] ], () => fields( glance( CASES.today.levels, {}, CASES.translated.translations ) ) );

scenario( 'a word whose last character is deleted, or that is only spaces, leaves its field empty, the default not brought back into it; the table says the default, as the server draws it', [ [ [ '', 'Recognition' ], [ '', 'Yes' ], [ '', 'No' ] ], CASES.today.drawn, [ { yes: '' } ] ], () => {
	const changes = [];
	const typedOne = editor( holding( CASES.today.levels ), {} ).registered[ 'wpcredits/partner-glance' ].edit( { attributes: { yes: 'Y' }, setAttributes: ( change ) => changes.push( change ) } );
	const emptied = glance( CASES.today.levels, { label: '', yes: '', no: '   ' } );

	all( typedOne, named( 'TextControl' ), true )[ 0 ].props.onChange( '' );

	return [ fields( emptied ), table( emptied ), changes ];
} );

scenario( 'typed, they are what the table says; the head word is edited in place, the two others in the settings, each showing its value', [ CASES.typed.drawn, [ 'What it gives' ], [ 'Included', 'Not included' ] ], () => {
	const tree = glance( CASES.today.levels, CASES.typed.attributes );

	return [
		table( tree ),
		all( tree, named( 'PlainText' ) ).map( ( node ) => node.props.value ),
		all( tree, named( 'TextControl' ), true ).map( ( node ) => node.props.value ),
	];
} );

scenario( 'a head word of spaces is none, as the server judges it', 'Recognition', () => table( glance( CASES.today.levels, { label: '   ' } ) ).head[ 0 ] );

/* ---- a level and the levels ------------------------------------------------------------------ */

function level( attributes, isSelected, onChange ) {
	return editor( holding( null ), {} ).registered[ 'wpcredits/partner-level' ].edit( { attributes, isSelected: !! isSelected, setAttributes: onChange || ( () => {} ) } );
}

scenario( 'a level is drawn as its card: the program class and the tag after the name for a level with a tag, its amount, its lead and its list of what it includes', [ 'wpc-partners-level wpc-partners-level--program', [ 'Partner', 'Funds a named program' ], 'From $30,000 a year', 3 ], () => {
	const partner = CASES.today.levels[ 4 ];
	const tree = level( partner );
	const card = all( tree, tag( 'li' ) )[ 0 ];

	return [
		card.props.className,
		all( all( card, tag( 'h3' ) )[ 0 ], named( 'RichText' ) ).map( ( node ) => node.props.value ),
		said( all( card, ( node ) => 'function' === typeof node.type && 'wpc-partners-level__amount' === node.props.className )[ 0 ] ),
		all( all( card, ( node ) => 'ul' === node.type )[ 0 ], tag( 'li' ) ).length,
	];
} );

scenario( 'a level with no tag shows none and no program class, but a tag field while it is selected; with no items, a hint in place of the list', [ 'wpc-partners-level', 1, 2, 0, 1 ], () => {
	const plain = { name: 'Friend', tag: ' ', items: [] };
	const idle = level( plain, false );
	const selected = level( plain, true );

	return [
		all( idle, tag( 'li' ) )[ 0 ].props.className,
		all( all( idle, tag( 'h3' ) )[ 0 ], named( 'RichText' ) ).length,
		all( all( selected, tag( 'h3' ) )[ 0 ], named( 'RichText' ) ).length,
		all( idle, ( node ) => 'ul' === node.type ).length,
		all( idle, ( node ) => 'p' === node.type && 'wpc-partners-level__hint' === node.props.className ).length,
	];
} );

scenario( 'its short amount and its items, one to a line, are in its settings; the items typed keep what is typed until the field loses the focus, then each is trimmed and an empty one goes', [ '$5,000', 'Logo\nBadge', [ { items: [ ' Logo ', '', 'Badge' ] } ], [ { items: [ 'Logo', 'Badge' ] } ] ], () => {
	const typed = [];
	const tree = level( { name: 'Supporter', short: '$5,000', items: [ 'Logo', 'Badge' ] }, true, ( change ) => typed.push( change ) );
	const short = all( tree, named( 'TextControl' ), true )[ 0 ];
	const items = all( tree, named( 'TextareaControl' ), true )[ 0 ];
	const blurred = [];

	items.props.onChange( ' Logo \n\nBadge' );
	all( level( { name: 'Supporter', items: [ ' Logo ', '', 'Badge' ] }, true, ( change ) => blurred.push( change ) ), named( 'TextareaControl' ), true )[ 0 ].props.onBlur();

	return [ short.props.value, items.props.value, typed, blurred ];
} );

scenario( 'the levels are a list of Partner levels and nothing else, a new one starting with one empty level', [ 'ul', 'wpc-partners-levels__grid', [ 'wpcredits/partner-level' ], [ [ 'wpcredits/partner-level' ] ] ], () => {
	const loaded = editor( holding( null ), {} );
	const tree = loaded.registered[ 'wpcredits/partner-levels' ].edit( {} );

	return [ tree.type, tree.props.className, loaded.inner.allowedBlocks, loaded.inner.template ];
} );

console.log( '\n' + ran + ' scenarios, ' + failed + ' differ' );

process.exit( failed ? 1 : 0 );
