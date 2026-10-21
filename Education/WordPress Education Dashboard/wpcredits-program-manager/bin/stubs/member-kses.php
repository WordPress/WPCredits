<?php
/**
 * What WordPress does to a post's title and text on the way in for a person without
 * `unfiltered_html`, which every sponsor member is: core adds `wp_filter_kses` to `title_save_pre`
 * and `wp_filter_post_kses` to `content_save_pre` for such a person (`kses_init_filters()`), and
 * `wp_insert_post()` and `wp_update_post()` run them. kses writes every bare "&" as `&amp;`, and
 * leaves an entity that is already one alone. It reads a "<" with everything after it up to the
 * next ">" as a tag (`wp_kses_split()`), drops one it does not allow, words and all, and writes
 * every other ">" as `&gt;`; a "<" with no ">" before the next "<" its `pre_kses` filter writes as
 * `&lt;` first (`wp_pre_kses_less_than()`). The text cleaner takes out the tags a person types
 * before a title or a description reaches the insert, and leaves a "<" as typed only where white
 * space other than a line feed follows it and a ">" comes before the next "<": kses reads "< 12
 * welcome, adults >" as a tag named "12", and drops it. The stand-in drops every such span, and
 * a "<" with no ">" after it with everything to the end, which cleaned text never holds; core
 * would keep a span that names an element it allows, "< b >" as `<b>`, which no check types.
 *
 * A suite's `wp_insert_post()` and `wp_update_post()` stand-ins call `wpcpm_stub_kses_post_args()`
 * with the unslashed arguments. It filters in every check of a suite that loads this file, so every
 * check that reads what a press stores sees what WordPress stores; the plain-text mails read each
 * entity back (`WPCPM_Typed_Text::mail_text()`), so the checks that read an event's words in a
 * mail see them as typed. A suite turns it off by setting `$GLOBALS['member_kses']` to false, and
 * `wpcpm_stub_with_member_kses()` runs one step with it on whatever the suite set. The person's
 * capability is asked of the suite's own `current_user_can()` (bin/stubs/caps.php): an account the
 * suite grants `unfiltered_html` in `$GLOBALS['grants']`, an administrator, is stored as typed.
 *
 * Loaded with `require_once __DIR__ . '/stubs/member-kses.php';` from a suite's header.
 */

if ( ! isset( $GLOBALS['member_kses'] ) ) {
	$GLOBALS['member_kses'] = true;
}

/**
 * A title or a text as kses stores it for a person without `unfiltered_html`.
 *
 * @param string $text The unslashed text.
 * @return string
 */
function wpcpm_stub_member_kses( $text ) {
	$text = preg_replace( '/&(?![A-Za-z]{2,8}[0-9]{0,2};|#[0-9]{1,7};|#[xX][0-9A-Fa-f]{1,6};)/', '&amp;', (string) $text );

	return (string) preg_replace_callback(
		'/<[^>]*(?:>|$)|>/',
		static function ( $found ) {
			return '>' === $found[0] ? '&gt;' : '';
		},
		(string) $text
	);
}

/**
 * The arguments of a post as the insert keeps them: the title and the text through kses while the
 * stand-in is on, unless the signed-in person holds `unfiltered_html`.
 *
 * @param array $args The unslashed arguments of `wp_insert_post()` or `wp_update_post()`.
 * @return array
 */
function wpcpm_stub_kses_post_args( array $args ) {
	if ( empty( $GLOBALS['member_kses'] ) || current_user_can( 'unfiltered_html' ) ) {
		return $args;
	}

	foreach ( array( 'post_title', 'post_content' ) as $key ) {
		if ( isset( $args[ $key ] ) && is_string( $args[ $key ] ) ) {
			$args[ $key ] = wpcpm_stub_member_kses( $args[ $key ] );
		}
	}

	return $args;
}

/**
 * A step made with kses on for the people who do not hold `unfiltered_html`, off again after it.
 *
 * @param Closure $step The step.
 * @return mixed What the step answered.
 */
function wpcpm_stub_with_member_kses( Closure $step ) {
	$was                    = ! empty( $GLOBALS['member_kses'] );
	$GLOBALS['member_kses'] = true;

	try {
		return $step();
	} finally {
		$GLOBALS['member_kses'] = $was;
	}
}
