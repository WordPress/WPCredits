// NOT FILMED (round 2): see rig-report.md. The form stays closed until the languages are read from Airtable's
// Languages table, and a written row needs the Students table's schema; the stand-in answers neither, so the
// state cannot be prepared honestly. prepare() refuses until that changes.
// Clip: "A student registered" (docs/sections/62-admin-steps-students.md), steps 1 to 3 (the rest is Airtable and a sync).
// Off camera: an invented school applies, confirms and is approved; the student registration form is switched on
// for the clip (Registrations from students on, Institutions offered set to First Contact Made, the stage a newly
// approved school starts at); an invented student then registers through the public form for that school.
import { applySchool, approveSchool, setSetting, php } from './_lib.mjs';

export const TECH = { name: 'Example Institute of Technology', city: 'Riverton', website: 'tech.example.org', contact: 'Avery Example', email: 'avery.example@example.org' };
export const JAMIE = { name: 'Jamie Example', first: 'Jamie', last: 'Example', email: 'jamie.example@example.org' };

export default {
  slug: 'a-student-registered',
  caption: 'A student registered: read the registration on the Students screen.',
  login: 'wpcredits-local',
  async prepare({ newContext, BASE }) {
    throw new Error('a-student-registered cannot be prepared honestly on the copy: see the header of this file');
    await applySchool(newContext, BASE, TECH);
    await approveSchool(newContext, BASE, TECH.name);
    setSetting('student_registrations_enabled', true);
    setSetting('student_registration_stages', ['First Contact Made']);
  },
  steps: [],
};
