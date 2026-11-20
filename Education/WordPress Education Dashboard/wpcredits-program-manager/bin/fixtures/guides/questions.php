<?php
/**
 * The test set's questions (AI Mentor R1, 1.123.101), read by bin/test-guide-questions.php and by the
 * Docker trial's script, so the trial asks exactly what the suite checks.
 *
 * Each: the role that asks, the question as typed, the English search words the model is expected to give,
 * the sections an answer may come from (any one of them among the five passages), the pages the first
 * passage may be on, and whether the question's own words alone must also find it (the fallback).
 *
 * @package WPCreditsProgramManager
 */

return array(
	// Student guide, asked by a student.
	array( 'student', 'How do I book a call with my mentor?', 'book call mentor calendar time', array( 'student-guide/#booking-a-call-with-your-mentor' ), array( 'student-guide/' ), true ),
	array( 'student', 'I never got the sign-in code by email. What can I do?', 'sign in code email not arrived resend', array( 'student-guide/#if-you-cannot-get-your-code' ), array( 'student-guide/' ), true ),
	array( 'student', '¿Cómo relleno mi informe en la Student Report Card?', 'fill in report form Student Report Card', array( 'student-guide/#report-form', 'faq/#report-form' ), array( 'student-guide/', 'faq/' ), false ),
	array( 'student', 'Devo lasciare il programma prima della fine. Cosa devo fare?', 'leave program early stop pause', array( 'student-guide/#leaving-the-program', 'faq/#pause-or-stop' ), array( 'student-guide/', 'faq/' ), false ),
	array( 'student', 'My status says Pending graduation. Am I still on the program?', 'pending graduation status still on program', array( 'student-guide/#when-your-graduation-is-pending' ), array( 'student-guide/' ), true ),
	array( 'student', 'My institution and my dates are wrong on my card.', 'wrong institution dates card correct', array( 'student-guide/#if-something-looks-wrong', 'faq/#details-wrong' ), array( 'student-guide/', 'faq/' ), true ),
	array( 'student', '¿Tendré un mentor?', 'will I have a mentor', array( 'faq/#mentor', 'student-guide/#my-mentor' ), array( 'faq/', 'student-guide/' ), false ),
	array( 'student', 'Who gives the academic credit for the program?', 'academic credit who gives school', array( 'faq/#academic-credit' ), array( 'faq/', 'handbook:' ), true ),

	// Mentor guide, asked by a mentor.
	array( 'mentor', 'How do I set my availability so students can book calls?', 'set availability hours students book calls', array( 'mentor-guide/#setting-your-availability-so-students-can-book' ), array( 'mentor-guide/' ), true ),
	array( 'mentor', 'What should I talk about in the first check-in with a new student?', 'first check-in new student questions cover', array( 'mentor-guide/#first-check-in', 'handbook:mentor-guide/mentor-check-in-templates/' ), array( 'mentor-guide/', 'handbook:mentor-guide/mentor-check-in-templates/' ), true ),
	array( 'mentor', 'Come vedo quali studenti non si fanno vivi da settimane?', 'students gone quiet not seen days', array( 'mentor-guide/#students-who-have-gone-quiet' ), array( 'mentor-guide/' ), false ),
	array( 'mentor', '¿Puedo escribir una sola nota para todos los estudiantes de una sesión de grupo?', 'one note whole group session attendees', array( 'mentor-guide/#one-note-for-the-whole-group' ), array( 'mentor-guide/' ), false ),
	array( 'mentor', 'How do I check that a student\'s contributions are genuine?', 'verify contributions genuine report tracking verification', array( 'mentor-guide/#tracking-and-verification' ), array( 'mentor-guide/', 'handbook:mentor-guide/mentor-checklist/' ), false ),

	// Institution guide, asked by an institution's member.
	array( 'institution', 'How do we enroll our students?', 'enroll students add list', array( 'institution-guide/#enrolling-students', 'faq/#school-enrolls-students' ), array( 'institution-guide/', 'faq/' ), true ),
	array( 'institution', 'A student\'s name and end date are wrong. Can we correct them?', 'correct student details name end date', array( 'institution-guide/#correcting-a-student-s-details' ), array( 'institution-guide/' ), true ),
	array( 'institution', 'Come aggiungo un collega che possa entrare per il nostro istituto?', 'invite colleague representative sign in institution', array( 'institution-guide/#your-representatives' ), array( 'institution-guide/' ), false ),
	array( 'institution', 'When do we get the semester report, and who writes it?', 'semester report drafted approved', array( 'institution-guide/#the-semester-report' ), array( 'institution-guide/' ), true ),
	array( 'institution', 'How do we record that a student dropped out?', 'mark student dropped out finish placement', array( 'institution-guide/#finishing-a-placement' ), array( 'institution-guide/' ), false ),

	// Sponsor guide, asked by a sponsor's member.
	array( 'sponsor', 'How do I create an offer for students?', 'create new offer students', array( 'sponsor-guide/#making-an-offer' ), array( 'sponsor-guide/' ), true ),
	array( 'sponsor', 'What happens when our discount codes run low?', 'codes run low warning add codes', array( 'sponsor-guide/#when-codes-run-low' ), array( 'sponsor-guide/' ), true ),
	array( 'sponsor', '¿Cómo propongo un evento que organizamos juntos?', 'plan co-host event', array( 'sponsor-guide/#planning-the-event' ), array( 'sponsor-guide/' ), false ),
	array( 'sponsor', 'Who approves our posts before they are published?', 'post review approve publish Administrator', array( 'sponsor-guide/#the-review', 'sponsor-guide/#posts-and-how-they-are-approved' ), array( 'sponsor-guide/' ), true ),
	array( 'sponsor', 'How do I add a colleague to our company\'s account?', 'add colleague people company sign in', array( 'sponsor-guide/#people' ), array( 'sponsor-guide/' ), false ),

	// The handbook, which nobody's own guide covers.
	array( 'student', 'How can I become a mentor for the program?', 'become mentor apply call for mentors', array( 'handbook:how-to-participate/call-for-mentors/' ), array( 'handbook:how-to-participate/call-for-mentors/', 'handbook:mentor-guide/' ), true ),
	array( 'student', 'How can a company sponsor WordPress Credits?', 'company partner sponsor program', array( 'handbook:how-to-participate/sponsors/' ), array( 'handbook:how-to-participate/sponsors/' ), true ),
	array( 'student', 'My university wants to launch a WordPress Credits program. What does it need to do?', 'university launch program institution join', array( 'handbook:how-to-participate/institutions/', 'faq/#who-can-join' ), array( 'handbook:how-to-participate/institutions/', 'faq/' ), true ),

	// The FAQ.
	array( 'student', 'Do I need to know how to code?', 'need know code coding programming', array( 'faq/#do-i-need-to-code' ), array( 'faq/' ), true ),
	array( 'student', '¿Cuesta algo participar en el programa?', 'cost free pay program', array( 'faq/#does-it-cost' ), array( 'faq/' ), false ),
	array( 'student', 'Quale indirizzo email devo usare su Slack?', 'Slack email address which use', array( 'faq/#slack-which-email' ), array( 'faq/' ), false ),
	array( 'student', 'I did not get the welcome email.', 'welcome email invitation not arrived', array( 'faq/#welcome-email-not-arrived' ), array( 'faq/' ), true ),

	// The Administrator guide, for an Administrator.
	array( 'administrator', 'How do I approve a mentor application?', 'approve mentor application decision', array( 'administrator-guide/#a-mentor-applied', 'administrator-guide/#decisions-on-a-mentor-application', 'administrator-guide/#from-application-to-mentoring-the-whole-path' ), array( 'administrator-guide/' ), false ),
	array( 'administrator', 'How does a student get their certificate?', 'student certificate graduation date approve quiz', array( 'administrator-guide/#a-student-is-waiting-to-graduate', 'administrator-guide/#from-first-interest-to-graduation-the-whole-path' ), array( 'administrator-guide/' ), false ),
	array( 'administrator', 'What is needed to sign up an institution?', 'institution application approve school enroll students', array( 'administrator-guide/#from-application-to-first-students-the-whole-path', 'administrator-guide/#a-school-applied', 'administrator-guide/#open-the-institution-application-form' ), array( 'administrator-guide/' ), false ),
);
