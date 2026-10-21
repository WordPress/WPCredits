<?php
/**
 * The plugin's emails as text: the template registry.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What each of the plugin's emails says, with `{placeholders}` where its values go.
 *
 * An email's name, its area and its audience are the catalog's (`WPCPM_Mail_Catalog`), which stays
 * the one list of them. The registry adds to a catalog id what makes the email editable: its subject
 * and body as shipped, the placeholders they use, and one sentence saying what sends it. Each area's
 * emails are defined in a file of their own under includes/mail/templates/, which returns them, and
 * an area joins `AREAS` when its emails move here.
 *
 * The shipped text is the wording the code wrote before the move, word for word. It is built with
 * `__()` once a request for each language (`all()`), so it is in the language the email is being
 * built in, the recipient's, as it was before.
 *
 * Filling a text (`fill()`):
 *
 * - every placeholder is replaced by its value as plain text, in one pass, so a value is never read
 *   as a placeholder itself, and a placeholder the email does not have is left as it was written;
 * - every value is read as a person reads it: a name or a title WordPress stores with its "&", "<"
 *   and ">" written as entities, an account's name and an application's title among them, arrives
 *   as the person typed it, read back as `WPCPM_Typed_Text::mail_text()` reads a typed text, once
 *   and no deeper, for every email of every area (since 1.122.23; a value with no entity in it is
 *   unchanged by it). So a builder hands each value over as stored, a typed text and the site's
 *   name (`WPCPM_Mail::site_name()`) among them: one it read back first would be read back twice,
 *   and a typed `&amp;lt;` would arrive as "<";
 * - in a body, a sentence whose placeholders are all optional and all empty is left out, and so is a
 *   line left with nothing on it, as the code left out a line with nothing to say before the move;
 *   a line ending in a colon, holding no placeholder once its own left-out sentences are taken out,
 *   directly above a line left out goes with it, since it introduced that line, while a colon line
 *   that still holds a placeholder always stays; and a blank line that the lines left out would
 *   leave doubled, or at the start or the end, goes too;
 * - a sentence holding a required placeholder is never left out, and neither is its line.
 *
 * A sentence ends at a full stop followed by a space, so "Questions? Write to {program_contact}." is
 * one sentence: a question or an exclamation mark ends none, and the full stop of "e.g." followed by
 * a space ends one. A subject is filled and never shortened, and is kept to one line: each line
 * break in it, in the text itself or in a value, is one space, since a subject is a header.
 *
 * A placeholder may go in the body alone (`body_only`), as the set-password link does: a subject is
 * never a link a person can follow, and it is the one part of an email the mail log keeps.
 */
final class WPCPM_Mail_Templates {

	/**
	 * The areas whose emails the registry holds, each with its file under includes/mail/templates/.
	 *
	 * @var array<string,string>
	 */
	const AREAS = array(
		WPCPM_Mail_Catalog::MODULE_INVITATIONS  => 'invitations',
		WPCPM_Mail_Catalog::MODULE_CALLS        => 'calls',
		WPCPM_Mail_Catalog::MODULE_INSTITUTIONS => 'institutions',
		WPCPM_Mail_Catalog::MODULE_REPORTS      => 'reports',
		WPCPM_Mail_Catalog::MODULE_SPONSORS     => 'sponsors',
		WPCPM_Mail_Catalog::MODULE_EVENTS       => 'events',
		WPCPM_Mail_Catalog::MODULE_MENTOR_APPS  => 'mentor-applications',
	);

	/** What a placeholder looks like: a word in braces. */
	const PLACEHOLDER = '/\{([A-Za-z0-9_-]+)\}/';

	/** The option holding the Administrators' edits, not autoloaded. */
	const OPTION = 'wpcpm_mail_templates';

	/** The most characters a subject may have. */
	const SUBJECT_MAX = 200;

	/** The most characters a body may have. */
	const BODY_MAX = 10000;

	/** An email sent as the registry ships it. */
	const STATE_SHIPPED = 'shipped';

	/** An email sent as an Administrator edited it. */
	const STATE_EDITED = 'edited';

	/** An edited email whose shipped text an update has changed since the edit was saved. */
	const STATE_CHANGED = 'changed';

	/** An edit that no longer fits its email, so the shipped text is sent. */
	const STATE_SET_ASIDE = 'set-aside';

	/** The signature and footer, which is edited, restored and stored as a template is, under this name. */
	const FOOTER = 'footer';

	/**
	 * Every template, by id, in the order of `AREAS` and, within an area, of its file: the catalog's
	 * name, area and audience, then the registry's own fields. An id the catalog does not hold is left
	 * out, since it has no name to show.
	 *
	 * Built once a request for each language it is asked in, and kept for the rest of the request, so
	 * the shipped text is in the language of the moment: an email is built in its recipient's, and a
	 * request that sends emails in two languages builds the registry twice, once in each.
	 *
	 * @return array<string,array{id:string,label:string,module:string,audience:string,trigger:string,subject:string,body:string,eol:string,placeholders:array}>
	 */
	public static function all() {
		static $built = array();

		$locale = self::locale();

		if ( isset( $built[ $locale ] ) ) {
			return $built[ $locale ];
		}

		$out = array();

		foreach ( self::AREAS as $file ) {
			$defined = self::area( $file );

			foreach ( (array) $defined as $id => $entry ) {
				$named = WPCPM_Mail_Catalog::get( $id );

				if ( null === $named || ! is_array( $entry ) ) {
					continue;
				}

				$out[ $id ] = array(
					'id'           => (string) $id,
					'label'        => $named['label'],
					'module'       => $named['module'],
					'audience'     => $named['audience'],
					'trigger'      => isset( $entry['trigger'] ) ? (string) $entry['trigger'] : '',
					'subject'      => isset( $entry['subject'] ) ? (string) $entry['subject'] : '',
					'body'         => isset( $entry['body'] ) ? (string) $entry['body'] : '',
					'eol'          => isset( $entry['eol'] ) && "\r\n" === $entry['eol'] ? "\r\n" : "\n",
					'placeholders' => isset( $entry['placeholders'] ) ? (array) $entry['placeholders'] : array(),
				);
			}
		}

		$built[ $locale ] = $out;

		return $out;
	}

	/**
	 * One area's emails, as its file under includes/mail/templates/ returns them, read in a scope of its
	 * own: a variable one file sets is never seen by another, so each file names its helpers as it
	 * likes and none can lean on another's by accident.
	 *
	 * @param string $file The area's file, without its extension.
	 * @return mixed What the file returns: its emails, by id.
	 */
	private static function area( $file ) {
		return require __DIR__ . '/templates/' . $file . '.php';
	}

