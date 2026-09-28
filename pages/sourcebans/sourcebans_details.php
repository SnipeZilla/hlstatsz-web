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

// Details of one ban or comm block: a panel under its list row (AJAX), or a page of its own.
// AMXBans bans (GoldSrc servers) have their own type.
$type = in_array($_GET['type'] ?? '', array('comm', 'amxban'), true) ? $_GET['type'] : 'ban';
$id   = (int) ($_GET['id'] ?? 0);

if (is_ajax()) {
	$type === 'amxban' ? sbAmxDetails($id) : sbDetails($type, $id);
	exit;
}

printSectionTitle(t($type === 'comm' ? 'sb.details.comm' : 'sb.details.ban'));
?>
<section class="hlstats-section hlstats-card sb-details-page">
<?php $type === 'amxban' ? sbAmxDetails($id, true) : sbDetails($type, $id, true); ?>
</section>
