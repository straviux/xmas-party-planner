<?php
// Secret Santa — server API for a website deploy (PHP, no database needed).
// Stores the draw in data.php in the same folder. That file starts with a
// PHP exit guard, so requesting it directly in a browser returns nothing.
// The host dashboard never receives recipient mappings — only claimed status.
// A guest reveal returns ONE recipient, and only if that name is unclaimed.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

define('DATA_FILE', __DIR__ . '/data.php');
define('GUARD', "<?php http_response_code(403); exit; ?>\n");

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

if ($action === 'status') {
  $d = load_data();
  if (!$d) send(['exists' => false]);
  $claimed = 0; foreach ($d['guests'] as $g) if (!empty($g['claimed'])) $claimed++;
  send(['exists' => true, 'party' => $d['party'], 'budget' => $d['budget'],
        'total' => count($d['guests']), 'claimed' => $claimed]);
}

if ($action === 'list') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  $out = [];
  foreach ($d['guests'] as $g) $out[] = public_guest($g);
  send(['party' => $d['party'], 'budget' => $d['budget'], 'guests' => $out]);
}

if ($action === 'setup') {
  if (load_data()) send(['error' => 'already_set_up'], 409);
  $party = trim((string)($input['party'] ?? 'Christmas Party'));
  $budget = trim((string)($input['budget'] ?? ''));
  $host_pin = (string)($input['host_pin'] ?? '');
  $guests_in = $input['guests'] ?? [];
  if (strlen($host_pin) < 4) send(['error' => 'Host PIN must be at least 4 characters'], 400);
  if (!is_array($guests_in) || count($guests_in) < 2) send(['error' => 'Add at least 2 guests'], 400);
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
  if (count($guests) < 2) send(['error' => 'Add at least 2 guests with names'], 400);
  $ids = array_column($guests, 'id');
  $recipients = derangement($ids);
  foreach ($guests as $i => &$g) $g['recipient_id'] = $recipients[$i];
  unset($g);
  $d = ['party' => $party, 'budget' => $budget,
        'host_pin_hash' => password_hash($host_pin, PASSWORD_DEFAULT),
        'created_at' => date('c'), 'guests' => $guests,
        'wishlists' => [], 'poll' => null];
  if (!save_data($d)) send(['error' => 'Could not save. Check folder write permissions.'], 500);
  send(['ok' => true, 'total' => count($guests)]);
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

function require_host($d, $input) {
  $pin = (string)($input['host_pin'] ?? '');
  if (empty($d['host_pin_hash']) || !password_verify($pin, $d['host_pin_hash'])) {
    send(['error' => 'host_pin_incorrect'], 403);
  }
}

if ($action === 'host_status') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  require_host($d, $input);
  $out = [];
  foreach ($d['guests'] as $g) {
    $out[] = ['id' => $g['id'], 'display' => $g['display'], 'nickname' => $g['nickname'],
              'aliases' => $g['aliases'], 'claimed' => !empty($g['claimed']),
              'claimed_at' => $g['claimed_at'] ?? null];
  }
  send(['party' => $d['party'], 'budget' => $d['budget'], 'guests' => $out]);
}

if ($action === 'reset_one') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  require_host($d, $input);
  $id = (string)($input['id'] ?? '');
  foreach ($d['guests'] as &$g) if ($g['id'] === $id) { $g['claimed'] = false; $g['claimed_at'] = null; }
  unset($g);
  save_data($d);
  send(['ok' => true]);
}

if ($action === 'reset_all') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  require_host($d, $input);
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
  foreach ($d['guests'] as $g) {
    $items = [];
    foreach (($d['wishlists'][$g['id']] ?? []) as $it) {
      $items[] = ['id' => $it['id'], 'text' => $it['text'], 'link' => $it['link'],
                  'notes' => $it['notes'], 'reserved' => !empty($it['reserved_by']),
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
  if ($text === '') send(['error' => 'Wishlist item is empty'], 400);
  $link = trim((string)($input['link'] ?? ''));
  if ($link !== '' && !preg_match('#^https?://#i', $link)) $link = 'https://' . $link;
  $item = ['id' => 'w' . bin2hex(random_bytes(6)), 'text' => $text, 'link' => $link,
           'notes' => trim((string)($input['notes'] ?? '')), 'reserved_by' => null, 'created_at' => date('c')];
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
// Theme poll: one vote per guest (changeable until the host closes it),
// results are counts only — never who voted for what.
function poll_public($d, $me) {
  $p = $d['poll'] ?? null;
  if (!$p) return null;
  $counts = [];
  foreach ($p['options'] as $o) $counts[$o['id']] = 0;
  foreach (($p['votes'] ?? []) as $opt) if (isset($counts[$opt])) $counts[$opt]++;
  $opts = [];
  foreach ($p['options'] as $o) $opts[] = ['id' => $o['id'], 'text' => $o['text'], 'votes' => $counts[$o['id']]];
  return ['question' => $p['question'], 'options' => $opts,
          'total' => array_sum($counts), 'open' => !empty($p['open']),
          'my_vote' => ($me !== '' ? ($p['votes'][$me] ?? null) : null)];
}
if ($action === 'poll_get') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  $p = poll_public($d, (string)($_GET['me'] ?? ''));
  if (!$p) send(['poll' => null]);
  send(['poll' => $p]);
}
if ($action === 'poll_setup') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  require_host($d, $input);
  $q = trim((string)($input['question'] ?? 'What should our Christmas theme be?'));
  $opts_in = [];
  foreach ((array)($input['options'] ?? []) as $o) { $o = trim((string)$o); if ($o !== '') $opts_in[] = $o; }
  if ($q === '' || count($opts_in) < 2) send(['error' => 'Poll needs a question and at least 2 options'], 400);
  $opts = [];
  foreach ($opts_in as $o) $opts[] = ['id' => 'p' . bin2hex(random_bytes(5)), 'text' => $o];
  $d['poll'] = ['question' => $q, 'options' => $opts, 'votes' => [], 'open' => true];
  save_data($d);
  send(['ok' => true, 'poll' => poll_public($d, '')]);
}
if ($action === 'poll_vote') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  $p = $d['poll'] ?? null;
  if (!$p) send(['error' => 'No poll yet'], 404);
  if (empty($p['open'])) send(['error' => 'poll_closed'], 409);
  $g = find_guest($d, (string)($input['guest_id'] ?? ''));
  if (!$g) send(['error' => 'Guest not found'], 404);
  verify_guest_pin($g, $input);
  $opt = (string)($input['option_id'] ?? '');
  $ok = false; foreach ($p['options'] as $o) if ($o['id'] === $opt) $ok = true;
  if (!$ok) send(['error' => 'Option not found'], 404);
  $d['poll']['votes'][$g['id']] = $opt;
  save_data($d);
  send(['ok' => true, 'poll' => poll_public($d, $g['id'])]);
}
if ($action === 'poll_set_open') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  require_host($d, $input);
  if (empty($d['poll'])) send(['error' => 'No poll yet'], 404);
  $d['poll']['open'] = !empty($input['open']);
  save_data($d);
  send(['ok' => true, 'poll' => poll_public($d, '')]);
}
if ($action === 'poll_reset') {
  $d = load_data();
  if (!$d) send(['error' => 'not_set_up'], 404);
  require_host($d, $input);
  $d['poll'] = null;
  save_data($d);
  send(['ok' => true]);
}

send(['error' => 'Unknown action'], 400);
