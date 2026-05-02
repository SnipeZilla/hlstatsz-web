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
?>

<div class="panel">
<?php
    if (isset($_POST['confirm'])) {
		

		$deleteDays = (isset($_POST['delete_days']) && (int)$_POST['delete_days'] > 0) ? (int)$_POST['delete_days'] : 0;

		echo "<ul>\n";

      $dbt = "Deleting all inactive Players";
			echo "<li>$dbt ... ";
			$minTimestamp = date("U")-(86400*$deleteDays);
			$SQL = "DELETE FROM hlstats_Players WHERE last_event<$minTimestamp;";
			if ($db->query($SQL)) echo "OK\n"; else echo "ERROR\n"; 

      $dbt = "Deleting Clans without Players";
			echo "<li>$dbt ... ";
			$SQL = "DELETE FROM hlstats_Clans USING hlstats_Clans LEFT JOIN hlstats_Players ON (clan=clanId) WHERE isnull(clan);";
			if ($db->query($SQL)) echo "OK\n"; else echo "ERROR\n"; 
    
      $dbt = "Deleting Names from inactive Players";
			echo "<li>$dbt ... ";
			$SQL = "DELETE FROM hlstats_PlayerNames USING hlstats_PlayerNames LEFT JOIN hlstats_Players ON (hlstats_PlayerNames.playerId=hlstats_Players.playerId) WHERE isnull(hlstats_Players.playerId);";
			if ($db->query($SQL)) echo "OK\n"; else echo "ERROR\n"; 

      $dbt = "Deleting SteamIDs from inactive Players";
			echo "<li>$dbt ... ";
			$SQL = "DELETE FROM hlstats_PlayerUniqueIds USING hlstats_PlayerUniqueIds LEFT JOIN hlstats_Players ON (hlstats_PlayerUniqueIds.playerId=hlstats_Players.playerId) WHERE isnull(hlstats_Players.playerId);";
			if ($db->query($SQL)) echo "OK\n"; else echo "ERROR\n"; 

      $dbt = "Deleting Awards from inactive Players";
			echo "<li>$dbt ... ";
			$SQL = "DELETE FROM hlstats_Players_Awards USING hlstats_Players_Awards LEFT JOIN hlstats_Players ON (hlstats_Players_Awards.playerId=hlstats_Players.playerId) WHERE isnull(hlstats_Players.playerId);";
			if ($db->query($SQL)) echo "OK\n"; else echo "ERROR\n"; 
	  
	  $dbt = "Deleting Ribbons from inactive Players";
			echo "<li>$dbt ... ";
			$SQL = "DELETE FROM hlstats_Players_Ribbons USING hlstats_Players_Ribbons LEFT JOIN hlstats_Players ON (hlstats_Players_Ribbons.playerId=hlstats_Players.playerId) WHERE isnull(hlstats_Players.playerId);";
			if ($db->query($SQL)) echo "OK\n"; else echo "ERROR\n";

      $dbt = "Deleting History from inactive Players";
			echo "<li>$dbt ... ";
			$SQL = "DELETE FROM hlstats_Players_History USING hlstats_Players_History LEFT JOIN hlstats_Players ON (hlstats_Players_History.playerId=hlstats_Players.playerId) WHERE isnull(hlstats_Players.playerId);";
			if ($db->query($SQL)) echo "OK\n"; else echo "ERROR\n"; 

		echo "</ul>\n";

		message('success','Job done!');
        echo "&larr;&nbsp;<a href=\"?mode=admin\">Return to Admin</a>";

	}
	else
	{
?>

<form method="POST">

<p>
<?php
message('warning','Are you sure you want to clean up all statistics?');
?>
<div class="hlstats-admin-note">
All inactive players, clans and events will be deleted from the database. (All other admin settings will be retained)
</p>
<p>
<b>Note</b> You should kill <b>hlstats.pl</b> before resetting the stats. You can restart it after they are reset.
</p>
</div>
<p>
Only delete events older than <input type="number" style="width:64px" min="1" placeholder="all" name="delete_days"> days &nbsp;<em>(leave blank to delete all)</em>
</p>
<input type="hidden" name="confirm" value="1">
<div class="hlstats-admin-apply">
<input type="submit" value="Reset Stats">
</div>

</form>
<?php
	}
?>
</div>