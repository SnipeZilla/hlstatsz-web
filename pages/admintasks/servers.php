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
	
    function delete_server($server)
    {
    	global $db;
		$db->query("DELETE FROM `hlstats_Servers_Config` WHERE `serverId` = '" . $db->escape($server) . "';");
		$db->query("DELETE FROM `hlstats_server_load` WHERE `server_id`  = '" . $db->escape($server) . "'");
    }
	//list($realgame,$realname) = getRealGame($gamecode);
	$edlist = new EditList("serverId", "hlstats_Servers", "server",true,true,"serversettings", 'delete_server');
	$edlist->columns[] = new EditListColumn("address", "IP Address", 15, true, "ipaddress", "", 15);
	$edlist->columns[] = new EditListColumn("port", "Port", 5, true, "text", "27015", 5);
	$edlist->columns[] = new EditListColumn("name", "Server Name", 35, true, "text", "", 255);
	$edlist->columns[] = new EditListColumn("rcon_password", "Rcon Password", 10, false, "password", "", 128);
	$edlist->columns[] = new EditListColumn("publicaddress", "Public Address", 20, false, "text", "", 128);
	//$edlist->columns[] = new EditListColumn("game", "Game", 20, true, "select", "hlstats_Games.name/code/realgame='".$realname."'");
	$edlist->columns[] = new EditListColumn("sortorder", "Sort Order", 2, true, "text", "", 255);
	
	if ($_POST)
	{
		if ($edlist->update())
			message("success", "Operation successful.");
		else
			message("warning", $edlist->error());
	}

	$result = $db->query("
		SELECT
			serverId,
			address,
			port,
			name,
			sortorder,
			publicaddress,
			game,
			IF(rcon_password='','','(encrypted)') AS rcon_password
		FROM
			hlstats_Servers
		WHERE
			game='$gamecode'
		ORDER BY
			address ASC,
			port ASC
	");
	
	$edlist->draw($result, false);

?>
<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit">
</div>

