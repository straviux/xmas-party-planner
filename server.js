// Christmas Party — JavaScript only + SQLite, zero dependencies.
// Run: node server.js  (Node >= 22, uses node:sqlite)
// Then open http://localhost:3000  — or the PORT your host gives you.
// The SQLite file (data.db) lives OUTSIDE public/, so it can never be
// downloaded via the static file server. Only the API in this file reads it.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { DatabaseSync } from 'node:sqlite';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DB_PATH = process.env.DB_PATH || path.join(__dirname, 'data.db');
const PUBLIC_DIR = path.join(__dirname, 'public');
const PORT = Number(process.env.PORT || 3000);

const db = new DatabaseSync(DB_PATH);
db.exec(`
  CREATE TABLE IF NOT EXISTS meta (id INTEGER PRIMARY KEY CHECK (id=1), party TEXT, budget TEXT, host_pin_hash TEXT, created_at TEXT);
  CREATE TABLE IF NOT EXISTS guests (id TEXT PRIMARY KEY, display_name TEXT NOT NULL, nickname TEXT DEFAULT '', aliases TEXT DEFAULT '[]', pin_hash TEXT, recipient_id TEXT, claimed INTEGER DEFAULT 0, claimed_at TEXT);
  CREATE TABLE IF NOT EXISTS wishlists (id TEXT PRIMARY KEY, owner_id TEXT NOT NULL, text TEXT NOT NULL, link TEXT DEFAULT '', notes TEXT DEFAULT '', reserved_by TEXT, created_at TEXT);
  CREATE TABLE IF NOT EXISTS poll (id INTEGER PRIMARY KEY CHECK (id=1), question TEXT, open INTEGER DEFAULT 1);
  CREATE TABLE IF NOT EXISTS poll_options (id TEXT PRIMARY KEY, text TEXT NOT NULL, sort_order INTEGER);
  CREATE TABLE IF NOT EXISTS poll_votes (guest_id TEXT PRIMARY KEY, option_id TEXT NOT NULL);
`);

function send(res, code, obj) {
  res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' });
  res.end(JSON.stringify(obj));
}
function norm(s) {
  return String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').replace(/\s+/g, ' ').trim();
}
function hashPin(pin) {
  const salt = crypto.randomBytes(16).toString('hex');
  const hash = crypto.scryptSync(String(pin), salt, 64).toString('hex');
  return `${salt}:${hash}`;
}
function verifyPin(pin, stored) {
  if (!stored) return false;
  const [salt, hash] = String(stored).split(':');
  if (!salt || !hash) return false;
  const test = crypto.scryptSync(String(pin), salt, 64).toString('hex');
  return crypto.timingSafeEqual(Buffer.from(hash, 'hex'), Buffer.from(test, 'hex'));
}
function newId(prefix) { return prefix + crypto.randomBytes(6).toString('hex'); }
function publicGuest(g) {
  return { id: g.id, display: g.display_name, nickname: g.nickname, aliases: JSON.parse(g.aliases || '[]'), claimed: !!g.claimed, needs_pin: !!g.pin_hash };
}
function getMeta() { return db.prepare('SELECT * FROM meta WHERE id=1').get() || null; }
function getGuest(id) { return db.prepare('SELECT * FROM guests WHERE id=?').get(id) || null; }
function derangement(ids) {
  if (ids.length < 2) return null;
  for (let t = 0; t < 500; t++) {
    const r = [...ids].sort(() => Math.random() - 0.5);
    if (r.every((v, i) => v !== ids[i])) return r;
  }
  const r = [...ids]; r.push(r.shift()); return r;
}
function pollPublic(me) {
  const p = db.prepare('SELECT * FROM poll WHERE id=1').get();
  if (!p) return null;
  const options = db.prepare('SELECT * FROM poll_options ORDER BY sort_order').all();
  const counts = {};
  options.forEach(o => counts[o.id] = 0);
  db.prepare('SELECT option_id, COUNT(*) c FROM poll_votes GROUP BY option_id').all()
    .forEach(row => { if (counts[row.option_id] !== undefined) counts[row.option_id] = row.c; });
  const total = Object.values(counts).reduce((a, b) => a + b, 0);
  const myVote = me ? (db.prepare('SELECT option_id FROM poll_votes WHERE guest_id=?').get(me)?.option_id || null) : null;
  return { question: p.question, options: options.map(o => ({ id: o.id, text: o.text, votes: counts[o.id] })), total, open: !!p.open, my_vote: myVote };
}
function requireHost(body, meta) {
  if (!meta || !verifyPin(body.host_pin || '', meta.host_pin_hash)) return false;
  return true;
}
function readBody(req) {
  return new Promise(resolve => {
    let data = '';
    req.on('data', c => { data += c; if (data.length > 1e6) req.destroy(); });
    req.on('end', () => { try { resolve(data ? JSON.parse(data) : {}); } catch { resolve({}); } });
  });
}

