<?php
/**
 * Institutions module - the switch of the institutions' record (`institutions_on_site`): what every change of it
 * asks and does, for the settings screen's save and the command line alike.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One way to change the setting `institutions_on_site` (round 2 of the whole-branch wave of release 2, point 5): the
 * settings screen's save (`WPCPM_Settings_Screen::handle_settings_save()`) and `wp wpcredits setting set
 * institutions_on_site` both call `change()`, so the two cannot drift.
 *
 * - **Off to on** asks `WPCPM_Institutions_Index::may_turn_on()` first. A refusal changes nothing of the setting.
 * - **A change either way** empties the index changes owed first (`WPCPM_Institution_Agreement::forget_index_debts()`),
 *   and the copies owed to Airtable (`WPCPM_Institution_Copy::forget_pending_marks()`): turning on, before the store;
 *   going back, with it, once the warning's names are read (the trial's round, T1). A dry run empties nothing.
 * - **On to off** while copies are owed to Airtable goes through, and answers the warning (`back_sentence()`).
 *
 * `WPCPM_Settings::patch()` and `WPCPM_Settings::save()` themselves ask none of this: a direct call of either, and a
 * direct write of the option, pass the switch by.
 */
class WPCPM_Institutions_Switch {

	/** How many schools the warning at going back names at most. */
	const NAMES = 5;

	/**
	 * Change the switch: ask, empty, store, warn.
	 *
	 * The caller says how the value is stored, since the screen stores the whole tab with it and the command the one
	 * setting: `$store( $allowed )` is called once, with false when the guard refused the change (the screen then
	 * stores the rest of its tab with the switch as it was; the command stores nothing), and with true when the
	 * change may be made. It answers the switch as stored after it. A dry run asks the guard and calls nothing.
	 *
	 * @param bool     $to    The value the switch is to take; the caller has seen it differs from the stored one.
	 * @param callable $store Stores the change, or the rest without it; answers the switch as stored after it.
	 * @param bool     $dry   True to ask the guard alone: nothing is stored or emptied.
	 * @return array{refusal: WP_Error|null, stored: bool|null, warning: string, count: int, names: string[]}
	 *               `stored` is null when `$store` was not called; `count` and `names` (the first five) are the schools
	 *               the warning names.
	 */
	public static function change( $to, callable $store, $dry = false ) {
		$out = array(
			'refusal' => null,
			'stored'  => null,
			'warning' => '',
			'count'   => 0,
			'names'   => array(),
		);

		if ( $to ) {
			$allowed = WPCPM_Institutions_Index::may_turn_on();

			if ( is_wp_error( $allowed ) ) {
				$out['refusal'] = $allowed;

				if ( ! $dry ) {
					$out['stored'] = (bool) call_user_func( $store, false );
				}

				return $out;
			}
		}

		if ( $dry ) {
			return $out;
		}

		// Going back, the schools whose copies are owed are named before their marks go (W4, T1).
		$names = $to ? array() : WPCPM_Institution_Copy::pending_names();

		// The changes owed from an earlier time in site mode go first, so the first run after a later switch on pays
		// none of them over rows the sync wrote from Airtable meanwhile (W2). Turning on, a copy still marked as owed is
		// from an earlier time in site mode too: it goes before the store, so the first hourly run sends nothing old (T1).
		WPCPM_Institution_Agreement::forget_index_debts();

		if ( $to ) {
			WPCPM_Institution_Copy::forget_pending_marks();
		}

		$out['stored'] = (bool) call_user_func( $store, true );

		// Back to Airtable: the marks go with the store, so no later run sends them over what Airtable comes to hold
		// (T1), and the warning says whose changes Airtable never received (W4).
		if ( ! $to && ! $out['stored'] ) {
			WPCPM_Institution_Copy::forget_pending_marks();

			if ( array() !== $names ) {
				$out['count']   = count( $names );
				$out['names']   = array_slice( $names, 0, self::NAMES );
				$out['warning'] = self::back_sentence( $out['count'], $out['names'] );
			}
		}

		return $out;
	}

	/**
	 * The warning at going back to Airtable: one school by its name, or the count and the first five.
	 *
	 * @param int      $count How many schools' copies are owed.
	 * @param string[] $names The first five of them.
	 * @return string '' for none.
	 */
	public static function back_sentence( $count, array $names ) {
		$count = (int) $count;
		$names = array_values( array_filter( $names, 'is_string' ) );

		if ( $count < 1 || array() === $names ) {
			return '';
		}

		if ( 1 === $count ) {
			return sprintf(
				/* translators: %s: an institution's name. */
				__( 'The site reads Airtable again. Airtable never received the last changes of %s, and the site will not send them later: write them in Airtable by hand if they matter.', 'wpcredits-program-manager' ),
				$names[0]
			);
		}

		return sprintf(
			/* translators: 1: how many institutions, 2: the names of the first five of them. */
			_n( 'The site reads Airtable again. Airtable never received the last changes of %1$d institutions (%2$s), and the site will not send them later: write them in Airtable by hand if they matter.', 'The site reads Airtable again. Airtable never received the last changes of %1$d institutions (%2$s), and the site will not send them later: write them in Airtable by hand if they matter.', $count, 'wpcredits-program-manager' ),
			$count,
			wp_sprintf_l( '%l', $names )
		);
	}
}
