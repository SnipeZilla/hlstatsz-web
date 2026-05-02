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
?>
	<table>
		<tr>
			<th class="hlstats-main-column left">Participating Servers</th>
			<th>Address</th>
			<th>Map</th>
			<th>Played</th>
			<th>Players</th>
		</tr>
        
<?php
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
                game='$game'
            ORDER BY
                name, serverId
    ";
	$db->query($query);
	$this_server = array();
	$servers = array();
	while ($rowdata = $db->fetch_array()) {
		$servers[] = $rowdata;
		if ($rowdata['serverId'] == $server_id)
			$this_server = $rowdata;
	}
          
	$i=0;
	for ($i=0; $i<count($servers); $i++)
	{
		$rowdata = $servers[$i]; 
		$server_id = $rowdata['serverId'];
		$c = ($i % 2) + 1;
		$addr = $rowdata["addr"];
		$kills     = $rowdata['kills'];
		$headshots = $rowdata['headshots'];
		$player_string = $rowdata['act_players']."/".$rowdata['max_players'];
		$map_ct_wins = $rowdata['map_ct_wins'];
		$map_ts_wins = $rowdata['map_ts_wins'];
?>

		<tr>
			<td class="hlstats-name left"><?= $rowdata['name'] ?></td>
			<td><?= $addr ?></td>
			<td><?= $rowdata['act_map'] ?></td>
			<td>
            <?php
				echo TimeStamp(time()-$rowdata['map_started']);
			?>
            </td>
			<td><?= $player_string ?></td>
		</tr>
<?php } ?>
	</table>
<div>
<div style="float:right;">
    <a href="?mode=players&amp;game=<?=$game?>">Player Rankings&nbsp;&rarr;</a>
</div>
</div>