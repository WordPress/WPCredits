<?php
/**
 * WPCPM_Dashboard_Card: the one shell every card on the Sponsor Dashboard and the Administrator
 * Dashboard is drawn in, and the script that opens a card a link lands on.
 *
 * What each block pins, and why:
 *
 * - **The shell, byte for byte.** Every card is a section around a disclosure. On the Sponsor
 *   Dashboard the disclosure's id is the card's anchor, `wpcpm-sponsor-<key>`: the theme's card
 *   rhythm keys on the section, and `WPCPM_Sponsors_Dashboard::leave()`, the mails and the
 *   Administrator Dashboard land on the id. The shell is pinned whole for each thing a card can
 *   ask of it: nothing but a title, open, a count, a state, an action, and the agreement card's
 *   extra body class, so a card moved onto the shell draws exactly what it drew by hand.
 * - **The Administrator Dashboard's shape, byte for byte what its own card_open() printed.** Its
 *   cards keep their anchors on the section, `wpcpm-<key>`, where the mails, the wp-admin screens
 *   and every decision's way back land, and their own class on the section and on the disclosure,
 *   before the shared two, which is what that page's stylesheets key on. The expected bytes are a
 *   stored copy of what the page's own helper printed before its cards moved onto the shell: an
 *   open card, a folded one at zero, a count that needs formatting, a title and a key that need
 *   escaping.
 * - **The count is beside the title, inside the heading**, formatted for the reader, and absent
 *   when a card has none to give; a zero is a count and is printed. A card may give words of its
 *   own for its count, a count past a ceiling as the ceiling with a plus sign, which are printed in
 *   the number's place, escaped.
 * - **The state is one phrase in the summary**, after the heading and before the toggle, the way
 *   an offer's line of counts sits in its own summary.
 * - **The action sits after the fold, inside the section**, never in the summary: a control
 *   inside the summary would be a second interactive element inside the toggle, and anything
 *   else inside a closed disclosure is hidden. It links to the card's own anchor unless it names
 *   an address of its own, and close() prints the action of the card it closes, whether the card
 *   is drawn open or folded. The stylesheets show it only while its card is folded, whatever
 *   opened the card, a flash or a press in the browser: once the card is open its body is on
 *   screen, and an action after the body would come last in the reading and focus order while
 *   drawn beside the title. Words saying what the link does, naming its card or where it goes, may
 *   follow the label, visually hidden, so a link read out of its row, in a screen reader's list of
 *   links, stands alone.
 * - **Every value is escaped**: the key, the prefix, the title, the state, the action's words and
 *   address, the section's and the disclosure's classes, and the extra body class. The esc_url()
 *   stand-in drops and encodes what core's does for the characters these checks use.
 * - **Every card is drawn in the shell**: the files that draw the Sponsor Dashboard's thirteen
 *   cards and the Administrator Dashboard's seventeen open it once per card, close it wherever a
 *   card ends, and print no piece of it by hand, read from their source, so a change to the row
 *   reaches every card on both pages at once. Each names its key by a class constant or a literal
 *   and its title by a literal: the shell defaults both to '', and a card that could ask for an
 *   empty key would print an anchor that names no card.
 * - **The opener**, assets/js/dashboard-open.js, opens the disclosure a fragment names, the one a
 *   section it names opens with (an Administrator Dashboard card's anchor is on its section), and
 *   the ones around the element it names, when the page loads, whenever the fragment changes, and
 *   when a link to one of the page's anchors is pressed, since a link to the fragment already in
 *   the address fires no hashchange; it leaves the scrolling to the browser. After a press that
 *   opened a card and hid the link pressed (a row's action, hidden while its card is open), it
 *   moves the focus to the card's title once the browser has followed the link, since a browser
 *   drops the focus of what it hides; it does the same after a press on an At a glance tile, which
 *   stays on screen, since the browser leaves the focus on the page or on the tile rather than in
 *   the card the tile opened. A tabindex of -1 is given only to a title that has no place in the
 *   tab order, since -1 would take a title that has one out of it. A press that opens the link
 *   somewhere other than this page (a new tab or window, a download) is left to the browser: no
 *   card opens here and no focus moves. Read from the source, as bin/test-sponsor-badge.php reads
 *   copy.js: the suites run no browser.
 * - **Both pages enqueue the opener through the shell**: `enqueue_opener()` registers it once, in
 *   the footer and depending on nothing, keeps a registration something else made first, and
 *   enqueues it; neither dashboard registers the script itself.
 * - **One group of cards, one shape for both pages**: `group()` prints a group's wrapper, its
 *   heading and its one sentence over its cards, in the Sponsor Dashboard's classes unless a page
 *   asks for its own, every value escaped, and nothing at all for a group whose cards print nothing
 *   or nothing but white space; both dashboards print every group through it.
 * - **The plugin loads the class** among its shared classes, before any card drawn in it, and
 *   uninstall.php loads it too.
 *
 * Run from the plugin root:  php bin/test-dashboard-card.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return esc_html( $s ); }
/**
 * Core's esc_url() for what these checks give it: a space becomes %20, every character outside
 * its allowed set is dropped (a quote, an angle bracket), and in display context an ampersand
 * and an apostrophe are encoded. A fragment alone is left as it is, as core leaves it.
 *
 * @param string $url The address.
 * @return string
 */
function esc_url( $url ) {
	$url = str_replace( ' ', '%20', ltrim( (string) $url ) );
	$url = (string) preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url );
	return str_replace( array( '&', "'" ), array( '&#038;', '&#039;' ), $url );
}
// With the thousands separator core's English locale prints, so a count that skipped it shows.
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
// The script registry, as the two dashboards' suites stand it in: a handle registered once, with
// where it is from, its dependencies, its version and whether it waits for the footer, and enqueued
// by name. Every registration is recorded, so one made over another shows.
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );
$GLOBALS['scripts']              = array();
$GLOBALS['script_src']           = array();
$GLOBALS['script_args']          = array();
$GLOBALS['script_registrations'] = array();
function wp_script_is( $handle, $list = 'enqueued' ) { return 'registered' === $list && isset( $GLOBALS['script_src'][ $handle ] ); }
function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) { $GLOBALS['script_src'][ $handle ] = $src; $GLOBALS['script_args'][ $handle ] = array( $deps, $ver, $footer ); $GLOBALS['script_registrations'][] = $handle; }
function wp_enqueue_script( $handle ) { $GLOBALS['scripts'][] = $handle; }

