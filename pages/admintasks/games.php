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
	
    function delete_game($game)
    {
    	global $db;
		
		$srvtables = array(
			"hlstats_Events_Admin",
			"hlstats_Events_ChangeName",
			"hlstats_Events_ChangeRole",
			"hlstats_Events_ChangeTeam",
			"hlstats_Events_Chat",
			"hlstats_Events_Connects",
			"hlstats_Events_Disconnects",
			"hlstats_Events_Entries",
			"hlstats_Events_Frags",
			"hlstats_Events_Latency",
			"hlstats_Events_PlayerActions",
			"hlstats_Events_PlayerPlayerActions",
			"hlstats_Events_Rcon",
			"hlstats_Events_Statsme",
			"hlstats_Events_Statsme2",
			"hlstats_Events_StatsmeLatency",
			"hlstats_Events_StatsmeTime",
			"hlstats_Events_Suicides",
			"hlstats_Events_TeamBonuses",
			"hlstats_Events_Teamkills",
			"hlstats_Servers_Config"
		);
		$pltables = array(
			"hlstats_PlayerNames"
		);
		$dbtables = array(
			"hlstats_Actions",
			"hlstats_Awards",
			"hlstats_Ribbons",
			"hlstats_Roles",
			"hlstats_Teams",
			"hlstats_Weapons",
			"hlstats_Ranks",
			"hlstats_Maps_Counts",
			"hlstats_Servers",
			"hlstats_Players_History",
			"hlstats_Players_Awards",
			"hlstats_Players_Ribbons",
			"hlstats_PlayerUniqueIds",
			"hlstats_Players",
			"hlstats_Clans",
			"hlstats_Trend"
		);
		
		$resultServers = $db->query("SELECT serverId FROM hlstats_Servers WHERE game = '$game'");
		if ($db->num_rows($resultServers) > 0)
		{
			$serverlist = "(";
			while ($server = $db->fetch_row($resultServers))
			{
				$serverlist .= $server[0].',';
			}
			$serverlist = preg_replace('/,$/', ')',$serverlist);
			foreach ($srvtables as $srvt)
			{
				echo "<li>$srvt ... ";
				$db->query("DELETE FROM $srvt WHERE serverId IN $serverlist");
				echo "OK</li>\n";
			}
			echo "<li>hlstats_server_load ... ";
			$db->query("DELETE FROM hlstats_server_load WHERE server_id IN $serverlist");
			echo "OK</li>\n";
		}
		
		$resultPlayers = $db->query("SELECT playerId FROM hlstats_Players WHERE game = '$game'");
		if ($db->num_rows($resultPlayers) > 0)
		{
			$playerlist = "(";
			while ($player = $db->fetch_row($resultPlayers))
			{
				$playerlist .= $player[0].',';
			}
			$playerlist = preg_replace('/,$/', ')',$playerlist);
			foreach ($pltables as $plt)
			{
				echo "<li>$plt ... ";
				$db->query("DELETE FROM $plt WHERE playerId IN $playerlist");
				echo "OK</li>\n";
			}
		}
		
		foreach ($dbtables as $dbt)
		{
			echo "<li>$dbt ... ";
			echo removeGameSettings($dbt, $game);
		}

		echo "<li>hlstats_Games ...";
		$db->query("DELETE FROM hlstats_Games WHERE code='$game'");
		echo "OK\n";
		echo "</ul><p>\n";
		echo "Done.<p>";
    }
	
	function removeGameSettings($table, $game) {
		global $db;
		$db->query("SELECT COUNT(game) AS cnt FROM $table WHERE game='$game';");
		$r = $db->fetch_array();
		if ($r['cnt'] == 0)
		{
			$ret = "No data existent for selected gametype.";
		}
		else
		{
			$ret = $r['cnt']." entries deleted!";
			$SQL = "DELETE FROM $table WHERE game='$game';";
			$db->query($SQL);
		}
		return $ret."\n";
	}
	
	$edlist = new EditList("code", "hlstats_Games", "game", false, false, "", 'delete_game');
	$edlist->columns[] = new EditListColumn("code", "Game Code", 10, true, "readonly", "", 16);
	$edlist->columns[] = new EditListColumn("name", "Display Name", 30, true, "text", "", 128);
	$edlist->columns[] = new EditListColumn("realgame", "Game", 50, true, "select", "hlstats_Games_Supported.name/code/", 128);
	$edlist->columns[] = new EditListColumn("hidden", "<center>Hide Game</center>", 0, false, "checkbox");
	
	echo  '<div class="panel">';
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
After creating a game (> Tools > Duplicate Game Settings), you will be able to configure servers, awards, etc. for that game under Game Settings.
</p>
<p>
<strong>NOTE</strong>: Be cautious of deleting a game. Deleting a game will remove all related settings, including servers, players, and events for that game (and may take a while). You will have to manually remove any images yourself. IF YOU DELETE THE LAST GAME OF A TYPE, THERE IS NO EASY WAY TO MAKE A NEW GAME OF THAT TYPE. If you want to delete and that is the case, you are probably better off deleting all servers for that game and then just hiding the game.
</p>
</div>
<?php
	
	$result = $db->query("
		SELECT
			code,
			name,
			realgame,
			hidden
		FROM
			hlstats_Games
		ORDER BY
			code ASC
	");
	
	$edlist->draw($result, false);
?>

<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit">
</div>

</div>

