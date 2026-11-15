// Clip: "Read the cohort timeline: do we need more mentors?" (docs/sections/63-admin-steps-mentors.md).
// Nothing to prepare beyond the copy's own state: the timeline shows counts only, read from the copy's records.
export default {
  slug: 'read-the-cohort-timeline',
  caption: 'Read the cohort timeline: do we need more mentors?',
  login: 'wpcredits-local',
  async prepare() {},
  steps: [
    { caption: 'Open the Administrator Dashboard and choose Cohort timeline in the side menu, or press Open the cohort timeline on the Upcoming cohorts card.', go: '/administrator-dashboard/', hold: 1.0 },
    { press: { role: 'link', name: 'Cohort timeline', exact: false }, hold: 1.2 },
    { caption: 'Read the four figures at the top: Starting in the next 6 months, Active mentors, Mentor places and Mentors to recruit.', scrollToControl: { text: 'Starting in the next 6 months', exact: true }, offset: 72, hold: 0.3 },
    { pointerTo: { text: 'Starting in the next 6 months', exact: true }, hold: 0.9 },
    { pointerTo: { text: 'Active mentors', exact: true }, hold: 0.9 },
    { pointerTo: { text: 'Mentor places', exact: true }, hold: 0.9 },
    { pointerTo: { text: 'Mentors to recruit', exact: true }, hold: 1.4, poster: true },
    { caption: 'Read the sentence under them: it says from which month the mentors are short, or tight, or that they cover the next six months.', pointerTo: { text: /the confirmed students alone need more places|are tight|cover the next six months/ }, hold: 3.4 },
    { caption: 'In Month by month, find the first month whose Outlook is not Covered, and read its Balance.', scrollToControl: { role: 'heading', name: 'Month by month' }, offset: 48, hold: 0.6 },
    { pointerTo: { role: 'cell', name: 'SHORT', exact: false }, hold: 1.4 },
    { pointerTo: { role: 'cell', name: /short$/ }, hold: 2.4 },
    { caption: 'If Mentors to recruit is above 0, the program needs that many more mentors by the peak. Deciding the waiting mentor applications is one way to add them.', scrollToControl: { role: 'heading', name: 'Cohort timeline', level: 2 }, offset: 60, hold: 0.3 },
    { pointerTo: { text: 'Mentors to recruit', exact: true }, hold: 3.6 },
  ],
};