// Loaded only when it is there, so that before the class exists its checks fail as checks.
if ( file_exists( __DIR__ . '/../includes/class-wpcpm-dashboard-card.php' ) ) {
	require_once __DIR__ . '/../includes/class-wpcpm-dashboard-card.php';
}

$fail   = 0;
$checks = 0;
function ck( $label, $actual, $expected ) {
	global $fail, $checks;
	++$checks;
	$ok = $actual === $expected;
	if ( ! $ok ) { $fail++; }
	echo ( $ok ? "ok   " : "FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		echo "       expected: " . var_export( $expected, true ) . "\n";
		echo "       actual:   " . var_export( $actual, true ) . "\n";
	}
}

/**
 * One card drawn in the shell around a body, as a card draws itself: open(), its body, close().
 *
 * @param array  $args What the card asks of the shell.
 * @param string $body The card's own markup.
 * @return string The markup, or '' while the class is missing.
 */
function shell( array $args, $body = '<p>Body.</p>' ) {
	if ( ! class_exists( 'WPCPM_Dashboard_Card' ) ) {
		return '';
	}
	ob_start();
	WPCPM_Dashboard_Card::open( $args );
	echo $body;
	WPCPM_Dashboard_Card::close();
	return (string) ob_get_clean();
}

/**
 * The markup a call prints, or '' while the class is missing.
 *
 * @param callable $draw What to call.
 * @return string
 */
function printed( callable $draw ) {
	if ( ! class_exists( 'WPCPM_Dashboard_Card' ) ) {
		return '';
	}
	ob_start();
	$draw();
	return (string) ob_get_clean();
}

$section = '<section class="wpcpm-sponsor__card">';
$toggle  = '<span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary>';
$body    = '<div class="wpcpm-group__body"><p>Body.</p></div></details>';

