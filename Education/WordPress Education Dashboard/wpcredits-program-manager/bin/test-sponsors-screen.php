<?php
/**
 * The Sponsors module: the wp-admin screen, provisioning and members.
 *
 * What each block pins, and why it is worth pinning:
 *
 * - Provisioning creates an account from a sponsor's contact address, or attaches an existing
 *   one at that address; it never provisions two accounts for the same sponsor and never
 *   touches an administrator's account, because a manager already reaches every sponsor.
 * - The provision nonce is keyed to the record (`wpcpm_sponsor_provision_<record>`), so a
 *   token for one sponsor is not a token for another.
 * - The welcome is queued through `WPCPM_Mail::queue_invites()` and never sent from here:
 *   `wp_mail()` is stubbed to record a call precisely so that promise has a check behind it.
 * - `Dashboard account` is written straight to the sponsor's row the moment an account is
 *   created or attached, and cleared the moment a sponsor's last account is detached: nobody
 *   waits for a sync run to see it, because nothing outside the store is being told (1.111.0).
 * - Add and Edit share one set of rules, `clean_fields()`: one value that fails its rule
 *   refuses the whole save, a value identical to the one on the record is kept as it is, and
 *   every save that changes a field is logged by field name, never by value. A status set away
 *   from Approved detaches the accounts when the setting says revoke, and only then. A new name
 *   renames the sponsor's category through the real `WPCPM_Sponsor_Posts`, and keeps its slug.
 * - The forms' typed texts, the name, the contact person, What the sponsorship funds and the notes,
 *   over core's cleaners: counted as typed and refused, never cut, one over the limit, with a
 *   sentence that names the box; drawn back as typed with the shared rules' `maxlength`; and kept
 *   as stored when posted back unedited, a text over its limit and a held `&gt;` included.
 * - On the Edit form, a typing in one of those four boxes that the cleaner would take words from, a
 *   tag-like "<" or a percent octet, refuses the whole save before anything is written, with the
 *   shared sentence naming the box; a box posted back as drawn is not asked and keeps its stored
 *   text, a percent octet included; and every refusal brings the whole form back once as it was
 *   posted, every box as typed and every choice as chosen, to the manager and for the sponsor whose
 *   form it was alone. The flash stand-in keeps each user's channels apart, as the real class does in
 *   user meta.
 * - The Add form keeps those rules for its name, Contact person and notes (it posts no funded line),
 *   its typing kept under its own name and never drawn in a sponsor's Edit form. The Website, on
 *   both forms, is read as typed and cleaned as a web address, as the profile card reads its own:
 *   percent codes such as `%20` and `%C3%B3` are kept, a scheme-less address is still completed, one
 *   with a name before its host still refused, and a stored one posted back as drawn keeps its bytes.
 * - A line break inside a stored name, Contact person, funded line or Website is drawn as a space,
 *   one for each break, and a stored entity in the Website or the Contact email is drawn so that it
 *   posts back as stored: posted back as drawn, each is unedited and keeps its stored bytes, and is
 *   never refused; edited, it is asked as any other. The browser model in `posts_as_drawn()` drops a
 *   text input's line breaks, and an address's or a link's white space at either end, as a browser
 *   does.
 * - The report editor's narrative boxes keep the same rules: a Save that would lose words in one is
 *   refused before anything is stored, the notice naming the box by its label, a track's by the
 *   track; a box posted back as drawn is not asked and keeps its stored text, over its limit too;
 *   and a Save refused for a loss or for a narrative's length brings every box of that report back
 *   once as it was typed, to the manager who typed it alone.
 * - `mark_dashboard_account()` refuses a malformed record and answers false for a key the
 *   store does not hold; it never leaves the site, so there is no client error to hand back.
 * - `activate()` drops the version the badge's rewrite rule was flushed for (1.113.0): the
 *   activation request flushes the rules before the plugin's init has run in it, so the next
 *   request must write them again. It makes the Partners page where there is none, over the real
 *   `WPCPM_Sponsors_Page`, and puts it where the launch switch says: a draft while the switch is
 *   off, published while it is on.
 * - The handbook box, over the real `WPCPM_Sponsors_Listing` (1.113.0), is the Handbook listing
 *   tab's one card, and the tab, the bar's last, is shown only while the launch switch is on:
 *   while it is off the tab is not in the bar, its address opens the queue as the screen's own
 *   address does, and the box's id is on no page, whatever the store holds; and this file still
 *   names none of the three words the house rule bars.
 * - The help under the Tier box's *What the sponsorship funds* says where the line is shown: on the
 *   sponsor's dashboard and in its report, from Backer up, and nowhere public, since the Partners
 *   page lists every partner in the same format (1.123.18); the help is the only word at the field
 *   before it is typed.
 * - A renewal on the Edit form, a save that moves the day off a term whose tier carries a report
 *   before that report exists, leaves the term owed its report on the row, through the real
 *   `WPCPM_Sponsor_Report` (1.115.0), with the funded line the row held before the save; a second
 *   save while the term is still owed keeps the first.
 * - The Tier box's *Extra events this term*, for a tier that includes the co-hosted event alone and
 *   never on the Add form: a whole number from 0 to 11, saved with the term's last day as the save
 *   leaves it and audited by name, anything else refusing the save; a box saved as it was drawn
 *   changes nothing, so a renewal that leaves it alone leaves the number with the term it was
 *   granted for, and the box then holds none for the renewed term and says what the term before was
 *   granted, in words for a term still running, on its last day too, and for one that has ended;
 *   once the term a number was granted for has ended, the box holds none and a renewal typed with
 *   the number grants it to the renewed term in one save; a number saved with no term is not kept;
 *   the refusal names the box; and a save of the number and the notes names them on the trail in
 *   the forms' order. The Edit form reads the number from the row alone, so the box draws on a
 *   checkout without the event store, which this suite runs as.
 * - The annual sponsor report on this screen (1.115.0). The Reports tab, the bar's tab before the
 *   agreements, is the Reports card and no other tab draws it: each Approved sponsor's reports,
 *   newest period first, and the form that drafts one, its day the term's last day, or the owed
 *   term's while a renewal left one; a sponsor whose tier on that day carries no report, a Mentor
 *   sponsor, reads so instead of a form every press would be refused. The editor is that tab's view
 *   of one report, drawn alone in the card's place when the address names a report, with the way
 *   back to the tab, and no other tab draws it: the figures as tables, the narratives as boxes that
 *   are read-only once it is final, and Preview, the PDF, Mark final or Reopen, and Regenerate by
 *   its state. An ID naming no report draws the card under a notice that says so. Its six
 *   handlers each ask for the capability before the nonce, and every outcome is a sentence on this
 *   screen's channel, printed on the tab the press comes back to: a press made in the editor comes
 *   back to the editor, a refused draft and a press naming a report gone to the Reports tab, Not a
 *   renewal to the sponsor's Edit form, or to the Sponsors tab for a sponsor gone. A draft pressed
 *   while another request holds the store's lock for the sponsor writes nothing and says a report is
 *   being drafted. The Tier box's Not a renewal clears an owed term, on the audit trail, and says it
 *   is done, naming the term's last day, which the press carries beside its status for the one
 *   notice; a press with nothing to clear keeps a sentence of its own. The Edit form's address has
 *   one builder, the Sponsors class's, which Not a renewal, the daily run's mail and the
 *   Administrator Dashboard's Terms ending rows call, and the editor's has the report screen's: no
 *   other code under includes/ writes either address. The report's store is the real class, its
 *   figures stood in. A Partner's new draft opens with each
 *   track's box holding the partners' narrative the track was last published with, read through the
 *   Track Builder's real store, and the box of a track published with none empty.
 * - Every handler checks the capability, then the nonce, and only then asks
 *   `WPCPM_Sponsor_Policy::decide()` or changes anything. That order is proved where the handlers
 *   are pressed: bin/test-handlers.php presses every one of the module's handlers by name, by
 *   somebody without the capability and by a manager whose nonce fails, and
 *   bin/test-sponsors-accounts.php presses the Accounts tab's controls with a forged nonce and
 *   finds nothing done before the refusal. This file presses Add, Edit and Run again both ways too,
 *   and reads in each one's body that the check comes before the store is read or written.
 * - The screen is drawn in eight tabs by job while the launch switch is on, the queue first, which
 *   is the tab the screen's own address opens, and Handbook listing last; while the switch is off
 *   the bar holds the other seven. Each tab draws its own cards and no other tab's, and reads only
 *   what it draws (counted by the stand-ins for user queries and post queries, the index's among
 *   them). The Sponsors tab draws the migration box while the move onto the site is incomplete,
 *   then the Sponsors card and nothing of a sync: the table with its Tier column, and the form that
 *   adds a sponsor, which names the tab. The Sponsors card offers no Create account: a sponsor's
 *   account is created on the Accounts tab's No account view. Its last column is each sponsor's
 *   Edit and Manage accounts.
 * - With a sponsor's key in its address the Sponsors tab draws that sponsor's Edit form in place of
 *   its cards, and no other tab draws it: the sponsor's name, the way back to the tab, the form and
 *   the key, reading the index and no account. A key the index does not hold draws the tab's cards
 *   under a notice saying so, and is never printed. The Edit form, Add a sponsor and Run again each
 *   post the Sponsors tab: Add and every save of the Edit form land on the sponsor's Edit form, and a
 *   save for a sponsor gone, a refused Add and Run again land on the tab.
 * - The Accounts tab draws the accounts locked for the day, the invitations card and the sponsor
 *   accounts list, and prints no address. The list itself, its views, its Create account, its row
 *   actions and its invitations are bin/test-sponsors-accounts.php's, but for the sponsors the next
 *   two points name. With a sponsor in its address it draws that sponsor's accounts in their place,
 *   reading the index and that sponsor's accounts alone: the sponsor's name, the way back to the list
 *   as it stood, each account by name and address with Remove, then, for an Approved sponsor, the
 *   Attach account form and the posting switch, and for one that is not, one sentence saying neither
 *   is offered; for a value the index does not hold, that the link names no sponsor on this site and
 *   where a sponsor is added, never the value itself.
 * - A sponsor added on the site, held by the key the site minted for it, is taken by the list as one
 *   that came onto the site with the index is: on the No account view its checkbox posts that key
 *   and its row carries Create account and Manage accounts, and View page too with the Sponsor
 *   Dashboard's page in place, read from the table since this suite never loads the front end;
 *   ticked and pressed through the screen's load hook, Create account makes its account.
 * - A Create account or an Attach account whose sponsor's record cannot then be found to mark as
 *   having an account, the record gone from the store between the press's read of the index and its
 *   write, still makes or attaches the account and queues its welcome. One row's press and Attach
 *   account answer `record-unmarked`, a warning that the record could not be found and that a save
 *   of the sponsor's Edit form marks it if the Sponsors tab lists the sponsor: while the record is
 *   out of the store the tab does not list it, and once the record is back in the store the form's
 *   next save marks it, with nothing else changed too, and logs nothing for it, since no field a
 *   manager owns moved. The ticked sponsors' press counts each such account made or attached and
 *   under `unmarked`, and names those sponsors in a sentence of its own, with how such a record is
 *   marked; a detail that carries the count and no names is counted instead.
 * - Every press comes back to the tab it was made on, and its outcome prints there once, above
 *   the bar; one made on a sponsor's accounts comes back to them, and one that names no sponsor or
 *   another tab does not; the outcome a failed press leaves is worded by that tab, and the sync's
 *   outcomes are none of this screen's. No tab and no sponsor's accounts print a warning about a
 *   connection, even with none, nor name Airtable or a sync: the sponsors are the site's own
 *   records. The words the Sponsors class prints are its own, so its source names neither Airtable
 *   nor the base, and reports no sync error.
 * - The queue tab reads and the Administrator Dashboard decides: the sponsor applications, the
 *   sponsor posts, the sponsors' events while they are in use and the signed agreements waiting,
 *   each in a card of its own in the dashboard's order, each counting everything waiting and
 *   listing no more than the dashboard's card lists, and each item linking to its own item there;
 *   while that page is missing the tab says so once and nothing links. The Events card is titled as
 *   the dashboard's own and lists every event that waits, as that card does, from that card's own
 *   read: each with who planned it, the planners' accounts warmed in one query, how long ago it was
 *   planned and when it takes place, on the clock the time reader takes for itself; it carries the
 *   id the dashboard's Events card and the Overview link to; while the events are off it is not
 *   drawn and nothing of them is read. The menu bubble counts the events waiting while they are in
 *   use, as the dashboard's strip does, and an event's state changing forgets its count. An opened
 *   application draws only the decisions the dashboard cannot reach.
 *   The Agreements tab is every Approved sponsor's standing state; Reinstate is the dashboard's
 *   while its list of agreements out of force holds the document, and this tab's past that list.
 * - Every form the module draws on a tab carries the once attribute (`data-wpcpm-once`). The
 *   invitations card's are the mail layer's, which draws them without it on every audience's screen.
 * - `boot()`'s hooks, the Accounts tab's two invitation handlers and the screen's own load hook,
 *   are bin/test-sponsors-accounts.php's, which boots the module and reads them.
 * - Every reference to `WPCPM_Sponsors_Dashboard`, the Sponsor Dashboard's class, is guarded, so
 *   the screen draws with or without the front end: this suite never defines that class.
 *
 * Run from the plugin root:  php bin/test-sponsors-screen.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

// Core's one query for many options (6.4), which the listing asks before the handbook box's
// entries: nothing to prime in an options table held in memory.
function wp_prime_option_caches( $options ) {}

// Cron, recorded rather than run: schedule_cron() reads wp_next_scheduled(), and deactivate()
// and uninstall() clear hooks, the old sponsors sync's two among them (1.111.0).
function wp_next_scheduled( $hook ) {
	return $GLOBALS['cron'][ $hook ] ?? false;
}
function wp_schedule_event( $when, $recurrence, $hook ) {
	$GLOBALS['cron'][ $hook ] = (int) $when;
	return true;
}
function wp_clear_scheduled_hook( $hook ) {
	unset( $GLOBALS['cron'][ $hook ] );
	return 1;
}

$GLOBALS['opts']          = array();
$GLOBALS['umeta']         = array();
$GLOBALS['users']         = array();
$GLOBALS['nonce_checked'] = array();
$GLOBALS['inserted']      = array();
$GLOBALS['posts']         = array();
$GLOBALS['pmeta']         = array();
$GLOBALS['next_post']     = 900;

class WP_Error {
	private $c, $m;
	public function __construct( $c = '', $m = '', $d = null ) { $this->c = $c; $this->m = $m; }
	public function get_error_message() { return $this->m; }
	public function get_error_code() { return $this->c; }
}

/**
 * Enough of `WP_User` for the real `WPCPM_Sponsor_Members` to run against: `add_role()` and
 * `remove_role()` that mutate the role list without demoting anyone else, and `set_role()` for
 * the account that ends up with no role at all once a sponsor's is removed.
 *
 * The constructor takes the role list before the name and address, matching the fixture below
 * rather than the institutions screen suite's own order: that suite never gives `WP_User` a
 * role at construction time, and this one has to, since the sponsor accounts in its fixture already
 * carry the sponsor role when they are made.
 */
class WP_User {
	// `caps`: the real WPCPM_Sponsor_Posts grants and takes the posting capabilities per account on
	// every attach and detach. Kept, and read by nothing: caps.php answers user_can() from
	// $GLOBALS['manage'] and $GLOBALS['grants'] alone.
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array(), $caps = array();
	public function __construct( $id = 0, array $roles = array(), $name = '', $email = '' ) {
		$this->ID           = (int) $id;
		$this->roles        = $roles;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( str_replace( ' ', '', $name ) );
	}
	public function exists() { return $this->ID > 0; }
	public function add_role( $role ) {
		if ( ! in_array( $role, $this->roles, true ) ) {
			$this->roles[] = $role;
		}
	}
	public function remove_role( $role ) {
		$this->roles = array_values( array_diff( $this->roles, array( $role ) ) );
	}
	public function set_role( $role ) {
		$this->roles = '' === $role ? array() : array( $role );
	}
	public function add_cap( $cap ) { $this->caps[ $cap ] = true; }
	public function remove_cap( $cap ) { unset( $this->caps[ $cap ] ); }
}

/**
 * Enough of `WP_Post` for the post store below: the offers module reads and writes these five
 * properties alone, so nothing else is stood in for it. `post_date` joined them in 1.112.0:
 * the tier ladder's daily run orders a sponsor's published posts by it, and the grace preview's
 * last check gives a sponsor two.
 */
class WP_Post {
	public $ID = 0, $post_type = '', $post_title = '', $post_name = '', $post_status = 'private', $post_author = 0, $post_date = '';
	public function __construct( array $a ) { foreach ( $a as $k => $v ) { $this->$k = $v; } }
}

/**
 * The two outcomes `post()` tells apart. The institutions screen suite this file's other stubs
 * are copied from throws a plain `Exception` for both `wp_die()` and `wp_safe_redirect()` and
 * tells them apart by a string prefix on the message; that will not do here, because `post()`
 * needs two separate `catch` clauses to hand the caller a `die` outcome or a `redirect` one
 * without parsing the message first. Neither exception carries anything beyond its message:
 * `WPCPM_Test_Die` folds the status code into it (`wp_die( $message, 403 )` becomes
 * `"$message [403]"`) since that is the only way a check on the message text can see the code.
 */
class WPCPM_Test_Die extends Exception {}
class WPCPM_Test_Redirect extends Exception {}

function is_wp_error( $t ) { return $t instanceof WP_Error; }
// A translation is the English unless $GLOBALS['l10n'] holds one, so a check can read where a string
// is translated: the tab bar's labels and its name, as the translation tools collect them.
function __( $s, $d = null ) { return isset( $GLOBALS['l10n'][ $s ] ) ? $GLOBALS['l10n'][ $s ] : $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_kses( $s, $a = array() ) { return (string) $s; }
function esc_html__( $s, $d = null ) { return esc_html( __( $s, $d ) ); }
// Core's `esc_attr()`: an entity it finds is kept, only a bare "&" is written `&amp;`. A text input
// drawn through `WPCPM_Typed_Text::attr_text()` shows its held `&gt;` as four characters only so.
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function esc_attr__( $s, $d = null ) { return esc_html( __( $s, $d ) ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $url, $protocols = null ) { return preg_match( '#^https?://#i', (string) $url ) ? $url : ''; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
// Core's two cleaners (bin/stubs/cleaners.php): the Edit form's typed texts are counted, drawn and
// compared by what they write, `&lt;` and the entities after it, which `strip_tags()` writes none of.
require_once __DIR__ . '/stubs/cleaners.php';
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
/**
 * Core's sanitize_email(), as wp-includes/formatting.php writes it: the local part loses every
 * character outside its list, the ";" of an entity among them, so `a&amp;b@example.test` becomes
 * the other address `a&ampb@example.test`; the domain keeps letters, digits and hyphens in two
 * labels or more.
 */
function sanitize_email( $email ) {
	$email = (string) $email;
	if ( strlen( $email ) < 6 || false === strpos( $email, '@', 1 ) ) { return ''; }
	list( $local, $domain ) = explode( '@', $email, 2 );
	$local = preg_replace( '/[^a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]/', '', $local );
	if ( '' === $local ) { return ''; }
	$domain = trim( preg_replace( '/\.{2,}/', '', $domain ), " \t\n\r\0\x0B." );
	$subs   = array();
	foreach ( explode( '.', $domain ) as $sub ) {
		$sub = preg_replace( '/[^a-z0-9-]+/i', '', trim( $sub, " \t\n\r\0\x0B-" ) );
		if ( '' !== $sub ) { $subs[] = $sub; }
	}
	return count( $subs ) < 2 ? '' : $local . '@' . implode( '.', $subs );
}
function sanitize_user( $u, $strict = false ) { return preg_replace( '/[^a-z0-9 _.\-@]/i', '', (string) $u ); }
function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function apply_filters( $t, $v ) { return $v; }
function add_action( $h, $c, $p = 10, $n = 1 ) {}
function add_filter() {}
function register_post_type( $type, $args ) {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
// The menu bubble's cache (1.98.1), in the shape of bin/test-form-stash.php's transient store
// rather than a new one.
$GLOBALS['transients'] = array();
function set_transient( $k, $v, $ttl ) { $GLOBALS['transients'][ $k ] = array( 'v' => $v, 'ttl' => $ttl ); return true; }
function get_transient( $k ) { return isset( $GLOBALS['transients'][ $k ] ) ? $GLOBALS['transients'][ $k ]['v'] : false; }
function delete_transient( $k ) { unset( $GLOBALS['transients'][ $k ] ); return true; }
/** The pool's lock and WPCPM_Secret's key both need the test-and-set add_option() makes: the
 * write only happens when the row does not exist yet, which update_option() alone cannot tell. */
function add_option( $k, $v, $deprecated = '', $autoload = 'yes' ) {
	if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; }
	$GLOBALS['opts'][ $k ] = $v;
	return true;
}
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
/** delete_metadata( 'user', 0, $key, '', true ) is how uninstall() clears a meta key across
 * every account; only that shape is exercised here, so only it is stood in for. */
function delete_metadata( $type, $object_id, $key, $value = '', $delete_all = false ) {
	$GLOBALS['deleted_meta'][] = $key;
	if ( $delete_all ) {
		foreach ( array_keys( $GLOBALS['umeta'] ) as $id ) {
			unset( $GLOBALS['umeta'][ $id ][ $key ] );
		}
	} else {
		unset( $GLOBALS['umeta'][ (int) $object_id ][ $key ] );
	}
	return true;
}
function get_user_by( $field, $value ) {
	foreach ( $GLOBALS['users'] as $user ) {
		if ( 'email' === $field && strtolower( $user->user_email ) === strtolower( (string) $value ) ) { return $user; }
		if ( 'id' === $field && $user->ID === (int) $value ) { return $user; }
		if ( 'login' === $field && $user->user_login === (string) $value ) { return $user; }
	}
	return false;
}
/**
 * An account read by its ID, and core's warm-up of many accounts in one query, each kept in order in
 * `$GLOBALS['account_reads']`, so a check can see a list warm the accounts it names in one query
 * before it reads each. Declared before bin/stubs/posts.php, whose reader of an account by its ID
 * this one is, with the log.
 */
function get_userdata( $id ) {
	$GLOBALS['account_reads'][] = 'read ' . (int) $id;
	return isset( $GLOBALS['users'][ (int) $id ] ) ? $GLOBALS['users'][ (int) $id ] : false;
}
function cache_users( $user_ids ) { $GLOBALS['account_reads'][] = 'cache ' . implode( ',', array_map( 'intval', (array) $user_ids ) ); }
/** `get_users()` by meta key and value, and everyone for a call that names neither. */
/**
 * Enough of `$wpdb` for `WPCPM_Sponsors_Index::delete_all()`'s one raw query: a LIKE-prefix
 * DELETE against the options table it keeps in `$GLOBALS['opts']`, and for
 * `WPCPM_Sponsor_Approval::delete_all()`'s LIKE-prefix SELECT of the lock rows, which
 * `uninstall()` reaches for real once this suite has loaded that class; and for the read of its
 * own row a sponsor report's lock takes (bin/stubs/options-table.php).
 */
class WPCPM_Test_DB {
	public $options = 'wp_options';
	public function prepare( $sql, ...$args ) { return vsprintf( str_replace( '%s', "'%s'", $sql ), $args ); }
	public function get_var( $sql ) { return wpcpm_test_option_row( $sql ); }
	public function esc_like( $s ) { return addcslashes( (string) $s, '_%\\' ); }
	public function get_col( $sql ) {
		$out = array();

		if ( preg_match( "/LIKE '(.*)%'\$/", $sql, $m ) ) {
			$prefix = str_replace( array( '\\_', '\\%', '\\\\' ), array( '_', '%', '\\' ), $m[1] );
			foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
				if ( 0 === strpos( $name, $prefix ) ) { $out[] = $name; }
			}
		}

		return $out;
	}
	public function query( $sql ) {
		if ( preg_match( "/LIKE '(.*)%'\$/", $sql, $m ) ) {
			$prefix = str_replace( array( '\\_', '\\%', '\\\\' ), array( '_', '%', '\\' ), $m[1] );
			foreach ( array_keys( $GLOBALS['opts'] ) as $name ) {
				if ( 0 === strpos( $name, $prefix ) ) { unset( $GLOBALS['opts'][ $name ] ); }
			}
		}
		return true;
	}
}
$GLOBALS['wpdb'] = new WPCPM_Test_DB();
require_once __DIR__ . '/stubs/options-table.php';
function get_users( $args = array() ) {
	// Counted by the meta key asked for, so a check can say which tab read the live accounts.
	$asked = isset( $args['meta_key'] ) ? (string) $args['meta_key'] : '';
	$GLOBALS['user_queries'][ $asked ] = ( isset( $GLOBALS['user_queries'][ $asked ] ) ? (int) $GLOBALS['user_queries'][ $asked ] : 0 ) + 1;
	$out = array();
	foreach ( $GLOBALS['users'] as $id => $user ) {
		if ( ! isset( $args['meta_key'] ) ) { $out[] = $user; continue; }
		$value = $GLOBALS['umeta'][ (int) $id ][ $args['meta_key'] ] ?? null;
		if ( null !== $value && 0 === strcasecmp( (string) $value, (string) ( $args['meta_value'] ?? '' ) ) ) { $out[] = $user; }
	}
	return $out;
}
function username_exists( $login ) { $u = get_user_by( 'login', $login ); return $u ? $u->ID : false; }
// How a meta query is read, the one reading every stand-in user query shares; it notes in
// `$GLOBALS['unmodeled']` anything it is asked that it does not model.
$GLOBALS['unmodeled'] = array();
require_once __DIR__ . '/stubs/meta-matcher.php';
/**
 * The Accounts tab's list of sponsor accounts and its views' counts: the role, the view's meta query
 * read by the matcher above, the IDs a search hands in (`include`) and a contains search over the
 * columns it names, ordered by name and then by ID, the order a list nobody sorted is in, which is
 * the one this suite draws; a page of them, or IDs when `fields` asks; and the total. Each call is
 * kept, so a check can say which tab read the list. The list itself, paged, searched and sorted, is
 * bin/test-sponsors-accounts.php's.
 */
class WP_User_Query {
	private $results = array();
	private $total   = 0;
	public function __construct( $args = array() ) {
		$GLOBALS['list_queries'][] = $args;
		$include                   = ! empty( $args['include'] ) ? array_map( 'intval', (array) $args['include'] ) : null;
		$found                     = array();
		foreach ( $GLOBALS['users'] as $id => $user ) {
			if ( ! empty( $args['role'] ) && ! in_array( $args['role'], $user->roles, true ) ) { continue; }
			if ( null !== $include && ! in_array( (int) $id, $include, true ) ) { continue; }
			if ( ! wpcpm_stub_meta_matches( (int) $id, isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array() ) ) { continue; }
			if ( ! empty( $args['search'] ) ) {
				$term = trim( (string) $args['search'], '*' );
				$hit  = false;
				foreach ( isset( $args['search_columns'] ) ? (array) $args['search_columns'] : array() as $column ) {
					$hit = $hit || false !== stripos( (string) $user->$column, $term );
				}
				if ( ! $hit ) { continue; }
			}
			$found[] = $user;
		}
		usort( $found, static function ( $a, $b ) { $order = strcasecmp( $a->display_name, $b->display_name ); return 0 !== $order ? $order : $a->ID - $b->ID; } );
		$this->total = count( $found );
		if ( isset( $args['number'] ) && (int) $args['number'] > 0 ) {
			$found = array_slice( $found, isset( $args['offset'] ) ? (int) $args['offset'] : 0, (int) $args['number'] );
		}
		if ( isset( $args['fields'] ) && 'ID' === $args['fields'] ) {
			$found = array_map( static function ( $user ) { return (string) $user->ID; }, $found );
		}
		$this->results = array_values( $found );
	}
	public function get_results() { return $this->results; }
	public function get_total() { return $this->total; }
}
// What else the Accounts tab's list reaches of WordPress: its arguments, its rows-per-page choice,
// the names it orders, its view's label held until the count is known, the empty row, whether an
// account holds a stamp, and the account's editor.
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function get_user_option( $option, $user = 0 ) { return $GLOBALS['umeta'][ $user ? (int) $user : $GLOBALS['uid'] ][ $option ] ?? false; }
function remove_accents( $text, $locale = '' ) { return (string) $text; }
function esc_html_e( $s, $d = null ) { echo esc_html__( $s, $d ); }
function _n_noop( $singular, $plural, $domain = null ) { return array( 0 => $singular, 1 => $plural, 'singular' => $singular, 'plural' => $plural, 'context' => null, 'domain' => $domain ); }
function translate_nooped_plural( $nooped, $count, $domain = 'default' ) { return __( _n( $nooped['singular'], $nooped['plural'], $count ) ); }
function metadata_exists( $type, $id, $key ) { return 'user' === $type && isset( $GLOBALS['umeta'][ (int) $id ] ) && array_key_exists( $key, $GLOBALS['umeta'][ (int) $id ] ); }
function get_edit_user_link( $id ) { return 'https://example.test/wp-admin/user-edit.php?user_id=' . (int) $id; }
// And what the screen's load hook reaches before it hands a press in the list to the table: the
// screen the list's headings are named on, none here; the rows-per-page choice under Screen Options;
// and core's version, the one the plugin is tested up to (readme.txt), whose list table
// stubs/class-wp-list-table.php follows: from 6.7 on core names a list's Apply button, so a press
// of it sends `bulk_action` here as a browser sends it there. Anything else asked of get_bloginfo()
// is noted in `$GLOBALS['unmodeled']`, the last check's, as stubs/accounts-screen.php notes it.
function get_current_screen() { return null; }
function add_screen_option( $option, $args = array() ) {}
function get_bloginfo( $show = '', $filter = 'raw' ) {
	if ( 'version' === $show ) {
		return '7.1';
	}
	$GLOBALS['unmodeled'][] = 'get_bloginfo() ' . $show;
	return '';
}
function wp_generate_password( $l = 12, $s = true, $e = false ) { return substr( str_repeat( md5( (string) mt_rand() ), 2 ), 0, (int) $l ); }
function wp_mail( $to, $subject, $message = '', $headers = '', $attachments = array() ) {
	// Never expected to run: provisioning and attaching both queue through WPCPM_Mail::queue_invites()
	// rather than calling this, and a check later asserts $GLOBALS['mailed'] was never touched.
	$GLOBALS['mailed'][] = array( $to, $subject );
	return true;
}
// The post store: enough of posts, meta and get_posts() for the offers to live in. With
// `$GLOBALS['insert_fails']` set it refuses as core does, for the sponsor report the site could not
// store (1.115.0).
function wp_insert_post( array $args, $wp_error = false ) {
	if ( ! empty( $GLOBALS['insert_fails'] ) ) {
		return $wp_error ? new WP_Error( 'db_insert_error', 'Could not insert post into the database.' ) : 0;
	}
	$id = $GLOBALS['next_post']++;
	$GLOBALS['posts'][ $id ] = new WP_Post( array( 'ID' => $id, 'post_type' => $args['post_type'] ?? 'post', 'post_title' => $args['post_title'] ?? '', 'post_name' => $args['post_name'] ?? '', 'post_status' => $args['post_status'] ?? 'publish', 'post_author' => $args['post_author'] ?? 0 ) );
	return $id;
}
// With `$GLOBALS['update_fails']` set it refuses as core does, for a sponsor report's save whose
// version WordPress would not take (1.115.0).
function wp_update_post( array $args, $wp_error = false ) { if ( ! empty( $GLOBALS['update_fails'] ) ) { return $wp_error ? new WP_Error( 'invalid_post', 'Invalid post ID.' ) : 0; } $id = (int) $args['ID']; if ( isset( $GLOBALS['posts'][ $id ] ) ) { foreach ( array( 'post_title', 'post_name', 'post_status' ) as $field ) { if ( isset( $args[ $field ] ) ) { $GLOBALS['posts'][ $id ]->$field = $args[ $field ]; } } } return $id; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['pmeta'][ (int) $id ] ); return true; }
// The Partners page (1.113.0), which activate() makes where there is none and puts where the launch
// switch says: a post's status, core's lookup of a page at a slug, and the addresses the page's
// content links to.
define( 'OBJECT', 'OBJECT' );
function get_post_status( $id ) { return isset( $GLOBALS['posts'][ (int) $id ] ) ? $GLOBALS['posts'][ (int) $id ]->post_status : false; }
function get_page_by_path( $slug, $output = OBJECT, $post_type = 'page' ) { foreach ( $GLOBALS['posts'] as $post ) { if ( $slug === $post->post_name && in_array( $post->post_type, (array) $post_type, true ) ) { return $post; } } return null; }
function get_permalink( $id ) { return isset( $GLOBALS['posts'][ (int) $id ] ) ? 'https://example.test/' . $GLOBALS['posts'][ (int) $id ]->post_name . '/' : false; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function wp_delete_attachment( $id, $force = false ) { $GLOBALS['deleted_attachments'][] = (int) $id; return true; }
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['pmeta'][ (int) $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['pmeta'][ (int) $id ][ $k ] = $v; return true; }
// Post meta here keeps what it is handed, so the slash is the identity (bin/test-sponsor-offers.php
// holds the offers' writes to core's, whose meta unslashes).
function wp_slash( $v ) { return $v; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['pmeta'][ (int) $id ][ $k ] ); return true; }
// Run again's migration clears a key from every post when it completes.
function delete_post_meta_by_key( $k ) { $found = false; foreach ( array_keys( $GLOBALS['pmeta'] ) as $id ) { if ( array_key_exists( $k, $GLOBALS['pmeta'][ $id ] ) ) { unset( $GLOBALS['pmeta'][ $id ][ $k ] ); $found = true; } } return $found; }
function get_posts( array $args ) {
	$GLOBALS['queries'] = ( isset( $GLOBALS['queries'] ) ? (int) $GLOBALS['queries'] : 0 ) + 1;
	// And by the post type asked for, so a check can say which tab read the applications, and which
	// read the sponsors index, whose sponsors are posts of a type of their own.
	$type = isset( $args['post_type'] ) ? implode( ',', (array) $args['post_type'] ) : '';
	$GLOBALS['post_queries'][ $type ] = ( isset( $GLOBALS['post_queries'][ $type ] ) ? (int) $GLOBALS['post_queries'][ $type ] : 0 ) + 1;
	$out = array();
	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( isset( $args['post_type'] ) && $post->post_type !== $args['post_type'] ) { continue; }
		// post_status can be a single status or a list, as core's own get_posts() takes it (the
		// store's post_id_of() and delete_all() pass a list, naming every status, in place of
		// 'any').
		if ( isset( $args['post_status'] ) && 'any' !== $args['post_status'] && ! in_array( $post->post_status, (array) $args['post_status'], true ) ) { continue; }
		$ok = true;
		foreach ( (array) ( $args['meta_query'] ?? array() ) as $clause ) {
			if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
			$have = array_key_exists( $clause['key'], $GLOBALS['pmeta'][ $id ] ?? array() );
			if ( isset( $clause['compare'] ) && 'EXISTS' === $clause['compare'] ) { if ( ! $have ) { $ok = false; } continue; }
			// The application queue asks for several states at once.
			if ( isset( $clause['compare'] ) && 'IN' === $clause['compare'] ) { if ( ! $have || ! in_array( (string) $GLOBALS['pmeta'][ $id ][ $clause['key'] ], array_map( 'strval', (array) $clause['value'] ), true ) ) { $ok = false; } continue; }
			if ( ! $have || (string) $GLOBALS['pmeta'][ $id ][ $clause['key'] ] !== (string) $clause['value'] ) { $ok = false; }
		}
		// The store (1.111.0) asks a bare meta_key/meta_value pair, not meta_query.
		if ( $ok && isset( $args['meta_key'] ) && array_key_exists( 'meta_value', $args ) ) {
			$have = get_post_meta( $id, $args['meta_key'], true );
			if ( (string) $have !== (string) $args['meta_value'] ) { $ok = false; }
		}
		if ( $ok ) { $out[] = $post; }
	}
	// decided_posts() (1.98.1) orders by a meta value instead of the post ID; this is the one
	// other shape this class asks for, so it is the one other shape this stub answers.
	if ( isset( $args['orderby'] ) && 'meta_value_num' === $args['orderby'] && isset( $args['meta_key'] ) ) {
		$meta_key = $args['meta_key'];
		$desc     = isset( $args['order'] ) && 'DESC' === $args['order'];
		usort( $out, static function ( $a, $b ) use ( $meta_key, $desc ) {
			$by_meta = (int) get_post_meta( $a->ID, $meta_key, true ) - (int) get_post_meta( $b->ID, $meta_key, true );
			return $desc ? -$by_meta : $by_meta;
		} );
	} else {
		usort( $out, static function ( $a, $b ) { return $a->ID - $b->ID; } );
	}
	if ( isset( $args['numberposts'] ) && (int) $args['numberposts'] > 0 ) {
		$out = array_slice( $out, 0, (int) $args['numberposts'] );
	}
	// The IDs alone, as core answers `'fields' => 'ids'`: an opened application reads its place
	// among the open ones that way.
	if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
		return array_map( static function ( $post ) { return $post->ID; }, $out );
	}
	return $out;
}
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	$GLOBALS['nonce_checked'][] = $action;
	if ( ! $GLOBALS['nonce_ok'] ) {
		wp_die( 'The link you followed has expired.' );
	}
	return true;
}
function wp_die( $message = '', $code = 0 ) {
	throw new WPCPM_Test_Die( (string) $message . ( $code ? ' [' . (int) $code . ']' : '' ) );
}
function wp_safe_redirect( $location, $status = 302 ) {
	throw new WPCPM_Test_Redirect( (string) $location );
}
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
// Both shapes core accepts: a map of arguments and an address, which the tab bar and the queue's
// links use, and one key, its value and an address, which the way back to a tab uses (`tab_url()`).
// Nothing to add leaves the address as it was, as core's does: the way back to the accounts list
// adds where the list stood, which is nothing when it stood at its start.
function add_query_arg( $args, $url = '', $third = '' ) {
	if ( ! is_array( $args ) ) {
		$args = array( (string) $args => $url );
		$url  = $third;
	}
	if ( empty( $args ) ) {
		return $url;
	}
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args );
}
function wp_nonce_url( $url, $a ) { return $url . '&_wpnonce=' . rawurlencode( $a ); }
// The nonce field as core prints it: the nonce, and beside it, unless the caller asks for none, the
// address of the request that drew the form, which a handler that follows its referer goes back to.
function wp_nonce_field( $a = '', $n = '', $r = true, $e = true ) {
	echo '<input type="hidden" name="_wpnonce" value="nonce-' . esc_attr( $a ) . '" />';
	if ( $r ) {
		echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ) . '" />';
	}
}
function wp_create_nonce( $a = '' ) { return 'nonce'; }
function submit_button( $text, $type = 'primary', $name = 'submit', $wrap = true, $other = array() ) {
	$attrs = '';
	foreach ( (array) $other as $key => $value ) { $attrs .= ' ' . $key . '="' . esc_attr( $value ) . '"'; }
	printf( '<button type="submit" class="button button-%s" name="%s"%s>%s</button>', $type, $name, $attrs, esc_html( $text ) );
}
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function size_format( $b, $d = 0 ) { return (int) $b . ' B'; }
/** How long ago, in words: what a check mapped for the moment it is given, else "4 hours", the words every other check reads. */
function human_time_diff( $a, $b = 0 ) { return isset( $GLOBALS['ago'][ (int) $a ] ) ? $GLOBALS['ago'][ (int) $a ] : '4 hours'; }
// No timestamp is now, as in core: Add stamps a new sponsor's created day with wp_date( 'Y-m-d' ),
// and a stand-in that answered 1970-01-01 would let that check pass without proving the day. The
// grace preview's checks (1.112.0) pin today alone, through $GLOBALS['today'], and unset it after.
function wp_date( $format, $ts = null, $zone = null ) { return null === $ts && 'Y-m-d' === $format && isset( $GLOBALS['today'] ) ? $GLOBALS['today'] : gmdate( $format, null === $ts ? time() : (int) $ts ); }
function get_post_time( $format = 'U', $gmt = false, $post = null, $translate = false ) { return 1757000000; }
function wp_get_attachment_image( $id, $size = 'medium', $icon = false, $attr = array() ) { return '<img class="wpcpm-test-logo" data-id="' . (int) $id . '" src="https://example.test/uploads/' . (int) $id . '.png" alt="" />'; }
function esc_textarea( $s ) { return esc_html( $s ); }
// WPCPM_Sponsors_Listing's own calls, for the handbook box: no fixture here gives a sponsor a
// logo, so the attachment lookup answers as it does for an id nothing resolves to, and the
// listing's remove_accents() is the passthrough above, since no fixture name in this file carries
// an accent.
function wp_get_attachment_image_src( $id, $size = 'thumbnail', $icon = false ) { return false; }
function wp_list_pluck( $list, $field ) { return array_column( (array) $list, $field ); }

class WPCPM_Roles {
	const ROLE_STUDENT = 'wpcpm_student'; const ROLE_MENTOR = 'wpcpm_mentor'; const ROLE_INSTITUTION = 'wpcpm_institution'; const ROLE_SPONSOR = 'wpcpm_sponsor'; const ROLE_ADMIN = 'administrator'; const CAP_MANAGE = 'wpcpm_manage_program';
	public static function user_has_role( $user, $role ) { return $user instanceof WP_User && in_array( $role, $user->roles, true ); }
	public static function resolve_user( $user = null ) { if ( null === $user ) { return wp_get_current_user(); } return $user instanceof WP_User ? $user : get_user_by( 'id', $user ); }
	public static function insert_user( array $data ) { $id = 100 + count( $GLOBALS['users'] ); $u = new WP_User( $id, array( $data['role'] ), $data['display_name'], $data['user_email'] ); $u->user_login = $data['user_login']; $GLOBALS['users'][ $id ] = $u; $GLOBALS['inserted'][] = $data; return $id; }
}
// The stamps an invitation leaves, named once for every stand-in mail class.
require_once __DIR__ . '/stubs/stamps.php';
/**
 * The mail layer, as far as the screen reaches it: the queue provisioning puts a welcome in, the
 * masked address, and the invitations as the Accounts tab's frame reaches them: the stamps the
 * list's views read, the words a row's invitation leaves, its failure's among them, who was never
 * sent one (the accounts holding the role with no stamp), and the card, drawn as the real one draws
 * its button, a form posting the action and the fields the module hands it, without the once
 * attribute. The invitations themselves and the card's own words are
 * bin/test-sponsors-accounts.php's, against the real class.
 */
class WPCPM_Mail {
	const STAMPS = WPCPM_STUB_STAMPS;
	public static function queue_invites( array $ids ) { $GLOBALS['queued'] = array_merge( isset( $GLOBALS['queued'] ) ? $GLOBALS['queued'] : array(), $ids ); return count( $ids ); }
	public static function mask_address( $a ) { return substr( $a, 0, 1 ) . '***' . strstr( $a, '@' ); }
	public static function invite_notices() {
		return array(
			'invited'         => array( 'success', 'Invitation email sent.' ),
			'invite-too-soon' => array( 'warning', 'Nothing was sent: too soon.' ),
		);
	}
	public static function invite_failed_message() {
		return 'The invitation could not be sent.';
	}
	/** Nobody's invitation held here: bin/test-sponsors-accounts.php plays the held account. */
	public static function held_back( $role, $meta ) {
		return array();
	}
	public static function never_invited( $role, $meta ) {
		$GLOBALS['never_invited_asked'][] = array( $role, $meta );
		$never                            = array();
		foreach ( $GLOBALS['users'] as $id => $user ) {
			if ( in_array( $role, $user->roles, true ) && empty( array_intersect_key( isset( $GLOBALS['umeta'][ $id ] ) ? $GLOBALS['umeta'][ $id ] : array(), array_flip( self::STAMPS ) ) ) ) {
				$never[] = (int) $id;
			}
		}
		return $never;
	}
	public static function render_invite_card( array $args ) {
		echo '<div class="wpcpm-card wpcpm-invites"><h2>Invitations</h2>';
		if ( ! empty( $args['pending'] ) ) {
			echo '<form method="post" action="https://example.test/wp-admin/admin-post.php">';
			printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $args['action'] ) );
			foreach ( (array) $args['hidden'] as $name => $value ) {
				printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( $name ), esc_attr( $value ) );
			}
			printf( '<button type="submit">%s</button></form>', esc_html( sprintf( translate_nooped_plural( $args['button'], count( $args['pending'] ) ), count( $args['pending'] ) ) ) );
		}
		echo '</div>';
	}
}
class WPCPM_Mentors_Sync {
	public static function is_record_id( $v ) { return 1 === preg_match( '/^rec[A-Za-z0-9]{14}$/', (string) $v ); }
	public static function sponsorship() { return isset( $GLOBALS['sponsorship'] ) ? $GLOBALS['sponsorship'] : array(); }
}
class WPCPM_Students_Sync {
	const META_RECORD_ID = 'wpcpm_student_record_id';
	public static function get_program( $user_id ) { return array(); }
}
class WPCPM_Institutions {
	// The Administrator Dashboard's channel, which a sponsor report's draft refused there flashes on (1.115.0).
	const FLASH = 'institutions';
	public static function notify_managers( $context, $build, $key = 'agreement_notify' ) { $GLOBALS['sent'][] = array( 'managers', $key, $context, call_user_func( $build, null ) ); return 1; }
	/** The real one's answer over this file's accounts: an address, and the program capability through the shared stand-in, by ID. */
	public static function managers() { $out = array(); foreach ( $GLOBALS['users'] as $user ) { if ( '' !== trim( (string) $user->user_email ) && user_can( $user, 'wpcpm_manage_program' ) ) { $out[ $user->ID ] = $user; } } ksort( $out ); return array_values( $out ); }
}
class WPCPM_Institution_Members { const META_RECORD_ID = 'wpcpm_institution_record_id'; const META_ACTIVE = 'wpcpm_institution_active'; public static function institution_of( $user = null ) { return ''; } }
class WPCPM_Institution_Audit {
	const GROUND_MANAGER = 'manager'; const GROUND_MEMBER = 'member'; const GROUND_SYSTEM = 'system'; const EVIDENCE_INDEX = 'index'; const EVIDENCE_CACHE = 'cache';
	public static function record_sponsor( array $e ) { $GLOBALS['audit'][] = $e; return count( $GLOBALS['audit'] ); }
	public static function sponsor_entries( $kind = '', $limit = 50 ) { $out = array(); foreach ( array_reverse( $GLOBALS['audit'] ) as $i => $e ) { if ( '' !== $kind && $e['kind'] !== $kind ) { continue; } $out[] = array_merge( array( 'id' => $i, 'actor' => 0, 'time' => 1757000000 + $i, 'message' => '', 'data' => array() ), $e ); } return array_slice( $out, 0, $limit ); }
}
class WPCPM_Ceiling { public static function init() {} public static function claim( $k, $l, $w, $a = 1 ) { return true; } public static function count( $k, $w ) { return isset( $GLOBALS['ceiling_counts'][ $k ] ) ? (int) $GLOBALS['ceiling_counts'][ $k ] : 0; } public static function key( ...$p ) { return implode( ':', $p ); } }
/**
 * The flash channels as the real class keeps them: what a press sets waits for the next page, and
 * what a page takes is remembered for the rest of that page, so a second printer on the same channel
 * reads the same value again and a notice printed twice is seen. The memory is forgotten at the start
 * of each press and each page draw (`post()`, `render_screen()`), as a new request starts with
 * nothing taken.
 */
class WPCPM_Flash {
	/** @var array<string, mixed> User and channel => what this request took from it. */
	public static $taken = array();
	public static function set( $k, $v, $user_id = 0 ) { $GLOBALS['flash'][ self::user( $user_id ) ][ (string) $k ] = $v; }
	public static function take( $k, $user_id = 0 ) {
		$uid  = self::user( $user_id );
		$memo = $uid . '|' . $k;
		if ( array_key_exists( $memo, self::$taken ) ) {
			return self::$taken[ $memo ];
		}
		$v = isset( $GLOBALS['flash'][ $uid ][ $k ] ) ? $GLOBALS['flash'][ $uid ][ $k ] : '';
		unset( $GLOBALS['flash'][ $uid ][ $k ] );
		if ( empty( $GLOBALS['flash'][ $uid ] ) ) {
			unset( $GLOBALS['flash'][ $uid ] );
		}
		self::$taken[ $memo ] = $v;
		return $v;
	}
	/** Clear, unread, every channel of the user the test picks, as the real class does. */
	public static function sweep( $stale, $user_id = 0 ) {
		$uid = self::user( $user_id );
		foreach ( isset( $GLOBALS['flash'][ $uid ] ) ? $GLOBALS['flash'][ $uid ] : array() as $k => $v ) {
			if ( $stale( (string) $k, $v ) ) {
				unset( $GLOBALS['flash'][ $uid ][ $k ] );
			}
		}
		if ( empty( $GLOBALS['flash'][ $uid ] ) ) {
			unset( $GLOBALS['flash'][ $uid ] );
		}
	}
	/** A new request: nothing taken yet. */
	public static function new_request() { self::$taken = array(); }
	/** Each person's flash is their own, kept in their user meta: the given user, or the current one. */
	private static function user( $user_id ) { return (int) ( $user_id ? $user_id : $GLOBALS['uid'] ); }
}
/** What waits in the flash for anybody: every user's channels together, channel => value. */
function flash_left() {
	$left = array();
	foreach ( isset( $GLOBALS['flash'] ) ? (array) $GLOBALS['flash'] : array() as $channels ) {
		$left = array_merge( $left, (array) $channels );
	}
	return $left;
}
class WPCPM_Request {
	public static function posted_text( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) && is_scalar( $GLOBALS['post'][ $n ] ) ? trim( sanitize_text_field( (string) $GLOBALS['post'][ $n ] ) ) : $f; }
	public static function posted_lines( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) && is_scalar( $GLOBALS['post'][ $n ] ) ? trim( sanitize_textarea_field( (string) $GLOBALS['post'][ $n ] ) ) : $f; }
	// As typed: neither cleaned nor trimmed (the suite's posts carry no slashes to take off).
	public static function posted_raw( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) && is_scalar( $GLOBALS['post'][ $n ] ) ? (string) $GLOBALS['post'][ $n ] : $f; }
	// As the real reader keeps a code or a link: invalid UTF-8 read as nothing, control characters
	// dropped, the ends trimmed, and every percent code and every "<" left as typed.
	public static function posted_verbatim( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) && is_scalar( $GLOBALS['post'][ $n ] ) ? trim( (string) preg_replace( '/[^\P{C}\n\r\t]+/u', '', mb_check_encoding( (string) $GLOBALS['post'][ $n ], 'UTF-8' ) ? (string) $GLOBALS['post'][ $n ] : '' ) ) : $f; }
	public static function posted_list( $n, $pattern ) { $out = array(); foreach ( isset( $GLOBALS['post'][ $n ] ) && is_array( $GLOBALS['post'][ $n ] ) ? $GLOBALS['post'][ $n ] : array() as $v ) { if ( is_string( $v ) && 1 === preg_match( $pattern, $v ) ) { $out[ $v ] = true; } } return array_map( 'strval', array_keys( $out ) ); }
	public static function posted_key( $n, $f = '' ) { return isset( $GLOBALS['post'][ $n ] ) ? preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $GLOBALS['post'][ $n ] ) ) : $f; }
	public static function posted_id( $n ) { return isset( $GLOBALS['post'][ $n ] ) ? (int) $GLOBALS['post'][ $n ] : 0; }
	public static function text( $n, $f = '' ) { return isset( $GLOBALS['get'][ $n ] ) ? trim( (string) $GLOBALS['get'][ $n ] ) : $f; }
	public static function key( $n, $f = '' ) { return isset( $GLOBALS['get'][ $n ] ) ? (string) $GLOBALS['get'][ $n ] : $f; }
	public static function id( $n ) { return isset( $GLOBALS['get'][ $n ] ) ? (int) $GLOBALS['get'][ $n ] : 0; }
	// The rows ticked in the accounts list's form, each kept only when the pattern matches the whole
	// of it, as the real reader keeps them.
	public static function list( $n, $pattern ) { $out = array(); foreach ( isset( $GLOBALS['get'][ $n ] ) && is_array( $GLOBALS['get'][ $n ] ) ? $GLOBALS['get'][ $n ] : array() as $v ) { if ( is_string( $v ) && 1 === preg_match( $pattern, $v, $m ) && $m[0] === $v ) { $out[ $v ] = true; } } return array_map( 'strval', array_keys( $out ) ); }
}
// Connected unless a check says otherwise: the checks that draw the screen with no connection clear
// `connected`, and find nothing on it that asks about one.
class WPCPM_Settings { public static function get() { return $GLOBALS['settings']; } public static function get_value( $k, $d = null ) { return isset( $GLOBALS['settings'][ $k ] ) ? $GLOBALS['settings'][ $k ] : $d; } public static function is_connected() { return ! isset( $GLOBALS['connected'] ) || (bool) $GLOBALS['connected']; } }
class WPCPM_Mentors { public static function format_duration( $s ) { return $s . 's'; } }
// The real class: it prints the way from this screen into the Administrator Dashboard, and maps
// every press's way back: a form that says it came from the dashboard goes back there (a sponsor
// report's draft, 1.115.0), and anything else comes back to this screen.
require_once __DIR__ . '/../includes/class-wpcpm-return.php';
// What the courtesy scan noticed, in words (`WPCPM_Pdf_Check::in_words()`).
require_once __DIR__ . '/../includes/class-wpcpm-pdf-check.php';
// The Administrator Dashboard's page, which every item on the queue tab links into.
require_once __DIR__ . '/stubs/administrators-dashboard.php';
if ( ! class_exists( 'WPCPM_Administrators_Cards' ) ) {
	/**
	 * The Administrator Dashboard's cards, for the one number this screen reads from them: how many
	 * items each of the dashboard's sponsor cards lists, which is where the queue tab's lists stop so
	 * that no row links to an item the dashboard does not draw. A number of its own here, apart from
	 * the applications' `QUEUE_MAX` and the interests card's `INTERESTS_SHOWN`, which equal it on a
	 * real site, so a check can tell which of them a list stops at.
	 */
	class WPCPM_Administrators_Cards {
		const LIMIT = 40;
	}
}
// The Tier box's two Tracks-row collaborators (1.114.0), stood in rather than loaded: the real
// WPCPM_Track_Builder pulls in the whole Track Builder screen for one address-building method,
// and the real WPCPM_Tracks reads a compiled option this suite has no reason to seed.
class WPCPM_Tracks { public static function partner_tracks( $record ) { return isset( $GLOBALS['partner_tracks'][ $record ] ) ? $GLOBALS['partner_tracks'][ $record ] : array(); } }
class WPCPM_Track_Builder { public static function track_url( $post_id ) { return 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_track=' . (int) $post_id; } }
/*
 * The sponsor report's figures (1.115.0), stood in for the one reader of the program's records:
 * bin/test-sponsor-report.php proves the numbers over a seeded program, and the Reports card and
 * the editor on this screen draw what a snapshot holds. The tier, the funded line and the events
 * follow the real rules, the rest is fixed so a check can name each figure it expects, the events
 * held among it, none in this term (bin/test-sponsor-report.php draws the table over held events),
 * and `$GLOBALS['report_sponsorship']` overrides a key for the checks on an empty table. The two
 * lists are the real class's, pinned against its source in the section that uses them.
 */
class WPCPM_Sponsor_Report_Numbers {
	const COUNTS       = array( 'started', 'finished', 'active', 'hours', 'contributions', 'teams', 'institutions', 'countries' );
	const TRACK_COUNTS = array( 'started', 'active', 'hours', 'contributions', 'teams', 'institutions', 'countries' );
	public static function program( $from, $until ) { return array( 'started' => 12, 'finished' => 4, 'active' => 30, 'hours' => 1234.5, 'contributions' => 9, 'teams' => 3, 'institutions' => 5, 'countries' => 2 ); }
	public static function track( $key, $from, $until ) {
		$label = (string) $key;
		foreach ( isset( $GLOBALS['partner_tracks'] ) ? $GLOBALS['partner_tracks'] : array() as $tracks ) {
			foreach ( $tracks as $track ) {
				if ( $track['key'] === $key ) { $label = $track['label']; }
			}
		}
		return array( 'started' => 3, 'active' => 6, 'hours' => 88.0, 'contributions' => 2, 'teams' => 1, 'institutions' => 2, 'countries' => 1, 'label' => $label );
	}
	public static function sponsorship( $record, array $row, $from, $until ) {
		$tier   = WPCPM_Sponsor_Tiers::effective( $row, $until );
		$months = array();
		$first  = new DateTime( $from . ' 12:00:00', new DateTimeZone( 'UTC' ) );
		for ( $i = 0; $i < 12; $i++ ) {
			$months[ $first->format( 'Y-m' ) ] = 0;
			$first->modify( 'first day of next month' );
		}
		$keys               = array_keys( $months );
		$months[ $keys[0] ] = 1;
		$months[ $keys[5] ] = 2;
		$out                = array(
			'tier'        => $tier,
			'tier_label'  => WPCPM_Sponsor_Tiers::label( $tier ),
			'from'        => $from,
			'until'       => $until,
			'funded_line' => WPCPM_Sponsor_Tiers::includes( $tier, 'funded_line' ) && isset( $row['funded_line'] ) ? trim( (string) $row['funded_line'] ) : '',
			'mentors'     => array( array( 'name' => 'Marta Mentor Example', 'profile' => 'https://profiles.wordpress.org/marta-example/', 'students' => 3 ) ),
			'months'      => $months,
			'offers'      => array( array( 'title' => 'One year of the premium plugin', 'claims' => 3, 'series' => array( $keys[0] => 1, $keys[5] => 2 ) ) ),
			'posts'       => 2,
			'events'      => array_values( WPCPM_Sponsor_Tiers::events( $tier ) ),
			'events_held' => array(),
		);
		return isset( $GLOBALS['report_sponsorship'] ) ? array_merge( $out, $GLOBALS['report_sponsorship'] ) : $out;
	}
	public static function format_hours( $hours ) { $hours = round( (float) $hours, 1 ); return (string) number_format_i18n( $hours, floor( $hours ) === $hours ? 0 : 1 ); }
}
/** A report's revisions as the checks set them: the editor counts them beside each narrative. */
function wp_revisions_enabled( $post ) { return empty( $GLOBALS['revisions_off'] ); }
function wp_get_post_revisions( $post = 0, $args = null ) { $id = is_object( $post ) ? (int) $post->ID : (int) $post; return isset( $GLOBALS['revisions'][ $id ] ) ? $GLOBALS['revisions'][ $id ] : array(); }
/*
 * The categories, for the real WPCPM_Sponsor_Posts: a sponsor's category is a child term whose
 * name follows the sponsor's, and the Edit form renames it (1.111.0). Only the two calls a rename
 * makes are stood in, over $GLOBALS['terms'], and every rename is recorded.
 */
$GLOBALS['terms']        = array();
$GLOBALS['term_renames'] = array();
function get_term( $id, $taxonomy = '' ) {
	return isset( $GLOBALS['terms'][ (int) $id ] ) ? (object) array_merge( array( 'term_id' => (int) $id ), $GLOBALS['terms'][ (int) $id ] ) : null;
}
function wp_update_term( $id, $taxonomy, $args = array() ) {
	$GLOBALS['term_renames'][] = array( (int) $id, $taxonomy, $args );
	foreach ( $args as $field => $value ) {
		$GLOBALS['terms'][ (int) $id ][ $field ] = $value;
	}
	return array( 'term_id' => (int) $id );
}

require_once __DIR__ . '/stubs/caps.php';
require_once __DIR__ . '/stubs/posts.php';
// The readers every screen suite shares: a drawn form's fields by its action, an address's
// arguments, the notices a press left.
require_once __DIR__ . '/stubs/screen-helpers.php';
require_once __DIR__ . '/../includes/class-wpcpm-refusal-meter.php';
// The bar every audience's screen prints above its tab, drawn here by the real printer.
require_once __DIR__ . '/../includes/class-wpcpm-screen-tabs.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-module.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sync-module.php';
// Before the module, which uses it: PHP declares a class only once the traits it uses are declared.
require_once __DIR__ . '/../includes/modules/trait-wpcpm-accounts-screen.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-members.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-policy.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-roster.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-index.php';
// The real class since 1.111.0, for the category a renamed sponsor's name carries to: the stand-in
// that used to answer here knew nothing of terms. Content access comes with it for the one
// constant its post queries name.
require_once __DIR__ . '/../includes/class-wpcpm-content-access.php';
// Read in under another name, through a rewrite of its source as bin/test-feedback.php reads the
// class it tests, so that the stand-in below holds the class's own name over every real method but
// the two the queue tab and the posting switch read.
$wpcpm_test_posts_source = preg_replace( '/^<\?php/', '', (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-posts.php' ), 1 );
$wpcpm_test_posts_source = str_replace( "\nclass WPCPM_Sponsor_Posts {\n", "\nclass WPCPM_Test_Real_Sponsor_Posts {\n", $wpcpm_test_posts_source, $wpcpm_test_renamed );
if ( 1 !== $wpcpm_test_renamed ) {
	echo "FAIL the real WPCPM_Sponsor_Posts was not found where its declaration was read, so the stand-in has nothing to extend\n";
	exit( 1 );
}
eval( $wpcpm_test_posts_source );
/**
 * The real class under its own name, but for two reads answered from the checks' own fixture.
 * pending_all() answers the queue tab's posts card from $GLOBALS['pending_posts'], in the facts' own
 * shape, and counts each read, so a check can say which tab asked. As the real read does, it applies
 * its limit first and leaves out a row afterward (one marked `dropped` here, where the real class
 * drops a post whose sponsor stamp is not a sponsor key), so a read can return fewer than asked.
 * posting_enabled() reads the same $GLOBALS['posting_off'] the checks below set and clear. Every
 * other method is the real one, the category a renamed sponsor carries to and the posts the tier
 * ladder's run puts to sleep among them. The real methods reach the real reads, never these two: the
 * real class calls `self::posting_enabled()`, which binds to the class read in above, so its
 * `apply_caps()`, `may_post()` and `render()` read the real flags option and never
 * $GLOBALS['posting_off'].
 */
class WPCPM_Sponsor_Posts extends WPCPM_Test_Real_Sponsor_Posts {
	public static function posting_enabled( $record ) { return empty( $GLOBALS['posting_off'][ $record ] ); }
	public static function pending_all( $limit = 50 ) {
		$GLOBALS['stand_in_reads']['posts'] = ( isset( $GLOBALS['stand_in_reads']['posts'] ) ? (int) $GLOBALS['stand_in_reads']['posts'] : 0 ) + 1;
		$rows = array_slice( isset( $GLOBALS['pending_posts'] ) ? $GLOBALS['pending_posts'] : array(), 0, max( 1, (int) $limit ) );
		return array_values( array_filter( $rows, static function ( $row ) { return empty( $row['dropped'] ); } ) );
	}
}
/**
 * The Administrator Dashboard's events, for the four calls the Sponsors screen makes of the queue
 * class. Whether they are in use is `$GLOBALS['events_shown']`, off unless a check turns it on, so
 * every draw before the events' own checks runs as a site with them off does. What waits is answered
 * from `$GLOBALS['waiting_events']` in the rows' own shape, oldest first as the real read answers it,
 * and each read is counted, so a check can say which tab asked; the menu bubble's count is how many
 * rows there are, none while the events are off. An event's time is worded as the real reader words
 * it, on UTC here, and the clock each caller hands it is kept in `$GLOBALS['time_fact_zones']`, so a
 * check can see that the tab hands none and the real reader takes the viewing manager's own, which
 * bin/test-sponsor-event-queue.php pins.
 */
class WPCPM_Sponsor_Event_Queue {
	public static function shown() { return ! empty( $GLOBALS['events_shown'] ); }
	public static function waiting_rows() {
		$GLOBALS['stand_in_reads']['events'] = ( isset( $GLOBALS['stand_in_reads']['events'] ) ? (int) $GLOBALS['stand_in_reads']['events'] : 0 ) + 1;
		return self::shown() && isset( $GLOBALS['waiting_events'] ) ? $GLOBALS['waiting_events'] : array();
	}
	public static function count_waiting() { return self::shown() && isset( $GLOBALS['waiting_events'] ) ? count( $GLOBALS['waiting_events'] ) : 0; }
	public static function time_fact( array $row, $zone = null ) {
		$GLOBALS['time_fact_zones'][] = $zone;
		return sprintf( '%1$s to %2$s, %3$d minutes, in your time zone (UTC)', gmdate( 'F j, Y g:i a', (int) $row['start'] ), gmdate( 'g:i a', (int) $row['end'] ), (int) $row['minutes'] );
	}
}
require_once __DIR__ . '/../includes/class-wpcpm-secret.php';
require_once __DIR__ . '/../includes/class-wpcpm-field-value.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-codes.php';
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-offers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-claims.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tools.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-interests.php';
// Add and Edit offer the choices three real classes already hold (the interests card's support
// choices, the profile card's product types, the application form's sponsorship options), and the
// migration box reads the move's own report: all four loaded before the first render (1.111.0).
// The guard comes with the application class because its open application prints the checks'
// words, which name the guard's numbers.
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-profile.php';
require_once __DIR__ . '/../includes/class-wpcpm-form-guard.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-application.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-migration.php';
// The tier ladder (1.112.0): the Tier box on the Edit form and the index's Tier column both read
// it now, for the five tiers, an unset row's Friend default and the day a term ends. The
// daily run's preview joins them: the index draws WPCPM_Sponsor_Tiers_Run::preview() above the
// table, over the real offers and posts classes loaded above.
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers-run.php';
// The badge (1.113.0), for activate(), which drops the version the badge's rule was flushed for.
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-badge.php';
// The sponsor report's store (1.115.0), for the Edit save, which records a renewed term's owed report.
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report.php';
// Its PDF and printed document, for the editor's Preview link, its PDF forms and the figures'
// names, and the screen that draws the Reports card and the editor (1.115.0). The files class is
// loaded for what the editor reads of it; its routes are bin/test-sponsor-report.php's to prove.
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-files.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-screen.php';
// The Track Builder's definition and store, for a Partner's draft, which begins each track block
// with the partners' narrative the track's published copy holds.
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-definition.php';
require_once __DIR__ . '/../includes/tracks/class-wpcpm-track-store.php';
// The listing (1.113.0), for the handbook box, which draws the same HTML the public page does.
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-listing.php';
// The Partners page (1.113.0), for activate(), which makes it and puts it where the switch says.
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-page.php';
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php';

/**
 * The plugin's lazy loader, as this suite has it: the stand-in for core's list table, then the
 * accounts base and the Sponsors table, so the Accounts tab draws its own list, which
 * bin/test-sponsors-accounts.php reads row by row. The table's file is required once it exists, so
 * a copy of the plugin without it fails its checks rather than ending this run.
 */
function wpcpm_load_accounts_tables() {
	require_once __DIR__ . '/stubs/class-wp-list-table.php';
	require_once __DIR__ . '/../includes/class-wpcpm-accounts-table.php';

	if ( file_exists( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-table.php' ) ) {
		require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsors-table.php';
	}
}

// ck(), then the fixture: an index of three sponsors written through the real index class.
// 1.111.0: the manager is a site user id now, not a Team Members record.
$A = 'recSPONSOR0000001'; $B = 'recSPONSOR0000002'; $C = 'recSPONSOR0000003';
WPCPM_Sponsors_Index::write( array(
	$A => array( 'name' => 'Mango Example ', 'status' => 'Approved', 'product_type' => 'Hosting', 'contact_person' => 'Rep One', 'contact_email' => 'maciej@a8c.com', 'manager' => '1' ),
	$B => array( 'name' => 'Wexample', 'status' => 'Approved', 'contact_email' => '' ),
	$C => array( 'name' => 'Ember Example', 'status' => 'Paused', 'contact_email' => 'maciej@a8c.com' ),
), time() );
$GLOBALS['settings'] = array( 'sponsor_on_inactive' => 'keep' );
// A distinct address from every sponsor's contact_email: get_user_by( 'email', ... ) must not
// resolve the acting manager when it looks up a sponsor's contact address.
$GLOBALS['users']    = array( 1 => new WP_User( 1, array( 'administrator' ), 'Maciej (Matt) Pilarski', 'manager@example.test' ) );
$GLOBALS['manage']   = array( 1 );
$GLOBALS['uid']      = 1;
$GLOBALS['nonce_ok'] = true;
$GLOBALS['queued']   = array();
$GLOBALS['audit']    = array();
$module = new WPCPM_Sponsors();
// The move onto the site is complete here, so the migration box stays off until its own section.
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, array( 'complete' => true, 'rows' => 3, 'posts' => 3, 'seeded' => 0, 'unseeded' => array(), 'unmapped' => array(), 'at' => time(), 'runs' => 1 ) );

function post( array $fields, $action ) {
	$GLOBALS['post'] = $fields;
	WPCPM_Flash::new_request();
	// An Error is an outcome too, so a handler that fatals fails its own check instead of ending
	// the run before the checks after it can say what else is wrong.
	try { call_user_func( $action ); } catch ( WPCPM_Test_Redirect $e ) { return array( 'redirect', $e->getMessage(), WPCPM_Flash::take( WPCPM_Sponsors::FLASH ) ); } catch ( WPCPM_Test_Die $e ) { return array( 'die', $e->getMessage() ); } catch ( Throwable $e ) { return array( 'error', get_class( $e ) . ': ' . $e->getMessage() ); }
	return array( 'fell-through' );
}

/** One method's body, for the assertions that read the source. */
function method_body( $src, $name ) {
	$body = substr( $src, (int) strpos( $src, 'function ' . $name . '(' ) );
	return substr( $body, 0, (int) strpos( $body, "\n\t}\n" ) );
}

/** Whether both are in the text and the first comes first; false when either is missing. */
function before( $text, $first, $second ) {
	$a = strpos( (string) $text, $first );
	$b = strpos( (string) $text, $second );
	return is_int( $a ) && is_int( $b ) && $a < $b;
}

/** The newest audit row, with every field a check reads, empty when nothing was logged. */
function last_audit() {
	$row = empty( $GLOBALS['audit'] ) ? array() : end( $GLOBALS['audit'] );
	return array_merge( array( 'kind' => '', 'sponsor' => '', 'subject' => '', 'actor' => 0, 'ground' => '', 'evidence' => '', 'message' => '', 'data' => array() ), (array) $row );
}

/**
 * The screen's HTML, captured, for the address's query (`tab`, `wpcpm_sapp_id`, `edit`); the read
 * counters start afresh, so what they hold afterward is what this one draw asked for (`reads_now()`).
 * Each draw is a request of its own, so it starts with the sponsors index unread: the index keeps
 * what a request read of it until the request ends (`WPCPM_Sponsors_Index::forget()`).
 *
 * @param array $get Query arguments the render should see.
 * @return string
 */
function render_screen( array $get = array() ) {
	WPCPM_Flash::new_request();
	WPCPM_Sponsors_Index::forget();
	$GLOBALS['get']            = $get;
	$GLOBALS['user_queries']   = array();
	$GLOBALS['list_queries']   = array();
	$GLOBALS['post_queries']   = array();
	$GLOBALS['stand_in_reads'] = array();
	// The list table reads the request where core's does, beside the plugin's reader above.
	$_GET                   = $get;
	$_REQUEST               = $get;
	$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?' . http_build_query( array_merge( array( 'page' => 'wpcpm-sponsors' ), $get ) );
	ob_start();
	( new WPCPM_Sponsors() )->render_admin_page();
	return (string) ob_get_clean();
}

/**
 * One tab of the screen, captured: the screen's address naming the tab, and any other arguments.
 *
 * @param string $tab A tab's slug, or any other value.
 * @param array  $get Other query arguments.
 * @return string
 */
function render_tab( $tab, array $get = array() ) {
	return render_screen( array_merge( array( 'tab' => $tab ), $get ) );
}

/**
 * The heading of every card a render draws, in document order, without its count.
 *
 * @param string $html Rendered screen.
 * @return string[]
 */
function cards_of( $html ) {
	preg_match_all( '#<div class="wpcpm-card[^"]*"[^>]*><h2[^>]*>(.*?)</h2>#s', (string) $html, $m );
	$out = array();
	foreach ( $m[1] as $heading ) {
		$out[] = trim( html_entity_decode( strip_tags( preg_replace( '# <span class="wpcpm-count">[^<]*</span>#', '', $heading ) ), ENT_QUOTES ) );
	}
	return $out;
}

/**
 * The tab bar a render prints: core's newer bar, a navigation region named for a screen reader,
 * holding plain links.
 *
 * @param string $html Rendered screen.
 * @return array|null The name the region gives itself, then each tab: its address, entities
 *                    decoded; its label as printed; whether its class marks it as the tab shown;
 *                    and whether it says so to a screen reader. Null when there is no bar.
 */
function bar_of( $html ) {
	if ( ! preg_match( '#<nav class="nav-tab-wrapper wp-clearfix" aria-label="([^"]*)">(.*?)</nav>#s', (string) $html, $bar ) ) {
		return null;
	}
	preg_match_all( '#<a href="([^"]*)" class="nav-tab( nav-tab-active)?"( aria-current="page")?>([^<]*)</a>#', $bar[2], $links, PREG_SET_ORDER );
	$tabs = array();
	foreach ( $links as $link ) {
		$tabs[] = array( html_entity_decode( $link[1], ENT_QUOTES, 'UTF-8' ), html_entity_decode( $link[4], ENT_QUOTES, 'UTF-8' ), '' !== $link[2], '' !== $link[3] );
	}
	return array( html_entity_decode( $bar[1], ENT_QUOTES, 'UTF-8' ), $tabs );
}

/**
 * The tabs a render's forms name in the field a press comes back by, each with how many forms name it.
 *
 * @param string $html Rendered screen.
 * @return array<string, int>
 */
function tab_fields( $html ) {
	preg_match_all( '#<input type="hidden" name="wpcpm_tab" value="([^"]*)" />#', (string) $html, $m );
	return array_count_values( $m[1] );
}

/**
 * The forms a render posts, each by the admin-post action it sends, and with `$unguarded` only those
 * that lack the once attribute, so a failure names the form.
 *
 * @param string $html      Rendered screen.
 * @param bool   $unguarded Whether to keep only the forms without `data-wpcpm-once`.
 * @return string[]
 */
function post_forms( $html, $unguarded = false ) {
	preg_match_all( '#<form\b([^>]*)>(.*?)</form>#s', (string) $html, $found, PREG_SET_ORDER );
	$out = array();
	foreach ( $found as $form ) {
		// The method in any case, as HTML reads it, so a form written method="POST" is not skipped.
		if ( ! preg_match( '/\bmethod="post"/i', $form[1] ) || ( $unguarded && false !== strpos( $form[1], 'data-wpcpm-once' ) ) ) {
			continue;
		}
		$fields = hidden_fields_of( $form[2] );
		$out[]  = isset( $fields['action'] ) ? $fields['action'] : '(no action)';
	}
	return $out;
}

/**
 * A render without the invitations card, and the card alone. The card is the mail layer's
 * (`WPCPM_Mail::render_invite_card()`), drawn on every audience's accounts screen with forms of its
 * own that carry no once attribute on any of them, so the forms the module draws are read
 * apart from it.
 *
 * @param string $html Rendered screen.
 * @return string
 */
function without_invitations( $html ) {
	return (string) preg_replace( '#<div class="wpcpm-card wpcpm-invites">.*?</div>#s', '', (string) $html );
}
function invitations_of( $html ) {
	return preg_match( '#<div class="wpcpm-card wpcpm-invites">.*?</div>#s', (string) $html, $found ) ? $found[0] : '';
}

/**
 * What the last draw read of the six things a tab reads only when it draws from them: the sponsors
 * index (a query of its sponsors' posts, which a draw makes once, since the index keeps what it read
 * for the rest of the request), the accounts (the live ones, or the list of every account holding
 * the Sponsor role), the applications, the sponsor posts waiting, the signed agreements waiting and
 * the agreements out of force.
 *
 * @return array<string, bool>
 */
function reads_now() {
	return array(
		'index'             => ! empty( $GLOBALS['post_queries'][ WPCPM_Sponsors_Index::POST_TYPE ] ),
		'accounts'          => ! empty( $GLOBALS['user_queries'][ WPCPM_Sponsor_Members::META_ACTIVE ] ) || ! empty( $GLOBALS['list_queries'] ),
		'applications'      => class_exists( 'WPCPM_Sponsor_Application', false ) && ! empty( $GLOBALS['post_queries'][ WPCPM_Sponsor_Application::POST_TYPE ] ),
		'posts'             => ! empty( $GLOBALS['stand_in_reads']['posts'] ),
		'waiting documents' => ! empty( $GLOBALS['stand_in_reads']['waiting documents'] ),
		'out of force'      => ! empty( $GLOBALS['stand_in_reads']['out of force'] ),
	);
}

/**
 * The cards a render draws that carry an id, in document order: each card's id, its heading without
 * the count, and the count.
 *
 * @param string $html Rendered screen.
 * @return array[]
 */
function counted_cards_of( $html ) {
	preg_match_all( '#<div class="wpcpm-card" id="([^"]*)"><h2>(.*?) <span class="wpcpm-count">([^<]*)</span></h2>#s', (string) $html, $found, PREG_SET_ORDER );
	$out = array();
	foreach ( $found as $card ) {
		$out[] = array( $card[1], html_entity_decode( $card[2], ENT_QUOTES ), $card[3] );
	}
	return $out;
}

/**
 * One card of a render, from its opening tag to the tag before the next card or the end of the wrap.
 *
 * @param string $html Rendered screen.
 * @param string $id   The card's id.
 * @return string
 */
function card_body( $html, $id ) {
	$at = strpos( (string) $html, '<div class="wpcpm-card" id="' . $id . '">' );
	if ( false === $at ) {
		return '';
	}
	$body = substr( (string) $html, $at );
	$next = strpos( $body, '<div class="wpcpm-card', 1 );
	return false === $next ? $body : substr( $body, 0, $next );
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

echo "=== The module ===\n";
ck( 'the menu label carries no bubble while nothing needs a manager', $module->menu_label(), 'Sponsors' );
// The class keeps extending WPCPM_Sync_Module for its helpers (verify(),
// redirect_back(), the flashed notice) and has had no sync of its own since 1.111.0.
$sync_class = new ReflectionMethod( 'WPCPM_Sponsors', 'sync_class' );
if ( PHP_VERSION_ID < 80100 ) {
	$sync_class->setAccessible( true );
}
$boot_src = method_body( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' ), 'boot' );
ck( 'the module keeps the sync module\'s helpers and has no sync: sync_class() is empty, no sync action is named, boot() hooks no sync handler', array(
	is_subclass_of( $module, 'WPCPM_Sync_Module' ),
	$sync_class->invoke( $module ),
	array( WPCPM_Sponsors::ACTION_SYNC, WPCPM_Sponsors::ACTION_CANCEL, WPCPM_Sponsors::ACTION_TICK ),
	array( strpos( $boot_src, 'handle_sync' ), strpos( $boot_src, 'handle_cancel' ), strpos( $boot_src, 'handle_tick' ) ),
), array( true, '', array( '', '', '' ), array( false, false, false ) ) );
ck( 'and boot() hooks the three handlers this screen gained in their place', array(
	substr_count( $boot_src, "add_action( 'admin_post_' . self::ACTION_ADD, array( \$this, 'handle_add' ) );" ),
	substr_count( $boot_src, "add_action( 'admin_post_' . self::ACTION_EDIT, array( \$this, 'handle_edit' ) );" ),
	substr_count( $boot_src, "add_action( 'admin_post_' . self::ACTION_MIGRATE, array( \$this, 'handle_migrate' ) );" ),
), array( 1, 1, 1 ) );
ck( 'named as the plan names them', array_map( static function ( $name ) { return defined( 'WPCPM_Sponsors::' . $name ) ? constant( 'WPCPM_Sponsors::' . $name ) : null; }, array( 'ACTION_ADD', 'ACTION_EDIT', 'ACTION_MIGRATE', 'LOG_ADDED', 'LOG_EDITED' ) ), array( 'wpcpm_sponsor_add', 'wpcpm_sponsor_edit', 'wpcpm_sponsors_migrate', 'sponsor_added', 'sponsor_edited' ) );
ck( 'every status the handlers flash has a sentence', array_values( array_diff( array( 'provisioned', 'provision-attached', 'provision-admin', 'provision-inactive', 'provision-no-email', 'provision-refused', 'provision-failed', 'record-unmarked', 'attached', 'attach-no-account', 'attach-refused', 'detached', 'detach-refused', 'refused', 'sponsor-added', 'sponsor-saved', 'sponsor-rejected', 'name-too-long', 'contact-too-long', 'funded-too-long', 'notes-too-long', 'name-loss', 'contact-loss', 'funded-loss', 'notes-loss', 'sponsor-unchanged', 'sponsor-detached', 'sponsor-not-saved', 'migrated' ), array_keys( WPCPM_Sponsors::messages() ) ) ), array() );
ck( 'a refused provisioning says the account cannot act for the sponsor, an error, and sends the reader to no card', WPCPM_Sponsors::messages()['provision-refused'], array( 'error', 'That account cannot act for this sponsor.' ) );

echo "\n=== Provisioning ===\n";
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'an Approved sponsor with a contact address gets an account', $r[0] === 'redirect' ? $r[2] : $r, 'provisioned' );
ck( 'the nonce is keyed to the record', end( $GLOBALS['nonce_checked'] ), 'wpcpm_sponsor_provision_' . $A );
$new = end( $GLOBALS['users'] );
ck( 'with the sponsor role, the contact\'s name and address', array( $new->roles, $new->display_name, $new->user_email ), array( array( 'wpcpm_sponsor' ), 'Rep One', 'maciej@a8c.com' ) );
ck( 'stamped for the sponsor, provisioned', array( WPCPM_Sponsor_Members::sponsor_of( $new ), get_user_meta( $new->ID, WPCPM_Sponsor_Members::META_MEMBERSHIP, true )['how'] ), array( $A, 'provisioned' ) );
ck( 'the welcome is queued, never sent here', array( $GLOBALS['queued'], isset( $GLOBALS['mailed'] ) ? $GLOBALS['mailed'] : array() ), array( array( $new->ID ), array() ) );

echo "\n=== Provisioning seeds the first offer ===\n";
$seeded = WPCPM_Sponsor_Offers::offers_of( $A );
ck( 'provisioning seeded one draft offer from the index, marked primary', array( count( $seeded ), reset( $seeded )['state'], reset( $seeded )['primary'] ), array( 1, 'draft', true ) );

ck( 'the row\'s account flag is true at once', WPCPM_Sponsors_Index::row( $A )['dashboard_account'], true );
ck( 'and it is logged', array( end( $GLOBALS['audit'] )['kind'], end( $GLOBALS['audit'] )['sponsor'], end( $GLOBALS['audit'] )['ground'] ), array( 'provisioned', $A, 'manager' ) );
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'pressing again makes and attaches nothing new and says the sponsor already has an account', array( $r[2], count( $GLOBALS['inserted'] ), count( WPCPM_Sponsor_Members::members_of( $A ) ) ), array( 'provision-already', 1, 1 ) );
$GLOBALS['users'][7] = new WP_User( 7, array( 'wpcpm_mentor' ), 'Ines', 'maciej@a8c.com' );
// The created account moves to another address, so the contact address now names the mentor
// alone: the stub's get_user_by( 'email' ) must not have two candidates.
$new->user_email = 'former@example.test';
WPCPM_Sponsor_Members::detach( $new->ID, 'removed', 1 );
$GLOBALS['queued'] = array();
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'an existing account at that address is attached rather than duplicated', array( $r[2], count( $GLOBALS['inserted'] ) ), array( 'provision-attached', 1 ) );
$r = post( array( 'wpcpm_sponsor' => $B ), array( $module, 'handle_provision' ) );
ck( 'a sponsor with no contact address cannot be given an account', $r[2], 'provision-no-email' );
$r = post( array( 'wpcpm_sponsor' => $C ), array( $module, 'handle_provision' ) );
ck( 'nor can one that is not Approved', $r[2], 'provision-inactive' );
$GLOBALS['users'][7]->roles = array( 'administrator' );
foreach ( WPCPM_Sponsor_Members::members_of( $A ) as $m ) { WPCPM_Sponsor_Members::detach( $m->ID, 'removed', 1 ); }
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'an administrator\'s address is refused', $r[2], 'provision-admin' );
$GLOBALS['nonce_ok'] = false;
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'a bad nonce dies', $r[0], 'die' );
$GLOBALS['nonce_ok'] = true;
$GLOBALS['uid'] = 9; $GLOBALS['users'][9] = new WP_User( 9, array( 'subscriber' ), 'Stranger', 'maciej@a8c.com' );
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_provision' ) );
ck( 'a non-manager dies with 403', $r[0] === 'die' && false !== strpos( $r[1], '403' ), true );
$GLOBALS['uid'] = 1;

echo "\n=== Members ===\n";
$GLOBALS['users'][7]->roles = array( 'wpcpm_mentor' );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_op' => 'attach', 'wpcpm_email' => 'maciej@a8c.com' ), array( $module, 'handle_members' ) );
ck( 'attach by address finds the account', $r[2], 'attached' );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_op' => 'attach', 'wpcpm_email' => 'nobody@example.test' ), array( $module, 'handle_members' ) );
ck( 'an address with no account is not created here: provisioning does that', $r[2], 'attach-no-account' );
ck( 'and its sentence says Attach account takes an account that exists, and where a sponsor\'s first account is made', WPCPM_Sponsors::messages()['attach-no-account'], array( 'error', 'No account has that address. Attach account attaches an account that exists; a sponsor\'s first account is made with Create account on the No account view.' ) );
$attached = WPCPM_Sponsor_Members::members_of( $A );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_op' => 'detach', 'wpcpm_user' => $attached[0]->ID ), array( $module, 'handle_members' ) );
ck( 'detach removes the one account', array( $r[2], WPCPM_Sponsor_Members::members_of( $A ) ), array( 'detached', array() ) );
ck( 'the row\'s account flag is false at once', WPCPM_Sponsors_Index::row( $A )['dashboard_account'], false );
$r = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_op' => 'eat' ), array( $module, 'handle_members' ) );
ck( 'an op that is not one is the one refusal', $r[2], 'refused' );

echo "\n=== Provisioning refuses a student's address, and a detach refuses another sponsor's member ===\n";
// $C ('Ember Example') is Paused in the fixture; flipped to Approved only for this scenario and
// flipped back before the screen renders below, so nothing else in this file sees a fourth
// qualifying sponsor or an extra Create account button.
WPCPM_Sponsors_Index::patch( $C, array( 'status' => WPCPM_Sponsors_Index::STATUS_APPROVED ) );
// maciej@a8c.com currently names both Ines (id 7) and the Stranger (id 9); both moved
// aside so the student account below is the one and only match, the same technique the
// provisioning tests above already use to steer get_user_by( 'email', ... ).
$GLOBALS['users'][7]->user_email = 'moved-aside-1@example.test';
$GLOBALS['users'][9]->user_email = 'moved-aside-2@example.test';
$GLOBALS['users'][11]                                    = new WP_User( 11, array( 'wpcpm_student' ), 'A Student', 'maciej@a8c.com' );
$GLOBALS['umeta'][11]['wpcpm_student_record_id'] = 'recSTUDENT0000001';
$r = post( array( 'wpcpm_sponsor' => $C ), array( $module, 'handle_provision' ) );
ck( 'provisioning a sponsor whose contact belongs to a student account is refused', $r[2], 'provision-refused' );
$GLOBALS['users'][7]->user_email = 'maciej@a8c.com';
$GLOBALS['users'][9]->user_email = 'maciej@a8c.com';
WPCPM_Sponsors_Index::patch( $C, array( 'status' => 'Paused' ) );

echo "\n=== A stored contact address that holds an entity ===\n";
// A row can hold its address with an entity, `&amp;` for the "&" of the address, as text written by
// other means is stored. sanitize_email() drops the ";" of it and keeps the rest, which is another
// address: provisioning would make the account there, or attach the account somebody else holds
// there. The address is read back first, so the account is made at the address the row means, and
// the No account view says what Create account will do with it. $C is flipped to Approved for this
// alone, as above, and put back after.
$entity_mail   = 'contact+a&amp;b@example.test';
$meant_mail    = 'contact+a&b@example.test';
$mangled_mail  = 'contact+a&ampb@example.test';
$inserted_then = count( $GLOBALS['inserted'] );
WPCPM_Sponsors_Index::patch( $C, array( 'status' => WPCPM_Sponsors_Index::STATUS_APPROVED, 'contact_email' => $entity_mail ) );
$GLOBALS['users'][13] = new WP_User( 13, array( 'subscriber' ), 'Somebody Else', $mangled_mail );
$entity_row = array_values( array_filter( WPCPM_Sponsors::provision_worklist(), static function ( $row ) use ( $C ) { return $C === $row['id']; } ) );
ck( 'the No account view reads the address the row means: its mask, ready, and no account found at it',
	isset( $entity_row[0] ) ? array( $entity_row[0]['contact'], $entity_row[0]['existing'], $entity_row[0]['reason'], $entity_row[0]['ready'] ) : null,
	array( WPCPM_Mail::mask_address( $meant_mail ), false, '', true ) );
$r     = post( array( 'wpcpm_sponsor' => $C ), array( $module, 'handle_provision' ) );
$entry = count( $GLOBALS['inserted'] ) > $inserted_then ? end( $GLOBALS['inserted'] ) : null;
ck( 'Create account makes the account at the address the row means, never at what sanitize_email() leaves of the entity, and attaches nobody who holds that other address',
	array( $r[2], count( $GLOBALS['inserted'] ) - $inserted_then, is_array( $entry ) ? $entry['user_email'] : null, WPCPM_Sponsor_Members::sponsor_of( 13 ) ),
	array( 'provisioned', 1, $meant_mail, '' ) );
ck( 'and the row keeps its address as stored', WPCPM_Sponsors_Index::row( $C )['contact_email'], $entity_mail );
foreach ( WPCPM_Sponsor_Members::members_of( $C ) as $m ) { WPCPM_Sponsor_Members::detach( $m->ID, 'removed', 1 ); }
unset( $GLOBALS['users'][13] );
// A numbered entity too, `&#43;` for the "+" of an address: the Edit form's box shows the address
// with every entity read, named or numbered, and the address is read back the same way here.
$numbered_mail    = 'sam&#43;co@example.org';
$numbered_meant   = 'sam+co@example.org';
$numbered_mangled = 'sam&#43co@example.org';
$inserted_then    = count( $GLOBALS['inserted'] );
WPCPM_Sponsors_Index::patch( $C, array( 'contact_email' => $numbered_mail ) );
$GLOBALS['users'][14] = new WP_User( 14, array( 'subscriber' ), 'Someone Else', $numbered_mangled );
$numbered_row = array_values( array_filter( WPCPM_Sponsors::provision_worklist(), static function ( $row ) use ( $C ) { return $C === $row['id']; } ) );
$r            = post( array( 'wpcpm_sponsor' => $C ), array( $module, 'handle_provision' ) );
$entry        = count( $GLOBALS['inserted'] ) > $inserted_then ? end( $GLOBALS['inserted'] ) : null;
ck( 'a stored address holding a numbered entity, "sam&#43;co@example.org", is read as the address it means: the No account view masks it, and Create account makes the account there and attaches nobody who holds what sanitize_email() leaves of it',
	array( isset( $numbered_row[0] ) ? array( $numbered_row[0]['contact'], $numbered_row[0]['existing'] ) : null, $r[2], is_array( $entry ) ? $entry['user_email'] : null, WPCPM_Sponsor_Members::sponsor_of( 14 ), WPCPM_Sponsors_Index::row( $C )['contact_email'] ),
	array( array( WPCPM_Mail::mask_address( $numbered_meant ), false ), 'provisioned', $numbered_meant, '', $numbered_mail ) );
foreach ( WPCPM_Sponsor_Members::members_of( $C ) as $m ) { WPCPM_Sponsor_Members::detach( $m->ID, 'removed', 1 ); }
unset( $GLOBALS['users'][14] );
// An entity for a character `sanitize_email()` drops: read back, "sam;co@example.org", of which
// `sanitize_email()` would leave another address, "samco@example.org", perhaps somebody's. The
// address reads as none: Create account refuses it as having no address, the No account view
// says so, nobody is attached and no account is made there.
$dropped_mail  = 'sam&#59;co@example.org';
$dropped_other = 'samco@example.org';
$inserted_then = count( $GLOBALS['inserted'] );
WPCPM_Sponsors_Index::patch( $C, array( 'contact_email' => $dropped_mail ) );
$GLOBALS['users'][15] = new WP_User( 15, array( 'subscriber' ), 'A Third Person', $dropped_other );
$dropped_row = array_values( array_filter( WPCPM_Sponsors::provision_worklist(), static function ( $row ) use ( $C ) { return $C === $row['id']; } ) );
$r           = post( array( 'wpcpm_sponsor' => $C ), array( $module, 'handle_provision' ) );
ck( 'a stored address whose entity is for a character sanitize_email() drops, "sam&#59;co@example.org", reads as no address: the No account view says so, Create account refuses it, nobody is attached and no account is made',
	array( isset( $dropped_row[0] ) ? $dropped_row[0]['reason'] : null, $r[2], count( $GLOBALS['inserted'] ) - $inserted_then, WPCPM_Sponsor_Members::sponsor_of( 15 ), WPCPM_Sponsors_Index::row( $C )['contact_email'] ),
	array( 'no-email', 'provision-no-email', 0, '', $dropped_mail ) );
unset( $GLOBALS['users'][15] );
WPCPM_Sponsors_Index::patch( $C, array( 'status' => 'Paused', 'contact_email' => 'maciej@a8c.com' ) );

$GLOBALS['users'][12] = new WP_User( 12, array( 'subscriber' ), 'Another Rep', 'maciej@a8c.com' );
WPCPM_Sponsor_Members::attach( 12, $A, WPCPM_Sponsor_Members::HOW_MANAGER, 1 );
$r = post( array( 'wpcpm_sponsor' => $B, 'wpcpm_op' => 'detach', 'wpcpm_user' => 12 ), array( $module, 'handle_members' ) );
ck( 'a detach naming a member of a different sponsor is refused', $r[2], 'detach-refused' );
WPCPM_Sponsor_Members::detach( 12, WPCPM_Sponsor_Members::REASON_REMOVED, 1 );

echo "\n=== The screen, in eight tabs by job ===\n";
// Each tab drawn at its own address, and the screen at its own, which names no tab. Nothing waits
// yet, so the queue tab's three cards are empty here; the tabs are read again in the queue's
// section (each tab's cards and what each reads). The launch switch is on for these draws, so the
// bar holds all eight; while it is off Handbook listing is not in the bar, which the handbook box's
// section checks. One sponsor's logo is on the site, so the Logo column says both of its words.
WPCPM_Sponsors_Index::write_logo_record( $C, array( 'colour' => 55, 'source' => 'site' ) );
$GLOBALS['settings']['sponsor_tiers_live'] = 1;
$slugs = array( 'queue', 'sponsors', 'accounts', 'offers', 'interests', 'reports', 'agreements', 'listing' );
$tabs  = array();
foreach ( $slugs as $slug ) {
	$tabs[ $slug ] = render_tab( $slug );
}
$bare = render_screen();

ck( 'the screen holds eight tabs, the queue first, the reports before the agreements and the handbook listing last, in the words a manager knows each job by', WPCPM_Sponsors::TABS, array(
	'queue'      => 'Waiting for review',
	'sponsors'   => 'Sponsors',
	'accounts'   => 'Accounts',
	'offers'     => 'Offers and codes',
	'interests'  => 'Interests',
	'reports'    => 'Reports',
	'agreements' => 'Agreements',
	'listing'    => 'Handbook listing',
) );

$home = 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors';
/*
 * The bar as the screen prints it with one tab shown: the seven tabs the screen always holds, then,
 * while the launch switch is on, Handbook listing. The checks draw with the switch off, as on a site
 * before the launch, but where a section turns it on and says so.
 */
$bar_for = function ( $shown, $with_listing = false ) use ( $home, $slugs ) {
	$labels = array_combine( $slugs, array( 'Waiting for review', 'Sponsors', 'Accounts', 'Offers and codes', 'Interests', 'Reports', 'Agreements', 'Handbook listing' ) );
	if ( ! $with_listing ) {
		unset( $labels['listing'] );
	}
	$out = array();
	foreach ( $labels as $slug => $label ) {
		$out[] = array( $home . '&tab=' . $slug, $label, $shown === $slug, $shown === $slug );
	}
	return array( 'Secondary menu', $out );
};

ck( 'the bar holds the eight in that order, each at the screen\'s address with its tab, and the screen\'s own address marks the queue, for the eye and for a screen reader', bar_of( $bare ), $bar_for( 'queue', true ) );
$marked = array();
$wanted = array();
foreach ( $tabs as $slug => $drawn ) {
	$marked[ $slug ] = bar_of( $drawn );
	$wanted[ $slug ] = $bar_for( $slug, true );
}
ck( 'each tab asked for by its slug is the one the bar marks', $marked, $wanted );
ck( 'the screen\'s own address draws the queue tab, tag for tag', $bare, $tabs['queue'] );
ck( 'and so does a tab the screen does not have', render_tab( 'nope' ), $bare );
ck( 'the frame: the heading, the lede in the screen\'s words, then the screen\'s one bar, which no heading holds, all inside the wrap', array(
	0 === strpos( $bare, '<div class="wrap wpcpm-wrap"><h1>Sponsors</h1><p class="wpcpm-lede">The companies that sponsor mentors and offer their tools to students. Each has a Sponsor Dashboard, its own people and its offer; their accounts are created and invited on the Accounts tab.</p><nav class="nav-tab-wrapper wp-clearfix"' ),
	substr_count( $bare, 'nav-tab-wrapper' ),
	preg_match( '#<h[1-6][^>]*nav-tab-wrapper#', $bare ),
	'</div>' === substr( $bare, -6 ),
), array( true, 1, 0, true ) );

$GLOBALS['l10n'] = array(
	'Waiting for review' => 'En espera de revisión',
	'Sponsors'           => 'Patrocinadores',
	'Accounts'           => 'Cuentas',
	'Offers and codes'   => 'Ofertas y códigos',
	'Interests'          => 'Intereses',
	'Reports'            => 'Informes',
	'Agreements'         => 'Acuerdos',
	'Handbook listing'   => 'Listado del manual',
	'Secondary menu'     => 'Menú secundario',
);
$spanish         = bar_of( render_tab( 'interests' ) );
$GLOBALS['l10n'] = array();
ck( 'the labels are translated where the bar prints them, and so is the name the bar gives itself',
	null === $spanish ? null : array( $spanish[0], array_column( $spanish[1], 1 ) ),
	array( 'Menú secundario', array( 'En espera de revisión', 'Patrocinadores', 'Cuentas', 'Ofertas y códigos', 'Intereses', 'Informes', 'Acuerdos', 'Listado del manual' ) ) );

// The sponsors are the site's own records, so the tab that lists them draws no sync of any kind: the
// move onto the site is complete here, so the Sponsors card is its one card.
ck( 'the Sponsors tab draws the Sponsors card and nothing of a sync: no sync card, no progress bar, no Sync or Cancel, no last sync\'s error', array(
	cards_of( $tabs['sponsors'] ),
	strpos( $tabs['sponsors'], 'data-wpcpm-progress' ),
	strpos( $tabs['sponsors'], 'Sync sponsors now' ),
	strpos( $tabs['sponsors'], 'Cancel sync' ),
	strpos( $tabs['sponsors'], 'Last sync error' ),
), array( array( 'Sponsors' ), false, false, false, false ) );
ck( 'and the index card, which lists every sponsor with its status and manager', false !== strpos( $tabs['sponsors'], 'Mango Example' ) && false !== strpos( $tabs['sponsors'], 'Paused' ) && false !== strpos( $tabs['sponsors'], 'Maciej (Matt) Pilarski' ), true );
// No sponsor here has a tier, so each reads as a Friend; Ember Example's logo is on the site
// (written above), and the other two have none.
ck( 'its table is headed Sponsor, Status, Tier, Product, Program contact, Accounts and Logo, then the column of links, and each row says its sponsor\'s tier and whether its logo is on the site', array(
	false !== strpos( $tabs['sponsors'], '<table class="wpcpm-table widefat striped"><thead><tr><th scope="col">Sponsor</th><th scope="col">Status</th><th scope="col">Tier</th><th scope="col">Product</th><th scope="col">Program contact</th><th scope="col">Accounts</th><th scope="col">Logo</th><th scope="col"><span class="screen-reader-text">' ),
	substr_count( $tabs['sponsors'], '<td>Friend</td>' ),
	substr_count( $tabs['sponsors'], '<td>On the site</td>' ),
	substr_count( $tabs['sponsors'], '<td>None</td>' ),
), array( true, 3, 1, 2 ) );
ck( 'the Sponsors card offers no Create account: no tab draws a form that posts it', substr_count( implode( '', $tabs ), 'value="' . WPCPM_Sponsors::ACTION_PROVISION . '"' ), 0 );
// The card's last column opens each sponsor's Edit form, a view of this tab, and its accounts, each
// link named for a screen reader by the sponsor's name as the card prints it, trimmed: one of each a
// row, Paused Ember Example's among them.
$links_cell = static function ( $record, $name ) use ( $home ) {
	return '<td><a href="' . $home . '&tab=sponsors&edit=' . $record . '">Edit<span class="screen-reader-text"> ' . $name . '</span></a> <a href="' . $home . '&tab=accounts&wpcpm_sponsor=' . $record . '">Manage accounts<span class="screen-reader-text"> of ' . $name . '</span></a></td></tr>';
};
ck( 'its last column is each sponsor\'s Edit, to its Edit form on this tab, then its Manage accounts, to that sponsor\'s accounts on the Accounts tab, each named by the sponsor for a screen reader: on each of its three rows, whatever the sponsor\'s status, and no row ends in an empty cell', array(
	substr_count( $tabs['sponsors'], '>Edit<span class="screen-reader-text">' ),
	substr_count( $tabs['sponsors'], '>Manage accounts<span class="screen-reader-text">' ),
	false !== strpos( $tabs['sponsors'], $links_cell( $A, 'Mango Example' ) ),
	false !== strpos( $tabs['sponsors'], $links_cell( $B, 'Wexample' ) ),
	false !== strpos( $tabs['sponsors'], $links_cell( $C, 'Ember Example' ) ),
	substr_count( $tabs['sponsors'], '<td></td></tr>' ),
), array( 3, 3, true, true, true, 0 ) );
ck( 'and that column\'s heading is Edit and Manage accounts for a screen reader, so no header cell is empty', array(
	false !== strpos( $tabs['sponsors'], '<th scope="col">Logo</th><th scope="col"><span class="screen-reader-text">Edit and Manage accounts</span></th></tr></thead>' ),
	substr_count( $tabs['sponsors'], '<th scope="col"></th>' ),
), array( true, 0 ) );
// Add a sponsor ends the card: its form, under the table, names the Sponsors tab, so a press that is
// refused (a name of spaces alone) comes back to the tab it was made on.
$add_form = form_fields_of( $tabs['sponsors'], WPCPM_Sponsors::ACTION_ADD );
$r_add    = post( array_merge( isset( $add_form[0] ) ? $add_form[0] : array(), array( 'wpcpm_name' => '   ' ) ), array( $module, 'handle_add' ) );
ck( 'the card ends with Add a sponsor, its form under the table with its own nonce and the Sponsors tab named, and a refused Add comes back to the Sponsors tab with its outcome', array(
	$add_form,
	before( $tabs['sponsors'], '</table>', '<h3 id="wpcpm-sponsor-add">Add a sponsor</h3>' ),
	$r_add,
), array(
	array( array( '_wpnonce' => 'nonce-wpcpm_sponsor_add', 'action' => 'wpcpm_sponsor_add', 'wpcpm_support[]' => '', 'wpcpm_tab' => 'sponsors', 'wpcpm_test' => '0' ) ),
	true,
	array( 'redirect', $home . '&tab=sponsors', 'sponsor-rejected' ),
) );
// While the move onto the site is incomplete, its box stands above the Sponsors card on the Sponsors
// tab and on no other; the move is put back as complete after.
$moved = get_option( WPCPM_Sponsors_Migration::OPT_REPORT );
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, array( 'complete' => false, 'rows' => 3, 'posts' => 2, 'seeded' => 0, 'unseeded' => array(), 'unmapped' => array(), 'at' => time(), 'runs' => 1 ) );
$boxed = array();
foreach ( $slugs as $slug ) {
	$boxed[ $slug ] = substr_count( render_tab( $slug ), 'id="wpcpm-sponsors-migration"' );
}
$moving = render_tab( 'sponsors' );
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, $moved );
ck( 'while the move onto the site is incomplete the Sponsors tab draws the migration box, then the Sponsors card, and no other tab draws the box; once the move is complete the tab draws the card alone', array(
	$boxed,
	cards_of( $moving ),
	cards_of( render_tab( 'sponsors' ) ),
), array(
	array_merge( array_fill_keys( $slugs, 0 ), array( 'sponsors' => 1 ) ),
	array( 'Moving sponsors onto the site', 'Sponsors' ),
	array( 'Sponsors' ),
) );
ck( 'the Accounts tab draws the invitations card and the sponsor accounts list, a WordPress list table under the tab\'s address, and no sponsor\'s accounts: no Remove, no Attach account and no posting switch, which are drawn for one sponsor at a time', array(
	cards_of( $tabs['accounts'] ),
	false !== strpos( $tabs['accounts'], '<form method="get" action="#wpcpm-accounts-list">' ),
	false !== strpos( $tabs['accounts'], '<input type="hidden" name="tab" value="accounts" />' ),
	form_fields_of( $tabs['accounts'], WPCPM_Sponsors::ACTION_MEMBERS ),
	form_fields_of( $tabs['accounts'], WPCPM_Sponsor_Posts::ACTION_FLAGS ),
), array( array( 'Invitations', 'Sponsor accounts' ), true, true, array(), array() ) );
ck( 'the Interests tab draws the interests log card and no other', cards_of( $tabs['interests'] ), array( 'Interests' ) );
// The once attribute is what the submit guard, forms.js, reads, in wp-admin as on the dashboards;
// no suite runs the script, so here each check reads the attribute and nothing more.
ck( 'every form the module draws on a tab carries the once attribute', array_map( static function ( $html ) { return post_forms( without_invitations( $html ), true ); }, $tabs ), array_fill_keys( $slugs, array() ) );
// The agreement forms name no tab: they come back by their referer. The Accounts tab draws no form
// here: the invitations card draws its button only for an account never invited, and there is none.
ck( 'every form whose press comes back by the tab it names names its own: Add a sponsor the Sponsors tab', array_map( 'tab_fields', $tabs ), array(
	'queue'      => array(),
	'sponsors'   => array( 'sponsors' => 1 ),
	'accounts'   => array(),
	'offers'     => array(),
	'interests'  => array(),
	'reports'    => array(),
	'agreements' => array(),
	'listing'    => array(),
) );

$reload = 'That action could not be completed. Reload the screen and try again.';
$worded = array();
foreach ( $slugs as $slug ) {
	WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'error' );
	preg_match( '#<div class="notice notice-error is-dismissible"><p>(.*?)</p></div>#', render_tab( $slug ), $error_notice );
	$worded[ $slug ] = isset( $error_notice[1] ) ? $error_notice[1] : '';
}
ck( 'the outcome a failed press leaves is worded by the tab it comes back to: on the Accounts tab the invitation\'s, the one press there that fails so, and on the seven tabs that print no error below it, the Sponsors tab among them, a reload', $worded, array(
	'queue'      => $reload,
	'sponsors'   => $reload,
	'accounts'   => 'The invitation could not be sent.',
	'offers'     => $reload,
	'interests'  => $reload,
	'reports'    => $reload,
	'agreements' => $reload,
	'listing'    => $reload,
) );
// No sync runs on this screen, so the sync's other two outcomes are none of its words: each, flashed,
// is taken and prints nothing, on every tab.
$sync_said = array();
foreach ( $slugs as $slug ) {
	foreach ( array( 'started', 'cancelled' ) as $outcome ) {
		WPCPM_Flash::set( WPCPM_Sponsors::FLASH, $outcome );
		$sync_said[ $slug ][ $outcome ] = notices_in( render_tab( $slug ) );
	}
}
ck( 'the sync\'s outcomes are not the screen\'s: started and cancelled print nothing on any tab, and the screen answers none of the three as its own', array( $sync_said, WPCPM_Sponsors::sync_messages() ), array( array_fill_keys( $slugs, array( 'started' => '', 'cancelled' => '' ) ), array() ) );
// One outcome, flashed before each draw, is printed once on every tab, before the bar: the suite's
// WPCPM_Flash remembers what a page took, as the real one does, so a second printer on the channel
// would print it again and be counted here.
$printed_once = array();
foreach ( $slugs as $slug ) {
	WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'error' );
	$drawn = render_tab( $slug );
	preg_match_all( '#<div class="notice notice-[a-z]+ is-dismissible">#', $drawn, $dismissible, PREG_OFFSET_CAPTURE );
	$printed_once[ $slug ] = array( count( $dismissible[0] ), isset( $dismissible[0][0][1] ) && $dismissible[0][0][1] < (int) strpos( $drawn, '<nav class="nav-tab-wrapper' ) );
}
ck( 'one outcome flashed is the one dismissible notice on every tab, printed once, before the bar', $printed_once, array_fill_keys( $slugs, array( 1, true ) ) );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$written = array();
foreach ( array_merge( glob( __DIR__ . '/../includes/*.php' ), glob( __DIR__ . '/../includes/modules/*.php' ) ) as $file ) {
	$times = substr_count( (string) file_get_contents( $file ), "'" . $reload . "'" );
	if ( $times > 0 ) {
		$written[ basename( $file ) ] = $times;
	}
}
ck( 'and the reload is one sentence, written once, in the accessor the Institutions and Sponsors screens both read', array(
	$written,
	method_exists( 'WPCPM_Sync_Module', 'failed_message' ) ? WPCPM_Sync_Module::failed_message() : null,
	substr_count( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-institutions.php' ), 'self::failed_message()' ),
	substr_count( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' ), 'self::failed_message()' ),
), array( array( 'class-wpcpm-sync-module.php' => 1 ), array( 'error', $reload ), 1, 1 ) );

// Create account is pressed while its sponsor is not Approved any more, so the handler refuses it and
// creates nothing, and Attach account, on Mango Example's accounts, with an address no account has:
// where a press lands is its own to say, whatever the outcome. Create account is pressed as the
// Sponsors card's form posted it, naming its old tab: it is the Accounts tab's list that creates
// accounts now, and that is where it comes back to (a row's link, and the list as it stood, are
// bin/test-sponsors-accounts.php's). Attach account comes back to the accounts it was pressed on.
WPCPM_Sponsors_Index::patch( $A, array( 'status' => 'Paused' ) );
$r_create = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_tab' => 'sponsors' ), array( $module, 'handle_provision' ) );
WPCPM_Sponsors_Index::patch( $A, array( 'status' => WPCPM_Sponsors_Index::STATUS_APPROVED ) );
$attach_form = form_fields_of( render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $A ) ), WPCPM_Sponsors::ACTION_MEMBERS );
$r_attach    = post( array_merge( isset( $attach_form[0] ) ? $attach_form[0] : array(), array( 'wpcpm_email' => 'nobody@example.test' ) ), array( $module, 'handle_members' ) );
ck( 'Create account comes back to the Accounts tab, and Attach account to the accounts of the sponsor it was pressed for, on that tab, each with its outcome', array( $r_create, $r_attach ), array(
	array( 'redirect', $home . '&tab=accounts', 'provision-inactive' ),
	array( 'redirect', $home . '&tab=accounts&wpcpm_sponsor=' . $A, 'attach-no-account' ),
) );
// A press of the members handler comes back to a sponsor's accounts only when it names both the
// Accounts tab and the sponsor, as the forms there post them: one that names the tab alone comes
// back to the tab itself, and one that names a sponsor and another tab to that tab.
$r_no_sponsor = post( array( 'wpcpm_op' => 'attach', 'wpcpm_email' => 'nobody@example.test', 'wpcpm_tab' => 'accounts' ), array( $module, 'handle_members' ) );
$r_other_tab  = post( array( 'wpcpm_sponsor' => $A, 'wpcpm_op' => 'attach', 'wpcpm_email' => 'nobody@example.test', 'wpcpm_tab' => 'sponsors' ), array( $module, 'handle_members' ) );
ck( 'a press that names the Accounts tab and no sponsor comes back to the tab itself, and one that names a sponsor and another tab to that tab', array( $r_no_sponsor, $r_other_tab ), array(
	array( 'redirect', $home . '&tab=accounts', 'attach-no-account' ),
	array( 'redirect', $home . '&tab=sponsors', 'attach-no-account' ),
) );
ck( 'a sponsor\'s accounts are the Accounts tab with the sponsor\'s record in the address, encoded as a query value', array(
	method_exists( 'WPCPM_Sponsors', 'accounts_view_url' ) ? WPCPM_Sponsors::accounts_view_url( $B ) : 'no accounts_view_url()',
	method_exists( 'WPCPM_Sponsors', 'accounts_view_url' ) ? WPCPM_Sponsors::accounts_view_url( 'rec a&b #1' ) : 'no accounts_view_url()',
), array( $home . '&tab=accounts&wpcpm_sponsor=' . $B, $home . '&tab=accounts&wpcpm_sponsor=rec%20a%26b%20%231' ) );

// The page the redirect opens shows the outcome its press flashed: once, on the tab its address
// names, above the bar, and not again on the next page. Attach account's opens the sponsor's
// accounts it was pressed on, under the bar.
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, (string) $r_attach[2] );
$landed = render_screen( link_of( (string) $r_attach[1] )[1] );
$again  = render_screen( link_of( (string) $r_attach[1] )[1] );
$said   = '<div class="notice notice-error is-dismissible"><p>No account has that address. Attach account attaches an account that exists; a sponsor&#039;s first account is made with Create account on the No account view.</p></div>';
ck( 'the outcome a press leaves prints once, on the tab it comes back to, above the bar and every card, and not on the next page: Attach account\'s above the accounts of the sponsor it was pressed for', array(
	substr_count( $landed, $said ),
	false !== strpos( $landed, $said ) && strpos( $landed, $said ) < (int) strpos( $landed, '<nav class="nav-tab-wrapper' ),
	bar_of( $landed ) === $bar_for( 'accounts' ),
	false !== strpos( $landed, '</nav><div class="wpcpm-card"><h2>Mango Example</h2>' ),
	notices_in( $again ),
), array( 1, true, true, true, '' ) );

echo "\n=== mark_dashboard_account() ===\n";
// Both false alone would pass even with the is_key() guard deleted, since patch() refuses a
// malformed key on its own account (post_id_of() fails the same is_key() check); what pins the
// contract is that neither call leaves a mark: the store, freshly read and not the request's
// cached copy, is byte for byte what it was, and the unknown key never became a row.
WPCPM_Sponsors_Index::forget();
$before_flag = WPCPM_Sponsors_Index::rows();
$malformed   = WPCPM_Sponsors::mark_dashboard_account( 'nope', true );
$unknown     = WPCPM_Sponsors::mark_dashboard_account( 'spoZZZZ0000000009', true );
WPCPM_Sponsors_Index::forget();
ck( 'a malformed key and an unknown key both answer false and touch nothing', array( $malformed, $unknown, WPCPM_Sponsors_Index::rows(), WPCPM_Sponsors_Index::has( 'spoZZZZ0000000009' ) ), array( false, false, $before_flag, false ) );

echo "\n=== The Offers card and its handlers ===\n";
// Run here, before Uninstall: the Uninstall section below calls the real uninstall(), which
// also deletes every offer, pool and claim, so the offer seeded above must be exercised before
// that happens. The uninstall-side check on this same offer is appended after the Uninstall
// section's own call instead of calling uninstall() a second time here, which would both
// double-count that section's $GLOBALS['deleted_meta'] tally and erase every account's
// sponsor-member stamp before its "some account still carries this module's stamps before
// uninstall" check runs.
// B gets a live account, attached directly through WPCPM_Sponsor_Members::attach() rather than
// through WPCPM_Sponsors::provision(), so no offer is seeded for it and the Seed button's own
// condition (an account, no offer) is met by someone. Without it, accounts_by_sponsor() holds
// nothing for any sponsor at this point in the run (A's own account was detached above and never
// reattached), and the Seed checks below would have no button to read.
WPCPM_Sponsors_Index::patch( $B, array( 'contact_email' => 'maciej@a8c.com' ) );
$rep_b                      = 60;
$GLOBALS['users'][ $rep_b ] = new WP_User( $rep_b, array( 'subscriber' ), 'Rep B', 'repb@example.test' );
WPCPM_Sponsor_Members::attach( $rep_b, $B, WPCPM_Sponsor_Members::HOW_MANAGER, 1 );
$screen = render_tab( 'offers' );
ck( 'the Offers and codes tab has the Offers card with the seeded offer and its counts', array( false !== strpos( $screen, 'id="wpcpm-sponsor-offers"' ), false !== strpos( $screen, 'Not switched on yet' ), false !== strpos( $screen, '<td>0</td>' ) ), array( true, true, true ) );
ck( 'a sponsor with an account and no offer gets a Seed button with its own nonce', false !== strpos( $screen, 'nonce-' . WPCPM_Sponsors::ACTION_SEED . '_' . $B ), true );
ck( 'and one with an offer does not', strpos( $screen, 'nonce-' . WPCPM_Sponsors::ACTION_SEED . '_' . $A ), false );
// Seed is the tab's one form here (nobody has claimed yet), so the attribute is read on a tab that draws it.
$seed_form = form_fields_of( $screen, WPCPM_Sponsors::ACTION_SEED );
ck( 'Seed carries the once attribute, saying it is seeding, and names the tab its press comes back to', array(
	post_forms( $screen ),
	post_forms( $screen, true ),
	false !== strpos( $screen, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Seeding">' ),
	isset( $seed_form[0][ WPCPM_Sponsors::TAB_FIELD ] ) ? $seed_form[0][ WPCPM_Sponsors::TAB_FIELD ] : '',
), array( array( WPCPM_Sponsors::ACTION_SEED ), array(), true, 'offers' ) );

echo "\n=== The posting switch, per sponsor ===\n";
// B carries a live account (Rep B, attached above), so this is a sponsor with an account, as the
// switch is meant for. The switch is drawn on the sponsor's accounts, a view of the Accounts tab.
$screen = render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $B ) );
ck( 'each sponsor with accounts has the posting switch on its accounts, keyed to its record', array(
	false !== strpos( $screen, '<input type="hidden" name="action" value="wpcpm_sponsor_flags" />' ),
	false !== strpos( $screen, 'wpcpm_sponsor_flags_' . $B ),
	false !== strpos( $screen, 'name="wpcpm_on" value="0"' ),
	false !== strpos( $screen, 'Turn posting off' ),
), array( true, true, true, true ) );
$GLOBALS['posting_off'][ $B ] = true;
$screen = render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $B ) );
ck( 'with posting off the switch offers to turn it on', array( false !== strpos( $screen, 'name="wpcpm_on" value="1"' ), false !== strpos( $screen, 'Turn posting on' ), false !== strpos( $screen, 'Posting is off for this sponsor.' ) ), array( true, true, true ) );
$GLOBALS['posting_off'] = array();
// The switch's press comes back to the sponsor's accounts (`WPCPM_Sponsor_Posts::handle_flags()`,
// pressed in bin/test-sponsor-posts.php), and that page says what it did in the switch's own words.
$switch_lands = link_of( WPCPM_Sponsors::accounts_view_url( $B ) )[1];
$posting_said = array();
foreach ( array( 'posting-off', 'posting-on' ) as $outcome ) {
	WPCPM_Flash::set( WPCPM_Sponsors::FLASH, $outcome );
	$drawn                    = render_screen( $switch_lands );
	$posting_said[ $outcome ] = array( notices_in( $drawn ), (int) strpos( $drawn, 'is-dismissible' ) < (int) strpos( $drawn, '<nav class="nav-tab-wrapper' ) );
}
ck( 'the posting switch\'s two outcomes are said in their own words, once, above the bar, on the sponsor\'s accounts its press comes back to', $posting_said, array(
	'posting-off' => array( '<div class="notice notice-success is-dismissible"><p>Posting is off for this sponsor: its accounts can no longer write posts.</p></div>', true ),
	'posting-on'  => array( '<div class="notice notice-success is-dismissible"><p>Posting is on for this sponsor: its accounts can write posts and submit them for review.</p></div>', true ),
) );

echo "\n=== The Accounts tab, with an account on it ===\n";
// Rep B acts for B, attached above and never invited, so the tab draws its list with a row in it and
// the invitations card with its button; and the ceiling the roster reads has locked Rep B out of
// changes for the rest of today, so the tab names Rep B above the list.
$GLOBALS['ceiling_counts'] = array( WPCPM_Refusal_Meter::key( WPCPM_Sponsor_Roster::METER_SCOPE, WPCPM_Refusal_Meter::STEM_REFUSED, $GLOBALS['users'][ $rep_b ] ) => WPCPM_Refusal_Meter::PER_DAY );
$with_account              = render_tab( 'accounts' );
$with_account_read         = reads_now();
$locked_on                 = array();
// Every tab drawn as itself, the launch switch on, so Handbook listing is one of them.
$GLOBALS['settings']['sponsor_tiers_live'] = 1;
foreach ( $slugs as $slug ) {
	$locked_on[ $slug ] = substr_count( render_tab( $slug ), 'locked out of changes for the rest of today' );
}
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$GLOBALS['ceiling_counts'] = array();
$at                        = array(
	'locked'      => strpos( $with_account, '<div class="notice notice-warning"><p><strong>1 sponsor account is locked out of changes for the rest of today.</strong>' ),
	'invitations' => strpos( $with_account, '<div class="wpcpm-card wpcpm-invites">' ),
	'list'        => strpos( $with_account, '<h2>Sponsor accounts <span class="wpcpm-count">1</span></h2>' ),
);

ck( 'the tab draws, under the bar, the accounts locked today, the invitations card and the list, in that order, Rep B a row of it, and no sponsor\'s accounts: no other card, no Remove and no posting switch', array(
	! in_array( false, $at, true ) && $at['locked'] > strpos( $with_account, '</nav>' ) && $at['locked'] < $at['invitations'] && $at['invitations'] < $at['list'],
	false !== strpos( $with_account, 'id="wpcpm-account-' . $rep_b . '"' ),
	cards_of( $with_account ),
	form_fields_of( $with_account, WPCPM_Sponsors::ACTION_MEMBERS ),
	form_fields_of( $with_account, WPCPM_Sponsor_Posts::ACTION_FLAGS ),
), array( true, true, array( 'Invitations', 'Sponsor accounts' ), array(), array() ) );
ck( 'and no address is printed anywhere on it: the locked account is named by name and username, the list prints none', array( strpos( $with_account, '@' ), false !== strpos( $with_account, 'Rep B (repb)' ) ), array( false, true ) );
ck( 'the locked accounts are named by name and username, on the Accounts tab and on no other', array( false !== strpos( $with_account, 'Each was refused more requests than the daily ceiling allows: Rep B (repb).' ), $locked_on ), array( true, array(
	'queue'      => 0,
	'sponsors'   => 0,
	'accounts'   => 1,
	'offers'     => 0,
	'interests'  => 0,
	'reports'    => 0,
	'agreements' => 0,
	'listing'    => 0,
) ) );
ck( 'the invitations card counts Rep B and posts its own action, naming the Accounts tab, and every form the module draws on the tab carries the once attribute', array(
	form_fields_of( $with_account, WPCPM_Sponsors::ACTION_BULK ),
	false !== strpos( invitations_of( $with_account ), '>Invite 1 sponsor account that has never been invited</button>' ),
	post_forms( without_invitations( $with_account ), true ),
), array( array( array( 'action' => 'wpcpm_sponsors_bulk_invite', 'wpcpm_tab' => 'accounts' ) ), true, array() ) );
ck( 'the one form on it whose press comes back by the tab it names, the invitations card\'s, names the Accounts tab', tab_fields( $with_account ), array( 'accounts' => 1 ) );
ck( 'and it reads the index and the accounts, and nothing of the queue or the agreements', $with_account_read, array(
	'index'             => true,
	'accounts'          => true,
	'applications'      => false,
	'posts'             => false,
	'waiting documents' => false,
	'out of force'      => false,
) );

echo "\n=== One sponsor's accounts, a view of the Accounts tab ===\n";
// Wexample (B) is Approved and has Rep B, attached above; Mango Example (A) is Approved with no
// account; Ember Example (C) is Paused and is given an account for this section alone, as a sponsor
// that stopped being Approved keeps its accounts. Rep B is locked out of changes for today while
// Wexample's accounts are drawn, so the view is seen to leave out the locked accounts' notice as it
// leaves out the invitations card and the list.
$GLOBALS['ceiling_counts'] = array( WPCPM_Refusal_Meter::key( WPCPM_Sponsor_Roster::METER_SCOPE, WPCPM_Refusal_Meter::STEM_REFUSED, $GLOBALS['users'][ $rep_b ] ) => WPCPM_Refusal_Meter::PER_DAY );
$view_b                    = render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $B ) );
$view_b_read               = reads_now();
$view_b_queries            = array( $GLOBALS['user_queries'], $GLOBALS['list_queries'] );
$GLOBALS['ceiling_counts'] = array();
$view_b_body               = (string) strstr( $view_b, '</nav>' );
$drawn_at                  = array();
foreach ( array(
	'name'    => '<div class="wpcpm-card"><h2>Wexample</h2>',
	'back'    => '>Back to the accounts</a>',
	'account' => '<ul class="wpcpm-list"><li>Rep B (repb@example.test) <form',
	'attach'  => '<input type="hidden" name="wpcpm_op" value="attach" />',
	'posting' => '<p class="description">Posting is on: 1 account can write posts in wp-admin; an Administrator publishes them.</p>',
	'switch'  => '>Turn posting off</button></form>',
) as $part => $needle ) {
	$drawn_at[ $part ] = strpos( $view_b_body, $needle );
}
$drawn_in_order = $drawn_at;
asort( $drawn_in_order );

ck( 'with a sponsor in its address the Accounts tab draws that sponsor\'s accounts in place of the accounts locked today, the invitations card and the list: one card under the bar holding the sponsor\'s name, the way back to the accounts, Rep B by name and address with Remove, the Attach account form, then the posting sentence and its switch, in that order', array(
	in_array( false, $drawn_at, true ) ? $drawn_at : array_keys( $drawn_in_order ),
	substr_count( $view_b, '<div class="wpcpm-card' ),
	strpos( $view_b, 'locked out of changes' ),
	strpos( $view_b, 'wpcpm-invites' ),
	strpos( $view_b, '<form method="get"' ),
	strpos( $view_b, 'Sponsor accounts' ),
	bar_of( $view_b ) === $bar_for( 'accounts' ),
), array( array( 'name', 'back', 'account', 'attach', 'posting', 'switch' ), 1, false, false, false, false, true ) );

// A search kept whole on its way back is bin/test-sponsors-accounts.php's, against core's port of
// add_query_arg(); this suite's encodes what it is given.
$opened_from = render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $B, 'wpcpm_view' => 'never-invited', 'orderby' => 'sponsor', 'order' => 'desc', 'paged' => '2' ) );
$back_of     = static function ( $html ) {
	return link_of( preg_match( '#<a href="([^"]*)">Back to the accounts</a>#', (string) $html, $found ) ? $found[1] : '' );
};
ck( 'its way back is the list as it stood when the link that opened it was followed, its view, sort and page, which that link carried; opened with none, the Accounts tab itself', array( $back_of( $opened_from ), $back_of( $view_b ) ), array(
	array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_view' => 'never-invited', 'orderby' => 'sponsor', 'order' => 'desc', 'paged' => '2' ) ),
	array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts' ) ),
) );
ck( 'every form on it posts the Accounts tab and the sponsor beside its own fields, so a press made there comes back to it: Rep B\'s Remove and Attach account under the members handler\'s nonce, the switch under the one keyed to the sponsor', array(
	form_fields_of( $view_b, WPCPM_Sponsors::ACTION_MEMBERS ),
	form_fields_of( $view_b, WPCPM_Sponsor_Posts::ACTION_FLAGS ),
	tab_fields( $view_b ),
), array(
	array(
		array( '_wpnonce' => 'nonce-wpcpm_sponsor_members', 'action' => 'wpcpm_sponsor_members', 'wpcpm_op' => 'detach', 'wpcpm_sponsor' => $B, 'wpcpm_tab' => 'accounts', 'wpcpm_user' => (string) $rep_b ),
		array( '_wpnonce' => 'nonce-wpcpm_sponsor_members', 'action' => 'wpcpm_sponsor_members', 'wpcpm_op' => 'attach', 'wpcpm_sponsor' => $B, 'wpcpm_tab' => 'accounts' ),
	),
	array( array( '_wpnonce' => 'nonce-wpcpm_sponsor_flags_' . $B, 'action' => 'wpcpm_sponsor_flags', 'wpcpm_on' => '0', 'wpcpm_sponsor' => $B, 'wpcpm_tab' => 'accounts' ) ),
	array( 'accounts' => 3 ),
) );
ck( 'each is an inline form posting to admin-post.php, with the once attribute and its busy word: Remove with its confirm, Attach account with the address it asks for, and the switch', array(
	post_forms( $view_b ),
	post_forms( $view_b, true ),
	false !== strpos( $view_b, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Removing" data-wpcpm-confirm="Remove this account from the sponsor?">' ),
	false !== strpos( $view_b, '<button type="submit" class="button-link-delete">Remove</button></form></li></ul>' ),
	false !== strpos( $view_b, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Attaching">' ),
	false !== strpos( $view_b, '<label class="screen-reader-text" for="wpcpm-attach-' . $B . '">Email address</label><input type="email" id="wpcpm-attach-' . $B . '" name="wpcpm_email" placeholder="name@company.example" required /> <button type="submit" class="button">Attach account</button></form>' ),
	false !== strpos( $view_b, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Switching">' ),
), array( array( 'wpcpm_sponsor_members', 'wpcpm_sponsor_members', 'wpcpm_sponsor_flags' ), array(), true, true, true, true, true ) );
ck( 'it reads the index and that sponsor\'s accounts alone: no list is built for it, the live accounts of every sponsor are not read, and nothing of the queue or the agreements', array( $view_b_read, $view_b_queries ), array(
	array(
		'index'             => true,
		'accounts'          => false,
		'applications'      => false,
		'posts'             => false,
		'waiting documents' => false,
		'out of force'      => false,
	),
	array( array( WPCPM_Sponsor_Members::META_RECORD_ID => 1 ), array() ),
) );

$view_a = render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $A ) );
ck( 'an Approved sponsor with no account yet says so, and offers Attach account and its posting switch', array(
	false !== strpos( $view_a, '<h2>Mango Example</h2><p><a href="' . $home . '&tab=accounts">Back to the accounts</a></p><p class="description">No account yet.</p><form method="post"' ),
	post_forms( $view_a ),
	strpos( $view_a, '<ul' ),
), array( true, array( 'wpcpm_sponsor_members', 'wpcpm_sponsor_flags' ), false ) );

$cara                      = 62;
$GLOBALS['users'][ $cara ] = new WP_User( $cara, array( 'subscriber' ), 'Cara Rep', 'cara@example.test' );
$cara_attached             = WPCPM_Sponsor_Members::attach( $cara, $C, WPCPM_Sponsor_Members::HOW_MANAGER, 1 );
$view_c                    = render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $C ) );
WPCPM_Sponsor_Members::detach( $cara, WPCPM_Sponsor_Members::REASON_REMOVED, 1 );
ck( 'a sponsor that is not Approved is drawn its accounts with Remove, then one sentence saying why nothing else is offered: no Attach account form and no posting switch', array(
	true === $cara_attached,
	false !== strpos( $view_c, '</nav><div class="wpcpm-card"><h2>Ember Example</h2><p><a href="' . $home . '&tab=accounts">Back to the accounts</a></p><ul class="wpcpm-list"><li>Cara Rep (cara@example.test) <form' ),
	false !== strpos( $view_c, '</form></li></ul><p class="description">This sponsor is not Approved, so no account can be attached to it and its posting cannot be switched.</p></div>' ),
	form_fields_of( $view_c, WPCPM_Sponsors::ACTION_MEMBERS ),
	form_fields_of( $view_c, WPCPM_Sponsor_Posts::ACTION_FLAGS ),
	strpos( $view_c, 'Posting is' ),
	post_forms( $view_c, true ),
), array(
	true,
	true,
	true,
	array( array( '_wpnonce' => 'nonce-wpcpm_sponsor_members', 'action' => 'wpcpm_sponsor_members', 'wpcpm_op' => 'detach', 'wpcpm_sponsor' => $C, 'wpcpm_tab' => 'accounts', 'wpcpm_user' => (string) $cara ) ),
	array(),
	false,
	array(),
) );

// A value the index does not hold names no sponsor, whatever it looks like: a record the site never
// held, a real one in another case (a key is case-sensitive), no key at all, and markup.
$asked_for = array( 'recSPONSOR0000099', strtolower( $B ), 'not-a-record', '<b>x</b>' );
$unknown   = array();
foreach ( $asked_for as $asked ) {
	$drawn             = render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $asked ) );
	$unknown[ $asked ] = array(
		false !== strpos( $drawn, '</nav><div class="wpcpm-card"><p>That link names no sponsor on this site. A sponsor is added on the <a href="' . $home . '&tab=sponsors">Sponsors</a> tab.</p><p><a href="' . $home . '&tab=accounts">Back to the accounts</a></p></div></div>' ),
		strpos( $drawn, $asked ),
		strpos( $drawn, esc_html( $asked ) ),
		$GLOBALS['user_queries'],
		$GLOBALS['list_queries'],
	);
}
ck( 'a value the index does not hold, a real record in another case among them, one that is no key and one that is markup, is said to be no sponsor\'s, with where a sponsor is added, the Sponsors tab, and the way back to the list; nothing else is drawn or read for it, and the value is never printed', $unknown, array_fill_keys( $asked_for, array( true, false, false, array(), array() ) ) );

$module_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'the card that drew every Approved sponsor\'s accounts under the list is gone and nothing calls it: one sponsor\'s accounts are drawn by their view alone', array(
	method_exists( 'WPCPM_Sponsors', 'render_members' ),
	preg_match_all( '/(?:->|::)render_members\(/', $module_src ),
	method_exists( 'WPCPM_Sponsors', 'render_sponsor_accounts' ),
), array( false, 0, true ) );

// The other accounts screens warn while the connection their syncs need is missing, through the
// printer they share; this one has no sync to warn for, so with no connection no tab and no sponsor's
// accounts print a warning, nor a way to Settings, and the frame calls no printer of one and keeps no
// words for it. Nor does any of them name Airtable or a sync in the words a manager reads, whatever
// class draws them. The launch switch is on for these draws, so the Handbook listing tab is drawn
// too.
$GLOBALS['connected']                      = false;
$GLOBALS['settings']['sponsor_tiers_live'] = 1;
$warned                                    = array();
foreach ( array_merge( $slugs, array( 'one sponsor' ) ) as $slug ) {
	$drawn           = 'one sponsor' === $slug ? render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $B ) ) : render_tab( $slug );
	$warned[ $slug ] = array( substr_count( $drawn, 'not connected' ), substr_count( $drawn, 'notice-warning' ), substr_count( $drawn, 'page=wpcpm-settings' ), substr_count( $drawn, 'Airtable' ), preg_match_all( '/\bsync/i', strip_tags( $drawn ) ) );
}
$GLOBALS['connected'] = true;
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
ck( 'with no connection no tab and no sponsor\'s accounts print a warning about one or name Airtable or a sync, and the frame neither calls the shared warning nor keeps its words', array(
	$warned,
	strpos( method_body( $module_src, 'render_admin_page' ), 'render_not_connected' ),
	strpos( method_body( $module_src, 'screen_words' ), 'not_connected' ),
), array( array_fill_keys( array_merge( $slugs, array( 'one sponsor' ) ), array( 0, 0, 0, 0, 0 ) ), false, false ) );

$r        = post( isset( $seed_form[0] ) ? $seed_form[0] : array(), array( $module, 'handle_seed' ) );
$seeded_b = WPCPM_Sponsor_Offers::offers_of( $B );
ck( 'the manager presses B\'s Seed button: one draft offer, marked primary, logged as seeded, and the press comes back to the Offers and codes tab', array( $r[2], count( $seeded_b ), reset( $seeded_b )['state'], reset( $seeded_b )['primary'], end( $GLOBALS['audit'] )['kind'], $r[1] ), array( 'offer-seeded', 1, 'draft', true, WPCPM_Sponsor_Offers::LOG_SEEDED, $home . '&tab=offers' ) );
// The first offer is created in draft for the sponsor to complete, not seeded from the base, and
// its audit line names nobody by role.
ck( 'and the audit line says the first offer was created in draft for the sponsor to complete', end( $GLOBALS['audit'] )['message'], 'The first offer was created in draft; the sponsor completes it and switches it on from the Sponsor Dashboard.' );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, (string) $r[2] );
$seeded_page = render_screen( link_of( (string) $r[1] )[1] );
ck( 'and the Offers and codes tab it lands on says so in Seed\'s own words, once, above the bar', array( notices_in( $seeded_page ), (int) strpos( $seeded_page, 'is-dismissible' ) < (int) strpos( $seeded_page, '<nav class="nav-tab-wrapper' ), bar_of( $seeded_page ) === $bar_for( 'offers' ) ), array( '<div class="notice notice-success is-dismissible"><p>The first offer was created in draft; the sponsor completes it and switches it on from the Sponsor Dashboard.</p></div>', true, true ) );
$r = post( array( 'wpcpm_sponsor' => $B ), array( $module, 'handle_seed' ) );
ck( 'seeding it again does nothing, since it already has one', $r[2], 'offer-seed-none' );
$r = post( array( 'wpcpm_sponsor' => 'recNOTINDEXED0001' ), array( $module, 'handle_seed' ) );
ck( 'an unindexed record is refused before seeding', $r[2], 'refused' );
$offer_id = (int) key( WPCPM_Sponsor_Offers::offers_of( $A ) );
WPCPM_Sponsor_Offers::add_codes( $offer_id, "S-1\nS-2" );
WPCPM_Sponsor_Offers::set_state( $offer_id, 'live' );
$GLOBALS['program'] = array();
$claimant = ( new WP_User( 77, array( 'wpcpm_student' ), 'Student Seven', 'maciej@a8c.com' ) );
$GLOBALS['users'][77] = $claimant;
WPCPM_Sponsor_Codes::take( $offer_id, 77 );
update_user_meta( 77, WPCPM_Sponsor_Claims::META_CLAIMS, array( $offer_id => array( 'i' => 0, 'at' => time() ) ) );
$screen = render_tab( 'offers' );
ck( 'a claimant is listed to the manager with name, address, the last four characters and a Void button', array( false !== strpos( $screen, 'Student Seven' ), false !== strpos( $screen, 'maciej@a8c.com' ), false !== strpos( $screen, '>S-1<' ) || false !== strpos( $screen, 'S-1' ), false !== strpos( $screen, 'nonce-' . WPCPM_Sponsors::ACTION_CLAIM_VOID . '_' . $offer_id . '_77' ) ), array( true, true, true, true ) );
$void_form = form_fields_of( $screen, WPCPM_Sponsors::ACTION_CLAIM_VOID );
ck( 'Void carries the once attribute too, saying it is voiding, keeps its confirm, and names the tab its press comes back to', array(
	post_forms( $screen, true ),
	false !== strpos( $screen, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Voiding" data-wpcpm-confirm="Void this claim? The person may then claim again.">' ),
	isset( $void_form[0][ WPCPM_Sponsors::TAB_FIELD ] ) ? $void_form[0][ WPCPM_Sponsors::TAB_FIELD ] : '',
), array( array(), true, 'offers' ) );
$GLOBALS['uid'] = 1;
// post()'s redirect outcome is array( 'redirect', <url>, <flashed status> ) (see post() below),
// so $r[2] is what carries the flashed status.
$r = post( isset( $void_form[0] ) ? $void_form[0] : array(), array( $module, 'handle_claim_void' ) );
ck( 'a manager presses Void: the claim is voided, the manager is told, and the press comes back to the Offers and codes tab', array( $r[2], WPCPM_Sponsor_Claims::has_claimed( 77, $offer_id ), WPCPM_Sponsor_Codes::counts( $offer_id )['void'], $r[1] ), array( 'claim-voided', false, 1, $home . '&tab=offers' ) );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, (string) $r[2] );
$voided_page = render_screen( link_of( (string) $r[1] )[1] );
ck( 'and the Offers and codes tab it lands on says so in Void\'s own words, once, above the bar', array( notices_in( $voided_page ), (int) strpos( $voided_page, 'is-dismissible' ) < (int) strpos( $voided_page, '<nav class="nav-tab-wrapper' ), bar_of( $voided_page ) === $bar_for( 'offers' ) ), array( '<div class="notice notice-success is-dismissible"><p>The claim was voided. The person may claim again; the code stays void for the count.</p></div>', true, true ) );
$r = post( array( 'wpcpm_offer' => $offer_id, 'wpcpm_user' => 77 ), array( $module, 'handle_claim_void' ) );
ck( 'voiding again finds nothing', $r[2], 'claim-void-none' );
$r = post( array( 'wpcpm_sponsor' => $A ), array( $module, 'handle_seed' ) );
ck( 'seeding a sponsor that has an offer does nothing', $r[2], 'offer-seed-none' );
$GLOBALS['uid'] = 5;
ck( 'a sponsor member cannot void a claim', post( array( 'wpcpm_offer' => $offer_id, 'wpcpm_user' => 77 ), array( $module, 'handle_claim_void' ) )[0], 'die' );
// handle_seed() refuses a sponsor member as handle_claim_void() does, pressed by the fixture's
// one real sponsor member (Rep B, attached to B above) rather than a bare non-manager ID, and
// both handlers die on a bad nonce too.
$GLOBALS['uid'] = $rep_b;
ck( 'a sponsor member cannot seed an offer either', post( array( 'wpcpm_sponsor' => $B ), array( $module, 'handle_seed' ) )[0], 'die' );
$GLOBALS['uid']      = 1;
$GLOBALS['nonce_ok'] = false;
ck( 'a bad nonce dies for seeding too', post( array( 'wpcpm_sponsor' => $B ), array( $module, 'handle_seed' ) )[0], 'die' );
ck( 'and for voiding a claim, with a real offer and claimant so the nonce alone explains it', post( array( 'wpcpm_offer' => $offer_id, 'wpcpm_user' => 77 ), array( $module, 'handle_claim_void' ) )[0], 'die' );
$GLOBALS['nonce_ok'] = true;
$GLOBALS['uid']      = 1;

// Remove, pressed on Rep B's account on Wexample's accounts, the one live account here: the last
// press on this fixture before uninstall, since it leaves B with nobody.
$remove_form = array_values(
	array_filter(
		form_fields_of( render_tab( 'accounts', array( WPCPM_Sponsors::ARG_SPONSOR => $B ) ), WPCPM_Sponsors::ACTION_MEMBERS ),
		static function ( $fields ) {
			return isset( $fields['wpcpm_op'] ) && 'detach' === $fields['wpcpm_op'];
		}
	)
);
$r = post( isset( $remove_form[0] ) ? $remove_form[0] : array(), array( $module, 'handle_members' ) );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r[2] ) ? (string) $r[2] : '' );
$after_remove = 'redirect' === $r[0] ? render_screen( link_of( (string) $r[1] )[1] ) : '';
$removed_said = '<div class="notice notice-success is-dismissible"><p>The account no longer acts for the sponsor.</p></div>';
ck( 'Remove comes back to the accounts it was pressed on, with its outcome, printed once above the bar, over Wexample with no account left', array(
	count( $remove_form ),
	$r,
	substr_count( $after_remove, $removed_said ),
	false !== strpos( $after_remove, $removed_said ) && strpos( $after_remove, $removed_said ) < (int) strpos( $after_remove, '<nav class="nav-tab-wrapper' ),
	false !== strpos( $after_remove, '</nav><div class="wpcpm-card"><h2>Wexample</h2>' ) && false !== strpos( $after_remove, '<p class="description">No account yet.</p>' ),
), array( 1, array( 'redirect', $home . '&tab=accounts&wpcpm_sponsor=' . $B, 'detached' ), 1, true, true ) );

echo "\n=== A sponsor's Edit form, a view of the Sponsors tab ===\n";
// Mango Example (A) is Approved, with a contact and a program manager. The move onto the site is
// incomplete while its Edit form is drawn, and the tab is drawn without the key at the same moment,
// so the view is seen to leave out the migration box as it leaves out the Sponsors card and its Add
// form.
$moved_report = get_option( WPCPM_Sponsors_Migration::OPT_REPORT );
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, array( 'complete' => false, 'rows' => 3, 'posts' => 2, 'seeded' => 0, 'unseeded' => array(), 'unmapped' => array(), 'at' => time(), 'runs' => 1 ) );
$edit_a      = render_tab( 'sponsors', array( WPCPM_Sponsors::ARG_EDIT => $A ) );
$edit_a_read = reads_now();
$moving_tab  = render_tab( 'sponsors' );
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, $moved_report );
$edit_a_body = (string) strstr( $edit_a, '</nav>' );
$edit_at     = array();
foreach ( array(
	'name' => '</nav><div class="wpcpm-card" id="wpcpm-sponsor-edit"><h2>Edit Mango Example</h2>',
	'back' => '>Back to the sponsors</a>',
	'form' => '<form method="post" action="https://example.test/wp-admin/admin-post.php" data-wpcpm-once data-wpcpm-busy="Saving">',
	'save' => '>Save</button>',
	'key'  => 'Key: ' . $A . '; listing handle: ',
) as $part => $needle ) {
	$edit_at[ $part ] = strpos( $edit_a_body, $needle );
}
$edit_in_order = $edit_at;
asort( $edit_in_order );

ck( 'with a sponsor\'s key in its address the Sponsors tab draws that sponsor\'s Edit form in place of the migration box, the Sponsors card and its Add form: one card under the bar holding the sponsor\'s name, the way back to the sponsors, the form and its Save, then the key, in that order, the Sponsors tab marked in the bar, and the index read for it but no account', array(
	in_array( false, $edit_at, true ) ? $edit_at : array_keys( $edit_in_order ),
	cards_of( $edit_a ),
	cards_of( $moving_tab ),
	strpos( $edit_a, 'id="wpcpm-sponsor-add"' ),
	strpos( $edit_a, '<table class="wpcpm-table' ),
	bar_of( $edit_a ) === $bar_for( 'sponsors' ),
	array( $edit_a_read['index'], $edit_a_read['accounts'] ),
), array( array( 'name', 'back', 'form', 'save', 'key' ), array( 'Edit Mango Example' ), array( 'Moving sponsors onto the site', 'Sponsors' ), false, false, true, array( true, false ) ) );
$edit_elsewhere = array();
// Every other tab drawn as itself, the launch switch on, so Handbook listing is one of them.
$GLOBALS['settings']['sponsor_tiers_live'] = 1;
foreach ( array_diff( $slugs, array( 'sponsors' ) ) as $slug ) {
	$edit_elsewhere[ $slug ] = strpos( render_tab( $slug, array( WPCPM_Sponsors::ARG_EDIT => $A ) ), 'id="wpcpm-sponsor-edit"' );
}
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
ck( 'and it is the Sponsors tab\'s alone: the same key on any other tab draws that tab and no Edit form', $edit_elsewhere, array_fill_keys( array_diff( $slugs, array( 'sponsors' ) ), false ) );

$edit_link = static function ( $html, $pattern ) {
	return link_of( preg_match( $pattern, (string) $html, $found ) ? $found[1] : '' );
};
ck( 'its way back, and the Cancel beside Save, are the Sponsors tab, which lists every sponsor', array(
	$edit_link( $edit_a, '#<a href="([^"]*)">Back to the sponsors</a>#' ),
	$edit_link( $edit_a, '#>Save</button> <a href="([^"]*)">Cancel</a>#' ),
), array_fill( 0, 2, array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'sponsors' ) ) ) );

$edit_form = form_fields_of( $edit_a, WPCPM_Sponsors::ACTION_EDIT );
ck( 'its form posts the Sponsors tab beside the sponsor, under the nonce keyed to the sponsor, and carries the once attribute: the one form on the view, and the one there whose press comes back by the tab it names', array(
	$edit_form,
	post_forms( $edit_a ),
	post_forms( $edit_a, true ),
	tab_fields( $edit_a ),
), array(
	array( array( '_wpnonce' => 'nonce-wpcpm_sponsor_edit_' . $A, 'action' => 'wpcpm_sponsor_edit', 'wpcpm_sponsor' => $A, 'wpcpm_support[]' => '', 'wpcpm_tab' => 'sponsors', 'wpcpm_test' => '0' ) ),
	array( 'wpcpm_sponsor_edit' ),
	array(),
	array( 'sponsors' => 1 ),
) );

// A key the store does not hold names no sponsor, whatever it looks like: a key the site never held,
// a real one in another case (a key is case-sensitive), no key at all, and markup. The referer field
// core puts beside a nonce carries the address the page was drawn at, as on every page, so the value
// is looked for in everything but that field.
$asked_for = array( 'spoZZZZ0000000009', strtolower( $A ), 'not-a-key', '<b>x</b>' );
$unknown   = array();
foreach ( $asked_for as $asked ) {
	$drawn             = render_tab( 'sponsors', array( WPCPM_Sponsors::ARG_EDIT => $asked ) );
	$printed           = (string) preg_replace( '#<input type="hidden" name="_wp_http_referer" value="[^"]*" />#', '', $drawn );
	$unknown[ $asked ] = array(
		false !== strpos( $drawn, '</nav><div class="notice notice-warning"><p>That link names no sponsor on this site. Every sponsor on this site is listed on the Sponsors card below.</p></div><div class="wpcpm-card"><h2>Sponsors <span class="wpcpm-count">3</span></h2>' ),
		cards_of( $drawn ),
		strpos( $printed, $asked ),
		strpos( $printed, esc_html( $asked ) ),
	);
}
ck( 'a key the store does not hold, a real one in another case among them, one that is no key and one that is markup, draws the tab\'s cards under a notice that the link names no sponsor on this site and where every sponsor is listed, and the value is never printed', $unknown, array_fill_keys( $asked_for, array( true, array( 'Sponsors' ), false, false ) ) );

// Edit pressed as drawn changes nothing, and with the name emptied is refused: each comes back to the
// sponsor's Edit form. A form drawn for a sponsor the store no longer holds when it is pressed is
// refused, and comes back to the tab the form names.
$r_unchanged = post( isset( $edit_form[0] ) ? $edit_form[0] : array(), array( $module, 'handle_edit' ) );
$r_rejected  = post( array_merge( isset( $edit_form[0] ) ? $edit_form[0] : array(), array( 'wpcpm_name' => '' ) ), array( $module, 'handle_edit' ) );
$gone_key    = 'spoGONE0000000041';
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $gone_key, 'name' => 'Gone Example', 'status' => 'In review' ) ) );
$gone_form = form_fields_of( render_tab( 'sponsors', array( WPCPM_Sponsors::ARG_EDIT => $gone_key ) ), WPCPM_Sponsors::ACTION_EDIT );
wp_delete_post( WPCPM_Sponsors_Index::post_id_of( $gone_key ), true );
WPCPM_Sponsors_Index::forget();
$r_gone = post( isset( $gone_form[0] ) ? $gone_form[0] : array(), array( $module, 'handle_edit' ) );
ck( 'Edit\'s press comes back to the sponsor\'s Edit form, saved or refused, and one made for a sponsor the store no longer holds comes back to the Sponsors tab, each with its outcome', array( $r_unchanged, $r_rejected, $r_gone ), array(
	array( 'redirect', $home . '&tab=sponsors&edit=' . $A, 'sponsor-unchanged' ),
	array( 'redirect', $home . '&tab=sponsors&edit=' . $A, 'sponsor-rejected' ),
	array( 'redirect', $home . '&tab=sponsors', 'refused' ),
) );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_unchanged[2] ) ? (string) $r_unchanged[2] : '' );
$unchanged_page = 'redirect' === $r_unchanged[0] ? render_screen( link_of( (string) $r_unchanged[1] )[1] ) : '';
ck( 'the page a save lands on is the sponsor\'s Edit form, under the outcome, printed once above the bar', array(
	notices_in( $unchanged_page ),
	(int) strpos( $unchanged_page, 'is-dismissible' ) < (int) strpos( $unchanged_page, '<nav class="nav-tab-wrapper' ),
	bar_of( $unchanged_page ) === $bar_for( 'sponsors' ),
	false !== strpos( $unchanged_page, '</nav><div class="wpcpm-card" id="wpcpm-sponsor-edit"><h2>Edit Mango Example</h2>' ),
), array( '<div class="notice notice-info is-dismissible"><p>Nothing changed, so nothing was saved.</p></div>', true, true, true ) );

// Add, pressed with its form's fields and a name, lands on the new sponsor's Edit form.
$add_fields   = form_fields_of( render_tab( 'sponsors' ), WPCPM_Sponsors::ACTION_ADD );
$keys_before  = array_keys( WPCPM_Sponsors_Index::rows() );
$r_landing    = post( array_merge( isset( $add_fields[0] ) ? $add_fields[0] : array(), array( 'wpcpm_name' => 'Landing Example' ) ), array( $module, 'handle_add' ) );
$landed       = array_values( array_diff( array_keys( WPCPM_Sponsors_Index::rows() ), $keys_before ) );
$landed       = isset( $landed[0] ) ? (string) $landed[0] : '';
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_landing[2] ) ? (string) $r_landing[2] : '' );
$landing_page = 'redirect' === $r_landing[0] ? render_screen( link_of( (string) $r_landing[1] )[1] ) : '';
ck( 'Add lands on the new sponsor\'s Edit form, on this tab, which says it was added, once, above the bar', array(
	$r_landing,
	notices_in( $landing_page ),
	(int) strpos( $landing_page, 'is-dismissible' ) < (int) strpos( $landing_page, '<nav class="nav-tab-wrapper' ),
	bar_of( $landing_page ) === $bar_for( 'sponsors' ),
	false !== strpos( $landing_page, '</nav><div class="wpcpm-card" id="wpcpm-sponsor-edit"><h2>Edit Landing Example</h2>' ),
), array(
	array( 'redirect', $home . '&tab=sponsors&edit=' . $landed, 'sponsor-added' ),
	'<div class="notice notice-success is-dismissible"><p>The sponsor was added. Nothing else happens until its status is Approved and its account is created.</p></div>',
	true,
	true,
	true,
) );

// Run again, in the migration box while the move is incomplete, names this tab too: pressed while
// another run holds the move's lock, it moves nothing, and its press comes back to the tab, beside the
// box its words ask the reader to look at again.
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, array( 'complete' => false, 'rows' => 3, 'posts' => 2, 'seeded' => 0, 'unseeded' => array(), 'unmapped' => array(), 'at' => time(), 'runs' => 1 ) );
$migrate_form = form_fields_of( render_tab( 'sponsors' ), WPCPM_Sponsors::ACTION_MIGRATE );
update_option( WPCPM_Sponsors_Migration::OPT_LOCK, time() );
$r_busy = post( isset( $migrate_form[0] ) ? $migrate_form[0] : array(), array( $module, 'handle_migrate' ) );
delete_option( WPCPM_Sponsors_Migration::OPT_LOCK );
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, $moved_report );
ck( 'Run again posts the Sponsors tab under its own nonce, and its press comes back to that tab with its outcome', array( $migrate_form, $r_busy ), array(
	array( array( '_wpnonce' => 'nonce-wpcpm_sponsors_migrate', 'action' => 'wpcpm_sponsors_migrate', 'wpcpm_tab' => 'sponsors' ) ),
	array( 'redirect', $home . '&tab=sponsors', 'migrate-busy' ),
) );
// The sponsor Add made goes again, so nothing after this section counts it.
wp_delete_post( WPCPM_Sponsors_Index::post_id_of( $landed ), true );
WPCPM_Sponsors_Index::forget();
$GLOBALS['get'] = array();

echo "\n=== Uninstall ===\n";
// The last is a literal since 1.99.0: nothing writes it any more, and uninstall still removes
// what 1.93.0 to 1.98.1 wrote.
$meta_keys = array( WPCPM_Sponsor_Members::META_RECORD_ID, WPCPM_Sponsor_Members::META_ACTIVE, WPCPM_Sponsor_Members::META_RECORD_ID_WAS, WPCPM_Sponsor_Members::META_MEMBERSHIP, WPCPM_Sponsor_Members::META_INVITED, 'wpcpm_sponsor_profile' );
$before_users = count( $GLOBALS['users'] );
$stamped_before = array();
foreach ( $GLOBALS['users'] as $id => $user ) {
	foreach ( $meta_keys as $key ) {
		if ( '' !== get_user_meta( $id, $key, true ) ) {
			$stamped_before[] = $id . ':' . $key;
		}
	}
}
ck( 'some account still carries this module\'s stamps before uninstall (the scenario is real)', empty( $stamped_before ), false );
$module->uninstall();
// uninstall() also clears WPCPM_Sponsor_Claims::META_CLAIMS (its own check is below, "uninstall
// removes the offers, their pools and the claims"); filtered out here so this check pins exactly
// the six WPCPM_Sponsor_Members stamps this module's own loop deletes.
ck( 'uninstall() asks to delete every stamp this module owns', array_values( array_diff( $GLOBALS['deleted_meta'], array( WPCPM_Sponsor_Claims::META_CLAIMS ) ) ), $meta_keys );
$stamped_after = array();
foreach ( $GLOBALS['users'] as $id => $user ) {
	foreach ( $meta_keys as $key ) {
		if ( '' !== get_user_meta( $id, $key, true ) ) {
			$stamped_after[] = $id . ':' . $key;
		}
	}
}
ck( 'and every stamp is gone after uninstall, on every account', $stamped_after, array() );
ck( 'but no account was deleted', count( $GLOBALS['users'] ), $before_users );
ck( 'uninstall removes the offers, their pools and the claims', array( WPCPM_Sponsor_Offers::all(), get_option( WPCPM_Sponsor_Codes::option_name( $offer_id ) ), get_user_meta( 77, WPCPM_Sponsor_Claims::META_CLAIMS, true ) ), array( array(), false, '' ) );
$empty = render_tab( 'sponsors' );
ck( 'with no sponsor at all the index says how to add one, and the Add form is still there', array( false !== strpos( $empty, '<p>No sponsors yet. Add one below or approve an application.</p>' ), false !== strpos( $empty, 'nonce-wpcpm_sponsor_add' ) ), array( true, true ) );
// Uninstall took the move's report with the rest; this site had moved, so it is put back.
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, array( 'complete' => true, 'rows' => 3, 'posts' => 3, 'seeded' => 0, 'unseeded' => array(), 'unmapped' => array(), 'at' => time(), 'runs' => 1 ) );

echo "\n=== The agreement review on the Sponsors screen ===\n";
// The real WPCPM_Sponsor_Agreement is never loaded by this suite, so a stub stands in,
// answering from globals so each state below can be drawn. It declares all six SUMMARY_*
// constants, because agreement_word() below switches on all six (the real class declares all six
// too) and an undefined class constant is a fatal, not a warning. CRON_DISCARD and
// delete_all() are declared for the same reason from the other direction: PHP registers an
// unconditional top-level class the moment this file is compiled, not when execution reaches
// its declaration, so the Uninstall section far above (which calls the real uninstall(), and
// through it WPCPM_Sponsor_Agreement::delete_all() under a class_exists() guard that is
// already true) needs this stub to answer for those two as well, even though nothing below
// exercises them directly.
class WPCPM_Sponsor_Agreement {
	const ACTION_ACCEPT = 'wpcpm_sponsor_agr_accept';
	const ACTION_RETURN = 'wpcpm_sponsor_agr_return';
	const ACTION_REVOKE = 'wpcpm_sponsor_agr_revoke';
	const ACTION_REINSTATE = 'wpcpm_sponsor_agr_reinstate';
	const ACTION_ON_FILE = 'wpcpm_sponsor_agr_on_file';
	const ACTION_DOWNLOAD = 'wpcpm_sponsor_agr_download';
	const FIELD_POST = 'wpcpm_sponsor_agr_post';
	const FIELD_NOTE = 'wpcpm_sponsor_agr_note';
	const FIELD_DRIVE = 'wpcpm_sponsor_agr_drive';
	const MIN_NOTE = 20;
	const MAX_NOTE = 2000;
	const META_STATE = '_wpcpm_sagr_state';
	const STATE_REVOKED = 'revoked';
	const CRON_DISCARD = 'wpcpm_sponsor_agreement_discard';
	const SUMMARY_NONE = 'none';
	const SUMMARY_SUBMITTED = 'submitted';
	const SUMMARY_RETURNED = 'returned';
	const SUMMARY_REVOKED = 'revoked';
	const SUMMARY_ACCEPTED = 'accepted';
	const SUMMARY_ON_FILE = 'on_file';
	public static function awaiting_review( $limit = 200 ) {
		$GLOBALS['queries'] = ( isset( $GLOBALS['queries'] ) ? (int) $GLOBALS['queries'] : 0 ) + 1;
		$GLOBALS['stand_in_reads']['waiting documents'] = ( isset( $GLOBALS['stand_in_reads']['waiting documents'] ) ? (int) $GLOBALS['stand_in_reads']['waiting documents'] : 0 ) + 1;
		return array_slice( isset( $GLOBALS['queue'] ) ? $GLOBALS['queue'] : array(), 0, max( 1, (int) $limit ) );
	}
	// Every document out of force, oldest first, as the Administrator Dashboard's card reads them, as
	// many as asked for, each read counted and the number it asked for kept.
	public static function revoked_all( $limit = 200 ) {
		$GLOBALS['stand_in_reads']['out of force'] = ( isset( $GLOBALS['stand_in_reads']['out of force'] ) ? (int) $GLOBALS['stand_in_reads']['out of force'] : 0 ) + 1;
		$GLOBALS['stand_in_limits']['out of force'][] = (int) $limit;
		return array_slice( isset( $GLOBALS['revoked_ids'] ) ? $GLOBALS['revoked_ids'] : array(), 0, max( 1, (int) $limit ) );
	}
	public static function review_facts( $post_id ) { return isset( $GLOBALS['facts'][ $post_id ] ) ? $GLOBALS['facts'][ $post_id ] : array(); }
	// The question asked before Reinstate, in the stand-in's own words, so a check can tell the
	// screen printed the class's question and not one of its own.
	public static function reinstate_question() { return 'The agreement class asks before it reinstates.'; }
	public static function summary( $record ) { return isset( $GLOBALS['summaries'][ $record ] ) ? $GLOBALS['summaries'][ $record ] : array( 'state' => 'none', 'agreement_id' => 0, 'pending_id' => 0, 'accepted_at' => '', 'kind' => '', 'drive_url' => '', 'recorded_status' => '' ); }
	public static function posts_for( $record ) { return isset( $GLOBALS['agr_posts'][ $record ] ) ? $GLOBALS['agr_posts'][ $record ] : array(); }
	public static function manager_messages() { return array( 'agreement-accepted' => array( 'success', 'Accepted.' ) ); }
	// What a refused Take it out of force typed, handed back once, as the real class does.
	public static function kept_revoke_note( $post_id ) { $kept = $GLOBALS['kept_revoke'][ (int) $post_id ] ?? null; unset( $GLOBALS['kept_revoke'][ (int) $post_id ] ); return $kept; }
	public static function delete_all() {}
}

// Uninstall (above) wiped the index, so the TEST sponsor is written fresh rather than added to
// $A/$B/$C: nothing after this point reads their rows, and every check below keys off $T alone.
$T = 'recSPONSORTEST001';
WPCPM_Sponsors_Index::write( array(
	$T => array( 'name' => 'TEST Sponsor', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
), time() );

$GLOBALS['queue'] = array( 900 );
$GLOBALS['facts'] = array(
	900 => array( 'post_id' => 900, 'state' => 'submitted', 'kind' => 'own', 'sponsor' => $T, 'sponsor_name' => 'TEST Sponsor', 'uploaded_by' => 'Member One', 'uploaded_at' => '2026-09-05', 'original_name' => 'agreement.pdf', 'flags' => array( '/JavaScript' ), 'size' => 20480, 'members' => 2 ),
);
$GLOBALS['summaries'] = array( $T => array( 'state' => 'none', 'agreement_id' => 0, 'pending_id' => 900, 'accepted_at' => '', 'kind' => '', 'drive_url' => '', 'recorded_status' => 'Awaiting review' ) );

// The document waiting to be read is on the queue tab, read there and decided on the Administrator
// Dashboard: the review block is drawn as it was, the facts, the checklist, the scan and the download,
// and in the place of its two decisions the way to its own item on the dashboard's card.
$queue_tab = render_tab( 'queue' );
$review    = substr( $queue_tab, (int) strpos( $queue_tab, '<section class="wpcpm-review" id="wpcpm-review-900">' ) );
$review    = substr( $review, 0, (int) strpos( $review, '</section>' ) + strlen( '</section>' ) );

ck( 'the queue tab draws one review block with the facts a reviewer reads first', array(
	substr_count( $queue_tab, '<section class="wpcpm-review"' ),
	false !== strpos( $review, 'Review the signed agreement from TEST Sponsor' ),
	false !== strpos( $review, 'Member One' ),
	false !== strpos( $review, '2026-09-05' ),
	false !== strpos( $review, 'Read the whole document.' ),
), array( 1, true, true, true, true ) );
ck( 'the scan is named in words, with the PDF\'s own name for it, and said to be a courtesy', array( false !== strpos( $review, 'The scan noticed these in the file: JavaScript code (/JavaScript).' ), false !== strpos( $review, 'courtesy' ) ), array( true, true ) );
ck( 'the download is a link, keyed to the document', array( false !== strpos( $review, 'wpcpm_sponsor_agr_download' ), false !== strpos( $review, 'wpcpm_sponsor_agr_download_900' ) ), array( true, true ) );
// A stored name the scanner does not record is drawn as stored, and as text: a name holding a tag, a
// quote and an "&" beside one it words.
$kept_flags                     = $GLOBALS['facts'][900]['flags'];
$GLOBALS['facts'][900]['flags'] = array( '<b>"x"&</b>', '/AA' );
$hostile                        = render_tab( 'queue' );
$GLOBALS['facts'][900]['flags'] = $kept_flags;
ck( 'a stored name holding a tag is drawn as text in the review, escaped, beside one in words', array( false !== strpos( $hostile, 'The scan noticed these in the file: &lt;b&gt;&quot;x&quot;&amp;&lt;/b&gt;, actions that run when a page or a form field is used (/AA).' ), false !== strpos( $hostile, '<b>"x"' ) ), array( true, false ) );
ck( 'and neither Accept it nor Return it with this note is drawn: in their place, the way to the document\'s own item on the Administrator Dashboard, which decides it', array(
	substr_count( $queue_tab, '<form' ),
	strpos( $queue_tab, 'value="wpcpm_sponsor_agr_accept"' ),
	strpos( $queue_tab, 'value="wpcpm_sponsor_agr_return"' ),
	strpos( $queue_tab, 'Accept it' ),
	strpos( $queue_tab, 'Return it with this note' ),
	false !== strpos( $review, '<p class="wpcpm-agreement-panel__download"><a href="https://example.test/wp-admin/admin-post.php?action=wpcpm_sponsor_agr_download&post=900&_wpnonce=wpcpm_sponsor_agr_download_900">Download the signed agreement</a></p><p class="wpcpm-review__open"><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-agreement-900">Open on the Administrator Dashboard</a></p></section>' ),
), array( 0, false, false, false, false, true ) );
// The block only reads, whoever calls it: it takes the document alone and draws no form, so no call
// can put the two decisions back on this screen. Its last line is the institution review block's,
// class and all, so the two blocks end alike.
$review_body = method_body( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' ), 'render_agreement_review' );
ck( 'the review block takes the document alone and draws no form of its own', array(
	false !== strpos( $review_body, 'function render_agreement_review( $post_id ) {' ),
	substr_count( $review_body, 'render_agreement_form(' ),
	substr_count( $review_body, '<form' ),
), array( true, 0, 0 ) );

// The Agreements tab is every Approved sponsor's standing state, and says where the document waiting
// is: on the queue tab, at its card. It draws no review block and no word about the queue being empty.
$screen = render_tab( 'agreements' );
ck( 'the Agreements tab says what it holds, and where a document a company uploaded waits, linked to that card on the Waiting for review tab', array(
	false !== strpos( $screen, '<h2>Agreements</h2><p class="description">A sponsor agreement is optional: a company&#039;s Sponsor Dashboard, offers and codes work without one. This is the state of every Approved sponsor&#039;s agreement. A document a company uploaded waits on the <a href="https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=queue#wpcpm-sponsor-agreements">Waiting for review</a> tab.</p>' ),
	strpos( $screen, '<section class="wpcpm-review"' ),
	strpos( $screen, 'Nothing is waiting to be read.' ),
	strpos( $screen, 'value="wpcpm_sponsor_agr_accept"' ),
), array( true, false, false, false ) );
// The per-sponsor agreement state prints one sentence about the site alone. The whole paragraph, not
// a loose substring, so the proof is that nothing follows this one sentence.
ck( 'the agreement state names only what this site holds', false !== strpos( $screen, '<p class="description">This site: nothing on file.</p>' ), true );
ck( 'a sponsor with nothing recorded is offered the on-file form', array(
	false !== strpos( $screen, 'value="wpcpm_sponsor_agr_on_file"' ),
	false !== strpos( $screen, 'name="wpcpm_sponsor_agr_drive"' ),
), array( true, true ) );
ck( 'the on-file form takes the signed-on day too, and every required field says so in the label\'s voice', array(
	false !== strpos( $screen, 'type="date" id="wpcpm-signed-' . $T . '" name="wpcpm_sponsor_agr_signed_on"' ),
	false !== strpos( $screen, 'The link to the signed copy in the program&#039;s Drive folder. <span class="wpcpm-field__required">Required</span></label>' ),
	1 === preg_match( '/name="wpcpm_sponsor_agr_drive"[^>]*required/', $screen ),
), array( true, true, true ) );
ck( 'and the on-file form, the one form the tab draws for it, carries the once attribute', array( post_forms( $screen ), post_forms( $screen, true ) ), array( array( 'wpcpm_sponsor_agr_on_file' ), array() ) );
// The address each agreement form carries beside its nonce, which its handler goes back to
// (`WPCPM_Sponsor_Agreement::bounce()` follows the referer). The agreement class here is a stand-in
// without its handlers, so the way back is read off the drawn forms, and the page at that address
// is drawn with an agreement outcome flashed.
$referer_of      = static function ( $html, $action ) {
	preg_match_all( '#<form\b[^>]*>(.*?)</form>#s', (string) $html, $forms );
	foreach ( $forms[1] as $form ) {
		$fields = hidden_fields_of( $form );
		if ( isset( $fields['action'] ) && $action === $fields['action'] ) {
			return isset( $fields['_wp_http_referer'] ) ? link_of( $fields['_wp_http_referer'] ) : array( 'no referer', array() );
		}
	}
	return array( 'no such form', array() );
};
$agreement_backs = array( 'on file' => $referer_of( $screen, WPCPM_Sponsor_Agreement::ACTION_ON_FILE ) );
// The tab's name is the bar's own, so renaming the tab renames it in this sentence too.
$agreements_body = method_body( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' ), 'render_agreements' );
ck( 'and the sentence takes the tab\'s name from the labels the bar prints, writing none of its own', array( substr_count( $agreements_body, "self::tab_labels()['queue']" ), substr_count( $agreements_body, "'Waiting for review'" ) ), array( 1, 0 ) );

$GLOBALS['queue']     = array();
$GLOBALS['summaries'] = array( $T => array( 'state' => 'accepted', 'agreement_id' => 901, 'pending_id' => 0, 'accepted_at' => '2026-09-06', 'kind' => 'own', 'drive_url' => '', 'recorded_status' => 'Accepted' ) );
$screen = render_tab( 'agreements' );
ck( 'an accepted agreement is offered Take it out of force, keyed to it, and no on-file form', array(
	false !== strpos( $screen, 'value="wpcpm_sponsor_agr_revoke"' ),
	false !== strpos( $screen, 'nonce-wpcpm_sponsor_agr_revoke_901' ),
	false !== strpos( $screen, 'Take it out of force' ),
	false !== strpos( $screen, 'value="wpcpm_sponsor_agr_on_file"' ),
	false !== strpos( $screen, '2026-09-06' ),
), array( true, true, true, false, true ) );
ck( 'its note box carries the length the handler enforces', array( false !== strpos( $screen, 'minlength="20"' ), false !== strpos( $screen, 'maxlength="2000"' ) ), array( true, true ) );
/** What Take it out of force's note box holds as a browser shows it: a CR LF is one LF, and the one line feed right after the opening tag is dropped. */
$revoke_box = static function ( $html ) {
	if ( ! preg_match( '#<textarea id="wpcpm-note-[^"]*" name="wpcpm_sponsor_agr_note"[^>]*>(.*?)</textarea>#s', (string) $html, $m ) ) {
		return null;
	}
	return html_entity_decode( (string) preg_replace( '/^\n/', '', str_replace( "\r\n", "\n", $m[1] ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
};
$revoke_typed = "\nKids <12 free & \"more\" </textarea> C:\\drafts\nline two";
ck( 'drawn with nothing kept, the note box is empty', $revoke_box( $screen ), '' );
$GLOBALS['kept_revoke'] = array( 901 => $revoke_typed );
$screen_kept            = render_tab( 'agreements' );
ck( 'after a refused press, the note box holds what was typed, exactly, a leading line break included, and nothing in it closes the box early', array( $revoke_box( $screen_kept ), substr_count( $screen_kept, '</textarea>' ) ), array( $revoke_typed, 1 ) );
ck( 'and only once: drawn again, it is empty', $revoke_box( render_tab( 'agreements' ) ), '' );
ck( 'and Take it out of force, the one form the tab draws for it, carries the once attribute', array( post_forms( $screen ), post_forms( $screen, true ) ), array( array( 'wpcpm_sponsor_agr_revoke' ), array() ) );
$agreement_backs['out of force'] = $referer_of( $screen, WPCPM_Sponsor_Agreement::ACTION_REVOKE );
$empty_queue = render_tab( 'queue' );
ck( 'with nothing waiting, the queue tab\'s agreements card says so rather than printing nothing', array( false !== strpos( card_body( $empty_queue, 'wpcpm-sponsor-agreements' ), '<p>Nothing is waiting to be read.</p>' ), substr_count( $empty_queue, '<section class="wpcpm-review"' ) ), array( true, 0 ) );

// Reinstate is the Administrator Dashboard's, which lists every agreement out of force with it: the
// block says so, and links to the document's own item there, the latest revoked one, which the form
// that used to be here was keyed to.
$revoked = new WP_Post( array( 'ID' => 902, 'post_type' => 'wpcpm_sponsor_agr' ) );
$GLOBALS['pmeta'][902]     = array( '_wpcpm_sagr_state' => 'revoked' );
$GLOBALS['agr_posts']      = array( $T => array( $revoked ) );
$GLOBALS['summaries'][ $T ] = array( 'state' => 'revoked', 'agreement_id' => 0, 'pending_id' => 0, 'accepted_at' => '', 'kind' => 'own', 'drive_url' => '', 'recorded_status' => 'Revoked' );
// The one document out of force on the site, so the dashboard's "Out of force" list holds it.
$GLOBALS['revoked_ids'] = array( 902 );
$screen = render_tab( 'agreements' );
WPCPM_Administrators_Dashboard::$url = '';
$revoked_no_page                     = render_tab( 'agreements' );
WPCPM_Administrators_Dashboard::$url = 'https://example.test/administrator-dashboard/';
ck( 'a revoked one says it is reinstated on the Administrator Dashboard, under Out of force in its Sponsor Collaboration Agreements card, and links to that document\'s own item there, with no Put it back in force form', array(
	false !== strpos( $screen, '<p>It is reinstated on the Administrator Dashboard, under Out of force in its Sponsor Collaboration Agreements card.</p><p><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-agreement-902">Open on the Administrator Dashboard</a></p>' ),
	strpos( $screen, 'value="wpcpm_sponsor_agr_reinstate"' ),
	strpos( $screen, 'Put it back in force' ),
	post_forms( $screen ),
), array( true, false, false, array() ) );
ck( 'while the dashboard\'s page is missing, the page-missing sentence stands in the link\'s place', array(
	false !== strpos( $revoked_no_page, '<p>It is reinstated on the Administrator Dashboard, under Out of force in its Sponsor Collaboration Agreements card.</p><p class="wpcpm-warning">The dashboard class says its page is missing.</p>' ),
	substr_count( $revoked_no_page, 'Open on the Administrator Dashboard' ),
	post_forms( $revoked_no_page ),
), array( true, 0, array() ) );

// The dashboard's "Out of force" list is the oldest LIMIT documents out of force on the site, and it
// does not drain: a document leaves it only when it is reinstated, and one that is not its sponsor's
// latest never can be. So a sponsor's latest revoked document can stand past it, on no list at all,
// and it is reinstated here then, by the form the dashboard's row posts, under the sentence that says
// why. One fewer older document leaves it the last the list holds, linked there as before.
$limit       = WPCPM_Administrators_Cards::LIMIT;
$past_list   = '<p>The Administrator Dashboard lists the ' . $limit . ' oldest agreements out of force, and this one is past them, so it is reinstated here.</p>';
$reinstate   = '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-review__form" data-wpcpm-once data-wpcpm-busy="Reinstating">';
$older_ids   = range( 7001, 7000 + $limit );
$GLOBALS['revoked_ids'] = array_merge( array_slice( $older_ids, 0, $limit - 1 ), array( 902 ) );
$last_listed = render_tab( 'agreements' );
$GLOBALS['revoked_ids'] = array_merge( $older_ids, array( 902 ) );
$past        = render_tab( 'agreements' );
WPCPM_Administrators_Dashboard::$url = '';
$past_no_page                        = render_tab( 'agreements' );
WPCPM_Administrators_Dashboard::$url = 'https://example.test/administrator-dashboard/';
// Not found in the read, which is the Administrator Dashboard's own, is past the list too.
$GLOBALS['revoked_ids'] = $older_ids;
$unread      = render_tab( 'agreements' );
$reinstate_fields = form_fields_of( $past, WPCPM_Sponsor_Agreement::ACTION_REINSTATE );
ck( 'with one fewer older document out of force than the dashboard lists, it is the last that list holds: linked there, no form', array(
	false !== strpos( $last_listed, '<p>It is reinstated on the Administrator Dashboard, under Out of force in its Sponsor Collaboration Agreements card.</p><p><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-agreement-902">Open on the Administrator Dashboard</a></p>' ),
	post_forms( $last_listed ),
	strpos( $last_listed, 'so it is reinstated here' ),
), array( true, array(), false ) );
ck( 'one more and it is past that list: reinstated here, under the sentence that says why, by the form the dashboard posts, its button in the dashboard\'s word asking the agreement class\'s own question, with the once attribute, and no way to a list that does not hold it', array(
	false !== strpos( $past, $past_list . $reinstate ),
	post_forms( $past ),
	post_forms( $past, true ),
	isset( $reinstate_fields[0] ) ? $reinstate_fields[0] : array(),
	false !== strpos( $past, 'data-wpcpm-confirm="The agreement class asks before it reinstates.">Reinstate</button></form>' ),
	substr_count( $past, 'Open on the Administrator Dashboard' ),
	strpos( $past, 'It is reinstated on the Administrator Dashboard, under Out of force in its Sponsor Collaboration Agreements card.' ),
), array( true, array( 'wpcpm_sponsor_agr_reinstate' ), array(), array( '_wpnonce' => 'nonce-wpcpm_sponsor_agr_reinstate_902', 'action' => 'wpcpm_sponsor_agr_reinstate', 'wpcpm_sponsor_agr_post' => '902' ), true, 0, false ) );
ck( 'it needs no page there, so it keeps the form while the dashboard\'s page is missing, and says nothing about the page', array(
	false !== strpos( $past_no_page, $past_list . $reinstate ),
	post_forms( $past_no_page ),
	strpos( $past_no_page, 'The dashboard class says its page is missing.' ),
), array( true, array( 'wpcpm_sponsor_agr_reinstate' ), false ) );
ck( 'and so is one the read of the documents out of force does not reach', array( false !== strpos( $unread, $past_list . $reinstate ), post_forms( $unread ) ), array( true, array( 'wpcpm_sponsor_agr_reinstate' ) ) );
$agreement_backs['reinstate'] = $referer_of( $past, WPCPM_Sponsor_Agreement::ACTION_REINSTATE );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'agreement-accepted' );
$agreement_lands = render_screen( $agreement_backs['on file'][1] );
ck( 'each agreement form, the on-file form, Take it out of force and Reinstate past the dashboard\'s list, carries the Agreements tab as its way back, and the page there prints the outcome once, above the bar', array(
	$agreement_backs,
	notices_in( $agreement_lands ),
	(int) strpos( $agreement_lands, 'is-dismissible' ) < (int) strpos( $agreement_lands, '<nav class="nav-tab-wrapper' ),
	bar_of( $agreement_lands ) === $bar_for( 'agreements' ),
), array(
	array_fill_keys( array( 'on file', 'out of force', 'reinstate' ), array( '/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'agreements' ) ) ),
	'<div class="notice notice-success is-dismissible"><p>Accepted.</p></div>',
	true,
	true,
) );
// The read is the one the Administrator Dashboard's card makes, its oldest LIMIT documents out of
// force: a document found in it is one that card lists, and one not found is past it.
$GLOBALS['stand_in_limits'] = array();
render_tab( 'agreements' );
ck( 'the documents out of force are read as the Administrator Dashboard\'s card reads them, up to its own limit and no further', isset( $GLOBALS['stand_in_limits']['out of force'] ) ? $GLOBALS['stand_in_limits']['out of force'] : array(), array( WPCPM_Administrators_Cards::LIMIT ) );

// The documents out of force are read once for the whole tab, however many sponsors stand there.
$T2 = 'recSPONSORTEST004';
WPCPM_Sponsors_Index::write( array(
	$T  => array( 'name' => 'TEST Sponsor', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
	$T2 => array( 'name' => 'Second TEST Sponsor', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
), time() );
$GLOBALS['pmeta'][903]       = array( '_wpcpm_sagr_state' => 'revoked' );
$GLOBALS['agr_posts'][ $T2 ] = array( new WP_Post( array( 'ID' => 903, 'post_type' => 'wpcpm_sponsor_agr' ) ) );
$GLOBALS['summaries'][ $T2 ] = $GLOBALS['summaries'][ $T ];
$GLOBALS['revoked_ids']      = array( 902, 903 );
$two_out = render_tab( 'agreements' );
ck( 'two sponsors out of force are two blocks, each linked to its own document there, from one read of the documents out of force', array(
	substr_count( $two_out, 'It is reinstated on the Administrator Dashboard, under Out of force in its Sponsor Collaboration Agreements card.' ),
	false !== strpos( $two_out, '#wpcpm-sponsor-agreement-902"' ) && false !== strpos( $two_out, '#wpcpm-sponsor-agreement-903"' ),
	isset( $GLOBALS['stand_in_reads']['out of force'] ) ? (int) $GLOBALS['stand_in_reads']['out of force'] : 0,
), array( 2, true, 1 ) );
WPCPM_Sponsors_Index::write( array(
	$T => array( 'name' => 'TEST Sponsor', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
), time() );
unset( $GLOBALS['agr_posts'][ $T2 ], $GLOBALS['summaries'][ $T2 ] );
$GLOBALS['revoked_ids'] = array( 902 );

echo "\n=== The application queue on the Sponsors screen ===\n";

// The real application class, loaded at the top since 1.111.0 rather than stubbed: what is being
// pinned here is that the screen draws what the class stores, and the image handler and the mail
// exit are never reached by a render.

// The real approval class too, for its `is_half_done()` alone: the queue's half-done mark is
// read off the stamp and the state, and a stub here would pin the mark against a flag this
// file sets rather than against the condition. Nothing in this suite approves
// anything, so none of the class's collaborators is ever reached.
require_once __DIR__ . '/../includes/modules/class-wpcpm-sponsor-approval.php';


// Uninstall (above) wiped the index; the TEST sponsor was written fresh for the agreement
// section, with no website. One more row with a website, for the in-base match.
WPCPM_Sponsors_Index::write( array(
	$T                  => array( 'name' => 'TEST Sponsor', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
	'recSPONSORTEST002' => array( 'name' => 'Widgetry Ltd ', 'website' => 'https://www.widgetry.example/', 'status' => 'Approved', 'contact_email' => 'maciej@a8c.com' ),
), time() );

/** An application row, stored the way the form stored one before 1.123.12, with an answer to the support question it no longer asks. */
function seed_sponsor_application( $name, $state, array $signals = array(), array $logos = array() ) {
	$id = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Application::POST_TYPE, 'post_status' => 'private', 'post_title' => $name, 'post_author' => 0 ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_FIELDS, array( 'Company Name' => $name, 'Website' => 'https://widgetry.example', 'Contact Person Full Name' => 'Sam Sponsor', 'Contact Email' => 'maciej@a8c.com', 'Sponsorship options' => 'Sponsor mentors + tools/services', "Anything else you'd like to share." => 'We make gadgets.' ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_STATE, $state );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_REFERENCE, sprintf( 'SAPP-2026-%04d', $id ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_SIGNALS, $signals );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_EMAIL, md5( 'maciej@a8c.com' ) );
	// 'at' is 2026-09-01 12:00 UTC, which gmdate() (this file's wp_date() stub) reads as the date
	// the check below asserts ('Agreed 2026-09-01').
	update_post_meta( $id, WPCPM_Sponsor_Application::META_CONSENT, array( 'sentence' => 'I confirm the privacy policy.', 'url' => 'https://example.test/privacy/', 'policy' => 43, 'modified' => '2026-08-20 11:30:00', 'at' => 1788264000, 'ip' => '203.0.113.0', 'agent' => 'Mozilla/5.0 (test)' ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_LOGOS, array_merge( array( 'colour' => 0, 'white' => 0 ), $logos ) );
	return $id;
}

ck( 'with nothing waiting the bubble is not drawn', $module->menu_label(), 'Sponsors' );

$bare = render_screen();
ck( 'with no application at all the queue and the closed list under it each say so, quietly', array(
	false !== strpos( $bare, 'Nothing is waiting. New applications appear here.' ),
	false !== strpos( $bare, 'Recently decided' ),
	false !== strpos( $bare, 'No application has been decided yet.' ),
), array( true, true, true ) );

$open_id = seed_sponsor_application( 'Gadgetry Inc', 'new', array( 'in-base' ), array( 'colour' => 640, 'white' => 641 ) );
$held_id = seed_sponsor_application( 'Held Co', 'held', array( 'links', 'duplicate' ) );
$done_id = seed_sponsor_application( 'Done Co', 'rejected' );

// The bubble was just cached at zero by the "nothing waiting" check above; this stub's
// add_action() is a no-op, so none of the three real hooks fires when wp_insert_post() and
// update_post_meta() seed the fixture above, the way they would on a real site. Forgotten by
// hand so this check reads the fixture just seeded, not the count from before it existed.
WPCPM_Sponsors::forget_attention();
ck( 'the bubble counts the applications waiting', array( false !== strpos( $module->menu_label(), 'count-2' ), false !== strpos( $module->menu_label(), 'pending-count">2<' ) ), array( true, true ) );
ck( 'and the screen\'s sentences include the decisions\'', isset( WPCPM_Sponsors::messages()['sapp-approved'] ), true );

// The application's one-shot detail is printed in its own sentence on the queue tab, and only under
// the status it was tagged to. A detail the accounts list left on its own channel is not printed
// under an application's status, and is gone after that page, so it cannot surface under a later one.
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'sapp-account' );
WPCPM_Flash::set( WPCPM_Sponsor_Application::FLASH_DETAIL, array( 'status' => 'sapp-account', 'detail' => 'Sorry, that username already exists!' ) );
$account_failed = render_tab( 'queue' );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'sapp-approved' );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH_DETAIL, array( 'status' => 'provision-failed', 'created' => 0, 'attached' => 0, 'unmarked' => 0, 'left' => 0, 'limit' => 10, 'failed' => array( 'Left Behind Co: That account belongs to a student' ), 'failed_more' => 0 ) );
$approved_page = render_tab( 'queue' );
ck( 'on the queue tab, sapp-account prints the detail it carried in its own sentence, once, above the bar', array(
	notices_in( $account_failed ),
	(int) strpos( $account_failed, 'is-dismissible' ) < (int) strpos( $account_failed, '<nav class="nav-tab-wrapper' ),
), array( '<div class="notice notice-error is-dismissible"><p>The sponsor record was created, but the account could not be made. The site said: Sorry, that username already exists! Press Approve again once that is fixed.</p></div>', true ) );
ck( 'and a detail the accounts list left on its channel is not printed under an application\'s status, nor kept for a later page', array(
	notices_in( $approved_page ),
	strpos( $approved_page, 'Left Behind Co' ),
	array_key_exists( WPCPM_Sponsors::FLASH_DETAIL, flash_left() ),
), array( '<div class="notice notice-success is-dismissible"><p>The application is approved. The sponsor record, the account, the category, the logo and the first offer are in place, and the welcome is queued.</p></div>', false, false ) );

$screen = render_screen();

ck( 'the screen\'s own address draws the queue tab\'s three cards, the applications first, with their count and the two open rows, oldest first', array(
	cards_of( $screen ),
	false !== strpos( $screen, 'Sponsor applications <span class="wpcpm-count">2</span>' ),
	strpos( $screen, 'Gadgetry Inc' ) < strpos( $screen, 'Held Co' ),
	// The decided row is on the screen, but under "Recently decided"
	// and never in the queue itself: the count above is the queue's and stays at two.
	strpos( $screen, 'Done Co' ) > strpos( $screen, 'Recently decided' ),
), array( array( 'Sponsor applications', 'Sponsor posts to review', 'Signed agreements' ), true, true, true ) );
ck( 'a held row says it was held and how many checks held it, the duplicate mark aside', array( false !== strpos( $screen, 'wpcpm-inst-mark--held' ), false !== strpos( $screen, '1 check held it' ) ), array( true, true ) );
ck( 'the two duplicate marks are drawn where they apply', array( false !== strpos( $screen, 'possible duplicate' ), false !== strpos( $screen, 'already on the site' ) ), array( true, true ) );
ck( 'each row opens itself on this screen', false !== strpos( $screen, 'wpcpm_sapp_id=' . $open_id ), true );
ck( 'and nothing on the queue is a form', strpos( $screen, 'value="wpcpm_sapp_approve"' ), false );

$opened = render_screen( array( 'wpcpm_sapp_id' => $open_id ) );

ck( 'the opened application is drawn and the queue card is not: one card, on the queue tab, with the six columns and their answers', array(
	false !== strpos( $opened, 'id="wpcpm-sponsor-application"' ),
	strpos( $opened, 'id="wpcpm-sponsor-applications"' ),
	substr_count( $opened, '<div class="wpcpm-card' ),
	bar_of( $opened ) === $bar_for( 'queue' ),
	substr_count( $opened, '<code class="wpcpm-inst-record">' ) >= 8,
	false !== strpos( $opened, 'Sponsor mentors + tools/services' ),
	false !== strpos( $opened, 'We make gadgets.' ),
), array( true, false, 1, true, true, true, true ) );
// The row is stored the way the form stored one before 1.123.12, with an answer to the support
// question, which the form no longer asks: the opened application shows it, labeled as an older
// answer. The same application without that answer, as one filed since is stored, has no row for it.
$older_label  = 'How would you like to support WP Credits? (older answer, no longer asked)<br /><code class="wpcpm-inst-record">Sponsorship options</code></th><td>Sponsor mentors + tools/services</td>';
$older_fields = get_post_meta( $open_id, WPCPM_Sponsor_Application::META_FIELDS, true );
$newer_fields = $older_fields;
unset( $newer_fields['Sponsorship options'] );
update_post_meta( $open_id, WPCPM_Sponsor_Application::META_FIELDS, $newer_fields );
$opened_newer = render_screen( array( 'wpcpm_sapp_id' => $open_id ) );
update_post_meta( $open_id, WPCPM_Sponsor_Application::META_FIELDS, $older_fields );
ck( 'an older application\'s support answer is shown as an older answer, and one filed since has no row for the question', array(
	substr_count( $opened, $older_label ),
	strpos( $opened_newer, 'Sponsorship options' ),
	strpos( $opened_newer, 'How would you like to support WP Credits?' ),
	substr_count( $opened, '<code class="wpcpm-inst-record">' ) - substr_count( $opened_newer, '<code class="wpcpm-inst-record">' ),
	false !== strpos( $opened_newer, 'We make gadgets.' ),
), array( 1, false, false, 1, true ) );
$back = preg_match( '#<a href="([^"]*)">Back to the queue</a>#', $opened, $found ) ? html_entity_decode( $found[1], ENT_QUOTES, 'UTF-8' ) : '';
ck( 'its way back is the screen\'s own address at the queue card, which opens the queue tab', array( $back, cards_of( render_screen( link_of( $back )[1] ) ) ), array( $home . '#wpcpm-sponsor-applications', array( 'Sponsor applications', 'Sponsor posts to review', 'Signed agreements' ) ) );
ck( 'the consent evidence is one sentence naming the policy and its version', array( false !== strpos( $opened, 'Agreed 2026-09-01' ), false !== strpos( $opened, 'https://example.test/privacy/' ), false !== strpos( $opened, '2026-08-20 11:30:00' ) ), array( true, true, true ) );
ck( 'the two logos are shown from the Media Library', array( false !== strpos( $opened, 'data-id="640"' ), false !== strpos( $opened, 'data-id="641"' ), false !== strpos( $opened, 'In white' ) ), array( true, true, true ) );
ck( 'what the site already has: the row that matches by website, with its record and its status', array( false !== strpos( $opened, 'What the site already has' ), false !== strpos( $opened, 'recSPONSORTEST002' ), false !== strpos( $opened, 'creates a second record' ) ), array( true, true, true ) );
ck( 'and for the same company, the way to its account is Create account on this screen\'s Accounts tab', array( false !== strpos( $opened, 'use Create account on the Sponsors screen&#039;s Accounts tab instead.' ), strpos( $opened, 'Sponsors card' ) ), array( true, false ) );
ck( 'the checks are printed, and the in-base one in words', false !== strpos( $opened, 'already holds a sponsor with this name or website' ), true );
// The Administrator Dashboard's card lists the oldest open applications, and this one among them, so
// it is decided there: the opened application draws no decision, says so under one heading, and links
// to its own item on that card.
ck( 'an open application the dashboard\'s card lists is decided there: no decision is drawn, one heading says where, and the way to its own item there', array(
	substr_count( $opened, '<form' ),
	strpos( $opened, 'nonce-wpcpm_sapp_approve_' . $open_id ),
	strpos( $opened, 'What happens next' ),
	false !== strpos( $opened, '<h3>Where it is decided</h3><p>Applications are decided on the Administrator Dashboard. This one is listed in its Sponsor applications card, with every decision its state allows.</p><p><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-application-' . $open_id . '">Open on the Administrator Dashboard</a></p>' ),
	substr_count( $opened, 'Open on the Administrator Dashboard' ),
), array( 0, false, false, true, 1 ) );
ck( 'and the answers never print the applicant\'s address unescaped or a nonce for another row', array( false !== strpos( $opened, 'maciej@a8c.com' ), strpos( $opened, 'nonce-wpcpm_sapp_approve_' . $held_id ) ), array( true, false ) );

echo "\n=== The queue marks a contact address that already belongs to an account ===\n";
// A dedicated user and a dedicated application, at an address none of the fixture's other
// accounts holds, so the mark below is read off this one application rather than off whatever
// this file's own account churn happened to leave sitting at maciej@a8c.com.
$GLOBALS['users'][95] = new WP_User( 95, array( 'wpcpm_mentor' ), 'Existing Owner', 'existing-owner@example.test' );
$conflict_id = seed_sponsor_application( 'Conflict Co', 'new' );
update_post_meta( $conflict_id, WPCPM_Sponsor_Application::META_FIELDS, array_merge( get_post_meta( $conflict_id, WPCPM_Sponsor_Application::META_FIELDS, true ), array( 'Contact Email' => 'existing-owner@example.test' ) ) );
$with_conflict = render_screen();
ck( 'the queue marks a contact address that already belongs to an account', false !== strpos( $with_conflict, 'already an account' ), true );
ck( 'with the account modifier on the mark', false !== strpos( $with_conflict, 'wpcpm-inst-mark--account' ), true );

$decided = render_screen( array( 'wpcpm_sapp_id' => $done_id ) );
ck( 'a decided application offers the way back and the deletion', array( false !== strpos( $decided, 'value="wpcpm_sapp_reopen"' ), false !== strpos( $decided, 'value="wpcpm_sapp_purge"' ), strpos( $decided, 'value="wpcpm_sapp_approve"' ) ), array( true, true, false ) );

echo "\n=== A half-done approval is marked wherever the row is drawn ===\n";
// The record stamped with the row still open is what `WPCPM_Sponsor_Approval::is_half_done()`
// reads, and the real class is loaded in this file: the mark is read off the condition itself.
update_post_meta( $held_id, WPCPM_Sponsor_Application::META_RECORD, 'recSPONSORTEST003' );
// The opened application is drawn in the queue card's place, so its mark and its row's are read on
// two draws: the application opened, and the queue.
$half     = render_screen( array( 'wpcpm_sapp_id' => $held_id ) );
$half_row = render_screen();
ck( 'the mark is on the open application and on its queue row, once on each, in the same words', array(
	substr_count( $half, 'wpcpm-inst-mark--half-done' ),
	substr_count( $half, 'approval half done' ),
	false !== strpos( $half, 'Press Approve again to finish.' ),
	substr_count( $half_row, 'wpcpm-inst-mark--half-done' ),
	substr_count( $half_row, 'approval half done' ),
), array( 1, 1, true, 1, 1 ) );
ck( 'and no other row carries it', substr_count( $half_row, 'wpcpm_sapp_id=' . $open_id ) > 0 && 1 === substr_count( $half_row, 'wpcpm-inst-mark--half-done' ), true );
delete_post_meta( $held_id, WPCPM_Sponsor_Application::META_RECORD );

echo "\n=== Recently decided: the closed list under the queue ===\n";
// Two of the six decisions, Put back in the queue and Delete for good, are offered on a
// decided application alone, and until this list existed nothing on either surface listed
// one: they were reachable only by typing ?wpcpm_sapp_id= by hand, so a genuine application
// the checks filed as spam was seen by nobody. The three decided states are seeded with their
// own decision times, because the list is ordered by the decision and not by the row's age,
// and one open row is seeded beside them to prove it stays out of the list.

/**
 * The event row `decided_at()` reads and the meta `decided_posts()` sorts by, so the closed
 * list's order can be set on purpose (both stamped together since 1.98.1, as `add_event()` does).
 */
function decided_on( $id, $at ) {
	update_post_meta( $id, WPCPM_Sponsor_Application::META_EVENT, array( array( 'event' => 'decided', 'at' => (int) $at, 'actor' => 3, 'note' => '' ) ) );
	update_post_meta( $id, WPCPM_Sponsor_Application::META_DECIDED, (int) $at );
}

$spam_id     = seed_sponsor_application( 'Spammy Co', 'spam' );
$rejected_id = seed_sponsor_application( 'Rejected Co', 'rejected' );
$approved_id = seed_sponsor_application( 'Approved Ltd', 'approved' );
$waiting_id  = seed_sponsor_application( 'Waiting Co', 'new' );

// Deliberately not in ID order: a list that followed the posts rather than the decisions
// would come out as Done, Spammy, Rejected, Approved and pass nothing below.
decided_on( $done_id, 1788000000 );
decided_on( $spam_id, 1788200000 );
decided_on( $rejected_id, 1788100000 );
decided_on( $approved_id, 1788300000 );

$screen = render_screen();
$split  = (int) strpos( $screen, 'wpcpm-sapp-decided' );
$queued = substr( $screen, 0, $split );
$closed = substr( $screen, $split );

// The card's own body: from its id to the first tag that closes a div, which is its own.
$card = substr( $screen, (int) strpos( $screen, 'id="wpcpm-sponsor-applications"' ) );
$card = substr( $card, 0, (int) strpos( $card, '</div>' ) );
ck( 'the closed list is inside the queue\'s own card, after the queue itself', array(
	false !== strpos( $card, '<section class="wpcpm-sapp-decided">' ),
	strpos( $card, '<section class="wpcpm-sapp-decided">' ) > strpos( $card, '</ol>' ),
	false !== strpos( $card, '<h3>Recently decided</h3>' ),
), array( true, true, true ) );
ck( 'it lists the three decided states and nothing else, newest decision first', array(
	strpos( $closed, 'Approved Ltd' ) < strpos( $closed, 'Spammy Co' ),
	strpos( $closed, 'Spammy Co' ) < strpos( $closed, 'Rejected Co' ),
	strpos( $closed, 'Rejected Co' ) < strpos( $closed, 'Done Co' ),
), array( true, true, true ) );
ck( 'the open row is in the queue and never in the list under it', array( false !== strpos( $queued, 'Waiting Co' ), strpos( $closed, 'Waiting Co' ) ), array( true, false ) );
// An open row ends with the way to its own item on the Administrator Dashboard's card, as a post and
// a document do; a decided row draws none, since that card lists open applications only.
ck( 'the waiting row ends with the way to its own item on the Administrator Dashboard, and no decided row carries one', array(
	false !== strpos( $queued, '<span class="wpcpm-inst-muted">new</span></p><p><a href="https://example.test/administrator-dashboard/#wpcpm-sponsor-application-' . $waiting_id . '">Open on the Administrator Dashboard</a></p></li>' ),
	substr_count( $closed, '#wpcpm-sponsor-application-' ),
), array( true, 0 ) );
ck( 'every decided row carries its Open link, so both decisions can be reached by pressing', array(
	false !== strpos( $closed, 'wpcpm_sapp_id=' . $spam_id ),
	false !== strpos( $closed, 'wpcpm_sapp_id=' . $rejected_id ),
	false !== strpos( $closed, 'wpcpm_sapp_id=' . $approved_id ),
	false !== strpos( $closed, 'Open this application' ),
), array( true, true, true, true ) );
ck( 'and its state, in the words the screens use', array(
	false !== strpos( $closed, 'marked as spam' ),
	false !== strpos( $closed, '>rejected<' ),
	false !== strpos( $closed, '>approved<' ),
), array( true, true, true ) );
ck( 'a decided row says when it was decided; only a waiting one says how long it has waited', array(
	false !== strpos( $closed, 'Decided 4 hours ago, on 2026-09-01 22:00' ),
	strpos( $closed, 'Waiting 4 hours' ),
	false !== strpos( $queued, 'Waiting 4 hours' ),
), array( true, false, true ) );
// The cap is a constant, so the count is asserted against it rather than by lowering it: four
// decided rows are drawn because four is under `QUEUE_MAX`, and the slice that enforces it is
// read off the source.
ck( 'the list draws every decided row up to the queue\'s own cap', array(
	substr_count( $closed, 'class="wpcpm-queue-item"' ),
	min( 4, WPCPM_Sponsor_Application::QUEUE_MAX ),
	false !== strpos( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-application.php' ), 'array_slice( $rows, 0, self::QUEUE_MAX )' ),
), array( 4, 4, true ) );

$wrong = render_screen( array( 'wpcpm_sapp_id' => 42 ) );
ck( 'a post that is not an application opens nothing, and the queue tab\'s cards are drawn in its place', array( strpos( $wrong, 'id="wpcpm-sponsor-application"' ), cards_of( $wrong ) ), array( false, array( 'Sponsor applications', 'Sponsor posts to review', 'Signed agreements' ) ) );
$GLOBALS['get'] = array();

$screen_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'the bubble reads three queues behind guards', substr_count( method_body( $screen_src, 'attention_count' ), 'class_exists(' ) >= 3, true );

echo "\n=== The queue tab: the applications, the sponsor posts and the signed agreements, read here and decided on the Administrator Dashboard ===\n";
// The three kinds a manager decides on the Administrator Dashboard, each in a card of its own, in the
// dashboard's order. Each card counts everything waiting, lists no more than the dashboard's own card
// does, so that no row links to an item the dashboard does not draw, and says so when it lists fewer;
// each item links to its own item there.
$dashboard = 'https://example.test/administrator-dashboard/';
$post_row  = static function ( $id, $title, $author, $preview ) {
	return array( 'id' => $id, 'title' => $title, 'record' => 'recSPONSORTEST001', 'company' => 'TEST Sponsor', 'author' => $author, 'at' => time() - 3600, 'preview' => $preview );
};
$agr_facts = static function ( $id ) use ( $T ) {
	return array( 'post_id' => $id, 'state' => 'submitted', 'kind' => 'own', 'sponsor' => $T, 'sponsor_name' => 'TEST Sponsor', 'uploaded_by' => 'Member One', 'uploaded_at' => '2026-09-05', 'original_name' => 'agreement.pdf', 'flags' => array(), 'size' => 20480, 'members' => 2 );
};
$GLOBALS['pending_posts'] = array(
	$post_row( 905, 'Ten tips', 'Member One', 'https://example.test/?p=905&preview=true' ),
	$post_row( 906, 'Untitled thoughts <b>', '', '' ),
	array_merge( $post_row( 907, 'Notes from the field', '<b>Ann</b>', '' ), array( 'company' => '<b>Co</b>' ) ),
);
$GLOBALS['queue'] = array( 910 );
$GLOBALS['facts'] = array( 910 => $agr_facts( 910 ) );

$queue_tab  = render_tab( 'queue' );
$posts_card = card_body( $queue_tab, 'wpcpm-sponsor-posts' );
$agr_card   = card_body( $queue_tab, 'wpcpm-sponsor-agreements' );

ck( 'the queue tab draws three cards in the Administrator Dashboard\'s order, each with its id and its count of everything waiting', counted_cards_of( $queue_tab ), array(
	array( 'wpcpm-sponsor-applications', 'Sponsor applications', (string) count( WPCPM_Sponsor_Application::applications( WPCPM_Sponsor_Application::open_states() ) ) ),
	array( 'wpcpm-sponsor-posts', 'Sponsor posts to review', '3' ),
	array( 'wpcpm-sponsor-agreements', 'Signed agreements', '1' ),
) );
ck( 'the posts card says what it lists and where each post is decided', false !== strpos( $posts_card, '<p class="description">Posts that sponsors wrote and submitted for review, oldest first. A post is published or returned on the Administrator Dashboard.</p>' ), true );
ck( 'a post\'s row: its title, its sponsor and its author, how long ago it was submitted, Preview, and the way to its own item on the Administrator Dashboard', false !== strpos( $posts_card, '<ol class="wpcpm-queue"><li class="wpcpm-queue-item"><h3 class="wpcpm-queue-title">Ten tips</h3><p>From TEST Sponsor, by Member One. Submitted 4 hours ago.</p><p><a class="button" href="https://example.test/?p=905&preview=true">Preview</a></p><p><a href="' . $dashboard . '#wpcpm-sponsor-post-905">Open on the Administrator Dashboard</a></p></li>' ), true );
ck( 'a row with no preview prints no Preview, its title is escaped, and an author whose account is gone is said to be one', array(
	false !== strpos( $posts_card, '<li class="wpcpm-queue-item"><h3 class="wpcpm-queue-title">Untitled thoughts &lt;b&gt;</h3><p>From TEST Sponsor, by somebody whose account is gone. Submitted 4 hours ago.</p><p><a href="' . $dashboard . '#wpcpm-sponsor-post-906">Open on the Administrator Dashboard</a></p></li>' ),
	substr_count( $posts_card, '>Preview</a>' ),
), array( true, 1 ) );
ck( 'and a sponsor\'s name and an author\'s name are escaped as the title is', false !== strpos( $posts_card, '<li class="wpcpm-queue-item"><h3 class="wpcpm-queue-title">Notes from the field</h3><p>From &lt;b&gt;Co&lt;/b&gt;, by &lt;b&gt;Ann&lt;/b&gt;. Submitted 4 hours ago.</p>' ), true );
ck( 'the agreements card says what it lists and where each document is decided, then each document\'s review block, with the way to its own item on the Administrator Dashboard in the place of its decisions', array(
	false !== strpos( $agr_card, '<p class="description">Signed agreements that companies uploaded and that wait to be read, oldest first. Each is accepted or returned on the Administrator Dashboard.</p><section class="wpcpm-review" id="wpcpm-review-910">' ),
	false !== strpos( $agr_card, '<p class="wpcpm-review__open"><a href="' . $dashboard . '#wpcpm-sponsor-agreement-910">Open on the Administrator Dashboard</a></p></section>' ),
), array( true, true ) );
ck( 'nothing on the queue tab is a form, and no card says it lists fewer than wait while it lists them all', array( substr_count( $queue_tab, '<form' ), strpos( $queue_tab, 'Showing the oldest' ) ), array( 0, false ) );

// One more of each than the dashboard's cards list.
$GLOBALS['pending_posts'] = array();
$GLOBALS['queue']         = array();
$GLOBALS['facts']         = array();
for ( $i = 0; $i <= WPCPM_Administrators_Cards::LIMIT; $i++ ) {
	$GLOBALS['pending_posts'][]    = $post_row( 2000 + $i, 'Post ' . $i, 'Member One', '' );
	$GLOBALS['queue'][]            = 3000 + $i;
	$GLOBALS['facts'][ 3000 + $i ] = $agr_facts( 3000 + $i );
}
$full        = render_tab( 'queue' );
$full_posts  = card_body( $full, 'wpcpm-sponsor-posts' );
$full_agr    = card_body( $full, 'wpcpm-sponsor-agreements' );
$limit       = WPCPM_Administrators_Cards::LIMIT;
$window_said = '<p class="description">Showing the oldest ' . $limit . ' of ' . ( $limit + 1 ) . '. As these are decided on the Administrator Dashboard, the next of them take their place.</p>';
ck( 'past the dashboard\'s window each card counts every one waiting, lists as many as the dashboard\'s card does, oldest first, under the sentence that says so, and links none it does not list', array(
	array_column( array_slice( counted_cards_of( $full ), 1 ), 2 ),
	substr_count( $full_posts, '<li class="wpcpm-queue-item">' ),
	substr_count( $full_agr, '<section class="wpcpm-review"' ),
	false !== strpos( $full_posts, 'Administrator Dashboard.</p>' . $window_said . '<ol class="wpcpm-queue">' ),
	false !== strpos( $full_agr, 'Administrator Dashboard.</p>' . $window_said . '<section class="wpcpm-review" id="wpcpm-review-3000">' ),
	false !== strpos( $full_posts, '#wpcpm-sponsor-post-' . ( 2000 + $limit - 1 ) . '"' ) && false !== strpos( $full_agr, '#wpcpm-sponsor-agreement-' . ( 3000 + $limit - 1 ) . '"' ),
	strpos( $full_posts, '#wpcpm-sponsor-post-' . ( 2000 + $limit ) . '"' ),
	strpos( $full_agr, '#wpcpm-sponsor-agreement-' . ( 3000 + $limit ) . '"' ),
), array( array( (string) ( $limit + 1 ), (string) ( $limit + 1 ) ), $limit, $limit, true, true, true, false, false ) );

// The posts listed are the dashboard card's own read, not the first of a longer one: the read applies
// its limit before it leaves out a post whose sponsor stamp is no record, so the dashboard can draw one
// fewer than its number, and a list cut from the longer read would link to a post the card does not draw.
$GLOBALS['pending_posts'] = array();
for ( $i = 0; $i <= $limit; $i++ ) {
	$GLOBALS['pending_posts'][] = array_merge( $post_row( 4000 + $i, 'Post ' . $i, 'Member One', '' ), 0 === $i ? array( 'dropped' => true ) : array() );
}
$dropped_card = card_body( render_tab( 'queue' ), 'wpcpm-sponsor-posts' );
ck( 'a post the read leaves out under the dashboard\'s number shortens the list as it shortens the card there, and nothing past the card is linked', array(
	array_column( array_slice( counted_cards_of( render_tab( 'queue' ) ), 1, 1 ), 2 ),
	substr_count( $dropped_card, '<li class="wpcpm-queue-item">' ),
	false !== strpos( $dropped_card, '#wpcpm-sponsor-post-' . ( 4000 + $limit - 1 ) . '"' ),
	strpos( $dropped_card, '#wpcpm-sponsor-post-' . ( 4000 + $limit ) . '"' ),
	false !== strpos( $dropped_card, 'Showing the oldest ' . ( $limit - 1 ) . ' of ' . $limit . '. As these are decided' ),
), array( array( (string) $limit ), $limit - 1, true, false, true ) );

// One shown of more: the line in the singular.
$GLOBALS['pending_posts'] = array();
for ( $i = 0; $i <= $limit; $i++ ) {
	$GLOBALS['pending_posts'][] = array_merge( $post_row( 4000 + $i, 'Post ' . $i, 'Member One', '' ), $i < $limit - 1 ? array( 'dropped' => true ) : array() );
}
$one_card = card_body( render_tab( 'queue' ), 'wpcpm-sponsor-posts' );
ck( 'with one shown of two, the window line says so in the singular', array(
	false !== strpos( $one_card, '<p class="description">Showing the oldest 1 of 2. As this one is decided on the Administrator Dashboard, the next takes its place.</p>' ),
	substr_count( $one_card, '<li class="wpcpm-queue-item">' ),
), array( true, 1 ) );

// The counts stop where the menu bubble's does, and say so in its words past it: each card reads one
// more than COUNT_MAX, so it can tell a full ceiling from a count that reached it.
$at_ceiling = static function ( $how_many ) use ( $post_row, $agr_facts ) {
	$GLOBALS['pending_posts'] = array();
	$GLOBALS['queue']         = array();
	$GLOBALS['facts']         = array();
	for ( $i = 0; $i < $how_many; $i++ ) {
		$GLOBALS['pending_posts'][]    = $post_row( 5000 + $i, 'Post ' . $i, 'Member One', '' );
		$GLOBALS['queue'][]            = 6000 + $i;
		$GLOBALS['facts'][ 6000 + $i ] = $agr_facts( 6000 + $i );
	}
	return render_tab( 'queue' );
};
$full_ceiling = $at_ceiling( WPCPM_Sponsors::COUNT_MAX );
$past_ceiling = $at_ceiling( WPCPM_Sponsors::COUNT_MAX + 1 );
$ceiling      = WPCPM_Sponsors::COUNT_MAX;
ck( 'at the ceiling the two cards count it as it is; past it they print the bubble\'s "200+", in the heading and in the window line', array(
	array_column( array_slice( counted_cards_of( $full_ceiling ), 1 ), 2 ),
	substr_count( $full_ceiling, 'Showing the oldest ' . $limit . ' of ' . $ceiling . '. As these are decided' ),
	array_column( array_slice( counted_cards_of( $past_ceiling ), 1 ), 2 ),
	substr_count( $past_ceiling, 'Showing the oldest ' . $limit . ' of ' . $ceiling . '+. As these are decided' ),
), array( array( (string) $ceiling, (string) $ceiling ), 2, array( $ceiling . '+', $ceiling . '+' ), 2 ) );
$GLOBALS['pending_posts'] = array();
$GLOBALS['queue']         = array();
$GLOBALS['facts']         = array();
for ( $i = 0; $i <= $limit; $i++ ) {
	$GLOBALS['pending_posts'][]    = $post_row( 2000 + $i, 'Post ' . $i, 'Member One', '' );
	$GLOBALS['queue'][]            = 3000 + $i;
	$GLOBALS['facts'][ 3000 + $i ] = $agr_facts( 3000 + $i );
}

// While the dashboard's page is missing there is nowhere to link: the dashboard class's own sentence,
// once, above the three cards, and no card or row prints a link. An opened application says it in its
// own place instead, as it is drawn in the cards' place.
WPCPM_Administrators_Dashboard::$url = '';
$no_page     = render_tab( 'queue' );
$opened_gone = render_screen( array( 'wpcpm_sapp_id' => $open_id ) );
WPCPM_Administrators_Dashboard::$url = $dashboard;
$warned_at   = strpos( $no_page, '<p class="wpcpm-warning">The dashboard class says its page is missing.</p>' );
ck( 'while the dashboard\'s page is missing the queue tab says so once, under the bar and above its three cards, and no card or row links there', array(
	substr_count( $no_page, 'The dashboard class says its page is missing.' ),
	false !== $warned_at && $warned_at > (int) strpos( $no_page, '</nav>' ) && $warned_at < (int) strpos( $no_page, '<div class="wpcpm-card' ),
	substr_count( $no_page, 'Open on the Administrator Dashboard' ),
	array_column( counted_cards_of( $no_page ), 0 ),
	substr_count( card_body( $no_page, 'wpcpm-sponsor-posts' ), '<li class="wpcpm-queue-item">' ),
	substr_count( card_body( $no_page, 'wpcpm-sponsor-agreements' ), '<section class="wpcpm-review"' ),
), array( 1, true, 0, array( 'wpcpm-sponsor-applications', 'wpcpm-sponsor-posts', 'wpcpm-sponsor-agreements' ), $limit, $limit ) );
ck( 'and an opened application says it once, in its own place, with no link', array( substr_count( $opened_gone, 'The dashboard class says its page is missing.' ), substr_count( $opened_gone, 'Open on the Administrator Dashboard' ), cards_of( $opened_gone ) ), array( 1, 0, array( 'Gadgetry Inc SAPP-2026-' . sprintf( '%04d', $open_id ) ) ) );

$GLOBALS['pending_posts'] = array();
$GLOBALS['queue']         = array();
$GLOBALS['facts']         = array();
$empty_tab = render_tab( 'queue' );
ck( 'with nothing waiting each of the two cards says so, counts nothing and lists nothing', array(
	false !== strpos( card_body( $empty_tab, 'wpcpm-sponsor-posts' ), '<p>Nothing is waiting.</p>' ),
	false !== strpos( card_body( $empty_tab, 'wpcpm-sponsor-agreements' ), '<p>Nothing is waiting to be read.</p>' ),
	substr_count( card_body( $empty_tab, 'wpcpm-sponsor-posts' ) . card_body( $empty_tab, 'wpcpm-sponsor-agreements' ), '<ol' ),
	array_column( array_slice( counted_cards_of( $empty_tab ), 1 ), 2 ),
), array( true, true, 0, array( '0', '0' ) ) );

echo "\n=== The queue tab's Events card: the sponsors' events waiting, read here and decided on the Administrator Dashboard ===\n";
// The events a sponsor planned wait for the program's OK on the Administrator Dashboard's Events card.
// While the events are in use the queue tab lists them read-only, in a card of their own after the
// posts' card, as the dashboard draws its cards: the dashboard card's own read, oldest first, every
// one, as that card lists every one, and each linking to its own item there. While they are off the
// card is not drawn and nothing of the events is read. A row names who planned the event, read from
// the accounts the tab warms in one query; how long ago, from the moment it was planned, which reads
// as three days here while any other moment reads as four hours; and when it takes place, on the
// clock the time reader takes for itself.
$event_planned                    = (int) strtotime( '2026-09-30 12:00:00 UTC' );
$GLOBALS['ago'][ $event_planned ] = '3 days';
$event_row                        = static function ( $id, $title, $sponsor_name, $planned_author ) use ( $event_planned ) {
	$start = (int) strtotime( '2026-12-01 10:00:00 UTC' );
	return array( 'id' => $id, 'sponsor' => 'recSPONSORTEST001', 'sponsor_name' => $sponsor_name, 'title' => $title, 'description' => 'About it.', 'start' => $start, 'end' => $start + 5400, 'minutes' => 90, 'places' => 0, 'joined' => 0, 'link' => 'https://meet.example/call', 'people' => array(), 'offered' => true, 'program' => false, 'planned_author' => $planned_author, 'planned_at' => $event_planned );
};
$event_said = 'Planned 3 days ago, for December 1, 2026 10:00 am to 11:30 am, 90 minutes, in your time zone (UTC).';
// Two planners with an account, and a third, 6102, whose account is gone; taken out again below.
$GLOBALS['users'][6101]    = new WP_User( 6101, array(), 'Rep One', 'maciej+planner-one@a8c.com' );
$GLOBALS['users'][6103]    = new WP_User( 6103, array(), '<b>Ann</b>', 'maciej+planner-two@a8c.com' );
$GLOBALS['waiting_events'] = array(
	$event_row( 701, 'Office hours', 'TEST Sponsor', 6101 ),
	$event_row( 702, 'Q&A <b>live</b>', '<b>Co</b>', 6102 ),
	$event_row( 703, 'Launch talk', 'TEST Sponsor', 6103 ),
);
$GLOBALS['events_shown']    = false;
$events_off                 = render_tab( 'queue' );
$events_off_read            = ! empty( $GLOBALS['stand_in_reads']['events'] );
$GLOBALS['events_shown']    = true;
$GLOBALS['account_reads']   = array();
$GLOBALS['time_fact_zones'] = array();
$events_tab                 = render_tab( 'queue' );
$events_card                = card_body( $events_tab, 'wpcpm-sponsor-events' );
$events_account_reads       = $GLOBALS['account_reads'];
$events_zones               = $GLOBALS['time_fact_zones'];
ck( 'while the events are off the queue tab draws no Events card and reads nothing of them', array( array_column( counted_cards_of( $events_off ), 0 ), strpos( $events_off, 'wpcpm-sponsor-events' ), $events_off_read ), array( array( 'wpcpm-sponsor-applications', 'wpcpm-sponsor-posts', 'wpcpm-sponsor-agreements' ), false, false ) );
ck( 'while they are on it draws the Events card, titled as the Administrator Dashboard titles its own, after the posts\' card and before the signed agreements\', as the dashboard draws them, with its count of everything waiting', array_slice( counted_cards_of( $events_tab ), 1 ), array(
	array( 'wpcpm-sponsor-posts', 'Sponsor posts to review', '0' ),
	array( 'wpcpm-sponsor-events', 'Events', '3' ),
	array( 'wpcpm-sponsor-agreements', 'Signed agreements', '0' ),
) );
// The Administrator Dashboard's Events card links here at this id, and so does the Overview's count of
// the events waiting: the card has to carry exactly the id both name, read where each names it. The
// dashboard names the tab and the id first, then the card's title, which its links carry for a screen
// reader.
$events_cards_src    = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-administrators-cards.php' );
$events_overview_src = (string) file_get_contents( __DIR__ . '/../includes/class-wpcpm-overview.php' );
ck( 'the card carries exactly the id the Administrator Dashboard\'s Events card links to on this tab and the Overview\'s count of the events waiting opens', array(
	preg_match( "#self::sponsors_screen\\(\\s*'([a-z]+)',\\s*'([a-z-]+)'\\s*,#", method_body( $events_cards_src, 'render_sponsor_events' ), $dashboard_link ) ? array( $dashboard_link[1], $dashboard_link[2] ) : array(),
	preg_match( "#'sponsor_events'\\s*=>\\s*\\\$sponsor_queue \\. '\\#([a-z-]+)'#", $events_overview_src, $overview_link ) ? $overview_link[1] : '',
	preg_match( '#<div class="wpcpm-card" id="([^"]*)"><h2>Events <span class="wpcpm-count">#', $events_tab, $drawn_card ) ? $drawn_card[1] : '',
), array( array( 'queue', 'wpcpm-sponsor-events' ), 'wpcpm-sponsor-events', 'wpcpm-sponsor-events' ) );
ck( 'the card says what it lists and where each event is decided', false !== strpos( $events_card, '<p class="description">Events that sponsors planned and that wait for the program&#039;s OK, oldest first. An event is approved or returned on the Administrator Dashboard.</p>' ), true );
ck( 'an event\'s row: its title, its sponsor and who planned it, how long ago it was planned, when it takes place, and the way to its own item on the Administrator Dashboard', false !== strpos( $events_card, '<ol class="wpcpm-queue"><li class="wpcpm-queue-item"><h3 class="wpcpm-queue-title">Office hours</h3><p>From TEST Sponsor, by Rep One. ' . $event_said . '</p><p><a href="' . $dashboard . '#wpcpm-sponsor-event-701">Open on the Administrator Dashboard</a></p></li>' ), true );
ck( 'its title, its sponsor\'s name and its planner\'s are escaped, and a planner whose account is gone is said to be one', array(
	false !== strpos( $events_card, '<li class="wpcpm-queue-item"><h3 class="wpcpm-queue-title">Q&amp;A &lt;b&gt;live&lt;/b&gt;</h3><p>From &lt;b&gt;Co&lt;/b&gt;, by somebody whose account is gone. ' . $event_said . '</p><p><a href="' . $dashboard . '#wpcpm-sponsor-event-702">Open on the Administrator Dashboard</a></p></li>' ),
	false !== strpos( $events_card, '<li class="wpcpm-queue-item"><h3 class="wpcpm-queue-title">Launch talk</h3><p>From TEST Sponsor, by &lt;b&gt;Ann&lt;/b&gt;. ' . $event_said . '</p>' ),
), array( true, true ) );
ck( 'the rows keep the read\'s order, the oldest first, each linking once to its own item there; nothing here is a form, and no line says the list is cut', array(
	preg_match_all( '#<a href="' . preg_quote( $dashboard, '#' ) . '\#wpcpm-sponsor-event-(\d+)">#', $events_card, $linked ) ? array_map( 'intval', $linked[1] ) : array(),
	substr_count( $events_tab, '<form' ),
	strpos( $events_card, 'Showing the oldest' ),
), array( array( 701, 702, 703 ), 0, false ) );
ck( 'the tab reads the planners in one query: their accounts warmed together, each ID once, then each row reads its own, the one gone among them', $events_account_reads, array( 'cache 6101,6102,6103', 'read 6101', 'read 6102', 'read 6103' ) );
ck( 'and it hands the time reader no clock, one call a row, so the reader takes the viewing manager\'s own', $events_zones, array( null, null, null ) );

// More events waiting than the dashboard's other queue cards list: this card lists every one, as the
// dashboard's Events card does, with no line saying the list is cut, and one planner of them all is
// warmed once.
$GLOBALS['waiting_events'] = array();
for ( $i = 0; $i <= $limit; $i++ ) {
	$GLOBALS['waiting_events'][] = $event_row( 7000 + $i, 'Event ' . $i, 'TEST Sponsor', 6101 );
}
$GLOBALS['account_reads'] = array();
$events_full              = card_body( render_tab( 'queue' ), 'wpcpm-sponsor-events' );
ck( 'with more events waiting than the other queue cards list, the card counts and lists every one, each linked to its own item there, and prints no window line', array(
	preg_match( '#<h2>Events <span class="wpcpm-count">([^<]*)</span></h2>#', $events_full, $events_count ) ? $events_count[1] : '',
	substr_count( $events_full, '<li class="wpcpm-queue-item">' ),
	substr_count( $events_full, '#wpcpm-sponsor-event-' ),
	false !== strpos( $events_full, '#wpcpm-sponsor-event-' . ( 7000 + $limit ) . '"' ),
	false !== strpos( $events_full, 'Administrator Dashboard.</p><ol class="wpcpm-queue">' ),
	strpos( $events_full, 'Showing the oldest' ),
), array( (string) ( $limit + 1 ), $limit + 1, $limit + 1, true, true, false ) );
ck( 'and the planner of them all is warmed once', array( array_values( preg_grep( '/^cache /', $GLOBALS['account_reads'] ) ), count( $GLOBALS['account_reads'] ) ), array( array( 'cache 6101' ), $limit + 2 ) );

// While the dashboard's page is missing there is nowhere to link: the tab's one sentence above the
// cards says so, and the card still lists what waits.
WPCPM_Administrators_Dashboard::$url = '';
$events_no_page = card_body( render_tab( 'queue' ), 'wpcpm-sponsor-events' );
WPCPM_Administrators_Dashboard::$url = $dashboard;
ck( 'while the dashboard\'s page is missing the card still lists every event waiting, and none of them links there', array( substr_count( $events_no_page, '<li class="wpcpm-queue-item">' ), substr_count( $events_no_page, 'Open on the Administrator Dashboard' ) ), array( $limit + 1, 0 ) );

// Past the ceiling the heading prints the menu bubble's form, as the posts' and the agreements'
// headings do, and the card still lists every event that waits, with no line saying the list is cut.
$GLOBALS['waiting_events'] = array();
for ( $i = 0; $i <= WPCPM_Sponsors::COUNT_MAX; $i++ ) {
	$GLOBALS['waiting_events'][] = $event_row( 8000 + $i, 'Event ' . $i, 'TEST Sponsor', 6101 );
}
$events_past = card_body( render_tab( 'queue' ), 'wpcpm-sponsor-events' );
ck( 'past the ceiling the heading prints the bubble\'s "200+", and the card still lists every event that waits, each linked to its own item there, with no window line', array(
	preg_match( '#<h2>Events <span class="wpcpm-count">([^<]*)</span></h2>#', $events_past, $events_past_count ) ? $events_past_count[1] : '',
	substr_count( $events_past, '<li class="wpcpm-queue-item">' ),
	substr_count( $events_past, '#wpcpm-sponsor-event-' ),
	strpos( $events_past, 'Showing the oldest' ),
), array( WPCPM_Sponsors::COUNT_MAX . '+', WPCPM_Sponsors::COUNT_MAX + 1, WPCPM_Sponsors::COUNT_MAX + 1, false ) );

// An event whose post names no author, 0: the tab warms no account for it and reads none, and its row
// says its planner's account is gone, as for an account deleted since.
$GLOBALS['waiting_events'] = array(
	$event_row( 704, 'Unsigned talk', 'TEST Sponsor', 0 ),
	$event_row( 705, 'Signed talk', 'TEST Sponsor', 6101 ),
);
$GLOBALS['account_reads'] = array();
$events_no_author         = card_body( render_tab( 'queue' ), 'wpcpm-sponsor-events' );
ck( 'an event whose post names no author is said to be planned by somebody whose account is gone, and the tab neither warms nor reads an account for it', array(
	false !== strpos( $events_no_author, '<li class="wpcpm-queue-item"><h3 class="wpcpm-queue-title">Unsigned talk</h3><p>From TEST Sponsor, by somebody whose account is gone. ' . $event_said . '</p>' ),
	false !== strpos( $events_no_author, '<li class="wpcpm-queue-item"><h3 class="wpcpm-queue-title">Signed talk</h3><p>From TEST Sponsor, by Rep One. ' . $event_said . '</p>' ),
	$GLOBALS['account_reads'],
), array( true, true, array( 'cache 6101', 'read 6101' ) ) );

$GLOBALS['waiting_events'] = array();
$events_empty              = card_body( render_tab( 'queue' ), 'wpcpm-sponsor-events' );
ck( 'with nothing waiting the card says so in one sentence, counts nothing and lists nothing', array(
	false !== strpos( $events_empty, 'Administrator Dashboard.</p><p>Nothing is waiting.</p></div>' ),
	false !== strpos( $events_empty, '<h2>Events <span class="wpcpm-count">0</span></h2>' ),
	substr_count( $events_empty, '<ol' ),
), array( true, true, 0 ) );
$GLOBALS['events_shown'] = false;
unset( $GLOBALS['users'][6101], $GLOBALS['users'][6103] );

echo "\n=== Each tab draws its own cards and reads only what it draws ===\n";
// Every card's class is loaded by now, the queue's among them, so the eight tabs are read once more,
// with the launch switch on, so the bar holds the last one too, and the events in use, so the queue
// tab draws their card.
$all         = array();
$reads       = array();
$events_read = array();
$GLOBALS['settings']['sponsor_tiers_live'] = 1;
$GLOBALS['events_shown']                   = true;
foreach ( $slugs as $slug ) {
	$all[ $slug ]         = render_tab( $slug );
	$reads[ $slug ]       = reads_now();
	$events_read[ $slug ] = ! empty( $GLOBALS['stand_in_reads']['events'] );
}
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$GLOBALS['events_shown'] = false;
ck( 'each tab draws the cards of its job and none of another tab\'s: the applications, the posts, the events and the signed agreements waiting only on Waiting for review, the index only on Sponsors, the offers only on Offers and codes, the reports only on Reports, the listing for the handbook only on Handbook listing', array_map( 'cards_of', $all ), array(
	'queue'      => array( 'Sponsor applications', 'Sponsor posts to review', 'Events', 'Signed agreements' ),
	'sponsors'   => array( 'Sponsors' ),
	'accounts'   => array( 'Invitations', 'Sponsor accounts' ),
	'offers'     => array( 'Offers and codes' ),
	'interests'  => array( 'Interests' ),
	'reports'    => array( 'Reports' ),
	'agreements' => array( 'Agreements' ),
	'listing'    => array( 'Listing for the handbook' ),
) );
$read = static function ( $index, $accounts, $applications, $posts, $documents, $out_of_force ) {
	return array(
		'index'             => $index,
		'accounts'          => $accounts,
		'applications'      => $applications,
		'posts'             => $posts,
		'waiting documents' => $documents,
		'out of force'      => $out_of_force,
	);
};
// The fixture's TEST Sponsor is out of force here, so the Agreements tab reads the documents out of
// force for its block, and no other tab does.
ck( 'each tab reads only what it draws: the applications, the posts and the documents waiting on the queue alone, the documents out of force on Agreements alone, the index on the seven tabs that draw from it, the accounts by sponsor where they are counted or listed', $reads, array(
	'queue'      => $read( false, false, true, true, true, false ),
	'sponsors'   => $read( true, true, false, false, false, false ),
	'accounts'   => $read( true, true, false, false, false, false ),
	'offers'     => $read( true, true, false, false, false, false ),
	'interests'  => $read( true, false, false, false, false, false ),
	'reports'    => $read( true, false, false, false, false, false ),
	'agreements' => $read( true, false, false, false, false, true ),
	'listing'    => $read( true, false, false, false, false, false ),
) );
ck( 'and the events waiting are read on the queue alone', $events_read, array_merge( array_fill_keys( $slugs, false ), array( 'queue' => true ) ) );
// An open application the dashboard's card lists draws no form here, so the decisions an opened
// application still draws are read on a decided one: Put back in the queue and Delete for good.
$opened_decided = render_screen( array( 'wpcpm_sapp_id' => $spam_id ) );
ck( 'and every form a tab posts carries the once attribute, the record-keeping an opened application keeps here among them', array(
	array_map(
		static function ( $html ) {
			return post_forms( without_invitations( $html ), true );
		},
		array_merge( $all, array( 'opened' => $opened_decided ) )
	),
	post_forms( $opened_decided ),
), array( array_fill_keys( array_merge( $slugs, array( 'opened' ) ), array() ), array( 'wpcpm_sapp_reopen', 'wpcpm_sapp_purge' ) ) );

echo "\n=== The menu bubble is cached for a minute (1.98.1) ===\n";
$GLOBALS['transients'] = array();
$GLOBALS['queries']    = 0;
$first  = WPCPM_Sponsors::attention_count();
$after_first = (int) $GLOBALS['queries'];
$second = WPCPM_Sponsors::attention_count();
ck( 'the second call within a minute reads the transient and runs no query: the first counted, the second added nothing', array( $first === $second, $after_first > 0, (int) $GLOBALS['queries'] - $after_first, isset( $GLOBALS['transients']['wpcpm_sponsors_attention'] ), $GLOBALS['transients']['wpcpm_sponsors_attention']['ttl'] ), array( true, true, 0, true, 60 ) );
WPCPM_Sponsors::forget_attention();
ck( 'forgetting drops the transient, so the next page counts again', isset( $GLOBALS['transients']['wpcpm_sponsors_attention'] ), false );
$src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'a state change forgets the bubble: the three hooks are registered', array( substr_count( $src, "add_action( 'transition_post_status', array( __CLASS__, 'forget_attention_on_status' )" ), substr_count( $src, "add_action( 'updated_post_meta', array( __CLASS__, 'forget_attention_on_meta' )" ), substr_count( $src, "add_action( 'added_post_meta', array( __CLASS__, 'forget_attention_on_meta' )" ), substr_count( $src, "delete_transient( self::TRANSIENT_ATTENTION )" ) >= 2 ), array( 1, 1, 1, true ) );
// The Administrator Dashboard's strip counts the events waiting while they are in use (its Events
// waiting tile), so the bubble counts them too, and the two agree; with the events off neither does.
$GLOBALS['waiting_events'] = array( $event_row( 801, 'One', 'TEST Sponsor', 6101 ), $event_row( 802, 'Two', 'TEST Sponsor', 6101 ) );
WPCPM_Sponsors::forget_attention();
$bubble_off              = WPCPM_Sponsors::attention_count();
$GLOBALS['events_shown'] = true;
WPCPM_Sponsors::forget_attention();
$bubble_on               = WPCPM_Sponsors::attention_count();
$GLOBALS['events_shown'] = false;
WPCPM_Sponsors::forget_attention();
ck( 'while the events are in use the bubble counts the events waiting too, as the Administrator Dashboard\'s strip counts them, and while they are off it counts none', array( $bubble_on - $bubble_off, WPCPM_Sponsors::attention_count() === $bubble_off ), array( 2, true ) );
ck( 'and the count takes the queue\'s own count of the events, and names the event\'s state key among the keys whose change forgets it, which is pressed once the event store\'s stand-in is loaded, under Activation', array( false !== strpos( method_body( $src, 'attention_meta_keys' ), 'WPCPM_Sponsor_Event::META_STATE' ), false !== strpos( method_body( $src, 'attention_count' ), 'WPCPM_Sponsor_Event_Queue::count_waiting()' ) ), array( true, true ) );
$GLOBALS['waiting_events'] = array();
WPCPM_Sponsors::forget_attention();

echo "\n=== The retention run is on the clock ===\n";
$GLOBALS['cron'] = array();
WPCPM_Sponsors::schedule_cron();
ck( 'schedule_cron() puts the application purge on the clock beside the agreement discard, daily, nine hours out', array( isset( $GLOBALS['cron']['wpcpm_purge_sponsor_applications'] ), isset( $GLOBALS['cron']['wpcpm_sponsor_agreement_discard'] ), $GLOBALS['cron']['wpcpm_purge_sponsor_applications'] - time() > 8 * HOUR_IN_SECONDS ), array( true, true, true ) );
$before = $GLOBALS['cron']['wpcpm_purge_sponsor_applications'];
WPCPM_Sponsors::schedule_cron();
ck( 'and a second call leaves a job already scheduled alone', $GLOBALS['cron']['wpcpm_purge_sponsor_applications'], $before );
$sponsors_src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
$deactivate   = substr( $sponsors_src, (int) strpos( $sponsors_src, 'public function deactivate()' ) );
$deactivate   = substr( $deactivate, 0, (int) strpos( $deactivate, "\n\t}\n" ) );
ck( 'and deactivation takes the purge off the clock beside the discard', array( substr_count( $deactivate, 'wp_clear_scheduled_hook( WPCPM_Sponsor_Application::CRON_PURGE )' ), substr_count( $deactivate, 'wp_clear_scheduled_hook( WPCPM_Sponsor_Agreement::CRON_DISCARD )' ) ), array( 1, 1 ) );

echo "\n=== Uninstall and deactivation take the applications with them ===\n";
$GLOBALS['deleted_attachments'] = array();
$GLOBALS['opts'][ WPCPM_Sponsor_Application::OPT_PAGE ] = 77;
$GLOBALS['opts'][ WPCPM_Sponsor_Application::OPT_LOG ]  = array( array( 'at' => 1, 'id' => 1, 'reference' => 'SAPP-2026-0001', 'state' => 'spam', 'days' => 30, 'actor' => 0 ) );
$kept_id = seed_sponsor_application( 'Approved Co', 'approved', array(), array( 'colour' => 700, 'white' => 0 ) );
$gone_id = seed_sponsor_application( 'Open Co', 'new', array(), array( 'colour' => 701, 'white' => 702 ) );
WPCPM_Sponsors::schedule_cron();
$module->deactivate();
ck( 'deactivation takes the purge off the clock', isset( $GLOBALS['cron']['wpcpm_purge_sponsor_applications'] ), false );
WPCPM_Sponsors::schedule_cron();
// What the old sponsors sync kept, as a site uninstalled before its move onto the site finished
// still holds it: the migration's clean-up never ran there (1.111.0).
$sync_options = array( 'wpcpm_sponsors_state', 'wpcpm_sponsors_report', 'wpcpm_sponsors_last_sync', 'wpcpm_sponsors_last_error', 'wpcpm_sponsors_lock', 'wpcpm_sponsors_sync_shrink' );
foreach ( $sync_options as $option ) {
	$GLOBALS['opts'][ $option ] = 'held';
}
$GLOBALS['cron']['wpcpm_sponsors_daily']     = time();
$GLOBALS['cron']['wpcpm_sponsors_sync_tick'] = time();
$module->uninstall();
ck( 'uninstall deletes every application, whatever its state', array( get_post( $open_id ), get_post( $held_id ), get_post( $done_id ), get_post( $kept_id ), get_post( $gone_id ) ), array( null, null, null, null, null ) );
ck( 'and the files of the ones nobody approved, never an approved one\'s', array( in_array( 640, $GLOBALS['deleted_attachments'], true ), in_array( 701, $GLOBALS['deleted_attachments'], true ), in_array( 702, $GLOBALS['deleted_attachments'], true ), in_array( 700, $GLOBALS['deleted_attachments'], true ) ), array( true, true, true, false ) );
ck( 'the page option and the log go, and the purge is off the clock', array( get_option( WPCPM_Sponsor_Application::OPT_PAGE ), get_option( WPCPM_Sponsor_Application::OPT_LOG ), isset( $GLOBALS['cron']['wpcpm_purge_sponsor_applications'] ) ), array( false, false, false ) );

$sponsors_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'uninstall() reaches the approval\'s lock sweep behind a guard, and clears the purge hook', array( false !== strpos( method_body( $sponsors_src, 'uninstall' ), 'WPCPM_Sponsor_Approval::delete_all()' ), substr_count( $sponsors_src, 'wp_clear_scheduled_hook( WPCPM_Sponsor_Application::CRON_PURGE )' ) ), array( true, 2 ) );
// Through the names the migration keeps since the class that declared them went (1.111.0): its
// clean-up reads the same two lists, so the two places cannot drift apart.
ck( 'uninstall still takes the old sponsors sync\'s six options and two hooks, through the names the migration keeps', array(
	array_values( array_intersect( $sync_options, array_keys( $GLOBALS['opts'] ) ) ),
	isset( $GLOBALS['cron']['wpcpm_sponsors_daily'] ) || isset( $GLOBALS['cron']['wpcpm_sponsors_sync_tick'] ),
	false !== strpos( method_body( $sponsors_src, 'uninstall' ), 'WPCPM_Sponsors_Migration::SYNC_OPTIONS' ),
	false !== strpos( method_body( $sponsors_src, 'uninstall' ), 'WPCPM_Sponsors_Migration::SYNC_HOOKS' ),
), array( array(), false, true, true ) );

echo "\n=== Add a sponsor (1.111.0) ===\n";
// A site whose move onto the site is complete; the mentors index the Edit form's picker offers,
// two Active mentors and one who is not; a second program manager, so the manager select has a
// real choice to make. User 9, the Stranger, has an account and no program capability. The
// uninstall above emptied the store, so every sponsor from here on is one these sections add.
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, array( 'complete' => true, 'rows' => 0, 'posts' => 0, 'seeded' => 0, 'unseeded' => array(), 'unmapped' => array(), 'at' => time(), 'runs' => 1 ) );
$GLOBALS['sponsorship'] = array(
	'recSEED0000000011' => array( 'name' => 'Zoe Mentor', 'profile' => '', 'status' => 'Active', 'user_id' => 0, 'sponsored' => false, 'wants' => false, 'expertise' => array() ),
	'recSEED0000000012' => array( 'name' => 'Adam Mentor', 'profile' => '', 'status' => 'Active', 'user_id' => 0, 'sponsored' => false, 'wants' => false, 'expertise' => array() ),
	'recSEED0000000013' => array( 'name' => 'Bea Mentor', 'profile' => '', 'status' => 'Paused', 'user_id' => 0, 'sponsored' => false, 'wants' => false, 'expertise' => array() ),
);
$GLOBALS['users'][2]                        = new WP_User( 2, array( 'administrator' ), 'Second Manager', 'maciej+manager@a8c.com' );
$GLOBALS['manage']                          = array( 1, 2 );
$GLOBALS['uid']                             = 1;
$GLOBALS['nonce_ok']                        = true;
$GLOBALS['get']                             = array();
$GLOBALS['settings']['sponsor_on_inactive'] = 'keep';
$GLOBALS['nonce_checked']                   = array();

$before       = array_keys( WPCPM_Sponsors_Index::rows() );
$audit_before = count( $GLOBALS['audit'] );
$r            = post(
	array(
		'wpcpm_name'           => '  Quill Example  ',
		'wpcpm_website'        => 'quill.example',
		'wpcpm_contact_person' => 'Rep Quill',
		'wpcpm_contact_email'  => 'maciej+quill@a8c.com',
		'wpcpm_option'         => 'Sponsor mentors + tools/services',
		// The empty value first, as the form posts it: it says the boxes were drawn.
		'wpcpm_support'        => array( '', 'Sponsor tools or services', 'Something the form never offered' ),
		'wpcpm_product_type'   => 'Plugin',
		'wpcpm_manager'        => '2',
		'wpcpm_notes'          => "Met at a WordCamp.\nWrite again in October.",
	),
	array( $module, 'handle_add' )
);
$added = array_values( array_diff( array_keys( WPCPM_Sponsors_Index::rows() ), $before ) );
$Q     = isset( $added[0] ) ? (string) $added[0] : '';
$row   = (array) WPCPM_Sponsors_Index::row( $Q ) + WPCPM_Sponsors_Index::empty_row();
ck( 'Add creates an In review sponsor under a minted key with the fields given and lands on its edit form', array(
	$r,
	count( $added ),
	1 === preg_match( '/^spo[A-Za-z0-9]{14}$/', $Q ),
	end( $GLOBALS['nonce_checked'] ),
), array(
	array( 'redirect', 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=sponsors&edit=' . $Q, 'sponsor-added' ),
	1,
	true,
	'wpcpm_sponsor_add',
) );
ck( 'with every field it was given, cleaned, and the site\'s own facts: created today, no consent, no account, no mentor', array_intersect_key( $row, array_flip( array( 'record_id', 'slug', 'name', 'website', 'status', 'option', 'support', 'product_type', 'contact_person', 'contact_email', 'manager', 'mentors', 'consent', 'created', 'dashboard_account', 'notes' ) ) ), array(
	'record_id'         => $Q,
	'slug'              => 'quill-example',
	'name'              => 'Quill Example',
	'website'           => 'https://quill.example',
	'status'            => 'In review',
	'option'            => 'Sponsor mentors + tools/services',
	'support'           => array( 'Sponsor tools or services' ),
	'product_type'      => 'Plugin',
	'contact_person'    => 'Rep Quill',
	'contact_email'     => 'maciej+quill@a8c.com',
	'manager'           => '2',
	'mentors'           => array(),
	'consent'           => false,
	'created'           => gmdate( 'Y-m-d' ),
	'dashboard_account' => false,
	'notes'             => "Met at a WordCamp.\nWrite again in October.",
) );
$logged = last_audit();
ck( 'and it is logged as a manager\'s, naming the fields set and none of their values', array(
	count( $GLOBALS['audit'] ) - $audit_before,
	array( $logged['kind'], $logged['sponsor'], $logged['subject'], $logged['actor'], $logged['ground'], $logged['evidence'], $logged['data'] ),
	false === strpos( (string) wp_json_encode( $logged ), 'quill@' ) && false === strpos( (string) wp_json_encode( $logged ), 'WordCamp' ),
), array(
	1,
	array( 'sponsor_added', $Q, $Q, 1, 'manager', 'index', array( 'fields' => array( 'name', 'website', 'status', 'contact_person', 'contact_email', 'option', 'support', 'product_type', 'manager', 'notes' ) ) ),
	true,
) );

$count        = count( WPCPM_Sponsors_Index::rows() );
$audit_before = count( $GLOBALS['audit'] );
$outcomes     = array();
foreach ( array(
	array( 'wpcpm_name' => '   ', 'wpcpm_website' => 'nameless.example' ),
	array( 'wpcpm_website' => 'nameless.example' ),
	array( 'wpcpm_name' => str_repeat( 'n', 201 ) ),
	array( 'wpcpm_name' => 'Status Example', 'wpcpm_status' => '' ),
	array( 'wpcpm_name' => 'Status Example', 'wpcpm_status' => 'Maybe later' ),
) as $fields ) {
	$r          = post( $fields, array( $module, 'handle_add' ) );
	$outcomes[] = isset( $r[2] ) ? $r[2] : $r;
}
ck( 'a name is required: blank, missing or longer than 200 characters, it refuses the Add, as does a status that is not one of the five, and nothing is written; a name too long says so', array( $outcomes, count( WPCPM_Sponsors_Index::rows() ) - $count, count( $GLOBALS['audit'] ) - $audit_before ), array( array( 'sponsor-rejected', 'sponsor-rejected', 'name-too-long', 'sponsor-rejected', 'sponsor-rejected' ), 0, 0 ) );
$r = post( array( 'wpcpm_name' => str_repeat( 'L', 200 ), 'wpcpm_status' => 'Paused' ), array( $module, 'handle_add' ) );
ck( 'while two hundred characters is a name, and a status the manager chose is kept', array( isset( $r[2] ) ? $r[2] : $r, count( WPCPM_Sponsors_Index::rows() ) - $count, WPCPM_Sponsors_Index::status_counts() ), array( 'sponsor-added', 1, array( 'In review' => 1, 'Paused' => 1 ) ) );
// A list of mentors posted to Add, as a hand-made form could: the picker is the Edit form's.
$before = array_keys( WPCPM_Sponsors_Index::rows() );
$r      = post( array( 'wpcpm_name' => 'Mentored Example', 'wpcpm_mentors' => array( '', 'recSEED0000000011' ) ), array( $module, 'handle_add' ) );
$added  = array_values( array_diff( array_keys( WPCPM_Sponsors_Index::rows() ), $before ) );
ck( 'and Add links no mentor, even when a list of them is posted', array( isset( $r[2] ) ? $r[2] : $r, ( (array) WPCPM_Sponsors_Index::row( isset( $added[0] ) ? (string) $added[0] : '' ) + WPCPM_Sponsors_Index::empty_row() )['mentors'] ), array( 'sponsor-added', array() ) );

echo "\n=== Edit (1.111.0) ===\n";
$html           = render_tab( 'sponsors', array( 'edit' => $Q ) );
$GLOBALS['get'] = array();
$edit_card      = substr( $html, (int) strpos( $html, 'id="wpcpm-sponsor-edit"' ) );
$edit_card      = substr( $edit_card, 0, (int) strpos( $edit_card, 'Key: ' ) + 80 );
ck( 'the edit card comes first when ?edit= names a known sponsor, keyed to it', array(
	false !== strpos( $html, 'id="wpcpm-sponsor-edit"' ),
	false !== strpos( $html, '</nav><div class="wpcpm-card" id="wpcpm-sponsor-edit">' ),
	false !== strpos( $edit_card, 'nonce-wpcpm_sponsor_edit_' . $Q ),
	false !== strpos( $edit_card, '<input type="hidden" name="action" value="wpcpm_sponsor_edit" />' ),
	false !== strpos( $edit_card, '<input type="hidden" name="wpcpm_sponsor" value="' . $Q . '" />' ),
), array( true, true, true, true, true ) );
ck( 'with the name, the website, the status, the contacts, the sponsorship shape, the manager and the notes, each holding what the record holds', array(
	false !== strpos( $edit_card, 'name="wpcpm_name" value="Quill Example"' ),
	false !== strpos( $edit_card, 'name="wpcpm_website" value="https://quill.example"' ),
	false !== strpos( $edit_card, '<option value="In review" selected>In review</option>' ),
	5 === substr_count( substr( $edit_card, (int) strpos( $edit_card, 'name="wpcpm_status"' ), (int) strpos( substr( $edit_card, (int) strpos( $edit_card, 'name="wpcpm_status"' ) ), '</select>' ) ), '<option ' ),
	false !== strpos( $edit_card, 'name="wpcpm_contact_person" value="Rep Quill"' ),
	false !== strpos( $edit_card, 'type="email"' ) && false !== strpos( $edit_card, 'name="wpcpm_contact_email" value="maciej+quill@a8c.com"' ),
	false !== strpos( $edit_card, '<option value="Sponsor mentors + tools/services" selected>' ),
	6 === substr_count( $edit_card, 'type="checkbox" name="wpcpm_support[]"' ) && false !== strpos( $edit_card, 'value="Sponsor tools or services" checked' ),
	false !== strpos( $edit_card, '<option value="Plugin" selected>Plugin</option>' ),
	false !== strpos( $edit_card, '<option value="">Nobody yet</option>' ) && false !== strpos( $edit_card, '<option value="2" selected>Second Manager</option>' ) && false !== strpos( $edit_card, '<option value="1">Maciej (Matt) Pilarski</option>' ),
	false !== strpos( $edit_card, "Write again in October.</textarea>" ),
), array_fill( 0, 11, true ) );
ck( 'the sponsored mentors are checkboxes from the mentors index, the Active ones first, each "Name (status)"', array(
	substr_count( $edit_card, 'type="checkbox" name="wpcpm_mentors[]"' ),
	before( $edit_card, 'Adam Mentor (Active)', 'Zoe Mentor (Active)' ),
	before( $edit_card, 'Zoe Mentor (Active)', 'Bea Mentor (Paused)' ),
	strpos( $edit_card, 'value="recSEED0000000012" checked' ),
), array( 3, true, true, false ) );
ck( 'and the key and the listing handle are shown under the form, read only', array( false !== strpos( $edit_card, 'Key: ' . $Q . '; listing handle: quill-example' ), before( $edit_card, '</form>', 'Key: ' . $Q ), strpos( $edit_card, 'name="wpcpm_slug"' ) ), array( true, true, false ) );
// The Add form is the Sponsors card's, which the Edit form is drawn in place of: read on the tab.
$sponsors_tab = render_tab( 'sponsors' );
$add_form     = substr( $sponsors_tab, (int) strpos( $sponsors_tab, 'id="wpcpm-sponsor-add"' ) );
ck( 'the Add form under the table offers the same fields but the mentors, a new sponsor In review by default', array(
	false !== strpos( $add_form, 'nonce-wpcpm_sponsor_add' ),
	false !== strpos( $add_form, '<option value="In review" selected>In review</option>' ),
	strpos( $add_form, 'wpcpm_mentors[]' ),
	false !== strpos( $add_form, 'name="wpcpm_notes"' ) && false !== strpos( $add_form, 'name="wpcpm_manager"' ) && false !== strpos( $add_form, 'name="wpcpm_product_type"' ),
), array( true, true, false, true ) );
ck( 'and a key the store does not hold opens nothing', strpos( render_tab( 'sponsors', array( 'edit' => 'spoZZZZ0000000009' ) ), 'id="wpcpm-sponsor-edit"' ), false );
$GLOBALS['get'] = array();

$GLOBALS['nonce_checked'] = array();
$audit_before             = count( $GLOBALS['audit'] );
// What the Edit form posts, with three fields changed; the contact person and the contact
// address are not posted at all, so they must be left alone.
$edit = array(
	'wpcpm_sponsor'      => $Q,
	'wpcpm_name'         => 'Quill Example',
	'wpcpm_website'      => 'https://quill.example/tools',
	'wpcpm_status'       => 'In review',
	'wpcpm_option'       => 'Sponsor mentors + tools/services',
	'wpcpm_support'      => array( '', 'Sponsor tools or services' ),
	'wpcpm_product_type' => 'Plugin',
	'wpcpm_manager'      => '2',
	'wpcpm_mentors'      => array( '', 'recSEED0000000012' ),
	'wpcpm_notes'        => 'Write again in November.',
);
$r      = post( $edit, array( $module, 'handle_edit' ) );
$after  = (array) WPCPM_Sponsors_Index::row( $Q ) + WPCPM_Sponsors_Index::empty_row();
$logged = last_audit();
ck( 'Edit changes the fields it is given and audits their names', array(
	$r,
	end( $GLOBALS['nonce_checked'] ),
	array( $after['name'], $after['website'], $after['mentors'], $after['notes'], $after['contact_person'], $after['contact_email'], $after['support'], $after['manager'] ),
	count( $GLOBALS['audit'] ) - $audit_before,
	array( $logged['kind'], $logged['sponsor'], $logged['ground'], $logged['evidence'], $logged['data'] ),
	false === strpos( (string) wp_json_encode( $logged ), 'November' ),
), array(
	array( 'redirect', 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=sponsors&edit=' . $Q, 'sponsor-saved' ),
	'wpcpm_sponsor_edit_' . $Q,
	array( 'Quill Example', 'https://quill.example/tools', array( 'recSEED0000000012' ), 'Write again in November.', 'Rep Quill', 'maciej+quill@a8c.com', array( 'Sponsor tools or services' ), '2' ),
	1,
	array( 'sponsor_edited', $Q, 'manager', 'index', array( 'fields' => array( 'website', 'mentors', 'notes' ) ) ),
	true,
) );

echo "\n=== The Tier box (1.112.0) ===\n";
// A manager surface: it draws and saves whether or not Sponsor tiers is switched on in Settings
// (the sponsor_tiers_live setting), so this suite never sets that option.
$audit_before = count( $GLOBALS['audit'] );
$r            = post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_tier' => 'supporter', 'wpcpm_tier_until' => '2027-08-31', 'wpcpm_funded_line' => 'Mentor stipends' ), array( $module, 'handle_edit' ) );
$after        = (array) WPCPM_Sponsors_Index::row( $Q ) + WPCPM_Sponsors_Index::empty_row();
$logged       = last_audit();
ck( 'Edit saves the Tier box: the tier, the last day of the term and the funded line land in the row, audited by name', array(
	isset( $r[2] ) ? $r[2] : $r,
	array( $after['tier'], $after['tier_until'], $after['funded_line'] ),
	count( $GLOBALS['audit'] ) - $audit_before,
	$logged['data'],
), array(
	'sponsor-saved',
	array( 'supporter', '2027-08-31', 'Mentor stipends' ),
	1,
	array( 'fields' => array( 'tier', 'tier_until', 'funded_line' ) ),
) );

$tier_before = ( (array) WPCPM_Sponsors_Index::row( $Q ) + WPCPM_Sponsors_Index::empty_row() )['tier'];
$r           = post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_tier' => 'gold' ), array( $module, 'handle_edit' ) );
ck( 'a tier the ladder does not know refuses the save', array(
	isset( $r[2] ) ? $r[2] : $r,
	( (array) WPCPM_Sponsors_Index::row( $Q ) + WPCPM_Sponsors_Index::empty_row() )['tier'],
), array( 'sponsor-rejected', $tier_before ) );

$r = post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_tier_until' => '2027-02-30' ), array( $module, 'handle_edit' ) );
ck( 'a day that is not one refuses the save', isset( $r[2] ) ? $r[2] : $r, 'sponsor-rejected' );

$r = post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_funded_line' => str_repeat( 'f', 201 ) ), array( $module, 'handle_edit' ) );
ck( 'a funded line over 200 characters refuses the save, and says the line is too long', isset( $r[2] ) ? $r[2] : $r, 'funded-too-long' );

// $Q still holds the term posted above (2027-08-31): a real form, its tier flipped to Mentor
// sponsor without touching the date field, posts that same date right back.
$r      = post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_tier' => 'mentor', 'wpcpm_tier_until' => '2027-08-31' ), array( $module, 'handle_edit' ) );
$after  = (array) WPCPM_Sponsors_Index::row( $Q ) + WPCPM_Sponsors_Index::empty_row();
$logged = last_audit();
ck( 'a Friend keeps no term: the day is dropped, the tier saved', array(
	isset( $r[2] ) ? $r[2] : $r,
	array( $after['tier'], $after['tier_until'] ),
	$logged['data'],
), array(
	'sponsor-saved',
	array( 'mentor', '' ),
	array( 'fields' => array( 'tier', 'tier_until' ) ),
) );

// The renewal the thirty-day mail asks for, made before the term ends (1.115.0): the term the save
// moves off is owed its report, and the row says so; a second save while it is still owed keeps it.
$W      = 'spoAAAA0000000031';
$w_owed = array( 'tier' => 'backer', 'until' => '2026-09-30', 'funded_line' => 'Stipends for two mentors' );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $W, 'name' => 'Wren Example', 'status' => 'Approved', 'tier' => 'backer', 'tier_until' => '2026-09-30', 'funded_line' => 'Stipends for two mentors' ) ) );
$r      = post( array( 'wpcpm_sponsor' => $W, 'wpcpm_tier' => 'champion', 'wpcpm_tier_until' => '2027-09-30', 'wpcpm_funded_line' => 'A co-hosted event' ), array( $module, 'handle_edit' ) );
$after  = (array) WPCPM_Sponsors_Index::row( $W ) + WPCPM_Sponsors_Index::empty_row();
$logged = last_audit();
ck( 'a renewal on the Edit form leaves the term it moved off owed its report, the tier it held, its last day and the funded line it had before the save, and the log names what the manager changed', array(
	isset( $r[2] ) ? $r[2] : $r,
	array( $after['tier'], $after['tier_until'], $after['funded_line'], $after['report_owed'] ),
	$logged['data'],
), array(
	'sponsor-saved',
	array( 'champion', '2027-09-30', 'A co-hosted event', $w_owed ),
	array( 'fields' => array( 'tier', 'tier_until', 'funded_line' ) ),
) );
$r     = post( array( 'wpcpm_sponsor' => $W, 'wpcpm_tier_until' => '2027-10-31' ), array( $module, 'handle_edit' ) );
$after = (array) WPCPM_Sponsors_Index::row( $W ) + WPCPM_Sponsors_Index::empty_row();
ck( 'a second save while that term has no report keeps it: the first owed term wins', array( isset( $r[2] ) ? $r[2] : $r, $after['tier_until'], $after['report_owed'] ), array( 'sponsor-saved', '2027-10-31', $w_owed ) );

$no_edit_html   = render_tab( 'sponsors' );
$GLOBALS['get'] = array();
$add_form_now   = substr( $no_edit_html, (int) strpos( $no_edit_html, 'id="wpcpm-sponsor-add"' ) );
$before_keys    = array_keys( WPCPM_Sponsors_Index::rows() );
$r              = post( array( 'wpcpm_name' => 'Tierless Example', 'wpcpm_tier' => 'partner' ), array( $module, 'handle_add' ) );
$added_now      = array_values( array_diff( array_keys( WPCPM_Sponsors_Index::rows() ), $before_keys ) );
$U              = isset( $added_now[0] ) ? (string) $added_now[0] : '';
ck( 'the Add form knows nothing of the Tier box, and Add ignores tier fields posted to it', array(
	false !== strpos( $add_form_now, 'wpcpm_tier' ),
	isset( $r[2] ) ? $r[2] : $r,
	( (array) WPCPM_Sponsors_Index::row( $U ) + WPCPM_Sponsors_Index::empty_row() )['tier'],
), array( false, 'sponsor-added', '' ) );

$u_html         = render_tab( 'sponsors', array( 'edit' => $U ) );
$GLOBALS['get'] = array();
$u_card         = substr( $u_html, (int) strpos( $u_html, 'id="wpcpm-sponsor-edit"' ) );
ck( 'the Edit form draws the Tier box after the program manager: the five tiers with Friend chosen for an unset row, the term as a date input, the funded line', array(
	before( $u_card, 'name="wpcpm_manager"', 'name="wpcpm_tier"' ),
	before( $u_card, 'name="wpcpm_tier"', 'name="wpcpm_tier_until"' ),
	before( $u_card, 'name="wpcpm_tier_until"', 'name="wpcpm_funded_line"' ),
	before( $u_card, 'name="wpcpm_funded_line"', 'name="wpcpm_mentors[]"' ),
	false !== strpos( $u_card, '<option value="mentor" selected' ),
	false !== strpos( $u_card, 'type="date"' ),
), array( true, true, true, true, true, true ) );
// A Backer's line is public from launch day, and copied into the handbook: the help under the one
// field a manager types it into is the only place that can say so before it is typed.
ck( 'the funded line\'s help says where it is shown, the sponsor\'s dashboard and its report, and no public page', false !== strpos( $u_card, 'name="wpcpm_funded_line" value="" maxlength="200" /><p class="description">Shown from Backer up on the sponsor&#039;s dashboard and in its report, never on the Partners page: one line, for example the mentor stipends of the Developer Track.</p>' ), true );

echo "\n=== The Tier box's extra events ===\n";
// Three sponsors of their own, taken out again at the end so that nothing after this section counts
// them: a Champion and a Supporter whose terms run to 31 March 2027, and a Champion with no term.
// The box is drawn for a tier that includes the co-hosted event, the one the number adds to, and what
// it saves is kept with the term's last day as the save leaves it, so the store counts it for that
// term alone (bin/test-sponsor-events.php). Today is pinned, since the box says whether the term a
// number was granted for has ended.
$GLOBALS['today'] = '2026-10-06';
$X_CHAMPION       = 'spoAAAA0000000061';
$X_SUPPORTER      = 'spoAAAA0000000062';
$X_TERMLESS       = 'spoAAAA0000000063';
$X_LAPSED         = 'spoAAAA0000000064';
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $X_CHAMPION, 'name' => 'Extra Champion Co', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '2027-03-31' ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $X_SUPPORTER, 'name' => 'Extra Supporter Co', 'status' => 'Approved', 'tier' => 'supporter', 'tier_until' => '2027-03-31' ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $X_TERMLESS, 'name' => 'Extra Termless Co', 'status' => 'Approved', 'tier' => 'champion' ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $X_LAPSED, 'name' => 'Extra Lapsed Co', 'status' => 'Approved', 'tier' => 'champion', 'tier_until' => '2027-03-31', 'event_allowance' => array( 'until' => '2027-03-31', 'extra' => 2 ) ) ) );

/**
 * A sponsor's Edit card, as the Sponsors tab draws it with `?edit=` naming the sponsor.
 *
 * @param string $key The sponsor.
 * @return string
 */
function extra_card( $key ) {
	$html           = render_tab( 'sponsors', array( 'edit' => $key ) );
	$GLOBALS['get'] = array();
	return substr( $html, (int) strpos( $html, 'id="wpcpm-sponsor-edit"' ) );
}

/**
 * The Extra events this term row as drawn, from its opening to its end, or '' when it is not drawn.
 *
 * @param string $card An Edit card.
 * @return string
 */
function extra_row( $card ) {
	$at = strpos( $card, '<tr><th scope="row"><label for="wpcpm-edit-event-allowance">' );
	return false === $at ? '' : substr( $card, $at, (int) strpos( $card, '</tr>', $at ) + 5 - $at );
}

/** A sponsor's allowance as the store holds it. */
function allowance_of( $key ) {
	return ( (array) WPCPM_Sponsors_Index::row( $key ) + WPCPM_Sponsors_Index::empty_row() )['event_allowance'];
}

$extra_help     = 'Co-hosted events this sponsor may hold in the term that ends on the Term ends day, beyond the one its tier includes. A renewal that moves that day and leaves this box as it is starts the new term with none. Without a term nothing is saved here: a sponsor with no term waits only for its last event to take place.';
$x_card         = extra_card( $X_CHAMPION );
$x_add_html     = render_tab( 'sponsors' );
$GLOBALS['get'] = array();
$x_add_form     = substr( $x_add_html, (int) strpos( $x_add_html, 'id="wpcpm-sponsor-add"' ) );
ck( 'a Champion\'s Tier box draws Extra events this term under Term ends and before the funded line: a whole number from 0 to 11, holding 0 while nothing is granted, with its help', array(
	before( $x_card, 'name="wpcpm_tier_until"', 'name="wpcpm_event_allowance"' ),
	before( $x_card, 'name="wpcpm_event_allowance"', 'name="wpcpm_funded_line"' ),
	extra_row( $x_card ),
), array(
	true,
	true,
	'<tr><th scope="row"><label for="wpcpm-edit-event-allowance">Extra events this term</label></th><td><input type="number" id="wpcpm-edit-event-allowance" name="wpcpm_event_allowance" value="0" min="0" max="11" step="1" class="small-text" /><p class="description">' . $extra_help . '</p></td></tr>',
) );
ck( 'a Supporter\'s Tier box has no such row, since its tier includes no co-hosted event, and the Add form, which has no Tier box, has none either', array(
	false !== strpos( extra_card( $X_SUPPORTER ), 'name="wpcpm_tier_until"' ),
	strpos( extra_card( $X_SUPPORTER ), 'wpcpm_event_allowance' ),
	strpos( $x_add_form, 'wpcpm_event_allowance' ),
), array( true, false, false ) );

$audit_before = count( $GLOBALS['audit'] );
$r            = post( array( 'wpcpm_sponsor' => $X_CHAMPION, 'wpcpm_tier' => 'champion', 'wpcpm_tier_until' => '2027-03-31', 'wpcpm_event_allowance' => '2' ), array( $module, 'handle_edit' ) );
$logged       = last_audit();
ck( 'Edit saves the number with the term\'s last day, audited by name, and the box then holds it', array(
	isset( $r[2] ) ? $r[2] : $r,
	allowance_of( $X_CHAMPION ),
	count( $GLOBALS['audit'] ) - $audit_before,
	$logged['data'],
	false !== strpos( extra_row( extra_card( $X_CHAMPION ) ), 'name="wpcpm_event_allowance" value="2" min="0" max="11"' ),
), array( 'sponsor-saved', array( 'until' => '2027-03-31', 'extra' => 2 ), 1, array( 'fields' => array( 'event_allowance' ) ), true ) );

$r = post( array( 'wpcpm_sponsor' => $X_CHAMPION, 'wpcpm_tier_until' => '2027-03-31', 'wpcpm_event_allowance' => '2' ), array( $module, 'handle_edit' ) );
ck( 'the box saved as it was drawn changes nothing', array( isset( $r[2] ) ? $r[2] : $r, allowance_of( $X_CHAMPION ) ), array( 'sponsor-unchanged', array( 'until' => '2027-03-31', 'extra' => 2 ) ) );

$x_refused = array();
foreach ( array( '-1', '2.5', 'two', '12' ) as $x_posted ) {
	$r           = post( array( 'wpcpm_sponsor' => $X_CHAMPION, 'wpcpm_event_allowance' => $x_posted ), array( $module, 'handle_edit' ) );
	$x_refused[] = isset( $r[2] ) ? $r[2] : $r;
}
ck( 'a number below none, one that is not whole, words, and more than eleven refuse the save, and the grant stays', array( $x_refused, allowance_of( $X_CHAMPION ) ), array( array_fill( 0, 4, 'sponsor-rejected' ), array( 'until' => '2027-03-31', 'extra' => 2 ) ) );
ck( 'and the refusal\'s sentence names the box among the values it could not accept, with the highest number the box takes', WPCPM_Sponsors::messages()['sponsor-rejected'], array( 'error', 'Nothing was saved: one of the values could not be accepted. A sponsor needs a name; the website has to be a web address, the contact email an email address, Extra events this term a whole number from 0 to 11, and every choice one this form offers.' ) );

$x_saved = array();
foreach ( array( '11', '', '0' ) as $x_posted ) {
	$r         = post( array( 'wpcpm_sponsor' => $X_CHAMPION, 'wpcpm_event_allowance' => $x_posted ), array( $module, 'handle_edit' ) );
	$x_saved[] = array( isset( $r[2] ) ? $r[2] : $r, allowance_of( $X_CHAMPION ) );
}
ck( 'eleven is taken, a box emptied grants none, and none saved over none changes nothing', $x_saved, array(
	array( 'sponsor-saved', array( 'until' => '2027-03-31', 'extra' => 11 ) ),
	array( 'sponsor-saved', array() ),
	array( 'sponsor-unchanged', array() ),
) );

// A renewal that leaves the box as it was drawn: the number stays with the term it was granted for,
// the box holds none for the renewed term and says what the term before was granted, first while
// that term still runs, then once it has ended.
post( array( 'wpcpm_sponsor' => $X_CHAMPION, 'wpcpm_event_allowance' => '2' ), array( $module, 'handle_edit' ) );
$audit_before = count( $GLOBALS['audit'] );
$r            = post( array( 'wpcpm_sponsor' => $X_CHAMPION, 'wpcpm_tier' => 'champion', 'wpcpm_tier_until' => '2028-03-31', 'wpcpm_event_allowance' => '2' ), array( $module, 'handle_edit' ) );
$logged       = last_audit();
$x_running    = extra_row( extra_card( $X_CHAMPION ) );
$GLOBALS['today'] = '2027-04-02';
$x_ended      = extra_row( extra_card( $X_CHAMPION ) );
$GLOBALS['today'] = '2026-10-06';
ck( 'a renewal with the box untouched leaves the number naming the day it was granted for, and the box holds none for the renewed term and says what the term before was granted, in words for a term still running and for one that has ended', array(
	isset( $r[2] ) ? $r[2] : $r,
	( (array) WPCPM_Sponsors_Index::row( $X_CHAMPION ) )['tier_until'],
	allowance_of( $X_CHAMPION ),
	$logged['data'],
	$x_running,
	false !== strpos( $x_ended, 'value="0" min="0" max="11" step="1" class="small-text" /><p>2 more events were granted for the term that ended on March 31, 2027.</p>' ),
), array(
	'sponsor-saved',
	'2028-03-31',
	array( 'until' => '2027-03-31', 'extra' => 2 ),
	array( 'fields' => array( 'tier_until' ) ),
	'<tr><th scope="row"><label for="wpcpm-edit-event-allowance">Extra events this term</label></th><td><input type="number" id="wpcpm-edit-event-allowance" name="wpcpm_event_allowance" value="0" min="0" max="11" step="1" class="small-text" /><p>2 more events were granted for the term to March 31, 2027.</p><p class="description">' . $extra_help . '</p></td></tr>',
	true,
) );

$GLOBALS['today'] = '2027-03-31';
$x_last_day       = extra_row( extra_card( $X_CHAMPION ) );
$GLOBALS['today'] = '2026-10-06';
ck( 'on the last day of the term the number was granted for, that term still runs, so the box says it was granted for the term to that day, not for a term that has ended', array( false !== strpos( $x_last_day, '<p>2 more events were granted for the term to March 31, 2027.</p>' ), strpos( $x_last_day, 'ended' ) ), array( true, false ) );

$r = post( array( 'wpcpm_sponsor' => $X_CHAMPION, 'wpcpm_event_allowance' => '0' ), array( $module, 'handle_edit' ) );
ck( 'the box saved as drawn, none for the renewed term, still changes nothing: the number stays with its term until the box is changed', array( isset( $r[2] ) ? $r[2] : $r, allowance_of( $X_CHAMPION ) ), array( 'sponsor-unchanged', array( 'until' => '2027-03-31', 'extra' => 2 ) ) );

$r = post( array( 'wpcpm_sponsor' => $X_CHAMPION, 'wpcpm_event_allowance' => '2' ), array( $module, 'handle_edit' ) );
ck( 'the same number set again is the renewed term\'s own, and the words about the term before are gone', array( isset( $r[2] ) ? $r[2] : $r, allowance_of( $X_CHAMPION ), strpos( extra_row( extra_card( $X_CHAMPION ) ), 'granted for the term' ) ), array( 'sponsor-saved', array( 'until' => '2028-03-31', 'extra' => 2 ), false ) );

$r = post( array( 'wpcpm_sponsor' => $X_CHAMPION, 'wpcpm_tier_until' => '2029-03-31', 'wpcpm_event_allowance' => '3' ), array( $module, 'handle_edit' ) );
ck( 'a renewal that changes the number too keeps it with the day the save moves to', array( isset( $r[2] ) ? $r[2] : $r, allowance_of( $X_CHAMPION ), last_audit()['data'] ), array( 'sponsor-saved', array( 'until' => '2029-03-31', 'extra' => 3 ), array( 'fields' => array( 'tier_until', 'event_allowance' ) ) ) );

$r = post( array( 'wpcpm_sponsor' => $X_CHAMPION, 'wpcpm_event_allowance' => '4', 'wpcpm_notes' => 'Four this term, agreed on the call.' ), array( $module, 'handle_edit' ) );
ck( 'a save that changes the number and the notes names both on the trail in the forms\' order, the number before the notes', array( isset( $r[2] ) ? $r[2] : $r, allowance_of( $X_CHAMPION ), last_audit()['data'] ), array( 'sponsor-saved', array( 'until' => '2029-03-31', 'extra' => 4 ), array( 'fields' => array( 'event_allowance', 'notes' ) ) ) );

// A Champion whose term ended on 31 March 2027, with two more events granted for that term, opened
// on 10 April: no start can use that grant now, so the box holds none, says what the ended term was
// granted, and the number a renewal types is the renewed term's, in the one save.
$GLOBALS['today'] = '2027-04-10';
$x_lapsed_row     = extra_row( extra_card( $X_LAPSED ) );
$r                = post( array( 'wpcpm_sponsor' => $X_LAPSED, 'wpcpm_tier' => 'champion', 'wpcpm_tier_until' => '2028-03-31', 'wpcpm_event_allowance' => '2' ), array( $module, 'handle_edit' ) );
$GLOBALS['today'] = '2026-10-06';
ck( 'once the term a number was granted for has ended, the box holds none and says what that term was granted, and a renewal typed with the number grants it to the renewed term in one save', array(
	false !== strpos( $x_lapsed_row, 'name="wpcpm_event_allowance" value="0" min="0" max="11" step="1" class="small-text" /><p>2 more events were granted for the term that ended on March 31, 2027.</p>' ),
	isset( $r[2] ) ? $r[2] : $r,
	allowance_of( $X_LAPSED ),
	last_audit()['data'],
), array( true, 'sponsor-saved', array( 'until' => '2028-03-31', 'extra' => 2 ), array( 'fields' => array( 'tier_until', 'event_allowance' ) ) ) );

// No term, no count: a Champion with no Term ends day is bound by one event at a time alone, so a
// number saved without a day is not kept, and a number saved with the day that save sets is.
$r_termless = post( array( 'wpcpm_sponsor' => $X_TERMLESS, 'wpcpm_event_allowance' => '2' ), array( $module, 'handle_edit' ) );
$termless   = array( isset( $r_termless[2] ) ? $r_termless[2] : $r_termless, allowance_of( $X_TERMLESS ), false !== strpos( extra_row( extra_card( $X_TERMLESS ) ), 'value="0"' ) );
$r_termed   = post( array( 'wpcpm_sponsor' => $X_TERMLESS, 'wpcpm_tier_until' => '2027-06-30', 'wpcpm_event_allowance' => '2' ), array( $module, 'handle_edit' ) );
ck( 'a Champion with no term draws the box too, keeps no number saved without a day, and keeps one saved with the day the same save sets', array( $termless, isset( $r_termed[2] ) ? $r_termed[2] : $r_termed, allowance_of( $X_TERMLESS ) ), array( array( 'sponsor-unchanged', array(), true ), 'sponsor-saved', array( 'until' => '2027-06-30', 'extra' => 2 ) ) );

$x_before = array_keys( WPCPM_Sponsors_Index::rows() );
$r        = post( array( 'wpcpm_name' => 'Extra Added Co', 'wpcpm_event_allowance' => '3' ), array( $module, 'handle_add' ) );
$x_added  = array_values( array_diff( array_keys( WPCPM_Sponsors_Index::rows() ), $x_before ) );
$x_new    = isset( $x_added[0] ) ? (string) $x_added[0] : '';
ck( 'Add takes no number, even when one is posted: a new sponsor has no tier yet', array( isset( $r[2] ) ? $r[2] : $r, allowance_of( $x_new ), last_audit()['data'] ), array( 'sponsor-added', array(), array( 'fields' => array( 'name', 'status' ) ) ) );

foreach ( array( $X_CHAMPION, $X_SUPPORTER, $X_TERMLESS, $X_LAPSED, $x_new ) as $x_key ) {
	wp_delete_post( WPCPM_Sponsors_Index::post_id_of( $x_key ), true );
}
WPCPM_Sponsors_Index::forget();
unset( $GLOBALS['today'] );

echo "\n=== The Tier box's Tracks row (1.114.0) ===\n";
// Three sponsors of their own, not $Q or $U: the row's presence turns on the sponsor's own
// effective tier, and reusing either would tangle this with the tier_requested and lapsed-term
// scenarios those two carry through the rest of the file.
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array(
	'record_id' => 'recSEED0000000031',
	'name'      => 'Track Partner Co',
	'status'    => 'Approved',
	'tier'      => 'partner',
) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array(
	'record_id' => 'recSEED0000000032',
	'name'      => 'Trackless Partner Co',
	'status'    => 'Approved',
	'tier'      => 'partner',
) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array(
	'record_id' => 'recSEED0000000033',
	'name'      => 'Champion No Tracks Co',
	'status'    => 'Approved',
	'tier'      => 'champion',
) ) );
$GLOBALS['partner_tracks'] = array(
	'recSEED0000000031' => array(
		array( 'key' => 'developer', 'label' => 'Developer Track', 'post' => 301 ),
		array( 'key' => 'design', 'label' => 'Design Track', 'post' => 302 ),
	),
	// recSEED0000000032 deliberately has no entry: a Partner named on no track.
);

$html           = render_tab( 'sponsors', array( 'edit' => 'recSEED0000000031' ) );
$two_track_card = substr( $html, (int) strpos( $html, 'id="wpcpm-sponsor-edit"' ) );
ck( 'an effective Partner shows the Tracks row after Sponsored mentors, with a link per track', array(
	before( $two_track_card, 'name="wpcpm_mentors', '>Tracks<' ),
	false !== strpos( $two_track_card, 'Tracks</th><td><a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_track=301">Developer Track</a>, <a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder&wpcpm_track=302">Design Track</a><p class="description">Set on each track&#039;s page on the Track Builder; a partner whose term ends stays named there and stops showing on the site.</p></td></tr>' ),
), array( true, true ) );

$html           = render_tab( 'sponsors', array( 'edit' => 'recSEED0000000032' ) );
$no_track_card  = substr( $html, (int) strpos( $html, 'id="wpcpm-sponsor-edit"' ) );
ck( 'a Partner named on no track shows the row\'s "None yet" sentence and no link', array(
	false !== strpos( $no_track_card, 'Tracks</th><td>None yet. A track names its partners on the Track Builder; they show here once the track is published.<p class="description">' ),
	strpos( $no_track_card, '<a href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-track-builder' ),
), array( true, false ) );

$html           = render_tab( 'sponsors', array( 'edit' => 'recSEED0000000033' ) );
$champion_card  = substr( $html, (int) strpos( $html, 'id="wpcpm-sponsor-edit"' ) );
ck( 'a Champion draws no Tracks row at all', array(
	strpos( $champion_card, '>Tracks<' ),
	strpos( $champion_card, 'None yet. A track names its partners' ),
), array( false, false ) );

$GLOBALS['partner_tracks'] = array();
$GLOBALS['get']            = array();

WPCPM_Sponsors_Index::patch( $U, array( 'tier_requested' => 'backer' ) );
$u_html2        = render_tab( 'sponsors', array( 'edit' => $U ) );
$u_card2        = substr( $u_html2, (int) strpos( $u_html2, 'id="wpcpm-sponsor-edit"' ) );
ck( 'the Edit form names the requested tier when the record holds one', false !== strpos( $u_card2, 'Requested on the application: Backer.' ), true );
// "Not sure yet" is an answer the application's question offers, not a tier: the note prints it
// in the question's own words (1.113.0), where the ladder's label() would read it as its floor,
// and a value the question never offered is no request at all rather than that floor.
WPCPM_Sponsors_Index::patch( $U, array( 'tier_requested' => 'unsure' ) );
$u_html3        = render_tab( 'sponsors', array( 'edit' => $U ) );
$u_card3        = substr( $u_html3, (int) strpos( $u_html3, 'id="wpcpm-sponsor-edit"' ) );
WPCPM_Sponsors_Index::patch( $U, array( 'tier_requested' => 'gold' ) );
$u_html4        = render_tab( 'sponsors', array( 'edit' => $U ) );
$u_card4        = substr( $u_html4, (int) strpos( $u_html4, 'id="wpcpm-sponsor-edit"' ) );
WPCPM_Sponsors_Index::patch( $U, array( 'tier_requested' => 'backer' ) );
ck( 'and says not sure yet the way the application offered it, and nothing for a value it never offered', array(
	false !== strpos( $u_card3, 'Requested on the application: Not sure yet.' ),
	strpos( $u_card3, 'Requested on the application: Friend.' ),
	strpos( $u_card4, 'Requested on the application:' ),
), array( true, false, false ) );

WPCPM_Sponsors_Index::patch( $Q, array( 'tier' => 'supporter', 'tier_until' => '2099-12-31' ) );
WPCPM_Sponsors_Index::patch( $U, array( 'tier' => 'backer', 'tier_until' => '2026-01-31' ) );
$index_html     = render_tab( 'sponsors' );
$table          = substr( $index_html, (int) strpos( $index_html, '<table class="wpcpm-table' ) );
$future_day     = WPCPM_Sponsor_Tiers::format_day( '2099-12-31' );
$past_day       = WPCPM_Sponsor_Tiers::format_day( '2026-01-31' );
ck( 'the index lists each sponsor\'s tier with its term, and a lapsed term as a Friend', array(
	before( $table, '>Status</th>', '>Tier</th>' ),
	false !== strpos( $table, 'Supporter until ' . $future_day ),
	false !== strpos( $table, 'Friend (Backer ended ' . $past_day . ')' ),
), array( true, true, true ) );
$GLOBALS['get'] = array();

$audit_before = count( $GLOBALS['audit'] );
$r            = post( $edit, array( $module, 'handle_edit' ) );
ck( 'the same form sent again changes nothing, says so, and logs nothing', array( isset( $r[2] ) ? $r[2] : $r, count( $GLOBALS['audit'] ) - $audit_before ), array( 'sponsor-unchanged', 0 ) );

// The sponsor's category, as the approval makes one: a child of Sponsors named after the company,
// recorded for its key. The name follows the sponsor's on the Edit form, the one place a sponsor
// is renamed since 1.111.0, through the real WPCPM_Sponsor_Posts; the slug stays, so a link to the
// category keeps working. A save that leaves the name alone touches no term.
$GLOBALS['terms'][7000] = array( 'name' => 'Sponsors', 'slug' => 'sponsors', 'parent' => 0 );
$GLOBALS['terms'][7001] = array( 'name' => 'Quill Example', 'slug' => 'quill-example', 'parent' => 7000 );
update_option( WPCPM_Sponsor_Posts::OPT_TERM_PREFIX . $Q, 7001 );
$GLOBALS['term_renames'] = array();
$r_kept                  = post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_name' => 'Quill Example', 'wpcpm_notes' => 'The name stays.' ), array( $module, 'handle_edit' ) );
$renames_kept            = $GLOBALS['term_renames'];
$r_renamed               = post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_name' => 'Quill Example Studio' ), array( $module, 'handle_edit' ) );
ck( 'a renamed sponsor\'s category follows the name',
	array(
		isset( $r_kept[2] ) ? $r_kept[2] : $r_kept,
		$renames_kept,
		isset( $r_renamed[2] ) ? $r_renamed[2] : $r_renamed,
		$GLOBALS['terms'][7001],
		$GLOBALS['term_renames'],
	),
	array(
		'sponsor-saved',
		array(),
		'sponsor-saved',
		array( 'name' => 'Quill Example Studio', 'slug' => 'quill-example', 'parent' => 7000 ),
		array( array( 7001, 'category', array( 'name' => 'Quill Example Studio' ) ) ),
	)
);
// Back to the name the sections below were written against.
post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_name' => 'Quill Example' ), array( $module, 'handle_edit' ) );
$GLOBALS['term_renames'] = array();

WPCPM_Sponsors_Index::forget();
$before_row   = WPCPM_Sponsors_Index::row( $Q );
$audit_before = count( $GLOBALS['audit'] );
$outcomes     = array();
foreach ( array(
	array( 'wpcpm_status' => 'Maybe later' ),
	array( 'wpcpm_contact_email' => 'not an address' ),
	array( 'wpcpm_manager' => '9' ),
	array( 'wpcpm_manager' => '4242' ),
	array( 'wpcpm_option' => 'Sponsor everything' ),
	array( 'wpcpm_product_type' => 'Hardware' ),
	array( 'wpcpm_website' => 'https://' ),
	array( 'wpcpm_name' => '' ),
	array( 'wpcpm_contact_person' => str_repeat( 'p', 201 ) ),
	array( 'wpcpm_notes' => str_repeat( 'n', 4001 ) ),
) as $bad ) {
	// Each beside a change that is fine on its own, which must not land either.
	$r          = post( array_merge( array( 'wpcpm_sponsor' => $Q, 'wpcpm_notes' => 'This change must not land.' ), $bad ), array( $module, 'handle_edit' ) );
	$outcomes[] = isset( $r[2] ) ? $r[2] : $r;
}
WPCPM_Sponsors_Index::forget();
ck( 'an unknown status, a bad address or an unknown manager rejects the whole save, as does a contact person or notes too long, each named', array( $outcomes, WPCPM_Sponsors_Index::row( $Q ) === $before_row, count( $GLOBALS['audit'] ) - $audit_before ), array( array_merge( array_fill( 0, 8, 'sponsor-rejected' ), array( 'contact-too-long', 'notes-too-long' ) ), true, 0 ) );
ck( 'and each rejection lands back on the sponsor\'s edit form', $r[1], 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=sponsors&edit=' . $Q );

$r = post( array( 'wpcpm_sponsor' => 'spoZZZZ0000000009', 'wpcpm_name' => 'Nobody Example' ), array( $module, 'handle_edit' ) );
ck( 'a key the store does not hold is refused, and becomes no sponsor', array( $r, WPCPM_Sponsors_Index::has( 'spoZZZZ0000000009' ) ), array( array( 'redirect', 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors', 'refused' ), false ) );

// Zoe is in the index; recSEED0000000099 never was. Adam, linked above, is not ticked.
post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_mentors' => array( '', 'recSEED0000000011', 'recSEED0000000099' ) ), array( $module, 'handle_edit' ) );
$mentors_after = ( (array) WPCPM_Sponsors_Index::row( $Q ) + WPCPM_Sponsors_Index::empty_row() )['mentors'];
ck( 'mentors are limited to the sponsorship index: a ticked mentor it holds is linked, one it never held is not, an unticked one goes', $mentors_after, array( 'recSEED0000000011' ) );
// A mentor linked before this release who is no longer in the index (the index holds Active
// mentors alone): the picker still draws the link, and a save that leaves it ticked keeps it.
WPCPM_Sponsors_Index::patch( $Q, array( 'mentors' => array( 'recSEED0000000011', 'recSEED0000000088' ) ) );
$html           = render_tab( 'sponsors', array( 'edit' => $Q ) );
$GLOBALS['get'] = array();
post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_mentors' => array( '', 'recSEED0000000011', 'recSEED0000000088' ) ), array( $module, 'handle_edit' ) );
$kept = ( (array) WPCPM_Sponsors_Index::row( $Q ) + WPCPM_Sponsors_Index::empty_row() )['mentors'];
post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_mentors' => array( '', 'recSEED0000000011' ) ), array( $module, 'handle_edit' ) );
$gone = ( (array) WPCPM_Sponsors_Index::row( $Q ) + WPCPM_Sponsors_Index::empty_row() )['mentors'];
ck( 'a linked mentor the index no longer holds is drawn ticked, kept while it stays ticked, and gone once unticked', array( false !== strpos( $html, 'value="recSEED0000000088" checked' ), $kept, $gone ), array( true, array( 'recSEED0000000011', 'recSEED0000000088' ), array( 'recSEED0000000011' ) ) );
// A save that does not draw the picker at all (no empty value first) leaves the links alone.
post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_notes' => 'Only the notes.' ), array( $module, 'handle_edit' ) );
ck( 'and a form that does not carry the picker leaves the mentors and the support alone', array_intersect_key( (array) WPCPM_Sponsors_Index::row( $Q ), array_flip( array( 'support', 'mentors', 'notes' ) ) ), array( 'support' => array( 'Sponsor tools or services' ), 'mentors' => array( 'recSEED0000000011' ), 'notes' => 'Only the notes.' ) );

// Two sponsors that came over with a blank status (design section 2): the select says so, and a
// save that leaves it untouched keeps it rather than refusing or choosing Approved for them.
WPCPM_Sponsors_Index::patch( $Q, array( 'status' => '' ) );
$html           = render_tab( 'sponsors', array( 'edit' => $Q ) );
$GLOBALS['get'] = array();
$r              = post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_status' => '', 'wpcpm_notes' => 'Blank status, untouched.' ), array( $module, 'handle_edit' ) );
ck( 'a value already on the record passes as itself: a blank status is drawn as one and saved untouched', array( false !== strpos( $html, '<option value="" selected>' ), isset( $r[2] ) ? $r[2] : $r, ( (array) WPCPM_Sponsors_Index::row( $Q ) + WPCPM_Sponsors_Index::empty_row() )['status'] ), array( true, 'sponsor-saved', '' ) );
WPCPM_Sponsors_Index::patch( $Q, array( 'status' => 'In review' ) );
// A manager who has since lost the program capability (the Stranger, user 9): still assigned,
// drawn as such, and kept by a save that does not touch the select.
WPCPM_Sponsors_Index::patch( $Q, array( 'manager' => '9' ) );
$html           = render_tab( 'sponsors', array( 'edit' => $Q ) );
$GLOBALS['get'] = array();
$r              = post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_manager' => '9', 'wpcpm_notes' => 'Manager untouched.' ), array( $module, 'handle_edit' ) );
ck( 'and so does a manager who has lost the program capability, drawn as one', array( false !== strpos( $html, '<option value="9" selected>Stranger (not an Administrator now)</option>' ), isset( $r[2] ) ? $r[2] : $r, ( (array) WPCPM_Sponsors_Index::row( $Q ) + WPCPM_Sponsors_Index::empty_row() )['manager'] ), array( true, 'sponsor-saved', '9' ) );
// The index names that manager the Edit form's way, not with an empty cell that reads as never
// assigned: the assignment is waiting to be replaced.
$index_html     = render_tab( 'sponsors' );
$GLOBALS['get'] = array();
ck( 'and the index\'s Program contact column names them in the same words', substr_count( $index_html, '<td>Stranger (not an Administrator now)</td>' ), 1 );
WPCPM_Sponsors_Index::patch( $Q, array( 'manager' => '2' ) );
// No mentor index at all, and nothing linked: nothing to choose, so no picker and no list posted.
$saved_sponsorship      = $GLOBALS['sponsorship'];
$saved_mentors          = ( (array) WPCPM_Sponsors_Index::row( $Q ) + WPCPM_Sponsors_Index::empty_row() )['mentors'];
$GLOBALS['sponsorship'] = array();
WPCPM_Sponsors_Index::patch( $Q, array( 'mentors' => array() ) );
$html           = render_tab( 'sponsors', array( 'edit' => $Q ) );
$GLOBALS['get'] = array();
ck( 'with an empty mentors index and no linked mentor the form says why there is nothing to choose, and draws no list', array( false !== strpos( $html, 'No mentor can be chosen until the mentors index holds one. It is filled on the Mentors screen.' ), strpos( $html, 'wpcpm_mentors[]' ) ), array( true, false ) );
$GLOBALS['sponsorship'] = $saved_sponsorship;
WPCPM_Sponsors_Index::patch( $Q, array( 'mentors' => $saved_mentors ) );

echo "\n=== The status rule: when a sponsor is no longer Approved (1.111.0) ===\n";
// Two accounts act for Quill, which is Approved with its account flag set.
foreach ( array( 31, 32 ) as $rep ) {
	$GLOBALS['users'][ $rep ] = new WP_User( $rep, array( 'subscriber' ), 'Quill Rep ' . $rep, 'rep' . $rep . '@quill.example' );
}
WPCPM_Sponsors_Index::patch( $Q, array( 'status' => 'Approved', 'dashboard_account' => true ) );
WPCPM_Sponsor_Members::attach( 31, $Q, WPCPM_Sponsor_Members::HOW_MANAGER, 1 );
WPCPM_Sponsor_Members::attach( 32, $Q, WPCPM_Sponsor_Members::HOW_MANAGER, 1 );
$members = static function () use ( $Q ) {
	return array_map( static function ( $user ) { return (int) $user->ID; }, WPCPM_Sponsor_Members::members_of( $Q ) );
};
$flag = static function () use ( $Q ) {
	WPCPM_Sponsors_Index::forget();
	return ( (array) WPCPM_Sponsors_Index::row( $Q ) + WPCPM_Sponsors_Index::empty_row() )['dashboard_account'];
};

$GLOBALS['settings']['sponsor_on_inactive'] = 'keep';
$r = post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_status' => 'Paused' ), array( $module, 'handle_edit' ) );
ck( 'with the setting at keep, a status set away from Approved keeps the accounts and says saved', array( isset( $r[2] ) ? $r[2] : $r, $members(), $flag() ), array( 'sponsor-saved', array( 31, 32 ), true ) );
post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_status' => 'Approved' ), array( $module, 'handle_edit' ) );

$GLOBALS['settings']['sponsor_on_inactive'] = 'revoke';
$audit_before                               = count( $GLOBALS['audit'] );
$r                                          = post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_status' => 'Not Moving Forward' ), array( $module, 'handle_edit' ) );
$removed                                    = array();
foreach ( array_slice( $GLOBALS['audit'], $audit_before ) as $entry ) {
	if ( 'member_removed' === $entry['kind'] ) {
		$removed[] = array( $entry['subject'], $entry['data']['reason'], $entry['actor'] );
	}
}
ck( 'a status set away from Approved detaches the accounts when the setting says revoke', array( isset( $r[2] ) ? $r[2] : $r, $members(), $flag(), $removed ), array( 'sponsor-detached', array(), false, array( array( '31', 'revoked', 1 ), array( '32', 'revoked', 1 ) ) ) );
ck( 'and the notice says so in the plan\'s words', WPCPM_Sponsors::messages()['sponsor-detached'] ?? null, array( 'warning', 'Saved. The sponsor is no longer Approved, so its accounts were detached, as the setting says.' ) );
WPCPM_Sponsor_Members::attach( 31, $Q, WPCPM_Sponsor_Members::HOW_MANAGER, 1 );
$r = post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_status' => 'Paused' ), array( $module, 'handle_edit' ) );
ck( 'while a status that was not Approved changes without detaching anybody, whatever the setting', array( isset( $r[2] ) ? $r[2] : $r, $members() ), array( 'sponsor-saved', array( 31 ) ) );
WPCPM_Sponsor_Members::detach( 31, WPCPM_Sponsor_Members::REASON_REMOVED, 1 );
// Approved again with nobody attached and the flag set by hand: revoke finds nobody to detach.
post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_status' => 'Approved' ), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::patch( $Q, array( 'dashboard_account' => true ) );
$r = post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_status' => 'Paused' ), array( $module, 'handle_edit' ) );
ck( 'with nobody attached there is nobody to detach: the notice says saved, and the account flag is cleared all the same', array( isset( $r[2] ) ? $r[2] : $r, $flag() ), array( 'sponsor-saved', false ) );
$GLOBALS['settings']['sponsor_on_inactive'] = 'keep';

echo "\n=== The migration box (1.111.0) ===\n";
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, array( 'complete' => false, 'rows' => 31, 'posts' => 30, 'seeded' => 0, 'unseeded' => array( 'Held Offer Example' ), 'unmapped' => array( 'Unmapped Example', 'Second Unmapped Example' ), 'at' => time(), 'runs' => 2 ) );
$html = render_tab( 'sponsors' );
$box  = substr( $html, (int) strpos( $html, 'id="wpcpm-sponsors-migration"' ) );
$box  = substr( $box, 0, (int) strpos( $box, '</form>' ) + 7 );
ck( 'the migration box shows while the migration is incomplete', array(
	false !== strpos( $box, '<h2>Moving sponsors onto the site</h2>' ),
	false !== strpos( $box, '30 of 31 rows are posts.' ),
	false !== strpos( $box, 'Unmapped Example, Second Unmapped Example' ),
	false !== strpos( $box, 'Held Offer Example' ),
	false !== strpos( $box, '<input type="hidden" name="action" value="wpcpm_sponsors_migrate" />' ) && false !== strpos( $box, 'nonce-wpcpm_sponsors_migrate' ) && false !== strpos( $box, 'Run again' ),
	before( $html, 'id="wpcpm-sponsors-migration"', '<h2>Sponsors <span class="wpcpm-count">' ),
), array_fill( 0, 6, true ) );
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, array_merge( get_option( WPCPM_Sponsors_Migration::OPT_REPORT ), array( 'complete' => true, 'posts' => 31, 'unseeded' => array() ) ) );
ck( 'and gone once it is complete', strpos( render_tab( 'sponsors' ), 'Moving sponsors onto the site' ), false );
// Before the first run there are no counts to give: the report is its empty shape.
delete_option( WPCPM_Sponsors_Migration::OPT_REPORT );
$html = render_tab( 'sponsors' );
ck( 'before the move has run at all the box says so rather than counting nothing', array( false !== strpos( $html, 'The move has not run yet.' ), strpos( $html, 'rows are posts' ), false !== strpos( $html, 'Run again' ) ), array( true, false, true ) );

// A held index with one row the store does not hold yet: Run again moves it.
update_option( WPCPM_Sponsors_Index::OPT_NAME, array( 'v' => 1, 'read' => time(), 'rows' => array( 'recSEED0000000021' => array( 'name' => 'Held Example', 'status' => 'Paused' ) ) ) );
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, array( 'complete' => false, 'rows' => 1, 'posts' => 0, 'seeded' => 0, 'unseeded' => array(), 'unmapped' => array(), 'at' => time(), 'runs' => 1 ) );
$GLOBALS['nonce_checked'] = array();
$r                        = post( array(), array( $module, 'handle_migrate' ) );
$report                   = WPCPM_Sponsors_Migration::report();
ck( 'Run again runs the move and comes back to the screen with the migrated notice', array( $r, end( $GLOBALS['nonce_checked'] ), WPCPM_Sponsors_Index::has( 'recSEED0000000021' ), array( $report['runs'], $report['rows'], $report['posts'], $report['complete'] ) ), array( array( 'redirect', 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors', 'migrated' ), 'wpcpm_sponsors_migrate', true, array( 2, 1, 1, true ) ) );
// Drawn on the Sponsors tab, where the box's own form comes back to.
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'migrated' );
$html           = render_tab( 'sponsors' );
$GLOBALS['get'] = array();
// Singular: this run's total is the one row, so the sentence reads "row is", not "rows are".
ck( 'whose sentence says the counts', false !== strpos( $html, '1 of 1 row is a post.' ) && false !== strpos( $html, 'notice-' ), true );

// Run again goes through the migration's lock like the run on
// init does, so a press while another run holds it moves nothing and says so, rather than
// claiming a run that did not happen.
update_option( WPCPM_Sponsors_Index::OPT_NAME, array( 'v' => 1, 'read' => time(), 'rows' => array( 'recSEED0000000022' => array( 'name' => 'Second Held Example', 'status' => 'Paused' ) ) ) );
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, array( 'complete' => false, 'rows' => 1, 'posts' => 0, 'seeded' => 0, 'unseeded' => array(), 'unmapped' => array(), 'at' => time(), 'runs' => 2 ) );
update_option( 'wpcpm_sponsors_migrate_lock', time() );
$r = post( array(), array( $module, 'handle_migrate' ) );
ck( 'Run again while another run holds the lock moves nothing and says the move is already running', array( $r, WPCPM_Sponsors_Index::has( 'recSEED0000000022' ), WPCPM_Sponsors_Migration::report()['runs'], WPCPM_Sponsors::messages()['migrate-busy'] ?? null ), array( array( 'redirect', 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors', 'migrate-busy' ), false, 2, array( 'info', 'The move onto the site is already running. Give it a minute, then look at this box again.' ) ) );
delete_option( 'wpcpm_sponsors_migrate_lock' );
delete_option( WPCPM_Sponsors_Index::OPT_NAME );
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, array( 'complete' => true, 'rows' => 1, 'posts' => 1, 'seeded' => 0, 'unseeded' => array(), 'unmapped' => array(), 'at' => time(), 'runs' => 2 ) );

echo "\n=== Add, Edit and Run again refuse a stranger and a stale form (1.111.0) ===\n";
$rows_before  = WPCPM_Sponsors_Index::rows();
$audit_before = count( $GLOBALS['audit'] );
$runs_before  = WPCPM_Sponsors_Migration::report()['runs'];
$GLOBALS['uid'] = 9;
$refusals       = array(
	post( array( 'wpcpm_name' => 'Stranger Example' ), array( $module, 'handle_add' ) ),
	post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_notes' => 'Stranger.' ), array( $module, 'handle_edit' ) ),
	post( array(), array( $module, 'handle_migrate' ) ),
);
$GLOBALS['uid']      = 1;
$GLOBALS['nonce_ok'] = false;
$stale               = array(
	post( array( 'wpcpm_name' => 'Stale Example' ), array( $module, 'handle_add' ) ),
	post( array( 'wpcpm_sponsor' => $Q, 'wpcpm_notes' => 'Stale.' ), array( $module, 'handle_edit' ) ),
	post( array(), array( $module, 'handle_migrate' ) ),
);
$GLOBALS['nonce_ok'] = true;
WPCPM_Sponsors_Index::forget();
ck( 'a non-manager dies with 403 on each, and a bad nonce dies, and nothing is written or run', array(
	array_map( static function ( $r ) { return 'die' === $r[0] && false !== strpos( $r[1], '[403]' ); }, $refusals ),
	array_map( static function ( $r ) { return $r[0]; }, $stale ),
	WPCPM_Sponsors_Index::rows() === $rows_before,
	count( $GLOBALS['audit'] ) - $audit_before,
	WPCPM_Sponsors_Migration::report()['runs'],
), array( array( true, true, true ), array( 'die', 'die', 'die' ), true, 0, $runs_before ) );
$src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'each checks the capability and then the nonce before it reads or writes a sponsor', array(
	before( method_body( $src, 'handle_add' ), '$this->verify( self::ACTION_ADD );', 'self::clean_fields(' ),
	before( method_body( $src, 'handle_edit' ), '$this->verify( self::ACTION_EDIT . \'_\' . $key );', 'WPCPM_Sponsors_Index::row(' ),
	before( method_body( $src, 'handle_migrate' ), '$this->verify( self::ACTION_MIGRATE );', 'WPCPM_Sponsors_Migration::run();' ),
), array( true, true, true ) );

echo "\n=== The tier ladder's daily run: the grace preview and the schedule (1.112.0) ===\n";
// Two Approved sponsors with live offers, written straight into the offers' post store, and one
// published post each: a Supporter with two offers, and a sponsor with no tier recorded, a
// Friend, with one. No tier caps live offers since 1.123.18, when the partner page offered
// products at any level, so no offer goes dormant whatever the tier; neither tier includes a post,
// so both posts do. No other sponsor holds an offer by now (the uninstall above took them all).
// This file's get_posts() matches a list of statuses and the bare meta_key/meta_value pair
// posts_of() asks for, so the real WPCPM_Sponsor_Posts hands the plan a sponsor's posts. Today is
// pinned, so the preview is read against the default grace date, 2026-12-31, whatever day the
// suite runs on.
$tier_supporter = 'spoTTTT0000000031';
$tier_mentor    = 'spoUUUU0000000032';
WPCPM_Sponsors_Index::write( array(
	$tier_supporter => array( 'name' => 'Supporter Screen Example', 'status' => 'Approved', 'tier' => 'supporter' ),
	$tier_mentor    => array( 'name' => 'Mentor Screen Example', 'status' => 'Approved' ),
), time() );
$tier_offers = array();
foreach ( array( $tier_supporter, $tier_supporter, $tier_mentor ) as $n => $record ) {
	$tier_offer = wp_insert_post( array( 'post_type' => WPCPM_Sponsor_Offers::POST_TYPE, 'post_status' => 'private', 'post_title' => 'Tier offer ' . $n ) );
	update_post_meta( $tier_offer, WPCPM_Sponsor_Offers::META_SPONSOR, $record );
	update_post_meta( $tier_offer, WPCPM_Sponsor_Offers::META_STATE, WPCPM_Sponsor_Offers::STATE_LIVE );
	$tier_offers[] = $tier_offer;
}
$tier_posts = array();
foreach ( array( $tier_supporter, $tier_mentor ) as $n => $record ) {
	$tier_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Tier screen post ' . $n ) );
	update_post_meta( $tier_post, WPCPM_Sponsor_Policy::META_POST_SPONSOR, $record );
	$tier_posts[] = $tier_post;
}

/** The grace preview's paragraph as a page draws it, or '' when it is not drawn. */
function grace_preview( $html ) {
	$at = strpos( $html, '<p class="description">Under the tiers as set' );
	return false === $at ? '' : substr( $html, $at, (int) strpos( $html, '</p>', $at ) + 4 - $at );
}

$GLOBALS['today'] = '2026-10-06';
$html             = render_tab( 'sponsors' );
$drawn            = grace_preview( $html );
ck( 'the screen says what the tiers as set would put to dormant on the day after the grace date, and that the switch is off',
	array( $drawn, '' !== $drawn && false !== strpos( $html, $drawn . '<table class="wpcpm-table widefat striped"><thead><tr><th scope="col">Sponsor</th>' ) ),
	array( '<p class="description">Under the tiers as set, on January 1, 2027, the daily run puts 2 published posts to dormant: Supporter Screen Example (1 post); Mentor Screen Example (1 post). Sponsor tiers is off in Settings: nothing goes dormant until it is on.</p>', true )
);
ck( 'and names no offer: no tier caps live offers', strpos( $drawn, 'offer' ), false );

$GLOBALS['settings']['sponsor_tiers_live'] = 1;
$on_html = render_tab( 'sponsors' );
foreach ( $tier_posts as $tier_post ) {
	wp_delete_post( $tier_post, true );
}
$none_html        = render_tab( 'sponsors' );
$GLOBALS['today'] = '2027-01-01';
$after_html       = render_tab( 'sponsors' );
unset( $GLOBALS['settings']['sponsor_tiers_live'], $GLOBALS['today'] );
ck( 'with the switch on the Settings sentence goes, with nothing beyond a cap the preview says so, and past the grace date it is not drawn',
	array( grace_preview( $on_html ), grace_preview( $none_html ), grace_preview( $after_html ) ),
	array(
		'<p class="description">Under the tiers as set, on January 1, 2027, the daily run puts 2 published posts to dormant: Supporter Screen Example (1 post); Mentor Screen Example (1 post).</p>',
		'<p class="description">Under the tiers as set, nothing goes dormant on January 1, 2027.</p>',
		'',
	)
);

// A Backer, whose tier includes one post, has two published and no offer, so the newer post goes
// dormant; the other two sponsors' offers are still live, and nothing names an offer.
$tier_backer = 'spoVVVV0000000033';
WPCPM_Sponsors_Index::write( array( $tier_backer => array( 'name' => 'Backer Screen Example', 'status' => 'Approved', 'tier' => 'backer' ) ), time() );
foreach ( array( '2026-09-01 09:30:00', '2026-09-15 09:30:00' ) as $n => $went_live ) {
	$backer_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Backer screen post ' . $n ) );
	$GLOBALS['posts'][ $backer_post ]->post_date = $went_live;
	update_post_meta( $backer_post, WPCPM_Sponsor_Policy::META_POST_SPONSOR, $tier_backer );
}
$GLOBALS['today'] = '2026-10-06';
$posts_html       = render_tab( 'sponsors' );
$GLOBALS['get']   = array();
unset( $GLOBALS['today'] );
foreach ( $tier_offers as $tier_offer ) {
	update_post_meta( $tier_offer, WPCPM_Sponsor_Offers::META_STATE, WPCPM_Sponsor_Offers::STATE_ENDED );
}
ck( 'the preview names posts alone when only posts go dormant, the live offers beside them never',
	array( grace_preview( $posts_html ), strpos( grace_preview( $posts_html ), 'offer' ) ),
	array( '<p class="description">Under the tiers as set, on January 1, 2027, the daily run puts 1 published post to dormant: Backer Screen Example (1 post). Sponsor tiers is off in Settings: nothing goes dormant until it is on.</p>', false )
);
// No tier caps live offers, so the daily run puts none to dormant and the preview has no offers to
// count: its sentence is built from posts alone (1.123.18).
ck( 'the preview\'s sentence counts posts alone: no offer phrase is left in it to print', array( strpos( method_body( (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' ), 'render_grace_preview' ), 'offer' ) ), array( false ) );

// The same three steps the retention runs above are held to: on the clock ten hours out, an hour
// clear of the application purge; off it on deactivation; on it again; off it on uninstall.
$GLOBALS['cron'] = array();
WPCPM_Sponsors::schedule_cron();
$tier_run_in = isset( $GLOBALS['cron']['wpcpm_sponsor_tiers_daily'] ) ? $GLOBALS['cron']['wpcpm_sponsor_tiers_daily'] - time() : 0;
$module->deactivate();
$tier_run_deactivated = isset( $GLOBALS['cron']['wpcpm_sponsor_tiers_daily'] );
WPCPM_Sponsors::schedule_cron();
$tier_run_again = isset( $GLOBALS['cron']['wpcpm_sponsor_tiers_daily'] );
$module->uninstall();
$tier_run_uninstalled = isset( $GLOBALS['cron']['wpcpm_sponsor_tiers_daily'] );
$sponsors_src         = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'boot() schedules the run daily and deactivate() and uninstall() clear it', array(
	1 === preg_match( "/foreach \( array\([^)]*'WPCPM_Sponsor_Tiers_Run'[^)]*\) as \\\$front \)/", method_body( $sponsors_src, 'boot' ) ),
	$tier_run_in > 9 * HOUR_IN_SECONDS && $tier_run_in <= 10 * HOUR_IN_SECONDS,
	false !== strpos( method_body( $sponsors_src, 'schedule_cron' ), "'daily', WPCPM_Sponsor_Tiers_Run::CRON_HOOK" ),
	$tier_run_deactivated,
	$tier_run_again,
	$tier_run_uninstalled,
), array( true, true, true, false, true, false ) );

echo "\n=== Activation ===\n";
// activate() as the activation request runs it, over a site where the move onto the site is
// complete and the application page exists, so the migration and the page are left as they are.
// The request flushes the rules once every module has activated, and flushes them before this
// plugin's init has ever run in it, so the badge's rule is not in memory to be written: the
// version it was last flushed for has to go, or the next request would not write them again.
update_option( WPCPM_Sponsors_Migration::OPT_REPORT, array( 'complete' => true, 'rows' => 0, 'posts' => 0, 'seeded' => 0, 'unseeded' => array(), 'unmapped' => array(), 'at' => time(), 'runs' => 1 ) );
$GLOBALS['opts'][ WPCPM_Sponsor_Application::OPT_PAGE ] = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Sponsor the WordPress Credits Program', 'post_name' => WPCPM_Sponsor_Application::SLUG ) );
$GLOBALS['opts'][ WPCPM_Sponsor_Badge::OPT_REWRITE ]    = WPCPM_Sponsor_Badge::REWRITE_VERSION;
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$module->activate();
ck( 'activate() drops the version the badge\'s rule was flushed for, so the next request writes the rules with it', array_key_exists( WPCPM_Sponsor_Badge::OPT_REWRITE, $GLOBALS['opts'] ), false );
// The Partners page, made at activation where there is none, lands where the launch switch says:
// a draft while it is off, and published while it is on, so a site activated after the launch
// does not wait for the switch to be flipped again to show it. The second activation runs over a
// site where the first page and its option are gone, so it makes a page of its own.
$off_page   = WPCPM_Sponsors_Page::page_id();
$off_status = get_post_status( $off_page );
unset( $GLOBALS['posts'][ $off_page ], $GLOBALS['opts'][ WPCPM_Sponsors_Page::OPT_PAGE ] );
$GLOBALS['settings']['sponsor_tiers_live'] = 1;
$module->activate();
$on_page   = WPCPM_Sponsors_Page::page_id();
$on_status = get_post_status( $on_page );
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
ck( 'activate() makes the Partners page where there is none and puts it where the switch says: a draft while it is off, published while it is on', array( $off_page > 0, $off_status, $on_page > 0 && $on_page !== $off_page, $on_status ), array( true, 'draft', true, 'publish' ) );

/**
 * The sponsor event store, for its drain and its reminders' retry: deactivation takes both events
 * off the clock with the module's other jobs, and activation asks the store to put the drain back
 * while rows wait for it; the next daily run covers what the retry would have. Stood in from here
 * on, declared inside a function so every activate(), deactivate() and uninstall() above ran
 * without it, as a checkout without the store does; the store's own resume_drain() and retry are
 * pinned in bin/test-sponsor-events.php. Its state key too, the store's own, whose change forgets
 * the menu bubble's count once the store is loaded.
 */
function load_sponsor_event_stub() {
	if ( class_exists( 'WPCPM_Sponsor_Event' ) ) {
		return;
	}

	class WPCPM_Sponsor_Event {
		const CRON_DRAIN = 'wpcpm_sevent_drain';
		const CRON_RETRY = 'wpcpm_sevent_remind_retry';
		const META_STATE = '_wpcpm_sevent_state';
		public static function resume_drain() { ++$GLOBALS['drain_resumed']; }
	}
}

load_sponsor_event_stub();
$GLOBALS['cron']          = array( WPCPM_Sponsor_Event::CRON_DRAIN => time() + 60, WPCPM_Sponsor_Event::CRON_RETRY => time() + 300 );
$GLOBALS['drain_resumed'] = 0;
$module->deactivate();
$drain_after_deactivate = isset( $GLOBALS['cron'][ WPCPM_Sponsor_Event::CRON_DRAIN ] );
$retry_after_deactivate = isset( $GLOBALS['cron'][ WPCPM_Sponsor_Event::CRON_RETRY ] );
$module->activate();
ck( 'deactivate() takes the sponsor events\' drain off the clock with the module\'s other jobs, and activate() asks the store, once, to put it back while rows wait for it', array( $drain_after_deactivate, $GLOBALS['drain_resumed'] ), array( false, 1 ) );
ck( 'and deactivate() takes the reminders\' retry off the clock too: the next daily run starts its window where the last complete one stopped', $retry_after_deactivate, false );
// With the store loaded, an event planned, approved, returned or canceled writes its state key, and
// that change forgets the bubble's count, as an application's or an agreement's does; a change to
// another of the event's keys, the people on it, keeps it.
$GLOBALS['transients'] = array();
WPCPM_Sponsors::attention_count();
$bubble_primed = isset( $GLOBALS['transients']['wpcpm_sponsors_attention'] );
WPCPM_Sponsors::forget_attention_on_meta( 0, 1, '_wpcpm_sevent_state' );
$bubble_after_state = isset( $GLOBALS['transients']['wpcpm_sponsors_attention'] );
WPCPM_Sponsors::attention_count();
WPCPM_Sponsors::forget_attention_on_meta( 0, 1, '_wpcpm_sevent_person' );
$bubble_after_person = isset( $GLOBALS['transients']['wpcpm_sponsors_attention'] );
ck( 'an event\'s state changing forgets the bubble\'s count, and a change to another of its keys keeps it', array( $bubble_primed, $bubble_after_state, $bubble_after_person ), array( true, false, true ) );

echo "\n=== The handbook box (1.113.0) ===\n";

// A sponsor of its own, Approved with a website, so what the box has to list does not depend on
// what an earlier section above left Approved or not.
$handbook_sponsor = 'spoWWWW0000000041';
WPCPM_Sponsors_Index::write( array(
	$handbook_sponsor => array( 'name' => 'Handbook Screen Example', 'status' => 'Approved', 'website' => 'https://handbook.example/' ),
), time() );

// The box is the Handbook listing tab's one card, and the tab is in the bar only while the launch
// switch is on. While it is off, as on a site before the launch, the tab's address opens the first
// tab, the queue, as the address of a tab the screen does not have does: the page is the screen's
// own address, tag for tag, since the queue tab here draws no form whose field would carry the
// address it was drawn at.
$off_html = render_tab( 'listing' );
$off_bare = render_screen();
ck( 'the box is not drawn while the switch is off', strpos( $off_html, 'id="wpcpm-sponsors-listing-box"' ), false );
ck( 'while the switch is off the Handbook listing tab is not in the bar, which holds the other seven in their order, and the tab\'s address opens the first tab, the queue, tag for tag as the screen\'s own address does', array( bar_of( $off_html ), $off_html === $off_bare ), array( $bar_for( 'queue' ), true ) );

$GLOBALS['settings']['sponsor_tiers_live'] = 1;
$on_html = render_tab( 'listing' );
$on_read = reads_now();

ck( 'while it is on the tab is the bar\'s last, marked as the tab shown, and draws the box right under the bar as its one card, reading the index and nothing of the accounts, the queue or the agreements', array(
	bar_of( $on_html ),
	cards_of( $on_html ),
	false !== strpos( $on_html, '</nav><div class="wpcpm-card" id="wpcpm-sponsors-listing-box">' ),
	$on_read,
), array( $bar_for( 'listing', true ), array( 'Listing for the handbook' ), true, $read( true, false, false, false, false, false ) ) );

ck( 'with the switch on, the box carries its id, heading and lede paragraph', array(
	false !== strpos( $on_html, '<div class="wpcpm-card" id="wpcpm-sponsors-listing-box"><h2>Listing for the handbook</h2>' ),
	false !== strpos( $on_html, '<p>The partners as the Partners page lists them, in alphabetical order, as plain HTML with the logos&#039; public addresses: paste it into the handbook or the WordPress Foundation&#039;s page.</p>' ),
), array( true, true ) );

ck( 'the textarea holds the listing escaped, the one list with no tier heading, and the Copy button matches the badge card\'s own attributes', array(
	false !== strpos( $on_html, '<textarea id="wpcpm-sponsors-listing-html" class="large-text code" rows="12" readonly="readonly">&lt;ul&gt;' ) && false === strpos( $on_html, '&lt;h3' ),
	false !== strpos( $on_html, '<p><button type="button" class="button" data-wpcpm-copy="#wpcpm-sponsors-listing-html" data-wpcpm-copied="Copied." data-wpcpm-select-hint="Selected. Copy it with your keyboard.">Copy</button> <span class="wpcpm-copy__status" role="status" aria-live="polite"></span></p>' ),
), array( true, true ) );

// Every Approved sponsor paused at once, then restored: the store this file shares across every
// section above may hold others by now, and the note below is about the store, not this fixture.
$approved_now = array_keys( WPCPM_Sponsors_Index::approved() );
foreach ( $approved_now as $record ) {
	WPCPM_Sponsors_Index::patch( $record, array( 'status' => 'Paused' ) );
}
$empty_html = render_tab( 'listing' );
foreach ( $approved_now as $record ) {
	WPCPM_Sponsors_Index::patch( $record, array( 'status' => WPCPM_Sponsors_Index::STATUS_APPROVED ) );
}
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$GLOBALS['get'] = array();

ck( 'with no Approved sponsor the box says so, above an empty textarea', array(
	false !== strpos( $empty_html, '<p>No Approved sponsor yet.</p>' ),
	false !== strpos( $empty_html, '<textarea id="wpcpm-sponsors-listing-html" class="large-text code" rows="12" readonly="readonly"></textarea>' ),
), array( true, true ) );

echo "\n=== Sponsor reports: the Reports card, the editor and its handlers (1.115.0) ===\n";

/** The Reports card of a page, from its tag to the next card or the page's end, or '' when it is not drawn. */
function reports_card( $html ) {
	return card_body( $html, 'wpcpm-sponsor-reports' );
}

/** One sponsor's part of the Reports card: its heading up to the next sponsor's, or the card's end. */
function report_block( $card, $name ) {
	$at = strpos( (string) $card, '<h3>' . $name . '</h3>' );

	if ( false === $at ) {
		return '';
	}

	$next = strpos( (string) $card, '<h3>', $at + 4 );

	return false === $next ? substr( (string) $card, $at ) : substr( (string) $card, $at, $next - $at );
}

/** The editor of a page, or '' when it is not drawn. */
function editor_of( $html ) {
	$at = strpos( (string) $html, '<div class="wpcpm-card wpcpm-sreport" id="wpcpm-sreport-editor">' );

	return false === $at ? '' : substr( (string) $html, $at );
}

/** The one form of the editor that posts an action, from its tag to its end, or ''. */
function form_for( $html, $action ) {
	$field = strpos( (string) $html, '<input type="hidden" name="action" value="' . $action . '" />' );

	if ( false === $field ) {
		return '';
	}

	$open = strrpos( substr( (string) $html, 0, $field ), '<form ' );
	$end  = strpos( (string) $html, '</form>', $field );

	return false === $open || false === $end ? '' : substr( (string) $html, $open, $end + 7 - $open );
}

/**
 * What the store or the files class answers about one report, or 'no report' when the ID names
 * none: a check that finds no report fails, and the run goes on to say what else is wrong.
 */
function of_report( $id, $what ) {
	$post = get_post( (int) $id );

	if ( ! $post instanceof WP_Post ) {
		return 'no report';
	}

	switch ( $what ) {
		case 'narratives':
			return WPCPM_Sponsor_Report::narratives( $post );
		case 'state':
			return WPCPM_Sponsor_Report::state( $post );
		case 'final_at':
			return WPCPM_Sponsor_Report::final_at( $post );
		case 'print_url':
			return WPCPM_Sponsor_Report_Files::print_url( $post );
		case 'download_url':
			return WPCPM_Sponsor_Report_Files::download_url( $post );
	}

	return 'no report';
}

/**
 * One table of the editor, found by its caption: how many body rows and how many named columns it
 * has, or null when there is no such table. The words in it are the printed report's, and
 * bin/test-sponsor-report.php proves them; this suite proves the table is there and its shape.
 */
function table_of( $html, $caption ) {
	$at = strpos( (string) $html, '<caption>' . $caption . '</caption>' );

	if ( false === $at ) {
		return null;
	}

	$end   = strpos( (string) $html, '</table>', $at );
	$table = false === $end ? '' : substr( (string) $html, $at, $end - $at );
	$body  = strpos( $table, '<tbody>' );

	return array(
		'rows'    => false === $body ? 0 : substr_count( substr( $table, $body ), '<tr>' ),
		'columns' => substr_count( $table, '<th scope="col">' ),
	);
}

/**
 * The page a press lands on, followed: its address drawn as the next request draws it, with the
 * outcome the press flashed. What the bar marks, the opening tag of the first thing under the bar,
 * how many dismissible notices the page printed and whether each is above the bar; or the press's
 * own answer when it did not redirect, so a check that expects a page fails on it.
 *
 * @param array $pressed What `post()` answered.
 * @return array
 */
function landing_of( array $pressed ) {
	if ( 'redirect' !== $pressed[0] ) {
		return $pressed;
	}

	WPCPM_Flash::set( WPCPM_Sponsors::FLASH, (string) $pressed[2] );

	$page   = render_screen( link_of( (string) $pressed[1] )[1] );
	$bar    = bar_of( $page );
	$marked = array();
	foreach ( null === $bar ? array() : $bar[1] as $tab ) {
		if ( $tab[2] ) {
			$marked[] = $tab[1];
		}
	}
	$under = preg_match( '#</nav>(<div class="[^"]*"(?: id="[^"]*")?>)#', $page, $first ) ? $first[1] : '';
	preg_match_all( '#<div class="notice notice-[a-z]+ is-dismissible">#', $page, $notices, PREG_OFFSET_CAPTURE );
	$above = true;
	foreach ( $notices[0] as $notice ) {
		$above = $above && $notice[1] < (int) strpos( $page, '<nav class="nav-tab-wrapper' );
	}

	return array( implode( ', ', $marked ), $under, count( $notices[0] ), $above );
}

/** A stylesheet rule's declarations, property => value, whatever its spacing; array() when there is no such rule. */
function css_rule( $css, $selector ) {
	if ( 1 !== preg_match( '/(?:\A|[}\/])\s*' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/', (string) $css, $m ) ) {
		return array();
	}

	$out = array();

	foreach ( explode( ';', $m[1] ) as $declaration ) {
		$parts = explode( ':', $declaration, 2 );

		if ( 2 === count( $parts ) ) {
			$out[ trim( $parts[0] ) ] = trim( (string) preg_replace( '/\s+/', ' ', $parts[1] ) );
		}
	}

	ksort( $out );

	return $out;
}

// The Reports card's address is its tab's, and the editor's is that tab with the report named: a
// view of the tab. A sponsor's Edit form, where Not a renewal is pressed, is the Sponsors tab's view.
$S         = 'WPCPM_Sponsor_Report_Screen';
$screen_at = 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors';
$card_url  = $screen_at . '&tab=reports';
$editor_at = $card_url . '&wpcpm_sreport=';
$edit_view = $screen_at . '&tab=sponsors&edit=';
$landings  = array();
// The field core prints beside every nonce: the address of the page the form was drawn on, as the
// draw's own request names it (`render_screen()`).
$referer_on = static function ( array $get ) {
	return '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( '/wp-admin/admin.php?' . http_build_query( array_merge( array( 'page' => 'wpcpm-sponsors' ), $get ) ) ) . '" />';
};

// Sponsors of this section's own, so what the card lists for them does not depend on what the
// sections above left, whose last uninstall() took every sponsor with it: a Backer and a Partner
// whose terms ended on 30 September 2026, a Supporter with no term, a Friend, a paused
// Backer, and a Champion renewed early, owed the report of the Backer term the renewal moved off.
$GLOBALS['today'] = '2026-10-15';
$sr_backer        = 'spoAAAA0000000051';
$sr_partner       = 'spoAAAA0000000052';
$sr_supporter     = 'spoAAAA0000000053';
$sr_mentor        = 'spoAAAA0000000054';
$sr_paused        = 'spoAAAA0000000055';
$sr_owed          = 'spoAAAA0000000056';
foreach ( array(
	$sr_backer    => array( 'name' => 'Report Backer Example', 'tier' => 'backer', 'tier_until' => '2026-09-30', 'funded_line' => 'Stipends for two mentors' ),
	$sr_partner   => array( 'name' => 'Report Partner Example', 'tier' => 'partner', 'tier_until' => '2026-09-30', 'funded_line' => 'Mentors for the 150-hour track' ),
	$sr_supporter => array( 'name' => 'Report Supporter Example', 'tier' => 'supporter' ),
	$sr_mentor    => array( 'name' => 'Report Mentor Example', 'tier' => 'mentor' ),
	$sr_paused    => array( 'name' => 'Report Paused Example', 'status' => 'Paused', 'tier' => 'backer', 'tier_until' => '2026-09-30' ),
	$sr_owed      => array( 'name' => 'Report Renewed Example', 'tier' => 'champion', 'tier_until' => '2027-09-30', 'funded_line' => 'A co-hosted event', 'report_owed' => array( 'tier' => 'backer', 'until' => '2026-09-30', 'funded_line' => 'Stipends for two mentors' ) ),
) as $key => $fields ) {
	WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $key, 'status' => 'Approved' ), $fields ) );
}
$GLOBALS['partner_tracks'] = array(
	$sr_partner => array(
		array( 'key' => '150h', 'label' => 'WordPress Credits Program 150h', 'post' => 41 ),
		array( 'key' => 'design', 'label' => 'Design Track', 'post' => 42 ),
	),
);
// The two tracks' posts as the Track Builder keeps them, published: the first with words for its
// partners, the second with none.
foreach ( array(
	41 => array( 'key' => '150h', 'label' => 'WordPress Credits Program 150h', 'partners_narrative' => "Our partners' mentors met the track's students every week.\n\nMore of them next term." ),
	42 => array( 'key' => 'design', 'label' => 'Design Track' ),
) as $track_post => $published ) {
	$GLOBALS['posts'][ $track_post ]                                      = new WP_Post( array( 'ID' => $track_post, 'post_type' => WPCPM_Track_Store::POST_TYPE, 'post_title' => $published['label'], 'post_status' => 'publish' ) );
	$GLOBALS['pmeta'][ $track_post ][ WPCPM_Track_Store::META_PUBLISHED ] = WPCPM_Track_Definition::encode( $published );
}

$sponsors_now = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
$reports_src  = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-screen.php' );
$numbers_src  = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-report-numbers.php' );
$init_src     = method_body( $reports_src, 'init' );
$handlers     = array( 'handle_draft', 'handle_save', 'handle_final', 'handle_reopen', 'handle_regenerate', 'handle_not_renewal' );

ck( 'the figures stood in for this suite are the real class\'s two lists, in its order', array(
	false !== strpos( $numbers_src, "const COUNTS = array( '" . implode( "', '", WPCPM_Sponsor_Report_Numbers::COUNTS ) . "' );" ),
	false !== strpos( $numbers_src, "const TRACK_COUNTS = array( '" . implode( "', '", WPCPM_Sponsor_Report_Numbers::TRACK_COUNTS ) . "' );" ),
), array( true, true ) );
ck( 'boot() starts the report screen in its guarded list, after the report\'s PDF and printed document', false !== strpos( method_body( $sponsors_now, 'boot' ), "'WPCPM_Sponsor_Report_Files', 'WPCPM_Sponsor_Report_Screen' ) as \$front )" ), true );
ck( 'the report screen hooks its six forms as admin_post_ actions, none of them for a visitor who is signed out', array(
	array_map(
		static function ( $handler ) use ( $init_src ) {
			return substr_count( $init_src, "add_action( 'admin_post_' . self::ACTION_" . strtoupper( substr( $handler, 7 ) ) . ", array( __CLASS__, '" . $handler . "' ) );" );
		},
		$handlers
	),
	strpos( $init_src, 'nopriv' ),
), array( array_fill( 0, 6, 1 ), false ) );
ck( 'named as the plan names them', array(
	defined( $S . '::ACTION_DRAFT' ) ? array( $S::ACTION_DRAFT, $S::ACTION_SAVE, $S::ACTION_FINAL, $S::ACTION_REOPEN, $S::ACTION_REGENERATE, $S::ACTION_NOT_RENEWAL, $S::FIELD_RECORD, $S::FIELD_UNTIL ) : array(),
	defined( 'WPCPM_Sponsor_Report::LOG_OWED_CLEARED' ) ? constant( 'WPCPM_Sponsor_Report::LOG_OWED_CLEARED' ) : null,
), array(
	array( 'wpcpm_sreport_draft', 'wpcpm_sreport_save', 'wpcpm_sreport_final', 'wpcpm_sreport_reopen', 'wpcpm_sreport_regenerate', 'wpcpm_sreport_not_renewal', 'wpcpm_record', 'wpcpm_until' ),
	'sponsor_report_owed_cleared',
) );

// Every status the six handlers flash, each met by a check below.
$report_statuses = array( 'sreport-drafted', 'sreport-exists', 'sreport-bad-day', 'sreport-no-sponsor', 'sreport-no-tier', 'sreport-not-created', 'sreport-busy', 'sreport-saved', 'sreport-unchanged', 'sreport-save-final', 'sreport-unreadable', 'sreport-too-long', 'sreport-loss', 'sreport-final', 'sreport-final-already', 'sreport-final-unreadable', 'sreport-reopened', 'sreport-draft-already', 'sreport-regenerated', 'sreport-regenerate-final', 'sreport-regenerate-refused', 'sreport-gone', 'sreport-owed-cleared', 'sreport-owed-none', 'sreport-owed-not-saved', 'sreport-owed-gone' );
$report_words    = is_callable( array( $S, 'messages' ) ) ? (array) $S::messages() : array();
$report_types    = array();
foreach ( $report_words as $status => $words ) {
	$report_types[ $status ] = is_array( $words ) && isset( $words[0], $words[1] ) && in_array( $words[0], array( 'success', 'error', 'info', 'warning' ), true ) && '' !== trim( (string) $words[1] );
}
$sorted_words = array_keys( $report_words );
$sorted_flash = $report_statuses;
sort( $sorted_words );
sort( $sorted_flash );
ck( 'every outcome the report\'s handlers flash has words on the Sponsors screen, in a notice it draws, and none for an outcome nothing flashes', array(
	array_values( array_diff( $report_statuses, array_keys( WPCPM_Sponsors::messages() ) ) ),
	$sorted_words,
	array_values( array_unique( $report_types ) ),
), array( array(), $sorted_flash, array( true ) ) );
// "Any more" for "any longer" is UK usage; US English writes "anymore". Every sentence the screen
// flashes is read, the report's and its PDF's among them, so a new one is read too.
$uk_words = array();
foreach ( WPCPM_Sponsors::messages() as $status => $words ) {
	if ( is_array( $words ) && isset( $words[1] ) && false !== stripos( (string) $words[1], 'any more' ) ) {
		$uk_words[] = $status;
	}
}
ck( 'no sentence the Sponsors screen flashes says any more: a sponsor, a report or a PDF that is gone is not on this site anymore', array(
	$uk_words,
	WPCPM_Sponsors::messages()['sreport-no-sponsor'][1],
	WPCPM_Sponsors::messages()['sreport-pdf-gone'][1],
), array( array(), 'Nothing was drafted. That sponsor is not on this site anymore.', 'Nothing was changed. That report is not on this site anymore.' ) );

echo "\n--- The Reports card ---\n";
// Every tab, each drawn as itself with the launch switch on, so Handbook listing is one of them; then
// the Reports tab itself, last, so the forms the checks below draw beside it are drawn on its
// request.
$card_where = array();
$GLOBALS['settings']['sponsor_tiers_live'] = 1;
foreach ( $slugs as $slug ) {
	$card_where[ $slug ] = substr_count( render_tab( $slug ), 'id="wpcpm-sponsor-reports"' );
}
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
$html       = render_tab( 'reports' );
$card       = reports_card( $html );
$bar_labels = null === bar_of( $html ) ? array() : array_column( bar_of( $html )[1], 1 );
ck( 'the Reports card is drawn on its own tab, the bar\'s tab right before Agreements, directly under the bar and headed Reports', array(
	'' !== $card,
	in_array( 'Reports', $bar_labels, true ) && 1 === array_search( 'Agreements', $bar_labels, true ) - array_search( 'Reports', $bar_labels, true ),
	false !== strpos( $html, '</nav><div class="wpcpm-card" id="wpcpm-sponsor-reports"><h2>Reports</h2>' ),
	0 === strpos( $card, '<div class="wpcpm-card" id="wpcpm-sponsor-reports"><h2>Reports</h2><p class="description">' ),
), array( true, true, true, true ) );
ck( 'and it is the Reports tab\'s alone: the tab draws it once, as its one card, the tab marked in the bar, and no other tab draws it', array( $card_where, cards_of( $html ), bar_of( $html ) === $bar_for( 'reports' ) ), array( array_merge( array_fill_keys( $slugs, 0 ), array( 'reports' => 1 ) ), array( 'Reports' ), true ) );
$backer_block = report_block( $card, 'Report Backer Example' );
ck( 'a sponsor with no report reads so, then the form that drafts one: keyed to the sponsor, a date defaulting to the last day of its term', array(
	false !== strpos( $backer_block, '<p class="description">No report yet.</p>' ),
	before( $backer_block, 'No report yet.', '<form ' ),
	false !== strpos( $backer_block, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Drafting"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sreport_draft_' . $sr_backer . '" />' . $referer_on( array( 'tab' => 'reports' ) ) . '<input type="hidden" name="action" value="wpcpm_sreport_draft" /><input type="hidden" name="wpcpm_record" value="' . $sr_backer . '" />' ),
	false !== strpos( $backer_block, '<label for="wpcpm-sreport-until-' . $sr_backer . '">Last day of the term</label> <input type="date" id="wpcpm-sreport-until-' . $sr_backer . '" name="wpcpm_until" value="2026-09-30" required /> <button type="submit" class="button">Draft a report</button></form>' ),
	strpos( $backer_block, 'still due' ),
), array( true, true, true, true, false ) );
ck( 'a sponsor with no term drafts for today unless the manager picks another day', false !== strpos( report_block( $card, 'Report Supporter Example' ), 'name="wpcpm_until" value="2026-10-15"' ), true );
// The form is the renderer's, the one the Administrator Dashboard's Draft now is drawn by too, for
// the day the card offers: written once, beside the handler that reads it.
if ( is_callable( array( $S, 'render_draft_form' ) ) ) {
	ob_start();
	$S::render_draft_form( $sr_backer, '2026-09-30' );
	$rendered_form = (string) ob_get_clean();
} else {
	$rendered_form = '';
}
ck( 'the card\'s form is render_draft_form()\'s, drawn for the sponsor and the day the card offers', array( '' !== $rendered_form, false !== strpos( $backer_block, $rendered_form ) ), array( true, true ) );
$owed_block = report_block( $card, 'Report Renewed Example' );
ck( 'while a renewal left a term owed its report, the card says so before the form, and the form drafts for that term\'s last day, not the new term\'s', array(
	false !== strpos( $owed_block, '<p class="description">A report for the term to September 30, 2026 is still due.</p>' ),
	before( $owed_block, 'is still due.', '<form ' ),
	false !== strpos( $owed_block, 'name="wpcpm_until" value="2026-09-30"' ),
	strpos( $owed_block, 'value="2027-09-30"' ),
), array( true, true, true, false ) );
// The once-mark is the house's markup for a form, and forms.js, which wp-admin loads, reads it
// here; no suite runs the script, so it is pinned as markup.
ck( 'only Approved sponsors are on the card, and every form on it carries the house\'s once-mark', array(
	strpos( $card, 'Report Paused Example' ),
	substr_count( $card, '<form' ) > 3,
	substr_count( $card, '<form' ) === substr_count( $card, 'data-wpcpm-once' ),
), array( false, true, true ) );
$mentor_block = report_block( $card, 'Report Mentor Example' );
ck( 'a sponsor whose tier on the form\'s day carries no report, a Friend, reads so once and has no form, since every press of one would be refused', array(
	false !== strpos( $mentor_block, '<p class="description">No report for this tier.</p>' ),
	strpos( $mentor_block, 'No report yet.' ),
	strpos( $mentor_block, 'wpcpm_sreport_draft' ),
	strpos( $mentor_block, '<form' ),
), array( true, false, false, false ) );
ck( 'while a sponsor whose term has just ended, one with no term and one owed a moved term each keep the form', array(
	false !== strpos( $backer_block, 'value="wpcpm_sreport_draft"' ),
	false !== strpos( report_block( $card, 'Report Supporter Example' ), 'value="wpcpm_sreport_draft"' ),
	false !== strpos( $owed_block, 'value="wpcpm_sreport_draft"' ),
	strpos( $backer_block . $owed_block, 'No report for this tier.' ),
), array( true, true, true, false ) );
if ( is_callable( array( $S, 'render_card' ) ) ) {
	ob_start();
	$S::render_card( array( $sr_supporter => WPCPM_Sponsors_Index::row( $sr_supporter ) ), '2026-11-02' );
	$given_day = (string) ob_get_clean();
} else {
	$given_day = '';
}
ck( 'the card drafts for the day it is handed: a sponsor with no term is offered a report ending on that day', false !== strpos( report_block( $given_day, 'Report Supporter Example' ), 'name="wpcpm_until" value="2026-11-02"' ), true );
if ( is_callable( array( $S, 'render_card' ) ) ) {
	ob_start();
	$S::render_card( array() );
	$no_sponsors = (string) ob_get_clean();
} else {
	$no_sponsors = '';
}
ck( 'with no Approved sponsor the card says so', false !== strpos( $no_sponsors, '<p>No Approved sponsor yet.</p>' ), true );

echo "\n--- Draft a report ---\n";
$GLOBALS['nonce_checked'] = array();
$audit_mark               = count( $GLOBALS['audit'] );
$r                        = post( array( 'wpcpm_record' => $sr_backer, 'wpcpm_until' => '2026-09-30' ), array( $S, 'handle_draft' ) );
$sr_a26                   = WPCPM_Sponsor_Report::find( $sr_backer, '2026-09-30' );
$sr_a26_id                = $sr_a26 instanceof WP_Post ? (int) $sr_a26->ID : 0;
$kinds_of                 = static function ( array $rows ) {
	return array_map(
		static function ( $row ) {
			return array( $row['kind'], $row['ground'] );
		},
		array_values( $rows )
	);
};
ck( 'Draft a report makes the draft and opens it in the editor, under the nonce keyed to the sponsor, audited as drafted by the manager', array(
	$r,
	end( $GLOBALS['nonce_checked'] ),
	$sr_a26 instanceof WP_Post ? array( $sr_a26->post_title, WPCPM_Sponsor_Report::state( $sr_a26 ) ) : array(),
	$kinds_of( array_slice( $GLOBALS['audit'], $audit_mark ) ),
), array(
	array( 'redirect', $editor_at . $sr_a26_id, 'sreport-drafted' ),
	'wpcpm_sreport_draft_' . $sr_backer,
	array( 'Report Backer Example, October 2025 to September 2026', 'draft' ),
	array( array( 'sponsor_report_drafted', 'manager' ) ),
) );
$landings['Draft a report'] = $r;

$posts_mark = count( $GLOBALS['posts'] );
$audit_mark = count( $GLOBALS['audit'] );
$refusals   = array();
foreach ( array( array( $sr_backer, '2026-09-30' ), array( $sr_backer, '2026-02-30' ), array( 'spoZZZZ0000000077', '2026-09-30' ), array( $sr_mentor, '2026-09-30' ) ) as $pair ) {
	$refusals[] = post( array( 'wpcpm_record' => $pair[0], 'wpcpm_until' => $pair[1] ), array( $S, 'handle_draft' ) );
}
$GLOBALS['insert_fails'] = true;
$refusals[]              = post( array( 'wpcpm_record' => $sr_supporter, 'wpcpm_until' => '2025-12-31' ), array( $S, 'handle_draft' ) );
unset( $GLOBALS['insert_fails'] );
ck( 'a refused draft writes nothing and says why on the Reports card: that term\'s report exists, a day that is not one, a sponsor the store does not hold, a tier carrying no report, a report the site could not store', array(
	$refusals,
	count( $GLOBALS['posts'] ) - $posts_mark,
	count( $GLOBALS['audit'] ) - $audit_mark,
), array(
	array(
		array( 'redirect', $card_url, 'sreport-exists' ),
		array( 'redirect', $card_url, 'sreport-bad-day' ),
		array( 'redirect', $card_url, 'sreport-no-sponsor' ),
		array( 'redirect', $card_url, 'sreport-no-tier' ),
		array( 'redirect', $card_url, 'sreport-not-created' ),
	),
	0,
	0,
) );
$landings['a refused draft'] = $refusals[0];
// Another request, the daily run or a second program manager, is drafting a report of the Backer's
// right now and holds the store's lock for the sponsor; the day pressed has no report, so only the
// lock can refuse it.
$busy_lock                     = 'wpcpm_sreport_lock_' . $sr_backer;
$busy_at                       = time();
$GLOBALS['opts'][ $busy_lock ] = $busy_at;
$posts_mark                    = count( $GLOBALS['posts'] );
$audit_mark                    = count( $GLOBALS['audit'] );
$r_busy                        = post( array( 'wpcpm_record' => $sr_backer, 'wpcpm_until' => '2025-09-30' ), array( $S, 'handle_draft' ) );
$busy_left                     = get_option( $busy_lock, 'released' );
unset( $GLOBALS['opts'][ $busy_lock ] );
ck( 'a draft pressed while another request drafts one of the sponsor\'s reports writes nothing, says so on the Reports card, and leaves that request its lock', array(
	$r_busy,
	count( $GLOBALS['posts'] ) - $posts_mark,
	count( $GLOBALS['audit'] ) - $audit_mark,
	$busy_left,
	isset( WPCPM_Sponsors::messages()['sreport-busy'] ) ? WPCPM_Sponsors::messages()['sreport-busy'] : array(),
), array(
	array( 'redirect', $card_url, 'sreport-busy' ),
	0,
	0,
	$busy_at,
	array( 'error', 'A report for this sponsor is being drafted right now. Try again in a minute.' ),
) );
$r         = post( array( 'wpcpm_record' => $sr_backer, 'wpcpm_until' => '2026-09-30', 'wpcpm_return' => 'dashboard', 'wpcpm_return_to' => 'sponsor-reports' ), array( $S, 'handle_draft' ) );
$dashboard = WPCPM_Flash::take( WPCPM_Institutions::FLASH );
ck( 'a draft refused on the Administrator Dashboard goes back there, saying why on that page\'s channel', array( $r, $dashboard ), array( array( 'redirect', 'https://example.test/administrator-dashboard/#wpcpm-sponsor-reports', '' ), 'sreport-exists' ) );
$r         = post( array( 'wpcpm_record' => $sr_partner, 'wpcpm_until' => '2026-09-30', 'wpcpm_return' => 'dashboard', 'wpcpm_return_to' => 'sponsor-reports' ), array( $S, 'handle_draft' ) );
$sr_p26    = WPCPM_Sponsor_Report::find( $sr_partner, '2026-09-30' );
$sr_p26_id = $sr_p26 instanceof WP_Post ? (int) $sr_p26->ID : 0;
ck( 'and one made there opens the new draft in the editor, as a draft made on this screen does', array( $r, WPCPM_Flash::take( WPCPM_Institutions::FLASH ), $sr_p26_id > 0 ), array( array( 'redirect', $editor_at . $sr_p26_id, 'sreport-drafted' ), '', true ) );
post( array( 'wpcpm_record' => $sr_backer, 'wpcpm_until' => '2025-09-30' ), array( $S, 'handle_draft' ) );
$sr_a25    = WPCPM_Sponsor_Report::find( $sr_backer, '2025-09-30' );
$sr_a25_id = $sr_a25 instanceof WP_Post ? (int) $sr_a25->ID : 0;

$card         = reports_card( render_tab( 'reports' ) );
$backer_block = report_block( $card, 'Report Backer Example' );
ck( 'a sponsor\'s reports are listed newest period first, each by its title and its state, with Open, above the form', array(
	strpos( $backer_block, 'No report yet.' ),
	false !== strpos( $backer_block, '<ul class="wpcpm-list"><li>Report Backer Example, October 2025 to September 2026 <span class="description">Draft</span> <a href="' . $editor_at . $sr_a26_id . '">Open<span class="screen-reader-text"> Report Backer Example, October 2025 to September 2026</span></a></li>' ),
	false !== strpos( $backer_block, '<li>Report Backer Example, October 2024 to September 2025 <span class="description">Draft</span> <a href="' . $editor_at . $sr_a25_id . '">Open' ),
	before( $backer_block, 'October 2025 to September 2026', 'October 2024 to September 2025' ),
	before( $backer_block, '</ul>', '<form ' ),
), array( false, true, true, true, true ) );

echo "\n--- The editor ---\n";
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'sreport-saved' );
$GLOBALS['revisions'][ $sr_a26_id ] = array( 7001, 7002 );
$html                                = render_tab( 'reports', array( 'wpcpm_sreport' => $sr_a26_id ) );
$editor_read                         = reads_now();
$editor                              = editor_of( $html );
$saved_words                         = isset( WPCPM_Sponsors::messages()['sreport-saved'] ) ? WPCPM_Sponsors::messages()['sreport-saved'][1] : 'no words';
ck( 'a report named in the address is drawn alone, under the screen\'s heading and its notice, in place of every card', array(
	'' !== $editor,
	before( $html, '<h1>Sponsors</h1>', 'id="wpcpm-sreport-editor"' ),
	before( $html, '<div class="notice notice-success is-dismissible"><p>' . esc_html( $saved_words ) . '</p></div>', 'id="wpcpm-sreport-editor"' ),
	strpos( $html, 'id="wpcpm-sponsor-reports"' ),
	substr_count( $html, '<div class="wpcpm-card' ),
), array( true, true, true, false, 1 ) );
ck( 'headed by the report\'s title, it says the period and that the report is a draft, the day its figures were read, and leads back to the Reports card', array(
	0 === strpos( $editor, '<div class="wpcpm-card wpcpm-sreport" id="wpcpm-sreport-editor"><h2>Report Backer Example, October 2025 to September 2026</h2>' ),
	false !== strpos( $editor, '<p class="wpcpm-sreport__facts">The twelve months from October 1, 2025 to September 30, 2026. A draft: the sponsor sees nothing of it until it is marked final.</p>' ),
	false !== strpos( $editor, '<p class="description">Figures read from the program&#039;s records on October 15, 2026.</p>' ),
	false !== strpos( $editor, '<p><a href="' . $card_url . '">Back to the reports</a></p>' ),
), array( true, true, true, true ) );
// The editor is the Reports tab's view of one report, as one sponsor's Edit form is the Sponsors
// tab's: drawn in the card's place, and on no other tab, whatever that tab's address carries. Each
// other tab is drawn as itself, the launch switch on, so Handbook listing is one of them.
$editor_elsewhere = array();
$GLOBALS['settings']['sponsor_tiers_live'] = 1;
foreach ( array_diff( $slugs, array( 'reports' ) ) as $slug ) {
	$editor_elsewhere[ $slug ] = strpos( render_tab( $slug, array( 'wpcpm_sreport' => $sr_a26_id ) ), 'id="wpcpm-sreport-editor"' );
}
unset( $GLOBALS['settings']['sponsor_tiers_live'] );
ck( 'the editor is a view of the Reports tab: directly under the bar in the Reports card\'s place, the Reports tab marked in the bar, reading the report and neither the index nor an account; and no other tab draws it for the same report', array(
	false !== strpos( $html, '</nav><div class="wpcpm-card wpcpm-sreport" id="wpcpm-sreport-editor"><h2>' ),
	bar_of( $html ) === $bar_for( 'reports' ),
	$editor_read,
	$editor_elsewhere,
), array( true, true, $read( false, false, false, false, false, false ), array_fill_keys( array_diff( $slugs, array( 'reports' ) ), false ) ) );
ck( 'its way back is the Reports tab, which lists every Approved sponsor\'s reports, and it is the editor\'s one way back', array(
	$edit_link( $editor, '#<a href="([^"]*)">Back to the reports</a>#' ),
	substr_count( $editor, '>Back to the' ),
), array( array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'reports' ) ), 1 ) );
ck( 'the program as one table captioned with its section\'s heading, a row for each of its eight figures (their names and values are the printed report\'s, proved row for row in bin/test-sponsor-report.php)', table_of( $editor, 'The program this year' ), array( 'rows' => 8, 'columns' => 0 ) );
ck( 'the sponsorship: the brief under the section\'s heading, four rows for a Backer, which is invited to no event; the mentors, a row each under three columns; the claims, a row a month under the month, the offer that drew them and all offers', array(
	table_of( $editor, 'Your sponsorship' ),
	table_of( $editor, 'The mentors sponsored, and the students who started with each in these twelve months' ),
	table_of( $editor, 'Offers and claims by calendar month' ),
), array( array( 'rows' => 4, 'columns' => 0 ), array( 'rows' => 1, 'columns' => 3 ), array( 'rows' => 12, 'columns' => 3 ) ) );
ck( 'a Backer\'s two narratives, the cost line and looking ahead, each a labeled box of eight rows with the report\'s saved versions counted beside its label, after the figures and without a track', array(
	false !== strpos( $editor, '<div class="wpcpm-sreport__field"><p><label for="wpcpm-sreport-text-cost_line">The cost line</label> <span class="description">2 saved versions</span></p><textarea id="wpcpm-sreport-text-cost_line" name="wpcpm_sreport_text_cost_line" rows="8" class="large-text" maxlength="5000"></textarea></div>' ),
	false !== strpos( $editor, '<div class="wpcpm-sreport__field"><p><label for="wpcpm-sreport-text-ahead">Looking ahead</label> <span class="description">2 saved versions</span></p><textarea id="wpcpm-sreport-text-ahead" name="wpcpm_sreport_text_ahead" rows="8" class="large-text" maxlength="5000"></textarea></div>' ),
	before( $editor, '<caption>Offers and claims by calendar month</caption>', 'name="wpcpm_sreport_text_cost_line"' ),
	before( $editor, 'name="wpcpm_sreport_text_cost_line"', 'name="wpcpm_sreport_text_ahead"' ),
	substr_count( $editor, '<textarea' ),
	strpos( $editor, 'wpcpm_sreport_text_track' ),
), array( true, true, true, true, 2, false ) );
$save_form = form_for( $editor, 'wpcpm_sreport_save' );
ck( 'the narratives are one form that saves them all, keyed to the report', array(
	0 === strpos( $save_form, '<form method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-sreport__form" data-wpcpm-once data-wpcpm-busy="Saving"><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sreport_save_' . $sr_a26_id . '" />' . $referer_on( array( 'tab' => 'reports', 'wpcpm_sreport' => $sr_a26_id ) ) . '<input type="hidden" name="action" value="wpcpm_sreport_save" /><input type="hidden" name="wpcpm_sreport_post" value="' . $sr_a26_id . '" />' ),
	substr_count( $save_form, '<textarea' ),
	false !== strpos( $save_form, '<p class="submit"><button type="submit" class="button button-primary">Save the narratives</button></p></form>' ),
), array( true, 2, true ) );
$print_url   = of_report( $sr_a26_id, 'print_url' );
$upload_form = form_for( $editor, 'wpcpm_sreport_upload' );
$regen_form  = form_for( $editor, 'wpcpm_sreport_regenerate' );
ck( 'a draft\'s buttons: Preview in a new tab, the PDF to attach, Mark final, and Regenerate, whose question says the saved narratives are kept and words not saved are not; no Reopen, and nothing to remove', array(
	false !== strpos( $editor, '<a class="button" href="' . $print_url . '" target="_blank" rel="noopener noreferrer">Preview</a>' ),
	false !== strpos( $upload_form, 'enctype="multipart/form-data"' ) && false !== strpos( $upload_form, 'nonce-wpcpm_sreport_upload_' . $sr_a26_id ) && false !== strpos( $upload_form, '<input type="hidden" name="wpcpm_sreport_post" value="' . $sr_a26_id . '" />' ),
	false !== strpos( $upload_form, '<input type="file" id="wpcpm-sreport-file" name="wpcpm_sreport_file" accept="application/pdf,.pdf" required />' ) && false !== strpos( $upload_form, '>Attach the PDF</button>' ),
	strpos( $editor, 'value="wpcpm_sreport_remove"' ),
	false !== strpos( form_for( $editor, 'wpcpm_sreport_final' ), 'nonce-wpcpm_sreport_final_' . $sr_a26_id ) && false !== strpos( form_for( $editor, 'wpcpm_sreport_final' ), '>Mark final</button>' ),
	strpos( $editor, 'value="wpcpm_sreport_reopen"' ),
	false !== strpos( $regen_form, 'nonce-wpcpm_sreport_regenerate_' . $sr_a26_id ) && false !== strpos( $regen_form, '<button type="submit" class="button" data-wpcpm-confirm="Read the figures again from the program&#039;s records, for the same twelve months? The saved narratives are kept; words typed above and not saved are not.">Regenerate</button>' ),
	before( $editor, '>Preview</a>', 'value="wpcpm_sreport_upload"' ) && before( $editor, 'value="wpcpm_sreport_upload"', 'value="wpcpm_sreport_final"' ) && before( $editor, 'value="wpcpm_sreport_final"', 'value="wpcpm_sreport_regenerate"' ),
	before( $editor, '</form>', '>Preview</a>' ),
), array( true, true, true, false, true, false, true, true, true ) );
ck( 'every form in the editor carries the house\'s once-mark', array( substr_count( $editor, '<form' ), substr_count( $editor, 'data-wpcpm-once' ) ), array( 4, 4 ) );
ck( 'and its two questions, Mark final\'s and Regenerate\'s, are data-wpcpm-confirm sentences that forms.js asks, never an inline handler', array( substr_count( $editor, ' data-wpcpm-confirm="' ), strpos( $editor, 'onclick=' ), strpos( $editor, 'onsubmit=' ) ), array( 2, false, false ) );
$final_form = form_for( $editor, 'wpcpm_sreport_final' );
ck( 'Mark final is a secondary button, Save the narratives the page\'s one primary button, and Mark final asks first, saying words typed and not saved are not kept', array(
	false !== strpos( $final_form, '<button type="submit" class="button" data-wpcpm-confirm="Mark this report final? Words typed above and not saved are not kept: save the narratives first.">Mark final</button>' ),
	false !== strpos( $final_form, 'Words typed above and not saved are not kept: save the narratives first.' ),
	substr_count( $editor, 'button-primary' ),
), array( true, true, 1 ) );

$p_editor = editor_of( render_tab( 'reports', array( 'wpcpm_sreport' => $sr_p26_id ) ) );
ck( 'a Partner\'s report adds a table and a narrative for each track it partners, the track\'s seven figures without a finished count, between the cost line and looking ahead, and its brief a fifth row for the events it is invited to', array(
	table_of( $p_editor, 'The tracks: WordPress Credits Program 150h' ),
	table_of( $p_editor, 'The tracks: Design Track' ),
	false !== strpos( $p_editor, '<label for="wpcpm-sreport-text-track_150h">The tracks: WordPress Credits Program 150h</label> <span class="description">No saved versions yet.</span></p><textarea id="wpcpm-sreport-text-track_150h" name="wpcpm_sreport_text_track_150h" rows="8"' ),
	false !== strpos( $p_editor, 'name="wpcpm_sreport_text_track_design"' ),
	before( $p_editor, 'name="wpcpm_sreport_text_cost_line"', '<caption>The tracks: WordPress Credits Program 150h</caption>' ),
	before( $p_editor, 'name="wpcpm_sreport_text_track_150h"', '<caption>The tracks: Design Track</caption>' ),
	before( $p_editor, 'name="wpcpm_sreport_text_track_design"', 'name="wpcpm_sreport_text_ahead"' ),
	table_of( $p_editor, 'Your sponsorship' ),
), array( array( 'rows' => 7, 'columns' => 0 ), array( 'rows' => 7, 'columns' => 0 ), true, true, true, true, true, array( 'rows' => 5, 'columns' => 0 ) ) );
ck( 'and the new draft opens with the first track\'s box holding the words that track was last published with, for its partners, and the second\'s empty, that track having none', array(
	substr_count( $p_editor, 'name="wpcpm_sreport_text_track_150h" rows="8" class="large-text" maxlength="5000">Our partners&#039; mentors met the track&#039;s students every week.' . "\n\n" . 'More of them next term.</textarea>' ),
	substr_count( $p_editor, 'name="wpcpm_sreport_text_track_design" rows="8" class="large-text" maxlength="5000"></textarea>' ),
), array( 1, 1 ) );
$GLOBALS['revisions_off'] = true;
$no_versions              = editor_of( render_tab( 'reports', array( 'wpcpm_sreport' => $sr_p26_id ) ) );
unset( $GLOBALS['revisions_off'] );
ck( 'on a site that keeps no revisions a box says nothing of versions, where "No saved versions yet." would never come true', array(
	false !== strpos( $no_versions, '<label for="wpcpm-sreport-text-ahead">Looking ahead</label></p><textarea' ),
	strpos( $no_versions, 'saved version' ),
	strpos( $no_versions, 'No saved versions yet.' ),
), array( true, false, false ) );
$GLOBALS['report_sponsorship'] = array( 'mentors' => array(), 'offers' => array() );
post( array( 'wpcpm_sreport_post' => $sr_p26_id ), array( $S, 'handle_regenerate' ) );
unset( $GLOBALS['report_sponsorship'] );
$p_editor = editor_of( render_tab( 'reports', array( 'wpcpm_sreport' => $sr_p26_id ) ) );
ck( 'with no mentor linked and no offer claimed, the editor says so in a sentence where each table would be', array(
	false !== strpos( $p_editor, '<p class="description">No mentor was linked to this sponsor when the report was generated.</p>' ),
	false !== strpos( $p_editor, '<p class="description">None of the sponsor&#039;s offers was claimed in these months.</p>' ),
	strpos( $p_editor, '<caption>The mentors sponsored' ),
	strpos( $p_editor, '<caption>Offers and claims by calendar month</caption>' ),
), array( true, true, false, false ) );

echo "\n--- Save, Mark final, Reopen, Regenerate ---\n";
$GLOBALS['nonce_checked'] = array();
$r                        = post( array( 'wpcpm_sreport_post' => $sr_a26_id, 'wpcpm_sreport_text_cost_line' => "Two mentors' stipends.\n\nAudited in June.", 'wpcpm_sreport_text_ahead' => 'Next year we grow.', 'wpcpm_sreport_text_program' => 'Not a narrative.' ), array( $S, 'handle_save' ) );
$sr_words                 = array( 'cost_line' => "Two mentors' stipends.\n\nAudited in June.", 'ahead' => 'Next year we grow.' );
ck( 'Save stores every narrative the form sends and nothing under a key no section takes, under the nonce keyed to the report, and goes back to the editor', array( $r, end( $GLOBALS['nonce_checked'] ), of_report( $sr_a26_id, 'narratives' ) ), array( array( 'redirect', $editor_at . $sr_a26_id, 'sreport-saved' ), 'wpcpm_sreport_save_' . $sr_a26_id, $sr_words ) );
$landings['Save']  = $r;
$r                 = post( array( 'wpcpm_sreport_post' => $sr_a26_id, 'wpcpm_sreport_text_ahead' => 'Next year we grow again.' ), array( $S, 'handle_save' ) );
$sr_words['ahead'] = 'Next year we grow again.';
ck( 'a box the form does not send keeps its words', array( isset( $r[2] ) ? $r[2] : $r, of_report( $sr_a26_id, 'narratives' ) ), array( 'sreport-saved', $sr_words ) );
ck( 'and the editor puts the stored words back in their boxes, escaped', false !== strpos( editor_of( render_tab( 'reports', array( 'wpcpm_sreport' => $sr_a26_id ) ) ), 'maxlength="5000">Two mentors&#039; stipends.' . "\n\n" . 'Audited in June.</textarea>' ), true );
$GLOBALS['update_fails'] = true;
$r                       = post( array( 'wpcpm_sreport_post' => $sr_a26_id, 'wpcpm_sreport_text_ahead' => 'Words typed during a refused update.' ), array( $S, 'handle_save' ) );
unset( $GLOBALS['update_fails'] );
$refused_words = of_report( $sr_a26_id, 'narratives' );
post( array( 'wpcpm_sreport_post' => $sr_a26_id, 'wpcpm_sreport_text_ahead' => $sr_words['ahead'] ), array( $S, 'handle_save' ) );
ck( 'a save whose version WordPress would not take has stored the words all the same, and says they are saved', array( isset( $r[2] ) ? $r[2] : $r, is_array( $refused_words ) ? $refused_words['ahead'] : $refused_words, of_report( $sr_a26_id, 'narratives' ) ), array( 'sreport-saved', 'Words typed during a refused update.', $sr_words ) );

// A narrative is counted as the manager typed it, over core's cleaner: five thousand characters with
// a "<", quote marks, ampersands and the CR LF breaks a browser posts are saved whole; one more is
// refused with nothing stored, and the notice names the box; posted back as drawn, unedited, nothing
// is written, not even the last editor.
$sr_head  = "Two mentors' stipends, \"audited\" & signed < 25 & more\r\nSecond line\r\n";
$sr_5000  = $sr_head . str_repeat( 'w', 5000 - mb_strlen( str_replace( "\r\n", "\n", $sr_head ) ) );
$sr_kept  = str_replace( "\r\n", "\n", trim( sanitize_textarea_field( $sr_5000 ) ) );
$r        = post( array( 'wpcpm_sreport_post' => $sr_a26_id, 'wpcpm_sreport_text_cost_line' => $sr_5000 ), array( $S, 'handle_save' ) );
$sr_at    = of_report( $sr_a26_id, 'narratives' );
$r_over   = post( array( 'wpcpm_sreport_post' => $sr_a26_id, 'wpcpm_sreport_text_cost_line' => $sr_5000 . 'z', 'wpcpm_sreport_text_ahead' => 'A change beside it.' ), array( $S, 'handle_save' ) );
$sr_after = of_report( $sr_a26_id, 'narratives' );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_over[2] ) ? (string) $r_over[2] : '' );
$sr_long_html = render_tab( 'reports', array( 'wpcpm_sreport' => $sr_a26_id ) );
ck( 'Save keeps a narrative of five thousand characters as typed whole, and refuses one more with a notice that names the box, storing nothing, the other box\'s change with it', array(
	isset( $r[2] ) ? $r[2] : $r,
	is_array( $sr_at ) && $sr_kept === $sr_at['cost_line'],
	$r_over,
	$sr_after === $sr_at,
	notices_in( $sr_long_html ),
	flash_left(),
), array(
	'sreport-saved',
	true,
	array( 'redirect', $editor_at . $sr_a26_id, 'sreport-too-long' ),
	true,
	'<div class="notice notice-error is-dismissible"><p>Nothing was saved: &quot;The cost line&quot; is longer than 5000 characters. Shorten it and save again.</p></div>',
	array(),
) );
// The refusal's page drew every box as it was typed, once; drawn again, they hold what is stored.
$sr_editor = editor_of( render_tab( 'reports', array( 'wpcpm_sreport' => $sr_a26_id ) ) );
$sr_boxes  = array();
foreach ( array( 'cost_line', 'ahead' ) as $sr_key ) {
	$sr_drawn = preg_match( '#name="wpcpm_sreport_text_' . $sr_key . '" rows="8" class="large-text" maxlength="(\d+)">(.*?)</textarea>#s', $sr_editor, $m ) ? array( $m[2], (int) $m[1] ) : array( '', 0 );
	$sr_boxes[ 'wpcpm_sreport_text_' . $sr_key ] = browser_lines( browser_shows( $sr_drawn[0], true ) );
	if ( 'cost_line' === $sr_key ) {
		$sr_cost_box = $sr_drawn;
	}
}
$GLOBALS['pmeta'][ $sr_a26_id ]['_edit_last'] = 4242;
$r_same = post( array_merge( array( 'wpcpm_sreport_post' => $sr_a26_id ), $sr_boxes ), array( $S, 'handle_save' ) );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_same[2] ) ? (string) $r_same[2] : '' );
ck( 'the editor draws it back as typed, with the shared rule\'s maxlength, and the boxes posted back unedited save nothing and say so, as the Edit form says it: the words and the last editor stay', array(
	browser_shows( $sr_cost_box[0], true ),
	$sr_cost_box[1],
	$r_same,
	of_report( $sr_a26_id, 'narratives' ) === $sr_at,
	$GLOBALS['pmeta'][ $sr_a26_id ]['_edit_last'],
	notices_in( render_tab( 'reports', array( 'wpcpm_sreport' => $sr_a26_id ) ) ),
	isset( WPCPM_Sponsors::messages()['sreport-unchanged'] ) ? WPCPM_Sponsors::messages()['sreport-unchanged'][1] : null,
), array(
	str_replace( "\r\n", "\n", $sr_5000 ),
	WPCPM_Typed_Text::drawn_limit( $sr_at['cost_line'], 5000 ),
	array( 'redirect', $editor_at . $sr_a26_id, 'sreport-unchanged' ),
	true,
	4242,
	'<div class="notice notice-info is-dismissible"><p>Nothing changed, so nothing was saved.</p></div>',
	WPCPM_Sponsors::messages()['sponsor-unchanged'][1],
) );
// A narrative an earlier version cut at five thousand stored characters can end in a space, which
// the cleaner trims from the post-back: unedited, it changes nothing either.
$GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_SECTIONS ]['ahead'] = 'Cut short by the old cap, at ';
$sr_cut_before = $GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_SECTIONS ];
$r_cut         = post( array( 'wpcpm_sreport_post' => $sr_a26_id, 'wpcpm_sreport_text_ahead' => 'Cut short by the old cap, at ' ), array( $S, 'handle_save' ) );
ck( 'a narrative cut by an earlier version that ends in a space, posted back unedited, saves nothing and says so: the words and the last editor stay', array(
	isset( $r_cut[2] ) ? $r_cut[2] : $r_cut,
	$GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_SECTIONS ] === $sr_cut_before,
	$GLOBALS['pmeta'][ $sr_a26_id ]['_edit_last'],
), array( 'sreport-unchanged', true, 4242 ) );
// A track's box is named as the editor heads it, with the track's name.
$r_track = post( array( 'wpcpm_sreport_post' => $sr_p26_id, 'wpcpm_sreport_text_track_150h' => str_repeat( 't', 5002 ) ), array( $S, 'handle_save' ) );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_track[2] ) ? (string) $r_track[2] : '' );
ck( 'a track\'s narrative too long is named by the track, and without the box carried beside it the notice still says what to do', array(
	isset( $r_track[2] ) ? $r_track[2] : $r_track,
	notices_in( render_tab( 'reports', array( 'wpcpm_sreport' => $sr_p26_id ) ) ),
	isset( WPCPM_Sponsors::messages()['sreport-too-long'] ) ? WPCPM_Sponsors::messages()['sreport-too-long'] : array(),
), array(
	'sreport-too-long',
	'<div class="notice notice-error is-dismissible"><p>Nothing was saved: &quot;The tracks: WordPress Credits Program 150h&quot; is longer than 5000 characters. Shorten it and save again.</p></div>',
	array( 'error', 'Nothing was saved: a narrative is longer than 5000 characters. Shorten it and save again.' ),
) );
// The sentence that names the box is the one the Edit form's refusals print, built once: the
// report screen asks `WPCPM_Sponsors::long_sentence()` with the narrative limit and words nothing
// of its own.
$sr_builder = is_callable( array( 'WPCPM_Sponsors', 'long_sentence' ) );
WPCPM_Flash::new_request();
WPCPM_Flash::set( WPCPM_Sponsor_Report_Screen::FLASH_LONG, 'The cost line' );
$sr_printed = WPCPM_Sponsor_Report_Screen::sentence_for( 'sreport-too-long', 'A sentence that names no box.' );
WPCPM_Flash::new_request();
ck( 'the sentence a refused narrative prints is the builder\'s that the Edit form\'s refusals use, and the report screen words none of its own', array(
	$sr_builder,
	$sr_builder ? WPCPM_Sponsors::long_sentence( 'The cost line', WPCPM_Sponsor_Report::MAX_TEXT ) : '',
	$sr_printed,
	substr_count( method_body( $reports_src, 'sentence_for' ), 'is longer than' ),
), array(
	true,
	'Nothing was saved: "The cost line" is longer than 5000 characters. Shorten it and save again.',
	'Nothing was saved: "The cost line" is longer than 5000 characters. Shorten it and save again.',
	0,
) );
// A narrative the cleaner would take words from, a tag-like "<" or a percent octet, is refused before
// anything is written, the notice naming the box, and every narrative box of the report comes back
// once as it was typed, for the one manager and the one report it was typed into. A box posted back
// as drawn is not asked, and keeps its stored text. A Save refused for a narrative's length brings
// the boxes back too, and a clean Save is as before.

/** What a report's editor posts from its Save form as drawn, every narrative box as its box shows it. */
function sr_posts_of( $html ) {
	return posts_as_drawn( form_for( editor_of( $html ), 'wpcpm_sreport_save' ) );
}
/** A report's editor, drawn on the Reports tab, as a browser would post its Save form untouched. */
function sr_posts( $post_id ) {
	$html           = render_tab( 'reports', array( 'wpcpm_sreport' => $post_id ) );
	$GLOBALS['get'] = array();
	return sr_posts_of( $html );
}

$sr_drawn = sr_posts( $sr_a26_id );
$sr_other = sr_posts( $sr_p26_id );
$sr_typed = array_merge(
	$sr_drawn,
	array(
		'wpcpm_sreport_text_cost_line' => "Costs <25 \"audited\" & signed,\r\nadults >18 pay",
		'wpcpm_sreport_text_ahead'     => "A change beside it,\r\nwhich must not land.",
	)
);
ksort( $sr_typed );
$sr_meta = $GLOBALS['pmeta'][ $sr_a26_id ];
$r_loss  = post( $sr_typed, array( $S, 'handle_save' ) );
ck( 'a narrative the cleaner would take words from refuses the whole Save before anything is written, the other box\'s change with it', array(
	$r_loss,
	$GLOBALS['pmeta'][ $sr_a26_id ] === $sr_meta,
), array( array( 'redirect', $editor_at . $sr_a26_id, 'sreport-loss' ), true ) );
$sr_other_after = sr_posts( $sr_p26_id );
$GLOBALS['uid'] = 2;
$sr_theirs      = sr_posts( $sr_a26_id );
$GLOBALS['uid'] = 1;
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_loss[2] ) ? (string) $r_loss[2] : '' );
$sr_page        = render_tab( 'reports', array( 'wpcpm_sreport' => $sr_a26_id ) );
$GLOBALS['get'] = array();
ck( 'the editor comes back under the sentence that names the box, with every narrative box as it was typed', array(
	notices_in( $sr_page ),
	post_diff( $sr_typed, sr_posts_of( $sr_page ) ),
), array(
	'<div class="notice notice-error is-dismissible"><p>' . esc_html( WPCPM_Typed_Text::loss_message( 'The cost line' ) ) . '</p></div>',
	array(),
) );
ck( 'another report\'s editor and another manager\'s view of this one never see the typing, and drawn again the boxes hold what is stored', array(
	post_diff( $sr_other, $sr_other_after ),
	post_diff( $sr_drawn, $sr_theirs ),
	post_diff( $sr_drawn, sr_posts( $sr_a26_id ) ),
	flash_left(),
), array( array(), array(), array(), array() ) );
// A track's box is named as the editor heads it, with the track's name.
$r_track_loss = post( array_merge( $sr_other, array( 'wpcpm_sreport_text_track_150h' => 'Mentors <Sales> team' ) ), array( $S, 'handle_save' ) );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_track_loss[2] ) ? (string) $r_track_loss[2] : '' );
ck( 'a track\'s narrative the cleaner would take words from is named by the track, and without the box carried beside it the notice still says what to do', array(
	isset( $r_track_loss[2] ) ? $r_track_loss[2] : $r_track_loss,
	notices_in( render_tab( 'reports', array( 'wpcpm_sreport' => $sr_p26_id ) ) ),
	isset( WPCPM_Sponsors::messages()['sreport-loss'] ) ? WPCPM_Sponsors::messages()['sreport-loss'] : array(),
), array(
	'sreport-loss',
	'<div class="notice notice-error is-dismissible"><p>' . esc_html( WPCPM_Typed_Text::loss_message( 'The tracks: WordPress Credits Program 150h' ) ) . '</p></div>',
	array( 'error', 'Nothing was saved, because WordPress would remove part of what you typed in a narrative. To keep every word, put a space after each "<" (or write "less than") and after each "%", then save again.' ),
) );
$GLOBALS['get'] = array();

// A narrative drawn holding what the cleaner would take, as no save of this editor stores but an
// older store could, over its limit too: posted back as drawn it is not asked, and keeps its stored
// text, so the other box's change is saved.
$sr_octet = "Get 10%cashback, as an older store kept it.\n" . str_repeat( 'w', 5000 );
$GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_SECTIONS ]['cost_line'] = $sr_octet;
$r_held = post( array_merge( sr_posts( $sr_a26_id ), array( 'wpcpm_sreport_text_ahead' => 'Saved beside an octet.' ) ), array( $S, 'handle_save' ) );
$sr_now = of_report( $sr_a26_id, 'narratives' );
ck( 'a narrative drawn holding a percent octet the cleaner would take, over its limit too, is not asked when posted back as drawn: it keeps its stored text, and the other box is saved', array(
	isset( $r_held[2] ) ? $r_held[2] : $r_held,
	is_array( $sr_now ) ? $sr_now['cost_line'] === $sr_octet : $sr_now,
	is_array( $sr_now ) ? $sr_now['ahead'] : $sr_now,
), array( 'sreport-saved', true, 'Saved beside an octet.' ) );

// A narrative an older store kept over its limit, edited at the same length: refused for its length
// as before, and every box comes back as it was typed, once.
$GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_SECTIONS ]['cost_line'] = str_repeat( 'w', 5040 );
$sr_drawn = sr_posts( $sr_a26_id );
$sr_typed = array_merge(
	$sr_drawn,
	array(
		'wpcpm_sreport_text_cost_line' => 'v' . str_repeat( 'w', 5039 ),
		'wpcpm_sreport_text_ahead'     => "Another change,\r\nkept with it.",
	)
);
ksort( $sr_typed );
$r_long = post( $sr_typed, array( $S, 'handle_save' ) );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_long[2] ) ? (string) $r_long[2] : '' );
ck( 'a Save refused for a narrative\'s length brings every box back as it was typed too, once', array(
	isset( $r_long[2] ) ? $r_long[2] : $r_long,
	post_diff( $sr_typed, sr_posts( $sr_a26_id ) ),
	post_diff( $sr_drawn, sr_posts( $sr_a26_id ) ),
), array( 'sreport-too-long', array(), array() ) );

// With a space after each "<", the cleaner keeps every word, and the Save goes through as before: the
// box comes back exactly as typed. A refusal whose page was never drawn leaves nothing behind.
$r_unseen  = post( array_merge( $sr_drawn, array( 'wpcpm_sreport_text_cost_line' => 'Costs <25, adults >18, never drawn.' ) ), array( $S, 'handle_save' ) );
$sr_spaced = "Costs < 25 \"audited\" & signed,\r\nadults > 18 pay";
$r_spaced  = post( array_merge( $sr_drawn, array( 'wpcpm_sreport_text_cost_line' => $sr_spaced ) ), array( $S, 'handle_save' ) );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_spaced[2] ) ? (string) $r_spaced[2] : '' );
$sr_page        = render_tab( 'reports', array( 'wpcpm_sreport' => $sr_a26_id ) );
$GLOBALS['get'] = array();
$sr_now         = of_report( $sr_a26_id, 'narratives' );
ck( 'with a space after each "<" the narrative is saved with every word and comes back exactly as typed; the refusal before it, never drawn, left nothing', array(
	isset( $r_unseen[2] ) ? $r_unseen[2] : $r_unseen,
	isset( $r_spaced[2] ) ? $r_spaced[2] : $r_spaced,
	is_array( $sr_now ) ? $sr_now['cost_line'] : $sr_now,
	notices_in( $sr_page ),
	post_diff( array_merge( $sr_drawn, array( 'wpcpm_sreport_text_cost_line' => $sr_spaced ) ), sr_posts_of( $sr_page ) ),
	flash_left(),
), array(
	'sreport-loss',
	'sreport-saved',
	str_replace( "\r\n", "\n", trim( sanitize_textarea_field( $sr_spaced ) ) ),
	'<div class="notice notice-success is-dismissible"><p>The narratives are saved.</p></div>',
	array(),
	array(),
) );

// Back to the words the checks after these were written against.
post( array( 'wpcpm_sreport_post' => $sr_a26_id, 'wpcpm_sreport_text_cost_line' => $sr_words['cost_line'], 'wpcpm_sreport_text_ahead' => $sr_words['ahead'] ), array( $S, 'handle_save' ) );

$GLOBALS['nonce_checked'] = array();
$audit_mark               = count( $GLOBALS['audit'] );
$r                        = post( array( 'wpcpm_sreport_post' => $sr_a26_id ), array( $S, 'handle_final' ) );
$stamp                    = of_report( $sr_a26_id, 'final_at' );
ck( 'Mark final makes the report final and stamps who marked it, under the nonce keyed to the report, audited as final', array(
	$r,
	end( $GLOBALS['nonce_checked'] ),
	of_report( $sr_a26_id, 'state' ),
	isset( $stamp['by'] ) ? $stamp['by'] : 0,
	$kinds_of( array_slice( $GLOBALS['audit'], $audit_mark ) ),
), array( array( 'redirect', $editor_at . $sr_a26_id, 'sreport-final' ), 'wpcpm_sreport_final_' . $sr_a26_id, 'final', 1, array( array( 'sponsor_report_final', 'manager' ) ) ) );
$landings['Mark final'] = $r;
// A known moment, so the stamp can be read back whole: 14 October 2026, 9:30 in the morning, UTC.
$GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_FINAL ]['at'] = gmmktime( 9, 30, 0, 10, 14, 2026 );
$editor = editor_of( render_tab( 'reports', array( 'wpcpm_sreport' => $sr_a26_id ) ) );
ck( 'a final report\'s editor: who marked it final and when, the narratives read-only with nothing to save, Reopen in place of Mark final, no Regenerate, and the PDF still to attach', array(
	false !== strpos( $editor, '<p class="wpcpm-sreport__facts">The twelve months from October 1, 2025 to September 30, 2026. Final: marked final on October 14, 2026 at 9:30 am by Maciej (Matt) Pilarski.</p>' ),
	substr_count( $editor, 'readonly="readonly"' ),
	false !== strpos( $editor, '<textarea id="wpcpm-sreport-text-cost_line" rows="8" class="large-text" readonly="readonly">Two mentors&#039; stipends.' ),
	strpos( $editor, 'name="wpcpm_sreport_text_' ),
	strpos( $editor, 'value="wpcpm_sreport_save"' ),
	false !== strpos( $editor, '<p class="description">The report is final, so its narratives are read-only: reopen it to change them.</p>' ),
	false !== strpos( form_for( $editor, 'wpcpm_sreport_reopen' ), 'nonce-wpcpm_sreport_reopen_' . $sr_a26_id ) && false !== strpos( form_for( $editor, 'wpcpm_sreport_reopen' ), '>Reopen</button>' ),
	strpos( $editor, 'value="wpcpm_sreport_final"' ),
	strpos( $editor, 'value="wpcpm_sreport_regenerate"' ),
	'' !== form_for( $editor, 'wpcpm_sreport_upload' ),
), array( true, 2, true, false, false, true, true, false, false, true ) );
$audit_mark = count( $GLOBALS['audit'] );
$final_refusals = array(
	post( array( 'wpcpm_sreport_post' => $sr_a26_id, 'wpcpm_sreport_text_ahead' => 'Too late.' ), array( $S, 'handle_save' ) ),
	post( array( 'wpcpm_sreport_post' => $sr_a26_id ), array( $S, 'handle_final' ) ),
	post( array( 'wpcpm_sreport_post' => $sr_a26_id ), array( $S, 'handle_regenerate' ) ),
);
ck( 'a final report is not saved, marked final again or generated again until it is reopened, and each says so', array(
	array_map( static function ( $r ) { return isset( $r[2] ) ? $r[2] : $r; }, $final_refusals ),
	of_report( $sr_a26_id, 'narratives' ),
	count( $GLOBALS['audit'] ) - $audit_mark,
), array( array( 'sreport-save-final', 'sreport-final-already', 'sreport-regenerate-final' ), $sr_words, 0 ) );

$GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_FILE ]      = array( 'path' => '2026/' . str_repeat( 'b', 32 ) . '.pdf', 'sha256' => str_repeat( 'c', 64 ), 'size' => 2048 );
$GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_FILE_NAME ] = 'backer-report.pdf';
$editor       = editor_of( render_tab( 'reports', array( 'wpcpm_sreport' => $sr_a26_id ) ) );
$remove_form  = form_for( $editor, 'wpcpm_sreport_remove' );
$backer_block = report_block( reports_card( render_tab( 'reports' ) ), 'Report Backer Example' );
ck( 'with a PDF attached the editor names it for the manager and offers it to download, to replace and to remove, and the card says so beside the state', array(
	false !== strpos( $editor, '<p>A PDF is attached: backer-report.pdf, 2048 B. <a href="' . of_report( $sr_a26_id, 'download_url' ) . '">Download it</a></p>' ),
	false !== strpos( form_for( $editor, 'wpcpm_sreport_upload' ), '>Replace the PDF</button>' ),
	false !== strpos( $remove_form, 'nonce-wpcpm_sreport_remove_' . $sr_a26_id ) && false !== strpos( $remove_form, '<input type="hidden" name="wpcpm_sreport_post" value="' . $sr_a26_id . '" />' ) && false !== strpos( $remove_form, '<button type="submit" class="button-link-delete" data-wpcpm-confirm="Remove the PDF from this report? The file is deleted from this site.">Remove the PDF</button>' ),
	false !== strpos( $backer_block, '<span class="description">Final since October 14, 2026, with a PDF attached</span>' ),
), array( true, true, true, true ) );

$GLOBALS['nonce_checked'] = array();
$audit_mark               = count( $GLOBALS['audit'] );
$r                        = post( array( 'wpcpm_sreport_post' => $sr_a26_id ), array( $S, 'handle_reopen' ) );
ck( 'Reopen makes the report a draft again, the stamp gone, under the nonce keyed to the report, audited as reopened', array(
	$r,
	end( $GLOBALS['nonce_checked'] ),
	of_report( $sr_a26_id, 'state' ),
	of_report( $sr_a26_id, 'final_at' ),
	$kinds_of( array_slice( $GLOBALS['audit'], $audit_mark ) ),
), array( array( 'redirect', $editor_at . $sr_a26_id, 'sreport-reopened' ), 'wpcpm_sreport_reopen_' . $sr_a26_id, 'draft', array(), array( array( 'sponsor_report_reopened', 'manager' ) ) ) );
$landings['Reopen'] = $r;
$r                  = post( array( 'wpcpm_sreport_post' => $sr_a26_id ), array( $S, 'handle_reopen' ) );
ck( 'a draft is not reopened, and says so', isset( $r[2] ) ? $r[2] : $r, 'sreport-draft-already' );

$GLOBALS['nonce_checked'] = array();
$r                        = post( array( 'wpcpm_sreport_post' => $sr_a26_id ), array( $S, 'handle_regenerate' ) );
$logged                   = last_audit();
ck( 'Regenerate reads the records again into the same report, keeps the narratives, and says so on the trail', array(
	$r,
	end( $GLOBALS['nonce_checked'] ),
	of_report( $sr_a26_id, 'narratives' ),
	array( $logged['kind'], $logged['subject'], $logged['data'] ),
), array( array( 'redirect', $editor_at . $sr_a26_id, 'sreport-regenerated' ), 'wpcpm_sreport_regenerate_' . $sr_a26_id, $sr_words, array( 'sponsor_report_drafted', (string) $sr_a26_id, array( 'until' => '2026-09-30', 'regenerated' => 1 ) ) ) );
$landings['Regenerate'] = $r;
WPCPM_Sponsors_Index::patch( $sr_backer, array( 'tier' => 'mentor' ) );
$r = post( array( 'wpcpm_sreport_post' => $sr_a26_id ), array( $S, 'handle_regenerate' ) );
WPCPM_Sponsors_Index::patch( $sr_backer, array( 'tier' => 'backer' ) );
ck( 'a report whose own term the row now says was a Friend\'s is not generated again, and says why', isset( $r[2] ) ? $r[2] : $r, 'sreport-regenerate-refused' );

$stored_snapshot = isset( $GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_DATA ] ) ? $GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_DATA ] : array();
$GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_DATA ]['v'] = 99;
$editor = editor_of( render_tab( 'reports', array( 'wpcpm_sreport' => $sr_a26_id ) ) );
$r      = post( array( 'wpcpm_sreport_post' => $sr_a26_id, 'wpcpm_sreport_text_ahead' => 'Not while it cannot be read.' ), array( $S, 'handle_save' ) );
// A Mark final form drawn before the figures went unreadable, pressed now.
$audit_mark  = count( $GLOBALS['audit'] );
$r_final     = post( array( 'wpcpm_sreport_post' => $sr_a26_id ), array( $S, 'handle_final' ) );
$final_state = of_report( $sr_a26_id, 'state' );
$final_rows  = count( $GLOBALS['audit'] ) - $audit_mark;
$GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_STATE ] = 'final';
$final_unreadable = editor_of( render_tab( 'reports', array( 'wpcpm_sreport' => $sr_a26_id ) ) );
$GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_STATE ] = 'draft';
$GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_DATA ]  = $stored_snapshot;
ck( 'a report whose figures cannot be read says so and draws no table and no box, offers Regenerate and not Mark final, and takes no narrative', array(
	false !== strpos( $editor, '<div class="notice notice-warning inline"><p>This report&#039;s figures cannot be read, so its sections are not shown. Regenerate reads them again for the same twelve months, and the narratives are kept.</p></div>' ),
	strpos( $editor, '<table' ),
	strpos( $editor, '<textarea' ),
	strpos( $editor, 'value="wpcpm_sreport_final"' ),
	'' !== form_for( $editor, 'wpcpm_sreport_regenerate' ),
	isset( $r[2] ) ? $r[2] : $r,
	of_report( $sr_a26_id, 'narratives' ),
), array( true, false, false, false, true, 'sreport-unreadable', $sr_words ) );
ck( 'and Mark final refuses it, whatever form was pressed, since the sponsor would read a report of nothing; nor is Mark final\'s sentence drawn without its button', array( isset( $r_final[2] ) ? $r_final[2] : $r_final, $final_state, $final_rows, strpos( $editor, 'Marked final, the report is' ) ), array( 'sreport-final-unreadable', 'draft', 0, false ) );
ck( 'and a final one that cannot be read is reopened first', array( false !== strpos( $final_unreadable, '<div class="notice notice-warning inline"><p>This report&#039;s figures cannot be read, so its sections are not shown. Reopen it, and Regenerate reads them again for the same twelve months; the narratives are kept.</p></div>' ), '' !== form_for( $final_unreadable, 'wpcpm_sreport_reopen' ), strpos( $final_unreadable, 'value="wpcpm_sreport_regenerate"' ) ), array( true, true, false ) );

echo "\n--- What the handlers refuse ---\n";
$plain_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Not a report' ) );
if ( isset( $GLOBALS['posts'][ $sr_a25_id ] ) ) {
	$GLOBALS['posts'][ $sr_a25_id ]->post_status = 'trash';
}
$outcomes = array();
foreach ( array( 'handle_save', 'handle_final', 'handle_reopen', 'handle_regenerate' ) as $handler ) {
	foreach ( array( 0, $plain_post, $sr_a25_id ) as $not_a_report ) {
		$outcomes[] = post( array( 'wpcpm_sreport_post' => $not_a_report ), array( $S, $handler ) );
	}
}
if ( isset( $GLOBALS['posts'][ $sr_a25_id ] ) ) {
	$GLOBALS['posts'][ $sr_a25_id ]->post_status = 'private';
}
ck( 'each of the editor\'s four forms naming no report, a post that is not one, or a report in the trash goes back to the Sponsors screen, saying so', $outcomes, array_fill( 0, 12, array( 'redirect', $card_url, 'sreport-gone' ) ) );
$landings['a press naming no report'] = $outcomes[0];

$GLOBALS['uid']           = 9;
$GLOBALS['nonce_checked'] = array();
$refused                  = array();
foreach ( $handlers as $handler ) {
	$r         = post( array( 'wpcpm_sreport_post' => $sr_a26_id, 'wpcpm_record' => $sr_backer, 'wpcpm_until' => '2025-06-30' ), array( $S, $handler ) );
	$refused[] = 'die' === $r[0] && false !== strpos( $r[1], '[403]' );
}
$GLOBALS['uid'] = 1;
ck( 'each of the six refuses an account without the program\'s capability, before a nonce is read', array( $refused, $GLOBALS['nonce_checked'] ), array( array_fill( 0, 6, true ), array() ) );
// What a stale press must leave as it found it, read before the presses: a handler that writes
// before its nonce still dies, and still asks for the right nonce, so only this can tell.
$stale_before             = array( of_report( $sr_a26_id, 'state' ), of_report( $sr_a26_id, 'narratives' ), WPCPM_Sponsor_Report::snapshot( get_post( $sr_a26_id ) ), count( $GLOBALS['audit'] ), WPCPM_Sponsor_Report::owed_of( (array) WPCPM_Sponsors_Index::row( $sr_owed ) ) );
$GLOBALS['nonce_ok']      = false;
$GLOBALS['nonce_checked'] = array();
$stale                    = array();
foreach ( $handlers as $handler ) {
	// Not a renewal's press names the sponsor that owes a term, so a clear made before its nonce shows.
	$r       = post( array( 'wpcpm_sreport_post' => $sr_a26_id, 'wpcpm_record' => 'handle_not_renewal' === $handler ? $sr_owed : $sr_backer, 'wpcpm_until' => '2025-06-30', 'wpcpm_sreport_text_ahead' => 'Forged.' ), array( $S, $handler ) );
	$stale[] = $r[0];
}
$GLOBALS['nonce_ok'] = true;
ck( 'and a stale form, the nonce keyed to the sponsor or the report it names', array( $stale, $GLOBALS['nonce_checked'], WPCPM_Sponsor_Report::find( $sr_backer, '2025-06-30' ) ), array(
	array_fill( 0, 6, 'die' ),
	array( 'wpcpm_sreport_draft_' . $sr_backer, 'wpcpm_sreport_save_' . $sr_a26_id, 'wpcpm_sreport_final_' . $sr_a26_id, 'wpcpm_sreport_reopen_' . $sr_a26_id, 'wpcpm_sreport_regenerate_' . $sr_a26_id, 'wpcpm_sreport_not_renewal_' . $sr_owed ),
	null,
) );
// Reopen has nothing to undo on a draft, so its stale press is made against the report while it is final.
$GLOBALS['nonce_ok']                                                 = false;
$GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_STATE ] = 'final';
post( array( 'wpcpm_sreport_post' => $sr_a26_id ), array( $S, 'handle_reopen' ) );
$stale_reopened                                                      = of_report( $sr_a26_id, 'state' );
$GLOBALS['pmeta'][ $sr_a26_id ][ WPCPM_Sponsor_Report::META_STATE ] = 'draft';
$GLOBALS['nonce_ok']                                                 = true;
ck( 'a stale form changes nothing: no state move, no narrative, no fresh snapshot, no row on the trail, no owed term cleared', array( of_report( $sr_a26_id, 'state' ), of_report( $sr_a26_id, 'narratives' ), WPCPM_Sponsor_Report::snapshot( get_post( $sr_a26_id ) ), count( $GLOBALS['audit'] ), WPCPM_Sponsor_Report::owed_of( (array) WPCPM_Sponsors_Index::row( $sr_owed ) ), $stale_reopened ), array_merge( $stale_before, array( 'final' ) ) );
// Nor is anything looked up first: a stale form naming no report, or no sponsor, meets the expired
// link, never a sentence that would tell a report or a sponsor from none.
$GLOBALS['nonce_ok'] = false;
$first               = array();
foreach ( array( 'handle_save', 'handle_final', 'handle_reopen', 'handle_regenerate' ) as $handler ) {
	$r       = post( array( 'wpcpm_sreport_post' => 0 ), array( $S, $handler ) );
	$first[] = $r[0];
}
$r                   = post( array( 'wpcpm_record' => 'spoZZZZ0000000078' ), array( $S, 'handle_not_renewal' ) );
$first[]             = $r[0];
$GLOBALS['nonce_ok'] = true;
ck( 'and the nonce is read before anything is looked up: a stale form naming no report or no sponsor dies on it', $first, array_fill( 0, 5, 'die' ) );
$html = render_tab( 'reports', array( 'wpcpm_sreport' => $plain_post ) );
ck( 'an address naming a post that is not a report draws the screen\'s cards under a notice that says so', array(
	false !== strpos( $html, '<div class="notice notice-warning"><p>That report is not on this site anymore. Every Approved sponsor&#039;s reports are listed on the Reports card below.</p></div>' ),
	false !== strpos( $html, 'id="wpcpm-sponsor-reports"' ),
	strpos( $html, 'id="wpcpm-sreport-editor"' ),
), array( true, true, false ) );
// Whatever the address names that is no report: an ID no post has, a post that is not a report, a
// report in the trash. Each is the Reports tab's notice, right under the bar, then its card; an
// address whose report is no number at all names nothing, and draws the card with no notice.
$no_report_said = '</nav><div class="notice notice-warning"><p>That report is not on this site anymore. Every Approved sponsor&#039;s reports are listed on the Reports card below.</p></div><div class="wpcpm-card" id="wpcpm-sponsor-reports"><h2>Reports</h2>';
$no_report      = array();
if ( isset( $GLOBALS['posts'][ $sr_a25_id ] ) ) {
	$GLOBALS['posts'][ $sr_a25_id ]->post_status = 'trash';
}
foreach ( array( 'no post' => 987654, 'not a report' => $plain_post, 'in the trash' => $sr_a25_id, 'no number' => 'none' ) as $named => $asked ) {
	$drawn               = render_tab( 'reports', array( 'wpcpm_sreport' => $asked ) );
	$no_report[ $named ] = array( false !== strpos( $drawn, $no_report_said ), false !== strpos( $drawn, '</nav><div class="wpcpm-card" id="wpcpm-sponsor-reports"><h2>Reports</h2>' ), strpos( $drawn, 'id="wpcpm-sreport-editor"' ), cards_of( $drawn ) );
}
if ( isset( $GLOBALS['posts'][ $sr_a25_id ] ) ) {
	$GLOBALS['posts'][ $sr_a25_id ]->post_status = 'private';
}
ck( 'an address naming no report, an ID no post has, a post that is not a report or a report in the trash, draws the notice right under the bar and the Reports card under it, and no editor; one whose report is no number draws the card alone', $no_report, array(
	'no post'      => array( true, false, false, array( 'Reports' ) ),
	'not a report' => array( true, false, false, array( 'Reports' ) ),
	'in the trash' => array( true, false, false, array( 'Reports' ) ),
	'no number'    => array( false, true, false, array( 'Reports' ) ),
) );

echo "\n--- The Tier box: Report due and Not a renewal ---\n";
$html      = render_tab( 'sponsors', array( 'edit' => $sr_owed ) );
$owed_card = substr( $html, (int) strpos( $html, 'id="wpcpm-sponsor-edit"' ) );
ck( 'while a term is owed its report the Tier box says so under Term ends, with Not a renewal, whose form is drawn after the Edit form: a form cannot sit inside another', array(
	false !== strpos( $owed_card, '<tr><th scope="row">Report due</th><td><p>A report for the term to September 30, 2026 is still due.</p><p><button type="submit" form="wpcpm-sreport-not-renewal" class="button">Not a renewal</button></p><p class="description">' ),
	false !== strpos( $owed_card, '>Not a renewal</button></p><p class="description">' ),
	before( $owed_card, 'name="wpcpm_tier_until"', '>Report due<' ),
	before( $owed_card, '>Report due<', 'name="wpcpm_funded_line"' ),
	before( $owed_card, '</form>', 'id="wpcpm-sreport-not-renewal"' ),
	false !== strpos( $owed_card, '<form method="post" action="https://example.test/wp-admin/admin-post.php" id="wpcpm-sreport-not-renewal" data-wpcpm-once data-wpcpm-busy="Saving" data-wpcpm-confirm="Say that no report is owed for that term? Do this only when its last day was moved to correct it, not to renew, since that term then gets no report."><input type="hidden" name="_wpnonce" value="nonce-wpcpm_sreport_not_renewal_' . $sr_owed . '" />' . $referer_on( array( 'tab' => 'sponsors', 'edit' => $sr_owed ) ) . '<input type="hidden" name="action" value="wpcpm_sreport_not_renewal" /><input type="hidden" name="wpcpm_record" value="' . $sr_owed . '" /></form>' ),
	substr_count( $html, 'id="wpcpm-sreport-not-renewal"' ),
), array( true, true, true, true, true, true, 1 ) );
// The button sits in the Edit form and posts its own form from outside it, so the question is on that
// form, as the offer's Void form carries its own: forms.js asks it whatever the browser says was pressed.
ck( 'Not a renewal asks before it posts, its question a data-wpcpm-confirm on the form it posts, once, and no inline handler on its button', array(
	substr_count( $html, 'data-wpcpm-confirm="Say that no report is owed for that term? Do this only when its last day was moved to correct it, not to renew, since that term then gets no report."' ),
	strpos( $owed_card, 'onclick=' ),
), array( 1, false ) );
// The Backer's own Edit form, drawn: it owes no term, so its Tier box has neither.
$b_html = render_tab( 'sponsors', array( 'edit' => $sr_backer ) );
ck( 'a sponsor owed nothing draws neither the row nor its form', array( false !== strpos( $b_html, '</nav><div class="wpcpm-card" id="wpcpm-sponsor-edit"><h2>Edit Report Backer Example</h2>' ), strpos( $b_html, '>Report due<' ), strpos( $b_html, 'wpcpm-sreport-not-renewal' ) ), array( true, false, false ) );
// A clear the store refuses: another request trashes the sponsor between this press's read of the
// row, which the warm index answers, and its write, which patch() refuses for a post not private.
$owed_post = WPCPM_Sponsors_Index::post_id_of( $sr_owed );
WPCPM_Sponsors_Index::rows();
$GLOBALS['posts'][ $owed_post ]->post_status = 'trash';
$audit_mark                                  = count( $GLOBALS['audit'] );
$r_refused                                   = post( array( 'wpcpm_record' => $sr_owed ), array( $S, 'handle_not_renewal' ) );
$GLOBALS['posts'][ $owed_post ]->post_status = 'private';
WPCPM_Sponsors_Index::forget();
ck( 'a clear the store refuses says nothing changed, writes no row, and the term stays owed', array( $r_refused, count( $GLOBALS['audit'] ) - $audit_mark, WPCPM_Sponsor_Report::owed_of( (array) WPCPM_Sponsors_Index::row( $sr_owed ) ) ), array( array( 'redirect', $edit_view . $sr_owed, 'sreport-owed-not-saved' ), 0, array( 'tier' => 'backer', 'until' => '2026-09-30', 'funded_line' => 'Stipends for two mentors' ) ) );
$GLOBALS['nonce_checked'] = array();
$audit_mark               = count( $GLOBALS['audit'] );
$r                        = post( array( 'wpcpm_record' => $sr_owed ), array( $S, 'handle_not_renewal' ) );
$logged                   = last_audit();
$owed_after               = ( (array) WPCPM_Sponsors_Index::row( $sr_owed ) + WPCPM_Sponsors_Index::empty_row() )['report_owed'];
// The Edit form the press goes back to, its status flashed again as leave() flashed it: post() took
// it, and the screen takes it once more to draw the notice.
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'redirect' === $r[0] ? $r[2] : '' );
$cleared_html              = render_tab( 'sponsors', array( 'edit' => $sr_owed ) );
$landings['Not a renewal'] = $r;
ck( 'Not a renewal clears the owed term under the nonce keyed to the sponsor, on the trail on the manager\'s ground against the index, and goes back to the Edit form saying it is done: the term to its last day, in the Tier box\'s format, was a corrected day, not a renewal, so no report is owed for it', array(
	$r,
	end( $GLOBALS['nonce_checked'] ),
	$owed_after,
	count( $GLOBALS['audit'] ) - $audit_mark,
	array( $logged['kind'], $logged['sponsor'], $logged['subject'], $logged['actor'], $logged['ground'], $logged['evidence'], $logged['data'] ),
	false !== strpos( $cleared_html, '<div class="notice notice-success is-dismissible"><p>Done: the term to September 30, 2026 was a corrected day, not a renewal, so no report is owed for it.</p></div>' ),
	substr_count( $cleared_html, 'class="notice ' ),
), array(
	array( 'redirect', $edit_view . $sr_owed, 'sreport-owed-cleared' ),
	'wpcpm_sreport_not_renewal_' . $sr_owed,
	array(),
	1,
	array( 'sponsor_report_owed_cleared', $sr_owed, $sr_owed, 1, 'manager', 'index', array( 'until' => '2026-09-30', 'tier' => 'backer' ) ),
	true,
	1,
) );
ck( 'the day travels beside the status for that one notice and is taken with it; without it, as on a page that is not the Sponsors screen, the status still says it is done, naming no day', array(
	flash_left(),
	isset( WPCPM_Sponsors::messages()['sreport-owed-cleared'] ) ? WPCPM_Sponsors::messages()['sreport-owed-cleared'] : array(),
), array(
	array(),
	array( 'success', 'Done: that term was a corrected day, not a renewal, so no report is owed for it.' ),
) );
$audit_mark = count( $GLOBALS['audit'] );
$again      = post( array( 'wpcpm_record' => $sr_owed ), array( $S, 'handle_not_renewal' ) );
$nobody     = post( array( 'wpcpm_record' => 'spoZZZZ0000000078' ), array( $S, 'handle_not_renewal' ) );
ck( 'a second press finds nothing owed, a sponsor the store does not hold is refused, and neither writes anything', array( $again, $nobody, count( $GLOBALS['audit'] ) - $audit_mark ), array( array( 'redirect', $edit_view . $sr_owed, 'sreport-owed-none' ), array( 'redirect', $screen_at . '&tab=sponsors', 'sreport-owed-gone' ), 0 ) );
$landings['Not a renewal, for a sponsor gone'] = $nobody;
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'redirect' === $again[0] ? $again[2] : '' );
$again_html = render_tab( 'sponsors', array( 'edit' => $sr_owed ) );
ck( 'a press with nothing to clear keeps a sentence of its own, and names no day', array( false !== strpos( $again_html, '<div class="notice notice-info is-dismissible"><p>Nothing changed. No term of that sponsor was waiting for its report.</p></div>' ), strpos( $again_html, 'Done:' ), flash_left() ), array( true, false, array() ) );
// A day left behind, as a redirect nobody followed would leave one: the next notice takes it,
// whatever its status, so it never names a day behind another notice or behind a later clear.
WPCPM_Flash::set( defined( $S . '::FLASH_CLEARED' ) ? constant( $S . '::FLASH_CLEARED' ) : 'no channel of the class', '2026-09-30' );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'sponsor-saved' );
$left_html  = render_tab( 'sponsors', array( 'edit' => $sr_owed ) );
$left_flash = flash_left();
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, 'sreport-owed-cleared' );
$later_html = render_tab( 'sponsors', array( 'edit' => $sr_owed ) );
preg_match_all( '#<div class="notice notice-[a-z]+ is-dismissible"><p>(.*?)</p></div>#', $left_html, $left_notices );
preg_match_all( '#<div class="notice notice-[a-z]+ is-dismissible"><p>(.*?)</p></div>#', $later_html, $later_notices );
ck( 'a day left behind is taken by the next notice, whatever its status, which keeps its own sentence; a later clear that carried no day names none', array( $left_notices[1], $left_flash, $later_notices[1] ), array( array( 'The sponsor was saved.' ), array(), array( 'Done: that term was a corrected day, not a renewal, so no report is owed for it.' ) ) );
$owed_html  = render_tab( 'sponsors', array( 'edit' => $sr_owed ) );
$owed_block = report_block( reports_card( render_tab( 'reports' ) ), 'Report Renewed Example' );
ck( 'once it is cleared the Tier box has no row, and the card no sentence, its form back to the row\'s own term', array( strpos( $owed_html, '>Report due<' ), strpos( $owed_block, 'still due' ), false !== strpos( $owed_block, 'name="wpcpm_until" value="2027-09-30"' ) ), array( false, false, true ) );

// The Edit form's address has one builder, the Sponsors class's, public and static so a class with
// no Sponsors module to hand can call it: Not a renewal's landing here and the daily run's mail about
// a term that ends. Neither writes the address itself any longer.
$edit_url  = new ReflectionMethod( 'WPCPM_Sponsors', 'edit_url' );
$run_src   = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsor-tiers-run.php' );
$edit_said = $edit_url->isPublic() && $edit_url->isStatic() ? array( WPCPM_Sponsors::edit_url( $sr_owed ), WPCPM_Sponsors::edit_url( 'spo a&b #1' ) ) : array();
ck( 'a sponsor\'s Edit form is the Sponsors tab with the sponsor\'s key in the address, encoded as a query value, built by one public method that Not a renewal and the daily run\'s mail both call', array(
	$edit_url->isPublic() && $edit_url->isStatic(),
	$edit_said,
	substr_count( method_body( $reports_src, 'handle_not_renewal' ), 'WPCPM_Sponsors::edit_url( $record )' ),
	substr_count( method_body( $run_src, 'mail_term_end' ), 'WPCPM_Sponsors::edit_url( $record )' ),
	array( strpos( $reports_src, 'WPCPM_Sponsors::ARG_EDIT' ), strpos( $run_src, 'WPCPM_Sponsors::ARG_EDIT' ) ),
), array( true, array( $edit_view . $sr_owed, $edit_view . 'spo%20a%26b%20%231' ), 1, 1, array( false, false ) ) );

// Neither the Edit form's address nor the editor's is written anywhere but by its builder: in the
// code under includes/, comments left out since they describe the addresses, nothing outside
// edit_url() and editor_url() names either argument, by its constant, as written in an address or
// as a key handed to add_query_arg(), but the constants' own definitions and the reads of the
// request. The editor's argument is caught by its name in any shape; the Edit form's, `edit`, a
// word other code uses too, as a key of a map whatever its value but markup (a row action named
// Edit, as the accounts list's and the Engagement tab's are, writes no address), as a key an array
// is given a value under whatever its value but markup, or as add_query_arg()'s first argument.
// The Emails tool's editor_url() (1.122.19) builds its own screen's address, whose `edit` names a
// template on the Emails screen and not a sponsor, so its body is left out as these two are.
$builders   = array(
	'modules/class-wpcpm-sponsors.php'              => 'edit_url',
	'modules/class-wpcpm-sponsor-report-screen.php' => 'editor_url',
	'tools/class-wpcpm-emails.php'                  => 'editor_url',
);
$by_hand    = '/\bARG_EDIT(?:OR)?\b|wpcpm_sreport(?![\w-])|[?&]edit=|[\'"]edit[\'"]\s*=>(?!\s*(?:sprintf\(\s*)?[\'"]<)|\[\s*[\'"]edit[\'"]\s*\]\s*=(?!=)(?!\s*(?:sprintf\(\s*)?[\'"]<)|add_query_arg\(\s*[\'"]edit[\'"]/';
$hand_built = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/../includes', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}
	$name = substr( $file->getPathname(), strlen( __DIR__ . '/../includes/' ) );
	$code = '';
	foreach ( token_get_all( (string) file_get_contents( $file->getPathname() ) ) as $token ) {
		$code .= ! is_array( $token ) ? $token : ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? str_repeat( "\n", substr_count( $token[1], "\n" ) ) : $token[1] );
	}
	if ( isset( $builders[ $name ] ) && false !== strpos( $code, 'function ' . $builders[ $name ] . '(' ) ) {
		$code = str_replace( method_body( $code, $builders[ $name ] ), '', $code );
	}
	foreach ( explode( "\n", $code ) as $line ) {
		$rest = preg_replace( array( '/WPCPM_Request::\w+\(\s*[\w:]*ARG_EDIT(?:OR)?\s*\)/', '/const ARG_EDIT(?:OR)? = \'[a-z_]+\';/' ), '', $line );
		if ( preg_match( $by_hand, $rest ) ) {
			$hand_built[ $name ][] = trim( $line );
		}
	}
}
ck( 'and no other code under includes/ writes either address: outside edit_url() and editor_url() each argument is named only where it is defined and where the request is read', $hand_built, array() );
ck( 'and the scan sees either address by either constant, as written in an address, or by either argument\'s name as a key of a map, whose value may be a variable, an encoded key or a cast one, as a key an array is given a value under, or as add_query_arg()\'s first argument', array_map(
	static function ( $line ) use ( $by_hand ) {
		return preg_match( $by_hand, $line );
	},
	array(
		'WPCPM_Sponsors::ARG_EDIT => $record,',
		'self::ARG_EDITOR => $id,',
		"admin_url( 'admin.php?page=wpcpm-sponsors&tab=sponsors&edit=' . \$key )",
		"add_query_arg( array( 'page' => 'wpcpm-sponsors', 'tab' => 'sponsors', 'edit' => \$key ), admin_url( 'admin.php' ) )",
		"add_query_arg( array( 'tab' => 'sponsors', 'edit' => rawurlencode( \$key ) ), admin_url( 'admin.php' ) )",
		"'edit' => (string) \$key,",
		"\$args['edit'] = \$key;",
		"add_query_arg( 'edit', \$key, \$url )",
		"add_query_arg( array( 'page' => 'wpcpm-sponsors', 'tab' => 'reports', 'wpcpm_sreport' => \$id ), admin_url( 'admin.php' ) )",
		"\$args['wpcpm_sreport'] = \$id;",
		"add_query_arg( 'wpcpm_sreport', \$id, \$url )",
	)
), array_fill( 0, 11, 1 ) );
ck( 'and leaves alone a map whose edit is a row action, its value a link\'s markup, as the accounts list\'s Edit is and the Engagement tab\'s, given under its key: that writes no address', array_map(
	static function ( $line ) use ( $by_hand ) {
		return preg_match( $by_hand, $line );
	},
	array(
		"return array( 'edit' => sprintf( '<a href=\"%1\$s\">%2\$s</a>', esc_url( \$edit ), esc_html__( 'Edit', 'wpcredits-program-manager' ) ) );",
		"\$actions = array( 'edit' => '<a href=\"' . esc_url( \$url ) . '\">Edit</a>' );",
		"\$actions['edit'] = sprintf( '<a href=\"%1\$s\">%2\$s</a>', esc_url( \$edit ), esc_html__( 'Edit', 'wpcredits-program-manager' ) );",
	)
), array( 0, 0, 0 ) );

ck( 'the report screen: no em or en dash, no Airtable, no sponsor ID compared with ===, no request read but through WPCPM_Request', array(
	preg_match( '/\x{2013}|\x{2014}/u', $reports_src ),
	strpos( $reports_src, 'Airtable' ),
	preg_match( '/wpcpm_sponsor(_record_id)?[^;]*===/', $reports_src ),
	preg_match( '/\$_(POST|GET|REQUEST|FILES)\b/', $reports_src ),
), array( 0, false, 0, 0 ) );
ck( 'each handler asks for the capability, then checks the nonce keyed to what it acts on, in its own body', array_map(
	static function ( $handler ) use ( $reports_src ) {
		$body  = method_body( $reports_src, $handler );
		$cap   = strpos( $body, 'current_user_can( WPCPM_Roles::CAP_MANAGE )' );
		$nonce = strpos( $body, 'check_admin_referer( self::ACTION_' );
		return false !== $cap && false !== $nonce && $cap < $nonce;
	},
	$handlers
), array_fill( 0, 6, true ) );

$admin_css = (string) file_get_contents( __DIR__ . '/../assets/css/admin.css' );
ck( 'the admin stylesheet dresses the editor: a caption at the left edge, close to the table it names and clear of the block before it, and a narrative\'s label the same way above its box', array(
	css_rule( $admin_css, '.wpcpm-sreport__table' ),
	css_rule( $admin_css, '.wpcpm-sreport__table caption' ),
	css_rule( $admin_css, '.wpcpm-sreport__field' ),
	css_rule( $admin_css, '.wpcpm-sreport__field p' ),
), array(
	array( 'margin' => '24px 0 0' ),
	array( 'font-size' => '14px', 'font-weight' => '600', 'padding' => '0 0 8px', 'text-align' => 'left' ),
	array( 'margin' => '24px 0 0' ),
	array( 'margin' => '0 0 8px' ),
) );

echo "\n--- Each press lands on the tab it was made on ---\n";
// Each press above, followed to the page its address opens, with the outcome it flashed: a press
// made in the editor comes back to the editor, a refused draft and a press naming no report to the
// Reports card, Not a renewal to the sponsor's Edit form, and Not a renewal for a sponsor gone to
// the Sponsors tab. Each prints its outcome once, above the bar.
$on_editor  = array( 'Reports', '<div class="wpcpm-card wpcpm-sreport" id="wpcpm-sreport-editor">', 1, true );
$on_reports = array( 'Reports', '<div class="wpcpm-card" id="wpcpm-sponsor-reports">', 1, true );
ck( 'each handler\'s press lands on the Reports tab or its editor, and Not a renewal on the Sponsors tab or its Edit form, the tab marked in the bar and the outcome printed once above it', array_map( 'landing_of', $landings ), array(
	'Draft a report'                    => $on_editor,
	'a refused draft'                   => $on_reports,
	'Save'                              => $on_editor,
	'Mark final'                        => $on_editor,
	'Reopen'                            => $on_editor,
	'Regenerate'                        => $on_editor,
	'a press naming no report'          => $on_reports,
	'Not a renewal'                     => array( 'Sponsors', '<div class="wpcpm-card" id="wpcpm-sponsor-edit">', 1, true ),
	'Not a renewal, for a sponsor gone' => array( 'Sponsors', '<div class="wpcpm-card">', 1, true ),
) );

unset( $GLOBALS['today'] );
$GLOBALS['get']            = array();
$GLOBALS['partner_tracks'] = array();
$GLOBALS['revisions']      = array();

echo "\n=== A sponsor added on the site, on the No account view ===\n";
// A sponsor added on the site is held by the key the site minted for it, not by a record ID it came
// onto the site with. Approved, with a contact address no account uses, it is a row of the No
// account view like any other, linked, ticked and given an account the same way; the rest of the
// list is bin/test-sponsors-accounts.php's. This suite never loads the front end, so the screen's
// draw leaves View page off every row: the row's View page is read from the table, with the Sponsor
// Dashboard's address set where the table keeps it for a draw.
$GLOBALS['uid']      = 1;
$GLOBALS['nonce_ok'] = true;
$GLOBALS['queued']   = array();
$minted              = WPCPM_Sponsors_Index::mint_key();
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $minted, 'name' => 'Minted Example', 'status' => 'Approved', 'contact_person' => 'Rep Minted', 'contact_email' => 'maciej+minted@a8c.com' ) ) );
$minted_view = render_tab( 'accounts', array( 'wpcpm_view' => 'no-account' ) );
$minted_row  = '';
foreach ( array_slice( preg_split( '/<tr[ >]/', between( $minted_view, '<tbody', '</tbody>' ) ), 1 ) as $row ) {
	if ( false !== strpos( $row, '<code class="wpcpm-inst-record">' . $minted . '</code>' ) ) {
		$minted_row = $row;
	}
}
$minted_actions = actions_in( $minted_row );
ck( 'a sponsor whose key the site minted is a row of the No account view, its checkbox posting that key under records[], with Create account, a nonce link to the provisioning handler keyed to that sponsor that comes back to the view, and Manage accounts, that sponsor\'s accounts on the Accounts tab', array(
	1 === preg_match( '/^spo[A-Za-z0-9]{14}\z/', $minted ),
	false !== strpos( $minted_row, '<input type="checkbox" name="records[]" id="wpcpm-record-' . $minted . '" value="' . $minted . '" />' ),
	array_keys( $minted_actions ),
	link_of( isset( $minted_actions['create'] ) ? $minted_actions['create'] : '' ),
	link_of( isset( $minted_actions['accounts'] ) ? $minted_actions['accounts'] : '' ),
), array(
	true,
	true,
	array( 'create', 'accounts' ),
	array( 'https://example.test/wp-admin/admin-post.php', array( 'action' => 'wpcpm_sponsor_provision', 'wpcpm_sponsor' => $minted, 'wpcpm_tab' => 'accounts', 'wpcpm_view' => 'no-account', '_wpnonce' => 'wpcpm_sponsor_provision_' . $minted ) ),
	array( 'https://example.test/wp-admin/admin.php', array( 'page' => 'wpcpm-sponsors', 'tab' => 'accounts', 'wpcpm_sponsor' => $minted, 'wpcpm_view' => 'no-account' ) ),
) );

wpcpm_load_accounts_tables();
$minted_table = new WPCPM_Sponsors_Table();
$kept_page    = new ReflectionProperty( 'WPCPM_Sponsors_Table', 'dashboard' );
$row_links    = new ReflectionMethod( 'WPCPM_Sponsors_Table', 'record_row_actions' );
// Needed on PHP 7.4, which this plugin still supports; a no-op since 8.1.
if ( PHP_VERSION_ID < 80100 ) {
	$kept_page->setAccessible( true );
	$row_links->setAccessible( true );
}
$kept_page->setValue( $minted_table, 'https://example.test/sponsor-dashboard/' );
$minted_listed = array_values( array_filter( WPCPM_Sponsors::provision_worklist(), static function ( $row ) use ( $minted ) { return $minted === $row['id']; } ) );
$minted_links  = isset( $minted_listed[0] ) ? $row_links->invoke( $minted_table, $minted_listed[0] ) : array();
ck( 'and with the Sponsor Dashboard\'s page in place its row carries View page between the two, the dashboard as that sponsor through the switcher\'s own argument', array(
	array_keys( $minted_links ),
	link_of( isset( $minted_links['view'] ) ? $minted_links['view'] : '' ),
), array(
	array( 'create', 'view', 'accounts' ),
	array( 'https://example.test/sponsor-dashboard/', array( WPCPM_Sponsor_Roster::ARG_VIEW => $minted ) ),
) );

// Ticked and pressed: the list's form as its Apply sends it, Create account chosen and the sponsor's
// key under records[], through the screen's load hook, where a press in the list is handled.
$minted_press    = array(
	'page'             => 'wpcpm-sponsors',
	'tab'              => 'accounts',
	'wpcpm_view'       => 'no-account',
	'_wpnonce'         => 'nonce',
	'_wp_http_referer' => '/wp-admin/admin.php?page=wpcpm-sponsors&tab=accounts&wpcpm_view=no-account',
	'action'           => 'create',
	'records'          => array( $minted ),
	'bulk_action'      => 'Apply',
);
$GLOBALS['get']  = $minted_press;
$_GET            = $minted_press;
$_REQUEST        = $minted_press;
$inserted_before = count( $GLOBALS['inserted'] );
$r_minted        = post( array(), array( $module, 'load_screen' ) );
$minted_detail   = WPCPM_Flash::take( WPCPM_Sponsors::FLASH_DETAIL );
$minted_members  = WPCPM_Sponsor_Members::members_of( $minted );
$minted_account  = isset( $minted_members[0] ) ? $minted_members[0] : new WP_User( 0 );
ck( 'ticked and pressed, Create account makes that sponsor its account, one created, none attached and no record left unmarked: the Sponsor role, the contact\'s name and address, acting for that sponsor, its welcome queued and the sponsor\'s row marked as having an account, and the press comes back to the No account view', array(
	$r_minted,
	is_array( $minted_detail ) ? array_intersect_key( $minted_detail, array_flip( array( 'created', 'attached', 'unmarked' ) ) ) : $minted_detail,
	count( $GLOBALS['inserted'] ) - $inserted_before,
	count( $minted_members ),
	array( $minted_account->roles, $minted_account->display_name, $minted_account->user_email ),
	$GLOBALS['queued'],
	WPCPM_Sponsors_Index::row( $minted )['dashboard_account'],
), array(
	array( 'redirect', $home . '&tab=accounts&wpcpm_view=no-account', 'provisioned' ),
	array( 'created' => 1, 'attached' => 0, 'unmarked' => 0 ),
	1,
	1,
	array( array( 'wpcpm_sponsor' ), 'Rep Minted', 'maciej+minted@a8c.com' ),
	array( $minted_account->ID ),
	true,
) );
$GLOBALS['get'] = array();
$_GET           = array();
$_REQUEST       = array();

echo "\n=== A Create account whose sponsor's record cannot be marked ===\n";
// The one way a sponsor's record refuses its mark: the record leaves the store between the press's
// read of the index and its write, as a stray trash made outside the plugin does. Held here by
// trashing the sponsor's post once the request has read the index, which the request keeps until it
// ends, and putting it back after the press, so whatever follows reads the record again. The account
// is made or attached all the same and its welcome queued.

/**
 * A sponsor's post moved to another status in the store, under the request's own copy of the index.
 *
 * @param string $key    Sponsor key.
 * @param string $status The post status to set.
 */
function sponsor_post_status( $key, $status ) {
	$GLOBALS['posts'][ WPCPM_Sponsors_Index::post_id_of( $key ) ]->post_status = $status;
}

// One row's Create account, pressed as the row's link sends it.
$GLOBALS['uid']      = 1;
$GLOBALS['nonce_ok'] = true;
$GLOBALS['queued']   = array();
$unmarked_one        = WPCPM_Sponsors_Index::mint_key();
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $unmarked_one, 'name' => 'Unmarked Example', 'status' => 'Approved', 'contact_person' => 'Rep Unmarked', 'contact_email' => 'maciej+unmarked@a8c.com' ) ) );
$row_link        = array( 'action' => 'wpcpm_sponsor_provision', 'wpcpm_sponsor' => $unmarked_one, 'wpcpm_tab' => 'accounts', 'wpcpm_view' => 'no-account', '_wpnonce' => 'nonce' );
$GLOBALS['get']  = $row_link;
$_GET            = $row_link;
$_REQUEST        = $row_link;
$inserted_before = count( $GLOBALS['inserted'] );
WPCPM_Sponsors_Index::forget();
WPCPM_Sponsors_Index::rows();
sponsor_post_status( $unmarked_one, 'trash' );
$r_unmarked = post( array(), array( $module, 'handle_provision' ) );
$GLOBALS['get'] = array();
$_GET           = array();
$_REQUEST       = array();
// Drawn while the record is still out of the store, as the manager reads the warning: the view the
// press lands on, and the Sponsors tab, which lists only the sponsors the store holds; then drawn
// again once the record is back.
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_unmarked[2] ) ? (string) $r_unmarked[2] : '' );
$gone_landing = 'redirect' === $r_unmarked[0] ? render_screen( link_of( (string) $r_unmarked[1] )[1] ) : '';
$gone_listed  = false !== strpos( render_tab( 'sponsors' ), $unmarked_one );
sponsor_post_status( $unmarked_one, 'private' );
WPCPM_Sponsors_Index::forget();
$back_listed      = false !== strpos( render_tab( 'sponsors' ), $unmarked_one );
$unmarked_members = WPCPM_Sponsor_Members::members_of( $unmarked_one );
$unmarked_account = isset( $unmarked_members[0] ) ? $unmarked_members[0] : new WP_User( 0 );
ck( 'a row\'s Create account whose sponsor\'s record cannot be marked makes the account all the same, acting for that sponsor with the Sponsor role and the contact\'s name and address, queues its welcome and logs it, and comes back to the No account view with record-unmarked; the record stays unmarked', array(
	$r_unmarked,
	count( $GLOBALS['inserted'] ) - $inserted_before,
	count( $unmarked_members ),
	array( $unmarked_account->roles, $unmarked_account->display_name, $unmarked_account->user_email ),
	$GLOBALS['queued'],
	array( last_audit()['kind'], last_audit()['sponsor'] ),
	WPCPM_Sponsors_Index::row( $unmarked_one )['dashboard_account'],
), array(
	array( 'redirect', $home . '&tab=accounts&wpcpm_view=no-account', 'record-unmarked' ),
	1,
	1,
	array( array( 'wpcpm_sponsor' ), 'Rep Unmarked', 'maciej+unmarked@a8c.com' ),
	array( $unmarked_account->ID ),
	array( 'provisioned', $unmarked_one ),
	false,
) );
ck( 'while the record is still out of the store, the view the press lands on prints the warning, and the Sponsors tab does not list the sponsor, so the save the warning names waits for the record; once the record is back, the tab lists the sponsor again', array( notices_in( $gone_landing ), $gone_listed, $back_listed ), array(
	'<div class="notice notice-warning is-dismissible"><p>The account acts for the sponsor, and its invitation is queued unless it already had one. The sponsor&#039;s record could not be found, so it is not marked as having an account. If the Sponsors tab lists the sponsor, saving it on its Edit form marks it.</p></div>',
	false,
	true,
) );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_unmarked[2] ) ? (string) $r_unmarked[2] : '' );
$unmarked_page = 'redirect' === $r_unmarked[0] ? render_screen( link_of( (string) $r_unmarked[1] )[1] ) : '';
ck( 'and the view says the account acts for the sponsor with its welcome queued unless it had one, that the sponsor\'s record could not be found to mark as having an account, and that a save of the sponsor\'s Edit form marks it if the Sponsors tab lists the sponsor, a warning', notices_in( $unmarked_page ), '<div class="notice notice-warning is-dismissible"><p>The account acts for the sponsor, and its invitation is queued unless it already had one. The sponsor&#039;s record could not be found, so it is not marked as having an account. If the Sponsors tab lists the sponsor, saving it on its Edit form marks it.</p></div>' );

// The record back in the store, the Sponsors tab lists the sponsor, and its Edit form saved as
// drawn changes no field a manager owns and marks the record all the same, as the warning says; the
// next save has nothing left to do.
$unmarked_form = form_fields_of( render_tab( 'sponsors', array( WPCPM_Sponsors::ARG_EDIT => $unmarked_one ) ), WPCPM_Sponsors::ACTION_EDIT );
$GLOBALS['get'] = array();
$_GET           = array();
$_REQUEST       = array();
$audit_before   = count( $GLOBALS['audit'] );
$r_marking      = post( isset( $unmarked_form[0] ) ? $unmarked_form[0] : array(), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::forget();
$marked_by_save = WPCPM_Sponsors_Index::row( $unmarked_one )['dashboard_account'];
$logged_by_save = count( $GLOBALS['audit'] ) - $audit_before;
$r_marked       = post( isset( $unmarked_form[0] ) ? $unmarked_form[0] : array(), array( $module, 'handle_edit' ) );
ck( 'the sponsor\'s Edit form saved as drawn marks the record and says the sponsor was saved, logging nothing since no field a manager owns changed; saved again, it changes nothing and says so', array(
	$r_marking,
	$marked_by_save,
	$logged_by_save,
	isset( $r_marked[2] ) ? $r_marked[2] : $r_marked,
), array(
	array( 'redirect', $home . '&tab=sponsors&edit=' . $unmarked_one, 'sponsor-saved' ),
	true,
	0,
	'sponsor-unchanged',
) );

// The ticked sponsors' Create account: one whose contact has no account, which it makes, and one
// whose contact has an account, which it attaches and whose record leaves the store mid-press. That
// one is the first the press takes, in the view's order by name: a record found and marked drops
// the request's copy of the index, so a sponsor taken after it is read afresh and found gone before
// its account is made.
$unmarked_made = WPCPM_Sponsors_Index::mint_key();
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $unmarked_made, 'name' => 'Abbey Unmarked', 'status' => 'Approved', 'contact_person' => 'Rep Made', 'contact_email' => 'maciej+made@a8c.com' ) ) );
$unmarked_attached = WPCPM_Sponsors_Index::mint_key();
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $unmarked_attached, 'name' => 'Aaron Unmarked', 'status' => 'Approved', 'contact_person' => 'Rep Attached', 'contact_email' => 'maciej+attached@a8c.com' ) ) );
$GLOBALS['users'][83] = new WP_User( 83, array( 'subscriber' ), 'Rep Attached', 'maciej+attached@a8c.com' );
$ticked_press         = array(
	'page'             => 'wpcpm-sponsors',
	'tab'              => 'accounts',
	'wpcpm_view'       => 'no-account',
	'_wpnonce'         => 'nonce',
	'_wp_http_referer' => '/wp-admin/admin.php?page=wpcpm-sponsors&tab=accounts&wpcpm_view=no-account',
	'action'           => 'create',
	'records'          => array( $unmarked_made, $unmarked_attached ),
	'bulk_action'      => 'Apply',
);
$GLOBALS['get']     = $ticked_press;
$_GET               = $ticked_press;
$_REQUEST           = $ticked_press;
$GLOBALS['queued']  = array();
$inserted_before    = count( $GLOBALS['inserted'] );
WPCPM_Sponsors_Index::forget();
WPCPM_Sponsors_Index::rows();
sponsor_post_status( $unmarked_attached, 'trash' );
$r_ticked      = post( array(), array( $module, 'load_screen' ) );
$ticked_detail = WPCPM_Flash::take( WPCPM_Sponsors::FLASH_DETAIL );
$GLOBALS['get'] = array();
$_GET           = array();
$_REQUEST       = array();
sponsor_post_status( $unmarked_attached, 'private' );
WPCPM_Sponsors_Index::forget();
$made_members     = WPCPM_Sponsor_Members::members_of( $unmarked_made );
$attached_members = WPCPM_Sponsor_Members::members_of( $unmarked_attached );
ck( 'ticked and pressed, Create account on two sponsors, one of whose records leaves the store mid-press, makes the one account and attaches the other, each acting for its sponsor with its welcome queued, counts one made and one attached and the one under unmarked, named, names neither as not created, and marks the record it could find and not the other', array(
	$r_ticked,
	$ticked_detail,
	count( $GLOBALS['inserted'] ) - $inserted_before,
	array_map( static function ( $user ) { return $user->user_email; }, $made_members ),
	array_map( static function ( $user ) { return $user->ID; }, $attached_members ),
	count( $GLOBALS['queued'] ),
	array( WPCPM_Sponsors_Index::row( $unmarked_made )['dashboard_account'], WPCPM_Sponsors_Index::row( $unmarked_attached )['dashboard_account'] ),
), array(
	array( 'redirect', $home . '&tab=accounts&wpcpm_view=no-account', 'provisioned' ),
	array( 'status' => 'provisioned', 'created' => 1, 'attached' => 1, 'unmarked' => 1, 'left' => 0, 'limit' => WPCPM_Sponsors::PROVISION_LIMIT, 'unmarked_names' => array( 'Aaron Unmarked' ), 'unmarked_more' => 0 ),
	1,
	array( 'maciej+made@a8c.com' ),
	array( 83 ),
	2,
	array( true, false ),
) );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_ticked[2] ) ? (string) $r_ticked[2] : '' );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH_DETAIL, $ticked_detail );
$ticked_page = 'redirect' === $r_ticked[0] ? render_screen( link_of( (string) $r_ticked[1] )[1] ) : '';
ck( 'and the view says so after the counts: the sponsor whose record could not be found, by name, and not the other, then how such a record is marked', notices_in( $ticked_page ), '<div class="notice notice-success is-dismissible"><p>1 account was created and its invitation queued. 1 existing account was attached. Not marked as having an account, because the record could not be found: Aaron Unmarked. Saving a sponsor on its Edit form marks it, if the Sponsors tab lists it.</p></div>' );

// The same press with both records leaving the store mid-press, one account made and one attached:
// the first mark fails and leaves the request's copy of the index as it was, so the second sponsor is
// read from that copy too, given its account, and its mark fails the same way. Both are counted under
// unmarked and named in the order the press took them, the view's order by name, which is neither the
// order they were added in nor the order they were ticked.
$both_bay = WPCPM_Sponsors_Index::mint_key();
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $both_bay, 'name' => 'Bay Both Gone', 'status' => 'Approved', 'contact_person' => 'Rep Bay', 'contact_email' => 'maciej+bay@a8c.com' ) ) );
$both_ash = WPCPM_Sponsors_Index::mint_key();
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $both_ash, 'name' => 'Ash Both Gone', 'status' => 'Approved', 'contact_person' => 'Rep Ash', 'contact_email' => 'maciej+ash@a8c.com' ) ) );
$GLOBALS['users'][85] = new WP_User( 85, array( 'subscriber' ), 'Rep Bay', 'maciej+bay@a8c.com' );
$both_press           = array_merge( $ticked_press, array( 'records' => array( $both_bay, $both_ash ) ) );
$GLOBALS['get']       = $both_press;
$_GET                 = $both_press;
$_REQUEST             = $both_press;
$GLOBALS['queued']    = array();
$inserted_before      = count( $GLOBALS['inserted'] );
WPCPM_Sponsors_Index::forget();
WPCPM_Sponsors_Index::rows();
sponsor_post_status( $both_bay, 'trash' );
sponsor_post_status( $both_ash, 'trash' );
$r_both         = post( array(), array( $module, 'load_screen' ) );
$both_detail    = WPCPM_Flash::take( WPCPM_Sponsors::FLASH_DETAIL );
$GLOBALS['get'] = array();
$_GET           = array();
$_REQUEST       = array();
sponsor_post_status( $both_bay, 'private' );
sponsor_post_status( $both_ash, 'private' );
WPCPM_Sponsors_Index::forget();
ck( 'ticked and pressed, Create account on two sponsors whose records both leave the store mid-press makes the one account and attaches the other, each acting for its sponsor with its welcome queued, counts both under unmarked, names both in the order the press took them, and marks neither record', array(
	$r_both,
	$both_detail,
	count( $GLOBALS['inserted'] ) - $inserted_before,
	array_map( static function ( $user ) { return $user->user_email; }, WPCPM_Sponsor_Members::members_of( $both_ash ) ),
	array_map( static function ( $user ) { return $user->ID; }, WPCPM_Sponsor_Members::members_of( $both_bay ) ),
	count( $GLOBALS['queued'] ),
	array( WPCPM_Sponsors_Index::row( $both_ash )['dashboard_account'], WPCPM_Sponsors_Index::row( $both_bay )['dashboard_account'] ),
), array(
	array( 'redirect', $home . '&tab=accounts&wpcpm_view=no-account', 'provisioned' ),
	array( 'status' => 'provisioned', 'created' => 1, 'attached' => 1, 'unmarked' => 2, 'left' => 0, 'limit' => WPCPM_Sponsors::PROVISION_LIMIT, 'unmarked_names' => array( 'Ash Both Gone', 'Bay Both Gone' ), 'unmarked_more' => 0 ),
	1,
	array( 'maciej+ash@a8c.com' ),
	array( 85 ),
	2,
	array( false, false ),
) );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_both[2] ) ? (string) $r_both[2] : '' );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH_DETAIL, $both_detail );
$both_page = 'redirect' === $r_both[0] ? render_screen( link_of( (string) $r_both[1] )[1] ) : '';
ck( 'and the view names both after the counts, in the order the press took them, then how such a record is marked', notices_in( $both_page ), '<div class="notice notice-success is-dismissible"><p>1 account was created and its invitation queued. 1 existing account was attached. Not marked as having an account, because the record could not be found: Ash Both Gone, Bay Both Gone. Saving a sponsor on its Edit form marks it, if the Sponsors tab lists it.</p></div>' );

// More sponsors whose records could not be found than the list names: seven, every record leaving
// the store mid-press, ticked in the reverse of the view's order. The detail names as many as the list
// names, in the order the press took them, and counts the rest, which the sentence says after them.
$lost_keys = array();
foreach ( array( 'A', 'B', 'C', 'D', 'E', 'F', 'G' ) as $letter ) {
	$lost_keys[ $letter ] = WPCPM_Sponsors_Index::mint_key();
	WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $lost_keys[ $letter ], 'name' => 'Lost Example ' . $letter, 'status' => 'Approved', 'contact_person' => 'Rep Lost ' . $letter, 'contact_email' => 'maciej+lost-' . strtolower( $letter ) . '@a8c.com' ) ) );
}
$lost_press     = array_merge( $ticked_press, array( 'records' => array_reverse( array_values( $lost_keys ) ) ) );
$GLOBALS['get'] = $lost_press;
$_GET           = $lost_press;
$_REQUEST       = $lost_press;
WPCPM_Sponsors_Index::forget();
WPCPM_Sponsors_Index::rows();
foreach ( $lost_keys as $lost_key ) {
	sponsor_post_status( $lost_key, 'trash' );
}
$r_lost         = post( array(), array( $module, 'load_screen' ) );
$lost_detail    = WPCPM_Flash::take( WPCPM_Sponsors::FLASH_DETAIL );
$GLOBALS['get'] = array();
$_GET           = array();
$_REQUEST       = array();
foreach ( $lost_keys as $lost_key ) {
	sponsor_post_status( $lost_key, 'private' );
}
WPCPM_Sponsors_Index::forget();
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_lost[2] ) ? (string) $r_lost[2] : '' );
WPCPM_Flash::set( WPCPM_Sponsors::FLASH_DETAIL, $lost_detail );
$lost_page = 'redirect' === $r_lost[0] ? render_screen( link_of( (string) $r_lost[1] )[1] ) : '';
ck( 'with more records not found than the list names, the detail names as many as it names, in the order the press took them, and counts the rest, and the view says how many more after the names', array( $lost_detail, notices_in( $lost_page ) ), array(
	array( 'status' => 'provisioned', 'created' => 7, 'attached' => 0, 'unmarked' => 7, 'left' => 0, 'limit' => WPCPM_Sponsors::PROVISION_LIMIT, 'unmarked_names' => array( 'Lost Example A', 'Lost Example B', 'Lost Example C', 'Lost Example D', 'Lost Example E' ), 'unmarked_more' => 2 ),
	'<div class="notice notice-success is-dismissible"><p>7 accounts were created, each with its invitation queued. Not marked as having an account, because the record could not be found: Lost Example A, Lost Example B, Lost Example C, Lost Example D, Lost Example E, and 2 more. Saving a sponsor on its Edit form marks it, if the Sponsors tab lists it.</p></div>',
) );
ck( 'a detail that counts such records and names none, as one flashed before the names were kept, says how many could not be found, in the singular and the plural, then how such a record is marked', array(
	WPCPM_Sponsors_Table::action_sentence( 'provisioned', array( 'created' => 1, 'attached' => 0, 'unmarked' => 1, 'left' => 0, 'limit' => WPCPM_Sponsors::PROVISION_LIMIT ) ),
	WPCPM_Sponsors_Table::action_sentence( 'provisioned', array( 'created' => 2, 'attached' => 0, 'unmarked' => 2, 'left' => 0, 'limit' => WPCPM_Sponsors::PROVISION_LIMIT ) ),
), array(
	'1 account was created and its invitation queued. 1 sponsor record could not be found, so it is not marked as having an account. Saving a sponsor on its Edit form marks it, if the Sponsors tab lists it.',
	'2 accounts were created, each with its invitation queued. 2 sponsor records could not be found, so they are not marked as having an account. Saving a sponsor on its Edit form marks it, if the Sponsors tab lists it.',
) );

// Attach account on a sponsor's accounts, the record leaving the store mid-press, made the same
// way: the account it attaches acts for the sponsor and its welcome is queued, so the press says
// that, and that the record could not be found, as a row's Create account does, not a refusal.
$attach_unmarked = WPCPM_Sponsors_Index::mint_key();
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $attach_unmarked, 'name' => 'Attach Unmarked', 'status' => 'Approved', 'contact_person' => 'Rep Attach', 'contact_email' => 'maciej+attach@a8c.com' ) ) );
$GLOBALS['users'][84] = new WP_User( 84, array( 'subscriber' ), 'Rep Attach', 'maciej+attach@a8c.com' );
$GLOBALS['queued']    = array();
WPCPM_Sponsors_Index::forget();
WPCPM_Sponsors_Index::rows();
sponsor_post_status( $attach_unmarked, 'trash' );
$r_attach_unmarked = post( array( 'action' => WPCPM_Sponsors::ACTION_MEMBERS, 'wpcpm_op' => 'attach', 'wpcpm_sponsor' => $attach_unmarked, 'wpcpm_tab' => 'accounts', 'wpcpm_email' => 'maciej+attach@a8c.com' ), array( $module, 'handle_members' ) );
sponsor_post_status( $attach_unmarked, 'private' );
WPCPM_Sponsors_Index::forget();
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_attach_unmarked[2] ) ? (string) $r_attach_unmarked[2] : '' );
$attach_landing = 'redirect' === $r_attach_unmarked[0] ? render_screen( link_of( (string) $r_attach_unmarked[1] )[1] ) : '';
ck( 'Attach account whose sponsor\'s record leaves the store mid-press attaches the account all the same and queues its welcome, and comes back to the sponsor\'s accounts with record-unmarked and its warning, not a refusal; the record stays unmarked', array(
	$r_attach_unmarked,
	WPCPM_Sponsor_Members::sponsor_of( $GLOBALS['users'][84] ),
	$GLOBALS['queued'],
	WPCPM_Sponsors_Index::row( $attach_unmarked )['dashboard_account'],
	notices_in( $attach_landing ),
), array(
	array( 'redirect', $home . '&tab=accounts&wpcpm_sponsor=' . $attach_unmarked, 'record-unmarked' ),
	$attach_unmarked,
	array( 84 ),
	false,
	'<div class="notice notice-warning is-dismissible"><p>The account acts for the sponsor, and its invitation is queued unless it already had one. The sponsor&#039;s record could not be found, so it is not marked as having an account. If the Sponsors tab lists the sponsor, saving it on its Edit form marks it.</p></div>',
) );

// Remove, the twin's other half: the sponsor's last account, its record marked as having one, removed
// while the record leaves the store mid-press. The account no longer acts for the sponsor whatever the
// clear of the record's flag answers, so the press says so, not a refusal; the clear cannot reach the
// record, so the flag stays as the record held it.
$remove_unmarked = WPCPM_Sponsors_Index::mint_key();
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $remove_unmarked, 'name' => 'Remove Unmarked', 'status' => 'Approved', 'contact_person' => 'Rep Remove', 'contact_email' => 'maciej+remove@a8c.com', 'dashboard_account' => true ) ) );
$GLOBALS['users'][86] = new WP_User( 86, array( 'subscriber' ), 'Rep Remove', 'maciej+remove@a8c.com' );
$remove_attached      = WPCPM_Sponsor_Members::attach( 86, $remove_unmarked, WPCPM_Sponsor_Members::HOW_MANAGER, 1 );
WPCPM_Sponsors_Index::forget();
WPCPM_Sponsors_Index::rows();
sponsor_post_status( $remove_unmarked, 'trash' );
$r_remove_unmarked = post( array( 'action' => WPCPM_Sponsors::ACTION_MEMBERS, 'wpcpm_op' => 'detach', 'wpcpm_sponsor' => $remove_unmarked, 'wpcpm_tab' => 'accounts', 'wpcpm_user' => 86 ), array( $module, 'handle_members' ) );
sponsor_post_status( $remove_unmarked, 'private' );
WPCPM_Sponsors_Index::forget();
WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r_remove_unmarked[2] ) ? (string) $r_remove_unmarked[2] : '' );
$remove_landing = 'redirect' === $r_remove_unmarked[0] ? render_screen( link_of( (string) $r_remove_unmarked[1] )[1] ) : '';
ck( 'Remove of a sponsor\'s last account whose record leaves the store mid-press removes the account all the same and comes back to the sponsor\'s accounts with detached and its sentence, not a refusal; the record\'s flag stays as it was', array(
	$remove_attached,
	$r_remove_unmarked,
	WPCPM_Sponsor_Members::sponsor_of( $GLOBALS['users'][86] ),
	WPCPM_Sponsor_Members::members_of( $remove_unmarked ),
	WPCPM_Sponsors_Index::row( $remove_unmarked )['dashboard_account'],
	notices_in( $remove_landing ),
), array(
	true,
	array( 'redirect', $home . '&tab=accounts&wpcpm_sponsor=' . $remove_unmarked, 'detached' ),
	'',
	array(),
	true,
	'<div class="notice notice-success is-dismissible"><p>The account no longer acts for the sponsor.</p></div>',
) );

echo "\n=== Typed texts on Add and Edit: counted, drawn and kept as typed ===\n";
// The name, the contact person, What the sponsorship funds and the notes are typed texts: each is
// counted as the manager typed it, an entity the cleaner wrote and a line break one character each,
// as the box counts them; refused rather than cut one over its limit, with a sentence that names the
// box; drawn back as typed, with room for a held `&gt;` (`WPCPM_Typed_Text`); and kept as stored when
// the box comes back reading the same, so an unedited save changes nothing. Over core's cleaners
// (bin/stubs/cleaners.php) and core's `esc_attr()`, without which a box that forgot the cleaner's
// entities would pass every check here.

/** The markup of a text input's value and its maxlength, by name; null when it is not drawn. */
function typed_input( $html, $name ) {
	return preg_match( '#name="' . preg_quote( $name, '#' ) . '" value="([^"]*)" maxlength="(\d+)"#', (string) $html, $m ) ? array( $m[1], (int) $m[2] ) : null;
}
/** The markup of a text area's text and its maxlength, by name; null when it is not drawn. */
function typed_area( $html, $name ) {
	return preg_match( '#<textarea id="[^"]*" name="' . preg_quote( $name, '#' ) . '"[^>]*maxlength="(\d+)">(.*?)</textarea>#s', (string) $html, $m ) ? array( $m[2], (int) $m[1] ) : null;
}
/** What a browser shows for a box's markup: each entity read once, and in a text area a CR LF read as LF. */
function browser_shows( $markup, $area = false ) {
	return html_entity_decode( $area ? str_replace( "\r\n", "\n", (string) $markup ) : (string) $markup, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}
/** What a browser posts from a text area showing this text: each line break as CR LF. */
function browser_lines( $shown ) {
	return str_replace( "\n", "\r\n", str_replace( array( "\r\n", "\r" ), "\n", (string) $shown ) );
}
/** A sponsor's Edit form, as the Sponsors tab draws it. */
function edit_form_of( $key ) {
	$html           = render_tab( 'sponsors', array( 'edit' => $key ) );
	$GLOBALS['get'] = array();
	return substr( $html, (int) strpos( $html, 'id="wpcpm-sponsor-edit"' ) );
}
/** What one of the typed boxes shows and posts back, by field: the box as a browser reads it, and its maxlength. */
function typed_box( $form, $field ) {
	$drawn = 'notes' === $field ? typed_area( $form, 'wpcpm_notes' ) : typed_input( $form, 'wpcpm_' . $field );
	if ( null === $drawn ) {
		return null;
	}
	$shown = browser_shows( $drawn[0], 'notes' === $field );
	return array( 'markup' => $drawn[0], 'shown' => $shown, 'posts' => 'notes' === $field ? browser_lines( $shown ) : $shown, 'maxlength' => $drawn[1] );
}

$GLOBALS['uid']      = 1;
$GLOBALS['nonce_ok'] = true;
$TT                  = 'spoTypedText00001';
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $TT, 'name' => 'Typed Example', 'status' => 'Approved', 'tier' => 'backer', 'tier_until' => '2099-09-30', 'contact_person' => 'Rep Typed', 'contact_email' => 'maciej+typed@a8c.com' ) ) );

// A line of two hundred characters as typed, and notes of four thousand with line breaks posted as
// CR LF: each "<" opens no tag, so the cleaner writes it `&lt;`, with the quote marks and the
// ampersands after it, and stores more characters than were typed.
$sign       = 'Q&A "mentors" & \'teams\' < 25 & "growing" ';
$line_200   = $sign . str_repeat( 'y', 200 - mb_strlen( $sign ) );
$lines      = "First \"line\" & more < 25 & 'quoted'\r\nSecond line\r\n";
$notes_4000 = $lines . str_repeat( 'n', 4000 - mb_strlen( str_replace( "\r\n", "\n", $lines ) ) );
$boxes      = array(
	'name'           => array( 'limit' => 200, 'typed' => $line_200, 'long' => 'name-too-long' ),
	'contact_person' => array( 'limit' => 200, 'typed' => $line_200, 'long' => 'contact-too-long' ),
	'funded_line'    => array( 'limit' => 200, 'typed' => $line_200, 'long' => 'funded-too-long' ),
	'notes'          => array( 'limit' => 4000, 'typed' => $notes_4000, 'long' => 'notes-too-long' ),
);

foreach ( $boxes as $field => $box ) {
	$area   = 'notes' === $field;
	$stored = $area ? trim( sanitize_textarea_field( $box['typed'] ) ) : trim( sanitize_text_field( $box['typed'] ) );

	$audit_before = count( $GLOBALS['audit'] );
	$r            = post( array( 'wpcpm_sponsor' => $TT, 'wpcpm_' . $field => $box['typed'] ), array( $module, 'handle_edit' ) );
	$kept         = (array) WPCPM_Sponsors_Index::row( $TT ) + WPCPM_Sponsors_Index::empty_row();
	ck( $field . ': a text at its limit as typed, a "<", quote marks and ampersands' . ( $area ? ' and CR LF line breaks' : '' ) . ' in it, is saved as the cleaner left it, though it stores more characters than its limit', array(
		isset( $r[2] ) ? $r[2] : $r,
		$kept[ $field ] === $stored,
		WPCPM_Typed_Text::typed_length( $kept[ $field ] ),
		mb_strlen( $kept[ $field ] ) > $box['limit'],
		count( $GLOBALS['audit'] ) - $audit_before,
	), array( 'sponsor-saved', true, $box['limit'], true, 1 ) );

	$audit_before = count( $GLOBALS['audit'] );
	$r            = post( array( 'wpcpm_sponsor' => $TT, 'wpcpm_' . $field => $box['typed'] . 'z', 'wpcpm_website' => 'https://typed.example/not-saved' ), array( $module, 'handle_edit' ) );
	ck( $field . ': one character more refuses the whole save with the sentence that names the box, and nothing is stored', array(
		isset( $r[2] ) ? $r[2] : $r,
		(array) WPCPM_Sponsors_Index::row( $TT ) + WPCPM_Sponsors_Index::empty_row() === $kept,
		count( $GLOBALS['audit'] ) - $audit_before,
	), array( $box['long'], true, 0 ) );

	// The page the refusal lands on, which draws the form with what was typed, once.
	edit_form_of( $TT );
	$drawn = typed_box( edit_form_of( $TT ), $field );
	ck( $field . ': the Edit form draws it back as typed, through the shared read-back, with the maxlength the shared rule gives it', array(
		null === $drawn ? null : $drawn['markup'],
		null === $drawn ? null : $drawn['shown'],
		null === $drawn ? null : $drawn['maxlength'],
	), array(
		$area ? esc_textarea( WPCPM_Typed_Text::typed_text( $stored ) ) : esc_attr( WPCPM_Typed_Text::attr_text( $stored ) ),
		$area ? str_replace( "\r\n", "\n", $box['typed'] ) : $box['typed'],
		WPCPM_Typed_Text::drawn_limit( $stored, $box['limit'] ),
	) );

	$audit_before = count( $GLOBALS['audit'] );
	$r            = post( array( 'wpcpm_sponsor' => $TT, 'wpcpm_' . $field => null === $drawn ? '' : $drawn['posts'] ), array( $module, 'handle_edit' ) );
	ck( $field . ': posted back unedited it changes nothing, says so, and logs nothing', array(
		isset( $r[2] ) ? $r[2] : $r,
		(array) WPCPM_Sponsors_Index::row( $TT ) + WPCPM_Sponsors_Index::empty_row() === $kept,
		count( $GLOBALS['audit'] ) - $audit_before,
	), array( 'sponsor-unchanged', true, 0 ) );
}

ck( 'each refusal for length says which box, its limit, and what to do', array(
	WPCPM_Sponsors::messages()['name-too-long'] ?? null,
	WPCPM_Sponsors::messages()['contact-too-long'] ?? null,
	WPCPM_Sponsors::messages()['funded-too-long'] ?? null,
	WPCPM_Sponsors::messages()['notes-too-long'] ?? null,
), array(
	array( 'error', 'Nothing was saved: "Name" is longer than 200 characters. Shorten it and save again.' ),
	array( 'error', 'Nothing was saved: "Contact person" is longer than 200 characters. Shorten it and save again.' ),
	array( 'error', 'Nothing was saved: "What the sponsorship funds" is longer than 200 characters. Shorten it and save again.' ),
	array( 'error', 'Nothing was saved: "Notes" is longer than 4000 characters. Shorten it and save again.' ),
) );

// The Add form shares the rules: its name, contact person and notes.
$count = count( WPCPM_Sponsors_Index::rows() );
$r_add = post( array( 'wpcpm_name' => $line_200, 'wpcpm_contact_person' => $line_200, 'wpcpm_notes' => $notes_4000 ), array( $module, 'handle_add' ) );
$r_out = array(
	post( array( 'wpcpm_name' => $line_200 . 'z' ), array( $module, 'handle_add' ) ),
	post( array( 'wpcpm_name' => 'Typed Add Example', 'wpcpm_contact_person' => $line_200 . 'z' ), array( $module, 'handle_add' ) ),
	post( array( 'wpcpm_name' => 'Typed Add Example', 'wpcpm_notes' => $notes_4000 . 'z' ), array( $module, 'handle_add' ) ),
);
ck( 'Add takes a name, a contact person and notes at their limits as typed, and refuses one more with the sentence that names the box', array(
	isset( $r_add[2] ) ? $r_add[2] : $r_add,
	array_map( static function ( $r ) { return isset( $r[2] ) ? $r[2] : $r; }, $r_out ),
	count( WPCPM_Sponsors_Index::rows() ) - $count,
), array( 'sponsor-added', array( 'name-too-long', 'contact-too-long', 'notes-too-long' ), 1 ) );
// The page the last refusal lands on, which draws the Add form with what was typed, once.
render_tab( 'sponsors' );
$GLOBALS['get'] = array();

// A stored text that counts over its limit as typed is kept as it is when its box comes back
// unedited, and does not stop a save of another field: only a changed text is measured.
WPCPM_Sponsors_Index::patch( $TT, array( 'contact_person' => str_repeat( 'o', 250 ) ) );
$drawn        = typed_box( edit_form_of( $TT ), 'contact_person' );
$audit_before = count( $GLOBALS['audit'] );
$r            = post( array( 'wpcpm_sponsor' => $TT, 'wpcpm_contact_person' => null === $drawn ? '' : $drawn['posts'], 'wpcpm_website' => 'https://typed.example/' ), array( $module, 'handle_edit' ) );
ck( 'a stored contact person over its limit is kept when it comes back unedited, and the save of another field goes through', array(
	isset( $r[2] ) ? $r[2] : $r,
	( (array) WPCPM_Sponsors_Index::row( $TT ) )['contact_person'],
	( (array) WPCPM_Sponsors_Index::row( $TT ) )['website'],
	last_audit()['data'],
), array( 'sponsor-saved', str_repeat( 'o', 250 ), 'https://typed.example/', array( 'fields' => array( 'website' ) ) ) );

// A contact person of two hundred characters typed on the profile card, stored as the card stores
// it, then saved again from the Edit form: unedited beside another field, and then edited, still two
// hundred as typed.
$card_typed = 'Ana "Rep" O\'Neil & Q&A team < 25 & "growing" ';
$card_typed = $card_typed . str_repeat( 'c', 200 - mb_strlen( $card_typed ) );
$card       = WPCPM_Sponsor_Profile::clean( 'contact_person', sanitize_text_field( $card_typed ) );
WPCPM_Sponsors_Index::patch( $TT, array( 'contact_person' => (string) $card['value'] ) );
$drawn  = typed_box( edit_form_of( $TT ), 'contact_person' );
$r_kept = post( array( 'wpcpm_sponsor' => $TT, 'wpcpm_contact_person' => null === $drawn ? '' : $drawn['posts'], 'wpcpm_notes' => 'Saved beside the contact person.' ), array( $module, 'handle_edit' ) );
$kept   = ( (array) WPCPM_Sponsors_Index::row( $TT ) )['contact_person'];
$edited = substr( $card_typed, 0, -1 ) . 'd';
$r_edit = post( array( 'wpcpm_sponsor' => $TT, 'wpcpm_contact_person' => $edited ), array( $module, 'handle_edit' ) );
ck( 'a contact person of two hundred characters typed on the profile card shows as typed on the Edit form, is kept by a save of another field, and can be edited there at the same length', array(
	$card['ok'],
	mb_strlen( (string) $card['value'] ) > 200,
	null === $drawn ? null : $drawn['shown'],
	isset( $r_kept[2] ) ? $r_kept[2] : $r_kept,
	$kept === (string) $card['value'],
	isset( $r_edit[2] ) ? $r_edit[2] : $r_edit,
	( (array) WPCPM_Sponsors_Index::row( $TT ) )['contact_person'] === trim( sanitize_text_field( $edited ) ),
), array( true, true, $card_typed, 'sponsor-saved', true, 'sponsor-saved', true ) );

// "We <3 our <b>team</b> > all", as the cleaner stores it: the "<" before the tag it took out is left
// `&lt;`, and the ">" after it raw. Drawn as stored, each box would show "We <3 our team > all", which
// the cleaner reads as a tag when it is posted, and the save of another field would store "We all".
$heart = 'We &lt;3 our team > all';
WPCPM_Sponsors_Index::patch( $TT, array( 'name' => $heart, 'contact_person' => $heart, 'funded_line' => $heart, 'notes' => $heart ) );
$form   = edit_form_of( $TT );
$posted = array( 'wpcpm_sponsor' => $TT, 'wpcpm_website' => 'https://typed.example/heart' );
$shown  = array();
foreach ( array_keys( $boxes ) as $field ) {
	$drawn                       = typed_box( $form, $field );
	$posted[ 'wpcpm_' . $field ] = null === $drawn ? '' : $drawn['posts'];
	$shown[ $field ]             = null === $drawn ? null : array( $drawn['shown'], $drawn['maxlength'] );
}
$r     = post( $posted, array( $module, 'handle_edit' ) );
$after = (array) WPCPM_Sponsors_Index::row( $TT ) + WPCPM_Sponsors_Index::empty_row();
ck( 'a stored "We &lt;3 our team > all" shows in each box with its ">" held, three characters more room for it, and survives an Edit save of another field in every box', array(
	$shown,
	isset( $r[2] ) ? $r[2] : $r,
	array( $after['name'], $after['contact_person'], $after['funded_line'], $after['notes'], $after['website'] ),
	last_audit()['data'],
), array(
	array(
		'name'           => array( 'We <3 our team &gt; all', 203 ),
		'contact_person' => array( 'We <3 our team &gt; all', 203 ),
		'funded_line'    => array( 'We <3 our team &gt; all', 203 ),
		'notes'          => array( 'We <3 our team &gt; all', 4003 ),
	),
	'sponsor-saved',
	array( $heart, $heart, $heart, $heart, 'https://typed.example/heart' ),
	array( 'fields' => array( 'website' ) ),
) );

// A stored note that ends in a line break, and a contact person that ends in a space, as an older
// store could keep them: the cleaner trims both from the post-back, and posted back unedited they are
// still the stored text, so the save changes nothing.
WPCPM_Sponsors_Index::patch( $TT, array( 'contact_person' => 'Rep Typed ', 'notes' => "Met at a WordCamp.\r\nWrite again in October.\r\n" ) );
$form         = edit_form_of( $TT );
$kept         = (array) WPCPM_Sponsors_Index::row( $TT );
$posted       = array( 'wpcpm_sponsor' => $TT );
foreach ( array( 'contact_person', 'notes' ) as $field ) {
	$drawn                       = typed_box( $form, $field );
	$posted[ 'wpcpm_' . $field ] = null === $drawn ? '' : $drawn['posts'];
}
$audit_before = count( $GLOBALS['audit'] );
$r            = post( $posted, array( $module, 'handle_edit' ) );
ck( 'a stored note ending in CR LF and a contact person ending in a space, posted back unedited, change nothing, say so, and log nothing', array(
	$posted['wpcpm_notes'],
	isset( $r[2] ) ? $r[2] : $r,
	(array) WPCPM_Sponsors_Index::row( $TT ) === $kept,
	count( $GLOBALS['audit'] ) - $audit_before,
), array( "Met at a WordCamp.\r\nWrite again in October.\r\n", 'sponsor-unchanged', true, 0 ) );

// A funded line with a "< 25", quote marks and ampersands: saved, drawn back exactly as typed,
// and saved again unedited, which changes nothing.
$plain  = 'Stipends for "first-time" mentors & Q&A hours, teams < 25 & the \'Developer Track\'';
$r_save = post( array( 'wpcpm_sponsor' => $TT, 'wpcpm_funded_line' => $plain ), array( $module, 'handle_edit' ) );
$saved  = (array) WPCPM_Sponsors_Index::row( $TT );
$drawn  = typed_box( edit_form_of( $TT ), 'funded_line' );
$r_same = post( array( 'wpcpm_sponsor' => $TT, 'wpcpm_funded_line' => null === $drawn ? '' : $drawn['posts'] ), array( $module, 'handle_edit' ) );
ck( 'a funded line with a "< 25", quote marks and ampersands is saved, comes back in its box exactly as typed, and an unedited save changes nothing', array(
	isset( $r_save[2] ) ? $r_save[2] : $r_save,
	null === $drawn ? null : $drawn['shown'],
	null === $drawn ? null : $drawn['maxlength'],
	isset( $r_same[2] ) ? $r_same[2] : $r_same,
	(array) WPCPM_Sponsors_Index::row( $TT ) === $saved,
), array( 'sponsor-saved', $plain, 200, 'sponsor-unchanged', true ) );

echo "\n=== A typing the cleaner would take words from, on the Edit form: refused, and the whole form comes back ===\n";
// The cleaner takes a "<" that white space does not follow, with the words up to the next ">", for a
// tag, and a "%" with two hexadecimal digits for a URL octet, and drops them: "Kids <12 free, adults
// >18 pay" would be stored as "Kids 18 pay". So a save that would lose words in one of the four typed
// boxes is refused before anything is written, the sentence naming the box, and the form comes back
// once as it was posted, every box as typed and every choice as chosen, for the one manager and the
// one sponsor whose form it was. A box posted back as it was drawn is not asked, and keeps its stored
// text whatever it holds. Every other refusal brings the form back too, and a clean save is as before.

/** What a browser posts from a drawn form's markup: every field it holds, as its box shows it. */
function posts_as_drawn( $form ) {
	$posted = array();
	preg_match_all( '#<input\b([^>]*)/>#', (string) $form, $inputs );
	foreach ( $inputs[1] as $attrs ) {
		$type  = preg_match( '#\btype="([^"]*)"#', $attrs, $m ) ? $m[1] : 'text';
		$name  = preg_match( '#\bname="([^"]*)"#', $attrs, $m ) ? html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) : '';
		$value = preg_match( '#\bvalue="([^"]*)"#', $attrs, $m ) ? browser_shows( $m[1] ) : '';
		if ( '' === $name || ( 'checkbox' === $type && 1 !== preg_match( '#\schecked\b#', $attrs ) ) ) {
			continue;
		}
		// A text input drops every line break of its value, and an address's or a link's input its
		// white space at either end too, as a browser cleans each value before it posts.
		if ( in_array( $type, array( 'text', 'email', 'url', 'search', 'tel' ), true ) ) {
			$value = str_replace( array( "\r", "\n" ), '', $value );
			$value = in_array( $type, array( 'email', 'url' ), true ) ? trim( $value, " \t\n\f\r" ) : $value;
		}
		if ( '[]' === substr( $name, -2 ) ) {
			$posted[ substr( $name, 0, -2 ) ][] = $value;
		} else {
			$posted[ $name ] = $value;
		}
	}
	preg_match_all( '#<select\b[^>]*\bname="([^"]*)"[^>]*>(.*?)</select>#s', (string) $form, $selects, PREG_SET_ORDER );
	foreach ( $selects as $select ) {
		preg_match_all( '#<option value="([^"]*)"( selected)?>#', $select[2], $options, PREG_SET_ORDER );
		$chosen = isset( $options[0] ) ? $options[0][1] : '';
		foreach ( $options as $option ) {
			if ( ! empty( $option[2] ) ) {
				$chosen = $option[1];
				break;
			}
		}
		$posted[ $select[1] ] = browser_shows( $chosen );
	}
	preg_match_all( '#<textarea\b[^>]*\bname="([^"]*)"[^>]*>(.*?)</textarea>#s', (string) $form, $areas, PREG_SET_ORDER );
	foreach ( $areas as $area ) {
		// The parser drops one line feed right after the opening tag.
		$text                = 0 === strpos( $area[2], "\n" ) ? substr( $area[2], 1 ) : $area[2];
		$posted[ $area[1] ] = browser_lines( browser_shows( $text, true ) );
	}
	ksort( $posted );
	return $posted;
}
/** What a page's Edit form posts as drawn: that form alone, from its tag to its end. */
function edit_posts_of( $html ) {
	$card = substr( (string) $html, (int) strpos( (string) $html, 'id="wpcpm-sponsor-edit"' ) );
	$form = substr( $card, (int) strpos( $card, '<form ' ) );
	return posts_as_drawn( substr( $form, 0, (int) strpos( $form, '</form>' ) + 7 ) );
}
/** A sponsor's Edit form, drawn on the Sponsors tab, as a browser would post it untouched. */
function edit_posts( $key ) {
	$html           = render_tab( 'sponsors', array( 'edit' => $key ) );
	$GLOBALS['get'] = array();
	return edit_posts_of( $html );
}
/** The fields two posts disagree on, each with the first's value and the second's: empty when they are one post. */
function post_diff( array $first, array $second ) {
	$diff = array();
	foreach ( array_unique( array_merge( array_keys( $first ), array_keys( $second ) ) ) as $field ) {
		$one = array_key_exists( $field, $first ) ? $first[ $field ] : null;
		$two = array_key_exists( $field, $second ) ? $second[ $field ] : null;
		if ( $one !== $two ) {
			$diff[ $field ] = array( $one, $two );
		}
	}
	return $diff;
}

$GLOBALS['uid']          = 1;
$GLOBALS['nonce_ok']     = true;
$GLOBALS['term_renames'] = array();
$LT                      = 'spoLossTyping0001';
$LO                      = 'spoLossOther00001';
$loss_row                = array(
	'name'           => 'Loss Typing Co',
	'website'        => 'https://loss.example/',
	'status'         => 'Approved',
	'contact_person' => 'Rep Loss',
	'contact_email'  => 'maciej+loss@a8c.com',
	'support'        => array( WPCPM_Sponsor_Interests::CHOICES[0] ),
	'tier'           => 'champion',
	'tier_until'     => '2099-09-30',
	'funded_line'    => 'Mentor stipends',
	'mentors'        => array( 'recSEED0000000012' ),
	'notes'          => "Met at a WordCamp.\nWrite again in October.",
);
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), $loss_row, array( 'record_id' => $LT ) ) );
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), $loss_row, array( 'record_id' => $LO, 'name' => 'Loss Other Co' ) ) );
$loss_edit = 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=sponsors&edit=';

// Every field of the form changed, each to a value its rule takes, so that only the box under test
// can refuse the save.
$changes = array(
	'wpcpm_name'            => 'Loss Typing Studio',
	'wpcpm_website'         => 'https://loss.example/changed',
	'wpcpm_status'          => 'Paused',
	'wpcpm_contact_person'  => 'Rep Changed',
	'wpcpm_contact_email'   => 'maciej+changed@a8c.com',
	'wpcpm_option'          => WPCPM_Sponsor_Application::OPTION_CHOICES[0],
	'wpcpm_support'         => array( '', WPCPM_Sponsor_Interests::CHOICES[1], WPCPM_Sponsor_Interests::CHOICES[2] ),
	'wpcpm_product_type'    => WPCPM_Sponsor_Profile::CHOICES['Type of product'][0],
	'wpcpm_manager'         => '2',
	'wpcpm_tier'            => 'backer',
	'wpcpm_tier_until'      => '2100-09-30',
	'wpcpm_event_allowance' => '3',
	'wpcpm_funded_line'     => 'Stipends, changed',
	'wpcpm_mentors'         => array( '', 'recSEED0000000011' ),
	'wpcpm_notes'           => "Changed notes.\r\nOn two lines.",
);
// A tag-like "<" in two of them, a percent octet in another, and a tag across a line break.
$lossy = array(
	'name'           => 'Kids <12 free, adults >18 pay',
	'contact_person' => 'Ana <Sales> Team',
	'funded_line'    => 'Stipends for 10%cashback mentors',
	'notes'          => "Kids <12 free,\r\nadults >18 pay",
);
$loss_status = array(
	'name'           => 'name-loss',
	'contact_person' => 'contact-loss',
	'funded_line'    => 'funded-loss',
	'notes'          => 'notes-loss',
);
$loss_label  = array(
	'name'           => 'Name',
	'contact_person' => 'Contact person',
	'funded_line'    => 'What the sponsorship funds',
	'notes'          => 'Notes',
);

ck( 'each loss refusal says nothing was saved, names the box by its label on the form, and says how to keep every word', array(
	WPCPM_Sponsors::messages()['name-loss'] ?? null,
	WPCPM_Sponsors::messages()['contact-loss'] ?? null,
	WPCPM_Sponsors::messages()['funded-loss'] ?? null,
	WPCPM_Sponsors::messages()['notes-loss'] ?? null,
), array(
	array( 'error', 'Nothing was saved, because WordPress would remove part of what you typed in "Name". To keep every word, put a space after each "<" (or write "less than") and after each "%", then save again.' ),
	array( 'error', 'Nothing was saved, because WordPress would remove part of what you typed in "Contact person". To keep every word, put a space after each "<" (or write "less than") and after each "%", then save again.' ),
	array( 'error', 'Nothing was saved, because WordPress would remove part of what you typed in "What the sponsorship funds". To keep every word, put a space after each "<" (or write "less than") and after each "%", then save again.' ),
	array( 'error', 'Nothing was saved, because WordPress would remove part of what you typed in "Notes". To keep every word, put a space after each "<" (or write "less than") and after each "%", then save again.' ),
) );

foreach ( $lossy as $field => $typing ) {
	$drawn = edit_posts( $LT );
	$other = edit_posts( $LO );
	$typed = array_merge( $drawn, $changes, array( 'wpcpm_' . $field => $typing ) );
	ksort( $typed );
	WPCPM_Sponsors_Index::forget();
	$row_before   = WPCPM_Sponsors_Index::row( $LT );
	$audit_before = count( $GLOBALS['audit'] );
	$r            = post( $typed, array( $module, 'handle_edit' ) );
	WPCPM_Sponsors_Index::forget();
	ck( $field . ': a typing the cleaner would take words from refuses the whole save before anything is written, every other change with it', array(
		$r,
		WPCPM_Sponsors_Index::row( $LT ) === $row_before,
		count( $GLOBALS['audit'] ) - $audit_before,
		$GLOBALS['term_renames'],
	), array( array( 'redirect', $loss_edit . $LT, $loss_status[ $field ] ), true, 0, array() ) );

	// Another sponsor's form, and another manager's view of this one, show what is stored.
	$other_after    = edit_posts( $LO );
	$GLOBALS['uid'] = 2;
	$theirs         = edit_posts( $LT );
	$GLOBALS['uid'] = 1;
	WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r[2] ) ? (string) $r[2] : '' );
	$page           = render_tab( 'sponsors', array( 'edit' => $LT ) );
	$GLOBALS['get'] = array();
	ck( $field . ': the Edit form comes back under the sentence that names the box, with every field as it was posted, each box exactly as typed and each choice as chosen', array(
		notices_in( $page ),
		post_diff( $typed, edit_posts_of( $page ) ),
	), array(
		'<div class="notice notice-error is-dismissible"><p>' . esc_html( WPCPM_Typed_Text::loss_message( $loss_label[ $field ] ) ) . '</p></div>',
		array(),
	) );
	ck( $field . ': another sponsor\'s Edit form and another manager\'s view of this one never see the typing, and drawn again the form shows what is stored', array(
		post_diff( $other, $other_after ),
		post_diff( $drawn, $theirs ),
		post_diff( $drawn, edit_posts( $LT ) ),
	), array( array(), array(), array() ) );
}

// A box drawn holding what the cleaner would take, as a row brought over from the program records can
// hold a percent octet, one of them over its limit: posted back as drawn it is not asked, and keeps its
// stored text, so the save of another field goes through.
$octets = array(
	'name'           => 'Loss Typing Co, 10%cashback',
	'contact_person' => 'Rep 10%cashback ' . str_repeat( 'o', 240 ),
	'funded_line'    => 'Stipends, 50%C3%B3 of them',
	'notes'          => "Get 10%cashback.\nWrite again.",
);
WPCPM_Sponsors_Index::patch( $LT, $octets );
$drawn        = edit_posts( $LT );
$audit_before = count( $GLOBALS['audit'] );
$r            = post( array_merge( $drawn, array( 'wpcpm_website' => 'https://loss.example/octets' ) ), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::forget();
$after = (array) WPCPM_Sponsors_Index::row( $LT ) + WPCPM_Sponsors_Index::empty_row();
ck( 'four boxes drawn holding a percent octet the cleaner would take, one over its limit, are not asked when posted back as drawn: each keeps its stored text, and the save of another field goes through', array(
	isset( $r[2] ) ? $r[2] : $r,
	array( $after['name'], $after['contact_person'], $after['funded_line'], $after['notes'] ),
	$after['website'],
	last_audit()['data'],
), array( 'sponsor-saved', array_values( $octets ), 'https://loss.example/octets', array( 'fields' => array( 'website' ) ) ) );

// Every other refusal brings the whole form back too: a name longer than its limit, typed into a box
// whose room holds a ">" the box shows as `&gt;`, and a contact email that is no address.
$held = 'We &lt;3 our team > all';
WPCPM_Sponsors_Index::patch( $LT, array( 'name' => $held . str_repeat( 'y', 200 - WPCPM_Typed_Text::typed_length( $held ) ) ) );
$drawn = edit_posts( $LT );
foreach ( array(
	'name-too-long'    => array( 'wpcpm_name' => str_repeat( 'z', 203 ) ),
	'sponsor-rejected' => array( 'wpcpm_contact_email' => 'not an address' ),
) as $status => $bad ) {
	$typed = array_merge( $drawn, $changes, $bad );
	ksort( $typed );
	$r = post( $typed, array( $module, 'handle_edit' ) );
	ck( 'refused as ' . $status . ', the form comes back as it was posted too, once', array(
		isset( $r[2] ) ? $r[2] : $r,
		post_diff( $typed, edit_posts( $LT ) ),
		post_diff( $drawn, edit_posts( $LT ) ),
	), array( $status, array(), array() ) );
}

// With a space after each "<", the cleaner keeps every word, and the save goes through as before: the
// boxes come back exactly as typed. A refusal whose page was never drawn leaves nothing behind: its
// typing is dropped when the form is posted again.
$drawn  = edit_posts( $LT );
$spaced = array(
	'wpcpm_name'  => 'Kids < 12 free, adults > 18 pay',
	'wpcpm_notes' => "Kids < 12 free,\r\nadults > 18 pay",
);
$r_unseen = post( array_merge( $drawn, array( 'wpcpm_name' => $lossy['name'], 'wpcpm_website' => 'https://loss.example/lost' ) ), array( $module, 'handle_edit' ) );
$r        = post( array_merge( $drawn, $spaced ), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::forget();
$after = (array) WPCPM_Sponsors_Index::row( $LT ) + WPCPM_Sponsors_Index::empty_row();
$back  = edit_posts( $LT );
ck( 'with a space after each "<" the save goes through, storing every word, and the boxes come back as typed; the refusal before it, never drawn, left nothing', array(
	isset( $r_unseen[2] ) ? $r_unseen[2] : $r_unseen,
	isset( $r[2] ) ? $r[2] : $r,
	array( $after['name'], $after['notes'], $after['website'] ),
	post_diff( array_merge( $drawn, $spaced ), $back ),
	last_audit()['data'],
), array(
	'name-loss',
	'sponsor-saved',
	array( trim( sanitize_text_field( $spaced['wpcpm_name'] ) ), trim( sanitize_textarea_field( $spaced['wpcpm_notes'] ) ), 'https://loss.example/octets' ),
	array(),
	array( 'fields' => array( 'name', 'notes' ) ),
) );

echo "\n=== The Add form keeps the Edit form's rules, and the Website keeps its percent codes ===\n";
// Add a sponsor posts the name, the Contact person and the notes the Edit form posts, and no funded
// line (the Tier box is the Edit form's). A typing the cleaner would take words from is refused
// before anything is added, the box named; every refusal brings the Add form back as it was posted,
// once, to the manager who pressed and under the Add form's own name, never in a sponsor's Edit form.
// The Website, on both forms, is read as typed and cleaned as a web address, so a percent code such
// as `%20` or `%C3%B3` is kept, typed fresh or posted back as drawn.

/** What the Sponsors tab's Add form posts as drawn: that form alone, from its tag to its end. */
function add_posts_of( $html ) {
	$at   = substr( (string) $html, (int) strpos( (string) $html, 'id="wpcpm-sponsor-add"' ) );
	$form = substr( $at, (int) strpos( $at, '<form ' ) );
	return posts_as_drawn( substr( $form, 0, (int) strpos( $form, '</form>' ) + 7 ) );
}
/** The Add form, drawn on the Sponsors tab, as a browser would post it untouched. */
function add_posts() {
	$html           = render_tab( 'sponsors' );
	$GLOBALS['get'] = array();
	return add_posts_of( $html );
}

$GLOBALS['uid'] = 1;
$add_tab        = 'https://example.test/wp-admin/admin.php?page=wpcpm-sponsors&tab=sponsors';
$add_drawn      = add_posts();
$lo_drawn       = edit_posts( $LO );
$add_changes    = array(
	'wpcpm_name'           => 'Added Example',
	'wpcpm_website'        => 'https://added.example/',
	'wpcpm_status'         => 'Paused',
	'wpcpm_contact_person' => 'Rep Added',
	'wpcpm_contact_email'  => 'maciej+added@a8c.com',
	'wpcpm_option'         => WPCPM_Sponsor_Application::OPTION_CHOICES[0],
	'wpcpm_support'        => array( '', WPCPM_Sponsor_Interests::CHOICES[1] ),
	'wpcpm_product_type'   => WPCPM_Sponsor_Profile::CHOICES['Type of product'][0],
	'wpcpm_manager'        => '2',
	'wpcpm_notes'          => "Met at a WordCamp.\r\nWrite in October.",
);
ck( 'the Add form posts the name, the Contact person and the notes, and no funded line', array(
	array_key_exists( 'wpcpm_name', $add_drawn ) && array_key_exists( 'wpcpm_contact_person', $add_drawn ) && array_key_exists( 'wpcpm_notes', $add_drawn ),
	array_key_exists( 'wpcpm_funded_line', $add_drawn ),
), array( true, false ) );

foreach ( array( 'name', 'contact_person', 'notes' ) as $field ) {
	$typed = array_merge( $add_drawn, $add_changes, array( 'wpcpm_' . $field => $lossy[ $field ] ) );
	ksort( $typed );
	$count        = count( WPCPM_Sponsors_Index::rows() );
	$audit_before = count( $GLOBALS['audit'] );
	$r            = post( $typed, array( $module, 'handle_add' ) );
	ck( 'Add, ' . $field . ': a typing the cleaner would take words from refuses the Add before anything is written', array(
		$r,
		count( WPCPM_Sponsors_Index::rows() ) - $count,
		count( $GLOBALS['audit'] ) - $audit_before,
	), array( array( 'redirect', $add_tab, $loss_status[ $field ] ), 0, 0 ) );

	// Another manager's Add form, and a sponsor's Edit form, never see the typing.
	$GLOBALS['uid'] = 2;
	$theirs         = add_posts();
	$GLOBALS['uid'] = 1;
	$edit_after     = edit_posts( $LO );
	WPCPM_Flash::set( WPCPM_Sponsors::FLASH, isset( $r[2] ) ? (string) $r[2] : '' );
	$page           = render_tab( 'sponsors' );
	$GLOBALS['get'] = array();
	ck( 'Add, ' . $field . ': the Sponsors tab comes back under the sentence that names the box, the Add form with every field as it was posted; another manager\'s Add form and a sponsor\'s Edit form show their own, and drawn again the Add form is empty', array(
		notices_in( $page ),
		post_diff( $typed, add_posts_of( $page ) ),
		post_diff( $add_drawn, $theirs ),
		post_diff( $lo_drawn, $edit_after ),
		post_diff( $add_drawn, add_posts() ),
	), array(
		'<div class="notice notice-error is-dismissible"><p>' . esc_html( WPCPM_Typed_Text::loss_message( $loss_label[ $field ] ) ) . '</p></div>',
		array(),
		array(),
		array(),
		array(),
	) );
}

// The length is measured as typed, as on the Edit form (pinned with the typed texts above); a refusal
// for it, or for any other value, brings the Add form back as it was posted too.
foreach ( array(
	'name-too-long'    => array( 'wpcpm_name' => str_repeat( 'n', 200 ) . 'z' ),
	'sponsor-rejected' => array( 'wpcpm_contact_email' => 'not an address' ),
) as $status => $bad ) {
	$typed = array_merge( $add_drawn, $add_changes, $bad );
	ksort( $typed );
	$r = post( $typed, array( $module, 'handle_add' ) );
	ck( 'Add refused as ' . $status . ': the form comes back as it was posted, once', array(
		isset( $r[2] ) ? $r[2] : $r,
		post_diff( 'name-too-long' === $status ? array_merge( $typed, array( 'wpcpm_name' => str_repeat( 'n', 200 ) ) ) : $typed, add_posts() ),
		post_diff( $add_drawn, add_posts() ),
	), array( $status, array(), array() ) );
}

// A clean Add is as before, every word kept; a refusal whose page was never drawn leaves nothing.
$r_unseen = post( array_merge( $add_drawn, $add_changes, array( 'wpcpm_name' => $lossy['name'] ) ), array( $module, 'handle_add' ) );
$before   = array_keys( WPCPM_Sponsors_Index::rows() );
$r        = post( array_merge( $add_drawn, $add_changes, array( 'wpcpm_name' => 'Kids < 12 free, adults > 18 pay' ) ), array( $module, 'handle_add' ) );
$added    = array_values( array_diff( array_keys( WPCPM_Sponsors_Index::rows() ), $before ) );
$add_row  = (array) WPCPM_Sponsors_Index::row( isset( $added[0] ) ? (string) $added[0] : '' ) + WPCPM_Sponsors_Index::empty_row();
ck( 'with a space after each "<" the Add goes through, keeping every word; the refusal before it, never drawn, left nothing in the Add form', array(
	isset( $r_unseen[2] ) ? $r_unseen[2] : $r_unseen,
	isset( $r[2] ) ? $r[2] : $r,
	$add_row['name'],
	post_diff( $add_drawn, add_posts() ),
), array( 'name-loss', 'sponsor-added', 'Kids < 12 free, adults > 18 pay', array() ) );

// The Website on both forms: typed fresh with percent codes, they are kept; a scheme-less address is
// still completed, and one with a name before its host still refused, as the profile card reads its
// own; and one the row holds, posted back as drawn, keeps its stored bytes.
$codes = 'https://loss.example/caf%C3%B3?q=a%20b';
$r     = post( array_merge( edit_posts( $LT ), array( 'wpcpm_website' => $codes ) ), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::forget();
$fresh = ( (array) WPCPM_Sponsors_Index::row( $LT ) )['website'];
$r_bare = post( array_merge( edit_posts( $LT ), array( 'wpcpm_website' => 'loss.example/a%20b' ) ), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::forget();
$bare     = ( (array) WPCPM_Sponsors_Index::row( $LT ) )['website'];
$r_named  = post( array_merge( edit_posts( $LT ), array( 'wpcpm_website' => 'https://ana%20b@loss.example/' ) ), array( $module, 'handle_edit' ) );
$named_at = edit_posts( $LT );
ck( 'the Edit form\'s Website keeps the percent codes typed into it, a scheme-less one is completed, and one with a name before its host is refused', array(
	isset( $r[2] ) ? $r[2] : $r,
	$fresh,
	isset( $r_bare[2] ) ? $r_bare[2] : $r_bare,
	$bare,
	isset( $r_named[2] ) ? $r_named[2] : $r_named,
	$named_at['wpcpm_website'] ?? null,
), array( 'sponsor-saved', $codes, 'sponsor-saved', 'https://loss.example/a%20b', 'sponsor-rejected', 'https://ana%20b@loss.example/' ) );

$held_site = 'loss.example/menu%20caf%C3%B3 ';
WPCPM_Sponsors_Index::patch( $LT, array( 'website' => $held_site ) );
$drawn        = edit_posts( $LT );
$audit_before = count( $GLOBALS['audit'] );
$r            = post( array_merge( $drawn, array( 'wpcpm_notes' => 'Saved beside a stored website.' ) ), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::forget();
ck( 'a Website the row holds with percent codes, no scheme and a space at its end, posted back as drawn, keeps its stored bytes while another field is saved', array(
	$drawn['wpcpm_website'] ?? null,
	isset( $r[2] ) ? $r[2] : $r,
	( (array) WPCPM_Sponsors_Index::row( $LT ) )['website'],
	last_audit()['data'],
), array( $held_site, 'sponsor-saved', $held_site, array( 'fields' => array( 'notes' ) ) ) );

$before  = array_keys( WPCPM_Sponsors_Index::rows() );
$r       = post( array_merge( $add_drawn, $add_changes, array( 'wpcpm_name' => 'Codes Example', 'wpcpm_website' => 'add.example/caf%C3%B3?q=a%20b' ) ), array( $module, 'handle_add' ) );
$added   = array_values( array_diff( array_keys( WPCPM_Sponsors_Index::rows() ), $before ) );
$add_row = (array) WPCPM_Sponsors_Index::row( isset( $added[0] ) ? (string) $added[0] : '' ) + WPCPM_Sponsors_Index::empty_row();
ck( 'the Add form\'s Website keeps the percent codes typed into it too', array( isset( $r[2] ) ? $r[2] : $r, $add_row['website'] ), array( 'sponsor-added', 'https://add.example/caf%C3%B3?q=a%20b' ) );

echo "\n=== One-line boxes drawn from a stored line break or entity post back what is stored ===\n";
// A text input drops the line breaks of its value, and a browser reads every entity in it once.
// Drawn as stored, a name kept with a line break inside it would post back with its words joined,
// and a Website holding `&amp;` would post back the "&" alone. So each stored line break in a
// one-line box is drawn as a space, and the Website with each "&" written `&amp;`: posted back as
// drawn, each reads as unedited and keeps its stored bytes. An edited box is asked as any other.
// The Contact email's box is an address box, whose browser refuses the whole form when its value is
// no address, and `&amp;` drawn as five characters is none: it is drawn as stored, so the box shows
// the address with each entity read, and a post-back that reads the same as the stored address is
// the box untouched, and keeps its stored bytes.

/** Whether a browser's address box takes a value: empty, or one valid e-mail address as HTML defines it. */
function browser_takes_email( $value ) {
	return '' === (string) $value || 1 === preg_match( '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/', (string) $value );
}

$GLOBALS['uid'] = 1;
$broken         = array(
	'name'           => "Caf%C3%A9\nCo",
	'contact_person' => "Rep\r\nLoss",
	'funded_line'    => "Stipends\n\nfor mentors",
);
WPCPM_Sponsors_Index::patch( $LT, $broken );
$drawn        = edit_posts( $LT );
$audit_before = count( $GLOBALS['audit'] );
$r            = post( array_merge( $drawn, array( 'wpcpm_notes' => 'Saved beside a stored line break.' ) ), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::forget();
$after = (array) WPCPM_Sponsors_Index::row( $LT ) + WPCPM_Sponsors_Index::empty_row();
ck( 'a name, a Contact person and a funded line stored with a line break inside are drawn with a space for each break, and posted back as drawn are unchanged and not refused: each keeps its stored bytes', array(
	array( $drawn['wpcpm_name'] ?? null, $drawn['wpcpm_contact_person'] ?? null, $drawn['wpcpm_funded_line'] ?? null ),
	isset( $r[2] ) ? $r[2] : $r,
	array( $after['name'], $after['contact_person'], $after['funded_line'] ),
	last_audit()['data'],
), array(
	array( 'Caf%C3%A9 Co', 'Rep Loss', 'Stipends  for mentors' ),
	'sponsor-saved',
	array_values( $broken ),
	array( 'fields' => array( 'notes' ) ),
) );
$r_codes = post( array_merge( $drawn, array( 'wpcpm_name' => 'Caf%C3%A9 Co Ltd' ) ), array( $module, 'handle_edit' ) );
edit_posts( $LT );
$r_plain = post( array_merge( $drawn, array( 'wpcpm_name' => 'Cafe Co Ltd' ) ), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::forget();
ck( 'the same name edited is asked as usual: a typing holding a percent code is refused, and one the cleaner keeps whole is saved', array(
	isset( $r_codes[2] ) ? $r_codes[2] : $r_codes,
	isset( $r_plain[2] ) ? $r_plain[2] : $r_plain,
	( (array) WPCPM_Sponsors_Index::row( $LT ) )['name'],
), array( 'name-loss', 'sponsor-saved', 'Cafe Co Ltd' ) );

// The fold is the shared one (`WPCPM_Typed_Text::one_line()`), which the profile card and the
// Offers card draw with too: each break a space, a CR LF one break, a lone CR one, a blank line two.
// The Sponsors screen keeps no fold of its own, so the screens cannot come to read a stored break
// two ways.
$folds = array(
	'name'           => "Lone\rCR Co",
	'contact_person' => "Two\r\n\r\nbreaks",
	'funded_line'    => "Mixed\n\r\rkinds",
	'website'        => "https://example.com/a\r\nb",
);
WPCPM_Sponsors_Index::patch( $LT, $folds );
$drawn = edit_posts( $LT );
$r     = post( array_merge( $drawn, array( 'wpcpm_notes' => 'Saved beside four kinds of stored break.' ) ), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::forget();
$after = (array) WPCPM_Sponsors_Index::row( $LT ) + WPCPM_Sponsors_Index::empty_row();
ck( 'a lone CR, a blank line between CR LFs, an LF before two CRs and a CR LF in a link are each drawn a space a break, as WPCPM_Typed_Text::one_line() draws them, and posted back as drawn each keeps its stored bytes', array(
	array( $drawn['wpcpm_name'] ?? null, $drawn['wpcpm_contact_person'] ?? null, $drawn['wpcpm_funded_line'] ?? null, $drawn['wpcpm_website'] ?? null ),
	array( WPCPM_Typed_Text::one_line( $folds['name'] ), WPCPM_Typed_Text::one_line( $folds['contact_person'] ), WPCPM_Typed_Text::one_line( $folds['funded_line'] ), WPCPM_Typed_Text::one_line( $folds['website'] ) ),
	isset( $r[2] ) ? $r[2] : $r,
	array( $after['name'], $after['contact_person'], $after['funded_line'], $after['website'] ),
	last_audit()['data'],
), array(
	array( 'Lone CR Co', 'Two  breaks', 'Mixed   kinds', 'https://example.com/a b' ),
	array( 'Lone CR Co', 'Two  breaks', 'Mixed   kinds', 'https://example.com/a b' ),
	'sponsor-saved',
	array_values( $folds ),
	array( 'fields' => array( 'notes' ) ),
) );
ck( 'and the Sponsors screen keeps no fold of its own', method_exists( 'WPCPM_Sponsors', 'one_line' ), false );

$entities = array(
	'website'       => 'https://example.com/?a=1&amp;b=2',
	'contact_email' => 'contact+a&amp;b@example.test',
);
WPCPM_Sponsors_Index::patch( $LT, $entities );
$drawn        = edit_posts( $LT );
$audit_before = count( $GLOBALS['audit'] );
$r            = post( array_merge( $drawn, array( 'wpcpm_notes' => 'Saved beside a stored entity.' ) ), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::forget();
$after = (array) WPCPM_Sponsors_Index::row( $LT ) + WPCPM_Sponsors_Index::empty_row();
ck( 'a Website stored with an entity posts back the stored bytes as drawn; a Contact email stored with one is drawn as the address it stores, which a browser\'s address box takes, and posted back as drawn it is untouched: the unedited save goes through and keeps both as stored', array(
	array( $drawn['wpcpm_website'] ?? null, $drawn['wpcpm_contact_email'] ?? null ),
	browser_takes_email( $drawn['wpcpm_contact_email'] ?? '' ),
	isset( $r[2] ) ? $r[2] : $r,
	array( $after['website'], $after['contact_email'] ),
	last_audit()['data'],
), array( array( $entities['website'], 'contact+a&b@example.test' ), true, 'sponsor-saved', array_values( $entities ), array( 'fields' => array( 'notes' ) ) ) );
WPCPM_Sponsors_Index::patch( $LT, array( 'contact_email' => 'sam&#43;co@example.org' ) );
$drawn = edit_posts( $LT );
$r     = post( array_merge( $drawn, array( 'wpcpm_notes' => 'Saved beside a numbered entity.' ) ), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::forget();
ck( 'a Contact email stored with a numbered entity, "sam&#43;co@example.org", is drawn as "sam+co@example.org", the address Create account makes for it, and posted back as drawn it is untouched',
	array( $drawn['wpcpm_contact_email'] ?? null, browser_takes_email( $drawn['wpcpm_contact_email'] ?? '' ), isset( $r[2] ) ? $r[2] : $r, ( (array) WPCPM_Sponsors_Index::row( $LT ) )['contact_email'], last_audit()['data'] ),
	array( 'sam+co@example.org', true, 'sponsor-saved', 'sam&#43;co@example.org', array( 'fields' => array( 'notes' ) ) ) );
WPCPM_Sponsors_Index::patch( $LT, array( 'contact_email' => $entities['contact_email'] ) );
$r_mail = post( array_merge( edit_posts( $LT ), array( 'wpcpm_contact_email' => 'maciej+ab@a8c.com' ) ), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::forget();
ck( 'that Contact email edited is an edit as before: a new address is saved, and the trail names the field', array(
	isset( $r_mail[2] ) ? $r_mail[2] : $r_mail,
	( (array) WPCPM_Sponsors_Index::row( $LT ) )['contact_email'],
	last_audit()['data'],
), array( 'sponsor-saved', 'maciej+ab@a8c.com', array( 'fields' => array( 'contact_email' ) ) ) );

WPCPM_Sponsors_Index::patch( $LT, array( 'website' => "https://loss.example/a\nb" ) );
$drawn = edit_posts( $LT );
$r     = post( array_merge( $drawn, array( 'wpcpm_notes' => 'Saved beside a broken link.' ) ), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::forget();
ck( 'a Website stored with a line break is drawn with a space, and posted back as drawn keeps its stored bytes', array(
	$drawn['wpcpm_website'] ?? null,
	isset( $r[2] ) ? $r[2] : $r,
	( (array) WPCPM_Sponsors_Index::row( $LT ) )['website'],
	last_audit()['data'],
), array( 'https://loss.example/a b', 'sponsor-saved', "https://loss.example/a\nb", array( 'fields' => array( 'notes' ) ) ) );

// The same row bin/test-sponsor-cards.php draws the profile card with: the Website, the Contact
// person and the Contact email are boxes of both, and read one way on both, drawn with the same
// values there and here, and posted back untouched, neither screen writes them.
$same_row = array(
	'website'        => "https://example.com/?a=1&amp;b=2\r\nabout",
	'contact_person' => "Bob &amp; Co\r\n\r\nSales",
	'contact_email'  => 'contact+a&amp;b@example.test',
);
WPCPM_Sponsors_Index::patch( $LT, $same_row );
$drawn = edit_posts( $LT );
$r     = post( array_merge( $drawn, array( 'wpcpm_notes' => 'Saved beside the profile card\'s boxes.' ) ), array( $module, 'handle_edit' ) );
WPCPM_Sponsors_Index::forget();
$after = (array) WPCPM_Sponsors_Index::row( $LT ) + WPCPM_Sponsors_Index::empty_row();
ck( 'the Edit form draws the Website, the Contact person and the Contact email as the profile card draws them, and posted back as drawn keeps all three as stored', array(
	array( $drawn['wpcpm_website'] ?? null, $drawn['wpcpm_contact_person'] ?? null, $drawn['wpcpm_contact_email'] ?? null ),
	isset( $r[2] ) ? $r[2] : $r,
	array( $after['website'], $after['contact_person'], $after['contact_email'] ),
	last_audit()['data'],
), array( array( 'https://example.com/?a=1&amp;b=2 about', 'Bob & Co  Sales', 'contact+a&b@example.test' ), 'sponsor-saved', array_values( $same_row ), array( 'fields' => array( 'notes' ) ) ) );

echo "\n=== House rules ===\n";
$src = file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors.php' );
ck( 'no em or en dash', preg_match( '/\x{2013}|\x{2014}/u', $src ), 0 );
// The words every tab's frame prints, and every card this class draws, are the class's own, so its
// source is where a sentence naming Airtable or the base would come back.
ck( 'and the class names neither Airtable nor the base, and reports no sync error', array( strpos( $src, 'Airtable' ), strpos( $src, 'the base' ), strpos( $src, 'Last sync error' ) ), array( false, false, false ) );
// Provisioning's outcomes and the accounts list's words for them are the class's and the table's, so
// a status, a count or a sentence for telling Airtable would come back in one of the two sources.
$table_src = (string) file_get_contents( __DIR__ . '/../includes/modules/class-wpcpm-sponsors-table.php' );
ck( 'and neither the class nor the accounts table keeps a status, a count or a sentence for Airtable: neither source holds the word, in any case', array( stripos( $src, 'airtable' ), stripos( $table_src, 'airtable' ) ), array( false, false ) );
ck( 'accounts come from WPCPM_Roles::insert_user() alone', strpos( $src, 'wp_insert_user(' ), false );
ck( 'activate() sets the repair flag itself, so a fresh install never queries for accounts it cannot have', false !== strpos( method_body( $src, 'activate' ), 'update_option( WPCPM_Sponsor_Members::OPT_CAPS_REPAIRED, 1, true )' ), true );
ck( 'the screen never constructs WPCPM_Airtable: the class does not even exist in this run', class_exists( 'WPCPM_Airtable' ), false );

ck( 'and the screen asked the meta matcher or get_bloginfo() nothing they do not model', $GLOBALS['unmodeled'], array() );

echo "\n=== Every invitation under the account's lock (1.123.14) ===\n";

// The private line takes public 1.122.32: one invitation at a time per person, sent and stamped by
// WPCPM_Mail::send_invite_now() under the account's lock. Read off the plugin's own code, comments
// left aside: WordPress's set-your-password email is sent, and an invitation stamped, inside that
// method alone, so no route of this line's own (the Sponsors screen's Resend, the sponsor members'
// invitations, approval, Create account, the bulk actions) can send around the lock; the screen's
// Resend hands the account to the members class, which hands it to that method.
$invite_calls = array();
$plugin_root  = dirname( __DIR__ ) . '/';
$walk         = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_root . 'includes', FilesystemIterator::SKIP_DOTS ) );

foreach ( $walk as $file ) {
	if ( '.php' !== substr( $file->getFilename(), -4 ) ) {
		continue;
	}

	$code   = (string) file_get_contents( $file->getPathname() );
	$tokens = array_values( array_filter( token_get_all( $code ), static function ( $t ) {
		return ! is_array( $t ) || ! in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true );
	} ) );
	$method = '';

	foreach ( $tokens as $i => $t ) {
		if ( is_array( $t ) && T_FUNCTION === $t[0] && isset( $tokens[ $i + 1 ] ) && is_array( $tokens[ $i + 1 ] ) ) {
			$method = $tokens[ $i + 1 ][1];
		}

		if ( ! is_array( $t ) || T_STRING !== $t[0] || ! in_array( $t[1], array( 'wp_new_user_notification', 'stamp_invited' ), true ) ) {
			continue;
		}

		$before = isset( $tokens[ $i - 1 ] ) ? $tokens[ $i - 1 ] : '';
		$after  = isset( $tokens[ $i + 1 ] ) ? $tokens[ $i + 1 ] : '';

		if ( '(' !== $after || ( is_array( $before ) && T_FUNCTION === $before[0] ) ) {
			continue;
		}

		$invite_calls[] = substr( $file->getPathname(), strlen( $plugin_root ) ) . ' ' . $method . '() calls ' . $t[1] . '()';
	}
}

sort( $invite_calls );
$screen_source = (string) file_get_contents( $plugin_root . 'includes/modules/class-wpcpm-sponsors.php' );
$invite_body   = substr( $screen_source, (int) strpos( $screen_source, 'protected function invite_one(' ) );
$invite_body   = substr( $invite_body, 0, (int) strpos( $invite_body, "\n\t}\n" ) );
ck( 'the plugin sends the invitation email and stamps an invitation in send_invite_now() alone, and the Sponsors screen\'s Resend reaches it through the members class', array( $invite_calls, trim( substr( $invite_body, (int) strpos( $invite_body, '{' ) + 1 ) ) ), array(
	array( 'includes/class-wpcpm-mail.php send_invite_now() calls stamp_invited()', 'includes/class-wpcpm-mail.php send_invite_now() calls wp_new_user_notification()' ),
	'return WPCPM_Sponsor_Members::send_invite( $user_id );',
) );

echo "\n=== Sponsor offers to review, on Waiting for review (1.123.18) ===\n";
// The review of sponsor offers has a card of its own on the queue tab, right after the posts', read
// to be decided on the Administrator Dashboard, and the menu bubble counts what waits there too. Its
// class is stood in here, at the end of the file, so every queue tab drawn above draws as it did:
// declared inside this function, it does not exist until the function runs.
function load_offer_review_stand_in() {
	if ( class_exists( 'WPCPM_Sponsor_Offer_Review' ) ) {
		return;
	}

	class WPCPM_Sponsor_Offer_Review {
		public static function waiting_all( $limit = 50 ) {
			return array_slice( isset( $GLOBALS['offer_reviews'] ) ? $GLOBALS['offer_reviews'] : array(), 0, max( 1, (int) $limit ) );
		}
	}
}
WPCPM_Sponsors::forget_attention();
$bubble_without = WPCPM_Sponsors::attention_count();
load_offer_review_stand_in();
$GLOBALS['offer_reviews'] = array(
	array( 'id' => 931, 'record' => 'recSPONSOR0000001', 'company' => 'Kappa <b>Example</b>', 'title' => 'A year & "more"', 'change' => 'new', 'by' => 'Member One', 'at' => time() - 600, 'fields' => array() ),
	array( 'id' => 932, 'record' => 'recSPONSOR0000001', 'company' => 'Kappa Example', 'title' => 'Hosting for students', 'change' => 'changes', 'by' => '', 'at' => time() - 300, 'fields' => array() ),
);
$offers_tab  = render_tab( 'queue' );
$offers_card = card_body( $offers_tab, 'wpcpm-sponsor-offers-review' );
$offers_ids  = array_column( counted_cards_of( $offers_tab ), 0 );
ck( 'the queue tab draws Sponsor offers to review right after the posts\' card, counting everything waiting', array( array_search( 'wpcpm-sponsor-offers-review', $offers_ids, true ) - array_search( 'wpcpm-sponsor-posts', $offers_ids, true ), array_values( array_filter( counted_cards_of( $offers_tab ), static function ( $card ) { return 'wpcpm-sponsor-offers-review' === $card[0]; } ) ) ), array( 1, array( array( 'wpcpm-sponsor-offers-review', 'Sponsor offers to review', '2' ) ) ) );
ck( 'it says what it lists and where each is decided, and each row says whether it is new or changes, whose, who sent it, how long ago, escaped, and links its own item on the Administrator Dashboard', array(
	false !== strpos( $offers_card, '<p class="description">Offers that sponsors sent for review, and changes they made to what students see of live ones, oldest first. Each is approved or returned on the Administrator Dashboard.</p>' ),
	false !== strpos( $offers_card, '<h3 class="wpcpm-queue-title">A year &amp; &quot;more&quot;</h3>' ),
	false !== strpos( $offers_card, 'A new offer from Kappa &lt;b&gt;Example&lt;/b&gt;, sent by Member One ' ),
	false !== strpos( $offers_card, 'Changes to a live offer of Kappa Example, sent by somebody whose account is gone ' ),
	false !== strpos( $offers_card, '#wpcpm-sponsor-offer-931' ) && false !== strpos( $offers_card, '#wpcpm-sponsor-offer-932' ),
	strpos( $offers_card, '<b>' ),
), array( true, true, true, true, true, false ) );
WPCPM_Sponsors::forget_attention();
ck( 'the menu bubble counts the offers and changes waiting for review', WPCPM_Sponsors::attention_count() - $bubble_without, 2 );
$GLOBALS['offer_reviews'] = array();
ck( 'with nothing waiting the card says so', false !== strpos( card_body( render_tab( 'queue' ), 'wpcpm-sponsor-offers-review' ), '<p>Nothing is waiting.</p>' ), true );
unset( $GLOBALS['offer_reviews'] );

echo "\n=== A test sponsor, never listed publicly (1.123.18) ===\n";
// An Administrator sets or clears the flag on the Edit form and on the Add form, as one box right
// after Status, and the Sponsors card's list says which rows are test sponsors. The box posts a
// hidden 0 before it, as each list on the forms posts its hidden empty value, so a form that drew
// it says what it holds, ticked or not, and a post that does not carry it leaves the flag alone.
$GLOBALS['uid']      = 1;
$GLOBALS['nonce_ok'] = true;
$TS                  = 'spoTestSponsor001';
WPCPM_Sponsors_Index::insert( array_merge( WPCPM_Sponsors_Index::empty_row(), array( 'record_id' => $TS, 'name' => 'Flag Example', 'status' => 'Approved', 'tier' => 'mentor' ) ) );
/** The test sponsor's row of a form, as drawn: ticked or not. */
function test_flag_row( $form, $ticked ) {
	return '<tr><th scope="row">Test sponsor</th><td><input type="hidden" name="wpcpm_test" value="0" /><label for="wpcpm-' . $form . '-test"><input type="checkbox" id="wpcpm-' . $form . '-test" name="wpcpm_test" value="1"' . ( $ticked ? ' checked' : '' ) . ' /> Test sponsor: not listed publicly</label><p class="description">A test sponsor is left out of the Partners page, the logo strip, the handbook listing and the public feed, and its offers, posts and events are shown to no student or mentor. Its own accounts still see its Sponsor Dashboard.</p></td></tr>';
}
$ts_form = edit_form_of( $TS );
ck( 'the Edit form draws the flag clear for a sponsor that is not one, right after Status and before the contact person', array(
	false !== strpos( $ts_form, test_flag_row( 'edit', false ) ),
	(int) strpos( $ts_form, 'name="wpcpm_status"' ) < (int) strpos( $ts_form, 'name="wpcpm_test"' ) && (int) strpos( $ts_form, 'name="wpcpm_test"' ) < (int) strpos( $ts_form, 'name="wpcpm_contact_person"' ),
), array( true, true ) );
$ts_posts               = edit_posts( $TS );
$ts_posts['wpcpm_test'] = '1';
$audit_before           = count( $GLOBALS['audit'] );
$r                      = post( $ts_posts, array( $module, 'handle_edit' ) );
ck( 'ticked and saved, the sponsor is a test sponsor, and the trail names the field', array( $r[2], WPCPM_Sponsors_Index::row( $TS )['test'], count( $GLOBALS['audit'] ) - $audit_before, last_audit()['data'] ), array( 'sponsor-saved', true, 1, array( 'fields' => array( 'test' ) ) ) );
ck( 'drawn again the box is ticked, and the form posted as drawn changes nothing', array( false !== strpos( edit_form_of( $TS ), test_flag_row( 'edit', true ) ), post( edit_posts( $TS ), array( $module, 'handle_edit' ) )[2], WPCPM_Sponsors_Index::row( $TS )['test'] ), array( true, 'sponsor-unchanged', true ) );
ck( 'a post that does not carry the box leaves the flag as it is', array( post( array_diff_key( edit_posts( $TS ), array( 'wpcpm_test' => true ) ), array( $module, 'handle_edit' ) )[2], WPCPM_Sponsors_Index::row( $TS )['test'] ), array( 'sponsor-unchanged', true ) );
ck( 'a value the box never posts is refused, and the flag stays', array( post( array_merge( edit_posts( $TS ), array( 'wpcpm_test' => 'yes' ) ), array( $module, 'handle_edit' ) )[2], WPCPM_Sponsors_Index::row( $TS )['test'] ), array( 'sponsor-rejected', true ) );
$ts_table = render_tab( 'sponsors' );
ck( 'the Sponsors card\'s list marks the row a test sponsor, and no other row', array( substr_count( $ts_table, '<span class="description">(test sponsor, not listed publicly)</span>' ), false !== strpos( $ts_table, 'Flag Example <span class="description">(test sponsor, not listed publicly)</span></td>' ) || false !== strpos( $ts_table, 'Flag Example</a> <span class="description">(test sponsor, not listed publicly)</span></td>' ) ), array( 1, true ) );
$ts_posts               = edit_posts( $TS );
$ts_posts['wpcpm_test'] = '0';
ck( 'unticked and saved, it is cleared', array( post( $ts_posts, array( $module, 'handle_edit' ) )[2], WPCPM_Sponsors_Index::row( $TS )['test'] ), array( 'sponsor-saved', false ) );

$ts_add = render_tab( 'sponsors' );
$ts_add = substr( $ts_add, (int) strpos( $ts_add, 'name="action" value="' . WPCPM_Sponsors::ACTION_ADD . '"' ) );
ck( 'the Add form draws the box too, clear', false !== strpos( $ts_add, test_flag_row( 'add', false ) ), true );
$ts_before = array_keys( WPCPM_Sponsors_Index::rows() );
$ts_fields = form_fields_of( render_tab( 'sponsors' ), WPCPM_Sponsors::ACTION_ADD );
$r         = post( array_merge( isset( $ts_fields[0] ) ? $ts_fields[0] : array(), array( 'wpcpm_name' => 'Added Under Test', 'wpcpm_test' => '1' ) ), array( $module, 'handle_add' ) );
$ts_added  = array_values( array_diff( array_keys( WPCPM_Sponsors_Index::rows() ), $ts_before ) );
ck( 'and a sponsor added with it ticked is a test sponsor from the start', array( $r[2], isset( $ts_added[0] ) ? WPCPM_Sponsors_Index::row( $ts_added[0] )['test'] : null ), array( 'sponsor-added', true ) );

printf( "\n%s (%d checks)\n", $fail ? "$fail FAILED" : 'ALL PASS', $checks );
exit( $fail ? 1 : 0 );
