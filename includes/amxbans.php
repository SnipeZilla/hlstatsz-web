<?php
/*
HLstatsZ - Real-time player and clan rankings and statistics
Originally HLstatsX Community Edition by Nicholas Hastings (2008–20XX)
Based on ELstatsNEO by Malte Bayer, HLstatsX by Tobias Oetzel, and HLstats by Simon Garner

HLstats > HLstatsX > HLstatsX:CE > HLStatsZ
HLstatsZ continues a long lineage of open-source server stats tools for Half-Life and Source games.
This version is released under the GNU General Public License v2 or later.

For current support and updates:
   https://snipezilla.com
   https://github.com/SnipeZilla
   https://forums.alliedmods.net/forumdisplay.php?f=156
*/
if ( !defined('IN_HLSTATS') ) { die('Do not access this file directly'); }

/*
 * AMXBans, the ban system of the GoldSrc servers, as HLstatsZ acts on it: from the HLstatsZ admin (Bans > AMXBans)
 * and from the bans pages (mode=sourcebans). Its database is DB_AMXNAME on the MySQL server of HLstatsZ, its tables
 * DB_AMXPREFIX_* ("amx" when not set).
 *
 * Its servers are reached over GoldSrc RCON with the password AMXBans keeps for them (serverinfo.rcon). Bans are
 * written as its website writes them, and the AMXBans plugin applies them when a player joins: ban_type 'S' matches
 * the Steam ID, 'SI' the IP address; a ban is active while expired = 0 and its length (minutes, 0 = permanent) has
 * not run out; an unban sets ban_length = -1 and expired = 1 and is logged in _bans_edit as "Unban: <reason>".
 */

require_once INCLUDE_PATH . '/class_rcon.php';

/**
 * Full name of an AMXBans table, e.g. `amxbans`.`amx_bans`.
 */
function amxTable($name)
{
    $prefix = defined('DB_AMXPREFIX') ? DB_AMXPREFIX : 'amx';
    return '`' . str_replace('`', '', DB_AMXNAME) . '`.`' . str_replace('`', '', $prefix) . '_' . $name . '`';
}

/**
 * An AMXBans server (id, timestamp, hostname, address, gametype, rcon), or null.
 */
function amxServer($id)
{
    global $db;

    $db->query("SELECT id, timestamp, hostname, address, gametype, rcon FROM " . amxTable('serverinfo') . " WHERE id = " . (int) $id);
    return $db->fetch_array() ?: null;
}

/**
 * Runs a console command on a server over GoldSrc RCON with the password AMXBans keeps: array(true, its reply) or
 * array(false, why it did not run), as plain text.
 */
function amxRcon(array $server, $command)
{
    if ((string) $server['rcon'] === '') {
        return array(false, 'no RCON password is set.');
    }
    if (!preg_match('/^(.+):(\d+)$/', (string) $server['address'], $m)) {
        return array(false, 'its address has no port.');
    }
    $rcon   = new Rcon($m[1], (int) $m[2], (string) $server['rcon'], true);
    $output = $rcon->execute($command);
    return $output === false ? array(false, (string) $rcon->error) : array(true, $output);
}

/**
 * The players on a server: array(true, list of name, userid, authid, ip, kind (0 player, 1 bot, 2 HLTV), immune), or
 * array(false, why not). From "amx_list" of the AMXBans plugin, as its website reads it (fields split by byte 0xFC);
 * from "status" when the plugin does not know that command (immunity then unknown).
 */
function amxPlayers(array $server)
{
    list($ok, $reply) = amxRcon($server, 'amx_list');
    if (!$ok) {
        return array(false, $reply);
    }
    $players = array();
    if (stripos($reply, 'Unknown command') === false) {
        foreach (preg_split('/\R/', $reply) as $line) {
            $f = explode("\xFC", $line);
            if (count($f) >= 6 && ctype_digit(trim($f[1]))) {
                $players[] = array('name' => $f[0], 'userid' => (int) $f[1], 'authid' => trim($f[2]), 'ip' => trim($f[3]),
                    'kind' => (int) $f[4], 'immune' => trim($f[5]) === '1');
            }
        }
        return array(true, $players);
    }
    list($ok, $reply) = amxRcon($server, 'status');
    if (!$ok) {
        return array(false, $reply);
    }
    // # 1 "Name" 12 STEAM_0:1:1234 5 12:34 20 0 203.0.113.5:27005 (bots and HLTV have no address)
    foreach (preg_split('/\R/', $reply) as $line) {
        if (preg_match('/^#\s*\d+\s+"(.*)"\s+(\d+)\s+(\S+)(?:\s+\S+){0,4}?(?:\s+(\d+\.\d+\.\d+\.\d+):\d+)?\s*$/', $line, $m)) {
            $kind = strtoupper($m[3]) === 'BOT' ? 1 : (strtoupper($m[3]) === 'HLTV' ? 2 : 0);
            $players[] = array('name' => $m[1], 'userid' => (int) $m[2], 'authid' => $m[3], 'ip' => $m[4] ?? '', 'kind' => $kind, 'immune' => false);
        }
    }
    return array(true, $players);
}

