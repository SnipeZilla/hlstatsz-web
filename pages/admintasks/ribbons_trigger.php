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

	if ($auth->userdata["acclevel"] < 80) {
        die ("Access denied!");
	}

	$edlist = new EditList("ribbonTriggerId", "hlstats_Ribbons_Trigger", "game");
	$edlist->columns[] = new EditListColumn("game", "Game", 0, true, "hidden", $gamecode);
	$edlist->columns[] = new EditListColumn("ribbonId", "Ribbon", 0, true, "select", "hlstats_Ribbons.ribbonName/ribbonId/game='$gamecode'");
	$edlist->columns[] = new EditListColumn("awardCode", "Trigger Award", 0, false, "select", "hlstats_Awards.name/code/game='$gamecode'");
	$edlist->columns[] = new EditListColumn("awardCount", "No. awards needed", 15, true, "text", "0", 64);
	$edlist->columns[] = new EditListColumn("special", "Special logic", 15, false, "text", "0", 64);

	if ($_POST)
	{
		if ($edlist->update())
			message("success", "Operation successful.");
		else
			message("warning", $edlist->error());
	}
	
?>

Always set special logic = 0 unless you know what you're doing!

<?php
	
	$result = $db->query("
		SELECT
			ribbonTriggerId,
			game,
			ribbonId,
			awardCode,
			awardCount,
			special
		FROM
			hlstats_Ribbons_Trigger
		WHERE
			game='$gamecode'
		ORDER BY
			ribbonTriggerId ASC
	");
	
	$edlist->draw($result);
?>

<table width="75%" border=0 cellspacing=0 cellpadding=0>
<tr>
	<td align="center"><input type="submit" value="  Apply  " class="submit"></td>
</tr>
</table>

