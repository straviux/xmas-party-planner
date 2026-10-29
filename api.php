<?php
// Christmas Party — server API for an Apache/PHP website deploy (no Node,
// no database needed). Frontend is index.html (Vue 3 + DaisyUI + Lucide,
// Material 3 Expressive, mobile-optimized).
// Stores the draw in data.php in the same folder. That file starts with a
// PHP exit guard, so requesting it directly in a browser returns nothing,
// and .htaccess denies it as a second layer.
// Host access is a host PIN set at setup (no Google login): the host
// enters the PIN to manage the party. Guests never log in:
// they enter the host's invite code (stored with the party, shown on the
// dashboard, carried in the QR link) to reach the guest flow. Per-guest
// PINs (optional, set by the host) still protect individual reveals.
// The host dashboard never receives recipient mappings — only claimed status.
// A guest reveal returns ONE recipient, and only if that name is unclaimed.
// Wishlist items have a kind: text, link, or image (screenshot, stored as
// a data URL with the item, under about 2 MB).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

define('DATA_FILE', __DIR__ . '/data.php');
define('SUPERADMIN_FILE', __DIR__ . '/superadmin.php');
define('GUARD', "<?php http_response_code(403); exit; ?>\n");
// TEMPORARY: host sign-in/PIN check disabled for poll management only
// (poll_setup, poll_set_open, poll_reset). Set this to false to re-enable
// it. Guest voting is unchanged — it never required host sign-in.
define('TEMP_POLL_HOST_CHECK_DISABLED', true);

function send($data, $code = 200) {
  http_response_code($code);
  echo json_encode($data);
  exit;
}
function norm($s) {
  // No mbstring dependency: transliterate first (Jóna -> Jona), then
  // strtolower works on plain ASCII. Display names are never changed.
  $s = trim((string)$s);
  $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
  if ($t !== false) $s = $t;
  $s = strtolower($s);
  $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
  return trim(preg_replace('/\s+/', ' ', $s));
}
function load_data() {
  if (!file_exists(DATA_FILE)) return null;
  $raw = file_get_contents(DATA_FILE);
  if ($raw === false) return null;
  // Strip the PHP guard line.
  $pos = strpos($raw, "\n");
  $json = ($pos === false) ? '' : substr($raw, $pos + 1);
  $d = json_decode($json, true);
  return is_array($d) ? $d : null;
}
function save_data($d) {
  $fp = fopen(DATA_FILE, 'c');
  if (!$fp) return false;
  if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }
  ftruncate($fp, 0);
  fwrite($fp, GUARD . json_encode($d, JSON_UNESCAPED_UNICODE));
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  @chmod(DATA_FILE, 0600);
  return true;
}
// Host access is the host PIN only (Google login removed): a host action
// passes if the supplied host PIN matches host_pin_hash. The PIN is set at
// setup and can be changed from Setup (Reset PIN) or by superadmin.
function require_host($input, $d = null) {
  if ($d !== null) {
    $pin = (string)($input['host_pin'] ?? '');
    if ($pin !== '' && !empty($d['host_pin_hash']) && password_verify($pin, $d['host_pin_hash'])) return;
  }
  send(['error' => 'host_not_signed_in'], 403);
}
function invite_code_new() {
  $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
  $c = '';
  for ($i = 0; $i < 6; $i++) $c .= $chars[random_int(0, strlen($chars) - 1)];
  return $c;
}
function public_guest($g) {
  return [
    'id' => $g['id'],
    'display' => $g['display'],
    'nickname' => $g['nickname'],
    'aliases' => $g['aliases'],
    'claimed' => !empty($g['claimed']),
    'needs_pin' => !empty($g['pin_hash']),
  ];
}
function derangement($ids) {
  // Shuffle until nobody gets themselves. 2 people simply swap.
  $n = count($ids);
  if ($n < 2) return null;
  for ($try = 0; $try < 500; $try++) {
    $r = $ids; shuffle($r);
    $ok = true;
    for ($i = 0; $i < $n; $i++) if ($r[$i] === $ids[$i]) { $ok = false; break; }
    if ($ok) return $r;
  }
  // Fallback for n>=2: rotate by one (guaranteed derangement).
  $r = $ids; $first = array_shift($r); $r[] = $first;
  return $r;
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

if ($action === 'invite_check') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  $code = strtoupper(trim((string)($input['code'] ?? '')));
  if ($code === '' || $code !== strtoupper((string)($d['invite_code'] ?? ''))) send(['error' => 'invite_incorrect'], 403);
  send(['ok' => true]);
}
if ($action === 'invite_get') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  require_host($input, $d);
  if (empty($d['invite_code'])) { $d['invite_code'] = invite_code_new(); save_data($d); }
  send(['invite_code' => $d['invite_code']]);
}
if ($action === 'invite_regen') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  require_host($input, $d);
  $d['invite_code'] = invite_code_new(); save_data($d);
  send(['invite_code' => $d['invite_code']]);
}