/**
 * Kicks a player off a server over RCON ("kick #userid"), with a message: array(done, why not).
 */
function amxKick(array $server, $userid, $message)
{
    $message = amxClean($message, 120);
    list($ok, $reply) = amxRcon($server, 'kick #' . (int) $userid . ($message !== '' ? ' "' . $message . '"' : ''));
    $reply = trim((string) $reply);
    // What the engine prints when it does not do it
    if ($ok && preg_match("/not found|couldn.t find|can.t find|unknown command/i", $reply)) {
        return array(false, $reply);
    }
    return array($ok, $reply);
}

/**
 * A reason or a message that goes in a console command between quotes, and in a column of $max characters:
 * without quote, command separator (;) or control character.
 */
function amxClean($text, $max = 100)
{
    return mb_substr(trim(preg_replace('/[\s";\x00-\x1F\x7F]+/', ' ', (string) $text)), 0, $max);
}

/**
 * A Steam ID in any of its forms (STEAM_x:y:z, [U:1:n], 7656…, a /profiles/ link) as GoldSrc writes it, or null.
 */
function amxSteamId($input)
{
    $input = trim((string) $input);
    if (preg_match('#steamcommunity\.com/profiles/(\d{17})#i', $input, $m)) {
        $input = $m[1];
    }
    if (preg_match('/^STEAM_[0-5]:([01]):(\d{1,10})$/i', $input, $m)) {
        $account = $m[2] * 2 + $m[1];
    } elseif (preg_match('/^\[?U:1:(\d{1,10})\]?$/i', $input, $m)) {
        $account = (int) $m[1];
    } elseif (preg_match('/^7656119\d{10}$/', $input)) {
        $account = (int) $input - 76561197960265728;
    } else {
        return null;
    }
    return $account > 0 && $account <= 0xFFFFFFFF ? 'STEAM_0:' . ($account % 2) . ':' . intdiv($account, 2) : null;
}

/**
 * How a ban of a player is matched when the player joins: 'S' (Steam ID) for a real Steam ID, 'SI' (IP address)
 * for the ids of players without Steam (STEAM_ID_LAN, VALVE_ID_LAN, STEAM_ID_PENDING...), or '' when neither is known.
 */
function amxBanType($authid, $ip)
{
    if (preg_match('/^STEAM_[0-5]:[01]:\d+$/', (string) $authid)) {
        return 'S';
    }
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'SI' : '';
}

/**
 * The active ban of this Steam ID ($type 'S') or IP address ('SI'), as the plugin would find it: its bid, or 0.
 */
function amxActiveBan($type, $authid, $ip)
{
    global $db;

    $match = $type === 'S' ? "ban_type = 'S' AND player_id = '" . $db->escape((string) $authid) . "'"
        : "ban_type = 'SI' AND player_ip = '" . $db->escape((string) $ip) . "'";
    $db->query("SELECT bid FROM " . amxTable('bans') . " WHERE $match AND expired = 0 AND (ban_length = 0 OR ban_created + ban_length * 60 > UNIX_TIMESTAMP()) ORDER BY bid DESC LIMIT 1");
    return (int) ($db->fetch_row()[0] ?? 0);
}

/**
 * Writes a ban as the AMXBans website does ($ban: type 'S' or 'SI', authid, ip, name, minutes (0 = permanent), reason;
 * $server: the AMXBans server it is given on, or null; $admin: name and steamid); returns its bid.
 */
