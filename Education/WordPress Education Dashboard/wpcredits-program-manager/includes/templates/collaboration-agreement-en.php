<?php
/**
 * The program's Collaboration Agreement, in English, as a block list.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * This is the plugin's copy of the Google Doc "[EN] Collaboration Agreement - WP Credits",
 * the agreement an institution signs before its account sees a roster. It is a list of
 * typed blocks, not HTML, because the same text has to come out as a print document, as
 * the plain text a checksum pins, and as whatever a later renderer wants - and because the
 * wording is a program decision that must not be tangled up with markup.
 *
 * Two owners, one direction. The program (the holder of info@wordpressfoundation.org, or
 * whoever the Education team lead names) owns the wording: the Doc is the master for what
 * the agreement says. The developer owns this copy: it is the master for what the site
 * generates. The program changes the Doc and names the change; the developer updates the
 * blocks, bumps `version` to the Doc's new modified date, refreshes the fixture and lists
 * the change in readme.txt. Nothing here is edited on its own initiative.
 *
 * The text is copied byte for byte, curly quotes included. Two departures, both deliberate:
 * "PLEASE MAKE A COPY" is left out because it is an instruction to a human opening the Doc,
 * not a clause; and the Code of Conduct hyperlink is written out as its words followed by
 * the address in parentheses, because a signed paper copy cannot be clicked.
 *
 * `[Institution Name]` appears exactly twice, in the title line and the first paragraph.
 * `WPCPM_Agreement_Template::load()` refuses the file if that count changes, and
 * `merge()` refuses any bracketed token that survives, so a placeholder added here by
 * mistake is caught before it prints on a document a rector signs.
 *
 * The strings are not wrapped in `__()` on purpose. A translation would be a different
 * agreement, and a different agreement is a sibling file with its own language code, its
 * own version and its own fixture (see `languages()`).
 *
 * bin/fixtures/agreement-template-en.json pins the sha256 of this file's plain text, its
 * version and its load-bearing sentences; bin/test-agreement-template.php checks them.
 * Any edit to the text without a version bump and a fixture refresh fails that test.
 */
return array(
	'language' => 'en',
	'version'  => '2025-11-04',
	'read'     => '2026-09-02',
	'source'   => 'https://docs.google.com/document/d/1iA2kJFx5KRrrpyajisoQa1n_5W4OQGgpnB7jpqjsVnY/edit',
	'blocks'   => array(
		array(
			'type' => 'h1',
			'text' => 'Collaboration Agreement',
		),
		array(
			'type' => 'h2',
			'text' => 'Between the WordPress Foundation and [Institution Name]',
		),
		array(
			'type' => 'p',
			'text' => 'This agreement sets out the understanding between the WordPress Foundation (WPF), a non-profit organization that supports the WordPress open-source project, and [Institution Name] (hereafter referred to as “the Institution”) regarding the participation of students in the WordPress Credits program (hereafter referred to as “the Program”).',
		),
		array(
			'type' => 'p',
			'text' => 'This document is not legally binding. Its purpose is to establish a framework for collaboration and shared expectations between the parties.',
		),
		array(
			'type' => 'h3',
			'text' => '1. Purpose',
		),
		array(
			'type' => 'p',
			'text' => 'The Program offers students practical experience contributing to the WordPress open-source project under the guidance of mentors. Students gain transferable skills in project design, digital collaboration, and community contribution. Institutions may recognize this work as part of their curricular internship, practice, or credit system.',
		),
		array(
			'type' => 'h3',
			'text' => '2. Roles and Responsibilities',
		),
		array(
			'type' => 'label',
			'text' => 'WordPress Foundation (WPF):',
		),
		array(
			'type'  => 'ul',
			'items' => array(
				'Provides structure, resources, and mentorship through the Program.',
				'Ensures students have access to contribution projects and onboarding materials.',
				'Offers feedback on student performance and completion certificates where appropriate.',
				'Maintains communication with the institution regarding program updates and student progress.',
			),
		),
		array(
			'type' => 'label',
			'text' => 'The Institution:',
		),
		array(
			'type'  => 'ul',
			'items' => array(
				'Informs students of the Program requirements, duration, and expectations.',
				'Assists students with any academic recognition, credits, or curricular requirements related to their participation.',
				'Provides a point of contact for coordination with WPF.',
				'Publish a dedicated page about the WP Credits program on the institution’s website.',
			),
		),
		array(
			'type' => 'label',
			'text' => 'Students:',
		),
		array(
			'type'  => 'ul',
			'items' => array(
				'Participate remotely in the Program following the guidelines provided.',
				'Commit to completing the required hours and tasks.',
				// In the Doc "Code of Conduct" is a hyperlink. On paper a link is invisible, so
				// the address is printed after the words it stood behind.
				'Abide by the WordPress community’s Code of Conduct (https://make.wordpress.org/handbook/community-code-of-conduct/).',
			),
		),
		array(
			'type' => 'h3',
			'text' => '3. Program Conditions',
		),
		array(
			'type'  => 'ul',
			'items' => array(
				'The Program is conducted fully online and remotely.',
				'Participation does not create an employment relationship between the student and WPF.',
				'The Program is free of charge for both institutions and students.',
			),
		),
		array(
			'type' => 'h3',
			'text' => '4. Liability and Insurance',
		),
		array(
			'type'  => 'ul',
			'items' => array(
				'WPF does not provide insurance coverage for students participating in the Program.',
				'WPF is not responsible for accidents, injuries, health issues, or any damages that may occur to students during their participation.',
				'The responsibility for ensuring that students are covered by any required insurance rests with the institution and/or the students themselves.',
			),
		),
		array(
			'type' => 'h3',
			'text' => '5. Duration',
		),
		array(
			'type' => 'p',
			'text' => 'The collaboration begins upon the signing of this agreement and remains valid until modified or terminated by either party with written notice.',
		),
		array(
			'type' => 'h3',
			'text' => '6. Acknowledgement',
		),
		array(
			'type' => 'p',
			'text' => 'By signing below, both parties confirm their commitment to collaborate in good faith to support student learning and participation in the WordPress Credits Program.',
		),
		array(
			'type'    => 'signatures',
			'parties' => array(
				array(
					'party' => 'For WordPress Foundation',
					'lines' => array( 'Name', 'Title', 'Date' ),
				),
				array(
					'party' => 'For The Institution',
					'lines' => array( 'Name', 'Title', 'Date' ),
				),
			),
		),
	),
);
