<?php
/**
 * The Sponsor events area's emails, as the template registry ships them.
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. Every sentence is the one the event code wrote before it moved here, under the same
 * translatable string (`WPCPM_Sponsor_Event`, `WPCPM_Sponsor_Event_List`, `WPCPM_Sponsor_Event_Planner`
 * and `WPCPM_Sponsor_Event_Queue`), but these: a line the code wrote whole in one of two wordings, the
 * places ("Places: 30" or "Places: no limit") and the people who joined so far ("3 people" or "nobody
 * yet"), takes the words that differ as one value (`{places}`, `{joined_so_far}`); the time is the
 * day and the hours (`{when}`) beside the zone's name (`{time_zone}`), as the calls' emails give them;
 * and the approval's two sentences, which the code wrote as one string when a calendar file went with
 * it, are the approval's sentence and the file's.
 *
 * The code said what the calendar file attached does only when the site could write one, and how
 * many of the people on a canceled event were told only when it could say so truly: each of those
 * sentences is a value (`{calendar_note}`, `{told_note}`), worked out where the email is sent, and
 * empty leaves it out, as the code did.
 *
 * An email that said other words in one case than in another is two templates: an event of the
 * program's own, which names the WordPress Credits Program as its host, and what an Administrator did
 * on a sponsor's behalf through the Sponsor Dashboard's switcher.
 *
 * Every time is the recipient's, on their own clock, with the zone named, as the code always wrote
 * it; the program contact's mail to an address with no account is on the site's clock.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// An example event for a preview and a test: tomorrow at 3 pm, an hour and a half long, on the clock of
// the Administrator looking at it, so the times read as a real email to them would.
$wpcpm_example = static function () {
	$zone  = WPCPM_Mentor_Availability::viewer_timezone( get_current_user_id() );
	$start = ( new DateTimeImmutable( 'tomorrow 15:00', $zone ) )->getTimestamp();

	return array(
		'start' => $start,
		'end'   => $start + 90 * MINUTE_IN_SECONDS,
		'zone'  => $zone,
	);
};

// Every placeholder an email of the area may have: what each means and its example, a value or a
// function that works one out when a preview or a test asks for it. An email takes the ones it uses
// (`$wpcpm_take`).
$wpcpm_placeholders = static function () use ( $wpcpm_example ) {
	return array(
		'site_name'     => array(
			'means'   => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return WPCPM_Mail::site_name();
			},
		),
		'event_title'   => array(
			'means'   => __( 'The event\'s title, as it was typed.', 'wpcredits-program-manager' ),
			'example' => 'Building accessible WordPress themes',
		),
		'company_name'  => array(
			'means'   => __( 'The sponsor\'s name; its key on the site when the site holds no name for it.', 'wpcredits-program-manager' ),
			'example' => 'Example Hosting',
		),
		'when'          => array(
			'means'   => __( 'When the event runs, its day and its start and end times, on the recipient\'s own clock, or on the site\'s for an address in Who is mailed that has no account.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $wpcpm_example ) {
				$call = $wpcpm_example();

				return WPCPM_Mentor_Calls::format_range( $call['start'], $call['end'], $call['zone'] );
			},
		),
		'time_zone'     => array(
			'means'   => __( 'The recipient\'s time zone, which the event\'s time is shown in; the site\'s for an address in Who is mailed that has no account.', 'wpcredits-program-manager' ),
			'example' => static function () use ( $wpcpm_example ) {
				$call = $wpcpm_example();

				return WPCPM_Mentor_Availability::zone_label( $call['zone']->getName() );
			},
		),
		'length'        => array(
			'means'   => __( 'How long the event runs, in words: "90 minutes".', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf(
					/* translators: %s: a number of minutes. */
					_n( '%s minute', '%s minutes', 90, 'wpcredits-program-manager' ),
					number_format_i18n( 90 )
				);
			},
		),
		'places'        => array(
			'means'   => __( 'How many people may join: a number, or "no limit".', 'wpcredits-program-manager' ),
			'example' => '30',
		),
		'call_link'     => array(
			'means'     => __( 'The event\'s call link. It can carry the meeting\'s passcode, so it goes in the body, never in the subject.', 'wpcredits-program-manager' ),
			'example'   => 'https://meet.example.test/wordpress-credits',
			'body_only' => true,
		),
		'joined_so_far' => array(
			'means'   => __( 'How many people have joined so far, counted: "3 people", or "nobody yet".', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf(
					/* translators: %s: how many people have joined the event so far. */
					_n( '%s person', '%s people', 3, 'wpcredits-program-manager' ),
					number_format_i18n( 3 )
				);
			},
		),
		'joined'        => array(
			'means'   => __( 'How many people joined, while anybody has. Empty leaves its line out.', 'wpcredits-program-manager' ),
			'example' => '12',
		),
		'description'   => array(
			'means'     => __( 'The event\'s description, as it was typed. It goes in the body, never in the subject, which the mail log keeps.', 'wpcredits-program-manager' ),
			'example'   => 'An hour on themes that everybody can use, with time for questions.',
			'body_only' => true,
		),
		'note'          => array(
			'means'     => __( 'The Administrator\'s note, as they typed it. The email needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => 'Please move it to a weekday, so more students can come.',
			'body_only' => true,
		),
		'told_note'     => array(
			'means'   => __( 'How many of the people who joined are told the event is canceled, said only when it is every one of them: "The 3 people who joined it have been told.", or "The 3 people who joined are being told." while some still wait to be mailed. Empty leaves it out.', 'wpcredits-program-manager' ),
			'example' => static function () {
				return sprintf(
					/* translators: %s: how many of the people on the event were told it is canceled. */
					_n( 'The %s person who joined it has been told.', 'The %s people who joined it have been told.', 3, 'wpcredits-program-manager' ),
					number_format_i18n( 3 )
				);
			},
		),
		// Each email that says it gives its own example, the sentence it says (`$wpcpm_adds`,
		// `$wpcpm_replaces`, `$wpcpm_removes` below), so its preview and its test show it.
		'calendar_note' => array(
			'means'   => __( 'What the calendar file attached does, said only while the site could write one. Empty leaves it out.', 'wpcredits-program-manager' ),
			'example' => '',
		),
		'events_card'   => array(
			'means'   => __( 'The Events card on the Sponsor Dashboard, while its page is published. Empty leaves its line out.', 'wpcredits-program-manager' ),
			'example' => static function () {
				$page = WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_SPONSOR );

				return '' === $page ? '' : $page . '#wpcpm-sponsor-events';
			},
		),
		'events_queue'  => array(
			'means'   => __( 'The Events card on the Administrator Dashboard, while its page is published. Empty leaves its line out.', 'wpcredits-program-manager' ),
			'example' => static function () {
				$page = WPCPM_Mail_Templates::page_of_type( WPCPM_Mail_Catalog::TYPE_ADMIN );

				return '' === $page ? '' : $page . '#wpcpm-sponsor-events';
			},
		),
	);
};