	/**
	 * The language the shipped text is built in now: the one WordPress loads translations in, which a
	 * switch into a recipient's language changes.
	 *
	 * @return string A locale, such as `en_US`; '' where WordPress cannot say.
	 */
	private static function locale() {
		if ( function_exists( 'determine_locale' ) ) {
			return (string) determine_locale();
		}

		return function_exists( 'get_locale' ) ? (string) get_locale() : '';
	}

	/**
	 * One template.
	 *
	 * @param string $id The email's id in the catalog.
	 * @return array|null The entry `all()` holds for it, or null when the registry has none.
	 */
	public static function get( $id ) {
		$all = self::all();

		return isset( $all[ (string) $id ] ) ? $all[ (string) $id ] : null;
	}

	/**
	 * The placeholders a text uses, each once, in the order they first appear.
	 *
	 * @param string $text A subject or a body.
	 * @return string[] Their names, without the braces.
	 */
	public static function placeholders_in( $text ) {
		preg_match_all( self::PLACEHOLDER, (string) $text, $found );

		return array_values( array_unique( $found[1] ) );
	}

	/**
	 * The placeholders an email cannot be sent without.
	 *
	 * @param array $placeholders A template's placeholders, name to what `all()` holds for each.
	 * @return string[] Their names.
	 */
	public static function required( array $placeholders ) {
		$out = array();

		foreach ( $placeholders as $name => $about ) {
			if ( ! empty( $about['required'] ) ) {
				$out[] = (string) $name;
			}
		}

		return $out;
	}

	/**
	 * The placeholders that go in an email's body and never in its subject: a subject is a header,
	 * never a link a person can follow, and the one part of an email the mail log keeps.
	 *
	 * @param array $placeholders A template's placeholders, name to what `all()` holds for each.
	 * @return string[] Their names.
	 */
	public static function body_only( array $placeholders ) {
		$out = array();

		foreach ( $placeholders as $name => $about ) {
			if ( ! empty( $about['body_only'] ) ) {
				$out[] = (string) $name;
			}
		}

		return $out;
	}

	/**
	 * A text with its values in place, by the rules the class docblock gives.
	 *
	 * @param string $text         A subject or a body, with `{placeholders}`.
	 * @param array  $values       Each placeholder's value, by name; one not given is empty.
	 * @param array  $placeholders The email's placeholders, name to what `all()` holds for each.
	 * @param string $eol          The line ending the result is written with.
	 * @param bool   $shorten      Whether empty optional sentences, and what they leave, are left out:
	 *                             true for a body, false for a subject, which is kept to one line,
	 *                             each line break in its text or in a value one space.
	 * @return string
	 */
	public static function fill( $text, array $values, array $placeholders, $eol = "\n", $shorten = true ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );

		if ( $shorten ) {
			$text = self::shorten( $text, $values, $placeholders );
		}

		$pairs = array();

		foreach ( self::placeholders_in( $text ) as $name ) {
			if ( isset( $placeholders[ $name ] ) ) {
				// As a person reads it: an entity WordPress stored a name or a title with, read back.
				$value = isset( $values[ $name ] ) ? wp_specialchars_decode( (string) $values[ $name ], ENT_QUOTES ) : '';

				$pairs[ '{' . $name . '}' ] = $shorten ? $value : (string) preg_replace( '/\r\n|\r|\n/', ' ', $value );
			}
		}

