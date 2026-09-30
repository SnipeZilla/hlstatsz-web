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

// A player searched from the header field of the SourceBans pages: their bans (SourceBans, and AMXBans when it is
// set up) and their comm blocks, as "type" asks (all, bans or comms). The lists are the Bans and Comm Blocks pages,
// which filter on q (sbPlayerMatch); a page or a sort of one list comes back for that list only.
$search = sbSearch();
$type   = in_array($_GET['type'] ?? '', array('bans', 'comms'), true) && sbComms() ? $_GET['type'] : 'all';   // no comm blocks: bans
$part   = is_ajax() && is_string($_GET['ajax'] ?? null) ? $_GET['ajax'] : '';

if (!is_ajax()) {
	printSectionTitle($search !== '' ? t('sb.search.title', array('{q}' => htmlspecialchars($search))) : t('sb.search.find'));
	if ($search === '') {
		echo '<p class="sb-empty">' . t('sb.search.empty') . '</p>';
		return;
	}
}
if ($type !== 'comms' && ($part === '' || $part === 'bans')) {
	include PAGE_PATH . '/sourcebans/sourcebans_bans.php';
}
if ($type !== 'bans' && sbComms() && ($part === '' || $part === 'comms')) {
	include PAGE_PATH . '/sourcebans/sourcebans_comms.php';
}