// The placeholders one email has, in the order given, those named in `$required` required, and
// those named in `$own` with what this email says of them, its explanation or its example, in place
// of the area's.
$wpcpm_take = static function ( array $names, array $required, array $own = array() ) use ( $wpcpm_placeholders ) {
	$all = $wpcpm_placeholders();
	$out = array();

	foreach ( $names as $name ) {
		$about        = ( isset( $own[ $name ] ) ? $own[ $name ] : array() ) + $all[ $name ];
		$out[ $name ] = array(
			'means'     => $about['means'],
			'example'   => $about['example'],
			'required'  => in_array( $name, $required, true ),
			'body_only' => ! empty( $all[ $name ]['body_only'] ),
		);
	}

	return $out;
};

// A body: its paragraphs, a blank line between each two.
$wpcpm_body = static function ( array $paragraphs ) {
	return implode( "\n\n", $paragraphs );
};

// The lines that say which event and when, as every email of the area gives them, one to a line.
/* translators: %s: the event's title. */
$wpcpm_title = sprintf( __( 'Title: %s', 'wpcredits-program-manager' ), '{event_title}' );
/* translators: 1: the day and the start and end times, 2: the time zone's name. */
$wpcpm_when = sprintf( __( 'When: %1$s (%2$s)', 'wpcredits-program-manager' ), '{when}', '{time_zone}' );
/* translators: %s: how long the event runs, e.g. 60 minutes. */
$wpcpm_length = sprintf( __( 'Length: %s', 'wpcredits-program-manager' ), '{length}' );
/* translators: %s: how many people may join, or "no limit". */
$wpcpm_places = sprintf( __( 'Places: %s', 'wpcredits-program-manager' ), '{places}' );
/* translators: %s: the event's call link. */
$wpcpm_join = sprintf( __( 'Join the call: %s', 'wpcredits-program-manager' ), '{call_link}' );
/* translators: %s: the event's call link. */
$wpcpm_link = sprintf( __( 'Call link: %s', 'wpcredits-program-manager' ), '{call_link}' );
/* translators: %s: the sponsor's name. */
$wpcpm_sponsor = sprintf( __( 'Sponsor: %s', 'wpcredits-program-manager' ), '{company_name}' );
$wpcpm_host    = __( 'Hosted by the WordPress Credits Program', 'wpcredits-program-manager' );