if ($action === 'status') {
  $d = load_data();
  if (!$d) send(['exists' => false]);
  $claimed = 0; foreach ($d['guests'] as $g) if (!empty($g['claimed'])) $claimed++;
  send(['exists' => true, 'party' => $d['party'], 'budget' => $d['budget'], 'date' => $d['date'] ?? '',
        'total' => count($d['guests']), 'claimed' => $claimed]);
}

function sorted_guests($d) {
  $gs = $d['guests'];
  usort($gs, function($a, $b) { return strcasecmp($a['display'], $b['display']); });
  return $gs;
}
if ($action === 'list') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  $out = [];
  foreach (sorted_guests($d) as $g) $out[] = public_guest($g);
  send(['party' => $d['party'], 'budget' => $d['budget'], 'date' => $d['date'] ?? '', 'guests' => $out]);
}

if ($action === 'setup') {
  if (load_data()) send(['error' => 'already_set_up'], 409);
  $pin_in = (string)($input['host_pin'] ?? '');
  if (strlen($pin_in) < 4) send(['error' => 'host_signin_required'], 400);
  $party = trim((string)($input['party'] ?? 'Christmas Party'));
  $budget = trim((string)($input['budget'] ?? ''));
  $date = trim((string)($input['date'] ?? ''));
  if ($date !== '' && !preg_match('#^\d{4}-\d{2}-\d{2}$#', $date)) send(['error' => 'Invalid party date'], 400);
  $guests_in = $input['guests'] ?? [];
  if (!is_array($guests_in)) $guests_in = [];
  $guests = []; $seen = [];
  foreach ($guests_in as $gi) {
    $display = trim((string)($gi['display'] ?? ''));
    if ($display === '') continue;
    $key = norm($display);
    if ($key === '' || isset($seen[$key])) send(['error' => 'Duplicate guest name: ' . $display], 400);
    $seen[$key] = true;
    $aliases = [];
    foreach ((array)($gi['aliases'] ?? []) as $a) {
      $a = trim((string)$a); if ($a !== '') $aliases[] = $a;
    }
    $pin = trim((string)($gi['pin'] ?? ''));
    $guests[] = [
      'id' => 'g' . bin2hex(random_bytes(6)),
      'display' => $display,
      'nickname' => trim((string)($gi['nickname'] ?? '')),
      'aliases' => array_values(array_unique($aliases)),
      'pin_hash' => ($pin !== '') ? password_hash($pin, PASSWORD_DEFAULT) : null,
      'recipient_id' => null, 'claimed' => false, 'claimed_at' => null,
    ];
  }
  $ids = array_column($guests, 'id');
  $recipients = derangement($ids);
  if ($recipients === null) $recipients = array_fill(0, count($ids), null);
  foreach ($guests as $i => &$g) $g['recipient_id'] = $recipients[$i];
  unset($g);
  $d = ['party' => $party, 'budget' => $budget, 'date' => $date,
        'invite_code' => invite_code_new(),
        'host_pin_hash' => password_hash($pin_in, PASSWORD_DEFAULT),
        'host_google_sub' => '', 'host_email' => '',
        'created_at' => date('c'), 'guests' => $guests,
        'wishlists' => [], 'poll' => null, 'polls' => []];
  if (!save_data($d)) send(['error' => 'Could not save. Check folder write permissions.'], 500);
  send(['ok' => true, 'total' => count($guests)]);
}

