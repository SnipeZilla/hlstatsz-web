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

	// Awards Info Page

	$db->query("SELECT name FROM hlstats_Games WHERE code='$game'");
	if ($db->num_rows() < 1) error("No such game '$game'.");

	list($gamename) = $db->fetch_row();
	$db->free_result();

	$type = valid_request($_GET['type'] ?? '');
	$tab = valid_request($_GET['tab'] ?? '');

	if (is_ajax())
	{
		$tabs = explode('_', preg_replace('[^a-z]', '', $tab));
		
		foreach ( $tabs as $tab )
		{
			if ( file_exists(PAGE_PATH . '/awards_' . $tab . '.php') )
			{
				@include(PAGE_PATH . '/awards_' . $tab . '.php');
			}
		}
		exit;
	}

?>

<div class="hlstats-tabs-bar">
<ul class="hlstats-tabs" id="tabs_playerinfo">
    <li class="active">
        <a href="#daily" class="tab" data-url="daily" data-target="tab1">Daily Awards</a>
    </li>
    <li>
        <a href="#global" class="tab" data-url="global" data-target="tab2">Global Awards</a>
    </li>
    <li>
        <a href="#ranks" class="tab" data-url="ranks" data-target="tab3">Ranks</a>
    </li>
    <li>
        <a href="#Ribbons" class="tab" data-url="Ribbons" data-target="tab4">Ribbons</a>
    </li>
</ul>
</div>

<!-- Tab content containers -->
<div id="tab1" class="hlstats-tab-content"></div>
<div id="tab2" class="hlstats-tab-content"></div>
<div id="tab3" class="hlstats-tab-content"></div>
<div id="tab4" class="hlstats-tab-content"></div>

<script>
Tabs.init({
    baseParams: {
        mode: "awards",
        game: "<?= $game ?>"
    }
});
</script>

