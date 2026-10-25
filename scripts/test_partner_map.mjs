// Tests for assets/partner-map.js, the map shared by the dashboard and map.html.
// Run from the repository root: node --test scripts/test_partner_map.mjs
//
// The script is loaded into a bare context with only `window`: everything that needs
// Leaflet lives inside init(), so popupHtml can be tested without a browser.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const SOURCE = new URL('../assets/partner-map.js', import.meta.url);
const ctx = { window: {} };
vm.runInNewContext(readFileSync(SOURCE, 'utf8'), ctx);
const { popupHtml } = ctx.window.WPCreditsPartnerMap;

const m = (url) => ({ city: 'C', country: 'X', institutions: [{ name: 'N', url }] });

test('escapes names and cities', () => {
  const html = popupHtml({ city: 'A & B', country: 'X', institutions: [{ name: 'Pundra <b>U</b> & Tech', url: 'https://p.example' }] });
  assert.ok(html.includes('A &amp; B'));
  assert.ok(html.includes('Pundra &lt;b&gt;U&lt;/b&gt; &amp; Tech'));
  assert.ok(!html.includes('<b>U</b>'));
});

test('links only http(s) urls, in a new tab', () => {
  assert.ok(popupHtml(m('https://a.example')).includes('href="https://a.example" target="_blank" rel="noopener noreferrer"'));
  assert.ok(!popupHtml(m('javascript:alert(1)')).includes('href'));
  assert.ok(!popupHtml(m(null)).includes('href'));
});

test('keeps phone scrolling free', () => {
  const src = readFileSync(SOURCE, 'utf8');
  assert.ok(src.includes('scrollWheelZoom: false'));
  assert.ok(src.includes('dragging: !L.Browser.mobile'));
  assert.ok(src.includes('https://tile.openstreetmap.org/{z}/{x}/{y}.png'));
});