if ($action === 'party_update') {
  $d = load_data(); if (!$d) send(['error' => 'not_set_up'], 404);
  require_host($input, $d);
  $date = trim((string)($input['date'] ?? ''));
  if ($date !== '' && !preg_match('#^\d{4}-\d{2}-\d{2}$#', $date)) send(['error' => 'Invalid party date'], 400);
  $party = trim((string)($input['party'] ?? ''));
  if ($party !== '') $d['party'] = $party;
  if (isset($input['budget'])) $d['budget'] = trim((string)$input['budget']);
  $d['date'] = $date;
  if (!save_data($d)) send(['error' => 'Could not save'], 500);
  send(['ok' => true, 'party' => $d['party'], 'budget' => $d['budget'], 'date' => $d['date']]);
}
if ($action === 'host_pin_reset') {
  $d = load_data(); if (!$d) send(['error' => 'not_set_up'], 404);
  $cur = (string)($input['host_pin'] ?? '');
  if ($cur === '' || empty($d['host_pin_hash']) || !password_verify($cur, $d['host_pin_hash'])) send(['error' => 'host_not_signed_in'], 403);
  $new = trim((string)($input['new_pin'] ?? ''));
  if (strlen($new) < 4) send(['error' => 'New PIN must be at least 4 characters'], 400);
  $d['host_pin_hash'] = password_hash($new, PASSWORD_DEFAULT);
  if (!save_data($d)) send(['error' => 'Could not save'], 500);
  send(['ok' => true]);
}
if ($action === 'reveal') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  $id = (string)($input['id'] ?? '');
  foreach ($d['guests'] as &$g) {
    if ($g['id'] !== $id) continue;
    if (!empty($g['claimed'])) send(['error' => 'already_claimed'], 409);
    if (!empty($g['pin_hash'])) {
      $pin = (string)($input['pin'] ?? '');
      if (!password_verify($pin, $g['pin_hash'])) send(['error' => 'pin_incorrect'], 403);
    }
    // Mark claimed BEFORE returning, under the file lock in save_data.
    $g['claimed'] = true; $g['claimed_at'] = date('c');
    $recipient = null;
    foreach ($d['guests'] as $r) if ($r['id'] === $g['recipient_id']) $recipient = $r;
    if (!$recipient) send(['error' => 'Draw is broken — ask the host to reset'], 500);
    if (!save_data($d)) send(['error' => 'Could not save claim'], 500);
    send(['ok' => true, 'recipient' => $recipient['display'],
          'recipient_nickname' => $recipient['nickname'],
          'budget' => $d['budget'], 'party' => $d['party']]);
  }
  unset($g);
  send(['error' => 'Guest not found'], 404);
}

if ($action === 'host_status') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  require_host($input, $d);
  $out = [];
  foreach (sorted_guests($d) as $g) {
    $out[] = ['id' => $g['id'], 'display' => $g['display'], 'nickname' => $g['nickname'],
              'aliases' => $g['aliases'], 'claimed' => !empty($g['claimed']),
              'claimed_at' => $g['claimed_at'] ?? null];
  }
  send(['party' => $d['party'], 'budget' => $d['budget'], 'date' => $d['date'] ?? '', 'guests' => $out]);
}

if ($action === 'reset_one') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  require_host($input, $d);
  $id = (string)($input['id'] ?? '');
  foreach ($d['guests'] as &$g) if ($g['id'] === $id) { $g['claimed'] = false; $g['claimed_at'] = null; }
  unset($g);
  save_data($d);
  send(['ok' => true]);
}

if ($action === 'reset_all') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  require_host($input, $d);
  @unlink(DATA_FILE);
  send(['ok' => true]);
}

