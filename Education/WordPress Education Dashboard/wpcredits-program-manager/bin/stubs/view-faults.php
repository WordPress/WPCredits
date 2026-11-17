<?php
/**
 * The whole-view check every card suite of the Institution Dashboard shares (Task 23, fix round 1).
 *
 * A card's own suite draws its real parts drawing under the head of the view that holds it, as the page prints that
 * head in the frame (`wpcpm_view_page()`), and asks `wpcpm_view_faults()` what is wrong with the whole: one H1, the
 * headings never skipping a level going down, no id printed twice, the view's lede said once, and no heading under the
 * head repeating the view's title. `bin/test-institutions-dashboard.php` pins `wpcpm_view_page()`'s head against the
 * page's real head for every view, so the head drawn here is the page's and not a copy that could drift.
 *
 * Require it once from a suite: `require_once __DIR__ . '/stubs/view-faults.php';`
 */

if ( ! function_exists( 'wpcpm_view_faults' ) ) {
	/**
	 * Each view's head as the Institution Dashboard prints it, by the menu's label: title and lede.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	function wpcpm_view_heads() {
		return array(
			'Students'                => array( 'Students', 'Your students on the program, by cohort and by where they are, with the list you can download.' ),
			'Enroll students'         => array( 'Enroll students', 'Add students to the program, one at a time or from a list.' ),
			'Semester reports'        => array( 'Semester reports', 'The report the program prepares for your institution each semester, to read and download as a PDF once an Administrator has approved it.' ),
			'Your institution'        => array( 'Your institution', 'What the program has on record about your institution, and who can see this page.' ),
			'Collaboration Agreement' => array( 'Collaboration Agreement', 'The agreement between your institution and the program, and the signed copy on file.' ),
			'Events'                  => array( 'Events', 'Events the program\'s sponsors host, to join with your colleagues.' ),
			'For Administrators'      => array( 'For Administrators', 'Only Administrators see this section.' ),
		);
	}

	/**
	 * The head of one view, exactly as the page prints it in the frame for a reader who is not an Administrator
	 * viewing as a school: the page's H1, the view's H2 and its lede.
	 *
	 * @param string $view A key of `wpcpm_view_heads()`.
	 * @return string
	 */
	function wpcpm_view_head( $view ) {
		$heads = wpcpm_view_heads();
		return '<header class="wpcpm-head"><div class="wpcpm-head__copy"><h1 class="wpcpm-head__eyebrow">Institution Dashboard</h1>'
			. '<h2 class="wpcpm-head__title">' . htmlspecialchars( $heads[ $view ][0], ENT_QUOTES ) . '</h2>'
			. '<p class="wpcpm-head__lede">' . htmlspecialchars( $heads[ $view ][1], ENT_QUOTES ) . '</p></div></header>';
	}

	/**
	 * One view's main area: its head, then the cards' markup as drawn.
	 *
	 * @param string $view  A key of `wpcpm_view_heads()`.
	 * @param string $cards The real cards' markup, in the view's order.
	 * @return string
	 */
	function wpcpm_view_page( $view, $cards ) {
		return wpcpm_view_head( $view ) . (string) $cards;
	}

	/**
	 * What is wrong with one drawn view: the H1 count, a skipped heading level, an id twice, the lede twice, the
	 * view's title again under the head. Empty when nothing is.
	 *
	 * @param string $html A view's main area (`wpcpm_view_page()`, or the page's own main area).
	 * @return string[]
	 */
	function wpcpm_view_faults( $html ) {
		$html   = (string) $html;
		$faults = array();
		if ( 1 !== substr_count( $html, '<h1' ) ) {
			$faults[] = substr_count( $html, '<h1' ) . ' h1';
		}
		preg_match_all( '#<h([1-6])\b#', $html, $levels );
		$last = 0;
		foreach ( $levels[1] as $level ) {
			if ( (int) $level > $last + 1 ) {
				$faults[] = 'h' . $last . ' then h' . $level;
			}
			$last = (int) $level;
		}
		preg_match_all( '/\sid="([^"]+)"/', $html, $ids );
		foreach ( array_count_values( $ids[1] ) as $id => $count ) {
			if ( $count > 1 ) {
				$faults[] = 'id ' . $id . ' x' . $count;
			}
		}
		$lede = preg_match( '#<p class="wpcpm-head__lede">(.*?)</p>#s', $html, $l ) ? $l[1] : '';
		if ( '' !== $lede && 1 !== substr_count( $html, $lede ) ) {
			$faults[] = 'lede said ' . substr_count( $html, $lede ) . ' times';
		}
		$title = preg_match( '#<h2 class="wpcpm-head__title">(.*?)</h2>#s', $html, $t ) ? $t[1] : '';
		$under = (string) substr( $html, (int) strpos( $html, '</header>' ) );
		if ( '' !== $title && preg_match( '#<h[1-6][^>]*>\s*' . preg_quote( $title, '#' ) . '\s*</h[1-6]>#', $under ) ) {
			$faults[] = 'the title again under the head';
		}
		return $faults;
	}

	/**
	 * The faults of every drawing in a map, each under one view's head; only the drawings with a fault, by name.
	 *
	 * @param string               $view     A key of `wpcpm_view_heads()`.
	 * @param array<string,string> $drawings Name => a real card's drawing (an empty drawing is skipped).
	 * @return array<string,string[]>
	 */
	function wpcpm_view_faults_of( $view, array $drawings ) {
		$out = array();
		foreach ( $drawings as $name => $html ) {
			if ( '' === trim( (string) $html ) ) {
				continue;
			}
			$faults = wpcpm_view_faults( wpcpm_view_page( $view, $html ) );
			if ( $faults ) {
				$out[ $name ] = $faults;
			}
		}
		return $out;
	}

	/**
	 * Each row of every parts list in a drawing, in page order, as one line a person reads: the row's name, its tag,
	 * its facts and its actions, so a fact or an action printed on the wrong row changes the row it lands on. Used for
	 * the per-row pins of the states that print more than one row of the same kind (Task 23, fix round 1, S5).
	 *
	 * @param string $html A card's drawing.
	 * @return string[] "name [tag] | fact; fact | action, action" per row.
	 */
	function wpcpm_list_rows( $html ) {
		$text = static function ( $part ) {
			return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( (string) preg_replace( '#<span class="screen-reader-text">.*?</span>#s', '', (string) $part ) ), ENT_QUOTES ) ) );
		};
		preg_match_all( '#<li class="wpcpm-ui-list__row[^"]*">(.*?)</li>#s', (string) $html, $rows );
		$out = array();
		foreach ( $rows[1] as $row ) {
			$name = preg_match( '#<span class="wpcpm-ui-list__name">(.*?)</span>(?=<span class="wpcpm-(?:status|ui-list__facts)|<div|</div>)#s', $row, $n ) ? $text( $n[1] ) : '';
			$tag  = preg_match( '#<span class="wpcpm-status[^"]*"[^>]*>(.*?)</span>#s', $row, $t ) ? ' [' . $text( $t[1] ) . ']' : '';
			preg_match_all( '#<span class="wpcpm-ui-item__fact">(.*?)</span>(?=<span class="wpcpm-ui-item__fact">|</span>)#s', $row, $f );
			preg_match_all( '#<(?:button|a)\b[^>]*>(.*?)</(?:button|a)>#s', $row, $a );
			$out[] = $name . $tag . ' | ' . implode( '; ', array_map( $text, $f[1] ) ) . ' | ' . implode( ', ', array_map( $text, $a[1] ) );
		}
		return $out;
	}
}
