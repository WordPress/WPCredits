<?php
/**
 * The program guides: each one's title, lead-in and the sections it is made of, in reading order.
 *
 * One table, returned as an array, so bin/build-docs.php and the tests read the same source.
 * An optional `demote` lists parts whose headings sit one level down; an optional `reference` lists
 * the parts whose ## headings are marked as reference in the block output.
 *
 * @package WPCreditsProgramManager
 */

return array(
	'students'       => array(
		'title' => 'Student guide',
		'lede'  => 'Everything on your Student Report Card, how to book a call with your mentor, and what to expect from the program.',
		'intro' => "Welcome to the Education Directory. This is where your place on the WordPress Credits Program lives while you are on it: your program details, who your mentor is, and the calendar you book your calls from.\n\nThis guide is written for students. Your mentor and the Administrators have their own.",
		'parts' => array( '00-signing-in', '10-student-card', '11-student-booking', '13-student-feedback', '12-student-help', '14-student-program' ),
	),
	'mentors'        => array(
		'title' => 'Mentor guide',
		'lede'  => 'Your Mentor Report Card, setting the hours students can book, tips, check-ins and a checklist for your mentoring, and what your students see on their own page.',
		'intro' => "Thank you for mentoring on the WordPress Credits Program. This guide covers your own Mentor Report Card, tips, check-in templates and a checklist for working with your students, and, at the end, what your students see on their Student Report Card - you cannot open a student's page yourself, so the whole of it is repeated here.\n\nIf you read one section today, make it *Setting your availability*: until you publish some hours, nobody can book a call with you.",
		'parts' => array(
			'00-signing-in',
			'20-mentor-card',
			'21-mentor-availability',
			'22-mentor-help',
			'23-mentor-tips',
			'24-mentor-check-ins',
			'25-mentor-checklist',
			'26-mentor-student-view',
			'27-mentor-students-read',
			'10-student-card',
			'11-student-booking',
			'13-student-feedback',
			'12-student-help',
			'14-student-program',
		),
		// The Student guide's sections sit one heading level down here, under *What your students
		// read*, so the contents list shows where the mentors' part ends (1.123.53, #283). Their
		// headings keep their words, so their anchors do not change.
		'demote' => array( '10-student-card', '11-student-booking', '13-student-feedback', '12-student-help', '14-student-program' ),
	),
	'administrators' => array(
		'title'     => 'Administrator guide',
		'lede'      => 'What to do, step by step, for institutions, students, mentors and sponsors, and a reference for every screen and setting.',
		'intro'     => "This guide is for the program's Administrators. It starts with the jobs you do, each as numbered steps with the names you will see on the screen. Under them is a reference for every screen, setting and tool.\n\nUse the search box, or the list of sections beside the guide, to find a job.",
		'parts'     => array(
			'60-admin-start',
			'61-admin-steps-institutions',
			'62-admin-steps-students',
			'63-admin-steps-mentors',
			'64-admin-steps-sponsors',
			'65-admin-steps-site',
			'66-admin-where-it-stands',
			'30-admin-wpadmin',
			'31-admin-settings',
			'32-admin-tools',
			'33-admin-access',
			'34-admin-operations',
			'35-admin-feedback',
			'00-signing-in',
			'67-admin-other-guides',
		),
		// The parts the guide view folds under the jobs: their ## headings carry the class wpcpm-guide-ref in the block output.
		'reference' => array( '30-admin-wpadmin', '31-admin-settings', '32-admin-tools', '33-admin-access', '34-admin-operations', '35-admin-feedback', '00-signing-in' ),
	),
	'institutions'   => array(
		'title' => 'Institution guide',
		'lede'  => 'Your Institution Dashboard, your students, your details and your Collaboration Agreement.',
		'intro' => "Thank you for bringing the WordPress Credits Program to your institution. This guide covers the Institution Dashboard, the page your institution has on the Education Directory: your students and the lists you can download, enrolling more of them, the semester report, your institution's details and how to correct them, your colleagues, and your Collaboration Agreement.\n\nIt is written for the people who act for an institution. The program's Administrators have a guide of their own.",
		'parts' => array( '00-signing-in', '50-institution-dashboard', '51-institution-students', '52-institution-details' ),
	),
	'sponsors'       => array(
		'title' => 'Sponsor guide',
		'lede'  => 'Your Sponsor Dashboard, your sponsorship and its events, your offers and codes, your posts, your mentors, your company and its agreement, and what your company can see.',
		'intro' => "Thank you for sponsoring the WordPress Credits Program. This guide covers the Sponsor Dashboard, the page your company has on the Education Directory: your sponsorship and the events you co-host, your offers and the codes students and mentors claim, the posts you write and how they are approved, the mentors you sponsor, your company's details and your Collaboration Agreement, and what the program shows you and what it keeps to itself.\n\nIt is written for the people who act for a sponsor. The program's Administrators have a guide of their own.",
		'parts' => array( '00-signing-in', '40-sponsor-dashboard', '41-sponsor-sponsorship', '42-sponsor-offers', '43-sponsor-posts', '44-sponsor-mentors', '45-sponsor-company', '46-sponsor-visibility' ),
	),
);
