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
if (!defined('IN_HLSTATS')) { die('Do not access this file directly'); }

require_once(PAGE_PATH . '/teamspeak_class.php');

$tsNickname = 'WebGuest';
if (!empty($_SESSION['ID64'])) {
    $steamId2 = preg_replace('/^STEAM_\d+?:/i', '', ToSteam2($_SESSION['ID64']));
    $db->query("SELECT p.lastName FROM hlstats_Players p
                INNER JOIN hlstats_PlayerUniqueIds u ON p.playerId = u.playerId
                WHERE u.uniqueId = '" . $db->escape($steamId2) . "'
                LIMIT 1");
    if (($row = $db->fetch_row()) && !empty($row[0])) {
        $tsNickname = $row[0];
    }
}

include (PAGE_PATH . '/voicecomm_serverlist.php');

$tsId = valid_request($_GET['tsId'] ?? '', true);
if ($tsId <= 0) {
    error('Invalid Teamspeak 3 server', 1);
    return;
}

$db->query("SELECT addr, queryPort, UDPPort, password FROM hlstats_Servers_VoiceComm WHERE serverId=" . intval($tsId));
$s = $db->fetch_array();
if (!$s) {
    error('Teamspeak 3 server not found', 1);
    return;
}

$uip   = $s['addr'];
$qPort = (int) $s['queryPort'];
$vPort = (int) $s['UDPPort'];

function show($tpl, $array)
{
    $path = PAGE_PATH . "/templates/teamspeak/{$tpl}.html";
    if (!is_readable($path)) return '';
    $out = file_get_contents($path);
    foreach ($array as $k => $v) {
        $out = str_replace("[{$k}]", $v, $out);
    }
    return $out;
}

function ts3_time($seconds)
{
    $seconds = (int) $seconds;
    if ($seconds < 0) $seconds = 0;
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    if ($h > 0) return "{$h}h {$m}m {$s}s";
    if ($m > 0) return "{$m}m {$s}s";
    return "{$s}s";
}

function ts3_clean_name($name)
{
    // Strip TS3 [spacer*]/[*spacer*] markers that are used purely for layout.
    return preg_replace('/\[[^\]]*spacer[^\]]*\]/i', '', $name);
}

function ts3_client_icon($c)
{
    if (!empty($c['client_away']) && $c['client_away'] == 1)                       return '16x16_away.png';
    if (!empty($c['client_flag_talking']) && $c['client_flag_talking'] == 1)       return '16x16_player_on.png';
    if (isset($c['client_output_hardware']) && $c['client_output_hardware'] == 0)  return '16x16_hardware_output_muted.png';
    if (!empty($c['client_output_muted']) && $c['client_output_muted'] == 1)       return '16x16_output_muted.png';
    if (isset($c['client_input_hardware']) && $c['client_input_hardware'] == 0)    return '16x16_hardware_input_muted.png';
    if (!empty($c['client_input_muted']) && $c['client_input_muted'] == 1)         return '16x16_input_muted.png';
    return '16x16_player_off.png';
}

function ts3_channel_icon($ch)
{
    if (!empty($ch['channel_maxclients']) && $ch['channel_maxclients'] > -1
        && isset($ch['total_clients']) && $ch['total_clients'] >= $ch['channel_maxclients']) {
        return '16x16_channel_red.png';
    }
    if (!empty($ch['channel_maxfamilyclients']) && $ch['channel_maxfamilyclients'] > -1
        && isset($ch['total_clients_family']) && $ch['total_clients_family'] >= $ch['channel_maxfamilyclients']) {
        return '16x16_channel_red.png';
    }
    if (!empty($ch['channel_flag_password']) && $ch['channel_flag_password'] == 1) {
        return '16x16_channel_yellow.png';
    }
    return '16x16_channel_green.png';
}

function ts3_render_tree($parentId, $channels, $clients)
{
    $base = IMAGE_PATH . '/teamspeak3/';
    $out  = '';
    foreach ($channels as $ch) {
        if ((int) $ch['pid'] !== (int) $parentId) continue;

        $name = htmlspecialchars(ts3_clean_name($ch['channel_name']));
        $icon = ts3_channel_icon($ch);

        $out .= '<div class="ts3-channel" style="margin-left:18px;clear:both;">';
        $out .= '<img src="' . $base . $icon . '" alt="" style="vertical-align:middle;" /> ' . $name;

        foreach ($clients as $cl) {
            if ((int) $cl['cid'] !== (int) $ch['cid']) continue;
            $cicon = ts3_client_icon($cl);
            $out .= '<div class="ts3-client" style="margin-left:22px;">';
            $out .= '<img src="' . $base . $cicon . '" alt="" style="vertical-align:middle;" /> ';
            $out .= '<span style="font-weight:bold;">' . htmlspecialchars($cl['client_nickname']) . '</span>';
            $out .= '</div>';
        }

        $out .= ts3_render_tree($ch['cid'], $channels, $clients);
        $out .= '</div>';
    }
    return $out;
}

$ts  = new TeamSpeak3Query($uip, $qPort, $vPort, 5);
$ts3 = $ts->query(30);

if ($ts3['error']) {
    error('Could not query Teamspeak 3 server: ' . htmlspecialchars($ts3['error']), 1);
    return;
}

$info     = $ts3['serverinfo'];
$channels = $ts3['channels'];

// Drop ServerQuery clients (client_type=1) from the user list.
$clients = [];
foreach ($ts3['clients'] as $c) {
    if ((int) ($c['client_type'] ?? 0) !== 0) continue;
    $clients[] = $c;
}
usort($clients, function ($a, $b) {
    return strcasecmp($a['client_nickname'] ?? '', $b['client_nickname'] ?? '');
});

$chanById = [];
foreach ($channels as $c) {
    $chanById[(int) $c['cid']] = $c;
}

$base = IMAGE_PATH . '/teamspeak3/';

$perPage     = 20;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$totalUsers  = count($clients);
$start       = ($currentPage - 1) * $perPage;
$pageClients = array_slice($clients, $start, $perPage);

$userstats = '';
foreach ($pageClients as $cl) {
    $cid = (int) $cl['cid'];
    $channelName = isset($chanById[$cid])
        ? htmlspecialchars(ts3_clean_name($chanById[$cid]['channel_name']))
        : '-';

    $connected = isset($cl['client_lastconnected']) ? (int) $cl['client_lastconnected'] : 0;
    $loginSecs = $connected > 0 ? (time() - $connected) : 0;
    $idleSecs  = isset($cl['client_idle_time']) ? (int) ($cl['client_idle_time'] / 1000) : 0;

    $playerCell  = '<img src="' . $base . ts3_client_icon($cl) . '" alt="" style="vertical-align:middle;" /> ';
    $playerCell .= '<span style="font-weight:bold;">' . htmlspecialchars($cl['client_nickname']) . '</span>';

    $userstats .= show('userstats', [
        'player'  => $playerCell,
        'channel' => $channelName,
        'misc3'   => ts3_time($loginSecs),
        'misc4'   => ts3_time($idleSecs),
    ]);
}

if ($userstats === '') {
    $userstats = '<tr><td class="left" colspan="4">No users connected.</td></tr>';
}

$paginationHtml = $totalUsers > $perPage
    ? preg_replace('/(page=\d+)"/', '$1#ts3users"', Pagination($totalUsers, $currentPage, $perPage, 'page', false))
    : '';

$serverPort = $info['virtualserver_port'] ?? $vPort;
$infoHtml  = '';
$infoHtml .= '<tr><td class="left"><strong>Server name:</strong></td><td class="left">' . htmlspecialchars($info['virtualserver_name'] ?? '') . '</td></tr>';
$infoHtml .= '<tr><td class="left"><strong>Address:</strong></td><td class="left">'
           . '<a href="ts3server://' . htmlspecialchars($uip) . '?port=' . (int) $serverPort . (!empty($s['password']) ? '&amp;password=' . urlencode($s['password']) : '') . '&amp;nickname=' . urlencode($tsNickname) . '">'
           . htmlspecialchars($uip . ':' . $serverPort) . '</a></td></tr>';
$infoHtml .= '<tr><td class="left"><strong>Version:</strong></td><td class="left">' . htmlspecialchars($info['virtualserver_version'] ?? '') . '</td></tr>';
$infoHtml .= '<tr><td class="left"><strong>Platform:</strong></td><td class="left">' . htmlspecialchars($info['virtualserver_platform'] ?? '') . '</td></tr>';
if (!empty($info['virtualserver_welcomemessage'])) {
    $infoHtml .= '<tr><td class="left"><strong>Welcome:</strong></td><td class="left">'
               . nl2br(htmlspecialchars($info['virtualserver_welcomemessage'])) . '</td></tr>';
}

// virtualserver_clientsonline counts ServerQuery + our probe; show real users.
$realUsers = max(0, $totalUsers);

echo show('teamspeak', [
    'head'         => 'Teamspeak 3 Overview',
    't_name'       => 'Server name',
    'name'         => htmlspecialchars($info['virtualserver_name'] ?? ''),
    't_os'         => 'Platform',
    'os'           => htmlspecialchars($info['virtualserver_platform'] ?? ''),
    't_uptime'     => 'Uptime',
    'uptime'       => ts3_time($info['virtualserver_uptime'] ?? 0),
    't_channels'   => 'Channels',
    'channels'     => (int) ($info['virtualserver_channelsonline'] ?? count($channels)),
    't_user'       => 'Users',
    'user'         => $realUsers . ' / ' . (int) ($info['virtualserver_maxclients'] ?? 0),
    'users_head'   => 'User Information',
    'player'       => 'User',
    'channel'      => 'Channel',
    'logintime'    => 'Login time',
    'idletime'     => 'Idle time',
    'channel_head' => 'Channel Information',
    'uchannels'    => ts3_render_tree(0, $channels, $clients),
    'info'         => $infoHtml,
    'userstats'    => $userstats,
    'pagination'   => $paginationHtml,
]);
?>
