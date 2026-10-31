<?php

namespace App\Services;

/**
 * JSON-file party store — a direct port of the plain-PHP api.php store.
 *
 * Files live under storage/app/ (never under public/), so they are not
 * web-accessible at all. Writes use flock() and chmod 0600, exactly as
 * the original did for data.php. No database tables are used for party
 * data; the users table is used only for the host username/password
 * login (see DatabaseSeeder).
 */
class PartyStore
{
    public const TEMP_POLL_HOST_CHECK_DISABLED = false;

    public static function partyPath(): string
    {
        return storage_path('app/party.json');
    }


    public static function load(): ?array
    {
        $path = self::partyPath();
        if (!file_exists($path)) {
            return null;
        }
        $d = json_decode((string) file_get_contents($path), true);
        return is_array($d) ? $d : null;
    }

    public static function save(array $d): bool
    {
        $fp = fopen(self::partyPath(), 'c');
        if (!$fp) {
            return false;
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }
        ftruncate($fp, 0);
        fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        @chmod(self::partyPath(), 0600);
        return true;
    }

    /**
     * Read-modify-write under one flock, for race-sensitive actions
     * (entry lock, reserve, vote). Stronger than the original, which
     * locked only during the write; externally the behaviour is the
     * same: the first writer locks, the second sees already_claimed.
     */
    public static function mutate(callable $fn)
    {
        $fp = fopen(self::partyPath(), 'c+');
        if (!$fp) {
            throw new PartyException('Could not save', 500);
        }
        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $d = $raw ? json_decode($raw, true) : null;
        if (!is_array($d)) {
            flock($fp, LOCK_UN);
            fclose($fp);
            throw new PartyException('not_set_up', 404);
        }
        $result = $fn($d);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        @chmod(self::partyPath(), 0600);
        return $result;
    }

    public static function deleteParty(): void
    {
        @unlink(self::partyPath());
    }

    public static function norm(string $s): string
    {
        $s = trim($s);
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false) {
            $s = $t;
        }
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    public static function inviteCodeNew(): string
    {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $c = '';
        for ($i = 0; $i < 6; $i++) {
            $c .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $c;
    }

    public static function publicGuest(array $g): array
    {
        return [
            'id' => $g['id'],
            'display' => $g['display'],
            'nickname' => $g['nickname'],
            'aliases' => $g['aliases'],
            'claimed' => !empty($g['claimed']),
            'needs_pin' => !empty($g['pin_hash']),
        ];
    }

    public static function derangement(array $ids): ?array
    {
        $n = count($ids);
        if ($n < 2) {
            return null;
        }
        for ($try = 0; $try < 500; $try++) {
            $r = $ids;
            shuffle($r);
            $ok = true;
            for ($i = 0; $i < $n; $i++) {
                if ($r[$i] === $ids[$i]) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return $r;
            }
        }
        $r = $ids;
        $first = array_shift($r);
        $r[] = $first;
        return $r;
    }

    public static function sortedGuests(array $d): array
    {
        $gs = $d['guests'];
        usort($gs, fn ($a, $b) => strcasecmp($a['display'], $b['display']));
        return $gs;
    }

    public static function findGuest(array $d, string $id): ?array
    {
        foreach ($d['guests'] as $g) {
            if ($g['id'] === $id) {
                return $g;
            }
        }
        return null;
    }

    public static function verifyGuestPin(array $g, array $input): void
    {
        if (!empty($g['pin_hash'])) {
            $pin = (string) ($input['pin'] ?? '');
            if (!password_verify($pin, $g['pin_hash'])) {
                throw new PartyException('pin_incorrect', 403);
            }
        }
    }

    /**
     * Host auth is session-based username/password (users table).
     * $input/$d/$action are kept only so existing call sites need no
     * signature change; they are not used for authentication.
     */
    public static function requireHost(array $input, ?array $d, string $action = ''): void
    {
        $id = session('host_user_id');
        if ($id && \App\Models\User::find($id)) {
            return;
        }
        throw new PartyException('host_not_signed_in', 403);
    }

    public static function hostUser(): ?\App\Models\User
    {
        $id = session('host_user_id');
        return $id ? \App\Models\User::find($id) : null;
    }

    public static function pollsList(array $d): array
    {
        if (!empty($d['polls']) && is_array($d['polls'])) {
            return $d['polls'];
        }
        if (!empty($d['poll'])) {
            $p = $d['poll'];
            if (empty($p['id'])) {
                $p['id'] = 'poll1';
            }
            return [$p];
        }
        return [];
    }

    public static function pollIndex(array $polls, string $id): int
    {
        foreach ($polls as $i => $p) {
            if (($p['id'] ?? '') === $id) {
                return $i;
            }
        }
        return -1;
    }

    public static function pollPublicOne(array $p, string $me): array
    {
        $counts = [];
        foreach ($p['options'] as $o) {
            $counts[$o['id']] = 0;
        }
        foreach (($p['votes'] ?? []) as $opt) {
            if (isset($counts[$opt])) {
                $counts[$opt]++;
            }
        }
        $opts = [];
        foreach ($p['options'] as $o) {
            $opts[] = ['id' => $o['id'], 'text' => $o['text'], 'votes' => $counts[$o['id']]];
        }
        return [
            'id' => $p['id'] ?? '',
            'question' => $p['question'],
            'options' => $opts,
            'total' => array_sum($counts),
            'open' => !empty($p['open']),
            'my_vote' => ($me !== '' ? ($p['votes'][$me] ?? null) : null),
        ];
    }

    public static function pollsPublic(array $d, string $me): array
    {
        $out = [];
        foreach (self::pollsList($d) as $p) {
            $out[] = self::pollPublicOne($p, $me);
        }
        return $out;
    }

    public static function pollResolve(array $d, array $input): array
    {
        $polls = self::pollsList($d);
        $id = (string) ($input['poll_id'] ?? '');
        if ($id === '') {
            if (count($polls) === 1) {
                return [0, $polls];
            }
            if (count($polls) === 0) {
                throw new PartyException('No poll yet', 404);
            }
            throw new PartyException('poll_id required', 400);
        }
        $i = self::pollIndex($polls, $id);
        if ($i < 0) {
            throw new PartyException('Poll not found', 404);
        }
        return [$i, $polls];
    }

    public static function convertToAvif(string $image): string
    {
        if ($image === '' || !function_exists('imageavif') || !function_exists('imagecreatefromstring')) {
            return $image;
        }
        $raw = base64_decode((string) preg_replace('#^data:image/[^;]+;base64,#', '', $image));
        $img = $raw !== false ? @imagecreatefromstring($raw) : false;
        if ($img === false) {
            return $image;
        }
        if (function_exists('imageistruecolor') && !imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        if (function_exists('imagesavealpha')) {
            imagesavealpha($img, true);
        }
        ob_start();
        $ok = imageavif($img, null, 80);
        $data = ob_get_clean();
        imagedestroy($img);
        if ($ok && $data !== '') {
            return 'data:image/avif;base64,'.base64_encode($data);
        }
        return $image;
    }
}
