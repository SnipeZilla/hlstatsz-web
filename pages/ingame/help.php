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
			<th class="hlstats-name left" colspan="3">Commands display the results ingame</th>
		</tr>
		<tr>
			<td class="left">rank [skill, points, place (to all)]</td>
			<td>=</td>
			<td class="left">Current position</td>
		</tr>
		<tr>
			<td class="left">kpd [kdratio, kdeath (to all)]</td>
			<td>=</td>
			<td class="left">Total player statistics</td>
		</tr>
		<tr>
			<td class="left">session [session_data (to all)]</td>
			<td>=</td>
			<td class="left">Current session statistics</td>
		</tr>
		<tr>
			<td class="left">next</td>
			<td>=</td>
			<td class="left">Players ahead in the ranking.</td>
		</tr>
		<tr class="data-table-head">
			<td class="fSmall data-table" colspan="3">Commands display the results in window</td>
		</tr>
		<tr>
			<td class="left">load</td>
			<td>=</td>
			<td class="left">Statistics from all servers</td>
		</tr>
		<tr>
			<td class="left">status</td>
			<td>=</td>
			<td class="left">Current server status</td>
		</tr>
		<tr>
			<td class="left">servers</td>
			<td>=</td>
			<td class="left">List of all participating servers</td>
		</tr>
		<tr>
			<td class="left">top20 [top5, top10]</td>
			<td>=</td>
			<td class="left">Top-Players</td>
		</tr>
		<tr>
			<td class="left">clans</td>
			<td>=</td>
			<td class="left">Clan ranking</td>
		</tr>
		<tr>
			<td class="left">cheaters</td>
			<td>=</td>
			<td class="left">Banned players</td>
		</tr>
		<tr>
			<td class="left">statsme</td>
			<td>=</td>
			<td class="left">Statistic summary</td>
		</tr>
		<tr>
			<td class="left">weapons [weapon]</td>
			<td>=</td>
			<td class="left">Weapons usage</td>
		</tr>
		<tr>
			<td class="left">accuracy</td>
			<td>=</td>
			<td class="left">Weapons accuracy</td>
		</tr>
		<tr>
			<td class="left">targets [target]</td>
			<td>=</td>
			<td class="left">Targets hit positions</td>
		</tr>
		<tr>
			<td class="left">kills [kill, player_kills]</td>
			<td>=</td>
			<td class="left">Kill statistics (5 or more kills)</td>
		</tr>
		<tr>
			<td class="left">actions [action]</td>
			<td>=</td>
			<td class="left">Server actions summary</td>
		</tr>
		<tr>
			<td class="left">help [cmd, cmds, commands]</td>
			<td>=</td>
			<td class="left">Help screen</td>
		</tr>
		<tr class="data-table-head">
			<td class="fSmall data-table" colspan="3">&nbsp;Commands to set your user options</td>
		</tr>
		<tr>
			<td class="left">hlx_auto clear|start|end|kill command</td>
			<td>=</td>
			<td class="left">Auto-Command on specific event (on death, roundstart, roundend)</td>
		</tr>
		<tr>
			<td class="left">hlx_display 0|1</td>
			<td>=</td>
			<td class="left">Enable or disable displaying console events.</td>
		</tr>
		<tr>
			<td class="left">hlx_chat 0|1</td>
			<td>=</td>
			<td class="left">Enable or disable the displaying of global chat events(if enabled).</td>
		</tr>
		<tr>
			<td class="left">/hlx_set realname|email|homepage [value]</td>
			<td>=</td>
			<td class="left">(Type in chat, not console) Sets your player info.</td>
		</tr>
		<tr>
			<td class="left">/hlx_hideranking</td>
			<td>=</td>
			<td class="left">(Type in chat, not console) Makes you invisible on player rankings, unranked.</td>
		</tr>
    </table>
    <table>
		<tr>
			<th class="hlstats-name left">Participating Servers</th>
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
			serverId
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
			<td><?php
				echo TimeStamp(time()-$rowdata['map_started']);
			?></td>
			<td><?= $player_string ?></td>
		</tr>
<?php } ?>
    </table>