echo "=== The shell ===\n";
ck( 'the plainest card: a section around a disclosure keyed to its anchor, the title, the toggle and the body, closed',
	shell( array( 'key' => 'profile', 'title' => 'Company profile and logo' ) ),
	$section . '<details id="wpcpm-sponsor-profile" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Company profile and logo</h3>' . $toggle . $body . '</section>'
);
ck( 'open: the disclosure carries the open attribute, and nothing else moves',
	shell( array( 'key' => 'events', 'title' => 'Events', 'open' => true ) ),
	$section . '<details id="wpcpm-sponsor-events" class="wpcpm-group wpcpm-group__disclosure" open><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Events</h3>' . $toggle . $body . '</section>'
);
ck( 'a count: beside the title, inside the heading',
	shell( array( 'key' => 'posts', 'title' => 'Posts', 'count' => 3 ) ),
	$section . '<details id="wpcpm-sponsor-posts" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Posts <span class="wpcpm-group__count">3</span></h3>' . $toggle . $body . '</section>'
);
ck( 'formatted for the reader, and a zero is a count, printed as one',
	array(
		false !== strpos( shell( array( 'key' => 'usage', 'title' => 'Usage', 'count' => 1234 ) ), '<h3 class="wpcpm-group__title">Usage <span class="wpcpm-group__count">1,234</span></h3>' ),
		false !== strpos( shell( array( 'key' => 'people', 'title' => 'People', 'count' => 0 ) ), '<h3 class="wpcpm-group__title">People <span class="wpcpm-group__count">0</span></h3>' ),
	),
	array( true, true )
);
// The title's words carry an id when a card asks for one (`title_id`, 1.123.17), the anchor and
// `-title`, for a table's scroll box to be named by them and not by the count; the heading is the
// same otherwise, and a card that does not ask prints none.
ck( 'a card that asks gives its title words an id, the anchor and -title, on either page, and one that does not prints none',
	array(
		false !== strpos( shell( array( 'key' => 'usage', 'title' => 'Usage', 'count' => 5, 'title_id' => true ) ), '<h3 class="wpcpm-group__title"><span id="wpcpm-sponsor-usage-title">Usage</span> <span class="wpcpm-group__count">5</span></h3>' ),
		false !== strpos( shell( array( 'key' => 'health', 'title' => 'Syncs and health', 'prefix' => 'wpcpm-', 'title_id' => true ) ), '<h3 class="wpcpm-group__title"><span id="wpcpm-health-title">Syncs and health</span></h3>' ),
		strpos( shell( array( 'key' => 'usage', 'title' => 'Usage', 'count' => 5 ) ), '-title"' ),
		WPCPM_Dashboard_Card::title_id( 'wpcpm-sponsor-usage' ),
	),
	array( true, true, false, 'wpcpm-sponsor-usage-title' )
);
// Each card drawn first, so a shell that printed nothing cannot pass for one without a count.
$null_count = shell( array( 'key' => 'reports', 'title' => 'Reports', 'count' => null ) );
$no_count   = shell( array( 'key' => 'reports', 'title' => 'Reports' ) );
ck( 'no count at all when a card has none to give',
	array(
		false !== strpos( $null_count, '<h3 class="wpcpm-group__title">Reports</h3>' ),
		strpos( $null_count, 'wpcpm-group__count' ),
		false !== strpos( $no_count, '<h3 class="wpcpm-group__title">Reports</h3>' ),
		strpos( $no_count, 'wpcpm-group__count' ),
	),
	array( true, false, true, false )
);
// A count past a ceiling is printed as the words a card gives for it, the ceiling with a plus sign,
// while the number stays what the card counts: the words stand in for the number, never for the
// count itself, so a card with no count prints none whatever words it gives.
ck( 'a count may come with words of its own, printed in its place beside the title and escaped; with no count there is still none',
	array(
		false !== strpos( shell( array( 'key' => 'posts', 'title' => 'Posts', 'count' => 201, 'shown' => '200+' ) ), '<h3 class="wpcpm-group__title">Posts <span class="wpcpm-group__count">200+</span></h3>' ),
		false !== strpos( shell( array( 'key' => 'posts', 'title' => 'Posts', 'count' => 201, 'shown' => '<b>200</b>+' ) ), '<h3 class="wpcpm-group__title">Posts <span class="wpcpm-group__count">&lt;b&gt;200&lt;/b&gt;+</span></h3>' ),
		false !== strpos( shell( array( 'key' => 'posts', 'title' => 'Posts', 'count' => 1234, 'shown' => '' ) ), '<h3 class="wpcpm-group__title">Posts <span class="wpcpm-group__count">1,234</span></h3>' ),
		strpos( shell( array( 'key' => 'posts', 'title' => 'Posts', 'shown' => '200+' ) ), 'wpcpm-group__count' ),
	),
	array( true, true, true, false )
);
ck( 'a state: one phrase in the summary, after the heading and before the toggle',
	shell( array( 'key' => 'offers', 'title' => 'Offers and codes', 'state' => 'Two live, five claims this month.' ) ),
	$section . '<details id="wpcpm-sponsor-offers" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Offers and codes</h3><span class="wpcpm-sponsor__state">Two live, five claims this month.</span>' . $toggle . $body . '</section>'
);
ck( 'an action: after the fold, inside the section, a link to the card\'s own anchor unless it names another',
	shell( array( 'key' => 'offers', 'title' => 'Offers and codes', 'action' => array( 'label' => 'New offer' ) ) ),
	$section . '<details id="wpcpm-sponsor-offers" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Offers and codes</h3>' . $toggle . $body . '<p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-offers">New offer</a></p></section>'
);
ck( 'and an address of its own when it names one',
	substr( shell( array( 'key' => 'posts', 'title' => 'Posts', 'action' => array( 'label' => 'Write a post', 'href' => 'https://example.test/wp-admin/post-new.php' ) ) ), -strlen( '</details><p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="https://example.test/wp-admin/post-new.php">Write a post</a></p></section>' ) ),
	'</details><p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="https://example.test/wp-admin/post-new.php">Write a post</a></p></section>'
);
ck( 'everything at once on a folded row, in its place',
	shell( array( 'key' => 'offers', 'title' => 'Offers and codes', 'count' => 3, 'state' => 'Two live.', 'action' => array( 'label' => 'New offer' ) ) ),
	$section . '<details id="wpcpm-sponsor-offers" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Offers and codes <span class="wpcpm-group__count">3</span></h3><span class="wpcpm-sponsor__state">Two live.</span>' . $toggle . $body . '<p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-offers">New offer</a></p></section>'
);
// The action is printed whatever the card's state when the page is drawn, and the stylesheets hide it
// while its card is open: a card opens in the browser too, by a press on its title, a tile or the
// action itself, and a card drawn open can be folded, so what the server knew at render is not what
// decides it. One rule, keyed on the disclosure's own state, does.
ck( 'and drawn open, the same card prints its action too: the stylesheets hide it while the card is open, whatever opened it',
	shell( array( 'key' => 'offers', 'title' => 'Offers and codes', 'count' => 3, 'open' => true, 'state' => 'Two live.', 'action' => array( 'label' => 'New offer' ) ) ),
	$section . '<details id="wpcpm-sponsor-offers" class="wpcpm-group wpcpm-group__disclosure" open><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Offers and codes <span class="wpcpm-group__count">3</span></h3><span class="wpcpm-sponsor__state">Two live.</span>' . $toggle . $body . '<p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-offers">New offer</a></p></section>'
);
// A row's link read out of its row, in a screen reader's list of links, says only "Edit": the words
// that name the card follow the label inside the link, visually hidden, as the Reports card's Open
// and PDF carry the report's title.
ck( 'an action may name its card for a screen reader: the words follow the label inside the link, visually hidden',
	substr( shell( array( 'key' => 'profile', 'title' => 'Company profile and logo', 'action' => array( 'label' => 'Edit', 'context' => 'the company profile and logo' ) ) ), -strlen( '</details><p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-profile">Edit<span class="screen-reader-text"> the company profile and logo</span></a></p></section>' ) ),
	'</details><p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-profile">Edit<span class="screen-reader-text"> the company profile and logo</span></a></p></section>'
);
ck( 'a body class a card adds is added to the body, after the shared one',
	shell( array( 'key' => 'agreement', 'title' => 'Collaboration Agreement', 'body_class' => 'wpcpm-agreement' ) ),
	$section . '<details id="wpcpm-sponsor-agreement" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Collaboration Agreement</h3>' . $toggle . '<div class="wpcpm-group__body wpcpm-agreement"><p>Body.</p></div></details></section>'
);
ck( 'an empty state and an action with no words print nothing: a row says nothing rather than an empty phrase, and offers no link without a name',
	shell( array( 'key' => 'usage', 'title' => 'Usage', 'state' => '', 'action' => array( 'label' => '' ) ) ),
	$section . '<details id="wpcpm-sponsor-usage" class="wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Usage</h3>' . $toggle . $body . '</section>'
);

echo "\n=== The Administrator Dashboard's shape ===\n";
/**
 * One Administrator Dashboard card drawn in the shell, as that page asks for its cards: the anchor
 * `wpcpm-<key>` on the section, the page's class on the section and on the disclosure, the count
 * always given, and open as the card's rule asks: this helper's cards, the queues', open above
 * zero.
 *
 * @param string $key   The card's key.
 * @param string $title The title.
 * @param int    $count The count.
 * @param array  $more  Anything else the card asks of the shell.
 * @return string The markup, or '' while the class is missing.
 */
