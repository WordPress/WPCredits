# Institutions resend a student's welcome invitation (issue #243): design

16 October 2026. Plugin release 1.122.30 (live runs 1.122.29). Issue: WordPress/WPCredits#243.

Amended the same day with the owner's answers to the plan's review: the card's wording (section 3), the mail refused (sections 3 and 7) and the privacy clause (section 5).

## 1. The problem

When a school adds a student, the program sends the student's welcome invitation once. If the student loses it, or it lands in spam, the school cannot send it again and has to ask the program team, who resend it from wp-admin. The school waits, the team is the go-between, and the student loses days.

## 2. The owner's decisions (16 October 2026)

1. **Who:** every member of the institution, for any student on its roster, as adding and editing students already work (membership is the permission, decision 13 of the institutions design). An Administrator who opens the institution's dashboard through the switcher may press it too; Administrators keep their own Resend in wp-admin.
2. **Which students:** those with an account and no recorded sign-in (the sign-in record, `wpcpm_last_sign_in`, since 1.122.26). A student who has signed in once gets no new control: a lost password has WordPress's own reset.
3. **Limits:** the existing 15 minutes per student (`WPCPM_Mail::may_invite()`: another send would cancel the link just mailed), and a ceiling of 20 resends a day for each institution.
4. **The email and the record:** the student gets the site's own welcome invitation, exactly the email Administrators send from wp-admin (`WPCPM_Students_Sync::send_invite()`), with a new link that replaces the old one. The institution's history records who resent it and when. The program team is not emailed.

## 3. What a member sees

On a student's card on the Institution Dashboard (the roster's student view, `WPCPM_Institution_Student_View`), under the student's identity, one line about signing in, drawn only for a student with no sign-in on record (no `wpcpm_last_sign_in`):

- "No sign-in on record since October 15, 2026. Invitation last sent October 9, 2026." The first date is the day the site began recording sign-ins (`WPCPM_Sign_Ins::since()`), never written into the code; the second is the last invitation (`WPCPM_Mail::last_invited()`); both in the site's date format. When the site has never sent one: "No sign-in on record since October 15, 2026. No invitation sent yet." While the site keeps no sign-in record at all (`since()` is 0), there is no day to name and no line is drawn;
- a button, **Resend invitation** (**Send invitation** when none was sent), a form posting to the site's admin-post handler, locked at its first press and saying it is busy, as the plugin's other one-press buttons are.

The wording says what the site knows. The record began on 15 October 2026 (1.122.26), and its one-off seed could read a sign-in only from a trace still on file, so many students who used the site before that day have none on record. The line corrects itself at each student's next sign-in.

A student with a sign-in on record shows no line and no button: the card says nothing new about them, so the sign-in record is read for one question only (is a sign-in on record for this student) and no date of a sign-in is shown to the school.

After a press the card comes back with one notice:

- sent: "The invitation is on its way to {masked address}. The new link replaces the one in the earlier email." (the address masked as `WPCPM_Mail::mask_address()` masks it);
- too soon: the existing sentence, "This person was sent an invitation less than 15 minutes ago. Another one now would cancel the link in it, so nothing was sent.";
- the day's ceiling: "Your institution has sent 20 invitations today, the most a day allows, so nothing was sent. Try again tomorrow, or write to the program team.";
- signed in meanwhile: "This student has signed in since the page was drawn, so nothing was sent.";
- the mail refused: the shared error sentence the Resend in wp-admin shows, "The invitation could not be sent." The send answers an error (no account, no Student role, the fifteen minutes reached in the same instant), or the host refuses the email, which WordPress reports on `wp_mail_failed` and the press listens for around its own send alone: the day's place is given back and no history row is written; the stamp the send wrote stays, since its new link has replaced the old one either way;
- three more, approved by the owner on 16 October 2026: a first invitation, "The invitation is on its way to {masked address}." (no earlier email to speak of); the history row that would not insert after the email went, "The invitation is on its way to {masked address}, but the entry saying who sent it could not be written, so it is not in this institution's history. Tell your program contact."; and the policy's one refusal, "That record is not on your roster."

## 4. The press, in order

A new admin-post action, `wpcpm_institution_resend_invite`, beside the Institution Dashboard's other student actions, its nonce keyed to the student's account (`wpcpm_institution_resend_invite_{user_id}`), so a form drawn for one student is no use for another:

1. The nonce, then the policy: `WPCPM_Institution_Policy::decide( ACT_RESEND_INVITE, subject_student_account( $user_id ) )`, a new action granted on the member ground for the student's own institution and on the manager ground, refused for everybody else with the policy's one refusal (no hint whether the student exists).
2. The student: the account holds the Student role and has no `wpcpm_last_sign_in` (asked again here, not trusted from the page).
3. The gap: `WPCPM_Mail::may_invite()`; too soon refuses without spending the ceiling.
4. The ceiling: `WPCPM_Ceiling::claim( WPCPM_Institution_Resend::ceiling_key( $institution ), 20, DAY_IN_SECONDS )` (the key is `'institution-resend:'` and the record ID as written, built by the class itself because `WPCPM_Ceiling::key()` lowercases what it is given and a record ID is case-sensitive), claimed only once every refusal above has passed, so a refused press costs the school nothing.
5. The send: `WPCPM_Students_Sync::send_invite( $user_id )`, which stamps the invitation's time (`WPCPM_Mail::stamp_invited()`) and leaves its row in the email log as every invitation does.
6. The record: `WPCPM_Institution_Audit::record()` with the institution, the student's account, the member who pressed and the ground they pressed on, the kind `student-invite-resent`.
7. Back to the card with the notice (`WPCPM_Flash`), the card's own anchor.

## 5. Privacy

The Privacy Notice (page 654) says of the sign-in record: "The people who run the program use them to see which mentors are taking part and who may need a hand." This release lets an institution see whether a sign-in is on record for each of its own students since October 15, 2026, and nothing more (no date of a sign-in), so it can send the invitation again. **For the owner, before the deploy:** a clause in that paragraph, for example "and an institution sees whether a sign-in is on record for each of its students since October 15, 2026, and nothing more, so that it can send the welcome invitation again".

## 6. Guides

The institutions guide gains a paragraph on resending an invitation (where, for whom, the two limits); the Administrators' guide says the institution's history shows each resend.

## 7. Testing

- The policy suite: the new action for the member ground (own institution only), the manager ground, a member of another institution, a student, a signed-out visitor.
- The handler: every refusal in order (nonce, policy, not a student, signed in, too soon, the ceiling at 20 and its key per institution) and that none of them sends, stamps, records or spends the ceiling; the mail refused, with the suite's stand-in firing `wp_mail_failed`: the error notice, no history row and no place spent, the stamp the send wrote kept; the send stamps, logs one email and records one history row; the notice for each outcome.
- The card: no line for a student with a sign-in on record, nor while the site keeps no sign-in record; the two "No sign-in on record since" sentences, the record's first day and the last invitation in the site's date format; the two button labels; the nonce keyed to the account; nothing for a reader the policy refuses.
- A trial on the main-line copy (Studio 8883) as a member and through the switcher, every email read in the copy's own log.

## 8. Not in this release

Resending for a student with no account yet (the roster's rows without one); a bulk resend from the roster; telling the program team.