function amxAddBan(array $ban, $server, array $admin)
{
    global $db;

    $e = fn($value, $max) => "'" . $db->escape(mb_substr((string) $value, 0, $max)) . "'";
    $db->query("INSERT INTO " . amxTable('bans') . "
        (player_ip, player_id, player_nick, admin_ip, admin_id, admin_nick, ban_type, ban_reason, ban_created, ban_length, server_ip, server_name, ban_kicks, expired, imported)
        VALUES (" . $e($ban['ip'], 32) . ', ' . $e($ban['authid'], 35) . ', ' . $e(trim((string) $ban['name']) !== '' ? $ban['name'] : 'Unknown', 100) . ', '
        . $e($_SERVER['REMOTE_ADDR'] ?? '', 32) . ', ' . $e($admin['steamid'], 35) . ', ' . $e($admin['name'], 100) . ', ' . $e($ban['type'], 10) . ', '
        . $e($ban['reason'], 100) . ', UNIX_TIMESTAMP(), ' . max(0, (int) $ban['minutes']) . ', '
        . $e($server ? $server['address'] : '', 32) . ', ' . $e($server ? $server['hostname'] : 'website', 100) . ', 0, 0, 0)');
    return (int) $db->insert_id();
}

/**
 * The message a banned player is kicked with: "Banned for 1 day: <reason>", and where to appeal when the HLstatsZ
 * admin gave a page for it (Bans settings) and it fits.
 */
function amxBanMessage($minutes, $reason)
{
    global $g_options;

    $minutes = (int) $minutes;
    $units   = array(43200 => 'month', 10080 => 'week', 1440 => 'day', 60 => 'hour', 1 => 'minute');
    $length  = 'permanently';
    foreach ($units as $size => $unit) {
        if ($minutes > 0 && $minutes % $size === 0) {
            $n      = intdiv($minutes, $size);
            $length = "for $n $unit" . ($n === 1 ? '' : 's');
            break;
        }
    }
    $message = amxClean("Banned $length: $reason", 120);
    $appeal  = amxClean($g_options['appeal_url'] ?? '', 120);
    return $appeal !== '' && mb_strlen("$message. Appeal: $appeal") <= 120 ? "$message. Appeal: $appeal" : $message;
}

/**
 * Lifts a ban as the AMXBans website does, with the reason in its log of changes.
 */
function amxUnban($bid, $reason, array $admin)
{
    global $db;

    $db->query("INSERT INTO " . amxTable('bans_edit') . " (bid, edit_time, admin_nick, edit_reason) VALUES (" . (int) $bid . ", UNIX_TIMESTAMP(), '"
        . $db->escape(mb_substr((string) $admin['name'], 0, 32)) . "', '" . $db->escape(mb_substr('Unban: ' . $reason, 0, 255)) . "')");
    $db->query("UPDATE " . amxTable('bans') . " SET ban_length = -1, expired = 1 WHERE bid = " . (int) $bid);
}

/**
 * A line in the AMXBans log, as its website writes them ("AMXXAdmin config", "Kick online", "Add ban online",
 * "Add ban", "Ban edit"); marked as done from HLstatsZ.
 */
function amxLog($action, $remarks, $username)
{
    global $db;

    $db->query("INSERT INTO " . amxTable('logs') . " (timestamp, ip, username, action, remarks) VALUES (UNIX_TIMESTAMP(), '"
        . $db->escape((string) ($_SERVER['REMOTE_ADDR'] ?? '')) . "', '" . $db->escape(mb_substr((string) $username, 0, 32)) . "', '"
        . $db->escape($action) . "', '" . $db->escape(mb_substr($remarks . ' (HLstatsZ)', 0, 256)) . "')", false);
}

/**
 * The name and Steam ID a ban or unban from the website is recorded with, for the visitor signed in with Steam
 * ($id64): their AMXBans admin name when they are one, else "HLstatsZ admin".
 */
function amxWebAdmin($id64)
{
    global $db;

    $id64    = (string) $id64;
    $steamid = preg_match('/^7656119\d{10}$/', $id64) ? amxSteamId($id64) : null;
    $name    = 'HLstatsZ admin';
    if ($steamid) {
        $db->query("SELECT nickname, username FROM " . amxTable('amxadmins') . " WHERE steamid IN ('$steamid', '" . str_replace('STEAM_0:', 'STEAM_1:', $steamid) . "') ORDER BY id LIMIT 1");
        if ($row = $db->fetch_array()) {
            $name = trim((string) $row['nickname']) !== '' ? trim($row['nickname']) : trim((string) $row['username']);
        }
    }
    return array('name' => $name, 'steamid' => (string) $steamid);
}