function administrator_card( $key, $title, $count, array $more = array() ) {
	return shell(
		array_merge(
			array(
				'key'           => $key,
				'title'         => $title,
				'count'         => $count,
				'open'          => $count > 0,
				'prefix'        => 'wpcpm-',
				'section_class' => 'wpcpm-administrator__card',
				'details_class' => 'wpcpm-administrator__disclosure',
				'id_on'         => 'section',
			),
			$more
		)
	);
}
// A stored copy of what WPCPM_Administrators_Cards::card_open() and card_close() printed around
// '<p>Body.</p>' at cde94e7, the last commit before that page's cards moved onto the shell, with this
// suite's stand-ins: the page's anchors, mails and wp-admin screens land where these put them.
$administrator_today = array(
	'open, with its count'         => '<section class="wpcpm-administrator__card" id="wpcpm-applications"><details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure" open><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Institution applications <span class="wpcpm-group__count">2</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary><div class="wpcpm-group__body"><p>Body.</p></div></details></section>',
	'folded at zero, zero printed' => '<section class="wpcpm-administrator__card" id="wpcpm-health"><details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure"><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Syncs and health <span class="wpcpm-group__count">0</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary><div class="wpcpm-group__body"><p>Body.</p></div></details></section>',
	'a count formatted'            => '<section class="wpcpm-administrator__card" id="wpcpm-sponsor-reports"><details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure" open><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Sponsor reports <span class="wpcpm-group__count">1,234</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary><div class="wpcpm-group__body"><p>Body.</p></div></details></section>',
	'a title escaped'              => '<section class="wpcpm-administrator__card" id="wpcpm-interests"><details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure" open><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Offers &lt;b&gt;and&lt;/b&gt; codes &amp; more <span class="wpcpm-group__count">3</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary><div class="wpcpm-group__body"><p>Body.</p></div></details></section>',
	'a key escaped'                => '<section class="wpcpm-administrator__card" id="wpcpm-x&quot; onclick=&quot;y"><details class="wpcpm-administrator__disclosure wpcpm-group wpcpm-group__disclosure" open><summary class="wpcpm-group__summary"><h3 class="wpcpm-group__title">Odd <span class="wpcpm-group__count">1</span></h3><span class="wpcpm-mentee__toggle" aria-hidden="true"></span></summary><div class="wpcpm-group__body"><p>Body.</p></div></details></section>',
);
ck( 'an open card with its count: the anchor on the section, the page\'s class before the shared two, byte for byte what card_open() printed',
	administrator_card( 'applications', 'Institution applications', 2 ),
	$administrator_today['open, with its count']
);
ck( 'folded at zero, the zero printed', administrator_card( 'health', 'Syncs and health', 0 ), $administrator_today['folded at zero, zero printed'] );
ck( 'a count formatted for the reader', administrator_card( 'sponsor-reports', 'Sponsor reports', 1234 ), $administrator_today['a count formatted'] );
ck( 'a title escaped', administrator_card( 'interests', 'Offers <b>and</b> codes & more', 3 ), $administrator_today['a title escaped'] );
ck( 'and a key escaped in the section\'s id', administrator_card( 'x" onclick="y', 'Odd', 1 ), $administrator_today['a key escaped'] );
ck( 'an action on such a card links to the anchor on its section',
	substr( administrator_card( 'applications', 'Institution applications', 0, array( 'action' => array( 'label' => 'Go' ) ) ), -strlen( '</details><p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-applications">Go</a></p></section>' ) ),
	'</details><p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-applications">Go</a></p></section>'
);
// Each argument on its own, with the Sponsor Dashboard's anchor on the disclosure: a class for the
// section is the section's, a class for the disclosure comes before the shared two, and a place for
// the anchor the shell does not know is the disclosure, so no card can ask for an anchor on nothing.
ck( 'a section class and a disclosure class of their own, the second before the shared two, and an unknown place for the anchor is the disclosure',
	array(
		0 === strpos( shell( array( 'key' => 'offers', 'title' => 'Offers', 'section_class' => 'wpcpm-extra-card', 'details_class' => 'wpcpm-extra' ) ), '<section class="wpcpm-extra-card"><details id="wpcpm-sponsor-offers" class="wpcpm-extra wpcpm-group wpcpm-group__disclosure"><summary' ),
		'' !== shell( array( 'key' => 'offers', 'title' => 'Offers' ) ) && shell( array( 'key' => 'offers', 'title' => 'Offers', 'id_on' => 'summary' ) ) === shell( array( 'key' => 'offers', 'title' => 'Offers' ) ),
	),
	array( true, true )
);

echo "\n=== Every value escaped ===\n";
$loud = shell(
	array(
		'key'        => 'offers',
		'title'      => 'Offers <b>and</b> codes',
		'state'      => 'Two <b>live</b> & more.',
		'body_class' => 'wpcpm-agreement" data-x="1',
		'action'     => array(
			'label'   => '<b>New</b> offer',
			'href'    => 'https://example.test/?a=1&b=2"><script>',
			'context' => 'for <b>your</b> offers & codes',
		),
	)
);
ck( 'the title, the state and the action\'s words, the ones a screen reader hears among them, are text, never markup',
	array(
		false !== strpos( $loud, '<h3 class="wpcpm-group__title">Offers &lt;b&gt;and&lt;/b&gt; codes</h3>' ),
		false !== strpos( $loud, '<span class="wpcpm-sponsor__state">Two &lt;b&gt;live&lt;/b&gt; &amp; more.</span>' ),
		false !== strpos( $loud, '>&lt;b&gt;New&lt;/b&gt; offer<span class="screen-reader-text"> for &lt;b&gt;your&lt;/b&gt; offers &amp; codes</span></a>' ),
		strpos( $loud, '<b>' ),
	),
	array( true, true, true, false )
);
ck( 'the action\'s address goes through esc_url(), and the body class through esc_attr()',
	array(
		false !== strpos( $loud, 'href="https://example.test/?a=1&#038;b=2script"' ),
		false !== strpos( $loud, '<div class="wpcpm-group__body wpcpm-agreement&quot; data-x=&quot;1">' ),
		strpos( $loud, '<script>' ),
	),
	array( true, true, false )
);
$odd      = shell( array( 'key' => 'x" onclick="y', 'title' => 'Odd', 'action' => array( 'label' => 'Go' ) ) );
$odd_page = shell( array( 'key' => 'k', 'title' => 'Odd', 'prefix' => 'wpcpm-"x-', 'section_class' => 'a" onclick="b', 'details_class' => 'c" onclick="d', 'id_on' => 'section', 'action' => array( 'label' => 'Go' ) ) );
ck( 'the prefix, the section\'s class and the disclosure\'s class are escaped where they are printed',
	array(
		0 === strpos( $odd_page, '<section class="a&quot; onclick=&quot;b" id="wpcpm-&quot;x-k"><details class="c&quot; onclick=&quot;d wpcpm-group wpcpm-group__disclosure"><summary' ),
		false !== strpos( $odd_page, 'href="#wpcpm-x-k"' ),
	),
	array( true, true )
);
ck( 'a key is escaped in the id and in the anchor the action links to',
	array(
		false !== strpos( $odd, '<details id="wpcpm-sponsor-x&quot; onclick=&quot;y" class="wpcpm-group wpcpm-group__disclosure">' ),
		false !== strpos( $odd, 'href="#wpcpm-sponsor-x%20onclick=y"' ),
	),
	array( true, true )
);

