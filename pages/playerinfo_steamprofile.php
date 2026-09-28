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

$name = $playerdata['lastName'];
$status = 'Unknown';
$avatarFull = IMAGE_PATH . '/unknown.jpg';
$memberSince = 'Private';
$vacBanned = false;
$profileUrl = 'https://steamcommunity.com/profiles/' . $coid;
$xml = '';

if ($coid !== '76561197960265728' && !preg_match('/^BOT/i', (string) $uqid)) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $profileUrl . '?xml=1');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($curl, CURLOPT_TIMEOUT, 3);
    $xml = curl_exec($curl);
    $curl=null;
}

$xmlDoc = $xml ? @simplexml_load_string($xml) : null;

if ($xmlDoc) {
    $steamID  = (string) ($xmlDoc->steamID ?? '');
    if (!empty($steamID)) $name = $steamID;
    $status = (string) ($xmlDoc->onlineState ?? $status);
    $vacBanned = (string) ($xmlDoc->vacBanned ?? '') === '1';
    $avatarFull = (string) ($xmlDoc->avatarFull ?? $avatarFull);
    $memberSince = (string) ($xmlDoc->memberSince ?? $memberSince);
}
$memberSinceTs = ($memberSince !== 'Private') ? strtotime($memberSince) : false;

if ($name !== $playerdata['lastName']) {
    $playerdata['lastName'] = $name;

    $db->query("
        UPDATE hlstats_Players
        SET lastName = '" . $db->escape($name) . "'
        WHERE playerId = '" . (int) $playerdata['playerId'] . "'
    ");
}

// The header's identity part again, now with Steam's avatar, status and join date (see playerIdentity() in playerinfo.php)
playerIdentity($playerdata, (string) $uqid, (string) $coid, array(
    'avatar'       => $avatarFull,
    'status'       => $status,
    'member_since' => $memberSinceTs ? formatDate($memberSinceTs, IntlDateFormatter::LONG, IntlDateFormatter::NONE) : $memberSince,
    'vac'          => $vacBanned,
));