const MIME = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript; charset=utf-8', '.json': 'application/json', '.png': 'image/png', '.svg': 'image/svg+xml', '.ico': 'image/x-icon' };

const server = http.createServer(async (req, res) => {
  try {
    const url = new URL(req.url, 'http://localhost');
    const pathname = url.pathname;

    // ---- API ----
    if (pathname.startsWith('/api/')) {
      const action = pathname.slice(5);
      const body = req.method === 'POST' ? await readBody(req) : {};
      const meta = getMeta();

      if (action === 'status') {
        if (!meta) return send(res, 200, { exists: false });
        const total = db.prepare('SELECT COUNT(*) c FROM guests').get().c;
        const claimed = db.prepare('SELECT COUNT(*) c FROM guests WHERE claimed=1').get().c;
        return send(res, 200, { exists: true, party: meta.party, budget: meta.budget, total, claimed });
      }
      if (action === 'list') {
        if (!meta) return send(res, 404, { error: 'not_set_up' });
        const guests = db.prepare('SELECT * FROM guests ORDER BY display_name').all().map(publicGuest);
        return send(res, 200, { party: meta.party, budget: meta.budget, guests });
      }
      if (action === 'setup') {
        if (meta) return send(res, 409, { error: 'already_set_up' });
        const party = String(body.party || 'Christmas Party').trim();
        const budget = String(body.budget || '').trim();
        if (String(body.host_pin || '').length < 4) return send(res, 400, { error: 'Host PIN must be at least 4 characters' });
        const guestsIn = Array.isArray(body.guests) ? body.guests.filter(g => String(g.display || '').trim()) : [];
        if (guestsIn.length < 2) return send(res, 400, { error: 'Add at least 2 guests' });
        const seen = new Set(); const guests = [];
        for (const gi of guestsIn) {
          const display = String(gi.display).trim();
          const key = norm(display);
          if (!key || seen.has(key)) return send(res, 400, { error: 'Duplicate guest name: ' + display });
          seen.add(key);
          const pin = String(gi.pin || '').trim();
          guests.push({ id: newId('g'), display_name: display, nickname: String(gi.nickname || '').trim(),
            aliases: JSON.stringify((gi.aliases || []).map(a => String(a).trim()).filter(Boolean)),
            pin_hash: pin ? hashPin(pin) : null });
        }
        const recipients = derangement(guests.map(g => g.id));
        const insert = db.prepare('INSERT INTO guests (id, display_name, nickname, aliases, pin_hash, recipient_id) VALUES (?,?,?,?,?,?)');
        db.exec('BEGIN');
        try {
          db.prepare('INSERT INTO meta (id, party, budget, host_pin_hash, created_at) VALUES (1,?,?,?,?)')
            .run(party, budget, hashPin(body.host_pin), new Date().toISOString());
          guests.forEach((g, i) => insert.run(g.id, g.display_name, g.nickname, g.aliases, g.pin_hash, recipients[i]));
          db.exec('COMMIT');
        } catch (e) { db.exec('ROLLBACK'); throw e; }
        return send(res, 200, { ok: true, total: guests.length });
      }
      if (action === 'reveal') {
        if (!meta) return send(res, 404, { error: 'not_set_up' });
        const g = getGuest(String(body.id || ''));
        if (!g) return send(res, 404, { error: 'Guest not found' });
        if (g.claimed) return send(res, 409, { error: 'already_claimed' });
        if (g.pin_hash && !verifyPin(body.pin || '', g.pin_hash)) return send(res, 403, { error: 'pin_incorrect' });
        const r = getGuest(g.recipient_id);
        if (!r) return send(res, 500, { error: 'Draw is broken — ask the host to reset' });
        db.prepare('UPDATE guests SET claimed=1, claimed_at=? WHERE id=?').run(new Date().toISOString(), g.id);
        return send(res, 200, { ok: true, recipient: r.display_name, recipient_nickname: r.nickname, budget: meta.budget, party: meta.party });
      }
      if (action === 'host_status') {
        if (!meta) return send(res, 404, { error: 'not_set_up' });
        if (!requireHost(body, meta)) return send(res, 403, { error: 'host_pin_incorrect' });
        const guests = db.prepare('SELECT * FROM guests ORDER BY display_name').all().map(g => ({
          id: g.id, display: g.display_name, nickname: g.nickname, aliases: JSON.parse(g.aliases || '[]'),
          claimed: !!g.claimed, claimed_at: g.claimed_at || null }));
        return send(res, 200, { party: meta.party, budget: meta.budget, guests });
      }
      if (action === 'reset_one') {
        if (!meta) return send(res, 404, { error: 'not_set_up' });
        if (!requireHost(body, meta)) return send(res, 403, { error: 'host_pin_incorrect' });
        db.prepare('UPDATE guests SET claimed=0, claimed_at=NULL WHERE id=?').run(String(body.id || ''));
        return send(res, 200, { ok: true });
      }
      if (action === 'reset_all') {
        if (!meta) return send(res, 404, { error: 'not_set_up' });
        if (!requireHost(body, meta)) return send(res, 403, { error: 'host_pin_incorrect' });
        db.exec('DELETE FROM poll_votes; DELETE FROM poll_options; DELETE FROM poll; DELETE FROM wishlists; DELETE FROM guests; DELETE FROM meta;');
        return send(res, 200, { ok: true });
      }
      // ---- Wishlist ----
      if (action === 'wishlist_list') {
        if (!meta) return send(res, 404, { error: 'not_set_up' });
        const me = url.searchParams.get('me') || '';
        const board = db.prepare('SELECT * FROM guests ORDER BY display_name').all().map(g => ({
          id: g.id, display: g.display_name, nickname: g.nickname,
          items: db.prepare('SELECT * FROM wishlists WHERE owner_id=? ORDER BY created_at').all(g.id).map(it => ({
            id: it.id, text: it.text, link: it.link, notes: it.notes,
            reserved: !!it.reserved_by, reserved_by_me: me !== '' && it.reserved_by === me }))
        }));
        return send(res, 200, { board });
      }
      if (action === 'wishlist_add') {
        if (!meta) return send(res, 404, { error: 'not_set_up' });
        const g = getGuest(String(body.guest_id || ''));
        if (!g) return send(res, 404, { error: 'Guest not found' });
        if (g.pin_hash && !verifyPin(body.pin || '', g.pin_hash)) return send(res, 403, { error: 'pin_incorrect' });
        const text = String(body.text || '').trim();
        if (!text) return send(res, 400, { error: 'Wishlist item is empty' });
        let link = String(body.link || '').trim();
        if (link && !/^https?:\/\//i.test(link)) link = 'https://' + link;
        const item = { id: newId('w'), owner_id: g.id, text, link, notes: String(body.notes || '').trim(), created_at: new Date().toISOString() };
        db.prepare('INSERT INTO wishlists (id, owner_id, text, link, notes, reserved_by, created_at) VALUES (?,?,?,?,?,NULL,?)')
          .run(item.id, item.owner_id, item.text, item.link, item.notes, item.created_at);
        return send(res, 200, { ok: true, item: { ...item, reserved_by: null } });
      }
      if (action === 'wishlist_delete') {
        if (!meta) return send(res, 404, { error: 'not_set_up' });
        const g = getGuest(String(body.guest_id || ''));
        if (!g) return send(res, 404, { error: 'Guest not found' });
        if (g.pin_hash && !verifyPin(body.pin || '', g.pin_hash)) return send(res, 403, { error: 'pin_incorrect' });
        db.prepare('DELETE FROM wishlists WHERE id=? AND owner_id=?').run(String(body.item_id || ''), g.id);
        return send(res, 200, { ok: true });
      }
      if (action === 'wishlist_reserve') {
        if (!meta) return send(res, 404, { error: 'not_set_up' });
        const me = getGuest(String(body.guest_id || ''));
        if (!me) return send(res, 404, { error: 'Guest not found' });
        if (me.pin_hash && !verifyPin(body.pin || '', me.pin_hash)) return send(res, 403, { error: 'pin_incorrect' });
        const ownerId = String(body.owner_id || '');
        if (ownerId === me.id) return send(res, 400, { error: 'You cannot reserve your own wishlist item' });
        const it = db.prepare('SELECT * FROM wishlists WHERE id=? AND owner_id=?').get(String(body.item_id || ''), ownerId);
        if (!it) return send(res, 404, { error: 'Item not found' });
        if (it.reserved_by && it.reserved_by !== me.id) return send(res, 409, { error: 'already_reserved' });
        const next = it.reserved_by ? null : me.id;
        db.prepare('UPDATE wishlists SET reserved_by=? WHERE id=?').run(next, it.id);
        return send(res, 200, { ok: true, reserved: !!next });
      }
      // ---- Poll ----
      if (action === 'poll_get') {
        if (!meta) return send(res, 404, { error: 'not_set_up' });
        return send(res, 200, { poll: pollPublic(url.searchParams.get('me') || '') });
      }
      if (action === 'poll_setup') {
        if (!meta) return send(res, 404, { error: 'not_set_up' });
        if (!requireHost(body, meta)) return send(res, 403, { error: 'host_pin_incorrect' });
        const q = String(body.question || 'What should our Christmas theme be?').trim();
        const opts = (body.options || []).map(o => String(o).trim()).filter(Boolean);
        if (!q || opts.length < 2) return send(res, 400, { error: 'Poll needs a question and at least 2 options' });
        db.exec('BEGIN');
        try {
          db.exec('DELETE FROM poll_votes; DELETE FROM poll_options; DELETE FROM poll;');
          db.prepare('INSERT INTO poll (id, question, open) VALUES (1,?,1)').run(q);
          const ins = db.prepare('INSERT INTO poll_options (id, text, sort_order) VALUES (?,?,?)');
          opts.forEach((t, i) => ins.run(newId('p'), t, i));
          db.exec('COMMIT');
        } catch (e) { db.exec('ROLLBACK'); throw e; }
        return send(res, 200, { ok: true, poll: pollPublic('') });
      }
      if (action === 'poll_vote') {
        if (!meta) return send(res, 404, { error: 'not_set_up' });
        const p = db.prepare('SELECT * FROM poll WHERE id=1').get();
        if (!p) return send(res, 404, { error: 'No poll yet' });
        if (!p.open) return send(res, 409, { error: 'poll_closed' });
        const g = getGuest(String(body.guest_id || ''));
        if (!g) return send(res, 404, { error: 'Guest not found' });
        if (g.pin_hash && !verifyPin(body.pin || '', g.pin_hash)) return send(res, 403, { error: 'pin_incorrect' });
        const opt = db.prepare('SELECT * FROM poll_options WHERE id=?').get(String(body.option_id || ''));
        if (!opt) return send(res, 404, { error: 'Option not found' });
        db.prepare('INSERT INTO poll_votes (guest_id, option_id) VALUES (?,?) ON CONFLICT(guest_id) DO UPDATE SET option_id=excluded.option_id')
          .run(g.id, opt.id);
        return send(res, 200, { ok: true, poll: pollPublic(g.id) });
      }
      if (action === 'poll_set_open') {
        if (!meta) return send(res, 404, { error: 'not_set_up' });
        if (!requireHost(body, meta)) return send(res, 403, { error: 'host_pin_incorrect' });
        if (!db.prepare('SELECT * FROM poll WHERE id=1').get()) return send(res, 404, { error: 'No poll yet' });
        db.prepare('UPDATE poll SET open=? WHERE id=1').run(body.open ? 1 : 0);
        return send(res, 200, { ok: true, poll: pollPublic('') });
      }
      if (action === 'poll_reset') {
        if (!meta) return send(res, 404, { error: 'not_set_up' });
        if (!requireHost(body, meta)) return send(res, 403, { error: 'host_pin_incorrect' });
        db.exec('DELETE FROM poll_votes; DELETE FROM poll_options; DELETE FROM poll;');
        return send(res, 200, { ok: true });
      }
      return send(res, 400, { error: 'Unknown action' });
    }

    // ---- Static (public/ only — data.db is never served) ----
    let filePath = pathname === '/' ? 'index.html' : pathname.slice(1);
    filePath = path.normalize(filePath).replace(/^(\.\.[/\\])+/, '');
    const full = path.join(PUBLIC_DIR, filePath);
    if (!full.startsWith(PUBLIC_DIR) || !fs.existsSync(full) || fs.statSync(full).isDirectory()) {
      res.writeHead(404, { 'Content-Type': 'text/plain' }); return res.end('Not found');
    }
    const ext = path.extname(full).toLowerCase();
    res.writeHead(200, { 'Content-Type': MIME[ext] || 'application/octet-stream', 'Cache-Control': 'no-store' });
    fs.createReadStream(full).pipe(res);
  } catch (e) {
    console.error(e);
    if (!res.headersSent) send(res, 500, { error: 'Server error' });
  }
});

server.listen(PORT, () => console.log(`Christmas Party app running at http://localhost:${PORT}`));
