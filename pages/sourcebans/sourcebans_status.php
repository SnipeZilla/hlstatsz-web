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

// Host name, map and players of a server HLstatsZ does not track, for the servers table: a SourceBans one (sid=)
// or an AMXBans one (amx=), see sbRconStatus. Open to visitors: it tells no more than a server browser does, and
// the answers are kept a few seconds.
// Opened as a page, it shows the servers.
if (!is_ajax()) {
	include __DIR__ . '/sourcebans_servers.php';
	return;
}
$info = isset($_GET['amx']) ? sbAmxServerStatus((int) $_GET['amx'], true) : sbServerStatus((int) ($_GET['sid'] ?? 0), true);
sbReply($info ? array('ok' => true) + $info : array('ok' => false));
