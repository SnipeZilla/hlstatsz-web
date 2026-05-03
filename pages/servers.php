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

	require('livestats.php');
    $server_id = 1;

    if ((isset($_GET['server_id'])) && (is_numeric($_GET['server_id']))) {
        $server_id = valid_request($_GET['server_id'], true);
    } else {
        error("Invalid server ID provided.");
    }

    $query= "
            SELECT
                SUM(kills),
                SUM(headshots),
                count(serverId)     
            FROM
                hlstats_Servers
			WHERE 
				serverId='$server_id'
	";

	$result = $db->query($query);
	list($total_kills, $total_headshots) = $db->fetch_row($result);
        
	$query= "
        SELECT
            serverId,
            name,
            IF(publicaddress != '',
                publicaddress,
                concat(address, ':', port)
            ) AS addr,
            statusurl,
            kills,
            players,
            rounds, suicides, 
            headshots, 
            bombs_planted, 
            bombs_defused, 
            ct_wins, 
            ts_wins, 
            ct_shots, 
            ct_hits, 
            ts_shots, 
            ts_hits,
            act_players,
            max_players,
            act_map,
            map_started,
            map_ct_wins,
            map_ts_wins,
            game
        FROM
            hlstats_Servers
        WHERE
            serverId='$server_id'
	";

	$db->query($query);
	$servers   = array();
	$servers[] = $db->fetch_array();
        
?>


<?php
	printSectionTitle('Server Live View');
	$i=0;
	for ($i=0; $i<count($servers); $i++)
	{
		$rowdata = $servers[$i]; 
	
		$server_id = $rowdata['serverId'];
		$game = $rowdata['game'];
	
		$addr = $rowdata['addr'];          
		$kills     = $rowdata['kills'];
		$headshots = $rowdata['headshots'];
		$player_string = $rowdata['act_players']."/".$rowdata['max_players'];
		$map_teama_wins = $rowdata['map_ct_wins'];
		$map_teamb_wins = $rowdata['map_ts_wins'];
?>        <div class="hlstats-table-server hlstats-charts livestats">
		  <table class="livestats-table-server">
					<tr>
					<th class="hlstats-main-server left">Server</th>
					<th class="hide">Address</th>
						<th>Map</th>
					<th class="hide-1">Played</th>
						<th>Players</th>
					<th class="hide-1">Kills</th>
					<th class="hide-2">Headshots</th>
					<th class="hide-3">HS:K</th>
					</tr>
					<tr>
						<td class="left"><?php
			$image = getImage("/games/$game/game");
			echo '<span class="hlstats-icon"><img src="';
			if ($image) {
				echo $image['url'];
			} elseif ($image = getImage("/games/$realgame/game")) {
				echo $image['url'];
			} else {
				echo IMAGE_PATH . '/game.png';
			}
			echo "\" alt=\"$game\" /></span>";
			echo "<span class=\"hlstats-name\"><a href=\"" . $g_options['scripturl'] . "?game=$game\" style=\"text-decoration:none;\">" . htmlspecialchars($rowdata['name']) . "</a></span>";
	?></td>
						<td class="hide nowrap"><?php
			echo "<a title=\"Click To Join\" href=\"steam://connect/$addr\">$addr <a href=\"steam://connect/$addr\"></a>";
	?></td>
						<td><?php
			echo $rowdata['act_map'];
	?></td>
						<td class="hide-1 nowrap"><?php
			$stamp = $rowdata['map_started']==0?0:time() - $rowdata['map_started'];
			echo TimeStamp($stamp);
	?></td>
						<td><?php
			echo $player_string;
	?></td>
						<td class="hide-1 nowrap"><?php
			echo nf($kills);
	?></td>
						<td class="hide-2 nowrap"><?php
			echo nf($headshots);
	?></td>
						<td class="hide-3 nowrap"><?php
			if ($kills > 0)
				echo sprintf('%.2f', ($headshots / $kills));
			else
				echo sprintf('%.2f', 0);
	?></td>
					</tr>
			<tr>
			  <td class="responsive" style="padding:0px;text-align:center;" colspan="8">
<div class="responsive-table">
				<a href="<?php $g_options['scripturl'] ?>?mode=servers&amp;server_id=<?php echo $server_id ?>&amp;game=<?php echo $game ?>" style="text-decoration:none;"><img src="show_graph.php?type=0&amp;game=<?php echo $game; ?>&amp;server_id=<?=$server_id?>&amp;theme=<?=$theme?>" style="border:0px;" class="responsive" alt="Server Load Graph" title="Server Load Graph" /></a>
</div>
			  </td>
			</tr>
			</table>

<?php
		printserverstats($server_id);
	}  //for servers

echo '</div>';

 printSectionTitle('Server Load History');
 ?>
    
		<div class="responsive-table">
		<table>
			<tr>
				<td class="left">24h View</td>
			</tr>
			<tr>
				<td style="text-align:center; padding:0;">
					<img src="show_graph.php?type=0&amp;game=<?= $game; ?>&amp;server_id=<?= $server_id ?>&amp;theme=<?=$theme?>&amp;range=1" class="responsive" alt="24h View" />
				</td>
			</tr>
		</table>
		</div>
        <div class="responsive-table">
		<table>
			<tr>
				<td class="left">Last Week</td>
			</tr>
			<tr class="hlstats-graph">
				<td style="text-align:center;padding:0;">
					<img src="show_graph.php?type=0&amp;game=<?php echo $game; ?>&amp;server_id=<?php echo $server_id ?>&amp;theme=<?=$theme?>&amp;range=2" class="responsive" alt="Last Week" />
				</td>
			</tr>
		</table>
		</div>
		<div class="responsive-table">
		<table>
			<tr>
				<td class="left">Last Month</td>
			</tr>
			<tr class="hlstats-graph">
				<td style="text-align:center;padding:0;">
					<img src="show_graph.php?type=0&amp;game=<?php echo $game; ?>&amp;server_id=<?php echo $server_id ?>&amp;theme=<?=$theme?>&amp;range=3" class="responsive" alt="Last Month" />
				</td>
			</tr>
		</table>
		</div>
		<div class="responsive-table">
		<table>
			<tr>
				<td class="left">Last Year</td>
			</tr>
			<tr class="hlstats-graph">
				<td style="text-align:center;padding:0;">
					<img src="show_graph.php?type=0&amp;game=<?php echo $game; ?>&amp;server_id=<?php echo $server_id ?>&amp;theme=<?=$theme?>&amp;range=4" class="responsive" alt="Last Year" />
				</td>
			</tr>
		</table>
		</div>


