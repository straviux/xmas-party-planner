<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PartyStore as S;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function show(string $view = 'home'): Response
    {
        $d = S::load();
        $guests = [];
        $board = [];
        $polls = [];
        $sessionGuest = null;
        $hostSignedIn = false;
        if ($d) {
            foreach (S::sortedGuests($d) as $g) {
                $guests[] = S::publicGuest($g);
            }
            foreach (S::sortedGuests($d) as $g) {
                $items = [];
                foreach (($d['wishlists'][$g['id']] ?? []) as $it) {
                    $items[] = [
                        'id' => $it['id'], 'text' => $it['text'], 'link' => $it['link'], 'notes' => $it['notes'],
                        'kind' => ($it['kind'] ?? '') !== '' ? $it['kind'] : (!empty($it['link']) ? 'link' : 'text'),
                        'image' => $it['image'] ?? '', 'reserved' => !empty($it['reserved_by']),
                        'reserved_by_me' => false,
                    ];
                }
                $board[] = ['id' => $g['id'], 'display' => $g['display'], 'nickname' => $g['nickname'], 'items' => $items];
            }
            $polls = S::pollsPublic($d, '');
            $hostSignedIn = (bool) (session('host_user_id') && User::find(session('host_user_id')));
            $gid = session('guest_id');
            if ($gid) {
                $g = S::findGuest($d, $gid);
                if ($g) {
                    $sessionGuest = ['id' => $g['id'], 'display' => $g['display'], 'nickname' => $g['nickname'],
                        'claimed' => !empty($g['claimed']), 'needs_pin' => !empty($g['pin_hash'])];
                }
            }
        }

        return Inertia::render('Party', [
            'initialView' => $view,
            'setupDone' => (bool) $d,
            'party' => $d['party'] ?? 'Christmas Party',
            'budget' => $d['budget'] ?? '',
            'partyDate' => $d['date'] ?? '',
            'guests' => $guests,
            'wishBoard' => $board,
            'polls' => $polls,
            'sessionGuest' => $sessionGuest,
            'sessionGuestPin' => session('guest_pin', ''),
            'hostSignedIn' => $hostSignedIn,
            'hostUsername' => session('host_username', ''),
        ]);
    }
}
