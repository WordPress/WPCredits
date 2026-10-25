<?php
/**
 * The movable modules a dashboard is made of: the saved order, one move, and the two arrows.
 *
 * Three pages share this: the Student Report Card, whose order belongs to the student
 * (1.95.12), the Institution Dashboard, whose order belongs to the institution (1.96.4), and the
 * Sponsor Dashboard, whose order belongs to the company (Institutions release 3). Each page owns
 * its module keys and labels, where the order is kept and who may move a module; this class owns
 * what is the same on all of them - repairing a saved order against the modules the version
 * knows, moving one module a place, the two arrows with the fields the handlers read and
 * `assets/js/modules.js` posts, and, for the two dashboards that draw their modules alike, the
 * module itself: its wrapper, its arrows, its title and its lead.
 *
 * @package WPCredits_Program_Manager
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order, move, arrows.
 */
class WPCPM_Module_Order {

	/** The script that moves a module in place and saves the order in the background. */
	const SCRIPT = 'wpcpm-modules';

	/** The fields every mover posts and every move handler reads. */
	const FIELD_MODULE    = 'wpcpm_module';
	const FIELD_DIRECTION = 'wpcpm_direction';
	const FIELD_ASYNC     = 'wpcpm_async';

	/**
	 * A saved order, repaired: unknown keys are dropped, a module the saved order does not know
	 * joins at the end in the default order, nothing appears twice, and a value that is not a
	 * list is the default.
	 *
	 * @param mixed    $saved What was stored.
	 * @param string[] $known The module keys this version has, in their default order.
	 * @return string[]
	 */
	public static function repair( $saved, array $known ) {
		$order = array();

		foreach ( is_array( $saved ) ? $saved : array() as $key ) {
			if ( is_string( $key ) && in_array( $key, $known, true ) && ! in_array( $key, $order, true ) ) {
				$order[] = $key;
			}
		}

		foreach ( $known as $key ) {
			if ( ! in_array( $key, $order, true ) ) {
				$order[] = $key;
			}
		}

		return $order;
	}

	/**
	 * One module moved one place. At the edge, or for a key the order does not hold, the order
	 * comes back unchanged.
	 *
	 * @param string[] $order     The order to move within.
	 * @param string   $key       Module key.
	 * @param string   $direction `up` or `down`.
	 * @return string[]
	 */
	public static function moved( array $order, $key, $direction ) {
		$order = array_values( $order );
		$from  = array_search( $key, $order, true );

		if ( false === $from ) {
			return $order;
		}

		$to = 'up' === $direction ? $from - 1 : $from + 1;

		if ( $to < 0 || $to >= count( $order ) ) {
			return $order;
		}

		$order[ $from ] = $order[ $to ];
		$order[ $to ]   = $key;

		return $order;
	}

