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

function printserverstats($server_id)
{
	global $db, $g_options, $game;
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
	$result = $db->query($query);
	$servers   = array();
	$servers[] = $db->fetch_array($result);
?>



<?php

	$i=0;
	for ($i=0; $i<count($servers); $i++)
	{
		$rowdata = $servers[$i]; 
			
		$server_id = $rowdata['serverId'];
		$game = $rowdata['game'];
		$addr = $rowdata["addr"];
		$kills     = $rowdata['kills'];
		$headshots = $rowdata['headshots'];
		$player_string = $rowdata['act_players']."/".$rowdata['max_players'];
		$map_teama_wins = $rowdata['map_ct_wins'];
		$map_teamb_wins = $rowdata['map_ts_wins'];
		$mode = 'playerinfo';
		if (strpos($_SERVER['PHP_SELF'], 'ingame') !== FALSE)
		{
			$mode = 'statsme';
		}

?>
<table class="livestats-table-players">
	<tr>
		<th class="right">#</th>
		<th class="hlstats-main-column left responsive">Player</th>
		<th class="kd"><span class="k right">Kills</span><span class="sep">:</span><span class="d left">Deaths</span></th>
		<th class="hide">Hs</th>
		<th class="hide-1">HS:K</th>
		<th class="hide-1">Acc</th>
		<th class="hide">Lat</th>
		<th class="hide-2">Time</th>
		<th class="hide-2">+/-</th>
		<th class="hide-3">Skill</th>
	</tr>

<?php 
		unset($team_colors);
		$statsdata = $db->query("
			SELECT 
				team, 
				name, 
				teamkills, 
				teamdeaths, 
				teamheadshots, 
				teamping, 
				teamskill, 
				teamshots, 
				teamhits, 
				teamjointime, 
				IFNULL(playerlist_bgcolor,'#D5D5D5') as playerlist_bgcolor, 
				IFNULL(playerlist_color,'#050505') AS playerlist_color, 
				IFNULL( playerlist_index, 99 ) AS playerlist_index
			FROM 
				hlstats_Teams
			RIGHT JOIN
				(SELECT 
					team, 
					sum( kills ) AS teamkills, 
					sum( deaths ) AS teamdeaths, 
					sum( headshots ) AS teamheadshots, 
					avg( ping /2 ) AS teamping, 
					avg( skill ) AS teamskill, 
					sum( shots ) AS teamshots, 
					sum( hits ) AS teamhits, 
					sum( unix_timestamp( NOW( ) ) - connected ) AS teamjointime
				FROM 
					hlstats_Livestats
				WHERE 
					server_id = $server_id
					AND connected >0
				GROUP BY 
					team
				ORDER BY 
					teamkills
				) teaminfo
			ON
				code = team
			AND
				hlstats_Teams.game = '$game'
			ORDER BY 
				playerlist_index
			LIMIT 0 , 30
			");
		$teamdata = array();
		$playerdata = array();
		$teamno = 0;
		while ($thisteam = $db->fetch_array($statsdata))
		{
			$teamname = $db->escape($thisteam['team']);
			$teamdata[$teamno] = $thisteam;
			$pldata = $db->query("
								SELECT
									player_id, 
									name, 
									kills, 
									deaths, 
									headshots, 
									ping, 
									skill, 
									shots, 
									hits, 
									connected, 
									skill_change,
									cli_country,
									cli_state,
									cli_flag,
                                    steam_id
								FROM 
									hlstats_Livestats 
								WHERE 
									server_id = $server_id 
									AND team = '$teamname'
								ORDER BY 
									kills DESC
				");
			while ($thisplayer = $db->fetch_array($pldata))
			{
				$playerdata[$teamno][] = $thisplayer;
			}
			$teamno++;
		}

		$curteam = 0;
		while (isset($teamdata[$curteam]))
		{
			$j = 0;
            $thisteam = $teamdata[$curteam];

			$teamcolor         = empty($thisteam['team']) ? 'team_'.$curteam.' team_spectator' : 
                                                            'team_'.$curteam.' team_'.strtolower($thisteam['team']);
			$team_display_name = $thisteam['name'] ? htmlspecialchars($thisteam['name']) : htmlspecialchars(ucfirst(strtolower($thisteam['name'] ?? '')));
			if ($team_display_name == '') {
				// Team is not in hlstats_Teams, but is present in the game log, e.g. player<123><STEAM_0:0:xxxxxxx><SPECTATOR> and hence in Livestats table as (in cs1.6: SPECTATOR, UNASSIGNED) (in csgo: Spectator, Unassigned).
				$team_display_name = $thisteam['team'] ? htmlspecialchars(ucfirst(strtolower($thisteam['team']))) : '';
				// Team is not in hlstats_Teams, but is also empty in the game log, e.g. player<123><STEAM_0:0:xxxxxxx><> and hence is null in Livestats table. This is a connecting client 100% of the time.
				if ($team_display_name == '') {
					$team_display_name = 'Unknown team';
				}
			}

			while (isset($playerdata[$curteam][$j]))
			{
				$thisplayer = $playerdata[$curteam][$j];

?>
	<tr class="<?= $teamcolor ?>">
		<td class="right nowrap"><?php
				if (isset($thisplayer) && $team_display_name)
				{
					echo ($j+1);
				}
				else
				{
					echo '&nbsp;';
				}
?>		</td>
		<td class="left responsive"><?php
				if (isset($thisplayer))
				{
                    if ( $thisplayer['steam_id'] == 'BOT' ) {
                        $thisplayer['cli_flag'] = 'bot';
                        $thisplayer['cli_country'] = "I'm a bot";
                    };
					if (strlen($thisplayer['name'])>50)
					{
						$thisplayer['name'] = substr($thisplayer['name'], 0, 50);
					}
					if ($g_options['countrydata'])
					{
						echo '<span class="hlstats-flag"><img src="'.getFlag($thisplayer['cli_flag']).'" alt="'.ucfirst(strtolower($thisplayer['cli_country'])).'" title="'.ucfirst(strtolower($thisplayer['cli_country'])).'" /></span>';
					}
					echo '<a href="'.$g_options['scripturl'].'?mode='.$mode.'&amp;player='.$thisplayer['player_id'].'"><span class="hlstats-name">';
					echo htmlspecialchars($thisplayer['name'], ENT_COMPAT).'</span></a>';
				}
				else
				{
					echo '&nbsp;';
				}
?>
        </td>
<?php
        $k= isset($thisplayer) ? $thisplayer['kills'] : 0;
        $d= isset($thisplayer) ? $thisplayer['deaths'] : 0;
        echo '<td class="kd">
                <span class="k right">'.$k.'</span>
                <span class="sep">:</span>
                <span class="d left">'.$d.'</span>
              </td>';
?>
		<td class="hide"><?php
				if (isset($thisplayer))
				{
					echo $thisplayer['headshots'];
				}
				else
				{
					echo '&nbsp;';
				}
?>		</td>
		<td class="hide-1"><?php
				if (isset($thisplayer))
				{
					$hpk = sprintf('%.2f', 0);
					if ($thisplayer['kills'] > 0)
					{
						$hpk = sprintf('%.2f', $thisplayer['headshots']/$thisplayer['kills']);
					}
					echo $hpk;
				}
				else
				{
					echo '&nbsp;';
				}
?>		</td>
		<td class="hide-1"><?php
				if (isset($thisplayer))
				{
					$acc = sprintf('%.0f', 0);
					if ($thisplayer['shots'] > 0)
					{
						$acc = sprintf('%.0f', ($thisplayer['hits']/$thisplayer['shots'])*100);
					}
					echo "$acc%";
				}
				else
				{
					echo '&nbsp;';
				}
?>		</td>
		<td class="hide"><?php
				if (isset($thisplayer))
				{
					echo sprintf('%.0f', $thisplayer['ping'] / 2);
				}
				else
				{
					echo '&nbsp;';
				}
?>		</td>
		<td class="hide-2"><?php
				if (isset($thisplayer))
				{
					if ($thisplayer['connected']>0)
					{
						echo TimeStamp(time()-$thisplayer['connected']);
					}
					else
					{
						echo 'Unknown';
					}
				}
				else
				{
					echo '&nbsp;';
				}
?>		</td>
		<td class="hide-2"><?php
				if (isset($thisplayer))
				{
					echo $thisplayer['skill_change'];
				}
				else
				{
					echo '&nbsp;';
				}
?>		</td>
		<td class="hide-3"><?php
				if (isset($thisplayer))
				{
					echo nf($thisplayer['skill']);
				}
				else
				{
					echo '&nbsp;';
				}
?>		</td>
	</tr>

<?php
			$j++;
			} // End players
			
			if ($team_display_name)
			{
    ?>
	<tr class="<?= $teamcolor ?>">
		<td>&nbsp;</td>
		<td class="left"><?php
				echo "<strong>$team_display_name</strong>";
				if (($curteam === 0) && ($map_teamb_wins > 0))
				{
					echo '&nbsp;('.$map_teamb_wins.' wins)';
				}
				if (($curteam === 1) && ($map_teama_wins > 0))
				{
					echo '&nbsp;('.$map_teama_wins.' wins)';
				}
?>		</td>
        <?php
        $k= count($teamdata[$curteam]) > 0 ? $teamdata[$curteam]['teamkills'] : 0;
        $d= count($teamdata[$curteam]) > 0 ? $teamdata[$curteam]['teamdeaths'] : 0;
        echo '<td class="kd nowrap">
                <span class="k right">'.$k.'</span>
                <span class="sep">:</span>
                <span class="d left">'.$d.'</span>
              </td>';
        ?>
		<td class="hide"><?php
				if (count($teamdata[$curteam]) > 0)
				{
					echo $teamdata[$curteam]['teamheadshots'];
				}
				else
				{
					echo '&nbsp;';
				}
?>		</td>
		<td class="hide-1"><?php
				if (count($teamdata[$curteam]) > 0)
				{
					$hpk = sprintf('%.2f', 0);
					if ($teamdata[$curteam]['teamkills'] > 0)
					{
						$hpk = sprintf('%.2f', $teamdata[$curteam]['teamheadshots']/$teamdata[$curteam]['teamkills']);
					}
					echo $hpk;
				}
				else
				{
					echo '&nbsp;';
				}
?>		</td>
		<td class="hide-1">
            <?php
				if (count($teamdata[$curteam]) > 0)
				{
					$acc = sprintf('%.0f', 0);

					if ($teamdata[$curteam]['teamshots'] > 0)
					{
						$acc = sprintf('%.0f', ($teamdata[$curteam]['teamhits']/$teamdata[$curteam]['teamshots'])*100);
					}
					echo "$acc%";
				}
				else
				{
					echo '&nbsp;';
				}
            ?>
        </td>
		<td class="hide"><?php
				if (count($teamdata[$curteam]) > 0)
				{
					echo sprintf('%.0f', $teamdata[$curteam]['teamping'] / count($teamdata[$curteam]));
				}
				else
				{
					echo '&nbsp;';
				}
?>		</td>
		<td class="hide-2"><?php
				if (count($teamdata[$curteam]) > 0)
				{
					if ($teamdata[$curteam]['teamjointime'] > 0)
					{
						echo TimeStamp($teamdata[$curteam]['teamjointime']);
					}
					else
					{
						echo 'Spectator';
					}
				}
				else
				{
					echo '&nbsp;';
				}
?>		</td>
		<td class="hide-2">-</td>
		<td class="hide-3"><?php
				if (count($teamdata[$curteam])>0)
				{
					echo nf(sprintf('%.0f', $teamdata[$curteam]['teamskill']));
				}
				else
				{
					echo '&nbsp;';
				}
?>		</td>
	</tr>

<?php
			}
			$curteam++;
		} //while i for teams
		if (count($teamdata) == 0)
		{
?>
	<tr>
		<td><?php 
			echo '&nbsp;';  
?>		</td>
		<td class="left" colspan="9"><?php 
			echo "No Players";  
?>		</td>
	</tr>
<?php
		}
    ?>
</table>

<?php
	}  // for servers
}

?>
