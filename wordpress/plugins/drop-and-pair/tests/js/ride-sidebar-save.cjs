// Run with: node tests/js/ride-sidebar-save.cjs. Prints OK on success.
// Loads editor/ride-sidebar.js against stub wp.* globals and checks the apiFetch
// middleware that keeps unchanged pairing links out of a ride save.
const assert = require('assert');
const fs = require('fs');
let mw;
const stored = { 12: { meta: { dp_pairings: [ { pairing_id: 1, blurb: 'a' } ], dp_gist: 'old' } } };
const noop = () => null;
global.window = { dropAndPair: {} };
const wp = {
  element: { createElement: noop, Fragment: noop, useState: noop },
  data: { useSelect: noop, select: () => ({ getRawEntityRecord: (k, n, id) => stored[id] }) },
  coreData: { useEntityProp: noop }, components: {}, i18n: { __: (s) => s },
  plugins: { registerPlugin: noop }, apiFetch: { use: (f) => { mw = f; } },
};
window.wp = wp;
new Function('window', fs.readFileSync(require('path').join(__dirname, '../../editor/ride-sidebar.js'), 'utf8'))(window);
const sent = (o) => mw(o, (x) => x);
// Gist edit: merged meta carries the loaded links; they're dropped.
let out = sent({ path: '/wp/v2/dp_ride/12', method: 'PUT', data: { id: 12, meta: { dp_pairings: [ { pairing_id: 1, blurb: 'a' } ], dp_gist: 'new' } } });
assert.deepStrictEqual(out.data.meta, { dp_gist: 'new' });
assert.strictEqual(out.data.id, 12);
// Autosave and a locale query string too.
out = sent({ path: '/wp/v2/dp_ride/12/autosaves?_locale=user', method: 'POST', data: { meta: { dp_pairings: [ { pairing_id: 1, blurb: 'a' } ] } } });
assert.deepStrictEqual(out.data.meta, {});
// Links edited: sent as they are.
const edited = { path: '/wp/v2/dp_ride/12', data: { meta: { dp_pairings: [ { pairing_id: 1, blurb: 'b' } ] } } };
assert.strictEqual(sent(edited), edited);
// Other paths and types untouched.
const other = { path: '/wp/v2/dp_ride/123', data: { meta: { dp_pairings: [] } } };
assert.strictEqual(sent(other), other);
const park = { path: '/wp/v2/dp_park/12', data: { meta: { dp_pairings: [ { pairing_id: 1, blurb: 'a' } ] } } };
assert.strictEqual(sent(park), park);
const get = { path: '/wp/v2/dp_ride/12?context=edit' };
assert.strictEqual(sent(get), get);
console.log('OK');