echo "\n=== close() closes the card it opened ===\n";
$two = printed(
	static function () {
		WPCPM_Dashboard_Card::open( array( 'key' => 'offers', 'title' => 'Offers and codes', 'action' => array( 'label' => 'New offer' ) ) );
		WPCPM_Dashboard_Card::close();
		WPCPM_Dashboard_Card::open( array( 'key' => 'usage', 'title' => 'Usage' ) );
		WPCPM_Dashboard_Card::close();
	}
);
ck( 'two cards in a row: the first prints its action, and the second, which has none, prints none of the first\'s',
	array( substr_count( $two, 'wpcpm-sponsor__row-action' ), substr_count( $two, '</details></section>' ), false !== strpos( $two, '<p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-offers">New offer</a></p></section><section class="wpcpm-sponsor__card"><details id="wpcpm-sponsor-usage"' ) ),
	array( 1, 1, true )
);
$nested = printed(
	static function () {
		WPCPM_Dashboard_Card::open( array( 'key' => 'outer', 'title' => 'Outer', 'action' => array( 'label' => 'Outer action' ) ) );
		WPCPM_Dashboard_Card::open( array( 'key' => 'inner', 'title' => 'Inner', 'action' => array( 'label' => 'Inner action' ) ) );
		WPCPM_Dashboard_Card::close();
		WPCPM_Dashboard_Card::close();
	}
);
ck( 'one inside another: each close() prints the action of the card it closes, the inner one first',
	false !== strpos( $nested, '</div></details><p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-inner">Inner action</a></p></section></div></details><p class="wpcpm-sponsor__row-action"><a class="wpcpm-button wpcpm-button--secondary" href="#wpcpm-sponsor-outer">Outer action</a></p></section>' ),
	true
);
ck( 'a close() with nothing open still ends the markup, with no action',
	printed(
		static function () {
			WPCPM_Dashboard_Card::close();
		}
	),
	'</div></details></section>'
);

echo "\n=== Every card is drawn in the shell ===\n";
// The Sponsor Dashboard's twelve cards and the Administrator Dashboard's eighteen, by the file that
// draws them and how many it draws: the tier card draws Sponsorship and Events, the mentors class its
// two cards, the Sponsor Dashboard its People card, and the Administrator Dashboard's cards class all
// eighteen of that page, Sponsor offers to review among them since 1.123.18. Each opens the shell once per card, closes it wherever a card ends, and
// prints no piece of the shell by hand, so a change to the row reaches every card on both pages at
// once. Some cards end early: Usage when there is no offer, and on the Administrator Dashboard
// Offers running low, Sponsor terms ending and Sponsor reports when they hold nothing.
$early    = array(
	'class-wpcpm-sponsor-usage.php'        => 1,
	'class-wpcpm-administrators-cards.php' => 3,
);
$drawers  = array(
	'class-wpcpm-sponsor-tier-card.php'      => 2,
	'class-wpcpm-sponsor-report-card.php'    => 1,
	'class-wpcpm-sponsor-badge.php'          => 1,
	'class-wpcpm-sponsor-offers.php'         => 1,
	'class-wpcpm-sponsor-usage.php'          => 1,
	'class-wpcpm-sponsor-posts.php'          => 1,
	'class-wpcpm-sponsor-mentors.php'        => 2,
	'class-wpcpm-sponsor-profile.php'        => 1,
	'class-wpcpm-sponsors-dashboard.php'     => 1,
	'class-wpcpm-sponsor-agreement-card.php' => 1,
	'class-wpcpm-administrators-cards.php'   => 18,
);
$drawn    = array();
$expected = array();
foreach ( $drawers as $file => $cards ) {
	$src             = (string) file_get_contents( __DIR__ . '/../includes/modules/' . $file );
	$drawn[ $file ]  = array(
		'opens'   => substr_count( $src, 'WPCPM_Dashboard_Card::open(' ),
		'closes'  => substr_count( $src, 'WPCPM_Dashboard_Card::close()' ),
		'by hand' => substr_count( $src, '<section class="wpcpm-sponsor__card">' ) + substr_count( $src, '<section class="wpcpm-administrator__card" id=' ) + substr_count( $src, ' wpcpm-group wpcpm-group__disclosure"' ) + substr_count( $src, 'class="wpcpm-group wpcpm-group__disclosure"' ) + substr_count( $src, '<span class="wpcpm-mentee__toggle" aria-hidden="true"></span>' ) + substr_count( $src, '</details></section>' ),
	);
	$expected[ $file ] = array(
		'opens'   => $cards,
		'closes'  => $cards + ( isset( $early[ $file ] ) ? $early[ $file ] : 0 ),
		'by hand' => 0,
	);
}
ck( 'the thirty cards of both pages open and close the shell, and none prints a piece of it by hand', $drawn, $expected );
// Offer more, the interests card, left the Sponsor Dashboard in 1.123.22: its class draws no card.
ck( 'the interests class draws no card any more', substr_count( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php' ), 'WPCPM_Dashboard_Card::' ), 0 );
$administrator_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-administrators-cards.php' );
ck( 'the Administrator Dashboard\'s own card_open() and card_close() are gone, so its cards have one way to be drawn', array( strpos( $administrator_src, 'card_open' ), strpos( $administrator_src, 'card_close' ) ), array( false, false ) );
/**
 * The argument of every call to the shell's open() in a file's source, read with PHP's own tokens,
 * so a parenthesis inside a string or a nested call never ends one early.
 *
 * @param string $src The file's source.
 * @return string[] Each call's argument, as written.
 */
function shell_opens( $src ) {
	$tokens = token_get_all( $src );
	$calls  = array();
	$count  = count( $tokens );
	for ( $i = 0; $i < $count; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) || 'WPCPM_Dashboard_Card' !== $tokens[ $i ][1] || ! isset( $tokens[ $i + 2 ] ) || ! is_array( $tokens[ $i + 2 ] ) || 'open' !== $tokens[ $i + 2 ][1] ) {
			continue;
		}
		$depth = 0;
		$text  = '';
		for ( $j = $i + 3; $j < $count; $j++ ) {
			$piece = is_array( $tokens[ $j ] ) ? $tokens[ $j ][1] : $tokens[ $j ];
			if ( '(' === $piece ) { ++$depth; }
			if ( ')' === $piece ) { --$depth; }
			$text .= $piece;
			if ( 0 === $depth ) { break; }
		}
		$calls[] = $text;
	}
	return $calls;
}
// The shell defaults a key and a title to '', since a guard that refused to print would swallow the
// card's body: so each call names both where nothing at run time can empty them, its key by a class
// constant or a literal and its title by a literal through __().
$named          = array();
$expected_named = array();
foreach ( $drawers as $file => $cards ) {
	$named[ $file ] = array_map(
		static function ( $call ) {
			return array(
				1 === preg_match( "/'key'\\s*=>\\s*(?:self::[A-Z][A-Z_]*|'[a-z][a-z_-]*')\\s*,/", $call ),
				1 === preg_match( "/'title'\\s*=>\\s*__\\(\\s*'[^']+',\\s*'wpcredits-program-manager'\\s*\\)\\s*,/", $call ),
			);
		},
		shell_opens( (string) file_get_contents( __DIR__ . '/../includes/modules/' . $file ) )
	);
	$expected_named[ $file ] = array_fill( 0, $cards, array( true, true ) );
}
ck( 'and each names its key by a class constant or a literal and its title by a literal, so none can ask the shell for an anchor that names no card', $named, $expected_named );

