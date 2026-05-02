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

	global $game;

// Search
    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';

	$sr_query = $_GET['q'] ?? '';
	$sr_type = valid_request(strval($_GET['st'] ?? ''), false) or 'player';
	$sr_game = valid_request(strval((isset($_GET['game'])) ? $_GET['game'] : $game), false);
	$search = new Search($sr_query, $sr_type, $sr_game);
	$search->drawForm(array('mode' => 'search'));

	if ($sr_query || $sr_query == '0') {
		$search->drawResults();
	}
?>