// ---------- General party features: wishlist + theme poll ----------
// Wishlist: public by owner name, so a Secret Santa giver can look up
// their recipient's list after revealing. Only the owner can add/delete
// their items (guest PIN checked if set). Reserving is anonymous in the
// public view: an item shows "Reserved", never who reserved it, so two
// givers don't buy the same thing and anonymity is kept.
function find_guest($d, $id) {
  foreach ($d['guests'] as $g) if ($g['id'] === $id) return $g;
  return null;
}
function verify_guest_pin($g, $input) {
  if (!empty($g['pin_hash'])) {
    $pin = (string)($input['pin'] ?? '');
    if (!password_verify($pin, $g['pin_hash'])) send(['error' => 'pin_incorrect'], 403);
  }
}
if ($action === 'wishlist_list') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  $me = (string)($_GET['me'] ?? '');
  $board = [];
  foreach (sorted_guests($d) as $g) {
    $items = [];
    foreach (($d['wishlists'][$g['id']] ?? []) as $it) {
      $items[] = ['id' => $it['id'], 'text' => $it['text'], 'link' => $it['link'],
                  'notes' => $it['notes'],
                  'kind' => ($it['kind'] ?? '') !== '' ? $it['kind'] : (!empty($it['link']) ? 'link' : 'text'),
                  'image' => $it['image'] ?? '',
                  'reserved' => !empty($it['reserved_by']),
                  'reserved_by_me' => ($me !== '' && ($it['reserved_by'] ?? null) === $me)];
    }
    $board[] = ['id' => $g['id'], 'display' => $g['display'], 'nickname' => $g['nickname'], 'items' => $items];
  }
  send(['board' => $board]);
}
if ($action === 'wishlist_add') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  $g = find_guest($d, (string)($input['guest_id'] ?? ''));
  if (!$g) send(['error' => 'Guest not found'], 404);
  verify_guest_pin($g, $input);
  $text = trim((string)($input['text'] ?? ''));
  $link = trim((string)($input['link'] ?? ''));
  if ($link !== '' && !preg_match('#^https?://#i', $link)) $link = 'https://' . $link;
  $kind = in_array(($input['kind'] ?? ''), ['text', 'link', 'image'], true) ? $input['kind'] : ($link !== '' ? 'link' : 'text');
  $image = trim((string)($input['image'] ?? ''));
  if ($image !== '') {
    if (!preg_match('#^data:image/(png|jpeg|gif|webp|avif);base64,[A-Za-z0-9+/=]+$#', $image))
      send(['error' => 'Screenshot must be a PNG, JPEG, GIF, WebP or AVIF image'], 400);
    if (strlen($image) > 14000000)
      send(['error' => 'Screenshot is too large — keep it under 10 MB'], 400);
  }
  // Convert to AVIF on the server when GD supports it, to shrink stored screenshots.
  // If GD/AVIF is unavailable (or conversion fails), the original upload is stored as-is.
  if ($image !== '' && function_exists('imageavif') && function_exists('imagecreatefromstring')) {
    $raw = base64_decode(preg_replace('#^data:image/[^;]+;base64,#', '', $image));
    $img = $raw !== false ? @imagecreatefromstring($raw) : false;
    if ($img !== false) {
      if (function_exists('imageistruecolor') && !imageistruecolor($img)) imagepalettetotruecolor($img);
      if (function_exists('imagesavealpha')) imagesavealpha($img, true);
      ob_start(); $avifOk = imageavif($img, null, 80); $avifData = ob_get_clean();
      imagedestroy($img);
      if ($avifOk && $avifData !== '') $image = 'data:image/avif;base64,' . base64_encode($avifData);
    }
  }
  if ($text === '' && $link === '' && $image === '') send(['error' => 'Wishlist item is empty'], 400);
  $item = ['id' => 'w' . bin2hex(random_bytes(6)), 'text' => $text, 'link' => $link,
           'notes' => trim((string)($input['notes'] ?? '')), 'kind' => $kind, 'image' => $image,
           'reserved_by' => null, 'created_at' => date('c')];
  if (!isset($d['wishlists'])) $d['wishlists'] = [];
  $d['wishlists'][$g['id']][] = $item;
  if (!save_data($d)) send(['error' => 'Could not save'], 500);
  send(['ok' => true, 'item' => $item]);
}
if ($action === 'wishlist_delete') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  $g = find_guest($d, (string)($input['guest_id'] ?? ''));
  if (!$g) send(['error' => 'Guest not found'], 404);
  verify_guest_pin($g, $input);
  $item_id = (string)($input['item_id'] ?? '');
  $list = $d['wishlists'][$g['id']] ?? [];
  $d['wishlists'][$g['id']] = array_values(array_filter($list, function($it) use ($item_id) { return $it['id'] !== $item_id; }));
  save_data($d);
  send(['ok' => true]);
}
if ($action === 'wishlist_reserve') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  $me = find_guest($d, (string)($input['guest_id'] ?? ''));
  if (!$me) send(['error' => 'Guest not found'], 404);
  verify_guest_pin($me, $input);
  $owner_id = (string)($input['owner_id'] ?? '');
  $item_id = (string)($input['item_id'] ?? '');
  if ($owner_id === $me['id']) send(['error' => 'You cannot reserve your own wishlist item'], 400);
  // Iterate by index into $d itself — foreach over a ?? [] copy would
  // never persist the change (that was a real bug caught in testing).
  if (!isset($d['wishlists'][$owner_id])) send(['error' => 'Item not found'], 404);
  foreach ($d['wishlists'][$owner_id] as $idx => $it) {
    if ($it['id'] !== $item_id) continue;
    if (!empty($it['reserved_by']) && $it['reserved_by'] !== $me['id']) send(['error' => 'already_reserved'], 409);
    $new = (!empty($it['reserved_by'])) ? null : $me['id'];
    $d['wishlists'][$owner_id][$idx]['reserved_by'] = $new;
    save_data($d);
    send(['ok' => true, 'reserved' => !empty($new)]);
  }
  send(['error' => 'Item not found'], 404);
}
// Theme polls: a party can have several polls (one vote per guest per poll,
// changeable until the host closes that poll), results are counts only —
// never who voted for what. Older parties stored a single poll in $d['poll'];
// polls_list() migrates that into the $d['polls'] list on first poll action.
function polls_list($d) {
  if (!empty($d['polls']) && is_array($d['polls'])) return $d['polls'];
  if (!empty($d['poll'])) { $p = $d['poll']; if (empty($p['id'])) $p['id'] = 'poll1'; return [$p]; }
  return [];
}
function poll_index($polls, $id) {
  foreach ($polls as $i => $p) if (($p['id'] ?? '') === $id) return $i;
  return -1;
}
function poll_public_one($p, $me) {
  $counts = [];
  foreach ($p['options'] as $o) $counts[$o['id']] = 0;
  foreach (($p['votes'] ?? []) as $opt) if (isset($counts[$opt])) $counts[$opt]++;
  $opts = [];
  foreach ($p['options'] as $o) $opts[] = ['id' => $o['id'], 'text' => $o['text'], 'votes' => $counts[$o['id']]];
  return ['id' => $p['id'] ?? '', 'question' => $p['question'], 'options' => $opts,
          'total' => array_sum($counts), 'open' => !empty($p['open']),
          'my_vote' => ($me !== '' ? ($p['votes'][$me] ?? null) : null)];
}
function polls_public($d, $me) {
  $out = [];
  foreach (polls_list($d) as $p) $out[] = poll_public_one($p, $me);
  return $out;
}
function poll_resolve($d, $input) {
  $polls = polls_list($d);
  $id = (string)($input['poll_id'] ?? '');
  if ($id === '') {
    if (count($polls) === 1) return [0, $polls];
    if (count($polls) === 0) send(['error' => 'No poll yet'], 404);
    send(['error' => 'poll_id required'], 400);
  }
  $i = poll_index($polls, $id);
  if ($i < 0) send(['error' => 'Poll not found'], 404);
  return [$i, $polls];
}
if ($action === 'poll_get') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  $list = polls_public($d, (string)($_GET['me'] ?? ''));
  send(['polls' => $list, 'poll' => $list[0] ?? null]);
}
if ($action === 'poll_setup') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  if (!TEMP_POLL_HOST_CHECK_DISABLED) require_host($input, $d); // TEMPORARY: see flag above
  $q = trim((string)($input['question'] ?? 'What should our Christmas theme be?'));
  $opts_in = [];
  foreach ((array)($input['options'] ?? []) as $o) { $o = trim((string)$o); if ($o !== '') $opts_in[] = $o; }
  if ($q === '' || count($opts_in) < 2) send(['error' => 'Poll needs a question and at least 2 options'], 400);
  $opts = [];
  foreach ($opts_in as $o) $opts[] = ['id' => 'p' . bin2hex(random_bytes(5)), 'text' => $o];
  $polls = polls_list($d);
  $new = ['id' => 'pl' . bin2hex(random_bytes(5)), 'question' => $q, 'options' => $opts, 'votes' => [], 'open' => true, 'created_at' => date('c')];
  $polls[] = $new;
  $d['polls'] = $polls; unset($d['poll']);
  save_data($d);
  send(['ok' => true, 'polls' => polls_public($d, ''), 'poll' => poll_public_one($new, '')]);
}
if ($action === 'poll_vote') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  list($i, $polls) = poll_resolve($d, $input);
  $p = $polls[$i];
  if (empty($p['open'])) send(['error' => 'poll_closed'], 409);
  $g = find_guest($d, (string)($input['guest_id'] ?? ''));
  if (!$g) send(['error' => 'Guest not found'], 404);
  verify_guest_pin($g, $input);
  $opt = (string)($input['option_id'] ?? '');
  $ok = false; foreach ($p['options'] as $o) if ($o['id'] === $opt) $ok = true;
  if (!$ok) send(['error' => 'Option not found'], 404);
  $polls[$i]['votes'][$g['id']] = $opt;
  $d['polls'] = $polls; unset($d['poll']);
  save_data($d);
  send(['ok' => true, 'polls' => polls_public($d, $g['id']), 'poll' => poll_public_one($polls[$i], $g['id'])]);
}
if ($action === 'poll_set_open') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  if (!TEMP_POLL_HOST_CHECK_DISABLED) require_host($input, $d); // TEMPORARY: see flag above
  list($i, $polls) = poll_resolve($d, $input);
  $polls[$i]['open'] = !empty($input['open']);
  $d['polls'] = $polls; unset($d['poll']);
  save_data($d);
  send(['ok' => true, 'polls' => polls_public($d, ''), 'poll' => poll_public_one($polls[$i], '')]);
}
if ($action === 'poll_edit') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  if (!TEMP_POLL_HOST_CHECK_DISABLED) require_host($input, $d); // TEMPORARY: see flag above
  list($i, $polls) = poll_resolve($d, $input);
  $q = trim((string)($input['question'] ?? ''));
  $opts_in = [];
  foreach ((array)($input['options'] ?? []) as $o) { $o = trim((string)$o); if ($o !== '') $opts_in[] = $o; }
  if ($q === '' || count($opts_in) < 2) send(['error' => 'Poll needs a question and at least 2 options'], 400);
  // Option ids are kept by position, so votes on kept options survive an
  // edit; new options get new ids, dropped options' votes are ignored.
  $old = $polls[$i]['options'];
  $opts = [];
  foreach ($opts_in as $j => $o) $opts[] = ['id' => $old[$j]['id'] ?? ('p' . bin2hex(random_bytes(5))), 'text' => $o];
  $polls[$i]['question'] = $q;
  $polls[$i]['options'] = $opts;
  $d['polls'] = $polls; unset($d['poll']);
  save_data($d);
  send(['ok' => true, 'polls' => polls_public($d, ''), 'poll' => poll_public_one($polls[$i], '')]);
}
if ($action === 'poll_reset') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  if (!TEMP_POLL_HOST_CHECK_DISABLED) require_host($input, $d); // TEMPORARY: see flag above
  $polls = polls_list($d);
  $id = (string)($input['poll_id'] ?? '');
  if ($id !== '') {
    $i = poll_index($polls, $id);
    if ($i < 0) send(['error' => 'Poll not found'], 404);
    array_splice($polls, $i, 1);
  } elseif (count($polls) === 1) {
    $polls = [];
  } elseif (count($polls) === 0) {
    send(['error' => 'No poll yet'], 404);
  } else {
    send(['error' => 'poll_id required'], 400);
  }
  $d['polls'] = $polls; unset($d['poll']);
  save_data($d);
  send(['ok' => true, 'polls' => polls_public($d, '')]);
}