echo "\n=== The opener ===\n";
$opener_file = __DIR__ . '/../assets/js/dashboard-open.js';
$opener      = is_file( $opener_file ) ? (string) file_get_contents( $opener_file ) : '';
ck( 'assets/js/dashboard-open.js exists, and the Sponsor Dashboard\'s own opener is gone', array( '' !== $opener, is_file( __DIR__ . '/../assets/js/sponsor-open.js' ) ), array( true, false ) );
ck( 'it runs once the document is parsed, straight away when it already is, and again on every change of the fragment',
	array(
		false !== strpos( $opener, "document.addEventListener( 'DOMContentLoaded'" ),
		false !== strpos( $opener, "'loading' !== document.readyState" ),
		false !== strpos( $opener, "window.addEventListener( 'hashchange'" ),
	),
	array( true, true, true )
);
/**
 * A function of the opener's, from its signature to the brace that closes it, or '' when there is none.
 *
 * @param string $js        The script.
 * @param string $signature Its start, `function name(`.
 * @return string
 */
function opener_function( $js, $signature ) {
	$at = strpos( $js, $signature );
	if ( false === $at ) { return ''; }
	$body = substr( $js, $at );
	return substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );
}
$opens  = opener_function( $opener, 'function openFragment( hash )' );
$target = opener_function( $opener, 'function openTarget()' );
// A Sponsor Dashboard card's anchor is its disclosure's id, and an Administrator Dashboard card's is
// its section's, the disclosure being the section's first child: a fragment naming either opens the
// card, and one naming an element inside a card opens every disclosure around it.
ck( 'it reads the fragment, finds the element it names, and opens that disclosure, the one a section it names opens with, and every disclosure around it, answering the one a section opens with, else the innermost one, whose title takes the focus',
	array(
		false !== strpos( $target, 'openFragment( window.location.hash );' ),
		false !== strpos( $opens, 'target = document.getElementById( id );' ),
		1 === preg_match( "/if \( target && ! isDetails\( target \) && isDetails\( target\.firstElementChild \) \) \{\s*disclosure = target\.firstElementChild;\s*disclosure\.open = true;/", $opens ),
		1 === preg_match( "/for \( node = target; node; node = node\.parentElement \) \{\s*if \( isDetails\( node \) \) \{\s*node\.open = true;\s*disclosure = disclosure \|\| node;/", $opens ),
		1 === preg_match( "/function isDetails\( node \) \{\s*return !! node && 'details' === node\.nodeName\.toLowerCase\(\);/", $opener ),
	),
	array( true, true, true, true, true )
);
// A link to the fragment already in the address fires no hashchange: a tile or a row's action pressed
// after its card was folded again would open nothing. So a click on a link to one of the page's
// anchors, either page's, opens the card itself, through the same opener, with the fragment the link
// names; the browser then follows the link as it always does.
$clicked = opener_function( $opener, 'function openClicked( event )' );
ck( 'and a click on a link to one of the page\'s anchors opens the card too, on either page, since a link to the fragment already in the address fires no hashchange',
	array(
		false !== strpos( $opener, "document.addEventListener( 'click', openClicked );" ),
		false !== strpos( $clicked, "closest( 'a[href^=\"#wpcpm-\"]' )" ),
		false !== strpos( $clicked, "openFragment( link.getAttribute( 'href' ) )" ),
	),
	array( true, true, true )
);
// A row's action is hidden while its card is open, so the press that opens the card hides the link
// pressed, and a browser drops the focus of what it hides to the page. Once the browser has followed
// the link (a task after the press, so its own handling of the focus comes first), the focus goes to
// the card's summary, unless the link is still on screen or the browser gave the focus to something.
$focus = opener_function( $opener, 'function focusCard( disclosure, link )' );
ck( 'after a press that opened a card and hid the link pressed, the focus moves to the card\'s summary, once the browser has followed the link',
	array(
		1 === preg_match( '/window\.setTimeout\( function \(\) \{\s*focusCard\( disclosure, link \);\s*\}, 0 \);/', $clicked ),
		false !== strpos( $focus, 'link.getClientRects().length' ),
		false !== strpos( $focus, 'active !== document.body && active !== link' ),
		false !== strpos( $focus, 'summary.focus( { preventScroll: true } );' ),
		false !== strpos( $focus, "summary.setAttribute( 'tabindex', '-1' );" ),
	),
	array( true, true, true, true, true )
);
// An At a glance tile stays on screen after its card opens, but the browser following its link
// leaves the focus on the page, or on the tile, and not in the card it opened: a link inside the
// strip's list of tiles, on either page, takes the focus to the card's summary as a hidden row
// action does. One condition, beside the hidden link's, so any other link still on screen keeps
// what the browser did.
ck( 'and after a press on an At a glance tile, which stays on screen, the focus moves to the card\'s summary as well: one condition names the strip\'s list of tiles, so any other link still on screen keeps what the browser did',
	array(
		1 === preg_match( "/\\( link\\.getClientRects\\(\\)\\.length && ! link\\.closest\\( '\\.wpcpm-attention__tiles' \\) \\)/", $focus ),
		substr_count( $opener, 'wpcpm-attention__tiles' ),
	),
	array( true, 1 )
);
// A summary that already has a place in the tab order and still did not take the focus keeps its
// place: a tabindex of -1 would take it out of the tab order for good, so the tabindex is given only
// to a summary the browser gave no place of its own.
ck( 'the tabindex that lets a summary take the focus is given only to one with no place in the tab order of its own',
	1 === preg_match( "/if \( document\.activeElement !== summary && summary\.tabIndex < 0 \) \{\s*summary\.setAttribute\( 'tabindex', '-1' \);\s*summary\.focus\( \{ preventScroll: true \} \);/", $focus ),
	true
);
// A press that opens its link somewhere else, a new tab or window (Ctrl, Cmd or Shift held, or a
// button other than the first) or a download (Alt held), is not followed on this page: the opener
// leaves the page as it is, no card opened and no focus moved, and reads a plain press as before.
ck( 'a press that opens the link somewhere else is left to the browser: no card opens on this page and no focus moves',
	array(
		1 === preg_match( "/function elsewhere\( event \) \{\s*return !! \( event\.ctrlKey \|\| event\.metaKey \|\| event\.shiftKey \|\| event\.altKey \) \|\| \( 'number' === typeof event\.button && 0 !== event\.button \);/", $opener ),
		0 === strpos( substr( $clicked, (int) strpos( $clicked, 'var link = ' ) ), "var link = ! elsewhere( event ) && event.target && event.target.closest ? event.target.closest( 'a[href^=\"#wpcpm-\"]' ) : null;" ),
	),
	array( true, true )
);
ck( 'and nothing else: it neither scrolls nor stops the browser from doing so, closes nothing, and depends on no library',
	array( '' !== $opener, strpos( $opener, 'scrollIntoView' ), strpos( $opener, 'scrollTo' ), strpos( $opener, 'preventDefault' ), strpos( $opener, 'jQuery' ), strpos( $opener, '.open = false' ) ),
	array( true, false, false, false, false, false )
);

