// Run with: node tests/js/ride-sidebar-save.cjs. Prints OK on success.
// Loads editor/ride-sidebar.js against stub wp.* globals and checks the apiFetch
// middleware that keeps unchanged pairing links out of a ride save.
const assert = require('assert');
const fs = require('fs');
let mw, render;
const stored = { 12: { meta: { dp_pairings: [ { pairing_id: 1, blurb: 'a' } ], dp_gist: 'old' } } };
const noop = () => null;
// What the sidebar shows for ride 12; rendering the panel records its pairing IDs.
let shownMeta = { dp_pairings: [ { pairing_id: 1, blurb: 'a' }, { pairing_id: 2, blurb: 'c' } ] };
global.window = { dropAndPair: {} };
const wp = {
  element: { createElement: (type, props) => ({ type, props }), Fragment: noop, useState: () => [ '', noop ], useEffect: (f) => f() },
  data: {
    useSelect: (f) => f(() => ({ getCurrentPostId: () => 12, getCurrentPostType: () => 'dp_ride' })),
    select: () => ({ getRawEntityRecord: (k, n, id) => stored[id] }),
  },
  coreData: { useEntityProp: () => [ shownMeta, noop ] }, components: {}, i18n: { __: (s) => s },
  editor: {}, plugins: { registerPlugin: (n, s) => { render = s.render; } }, apiFetch: { use: (f) => { mw = f; } },
};
window.wp = wp;
new Function('window', fs.readFileSync(require('path').join(__dirname, '../../wp-content/plugins/drop-and-pair/editor/ride-sidebar.js'), 'utf8'))(window);
const sent = (o) => mw(o, (x) => x);
// Gist edit: merged meta carries the loaded links; they're dropped.
let out = sent({ path: '/wp/v2/dp_ride/12', method: 'PUT', data: { id: 12, meta: { dp_pairings: [ { pairing_id: 1, blurb: 'a' } ], dp_gist: 'new' } } });
assert.deepStrictEqual(out.data.meta, { dp_gist: 'new' });
assert.strictEqual(out.data.id, 12);
// Autosave and a locale query string too.
out = sent({ path: '/wp/v2/dp_ride/12/autosaves?_locale=user', method: 'POST', data: { meta: { dp_pairings: [ { pairing_id: 1, blurb: 'a' } ] } } });
assert.deepStrictEqual(out.data.meta, {});
// Links edited before the sidebar has shown any: sent as they are, with no base.
const edited = { path: '/wp/v2/dp_ride/12', data: { meta: { dp_pairings: [ { pairing_id: 1, blurb: 'b' } ] } } };
assert.strictEqual(sent(edited), edited);
// Once the panel has rendered, an edited list carries every pairing ID it has shown.
const panel = render();
panel.type(panel.props);
out = sent(edited);
assert.deepStrictEqual(out.data.meta, edited.data.meta);
assert.deepStrictEqual(out.data.dp_pairings_base, [ 1, 2 ]);
assert.strictEqual(edited.data.dp_pairings_base, undefined);
// IDs stay in the base after the link leaves the list, so its removal is honoured.
shownMeta = { dp_pairings: [ { pairing_id: 1, blurb: 'a' }, { pairing_id: 3, blurb: 'new' } ] };
panel.type(panel.props);
assert.deepStrictEqual(sent(edited).data.dp_pairings_base, [ 1, 2, 3 ]);
// Unchanged links are still left out, with no base.
out = sent({ path: '/wp/v2/dp_ride/12', data: { meta: { dp_pairings: [ { pairing_id: 1, blurb: 'a' } ] } } });
assert.deepStrictEqual(out.data, { meta: {} });
// Other paths and types untouched.
const other = { path: '/wp/v2/dp_ride/123', data: { meta: { dp_pairings: [] } } };
assert.strictEqual(sent(other), other);
const park = { path: '/wp/v2/dp_park/12', data: { meta: { dp_pairings: [ { pairing_id: 1, blurb: 'a' } ] } } };
assert.strictEqual(sent(park), park);
const get = { path: '/wp/v2/dp_ride/12?context=edit' };
assert.strictEqual(sent(get), get);
console.log('OK');