	/**
	 * One module as the Institution Dashboard and the Sponsor Dashboard draw it: the wrapper that
	 * `assets/js/modules.js` finds by its class and its id, the arrows when the reader may arrange
	 * the page, the title in the eyebrow voice, the lead under it, then the cards.
	 *
	 * The two pages differ in the arguments alone - the action and the fields the arrows post, and
	 * the two classes that follow the shared ones on the title and the lead - so the markup the
	 * stylesheets and the theme dress is one markup. It was the Institution Dashboard's own until
	 * the Sponsor Dashboard's sections became modules; the Institution Dashboard's output is
	 * unchanged, and its suite pins it. The Student Report Card draws its modules in a shape of its
	 * own and does not come through here.
	 *
	 * @param array $args {
	 *     The module.
	 *
	 *     @type string $key         Module key; the wrapper's id is `wpcpm-module-` and the key.
	 *     @type string $title       The title, as text; also the arrows' name.
	 *     @type string $lead        The sentence under the title, as text, or '' for none.
	 *     @type string $body        What the module's cards printed, already escaped.
	 *     @type int    $index       Its place among the modules the page draws, from 0.
	 *     @type int    $count       How many modules the page draws.
	 *     @type string $action      The admin-post action the arrows post to; also the nonce's.
	 *     @type array  $fields      The hidden fields the arrows post besides the module: whose page it is.
	 *     @type bool   $can_move    Whether the reader may arrange the page.
	 *     @type string $title_class The title's class after the shared one.
	 *     @type string $lead_class  The lead's class after the shared one.
	 * }
	 */
	public static function render_module( array $args ) {
		$args = array_merge(
			array(
				'key'         => '',
				'title'       => '',
				'lead'        => '',
				'body'        => '',
				'index'       => 0,
				'count'       => 0,
				'action'      => '',
				'fields'      => array(),
				'can_move'    => false,
				'title_class' => '',
				'lead_class'  => '',
			),
			$args
		);

		printf( '<div class="wpcpm-module wpcpm-module--%1$s" id="wpcpm-module-%1$s">', esc_attr( (string) $args['key'] ) );

		if ( $args['can_move'] ) {
			self::render_mover( (string) $args['action'], (string) $args['key'], (int) $args['index'], (int) $args['count'], (array) $args['fields'], (string) $args['title'] );
		}

		printf( '<h2 class="wpcpm-student__heading %1$s">%2$s</h2>', esc_attr( (string) $args['title_class'] ), esc_html( (string) $args['title'] ) );

		if ( ! empty( $args['lead'] ) ) {
			printf( '<p class="wpcpm-student__note %1$s">%2$s</p>', esc_attr( (string) $args['lead_class'] ), esc_html( (string) $args['lead'] ) );
		}

		echo $args['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by the cards, each of which escapes what it prints.
		echo '</div>';
	}

	/**
	 * The two arrows at a module's top right: one form, two submit buttons, the outer one
	 * disabled at the edge, as the block editor's mover does it.
	 *
	 * The form also carries the sentence assets/js/modules.js speaks when a background save is
	 * refused and it has put the module back.
	 *
	 * @param string $action The admin-post action the page's move handler listens on; also
	 *                       the nonce's action.
	 * @param string $key    Module key.
	 * @param int    $index  Its place in the order, from 0.
	 * @param int    $count  How many modules the page has.
	 * @param array  $hidden Extra hidden fields, name to value: whose page it is.
	 * @param string $label  The module's name, for the arrows' spoken labels.
	 */
	public static function render_mover( $action, $key, $index, $count, array $hidden, $label ) {
		printf(
			'<form class="wpcpm-module__mover" method="post" action="%1$s" data-wpcpm-refused="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			// What assets/js/modules.js says once it has put a module back because the background
			// save was refused. It belongs here rather than in the script for the same reason the
			// arrows' labels do: the script never writes a sentence of its own.
			esc_attr__( 'The move was not kept.', 'wpcredits-program-manager' )
		);
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( $action );
		echo '<input type="hidden" name="' . esc_attr( self::FIELD_MODULE ) . '" value="' . esc_attr( $key ) . '">';

		foreach ( $hidden as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}

		self::render_button( 'up', $label, 0 === (int) $index );
		self::render_button( 'down', $label, (int) $index >= (int) $count - 1 );

		echo '</form>';
	}

	/**
	 * One arrow.
	 *
	 * @param string $direction `up` or `down`.
	 * @param string $label     The module's name.
	 * @param bool   $disabled  Whether the module is already at that edge.
	 */
	private static function render_button( $direction, $label, $disabled ) {
		$text = 'up' === $direction
			/* translators: %s: name of a module on a dashboard. */
			? sprintf( __( 'Move %s up', 'wpcredits-program-manager' ), $label )
			/* translators: %s: name of a module on a dashboard. */
			: sprintf( __( 'Move %s down', 'wpcredits-program-manager' ), $label );

		// What the script tells a screen reader once the module has moved.
		$done = 'up' === $direction
			/* translators: %s: name of a module on a dashboard. */
			? sprintf( __( '%s moved up.', 'wpcredits-program-manager' ), $label )
			/* translators: %s: name of a module on a dashboard. */
			: sprintf( __( '%s moved down.', 'wpcredits-program-manager' ), $label );

		$path = 'up' === $direction ? 'M6.5 14.5 12 9l5.5 5.5' : 'M6.5 9.5 12 15l5.5-5.5';

		printf(
			'<button type="submit" class="wpcpm-module__move wpcpm-module__move--%1$s" name="%2$s" value="%1$s" aria-label="%3$s" title="%3$s" data-wpcpm-moved="%6$s"%4$s><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="%5$s" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></button>',
			esc_attr( $direction ),
			esc_attr( self::FIELD_DIRECTION ),
			esc_attr( $text ),
			$disabled ? ' disabled' : '',
			esc_attr( $path ),
			esc_attr( $done )
		);
	}
}