echo "\n=== Both pages enqueue the opener through the shell ===\n";
$opener_enqueued = static function () {
	$GLOBALS['scripts']              = array();
	$GLOBALS['script_registrations'] = array();
	if ( is_callable( array( 'WPCPM_Dashboard_Card', 'enqueue_opener' ) ) ) {
		WPCPM_Dashboard_Card::enqueue_opener();
	}
	return array( $GLOBALS['scripts'], $GLOBALS['script_registrations'], $GLOBALS['script_src'], $GLOBALS['script_args'] );
};
ck( 'enqueue_opener() registers assets/js/dashboard-open.js once, at the plugin\'s version, in the footer and depending on nothing, and enqueues it', $opener_enqueued(), array(
	array( 'wpcpm-dashboard-open' ),
	array( 'wpcpm-dashboard-open' ),
	array( 'wpcpm-dashboard-open' => WPCPM_PLUGIN_URL . 'assets/js/dashboard-open.js' ),
	array( 'wpcpm-dashboard-open' => array( array(), WPCPM_VERSION, true ) ),
) );
// Something else may register the handle first, as another screen's script can on a site that loads
// both: that registration is the one enqueued, and none is made over it.
$GLOBALS['script_src']  = array( 'wpcpm-dashboard-open' => 'https://example.test/registered-first/dashboard-open.js' );
$GLOBALS['script_args'] = array();
ck( 'and an opener something else registered first is enqueued as it is, and not registered again', $opener_enqueued(), array(
	array( 'wpcpm-dashboard-open' ),
	array(),
	array( 'wpcpm-dashboard-open' => 'https://example.test/registered-first/dashboard-open.js' ),
	array(),
) );
$sponsors_page = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-dashboard.php' );
$admin_page    = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-administrators-dashboard.php' );
ck( 'both dashboards ask the shell for the opener, once, and neither registers the script itself', array(
	substr_count( $sponsors_page, 'WPCPM_Dashboard_Card::enqueue_opener();' ),
	substr_count( $admin_page, 'WPCPM_Dashboard_Card::enqueue_opener();' ),
	substr_count( $sponsors_page . $admin_page, "'wpcpm-dashboard-open'" ),
), array( 1, 1, 0 ) );

echo "\n=== One group of cards, one shape for both pages ===\n";
/**
 * The markup the shell's group printer prints for a group, or '' while there is none.
 *
 * @param array $group What a page asks of the printer.
 * @return string
 */
