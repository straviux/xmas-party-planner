<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PartyException;
use App\Services\PartyStore as S;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function dispatch(Request $request, string $action): JsonResponse
    {
        try {
            $input = $request->json()->all();
            if (!is_array($input) || $input === []) {
                $input = $request->all();
            }
            // Session fallback: guest identity survives a refresh in
            // the Laravel version (see README — this is a deliberate
            // change from the old in-memory-only app). Host auth is
            // session host_user_id, checked by PartyStore::requireHost.
            $result = $this->handle($request, $action, $input);
            return response()->json($result);
        } catch (PartyException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    private function handle(Request $request, string $action, array $input): array
    {
        $d = null;
        $need = fn () => S::load() ?? throw new PartyException('not_set_up', 404);

        switch ($action) {
            case 'invite_check':
                $d = $need();
                $code = strtoupper(trim((string) ($input['code'] ?? '')));
                if ($code === '' || $code !== strtoupper((string) ($d['invite_code'] ?? ''))) {
                    throw new PartyException('invite_incorrect', 403);
                }
                return ['ok' => true];

            case 'invite_get':
                $d = $need();
                S::requireHost($input, $d, $action);
                if (empty($d['invite_code'])) {
                    $d['invite_code'] = S::inviteCodeNew();
                    S::save($d);
                }
                return ['invite_code' => $d['invite_code']];

            case 'invite_regen':
                $d = $need();
                S::requireHost($input, $d, $action);
                $d['invite_code'] = S::inviteCodeNew();
                S::save($d);
                return ['invite_code' => $d['invite_code']];

            case 'status':
                $d = S::load();
                if (!$d) {
                    return ['exists' => false];
                }
                $claimed = 0;
                foreach ($d['guests'] as $g) {
                    if (!empty($g['claimed'])) {
                        $claimed++;
                    }
                }
                return ['exists' => true, 'party' => $d['party'], 'budget' => $d['budget'], 'date' => $d['date'] ?? '', 'total' => count($d['guests']), 'claimed' => $claimed];

            case 'list':
                $d = $need();
                $out = [];
                foreach (S::sortedGuests($d) as $g) {
                    $out[] = S::publicGuest($g);
                }
                return ['party' => $d['party'], 'budget' => $d['budget'], 'date' => $d['date'] ?? '', 'guests' => $out];

            case 'setup': {
                // Host must sign in (username/password) before creating
                // the party — there is no PIN and no default PIN any more.
                S::requireHost($input, null, $action);
                if (S::load()) {
                    throw new PartyException('already_set_up', 409);
                }
                $party = trim((string) ($input['party'] ?? 'Christmas Party'));
                $budget = trim((string) ($input['budget'] ?? ''));
                $date = trim((string) ($input['date'] ?? ''));
                if ($date !== '' && !preg_match('#^\d{4}-\d{2}-\d{2}$#', $date)) {
                    throw new PartyException('Invalid party date', 400);
                }
                $guests = [];
                $seen = [];
                foreach ((array) ($input['guests'] ?? []) as $gi) {
                    $display = trim((string) ($gi['display'] ?? ''));
                    if ($display === '') {
                        continue;
                    }
                    $key = S::norm($display);
                    if ($key === '' || isset($seen[$key])) {
                        throw new PartyException('Duplicate guest name: '.$display, 400);
                    }
                    $seen[$key] = true;
                    $aliases = [];
                    foreach ((array) ($gi['aliases'] ?? []) as $a) {
                        $a = trim((string) $a);
                        if ($a !== '') {
                            $aliases[] = $a;
                        }
                    }
                    $pin = trim((string) ($gi['pin'] ?? ''));
                    if ($pin !== '' && !preg_match('/^\d{4}$/', $pin)) {
                        throw new PartyException('pin_must_be_4_digits', 400);
                    }
                    $guests[] = [
                        'id' => 'g'.bin2hex(random_bytes(6)),
                        'display' => $display,
                        'nickname' => trim((string) ($gi['nickname'] ?? '')),
                        'aliases' => array_values(array_unique($aliases)),
                        'pin_hash' => ($pin !== '') ? password_hash($pin, PASSWORD_DEFAULT) : null,
                        'recipient_id' => null, 'claimed' => false, 'claimed_at' => null,
                    ];
                }
                $ids = array_column($guests, 'id');
                $recipients = S::derangement($ids) ?? array_fill(0, count($ids), null);
                foreach ($guests as $i => &$g) {
                    $g['recipient_id'] = $recipients[$i];
                }
                unset($g);
                $new = ['party' => $party, 'budget' => $budget, 'date' => $date,
                    'invite_code' => S::inviteCodeNew(),
                    'created_at' => date('c'), 'guests' => $guests,
                    'wishlists' => [], 'poll' => null, 'polls' => []];
                if (!S::save($new)) {
                    throw new PartyException('Could not save. Check folder write permissions.', 500);
                }
                return ['ok' => true, 'total' => count($guests)];
            }

            case 'party_update':
                $d = $need();
                S::requireHost($input, $d, $action);
                $date = trim((string) ($input['date'] ?? ''));
                if ($date !== '' && !preg_match('#^\d{4}-\d{2}-\d{2}$#', $date)) {
                    throw new PartyException('Invalid party date', 400);
                }
                $party = trim((string) ($input['party'] ?? ''));
                if ($party !== '') {
                    $d['party'] = $party;
                }
                if (isset($input['budget'])) {
                    $d['budget'] = trim((string) $input['budget']);
                }
                $d['date'] = $date;
                if (!S::save($d)) {
                    throw new PartyException('Could not save', 500);
                }
                return ['ok' => true, 'party' => $d['party'], 'budget' => $d['budget'], 'date' => $d['date']];

            case 'enter':
            case 'reveal': {
                $id = (string) ($input['id'] ?? '');
                $result = S::mutate(function (&$d) use ($id, $input) {
                    foreach ($d['guests'] as &$g) {
                        if ($g['id'] !== $id) {
                            continue;
                        }
                        if (!empty($g['claimed'])) {
                            throw new PartyException('already_claimed', 409);
                        }
                        if (!empty($g['pin_hash'])) {
                            $pin = (string) ($input['pin'] ?? '');
                            if (!password_verify($pin, $g['pin_hash'])) {
                                throw new PartyException('pin_incorrect', 403);
                            }
                        }
                        $g['claimed'] = true;
                        $g['claimed_at'] = date('c');
                        $recipient = null;
                        foreach ($d['guests'] as $r) {
                            if ($r['id'] === $g['recipient_id']) {
                                $recipient = $r;
                            }
                        }
                        if (!$recipient) {
                            throw new PartyException('Draw is broken — ask the host to reset', 500);
                        }
                        return ['ok' => true, 'recipient' => $recipient['display'],
                            'recipient_nickname' => $recipient['nickname'],
                            'budget' => $d['budget'], 'party' => $d['party'],
                            'guest_id' => $g['id']];
                    }
                    throw new PartyException('Guest not found', 404);
                });
                // Guest identity (not the recipient) persists in session
                // so a refresh keeps guest home / wishlist access. The
                // recipient itself is never stored — a refresh after
                // entry shows the locked-guest home, never the recipient.
                session(['guest_id' => $result['guest_id'], 'guest_pin' => (string) ($input['pin'] ?? '')]);
                unset($result['guest_id']);
                return $result;
            }

            case 'host_status':
                // No party yet is a normal state, not an error: return 200
                // with exists=false instead of a 404, so the dashboard
                // shows the "Create party" prompt without a console error.
                // requireHost runs first, so signed-out callers still get 403.
                $d = S::load();
                S::requireHost($input, $d, $action);
                if (!$d) {
                    return ['exists' => false, 'party' => '', 'budget' => '', 'date' => '',
                        'invite_code' => '', 'username' => session('host_username', ''), 'guests' => []];
                }
                $out = [];
                foreach (S::sortedGuests($d) as $g) {
                    $out[] = ['id' => $g['id'], 'display' => $g['display'], 'nickname' => $g['nickname'],
                        'aliases' => $g['aliases'], 'claimed' => !empty($g['claimed']),
                        'claimed_at' => $g['claimed_at'] ?? null, 'pin_set' => !empty($g['pin_hash'])];
                }
                return ['party' => $d['party'], 'budget' => $d['budget'], 'date' => $d['date'] ?? '',
                    'invite_code' => $d['invite_code'] ?? '',
                    'username' => session('host_username', ''), 'guests' => $out];

            case 'guest_logout':
                session()->forget(['guest_id', 'guest_pin']);
                return ['ok' => true];

            case 'host_login': {
                $username = trim((string) ($input['username'] ?? ''));
                $password = (string) ($input['password'] ?? '');
                $user = $username !== '' ? User::where('username', $username)->first() : null;
                if (!$user || !Hash::check($password, $user->password)) {
                    throw new PartyException('Incorrect username or password', 403);
                }
                session(['host_user_id' => $user->id, 'host_username' => $user->username]);
                return ['ok' => true, 'username' => $user->username];
            }

            case 'host_change_password': {
                $user = S::hostUser() ?? throw new PartyException('host_not_signed_in', 403);
                $current = (string) ($input['current_password'] ?? '');
                if (!Hash::check($current, $user->password)) {
                    throw new PartyException('Current password is incorrect', 403);
                }
                $new = (string) ($input['new_password'] ?? '');
                if (strlen($new) < 4) {
                    throw new PartyException('New password must be at least 4 characters', 400);
                }
                // Plain assignment: the User model's hashed cast hashes it.
                $user->password = $new;
                $user->save();
                return ['ok' => true];
            }

            case 'host_logout':
                session()->forget(['host_user_id', 'host_username']);
                return ['ok' => true];

            case 'reset_one':
                $d = $need();
                S::requireHost($input, $d, $action);
                $id = (string) ($input['id'] ?? '');
                foreach ($d['guests'] as &$g) {
                    if ($g['id'] === $id) {
                        $g['claimed'] = false;
                        $g['claimed_at'] = null;
                    }
                }
                unset($g);
                S::save($d);
                $out = [];
                foreach (S::sortedGuests($d) as $g2) {
                    $out[] = ['id' => $g2['id'], 'display' => $g2['display'], 'nickname' => $g2['nickname'],
                        'aliases' => $g2['aliases'], 'claimed' => !empty($g2['claimed']),
                        'claimed_at' => $g2['claimed_at'] ?? null];
                }
                return ['ok' => true, 'guests' => $out];

            case 'reset_all':
                $d = $need();
                S::requireHost($input, $d, $action);
                S::deleteParty();
                session()->forget(['host_user_id', 'host_username', 'guest_id', 'guest_pin']);
                return ['ok' => true];

            case 'host_guest_add':
            case 'host_guest_update':
            case 'host_guest_remove': {
                $d = $need();
                S::requireHost($input, $d, $action);
                foreach ($d['guests'] as $g) {
                    if (!empty($g['claimed'])) {
                        throw new PartyException('Cannot change guests after names have been locked — reset claims first', 409);
                    }
                }
                $parse = function (array $gi): array {
                    $display = trim((string) ($gi['display'] ?? ''));
                    if ($display === '') {
                        throw new PartyException('Guest name is required', 400);
                    }
                    $aliases = [];
                    foreach ((array) ($gi['aliases'] ?? []) as $a) {
                        $a = trim((string) $a);
                        if ($a !== '') {
                            $aliases[] = $a;
                        }
                    }
                    $pin = trim((string) ($gi['pin'] ?? ''));
                    if ($pin !== '' && !preg_match('/^\d{4}$/', $pin)) {
                        throw new PartyException('pin_must_be_4_digits', 400);
                    }
                    return ['display' => $display, 'nickname' => trim((string) ($gi['nickname'] ?? '')),
                        'aliases' => array_values(array_unique($aliases)), 'pin' => $pin];
                };
                if ($action === 'host_guest_remove') {
                    $id = (string) ($input['id'] ?? $input['guest_id'] ?? '');
                    $d['guests'] = array_values(array_filter($d['guests'], fn ($g) => $g['id'] !== $id));
                    unset($d['wishlists'][$id]);
                } elseif ($action === 'host_guest_add') {
                    $parsed = $parse($input);
                    $key = S::norm($parsed['display']);
                    foreach ($d['guests'] as $g) {
                        if (S::norm($g['display']) === $key) {
                            throw new PartyException('Duplicate guest name: '.$parsed['display'], 400);
                        }
                    }
                    $d['guests'][] = ['id' => 'g'.bin2hex(random_bytes(6)), 'display' => $parsed['display'],
                        'nickname' => $parsed['nickname'], 'aliases' => $parsed['aliases'],
                        'pin_hash' => ($parsed['pin'] !== '') ? password_hash($parsed['pin'], PASSWORD_DEFAULT) : null,
                        'recipient_id' => null, 'claimed' => false, 'claimed_at' => null];
                } else {
                    $id = (string) ($input['id'] ?? $input['guest_id'] ?? '');
                    $parsed = $parse($input);
                    $key = S::norm($parsed['display']);
                    $found = false;
                    foreach ($d['guests'] as &$g) {
                        if ($g['id'] === $id) {
                            $found = true;
                            $g['display'] = $parsed['display'];
                            $g['nickname'] = $parsed['nickname'];
                            $g['aliases'] = $parsed['aliases'];
                            if ($parsed['pin'] !== '') {
                                $g['pin_hash'] = password_hash($parsed['pin'], PASSWORD_DEFAULT);
                            }
                            // Blank PIN on edit keeps the existing PIN (a guest
                            // PIN can only be set/changed, never silently wiped
                            // by an edit that leaves the field blank).
                        } elseif (S::norm($g['display']) === $key) {
                            throw new PartyException('Duplicate guest name: '.$parsed['display'], 400);
                        }
                    }
                    unset($g);
                    if (!$found) {
                        throw new PartyException('Guest not found', 404);
                    }
                }
                // Redraw after every guest change (recipients never exposed).
                $ids = array_column($d['guests'], 'id');
                $recipients = S::derangement($ids) ?? array_fill(0, count($ids), null);
                foreach ($d['guests'] as $i => &$g) {
                    $g['recipient_id'] = $recipients[$i];
                }
                unset($g);
                if (!S::save($d)) {
                    throw new PartyException('Could not save', 500);
                }
                $out = [];
                foreach (S::sortedGuests($d) as $g2) {
                    $out[] = ['id' => $g2['id'], 'display' => $g2['display'], 'nickname' => $g2['nickname'],
                        'aliases' => $g2['aliases'], 'claimed' => !empty($g2['claimed']),
                        'claimed_at' => $g2['claimed_at'] ?? null, 'pin_set' => !empty($g2['pin_hash'])];
                }
                return ['ok' => true, 'guests' => $out];
            }

            case 'host_unclaim_all':
                $d = $need();
                S::requireHost($input, $d, $action);
                foreach ($d['guests'] as &$g) {
                    $g['claimed'] = false;
                    $g['claimed_at'] = null;
                }
                unset($g);
                S::save($d);
                $out = [];
                foreach (S::sortedGuests($d) as $g2) {
                    $out[] = ['id' => $g2['id'], 'display' => $g2['display'], 'nickname' => $g2['nickname'],
                        'aliases' => $g2['aliases'], 'claimed' => !empty($g2['claimed']),
                        'claimed_at' => $g2['claimed_at'] ?? null];
                }
                return ['ok' => true, 'guests' => $out];

            case 'host_redraw':
                $d = $need();
                S::requireHost($input, $d, $action);
                foreach ($d['guests'] as $g) {
                    if (!empty($g['claimed'])) {
                        throw new PartyException('Cannot redraw after names have been revealed — reset claims first', 409);
                    }
                }
                $ids = array_column($d['guests'], 'id');
                $recipients = S::derangement($ids) ?? array_fill(0, count($ids), null);
                foreach ($d['guests'] as $i => &$g) {
                    $g['recipient_id'] = $recipients[$i];
                }
                unset($g);
                S::save($d);
                return ['ok' => true];

            case 'host_clear_wishlists':
                $d = $need();
                S::requireHost($input, $d, $action);
                $d['wishlists'] = [];
                S::save($d);
                return ['ok' => true];

            case 'wishlist_list':
                $d = $need();
                $me = (string) ($request->query('me', $input['me'] ?? ''));
                $board = [];
                foreach (S::sortedGuests($d) as $g) {
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
                return ['board' => $board];

            case 'wishlist_add':
                $d = $need();
                $g = S::findGuest($d, (string) ($input['guest_id'] ?? ''));
                if (!$g) {
                    throw new PartyException('Guest not found', 404);
                }
                S::verifyGuestPin($g, $input);
                $text = trim((string) ($input['text'] ?? ''));
                $link = trim((string) ($input['link'] ?? ''));
                if ($link !== '' && !preg_match('#^https?://#i', $link)) {
                    $link = 'https://'.$link;
                }
                $kind = in_array(($input['kind'] ?? ''), ['text', 'link', 'image'], true) ? $input['kind'] : ($link !== '' ? 'link' : 'text');
                $image = trim((string) ($input['image'] ?? ''));
                if ($image !== '') {
                    if (!preg_match('#^data:image/(png|jpeg|gif|webp|avif);base64,[A-Za-z0-9+/=]+$#', $image)) {
                        throw new PartyException('Screenshot must be a PNG, JPEG, GIF, WebP or AVIF image', 400);
                    }
                    if (strlen($image) > 14000000) {
                        throw new PartyException('Screenshot is too large — keep it under 10 MB', 400);
                    }
                    $image = S::convertToAvif($image);
                }
                if ($text === '' && $link === '' && $image === '') {
                    throw new PartyException('Wishlist item is empty', 400);
                }
                $item = ['id' => 'w'.bin2hex(random_bytes(6)), 'text' => $text, 'link' => $link,
                    'notes' => trim((string) ($input['notes'] ?? '')), 'kind' => $kind, 'image' => $image,
                    'reserved_by' => null, 'created_at' => date('c')];
                $d['wishlists'][$g['id']][] = $item;
                if (!S::save($d)) {
                    throw new PartyException('Could not save', 500);
                }
                return ['ok' => true, 'item' => $item];

            case 'wishlist_delete':
                $d = $need();
                $g = S::findGuest($d, (string) ($input['guest_id'] ?? ''));
                if (!$g) {
                    throw new PartyException('Guest not found', 404);
                }
                S::verifyGuestPin($g, $input);
                $itemId = (string) ($input['item_id'] ?? '');
                $list = $d['wishlists'][$g['id']] ?? [];
                $d['wishlists'][$g['id']] = array_values(array_filter($list, fn ($it) => $it['id'] !== $itemId));
                S::save($d);
                return ['ok' => true];

            case 'wishlist_reserve':
                $meId = (string) ($input['guest_id'] ?? '');
                $ownerId = (string) ($input['owner_id'] ?? '');
                $itemId = (string) ($input['item_id'] ?? '');
                return S::mutate(function (&$d) use ($meId, $ownerId, $itemId, $input) {
                    $me = S::findGuest($d, $meId);
                    if (!$me) {
                        throw new PartyException('Guest not found', 404);
                    }
                    S::verifyGuestPin($me, $input);
                    if ($ownerId === $me['id']) {
                        throw new PartyException('You cannot reserve your own wishlist item', 400);
                    }
                    if (!isset($d['wishlists'][$ownerId])) {
                        throw new PartyException('Item not found', 404);
                    }
                    foreach ($d['wishlists'][$ownerId] as $idx => $it) {
                        if ($it['id'] !== $itemId) {
                            continue;
                        }
                        if (!empty($it['reserved_by']) && $it['reserved_by'] !== $me['id']) {
                            throw new PartyException('already_reserved', 409);
                        }
                        $new = (!empty($it['reserved_by'])) ? null : $me['id'];
                        $d['wishlists'][$ownerId][$idx]['reserved_by'] = $new;
                        return ['ok' => true, 'reserved' => !empty($new)];
                    }
                    throw new PartyException('Item not found', 404);
                });

            case 'poll_get':
                $d = $need();
                $list = S::pollsPublic($d, (string) $request->query('me', ''));
                return ['polls' => $list, 'poll' => $list[0] ?? null];

            case 'poll_setup':
                $d = $need();
                S::requireHost($input, $d, $action);
                $q = trim((string) ($input['question'] ?? 'What should our Christmas theme be?'));
                $optsIn = [];
                foreach ((array) ($input['options'] ?? []) as $o) {
                    $o = trim((string) $o);
                    if ($o !== '') {
                        $optsIn[] = $o;
                    }
                }
                if ($q === '' || count($optsIn) < 2) {
                    throw new PartyException('Poll needs a question and at least 2 options', 400);
                }
                $opts = [];
                foreach ($optsIn as $o) {
                    $opts[] = ['id' => 'p'.bin2hex(random_bytes(5)), 'text' => $o];
                }
                $polls = S::pollsList($d);
                $new = ['id' => 'pl'.bin2hex(random_bytes(5)), 'question' => $q, 'options' => $opts, 'votes' => [], 'open' => true, 'created_at' => date('c')];
                $polls[] = $new;
                $d['polls'] = $polls;
                unset($d['poll']);
                S::save($d);
                return ['ok' => true, 'polls' => S::pollsPublic($d, ''), 'poll' => S::pollPublicOne($new, '')];

            case 'poll_vote':
                $gid = (string) ($input['guest_id'] ?? '');
                $opt = (string) ($input['option_id'] ?? '');
                return S::mutate(function (&$d) use ($gid, $opt, $input) {
                    [$i, $polls] = S::pollResolve($d, $input);
                    $p = $polls[$i];
                    if (empty($p['open'])) {
                        throw new PartyException('poll_closed', 409);
                    }
                    $g = S::findGuest($d, $gid);
                    if (!$g) {
                        throw new PartyException('Guest not found', 404);
                    }
                    S::verifyGuestPin($g, $input);
                    $ok = false;
                    foreach ($p['options'] as $o) {
                        if ($o['id'] === $opt) {
                            $ok = true;
                        }
                    }
                    if (!$ok) {
                        throw new PartyException('Option not found', 404);
                    }
                    $polls[$i]['votes'][$g['id']] = $opt;
                    $d['polls'] = $polls;
                    unset($d['poll']);
                    return ['ok' => true, 'polls' => S::pollsPublic($d, $g['id']), 'poll' => S::pollPublicOne($polls[$i], $g['id'])];
                });

            case 'poll_set_open':
                $d = $need();
                S::requireHost($input, $d, $action);
                [$i, $polls] = S::pollResolve($d, $input);
                $polls[$i]['open'] = !empty($input['open']);
                $d['polls'] = $polls;
                unset($d['poll']);
                S::save($d);
                return ['ok' => true, 'polls' => S::pollsPublic($d, ''), 'poll' => S::pollPublicOne($polls[$i], '')];

            case 'poll_edit':
                $d = $need();
                S::requireHost($input, $d, $action);
                [$i, $polls] = S::pollResolve($d, $input);
                $q = trim((string) ($input['question'] ?? ''));
                $optsIn = [];
                foreach ((array) ($input['options'] ?? []) as $o) {
                    $o = trim((string) $o);
                    if ($o !== '') {
                        $optsIn[] = $o;
                    }
                }
                if ($q === '' || count($optsIn) < 2) {
                    throw new PartyException('Poll needs a question and at least 2 options', 400);
                }
                $old = $polls[$i]['options'];
                $opts = [];
                foreach ($optsIn as $j => $o) {
                    $opts[] = ['id' => $old[$j]['id'] ?? ('p'.bin2hex(random_bytes(5))), 'text' => $o];
                }
                $polls[$i]['question'] = $q;
                $polls[$i]['options'] = $opts;
                $d['polls'] = $polls;
                unset($d['poll']);
                S::save($d);
                return ['ok' => true, 'polls' => S::pollsPublic($d, ''), 'poll' => S::pollPublicOne($polls[$i], '')];

            case 'poll_reset':
                $d = $need();
                S::requireHost($input, $d, $action);
                $polls = S::pollsList($d);
                $id = (string) ($input['poll_id'] ?? '');
                if ($id !== '') {
                    $i = S::pollIndex($polls, $id);
                    if ($i < 0) {
                        throw new PartyException('Poll not found', 404);
                    }
                    array_splice($polls, $i, 1);
                } elseif (count($polls) === 1) {
                    $polls = [];
                } elseif (count($polls) === 0) {
                    throw new PartyException('No poll yet', 404);
                } else {
                    throw new PartyException('poll_id required', 400);
                }
                $d['polls'] = $polls;
                unset($d['poll']);
                S::save($d);
                return ['ok' => true, 'polls' => S::pollsPublic($d, '')];

            default:
                throw new PartyException('Unknown action', 400);
        }
    }

}
