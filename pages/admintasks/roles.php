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

	$edlist = new EditList("roleId", "hlstats_Roles", "role", false);
	$edlist->columns[] = new EditListColumn("game", "Game", 0, true, "hidden", $gamecode);
	$edlist->columns[] = new EditListColumn("code", "Role Code", 20, true, "text", "", 32);
	$edlist->columns[] = new EditListColumn("name", "Role Name", 20, true, "text", "", 64);
	$edlist->columns[] = new EditListColumn("hidden", "Hide Role", 0, false, "checkbox");
echo '<div class="panel">';
	if ($_POST)
	{
		if ($edlist->update())
			message("success", "Operation successful.");
		else
			message("warning", $edlist->error());
	}
?>
<div class="hlstats-admin-note">
<p>
You can specify descriptive names for each game's role codes.
</p>
</div>
<?php $result = $db->query("
		SELECT
			roleId,
			code,
			name,
			hidden
		FROM
			hlstats_Roles
		WHERE
			game='$gamecode'
		ORDER BY
			code ASC
	");
	
	$edlist->draw($result);
?>

<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit">
</div>
</div>
