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

if (isset($_GET['token']) && isset($_SESSION['map'])) {
  $map      = $_SESSION['map'];
  $payload  = array('map' => $map, 'session' => $map);
  $expected = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), SECRET_KEY);
  if (hash_equals($expected, $_GET['token'])) {
    $map_dlurl = str_replace("%GAME%", $game, $g_options['map_dlurl']);
    if (substr($map_dlurl, -1) !== '/') { $map_dlurl .= '/'; }
    $map_dlurl .= $map;
    header("Content-Type: application/octet-stream");
    header("Location: $map_dlurl");
    exit;
  } else {
    include (PAGE_PATH . '/header.php');
    error(t('error.no.uniqueid'));
  }
} else {
    include (PAGE_PATH . '/header.php');
    error(t('error.no.uniqueid'));
}