		return strtr( str_replace( "\n", $shorten ? $eol : ' ', $text ), $pairs );
	}

	/**
	 * A body with what its empty optional placeholders leave out taken out: the sentences, the lines
	 * left with nothing, the colon lines above them, and the blank lines they leave doubled or at an
	 * end. The placeholders are still in the text; `fill()` puts the values in after.
	 *
	 * @param string $text         The body, its line endings "\n".
	 * @param array  $values       Each placeholder's value, by name.
	 * @param array  $placeholders The email's placeholders.
	 * @return string
	 */
	private static function shorten( $text, array $values, array $placeholders ) {
		$ends  = "\n" === substr( $text, -1 );
		$lines = explode( "\n", $ends ? substr( $text, 0, -1 ) : $text );
		$kept  = array();

		// Whether a placeholder may go: one the email has, optional, and empty.
		$may_go = static function ( $name ) use ( $values, $placeholders ) {
			return isset( $placeholders[ $name ] ) && empty( $placeholders[ $name ]['required'] ) && ( ! isset( $values[ $name ] ) || '' === (string) $values[ $name ] );
		};

		foreach ( $lines as $n => $line ) {
			$kept[ $n ] = $line;
			$names      = self::placeholders_in( $line );

			if ( array() === $names || array() === array_filter( $names, $may_go ) ) {
				continue;
			}

			preg_match( '/^[ \t]*/', $line, $indent );

			$parts = preg_split( '/(?<=\.)( +)/', substr( $line, strlen( $indent[0] ) ), -1, PREG_SPLIT_DELIM_CAPTURE );
			$left  = '';
			$space = '';

			foreach ( $parts as $i => $part ) {
				if ( 1 === $i % 2 ) {
					$space = $part;
					continue;
				}

				$in = self::placeholders_in( $part );

				if ( array() !== $in && count( array_filter( $in, $may_go ) ) === count( $in ) ) {
					continue;
				}

				$left .= ( '' === $left ? '' : $space ) . $part;
			}

			$kept[ $n ] = '' === trim( $left ) ? null : $indent[0] . $left;
		}

		// A line ending in a colon, holding no placeholder, directly above a line left out, introduced
		// it, and goes with it. One that still holds a placeholder, a required one or one whose value
		// prints, says something of its own and stays.
		foreach ( array_keys( $kept ) as $n ) {
			if ( null === $kept[ $n ] && $n > 0 && null !== $kept[ $n - 1 ] && ':' === substr( rtrim( $kept[ $n - 1 ] ), -1 ) && array() === self::placeholders_in( $kept[ $n - 1 ] ) ) {
				$kept[ $n - 1 ] = null;
			}
		}

		// Each run of lines left out: a blank line before it goes when a blank line or the end comes
		// after it, and a blank line after it goes when the run opened the text.
		$count = count( $kept );

		for ( $n = 0; $n < $count; $n++ ) {
			if ( null !== $kept[ $n ] || ( $n > 0 && null === $kept[ $n - 1 ] ) ) {
				continue;
			}

			$after = $n;

			while ( $after < $count && null === $kept[ $after ] ) {
				++$after;
			}

			$before = $n - 1;

			while ( $before >= 0 && null === $kept[ $before ] ) {
				--$before;
			}

			if ( $before >= 0 && '' === trim( $kept[ $before ] ) && ( $after >= $count || '' === trim( $kept[ $after ] ) ) ) {
				$kept[ $before ] = null;
			} elseif ( $before < 0 && $after < $count && '' === trim( $kept[ $after ] ) ) {
				$kept[ $after ] = null;
			}
		}

		$out = array_filter(
			$kept,
			static function ( $line ) {
				return null !== $line;
			}
		);

		return implode( "\n", $out ) . ( $ends ? "\n" : '' );
	}

	/**
	 * An email's subject and body as it goes out, filled with its values: the Administrators' edit
	 * when one fits, the shipped text otherwise (`text_of()`). No footer: the signature and footer is
	 * added once, where the email leaves, never by a builder.
	 *
	 * An edit whose subject fills to nothing, each of its words an optional placeholder that came out
	 * empty, is sent with the shipped subject, filled, since the mail layer sends no email without a
	 * subject. The subject is tested as `sanitize_text_field()` cleans it, which also takes out a run
	 * that reads as a tag: so an edit whose subject fills to such a run alone, as `<b></b>`, is sent
	 * with the shipped subject too. The mail layer would have sent that one: every email the
	 * registry fills goes out with a plain-text subject since 1.122.23 (`WPCPM_Mail::hand_off()`),
	 * whose cleaner keeps a "<" and what follows it. The test stays the stricter one, written before
	 * 1.122.23, when the mail layer would have emptied such a subject after `hand_off()` checked it.
	 *
	 * @param string $id     The email's id.
	 * @param array  $values Each placeholder's value, by name, worked out by whatever sends it.
	 * @return array{subject:string,body:string}|null Null when the registry has no such email.
	 */
	public static function render( $id, array $values ) {
		$entry = self::get( $id );

		if ( null === $entry ) {
			return null;
		}

		$text    = self::text_of( $id );
		$subject = self::fill( $text['subject'], $values, $entry['placeholders'], $entry['eol'], false );

		// As `sanitize_text_field()` cleans it: a value it reads as a tag leaves nothing either.
		if ( '' === trim( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $subject ) : $subject ) ) {
			$subject = self::fill( $entry['subject'], $values, $entry['placeholders'], $entry['eol'], false );
		}

		return array(
			'subject' => $subject,
			'body'    => self::fill( $text['body'], $values, $entry['placeholders'], $entry['eol'] ),
		);
	}

	/**
	 * An email as a builder of `WPCPM_Mail::send()` hands it to the mail layer: its subject and body as
	 * `render()` fills them with the values given, beside what stays in the sender's code (its Reply-To,
	 * and whether its subject is plain text). With no such template the subject is empty, so the mail
	 * layer sends nothing.
	 *
	 * @param string $id     The email's id.
	 * @param array  $values Each placeholder's value, by name, worked out in the recipient's language.
	 * @param array  $extra  What the builder hands over beside the subject and the body.
	 * @return array
	 */
	public static function mail( $id, array $values, array $extra = array() ) {
		$mail = self::render( $id, $values );

		if ( null === $mail ) {
			$mail = array(
				'subject' => '',
				'body'    => '',
			);
		}

		return array_merge( $mail, $extra );
	}

	/**
	 * The values an email is previewed and tested with: each placeholder's example, worked out now
	 * where a definition gives a function for it (the site's name, its login page, a dashboard).
	 *
	 * @param string $id The email's id.
	 * @return array<string,string> Name to example; empty when the registry has no such email.
	 */
	public static function example_values( $id ) {
		$entry = self::get( $id );

		return null === $entry ? array() : self::examples_of( $entry['placeholders'] );
	}

	/**
	 * Each placeholder's example: a string as it is, a closure called now.
	 *
	 * @param array $placeholders Name to what `all()` holds for each.
	 * @return array<string,string>
	 */
	public static function examples_of( array $placeholders ) {
		$out = array();

		foreach ( $placeholders as $name => $about ) {
			$example      = isset( $about['example'] ) ? $about['example'] : '';
			$out[ $name ] = $example instanceof Closure ? (string) $example() : (string) $example;
		}

		return $out;
	}

	/**
	 * The address of the dashboard page a kind of recipient has, while its page is published.
	 *
	 * @param string $type One of the catalog's recipient types (`WPCPM_Mail_Catalog::TYPE_*`).
	 * @return string The page's address, or '' for a type with no dashboard or a page not published.
	 */
	public static function page_of_type( $type ) {
		$pages = array(
			WPCPM_Mail_Catalog::TYPE_STUDENT     => 'WPCPM_Students_Dashboard',
			WPCPM_Mail_Catalog::TYPE_MENTOR      => 'WPCPM_Mentors_Dashboard',
			WPCPM_Mail_Catalog::TYPE_INSTITUTION => 'WPCPM_Institutions_Dashboard',
			WPCPM_Mail_Catalog::TYPE_SPONSOR     => 'WPCPM_Sponsors_Dashboard',
			WPCPM_Mail_Catalog::TYPE_ADMIN       => 'WPCPM_Administrators_Dashboard',
		);

		if ( ! isset( $pages[ $type ] ) || ! class_exists( $pages[ $type ] ) || ! method_exists( $pages[ $type ], 'page_url' ) ) {
			return '';
		}

		return (string) call_user_func( array( $pages[ $type ], 'page_url' ) );
	}

	/*
	 * Edits
	 * --------------------------------------------------------------------
	 */

	/**
	 * An email's text as it goes out: the Administrators' edit when there is one that fits, the
	 * shipped text otherwise. An edit fits while every placeholder it uses is one the email has,
	 * every required one is in its body and none that goes in the body alone is in its subject; one
	 * that no longer fits, because an update took a placeholder away or made one required or
	 * body-only, is set aside and the shipped text sent, and the Templates tab says why (`state()`).
	 *
	 * An edit is stored as WordPress's cleaner kept it, and read back here as the plain text it was
	 * typed as (`WPCPM_Typed_Text::mail_text()`), as every typed text the plugin mails is. The
	 * cleaner lets go of the white space at either end, so an edit of a body that ends with a line
	 * break, as a Semester reports email's does, is stored without it; read here, it ends as the
	 * shipped body does, so the email sent ends, after its signature and footer, as the shipped one
	 * does (since 1.122.23; an edit saved before is read so too). The editor's box draws the edit as
	 * stored.
	 *
	 * @param string $id The email's id.
	 * @return array{subject:string,body:string}|null Null when the registry has no such email.
	 */
	public static function text_of( $id ) {
		$entry = self::entry_of( $id );

		if ( null === $entry ) {
			return null;
		}

		$edit = self::fitting_edit( (string) $id, $entry );

		if ( null === $edit ) {
			return array(
				'subject' => $entry['subject'],
				'body'    => $entry['body'],
			);
		}

		$body = WPCPM_Typed_Text::mail_text( $edit['body'] );

		if ( "\n" === substr( str_replace( array( "\r\n", "\r" ), "\n", (string) $entry['body'] ), -1 ) && "\n" !== substr( $body, -1 ) ) {
			$body .= "\n";
		}

		return array(
			'subject' => WPCPM_Typed_Text::mail_text( $edit['subject'] ),
			'body'    => $body,
		);
	}

	/**
	 * Whether an email goes out as an Administrator edited it: the registry holds it, and it has
	 * an edit that fits, the text `text_of()` gives. In 1.122.22 the mail layer asked it of every
	 * email it sent, to send an edited subject as plain text. Since 1.122.23 every email the
	 * registry holds goes out with a plain-text subject, edited or not (`WPCPM_Mail::hand_off()`
	 * asks `get()`), and nothing in the plugin asks this; it stays for code outside the plugin that
	 * does, as the 1.122.22 changelog names it.
	 *
	 * @param string $id The email's id in the catalog.
	 * @return bool False for an email sent as shipped, an edit set aside, and an id the registry
	 *              does not hold, the signature and footer's included.
	 */
	public static function sent_as_edited( $id ) {
		$entry = self::get( (string) $id );

		return null !== $entry && null !== self::fitting_edit( (string) $id, $entry );
	}

	/**
	 * An email's edit while it fits the email (`misfit()`), the one `text_of()` sends.
	 *
	 * @param string $id    The email's id, or `FOOTER`.
	 * @param array  $entry What `entry_of()` holds for it.
	 * @return array|null The edit, as `edit()` gives it; null when there is none, or it no longer
	 *                    fits.
	 */
	private static function fitting_edit( $id, array $entry ) {
		$edit = self::edit( $id );

		if ( null === $edit || array() !== self::misfit( $entry['placeholders'], $edit['subject'], $edit['body'] ) ) {
			return null;
		}

		return $edit;
	}

	/**
	 * The entry an id names: a template of the registry's, or, for `FOOTER`, the signature and
	 * footer.
	 *
	 * @param string $id The email's id, or `FOOTER`.
	 * @return array|null
	 */
	private static function entry_of( $id ) {
		return self::FOOTER === (string) $id ? self::footer_entry() : self::get( $id );
	}

	/**
	 * What keeps a text from fitting an email, each placeholder written in its braces: the ones it
	 * uses, in either box, that the email does not have; the required ones its body leaves out; and
	 * the ones that go in the body alone that its subject holds.
	 *
	 * @param array  $placeholders The email's placeholders.
	 * @param string $subject      The subject, '' for a text that has none.
	 * @param string $body         The body.
	 * @return array{unknown?:string[],missing?:string[],in_subject?:string[]} Empty when the text fits.
	 */
	private static function misfit( array $placeholders, $subject, $body ) {
		$in_subject = self::placeholders_in( (string) $subject );
		$in_body    = self::placeholders_in( (string) $body );
		$unknown    = array_values( array_diff( array_unique( array_merge( $in_subject, $in_body ) ), array_keys( $placeholders ) ) );
		$missing    = array_values( array_diff( self::required( $placeholders ), $in_body ) );
		$misplaced  = array_values( array_intersect( self::body_only( $placeholders ), $in_subject ) );
		$braced     = static function ( $name ) {
			return '{' . $name . '}';
		};
		$out        = array();

		if ( array() !== $unknown ) {
			$out['unknown'] = array_map( $braced, $unknown );
		}

		if ( array() !== $missing ) {
			$out['missing'] = array_map( $braced, $missing );
		}

		if ( array() !== $misplaced ) {
			$out['in_subject'] = array_map( $braced, $misplaced );
		}

		return $out;
	}

	/**
	 * Everything the option holds, in its shape: each edited template by id, and the footer.
	 *
	 * @return array{templates:array<string,array>,footer:array|null}
	 */
	private static function stored() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'templates' => isset( $stored['templates'] ) && is_array( $stored['templates'] ) ? $stored['templates'] : array(),
			'footer'    => isset( $stored['footer'] ) && is_array( $stored['footer'] ) ? $stored['footer'] : null,
		);
	}

	/**
	 * The edit an email holds, as stored: its subject and body, who saved it, when, and the
	 * fingerprint of the shipped text it was made from.
	 *
	 * @param string $id The email's id.
	 * @return array{subject:string,body:string,by:int,at:int,shipped:string}|null Null when it has none.
	 */
	public static function edit( $id ) {
		$edit = self::read_edit( (string) $id );

		if ( ! is_array( $edit ) || ! isset( $edit['subject'], $edit['body'] ) ) {
			return null;
		}

		return array(
			'subject' => (string) $edit['subject'],
			'body'    => (string) $edit['body'],
			'by'      => isset( $edit['by'] ) ? (int) $edit['by'] : 0,
			'at'      => isset( $edit['at'] ) ? (int) $edit['at'] : 0,
			'shipped' => isset( $edit['shipped'] ) ? (string) $edit['shipped'] : '',
		);
	}

	/**
	 * An email's edit as the option holds it: a template's under its id, the footer's on its own.
	 *
	 * @param string $id The email's id, or `FOOTER`.
	 * @return mixed The stored value, or null.
	 */
	private static function read_edit( $id ) {
		$stored = self::stored();

		if ( self::FOOTER === $id ) {
			return $stored['footer'];
		}

		return isset( $stored['templates'][ $id ] ) ? $stored['templates'][ $id ] : null;
	}

	/**
	 * Store an email's edit, or drop it, and leave every other edit as it was.
	 *
	 * @param string     $id   The email's id, or `FOOTER`.
	 * @param array|null $edit The edit, or null to drop it.
	 */
	private static function write_edit( $id, $edit ) {
		$stored = self::stored();

		if ( self::FOOTER === $id ) {
			$stored['footer'] = $edit;
		} elseif ( null === $edit ) {
			unset( $stored['templates'][ $id ] );
		} else {
			$stored['templates'][ $id ] = $edit;
		}

		update_option( self::OPTION, $stored, false );
	}

	/**
	 * Why a typing may not be saved, or '' when it may, by the rules every save of the Emails tool
	 * follows, in this order: neither box is empty, the footer's one box included, since every email
	 * ends with it; every required placeholder is in the body; no placeholder that goes in the body
	 * alone is in the subject; every placeholder is one of the email's; WordPress's cleaner would
	 * take nothing from the text (the rule every form of the plugin follows); and the subject is at
	 * most `SUBJECT_MAX` characters and the body at most `BODY_MAX`, counted as their boxes count
	 * them.
	 *
	 * @param array       $placeholders The email's placeholders.
	 * @param string|null $subject      The subject as posted, unslashed and not cleaned; null for a
	 *                                  text that has none, the footer.
	 * @param string      $body         The body as posted, unslashed and not cleaned.
	 * @return string The sentence that refuses the save, or ''.
	 */
	public static function refusal( array $placeholders, $subject, $body ) {
		$body = (string) $body;

		if ( null !== $subject && '' === trim( (string) $subject ) ) {
			return __( 'Nothing was saved: the subject is empty.', 'wpcredits-program-manager' );
		}

		if ( '' === trim( $body ) ) {
			return null === $subject
				? __( 'Nothing was saved: the signature and footer is empty.', 'wpcredits-program-manager' )
				: __( 'Nothing was saved: the body is empty.', 'wpcredits-program-manager' );
		}

		$misfit = self::misfit( $placeholders, (string) $subject, $body );

		if ( isset( $misfit['missing'] ) ) {
			return sprintf(
				/* translators: %s: the placeholders the email needs, such as {set_password_link}. */
				__( 'Nothing was saved: this email needs %s.', 'wpcredits-program-manager' ),
				implode( ', ', $misfit['missing'] )
			);
		}

		if ( isset( $misfit['in_subject'] ) ) {
			return sprintf(
				/* translators: %s: the placeholders that go in the body alone, such as {set_password_link}. */
				_n( 'Nothing was saved: %s goes in the body, never in the subject.', 'Nothing was saved: %s go in the body, never in the subject.', count( $misfit['in_subject'] ), 'wpcredits-program-manager' ),
				implode( ', ', $misfit['in_subject'] )
			);
		}

		if ( isset( $misfit['unknown'] ) ) {
			return sprintf(
				/* translators: %s: the placeholders the email does not have, such as {student_nmae}. */
				_n( 'Nothing was saved: %s is not a placeholder of this email.', 'Nothing was saved: %s are not placeholders of this email.', count( $misfit['unknown'] ), 'wpcredits-program-manager' ),
				implode( ', ', $misfit['unknown'] )
			);
		}

		if ( null !== $subject && WPCPM_Typed_Text::cleaner_loses( (string) $subject, 'line' ) ) {
			return WPCPM_Typed_Text::loss_message( __( 'Subject', 'wpcredits-program-manager' ) );
		}

		if ( WPCPM_Typed_Text::cleaner_loses( $body, 'lines' ) ) {
			return WPCPM_Typed_Text::loss_message( null === $subject ? __( 'Signature and footer', 'wpcredits-program-manager' ) : __( 'Body', 'wpcredits-program-manager' ) );
		}

		if ( null !== $subject && WPCPM_Typed_Text::box_length( (string) $subject ) > self::SUBJECT_MAX ) {
			return sprintf(
				/* translators: %s: the most characters a subject may have. */
				__( 'Nothing was saved: the subject is longer than %s characters.', 'wpcredits-program-manager' ),
				number_format_i18n( self::SUBJECT_MAX )
			);
		}

		if ( WPCPM_Typed_Text::box_length( $body ) > self::BODY_MAX ) {
			return sprintf(
				null === $subject
					/* translators: %s: the most characters the signature and footer may have. */
					? __( 'Nothing was saved: the signature and footer is longer than %s characters.', 'wpcredits-program-manager' )
					/* translators: %s: the most characters a body may have. */
					: __( 'Nothing was saved: the body is longer than %s characters.', 'wpcredits-program-manager' ),
				number_format_i18n( self::BODY_MAX )
			);
		}

		return '';
	}

	/**
	 * Store an Administrator's edit of an email, when `refusal()` lets it be stored: its subject and
	 * body as WordPress's cleaner keeps them, each line break one "\n", with who saved it, when, and
	 * the fingerprint of the shipped text it was made from. A typing that reads as the shipped text
	 * stores nothing, and drops any edit, so the email stays shipped text and goes on translating.
	 *
	 * @param string      $id      The email's id.
	 * @param string|null $subject The subject as posted, unslashed and not cleaned; null for a text
	 *                             that has none.
	 * @param string      $body    The body as posted, unslashed and not cleaned.
	 * @param int         $user_id Who saved it.
	 * @return true|WP_Error `wpcpm_mail_refused`, carrying the sentence that refuses it, when nothing
	 *                       was stored.
	 */
	public static function save( $id, $subject, $body, $user_id ) {
		$id    = (string) $id;
		$entry = self::entry_of( $id );

		if ( null === $entry ) {
			return new WP_Error( 'wpcpm_mail_refused', __( 'Nothing was saved: the site has no such email.', 'wpcredits-program-manager' ) );
		}

		// Only the footer has no subject: for any other email a missing one is a subject left empty.
		if ( null === $subject && self::FOOTER !== $id ) {
			$subject = '';
		}

		$refused = self::refusal( $entry['placeholders'], $subject, $body );

		if ( '' !== $refused ) {
			return new WP_Error( 'wpcpm_mail_refused', $refused );
		}

		$subject = null === $subject ? '' : sanitize_text_field( (string) $subject );
		$body    = str_replace( array( "\r\n", "\r" ), "\n", sanitize_textarea_field( (string) $body ) );

		if ( self::reads_as( $subject, $entry['subject'] ) && self::reads_as( $body, $entry['body'] ) ) {
			self::write_edit( $id, null );

			return true;
		}

		self::write_edit(
			$id,
			array(
				'subject' => $subject,
				'body'    => $body,
				'by'      => (int) $user_id,
				'at'      => time(),
				'shipped' => self::shipped_fingerprint( $id ),
			)
		);

		return true;
	}

	/**
	 * Whether a stored text reads as another: the same once its entities are read back and the white
	 * space at either end is let go.
	 *
	 * @param string $stored A text as the cleaner kept it.
	 * @param string $other  Another, as the registry ships it.
	 * @return bool
	 */
	private static function reads_as( $stored, $other ) {
		return trim( WPCPM_Typed_Text::mail_text( $stored ) ) === trim( str_replace( array( "\r\n", "\r" ), "\n", (string) $other ) );
	}

	/**
	 * Drop an email's edit: from now on it is sent as shipped.
	 *
	 * @param string $id The email's id.
	 * @return bool Whether there was an edit to drop.
	 */
	public static function restore( $id ) {
		if ( null === self::read_edit( (string) $id ) ) {
			return false;
		}

		self::write_edit( (string) $id, null );

		return true;
	}

	/**
	 * A fingerprint of a shipped text: how the site tells, later, that an update changed the text an
	 * edit was made from.
	 *
	 * Built in the site's own language, whichever language the Administrator who saved reads, so two
	 * Administrators reading two languages are told the same thing.
	 *
	 * @param string $id The email's id.
	 * @return string
	 */
	public static function shipped_fingerprint( $id ) {
		$switched = function_exists( 'switch_to_locale' ) && function_exists( 'get_locale' ) ? switch_to_locale( get_locale() ) : false;
		$entry    = self::entry_of( $id );

		if ( $switched ) {
			restore_previous_locale();
		}

		return null === $entry ? '' : md5( $entry['subject'] . "\n\n" . $entry['body'] );
	}

	/**
	 * Where an email stands: shipped text, edited, edited with the shipped text changed since, or an
	 * edit set aside, with why, and who saved the edit and when.
	 *
	 * @param string $id The email's id.
	 * @return array{state:string,reason:string,by:int,at:int}
	 */
	public static function state( $id ) {
		$entry = self::entry_of( $id );
		$edit  = null === $entry ? null : self::edit( $id );

		if ( null === $edit ) {
			return array(
				'state'  => self::STATE_SHIPPED,
				'reason' => '',
				'by'     => 0,
				'at'     => 0,
			);
		}

		$misfit = self::misfit( $entry['placeholders'], $edit['subject'], $edit['body'] );
		$reason = '';

		if ( isset( $misfit['unknown'] ) ) {
			$reason = sprintf(
				/* translators: %s: the placeholders the edit uses, such as {topic}. */
				__( 'Edit set aside: it uses %s, which this email no longer has.', 'wpcredits-program-manager' ),
				implode( ', ', $misfit['unknown'] )
			);
		} elseif ( isset( $misfit['missing'] ) ) {
			$reason = sprintf(
				/* translators: %s: the placeholders the email needs, such as {set_password_link}. */
				__( 'Edit set aside: it lacks %s, which this email needs.', 'wpcredits-program-manager' ),
				implode( ', ', $misfit['missing'] )
			);
		} elseif ( isset( $misfit['in_subject'] ) ) {
			$reason = sprintf(
				/* translators: %s: the placeholders that go in the body alone, such as {set_password_link}. */
				_n( 'Edit set aside: its subject uses %s, which goes in the body.', 'Edit set aside: its subject uses %s, which go in the body.', count( $misfit['in_subject'] ), 'wpcredits-program-manager' ),
				implode( ', ', $misfit['in_subject'] )
			);
		}

		if ( '' !== $reason ) {
			$state = self::STATE_SET_ASIDE;
		} elseif ( self::shipped_fingerprint( $id ) !== $edit['shipped'] ) {
			$state = self::STATE_CHANGED;
		} else {
			$state = self::STATE_EDITED;
		}

		return array(
			'state'  => $state,
			'reason' => $reason,
			'by'     => $edit['by'],
			'at'     => $edit['at'],
		);
	}

	/**
	 * What a state is called on the Templates tab.
	 *
	 * @param array $state What `state()` answered.
	 * @return string
	 */
	public static function state_label( array $state ) {
		switch ( isset( $state['state'] ) ? $state['state'] : '' ) {
			case self::STATE_EDITED:
				return __( 'Edited', 'wpcredits-program-manager' );
			case self::STATE_CHANGED:
				return __( 'Edited, and the shipped text has changed since', 'wpcredits-program-manager' );
			case self::STATE_SET_ASIDE:
				return (string) $state['reason'];
		}

		return __( 'Shipped text', 'wpcredits-program-manager' );
	}

	/*
	 * The signature and footer
	 * --------------------------------------------------------------------
	 */

	/**
	 * The signature and footer as the registry ships it, in the wording the owner approved on
	 * 10 October 2026: the text every plugin email ends with, after a line holding "-- ". Its third
	 * line holds for everybody the plugin writes to, an applicant turned down and an Administrator
	 * among them.
	 *
	 * @return string
	 */
	public static function footer_shipped() {
		/* translators: The signature and footer every email of the plugin ends with, three lines. Keep {dashboard_link} and {program_contact} as they are: the site puts the person's dashboard and the program's contact address in their place, and leaves out the line or the sentence that has nothing to say, reading a full stop followed by a space as the end of a sentence. */
		return __( "WordPress Credits, WordPress Education\n{dashboard_link}\nThis email comes from the WordPress Credits program. Questions? Write to {program_contact}.", 'wpcredits-program-manager' );
	}

	/**
	 * The signature and footer as an entry of the registry, so it is edited, restored and told
	 * apart from its shipped text as a template is: its body, no subject, and its placeholders, none
	 * of them required.
	 *
	 * @return array
	 */
	private static function footer_entry() {
		return array(
			'id'           => self::FOOTER,
			'label'        => __( 'Signature and footer', 'wpcredits-program-manager' ),
			'module'       => '',
			'audience'     => '',
			'trigger'      => __( 'Added to the end of every email the plugin sends.', 'wpcredits-program-manager' ),
			'subject'      => '',
			'body'         => self::footer_shipped(),
			'eol'          => "\n",
			'placeholders' => self::footer_placeholders(),
		);
	}

	/**
	 * The signature and footer's placeholders, none of them required. `{site_link}`, the site's public
	 * home page, the same for everybody, joined in 1.122.37, for a footer written to people who cannot
	 * open a dashboard; the shipped footer does not use it.
	 *
	 * @return array Name to what `all()` holds for each.
	 */
	public static function footer_placeholders() {
		return array(
			'site_name'       => array(
				'means'    => __( 'The site\'s name.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					return WPCPM_Mail::site_name();
				},
				'required' => false,
			),
			'site_link'       => array(
				'means'    => __( 'The site\'s public address, the same for everyone. Empty for an email that names it already, which leaves its line out.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					return self::site_link();
				},
				'required' => false,
			),
			'dashboard_link'  => array(
				'means'    => __( 'The recipient\'s dashboard. Empty for somebody with no account, and for an email that names it already, which leaves its line out.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					// The address the tab's preview and the footer's test fill in, so the table agrees with both.
					return self::footer_examples( 'footer-test' )['dashboard_link'];
				},
				'required' => false,
			),
			'program_contact' => array(
				'means'    => __( 'The address in Contact address in emails. Empty leaves its sentence out.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					return self::program_contact();
				},
				'required' => false,
			),
		);
	}

	/**
	 * The address an email names for questions: the Contact address in emails setting, while it holds
	 * an address.
	 *
	 * @return string
	 */
	public static function program_contact() {
		$contact = class_exists( 'WPCPM_Settings' ) ? trim( (string) WPCPM_Settings::get_value( 'mail_contact', '' ) ) : '';

		return '' !== $contact && is_email( $contact ) ? $contact : '';
	}

	/**
	 * The site's public address, which `{site_link}` names: its home page, the same for everybody.
	 *
	 * @return string
	 */
	public static function site_link() {
		return (string) home_url( '/' );
	}

	/**
	 * What `{dashboard_link}` becomes for each kind of person with an account: the people of that
	 * kind, the full name of their page and its address, in the order of the plugin's five
	 * audiences. A kind whose page is not published is left out, since its people's footer has no
	 * dashboard line. The Signature and footer tab and the footer's test list them, so the
	 * Administrator who reads the Administrator Dashboard there knows everybody else gets their own.
	 *
	 * @return array<string,array{who:string,page:string,url:string}> By recipient type.
	 */
	public static function dashboard_pages() {
		$named = array(
			WPCPM_Mail_Catalog::TYPE_STUDENT     => array( __( 'Students', 'wpcredits-program-manager' ), __( 'Student Report Card', 'wpcredits-program-manager' ) ),
			WPCPM_Mail_Catalog::TYPE_MENTOR      => array( __( 'Mentors', 'wpcredits-program-manager' ), __( 'Mentor Report Card', 'wpcredits-program-manager' ) ),
			WPCPM_Mail_Catalog::TYPE_INSTITUTION => array( __( 'Institution members', 'wpcredits-program-manager' ), __( 'Institution Dashboard', 'wpcredits-program-manager' ) ),
			WPCPM_Mail_Catalog::TYPE_SPONSOR     => array( __( 'Sponsor members', 'wpcredits-program-manager' ), __( 'Sponsor Dashboard', 'wpcredits-program-manager' ) ),
			WPCPM_Mail_Catalog::TYPE_ADMIN       => array( __( 'Administrators', 'wpcredits-program-manager' ), __( 'Administrator Dashboard', 'wpcredits-program-manager' ) ),
		);
		$out   = array();

		foreach ( $named as $type => $words ) {
			$url = self::page_of_type( $type );

			if ( '' !== $url ) {
				$out[ $type ] = array(
					'who'  => $words[0],
					'page' => $words[1],
					'url'  => $url,
				);
			}
		}

		return $out;
	}

	/**
	 * The dashboard of an email's recipient: the page of the kind of person the account is, the
	 * email's audience deciding for an account of several kinds, as the mail log reads it
	 * (`WPCPM_Mail_Capture::type_of()`).
	 *
	 * @param WP_User|null $recipient The account, or null for an address with none.
	 * @param string       $audience  The email's audience in the catalog, or '' when the account decides.
	 * @return string The page's address, or '' for nobody with an account or no page published.
	 */
	public static function dashboard_of( $recipient, $audience = '' ) {
		if ( ! $recipient instanceof WP_User || ! $recipient->exists() || ! class_exists( 'WPCPM_Mail_Capture' ) ) {
			return '';
		}

		return self::page_of_type( WPCPM_Mail_Capture::type_of( $recipient, (string) $audience ) );
	}

	/**
	 * The signature and footer's values for one email to one recipient: those the footer's text
	 * uses, so sending an email reads nothing the footer does not print.
	 *
	 * @param WP_User|null $recipient The account, or null for an address with none.
	 * @param string       $context   The email's id in the catalog.
	 * @param string[]     $used      The placeholders the footer's text uses.
	 * @return array<string,string>
	 */
	public static function footer_values( $recipient, $context, array $used ) {
		$named = WPCPM_Mail_Catalog::get( $context );
		$out   = array();

		if ( in_array( 'site_name', $used, true ) ) {
			$out['site_name'] = WPCPM_Mail::site_name();
		}

		if ( in_array( 'site_link', $used, true ) ) {
			$out['site_link'] = self::site_link();
		}

		if ( in_array( 'dashboard_link', $used, true ) ) {
			$out['dashboard_link'] = self::dashboard_of( $recipient, null === $named ? '' : $named['audience'] );
		}

		if ( in_array( 'program_contact', $used, true ) ) {
			$out['program_contact'] = self::program_contact();
		}

		return $out;
	}

	/**
	 * The signature and footer's values for a preview or a test of an email: the site's name and its
	 * public address, the dashboard of the email's audience, and the contact address as set. An email
	 * to several kinds of people shows the dashboard of the people it is written for first: a
	 * sponsor's in the Sponsors area, whose one such email, the codes running low, goes to the
	 * sponsor's accounts and to an Administrator, and a student's elsewhere, as a group session's
	 * goes to its students and their mentor.
	 *
	 * @param string $context The email's id in the catalog.
	 * @return array<string,string>
	 */
	public static function footer_examples( $context ) {
		$named    = WPCPM_Mail_Catalog::get( $context );
		$audience = null === $named ? '' : $named['audience'];

		if ( '' === $audience ) {
			$audience = null !== $named && WPCPM_Mail_Catalog::MODULE_SPONSORS === $named['module'] ? WPCPM_Mail_Catalog::TYPE_SPONSOR : WPCPM_Mail_Catalog::TYPE_STUDENT;
		}

		return array(
			'site_name'       => WPCPM_Mail::site_name(),
			'site_link'       => self::site_link(),
			'dashboard_link'  => self::page_of_type( $audience ),
			'program_contact' => self::program_contact(),
		);
	}

	/**
	 * A body with the signature and footer at its end: a blank line, a line holding "-- ", then the
	 * footer, filled with its values and written with the body's own line ending. The body is kept
	 * byte for byte, and so is the way it ended: one that ended with a line break ends with one
	 * after the footer too. A body that already names the recipient's dashboard, as an invitation
	 * does under its last label, gets no second copy of it: `{dashboard_link}` is empty there, so its
	 * line is left out, by the plugin's rule that no address appears twice. The same holds for the
	 * site's home page, `{site_link}` (since 1.122.37), with one difference: every address of the
	 * site starts with the home page's, so a body names the home page only where its address
	 * stands whole (`names_address()`), and a body that names a dashboard or the sign-in page keeps
	 * it. A footer that comes out empty, every line of it left out, adds nothing, the "-- " line
	 * included; an empty footer is never saved (`refusal()`).
	 *
	 * @param string       $body      The email's body.
	 * @param WP_User|null $recipient The account it goes to, or null for an address with none.
	 * @param string       $context   The email's id in the catalog.
	 * @param array|null   $values    The footer's values, to use in place of the recipient's: a
	 *                                test's, from `footer_examples()`.
	 * @return string
	 */
	public static function add_footer( $body, $recipient, $context, $values = null ) {
		$body   = (string) $body;
		$eol    = false !== strpos( $body, "\r\n" ) ? "\r\n" : "\n";
		$text   = self::text_of( self::FOOTER );
		$values = is_array( $values ) ? $values : self::footer_values( $recipient, $context, self::placeholders_in( $text['body'] ) );

		if ( ! empty( $values['dashboard_link'] ) && false !== strpos( $body, (string) $values['dashboard_link'] ) ) {
			$values['dashboard_link'] = '';
		}

		if ( ! empty( $values['site_link'] ) && self::names_address( $body, (string) $values['site_link'] ) ) {
			$values['site_link'] = '';
		}

		$footer = rtrim( self::fill( $text['body'], $values, self::footer_placeholders(), $eol ), "\r\n\t " );

		if ( '' === $footer ) {
			return $body;
		}

		if ( substr( $body, -strlen( $eol ) ) === $eol ) {
			return $body . $eol . '-- ' . $eol . $footer . $eol;
		}

		return $body . $eol . $eol . '-- ' . $eol . $footer;
	}

	/**
	 * Whether a body names an address standing whole, with its last slash or without it: followed by
	 * the end, a space, a closing bracket, an angle bracket or a quote, or by a mark that ends a
	 * sentence and then the end or a space. So "https://example.test/." names the site's home page, and
	 * "https://example.test/student-report-card/" does not.
	 *
	 * @param string $body    The email's body.
	 * @param string $address An address, such as the site's home page.
	 * @return bool
	 */
	private static function names_address( $body, $address ) {
		$bare = rtrim( $address, '/' );

		return '' !== $bare && 1 === preg_match( '~' . preg_quote( $bare, '~' ) . '/?(?=$|[\s<>"\')\]]|[.,;:!?](?:$|\s))~', $body );
	}

	/*
	 * Tests
	 * --------------------------------------------------------------------
	 */

	/**
	 * Send an email to the Administrator who asked, filled with its example values, as Send me a test
	 * does: "[Test] " before its subject, its footer filled with the examples of its audience, and
	 * marked Test in the mail log. It goes to `$to` and to nobody else: the address is the account's,
	 * never one a form posted.
	 *
	 * The signature and footer's test is a short email that ends with it, logged as the catalog's
	 * `footer-test`; a template's is logged under the template's own id.
	 *
	 * @param string  $id The email's id, or `FOOTER`.
	 * @param WP_User $to The Administrator who pressed Send me a test.
	 * @return bool Whether the email was handed off.
	 */
	public static function send_test( $id, WP_User $to ) {
		$id = (string) $id;

		if ( self::FOOTER === $id ) {
			return WPCPM_Mail::send(
				$to,
				'footer-test',
				self::test_of(
					'footer-test',
					array(
						'subject' => sprintf(
							/* translators: %s: site name. */
							__( '[%s] The signature and footer', 'wpcredits-program-manager' ),
							wp_specialchars_decode( WPCPM_Mail::site_name(), ENT_QUOTES )
						),
						'body'    => self::footer_test_body(),
					)
				)
			);
		}

		if ( null === self::get( $id ) ) {
			return false;
		}

		return WPCPM_Mail::send( $to, $id, self::test_of( $id, null ) );
	}

	/**
	 * What the footer's test says above the footer (since 1.122.37, after WordPress/WPCredits#255):
	 * what it is and, while the footer names a dashboard and the Administrator Dashboard is
	 * published, that its link is that page because the test went to an Administrator, what
	 * everybody else's emails link to in its place (`dashboard_pages()`), unless an email names that
	 * page already, as every invitation does, and that somebody with no account gets none. The list leaves the Administrators out: the test's own link names their
	 * page, and a body naming it would leave the footer's dashboard line out (`add_footer()`). A
	 * footer with no `{dashboard_link}` has no dashboard to explain, so its test says what it is
	 * and nothing more.
	 *
	 * @return string
	 */
	private static function footer_test_body() {
		$opening = __( 'This is how every email the plugin sends ends.', 'wpcredits-program-manager' );
		$pages   = self::dashboard_pages();
		$text    = self::text_of( self::FOOTER );

		if ( ! isset( $pages[ WPCPM_Mail_Catalog::TYPE_ADMIN ] ) || ! in_array( 'dashboard_link', self::placeholders_in( $text['body'] ), true ) ) {
			return $opening;
		}

		unset( $pages[ WPCPM_Mail_Catalog::TYPE_ADMIN ] );

		$paragraphs = array( $opening . ' ' . __( 'This test went to you, an Administrator, so the dashboard link below is the Administrator Dashboard.', 'wpcredits-program-manager' ) );

		if ( array() !== $pages ) {
			$lines = array( __( 'In everybody else\'s emails, that line links to their own page, unless the email names that page already:', 'wpcredits-program-manager' ) );

			foreach ( $pages as $page ) {
				$lines[] = sprintf(
					/* translators: 1: a kind of person, such as Students, 2: the full name of their page, such as Student Report Card, 3: the page's address. */
					__( '%1$s: %2$s, %3$s', 'wpcredits-program-manager' ),
					$page['who'],
					$page['page'],
					$page['url']
				);
			}

			$paragraphs[] = implode( "\n", $lines );
		}

		$paragraphs[] = __( 'Somebody with no account, such as an applicant, gets no dashboard link, and its line is left out.', 'wpcredits-program-manager' );

		return implode( "\n\n", $paragraphs );
	}

	/**
	 * The builder a test hands the mail layer: the email as `render()` fills it with its examples, or
	 * the subject and body given, built in the recipient's language, "[Test] " before the subject,
	 * marked as a test, with the footer's examples.
	 *
	 * @param string     $context The email's id in the catalog.
	 * @param array|null $mail    The subject and body to send, or null for the template's own.
	 * @return callable
	 */
	private static function test_of( $context, $mail ) {
		return static function () use ( $context, $mail ) {
			$mail = null === $mail ? self::render( $context, self::example_values( $context ) ) : $mail;

			return array(
				'subject'       => sprintf(
					/* translators: %s: the email's subject. */
					__( '[Test] %s', 'wpcredits-program-manager' ),
					$mail['subject']
				),
				'body'          => $mail['body'],
				'plain_subject' => true,
				'test'          => true,
				'footer_values' => self::footer_examples( $context ),
			);
		};
	}
}