function group_printed( array $group ) {
	return printed(
		static function () use ( $group ) {
			if ( is_callable( array( 'WPCPM_Dashboard_Card', 'group' ) ) ) {
				WPCPM_Dashboard_Card::group( $group );
			}
		}
	);
}
// The classes the Administrator Dashboard asks for its groups; the Sponsor Dashboard's are the
// printer's own.
$administrator_group = array(
	'group_class' => 'wpcpm-administrator__group',
	'title_class' => 'wpcpm-administrator__group-title',
	'lead_class'  => 'wpcpm-administrator__group-lead',
);
ck( 'a group whose cards print nothing, or nothing but white space, is not printed at all, on either page',
	array(
		group_printed( array( 'id' => 'offer', 'title' => 'What you offer', 'lead' => 'Lead.', 'cards' => static function () {} ) ),
		group_printed( array( 'id' => 'offer', 'title' => 'What you offer', 'lead' => 'Lead.', 'cards' => static function () { echo " \n\t"; } ) ),
		group_printed( array_merge( $administrator_group, array( 'id' => 'watch', 'title' => 'Watch', 'lead' => 'Lead.', 'cards' => static function () {} ) ) ),
		group_printed( array_merge( $administrator_group, array( 'id' => 'watch', 'title' => 'Watch', 'lead' => 'Lead.', 'cards' => static function () { echo " \n\t"; } ) ) ),
	),
	array( '', '', '', '' )
);
ck( 'and one whose cards print something is its wrapper, its heading in the eyebrow the Resources and Updates headings wear, its one sentence in the note voice and its cards: in the Sponsor Dashboard\'s classes unless the page asks for its own, as the Administrator Dashboard does, and every value escaped',
	array(
		group_printed( array( 'id' => 'offer', 'title' => 'What you <b>offer</b>', 'lead' => 'What your company gives & writes.', 'cards' => static function () { echo '<section class="wpcpm-sponsor__card"></section>'; } ) ),
		group_printed( array_merge( $administrator_group, array( 'id' => 'watch', 'title' => 'Watch <b>it</b>', 'lead' => 'What to keep an eye on & more.', 'cards' => static function () { echo '<section class="wpcpm-administrator__card" id="wpcpm-x"></section>'; } ) ) ),
		group_printed( array( 'id' => 'a" onclick="b', 'title' => 'T', 'lead' => 'L', 'group_class' => 'c" d', 'title_class' => 'e"', 'lead_class' => 'f"', 'cards' => static function () { echo 'x'; } ) ),
	),
	array(
		'<div class="wpcpm-sponsor__group wpcpm-sponsor__group--offer"><h2 class="wpcpm-student__heading wpcpm-sponsor__group-title">What you &lt;b&gt;offer&lt;/b&gt;</h2><p class="wpcpm-student__note wpcpm-sponsor__group-lead">What your company gives &amp; writes.</p><section class="wpcpm-sponsor__card"></section></div>',
		'<div class="wpcpm-administrator__group wpcpm-administrator__group--watch"><h2 class="wpcpm-student__heading wpcpm-administrator__group-title">Watch &lt;b&gt;it&lt;/b&gt;</h2><p class="wpcpm-student__note wpcpm-administrator__group-lead">What to keep an eye on &amp; more.</p><section class="wpcpm-administrator__card" id="wpcpm-x"></section></div>',
		'<div class="c&quot; d c&quot; d--a&quot; onclick=&quot;b"><h2 class="wpcpm-student__heading e&quot;">T</h2><p class="wpcpm-student__note f&quot;">L</p>x</div>',
	)
);
ck( 'both dashboards print every group through the shell, the Administrator Dashboard asking for its own classes, and neither prints a group by hand',
	array(
		substr_count( $sponsors_page, 'WPCPM_Dashboard_Card::group(' ),
		substr_count( $admin_page, 'WPCPM_Dashboard_Card::group( array_merge( self::GROUP, $group ) );' ),
		substr_count( $sponsors_page . $admin_page, '__group--%' ) + substr_count( $sponsors_page . $admin_page, '__group-lead">%' ),
		strpos( $admin_page, 'function render_group(' ),
	),
	array( 1, 1, 0, false )
);

echo "\n=== Wiring and house rules ===\n";
$loader    = (string) file_get_contents( __DIR__ . '/../wpcredits-program-manager.php' );
$uninstall = (string) file_get_contents( __DIR__ . '/../uninstall.php' );
$at        = static function ( $src, $needle ) {
	$found = strpos( $src, $needle );
	return false === $found ? -1 : $found;
};
$shell      = "require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboard-card.php';";
$shell_un   = "require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-dashboard-card.php';";
$first_card = array(
	$at( $loader, "includes/modules/class-wpcpm-sponsor-tier-card.php'" ),
	$at( $loader, "includes/modules/class-wpcpm-administrators-cards.php'" ),
);
// Among the plugin's shared classes, after the form stash, the last of them, and so before every
// module, the two whose cards are drawn in it among them.
ck( 'the plugin loads the class among its shared classes, before either page\'s cards, and uninstall.php loads it too',
	array(
		false !== strpos( $loader, "require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-form-stash.php';\n" . $shell . "\n" ),
		$at( $loader, $shell ) >= 0 && $at( $loader, $shell ) < min( $first_card ) && min( $first_card ) >= 0,
		false !== strpos( $uninstall, "require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-form-stash.php';\n" . $shell_un . "\n" ),
		strpos( $loader . $uninstall, 'class-wpcpm-sponsor-card.php' ),
	),
	array( true, true, true, false )
);
ck( 'and the class is no longer one module\'s: its old file is gone', is_file( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-card.php' ), false );
$class_file = __DIR__ . '/../includes/class-wpcpm-dashboard-card.php';
$class_src  = is_file( $class_file ) ? (string) file_get_contents( $class_file ) : '';
ck( 'no em or en dash in the class, the opener or this suite', array( '' !== $class_src, preg_match( '/\x{2013}|\x{2014}/u', $class_src . $opener . (string) file_get_contents( __FILE__ ) ) ), array( true, 0 ) );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', (int) $checks );
exit( $fail ? 1 : 0 );