// ---------- Superadmin ----------
if ($action === 'superadmin_status') {
  $s = load_superadmin();
  send(['set' => !empty($s['pass_hash']), 'signed_in' => superadmin_session_valid($input) !== null]);
}
if ($action === 'superadmin_setup') {
  $s = load_superadmin();
  if (!empty($s['pass_hash'])) send(['error' => 'Superadmin account already set — sign in to change it'], 409);
  $user = trim((string)($input['username'] ?? ''));
  $pass = (string)($input['password'] ?? '');
  if ($user === '' || strlen($pass) < 4) send(['error' => 'Choose a username and a password of at least 4 characters'], 400);
  $session = bin2hex(random_bytes(32));
  save_superadmin(['user' => $user, 'pass_hash' => password_hash($pass, PASSWORD_DEFAULT),
                   'token_hash' => hash('sha256', $session), 'expires' => time() + 30 * 24 * 3600, 'created_at' => date('c')]);
  send(['ok' => true, 'session' => $session, 'username' => $user]);
}
if ($action === 'superadmin_login') {
  $s = load_superadmin();
  if (empty($s['pass_hash'])) send(['error' => 'Superadmin account not set yet'], 404);
  $user = trim((string)($input['username'] ?? ''));
  $pass = (string)($input['password'] ?? '');
  if ($user !== ($s['user'] ?? '') || !password_verify($pass, $s['pass_hash'])) send(['error' => 'Incorrect superadmin username or password'], 403);
  $session = bin2hex(random_bytes(32));
  $s['token_hash'] = hash('sha256', $session); $s['expires'] = time() + 30 * 24 * 3600;
  save_superadmin($s);
  send(['ok' => true, 'session' => $session, 'username' => $s['user']]);
}
if ($action === 'superadmin_logout') {
  $s = load_superadmin();
  if ($s) { $s['token_hash'] = null; $s['expires'] = 0; save_superadmin($s); }
  send(['ok' => true]);
}
if ($action === 'superadmin_change_password') {
  require_superadmin($input);
  $s = load_superadmin();
  $pass = (string)($input['new_password'] ?? '');
  if (strlen($pass) < 4) send(['error' => 'New password must be at least 4 characters'], 400);
  $s['pass_hash'] = password_hash($pass, PASSWORD_DEFAULT);
  save_superadmin($s);
  send(['ok' => true]);
}
if ($action === 'superadmin_get') {
  require_superadmin($input);
  $d = load_data();
  if (!$d) send(['exists' => false]);
  $out = [];
  foreach (sorted_guests($d) as $g) {
    $out[] = ['id' => $g['id'], 'display' => $g['display'], 'nickname' => $g['nickname'],
              'aliases' => $g['aliases'], 'claimed' => !empty($g['claimed']),
              'claimed_at' => $g['claimed_at'] ?? null, 'needs_pin' => !empty($g['pin_hash'])];
  }
  // Deliberately no recipient_id here: superadmin controls config/data,
  // but the draw stays blind even to superadmin.
  send(['exists' => true, 'party' => $d['party'], 'budget' => $d['budget'], 'date' => $d['date'] ?? '',
        'invite_code' => $d['invite_code'] ?? '', 'host_email' => $d['host_email'] ?? '',
        'host_pin_set' => !empty($d['host_pin_hash']), 'created_at' => $d['created_at'] ?? '',
        'guests' => $out, 'polls' => polls_public($d, ''),
        'wishlist_items' => array_sum(array_map('count', $d['wishlists'] ?? []))]);
}
if ($action === 'superadmin_party_update') {
  require_superadmin($input);
  $d = load_data(); if (!$d) send(['error' => 'not_set_up'], 404);
  $date = trim((string)($input['date'] ?? ''));
  if ($date !== '' && !preg_match('#^\d{4}-\d{2}-\d{2}$#', $date)) send(['error' => 'Invalid party date'], 400);
  $party = trim((string)($input['party'] ?? ''));
  if ($party !== '') $d['party'] = $party;
  if (isset($input['budget'])) $d['budget'] = trim((string)$input['budget']);
  $d['date'] = $date;
  if (!save_data($d)) send(['error' => 'Could not save'], 500);
  send(['ok' => true]);
}
if ($action === 'superadmin_invite_regen') {
  require_superadmin($input);
  $d = load_data(); if (!$d) send(['error' => 'not_set_up'], 404);
  $d['invite_code'] = invite_code_new(); save_data($d);
  send(['invite_code' => $d['invite_code']]);
}
if ($action === 'superadmin_host_pin_reset') {
  require_superadmin($input);
  $d = load_data(); if (!$d) send(['error' => 'not_set_up'], 404);
  $new = trim((string)($input['new_pin'] ?? ''));
  if (strlen($new) < 4) send(['error' => 'New host PIN must be at least 4 characters'], 400);
  $d['host_pin_hash'] = password_hash($new, PASSWORD_DEFAULT);
  if (!save_data($d)) send(['error' => 'Could not save'], 500);
  send(['ok' => true]);
}
if ($action === 'superadmin_reset_one') {
  require_superadmin($input);
  $d = load_data(); if (!$d) send(['error' => 'not_set_up'], 404);
  $id = (string)($input['id'] ?? '');
  foreach ($d['guests'] as &$g) if ($g['id'] === $id) { $g['claimed'] = false; $g['claimed_at'] = null; }
  unset($g); save_data($d);
  send(['ok' => true]);
}
if ($action === 'superadmin_reset_claims') {
  require_superadmin($input);
  $d = load_data(); if (!$d) send(['error' => 'not_set_up'], 404);
  foreach ($d['guests'] as &$g) { $g['claimed'] = false; $g['claimed_at'] = null; }
  unset($g); save_data($d);
  send(['ok' => true]);
}
if ($action === 'superadmin_redraw') {
  require_superadmin($input);
  $d = load_data(); if (!$d) send(['error' => 'not_set_up'], 404);
  foreach ($d['guests'] as $g) if (!empty($g['claimed'])) send(['error' => 'Cannot redraw after names have been revealed — reset claims first'], 409);
  $ids = array_column($d['guests'], 'id');
  $recipients = derangement($ids);
  if ($recipients === null) $recipients = array_fill(0, count($ids), null);
  foreach ($d['guests'] as $i => &$g) $g['recipient_id'] = $recipients[$i];
  unset($g); save_data($d);
  send(['ok' => true]);
}
if ($action === 'superadmin_clear_wishlists') {
  require_superadmin($input);
  $d = load_data(); if (!$d) send(['error' => 'not_set_up'], 404);
  $d['wishlists'] = []; save_data($d);
  send(['ok' => true]);
}
if ($action === 'superadmin_poll_set_open') {
  require_superadmin($input);
  $d = load_data(); if (!$d) send(['error' => 'not_set_up'], 404);
  list($i, $polls) = poll_resolve($d, $input);
  $polls[$i]['open'] = !empty($input['open']);
  $d['polls'] = $polls; unset($d['poll']); save_data($d);
  send(['ok' => true]);
}
if ($action === 'superadmin_poll_delete') {
  require_superadmin($input);
  $d = load_data(); if (!$d) send(['error' => 'not_set_up'], 404);
  $polls = polls_list($d);
  $id = (string)($input['poll_id'] ?? '');
  $i = poll_index($polls, $id);
  if ($i < 0) send(['error' => 'Poll not found'], 404);
  array_splice($polls, $i, 1);
  $d['polls'] = $polls; unset($d['poll']); save_data($d);
  send(['ok' => true]);
}
if ($action === 'superadmin_reset_all') {
  require_superadmin($input);
  @unlink(DATA_FILE);
  send(['ok' => true]);
}

send(['error' => 'Unknown action'], 400);