// What a calendar file attached does, as an email's own example of its sentence.
$wpcpm_adds     = array( 'calendar_note' => array( 'example' => __( 'The calendar file attached adds it to your calendar.', 'wpcredits-program-manager' ) ) );
$wpcpm_replaces = array( 'calendar_note' => array( 'example' => __( 'The calendar file attached replaces the one you had.', 'wpcredits-program-manager' ) ) );
$wpcpm_removes  = array( 'calendar_note' => array( 'example' => __( 'The calendar file attached takes it off your calendar.', 'wpcredits-program-manager' ) ) );

// An event that waits for approval has nobody on it, so the email about one has no people who joined to
// give: its preview and its test leave that line out, as the email does.
$wpcpm_nobody = array( 'joined' => array( 'example' => '' ) );

// The program contact's mail: the event, the people who joined once anybody has, the call link and
// the description, in that order, for every email of the eight.
$wpcpm_contact_facts = implode(
	"\n",
	array(
		$wpcpm_title,
		$wpcpm_when,
		$wpcpm_length,
		$wpcpm_places,
		/* translators: %s: how many people joined the event. */
		sprintf( __( 'People who joined: %s', 'wpcredits-program-manager' ), '{joined}' ),
		$wpcpm_link,
	)
);
$wpcpm_description   = __( 'Description:', 'wpcredits-program-manager' ) . "\n{description}";
$wpcpm_to_approve    = __( 'Approve it, or return it with a note, on the Administrator Dashboard under Events.', 'wpcredits-program-manager' ) . "\n{events_queue}";
$wpcpm_contact_takes = array( 'site_name', 'company_name', 'event_title', 'when', 'time_zone', 'length', 'places', 'joined', 'call_link', 'description', 'events_queue' );

