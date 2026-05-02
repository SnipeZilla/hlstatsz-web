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
?>
		
    <table class="data-table">
		<tr class="data-table-head">
			<th class="left"><?php
				if ($total_kills>0)
					$hpk = sprintf('%.2f', ($total_headshots/$total_kills)*100);
				else
					$hpk = sprintf('%.2f', 0);
				echo 'Tracking <strong>'.nf($total_players).'</strong> players with <strong>'.nf($total_kills).'</strong> kills and <strong>'.nf($total_headshots)."</strong> headshots (<strong>$hpk%</strong>) on <strong>$total_servers</strong> servers"; ?>
			</th>
		</tr>
        <tr class="data-table-head">
			<td style="text-align:center;padding:0px;">
				<img src="show_graph.php?type=0&amp;width=1120&amp;height=260&amp;server_id=<?php echo $server_id ?>&amp;bgcolor=<?php echo $g_options['graphbg_load']; ?>&amp;color=<?php echo $g_options['graphtxt_load']; ?>"  class="responsive" style="border:0px;">
			</td>
		</tr>
	</table>
