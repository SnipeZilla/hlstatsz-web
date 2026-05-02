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

	require(PAGE_PATH.'/livestats.php');

	$server_id = 1;
	if (isset($_GET['server_id']) && is_numeric($_GET['server_id'])) {
		$server_id = valid_request($_GET['server_id'], true);
	}

    $query= "
			SELECT
				count(*)
			FROM
				hlstats_Players
			WHERE 
				game='$game'
	";

	$result = $db->query($query);
	list($total_players) = $db->fetch_row($result);

    $query= "
			SELECT
				SUM(kills),
				SUM(headshots),
				count(serverId)		
			FROM
				hlstats_Servers
			WHERE 
				game='$game'
	";

	$result = $db->query($query);
	list($total_kills, $total_headshots, $total_servers) = $db->fetch_row($result);

    $query= "
			SELECT
				serverId,
				name,
				IF(publicaddress != '',
					publicaddress,
					concat(address, ':', port)
				) AS addr,
				kills,
				headshots,
				act_players,
				max_players,
				act_map,
				map_started,
				map_ct_wins,
				map_ts_wins
			FROM
				hlstats_Servers
			WHERE
				serverId='".$server_id."'
			ORDER BY
				name ASC,
				addr ASC
		";
		$db->query($query);
		$servers = array();

		while ($rowdata = $db->fetch_array()) {
			$servers[] = $rowdata;
		}

		$i=0;
		for ($i=0; $i<count($servers); $i++) {
			$rowdata = $servers[$i];
			$server_id = $rowdata['serverId'];
			$c = ($i % 2) + 1;
			$addr = $rowdata["addr"];
			$kills     = $rowdata['kills'];
			$headshots = $rowdata['headshots'];
			$player_string = $rowdata['act_players']."/".$rowdata['max_players'];
			$map_teama_wins = $rowdata['map_ct_wins'];
			$map_teamb_wins = $rowdata['map_ts_wins'];
?>

	<table>
		<tr>
			<th class="left">Server</th>
			<th>Address</th>
			<th>Map</th>
			<th>Played</th>
			<th>Players</th>
			<th>Kills</th>
			<th>Headshots</th>
			<th>Hpk</td>
		</tr>
		<tr>
			<td class="left"><?php
				echo '<span class="hlstats-name">'.$rowdata['name'].'</span>';
			?></td>
			<td><?php
				echo $addr;
			?></td>
			<td><?php
				echo $rowdata['act_map'];
			?></td>
			<td><?php
				echo TimeStamp(time()-$rowdata['map_started']);
			?></td>
			<td><?php
				echo $player_string;
			?></td>
			<td><?php
				echo nf($kills);
			?></td>
			<td><?php
				echo nf($headshots);
			?></td>
			<td><?php
				if ($kills>0)
					echo sprintf('%.4f', ($headshots/$kills));
				else  
				  echo sprintf('%.4f', 0);
			?></td>
		</tr>
	</table>

<?php
	printserverstats($server_id);
global $game;
	}  // for servers
?>
<div>
<div style="float:right;">
    <a href="?mode=players&amp;game=<?=$game?>">Player Rankings&nbsp;&rarr;</a>
</div>
</div>