return array(
	'event-joined'               => array(
		'trigger'      => __( 'When somebody joins a sponsor\'s event on their dashboard: to them, with the calendar file that adds it to their calendar.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] You joined: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				/* translators: %s: the sponsor's name. */
				sprintf( __( 'You joined %s\'s event.', 'wpcredits-program-manager' ), '{company_name}' ),
				implode( "\n", array( $wpcpm_title, $wpcpm_when, $wpcpm_length, $wpcpm_join ) ),
				'{calendar_note}',
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'company_name', 'when', 'time_zone', 'length', 'call_link', 'calendar_note' ), array( 'company_name', 'event_title', 'when' ), $wpcpm_adds ),
	),
	'event-joined-program'       => array(
		'trigger'      => __( 'When somebody joins an event of the program\'s own on their dashboard: to them, with the calendar file that adds it to their calendar.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] You joined: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'You joined an event hosted by the WordPress Credits Program.', 'wpcredits-program-manager' ),
				implode( "\n", array( $wpcpm_title, $wpcpm_when, $wpcpm_length, $wpcpm_join ) ),
				'{calendar_note}',
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'when', 'time_zone', 'length', 'call_link', 'calendar_note' ), array( 'event_title', 'when' ), $wpcpm_adds ),
	),
	'event-left'                 => array(
		'trigger'      => __( 'When somebody leaves a sponsor\'s event on their dashboard: to them, with the calendar file that takes it off their calendar.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] You left: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		/* translators: %s: the sponsor's name. */
		'body'         => sprintf( __( 'You left %s\'s event.', 'wpcredits-program-manager' ), '{company_name}' ) . ' {calendar_note}',
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'company_name', 'calendar_note' ), array( 'company_name' ), $wpcpm_removes ),
	),
	'event-left-program'         => array(
		'trigger'      => __( 'When somebody leaves an event of the program\'s own on their dashboard: to them, with the calendar file that takes it off their calendar.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] You left: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => __( 'You left an event hosted by the WordPress Credits Program.', 'wpcredits-program-manager' ) . ' {calendar_note}',
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'calendar_note' ), array(), $wpcpm_removes ),
	),
	'event-changed'              => array(
		'trigger'      => __( 'When a sponsor\'s approved event changes its time, length, title or call link: to each person on it, with the calendar file that replaces the one they had.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Event changed: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'An event you joined has changed. Here is what it is now:', 'wpcredits-program-manager' ),
				implode( "\n", array( $wpcpm_title, $wpcpm_sponsor, $wpcpm_when, $wpcpm_length, $wpcpm_join ) ),
				'{calendar_note}',
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'company_name', 'when', 'time_zone', 'length', 'call_link', 'calendar_note' ), array( 'company_name', 'event_title', 'when' ), $wpcpm_replaces ),
	),
	'event-changed-program'      => array(
		'trigger'      => __( 'When an event of the program\'s own changes its time, length, title or call link: to each person on it, with the calendar file that replaces the one they had.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Event changed: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'An event you joined has changed. Here is what it is now:', 'wpcredits-program-manager' ),
				implode( "\n", array( $wpcpm_title, $wpcpm_host, $wpcpm_when, $wpcpm_length, $wpcpm_join ) ),
				'{calendar_note}',
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'when', 'time_zone', 'length', 'call_link', 'calendar_note' ), array( 'event_title', 'when' ), $wpcpm_replaces ),
	),
	'event-canceled'             => array(
		'trigger'      => __( 'When a sponsor\'s event is canceled: to each person on it, with the calendar file that takes it off their calendar.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Event canceled: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'An event you joined is canceled.', 'wpcredits-program-manager' ),
				implode( "\n", array( $wpcpm_title, $wpcpm_sponsor, $wpcpm_when ) ),
				'{calendar_note}',
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'company_name', 'when', 'time_zone', 'calendar_note' ), array( 'company_name', 'event_title', 'when' ), $wpcpm_removes ),
	),
	'event-canceled-program'     => array(
		'trigger'      => __( 'When an event of the program\'s own is canceled: to each person on it, with the calendar file that takes it off their calendar.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Event canceled: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'An event you joined is canceled.', 'wpcredits-program-manager' ),
				implode( "\n", array( $wpcpm_title, $wpcpm_host, $wpcpm_when ) ),
				'{calendar_note}',
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'when', 'time_zone', 'calendar_note' ), array( 'event_title', 'when' ), $wpcpm_removes ),
	),
	'event-reminder'             => array(
		'trigger'      => __( 'The day before a sponsor\'s approved event: to each person on it.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Reminder: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				/* translators: %s: the sponsor's name. */
				sprintf( __( 'A reminder of %s\'s event, which you joined.', 'wpcredits-program-manager' ), '{company_name}' ),
				implode( "\n", array( $wpcpm_title, $wpcpm_when, $wpcpm_length, $wpcpm_join ) ),
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'company_name', 'when', 'time_zone', 'length', 'call_link' ), array( 'company_name', 'event_title', 'when' ) ),
	),
	'event-reminder-program'     => array(
		'trigger'      => __( 'The day before an event of the program\'s own: to each person on it.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Reminder: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'A reminder of an event hosted by the WordPress Credits Program, which you joined.', 'wpcredits-program-manager' ),
				implode( "\n", array( $wpcpm_title, $wpcpm_when, $wpcpm_length, $wpcpm_join ) ),
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'when', 'time_zone', 'length', 'call_link' ), array( 'event_title', 'when' ) ),
	),
	'event-reminder-sponsor'     => array(
		'trigger'      => __( 'The day before a sponsor\'s approved event: to every account of the sponsor that is not on it, with how many people have joined and never who.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Your event is coming up: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'A reminder of your company\'s event.', 'wpcredits-program-manager' ),
				implode(
					"\n",
					array(
						$wpcpm_title,
						$wpcpm_when,
						/* translators: %s: how many people have joined the event so far, counted, or "nobody yet". */
						sprintf( __( 'Joined so far: %s', 'wpcredits-program-manager' ), '{joined_so_far}' ),
					)
				),
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'when', 'time_zone', 'joined_so_far' ), array( 'event_title', 'when' ) ),
	),
	'event-approved'             => array(
		'trigger'      => __( 'When an Administrator approves a sponsor\'s event on the Administrator Dashboard: to every account of the sponsor, with the calendar file that adds it to their calendar.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Your event is approved: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'An Administrator approved your company\'s event.', 'wpcredits-program-manager' ) . ' {calendar_note}',
				implode( "\n", array( $wpcpm_title, $wpcpm_when, $wpcpm_length, $wpcpm_places, $wpcpm_link ) ),
				__( 'Your event is on the Events card of the Sponsor Dashboard:', 'wpcredits-program-manager' ) . "\n{events_card}",
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'when', 'time_zone', 'length', 'places', 'call_link', 'calendar_note', 'events_card' ), array( 'event_title', 'when' ), $wpcpm_adds ),
	),
	'event-returned'             => array(
		'trigger'      => __( 'When an Administrator returns a sponsor\'s event with a note, which cancels it: to every account of the sponsor, with the Administrator\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Your event was returned: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'An Administrator returned your company\'s event with this note:', 'wpcredits-program-manager' ),
				'{note}',
				implode( "\n", array( $wpcpm_title, $wpcpm_when ) ),
				__( 'The event is canceled. You can plan it again on your dashboard.', 'wpcredits-program-manager' ) . "\n{events_card}",
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'note', 'when', 'time_zone', 'events_card' ), array( 'event_title', 'when', 'note' ) ),
	),
	'event-returned-no-events'   => array(
		'trigger'      => __( 'When an Administrator returns the event of a sponsor whose tier no longer includes events, or which is no longer an approved sponsor, with a note, which cancels it: to every account of the sponsor, with the Administrator\'s address to reply to.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Your event was returned: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'An Administrator returned your company\'s event with this note:', 'wpcredits-program-manager' ),
				'{note}',
				implode( "\n", array( $wpcpm_title, $wpcpm_when ) ),
				__( 'The event is canceled. Your company no longer holds the tier that includes events.', 'wpcredits-program-manager' ),
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'note', 'when', 'time_zone' ), array( 'event_title', 'when', 'note' ) ),
	),
	'event-canceled-with-note'   => array(
		'trigger'      => __( 'When an Administrator cancels a sponsor\'s event with a note on the Administrator Dashboard: to every account of the sponsor, with the Administrator\'s address to reply to, and, for an event that was approved, the calendar file that takes it off their calendar.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Your event is canceled: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'An Administrator canceled your company\'s event, with this note:', 'wpcredits-program-manager' ),
				'{note}',
				implode( "\n", array( $wpcpm_title, $wpcpm_when ) ),
				'{told_note}',
				'{calendar_note}',
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'note', 'when', 'time_zone', 'told_note', 'calendar_note' ), array( 'event_title', 'when', 'note' ), $wpcpm_removes ),
	),
	'event-changed-sponsor'      => array(
		'trigger'      => __( 'When a sponsor changes its approved event on the Sponsor Dashboard: to every account of the sponsor, with the calendar file that replaces the one the approval sent.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Your event is changed: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Your company\'s event is changed.', 'wpcredits-program-manager' ) . ' {calendar_note}',
				implode( "\n", array( $wpcpm_title, $wpcpm_when, $wpcpm_length, $wpcpm_places, $wpcpm_link ) ),
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'when', 'time_zone', 'length', 'places', 'call_link', 'calendar_note' ), array( 'event_title', 'when' ), $wpcpm_replaces ),
	),
	'event-changed-sponsor-for'  => array(
		'trigger'      => __( 'When an Administrator changes a sponsor\'s approved event on the Sponsor Dashboard, on the sponsor\'s behalf: to every account of the sponsor, with the calendar file that replaces the one the approval sent.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Your event is changed: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				/* translators: %s: the sponsor's name. */
				sprintf( __( 'An Administrator changed it on %s\'s behalf.', 'wpcredits-program-manager' ), '{company_name}' ) . ' ' . __( 'Your company\'s event is changed.', 'wpcredits-program-manager' ) . ' {calendar_note}',
				implode( "\n", array( $wpcpm_title, $wpcpm_when, $wpcpm_length, $wpcpm_places, $wpcpm_link ) ),
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'company_name', 'when', 'time_zone', 'length', 'places', 'call_link', 'calendar_note' ), array( 'company_name', 'event_title', 'when' ), $wpcpm_replaces ),
	),
	'event-canceled-sponsor'     => array(
		'trigger'      => __( 'When a sponsor cancels its approved event on the Sponsor Dashboard: to every account of the sponsor, with the calendar file that takes it off their calendar.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Your event is canceled: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				__( 'Your company\'s event is canceled.', 'wpcredits-program-manager' ) . ' {calendar_note}',
				implode( "\n", array( $wpcpm_title, $wpcpm_when ) ),
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'when', 'time_zone', 'calendar_note' ), array( 'event_title', 'when' ), $wpcpm_removes ),
	),
	'event-canceled-sponsor-for' => array(
		'trigger'      => __( 'When an Administrator cancels a sponsor\'s approved event on the Sponsor Dashboard, on the sponsor\'s behalf: to every account of the sponsor, with the calendar file that takes it off their calendar.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the event's title. */
			__( '[%1$s] Your event is canceled: %2$s', 'wpcredits-program-manager' ),
			'{site_name}',
			'{event_title}'
		),
		'body'         => $wpcpm_body(
			array(
				/* translators: %s: the sponsor's name. */
				sprintf( __( 'An Administrator canceled it on %s\'s behalf.', 'wpcredits-program-manager' ), '{company_name}' ) . ' ' . __( 'Your company\'s event is canceled.', 'wpcredits-program-manager' ) . ' {calendar_note}',
				implode( "\n", array( $wpcpm_title, $wpcpm_when ) ),
			)
		),
		'placeholders' => $wpcpm_take( array( 'site_name', 'event_title', 'company_name', 'when', 'time_zone', 'calendar_note' ), array( 'company_name', 'event_title', 'when' ), $wpcpm_removes ),
	),
	'event-planned'              => array(
		'trigger'      => __( 'When a sponsor plans an event on the Sponsor Dashboard: to its assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the sponsor's name. */
			__( '[%1$s] %2$s planned an event', 'wpcredits-program-manager' ),
			'{site_name}',
			'{company_name}'
		),
		'body'         => $wpcpm_body(
			array(
				/* translators: %s: the sponsor's name. */
				sprintf( __( '%s planned an event on the Sponsor Dashboard. Nobody else sees it until an Administrator approves it.', 'wpcredits-program-manager' ), '{company_name}' ),
				$wpcpm_contact_facts,
				$wpcpm_description,
				$wpcpm_to_approve,
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_contact_takes, array( 'company_name', 'event_title', 'when', 'description' ), $wpcpm_nobody ),
	),
	'event-planned-for'          => array(
		'trigger'      => __( 'When an Administrator plans an event on the Sponsor Dashboard, on a sponsor\'s behalf: to the sponsor\'s assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the sponsor's name. */
			__( '[%1$s] %2$s planned an event', 'wpcredits-program-manager' ),
			'{site_name}',
			'{company_name}'
		),
		'body'         => $wpcpm_body(
			array(
				/* translators: %s: the sponsor's name. */
				sprintf( __( 'An Administrator planned an event on the Sponsor Dashboard on %s\'s behalf. Nobody else sees it until an Administrator approves it.', 'wpcredits-program-manager' ), '{company_name}' ),
				$wpcpm_contact_facts,
				$wpcpm_description,
				$wpcpm_to_approve,
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_contact_takes, array( 'company_name', 'event_title', 'when', 'description' ), $wpcpm_nobody ),
	),
	'event-changed-waiting'      => array(
		'trigger'      => __( 'When a sponsor changes its event that waits for approval, on the Sponsor Dashboard: to its assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the sponsor's name. */
			__( '[%1$s] %2$s changed its event', 'wpcredits-program-manager' ),
			'{site_name}',
			'{company_name}'
		),
		'body'         => $wpcpm_body(
			array(
				/* translators: %s: the sponsor's name. */
				sprintf( __( '%s changed its event on the Sponsor Dashboard. It is still waiting for an Administrator\'s approval.', 'wpcredits-program-manager' ), '{company_name}' ),
				$wpcpm_contact_facts,
				$wpcpm_description,
				$wpcpm_to_approve,
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_contact_takes, array( 'company_name', 'event_title', 'when', 'description' ), $wpcpm_nobody ),
	),
	'event-changed-waiting-for'  => array(
		'trigger'      => __( 'When an Administrator changes a sponsor\'s event that waits for approval, on the Sponsor Dashboard, on the sponsor\'s behalf: to the sponsor\'s assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the sponsor's name. */
			__( '[%1$s] %2$s changed its event', 'wpcredits-program-manager' ),
			'{site_name}',
			'{company_name}'
		),
		'body'         => $wpcpm_body(
			array(
				/* translators: %s: the sponsor's name. */
				sprintf( __( 'An Administrator changed the event on the Sponsor Dashboard on %s\'s behalf. It is still waiting for an Administrator\'s approval.', 'wpcredits-program-manager' ), '{company_name}' ),
				$wpcpm_contact_facts,
				$wpcpm_description,
				$wpcpm_to_approve,
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_contact_takes, array( 'company_name', 'event_title', 'when', 'description' ), $wpcpm_nobody ),
	),
	'event-changed-approved'     => array(
		'trigger'      => __( 'When a sponsor changes its approved event on the Sponsor Dashboard: to its assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the sponsor's name. */
			__( '[%1$s] %2$s changed its event', 'wpcredits-program-manager' ),
			'{site_name}',
			'{company_name}'
		),
		'body'         => $wpcpm_body(
			array(
				/* translators: %s: the sponsor's name. */
				sprintf( __( '%s changed its approved event on the Sponsor Dashboard. It stays approved.', 'wpcredits-program-manager' ), '{company_name}' ),
				$wpcpm_contact_facts,
				$wpcpm_description,
				__( 'The Administrator Dashboard lists it under Events.', 'wpcredits-program-manager' ) . "\n{events_queue}",
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_contact_takes, array( 'company_name', 'event_title', 'when', 'description' ) ),
	),
	'event-changed-approved-for' => array(
		'trigger'      => __( 'When an Administrator changes a sponsor\'s approved event on the Sponsor Dashboard, on the sponsor\'s behalf: to the sponsor\'s assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the sponsor's name. */
			__( '[%1$s] %2$s changed its event', 'wpcredits-program-manager' ),
			'{site_name}',
			'{company_name}'
		),
		'body'         => $wpcpm_body(
			array(
				/* translators: %s: the sponsor's name. */
				sprintf( __( 'An Administrator changed the approved event on the Sponsor Dashboard on %s\'s behalf. It stays approved.', 'wpcredits-program-manager' ), '{company_name}' ),
				$wpcpm_contact_facts,
				$wpcpm_description,
				__( 'The Administrator Dashboard lists it under Events.', 'wpcredits-program-manager' ) . "\n{events_queue}",
			)
		),
		'placeholders' => $wpcpm_take( $wpcpm_contact_takes, array( 'company_name', 'event_title', 'when', 'description' ) ),
	),
	'event-canceled-by-sponsor'  => array(
		'trigger'      => __( 'When a sponsor cancels its event on the Sponsor Dashboard: to its assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the sponsor's name. */
			__( '[%1$s] %2$s canceled its event', 'wpcredits-program-manager' ),
			'{site_name}',
			'{company_name}'
		),
		'body'         => $wpcpm_body(
			array(
				/* translators: %s: the sponsor's name. */
				sprintf( __( '%s canceled its event on the Sponsor Dashboard.', 'wpcredits-program-manager' ), '{company_name}' ),
				$wpcpm_contact_facts,
				$wpcpm_description,
			)
		),
		'placeholders' => $wpcpm_take( array_values( array_diff( $wpcpm_contact_takes, array( 'events_queue' ) ) ), array( 'company_name', 'event_title', 'when', 'description' ) ),
	),
	'event-canceled-for'         => array(
		'trigger'      => __( 'When an Administrator cancels a sponsor\'s event on the Sponsor Dashboard, on the sponsor\'s behalf: to the sponsor\'s assigned Administrator, or, with none, to the addresses in Who is mailed, or, with none set, to every Administrator.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: 1: the site's name, 2: the sponsor's name. */
			__( '[%1$s] %2$s canceled its event', 'wpcredits-program-manager' ),
			'{site_name}',
			'{company_name}'
		),
		'body'         => $wpcpm_body(
			array(
				/* translators: %s: the sponsor's name. */
				sprintf( __( 'An Administrator canceled the event on the Sponsor Dashboard on %s\'s behalf.', 'wpcredits-program-manager' ), '{company_name}' ),
				$wpcpm_contact_facts,
				$wpcpm_description,
			)
		),
		'placeholders' => $wpcpm_take( array_values( array_diff( $wpcpm_contact_takes, array( 'events_queue' ) ) ), array( 'company_name', 'event_title', 'when', 'description' ) ),
	),
);
