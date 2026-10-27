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

if (!defined('LIVE_INTERVAL')) define('LIVE_INTERVAL', 30);   // seconds between asks, and life of a saved page
define('LIVE_MAP_MARK', "\n<!--live:map-->\n");                // between the page body and the map data in a file

// One file per page and language: needs neither the session nor the database
function liveCacheFile(): string
{
    $params = $_GET;
    unset($params['live']);
    ksort($params);
    $lang = isset($_COOKIE['lang']) ? preg_replace('/[^a-z]/', '', (string) $_COOKIE['lang']) : '';
    if (!is_dir('./cache')) @mkdir('./cache', 0755, true);
    return './cache/hlstatsz_live_' . substr(md5(http_build_query($params) . '|' . $lang), 0, 16) . '.html';
}

// The language the page is rendered in, as loadLangStrings() picks it
function liveLang(): string
{
    global $g_options;
    $langs = array_values(array_filter(array_map('trim', explode(';', $g_options['Language'] ?? '')), fn($l) => preg_match('/^[a-z]{2}$/', $l)));
    $cookie = isset($_COOKIE['lang']) ? preg_replace('/[^a-z]/', '', $_COOKIE['lang']) : '';
    return in_array($cookie, $langs) ? $cookie : ($langs[0] ?? 'us');
}

// Changes whenever anything shown on the page changes; also every 5 minutes, for the "played" timers
function liveVersion(): string
{
    global $db, $g_options;
    static $version = null;
    if ($version !== null) return $version;

    $result = $db->query("
        SELECT
            (SELECT CONCAT_WS(',', COUNT(*), MAX(last_event), SUM(act_players), SUM(max_players), SUM(kills), SUM(map_started))
             FROM hlstats_Servers),
            (SELECT CONCAT_WS(',', COUNT(*), SUM(kills), SUM(deaths), SUM(skill), SUM(ping), SUM(connected))
             FROM hlstats_Livestats)
    ");
    list($servers, $live) = $db->fetch_row($result);

    $params = $_GET;
    unset($params['live']);
    ksort($params);

    return $version = substr(md5(implode('|', [
        $servers, $live, intdiv(time(), 300),
        http_build_query($params), liveLang(), $g_options['webversion'] ?? '',
    ])), 0, 16);
}

// The saved page: ['version', 'body', 'map', 'age'], or null
function liveCacheLoad(string $file): ?array
{
    $raw = @file_get_contents($file);
    if ($raw === false || !preg_match('/^<!--live:([0-9a-f]{16})-->\n/', $raw, $m)) return null;
    $parts = explode(LIVE_MAP_MARK, substr($raw, strlen($m[0])), 2);
    clearstatcache(true, $file);
    return ['version' => $m[1], 'body' => $parts[0], 'map' => $parts[1] ?? '', 'age' => time() - (int) @filemtime($file)];
}

// The saved page if it is LIVE_INTERVAL seconds old or less: no database
function liveFresh(string $file): ?array
{
    $entry = liveCacheLoad($file);
    return $entry && $entry['age'] <= LIVE_INTERVAL ? $entry : null;
}

// The saved page if it still shows the current data (older ones are checked with liveVersion() and renewed), or null
function liveCurrent(string $file): ?array
{
    $entry = liveCacheLoad($file);
    if (!$entry) return null;
    if ($entry['age'] <= LIVE_INTERVAL) return $entry;
    if ($entry['version'] !== liveVersion()) return null;
    @touch($file);
    $entry['age'] = 0;
    return $entry;
}

function liveCacheSave(string $file, string $version, string $body, string $map): bool
{
    $saved = @file_put_contents($file, "<!--live:$version-->\n" . $body . LIVE_MAP_MARK . $map, LOCK_EX) !== false;
    // now and then, drop pages nobody asked for in a while
    if (mt_rand(1, 20) === 1) {
        foreach (glob('./cache/hlstatsz_live_*.html') ?: [] as $old) {
            if (@filemtime($old) < time() - 600) @unlink($old);
        }
    }
    return $saved;
}

// Answer a live ask: 204 when the browser already shows this version, else the page body and the map data
function liveSend(array $entry, string $how): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Live-Version: ' . $entry['version']);
    header('X-Live-Cache: ' . $how);   // hit (no database), renewed (one query), saved, or failed (cache not writable)
    if (($_SERVER['HTTP_X_LIVE_VERSION'] ?? '') === $entry['version']) {
        http_response_code(204);
    } else {
        echo $entry['body'], $entry['map'];
    }
    exit;
}

// A visit or a refresh of a page: its saved body, when it still shows the current data. True when shown.
function liveShowSaved(): bool
{
    global $liveEntry;
    if (defined('HLSTATS_LIVE') || is_ajax()) return false;
    $liveEntry = liveCurrent(liveCacheFile());
    if (!$liveEntry) return false;
    echo $liveEntry['body'];
    liveStart($liveEntry['version']);
    return true;
}

// Starts the refresh in the browser (Live in hlstatsz.js); not in a page rendered for the refresh itself
function liveStart(?string $version = null): void
{
    if (defined('HLSTATS_LIVE')) return;
    echo '<script>Live.start(' . json_encode($version ?? liveVersion()) . ', ' . (LIVE_INTERVAL * 1000) . ');</script>';
}
