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
	global $theme;
	require (PAGE_PATH . '/livestats.php');
	$db->query("SELECT name FROM hlstats_Games WHERE code='$game'");
	if ($db->num_rows() < 1) {
		error("No such game '$game'.");
	}

	list($gamename) = $db->fetch_row();
	$db->free_result();

	$query = "
            SELECT COUNT(*) AS all_players,
            COUNT(CASE WHEN lastAddress <> '' THEN 1 END) AS total_players
            FROM hlstats_Players
            WHERE game = '$game';
	";
	$result = $db->query($query);
	list($all_players, $total_players) = $db->fetch_row($result);

	$query = "
			SELECT 
				players 
			FROM 
				hlstats_Trend 
			WHERE       
				game='$game'
				AND timestamp<=" . (time() - 86400) . "
			ORDER BY 
				timestamp DESC LIMIT 0,1
	";
	$result = $db->query($query);
	list($total_players_24h) = $db->fetch_row($result);
	$players_last_day = -1;
	if ($total_players_24h > 0) {
		$players_last_day = $all_players - $total_players_24h;
	}

	$query = "
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

	$query = "
			SELECT 
				kills 
			FROM 
				hlstats_Trend 
			WHERE       
				game='$game'
				AND timestamp<=" . (time() - 86400) . "
			ORDER BY 
				timestamp DESC LIMIT 0,1
	";
	$result = $db->query($query);
	list($total_kills_24h) = $db->fetch_row($result);
	$db->free_result();

	$kills_last_day = -1;
	if ($total_kills_24h > 0) {
		$kills_last_day = $total_kills - $total_kills_24h;
	}

	$query = "
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
                sortorder, name, serverId
	";
	$db->query($query);
	$servers = $db->fetch_row_set();
	$db->free_result();
    
    
printSectionTitle('Participating Servers');
?>

<table class="hlstats-map">
    <tr class="hlstats-stats"><th class="left" style="white-space:normal"><?php
		if ($total_kills > 0)
			$hpk = sprintf("%.2f", ($total_headshots / $total_kills) * 100);
		else
			$hpk = sprintf("%.2f", 0);
		if ($players_last_day > -1)
			echo "Tracking <b>" . nf($total_players) . "</b> players (<b>+" . nf($players_last_day) . "</b> new players last 24h) with <b>" . nf($total_kills) . "</b> kills (<b>+" . nf($kills_last_day) . "</b> last 24h) and <b>" . nf($total_headshots) . "</b> headshots (<b>$hpk%</b>) on <b>" . nf($total_servers) . "</b> servers";
		else
			echo "Tracking <b>" . nf($total_players) . "</b> players with <b>" . nf($total_kills) . "</b> kills and <b>" . nf($total_headshots) . "</b> headshots (<b>$hpk%</b>) on <b>" . nf($total_servers) . "</b> servers";
?></th>
		</tr>	
<?php

		if ($g_options['show_google_map'] == 1) {
?>
        <tr>
			<td>
				<div id="map"></div>
			</td>
        </tr>  
<?php
		}
		if ($g_options['show_server_load_image'] == 1) {
?>
		<tr class="hide-3">
			<td style="text-align:center;padding:0px;">
				<img src="show_graph.php?type=1&amp;game=<?=$game?>&amp;theme=<?=$theme?>" class="responsive" alt="Server Load Graph" title="serverLoadGraph" />
			</td>
		</tr>
<?php
		}
	
		echo '</table>';


        $slider = ($g_options['slider'] == 1 && count($servers) > 1) ? ' hlstats-slider' : '';
        echo '<div class="responsive-table livestats'.($slider ? ' slider-group' : ' expand-group').'">';

		$i=0;
		for ($i=0; $i<count($servers); $i++)
		{
			$rowdata = $servers[$i]; 

			$server_id = $rowdata['serverId'];


			$addr = $rowdata['addr'];
			$kills = $rowdata['kills'];
			$headshots = $rowdata['headshots'];
			$player_string = $rowdata['act_players'] . "/" . $rowdata['max_players'];
			$map_teama_wins = $rowdata['map_ct_wins'];
			$map_teamb_wins = $rowdata['map_ts_wins'];
            if ($g_options['slider'] == 0 || $i == 0) {
?>
		  <table class="livestats-table-server<?= $i == 0 ? ' livestats-table-first':' livestats-table-next' ?>">
				<tr>
					<th class="hlstats-main-server left responsive">Server</th>
					<th class="hide">Address</th>
					<th>Map</th>
					<th class="hide-1">Played</th>
					<th>Players</th>
					<th class="hide-1">Kills</th>
					<th class="hide-2">Headshots</th>
					<th class="hide-3">HS:K</th>
				</tr>
<?php } else { echo '<table class="livestats-table-server hlstats-table-fixed '.($i==count($servers)-1? ' livestats-table-last':' livestats-table-next').'">'; } ?>
				<tr<?= $slider ? ' class="hlstats-server-row"' : '' ?>>
					<td class="left"><?php
			$image = getImage("/games/$game/game");
			if ($slider) echo '<span class="hlstats-slider-arrow">▼</span>';
			echo '<span class="hlstats-icon"><img src="';
			if ($image) {
				echo $image['url'];
			} elseif ($image = getImage("/games/$realgame/game")) {
				echo $image['url'];
			} else {
				echo IMAGE_PATH . '/game.png';
			}
			echo "\" alt=\"$game\" /></span>";
			echo "<span class=\"hlstats-name\"><a href=\"" . $g_options['scripturl'] . "?mode=servers&amp;server_id=$server_id&amp;game=$game\">" . htmlspecialchars($rowdata['name']) . "</a></span>";
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
						<td class="nowrap"><?php
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
			<tr class="hlstats-graph hide-2<?= $slider ?>">
			  <td style="padding:0px;text-align:center;" colspan="8">
				<a href="<?php $g_options['scripturl'] ?>?mode=servers&amp;server_id=<?php echo $server_id ?>&amp;game=<?php echo $game ?>" style="text-decoration:none;"><img src="show_graph.php?type=0&amp;game=<?php echo $game; ?>&amp;server_id=<?=$server_id?>&amp;theme=<?=$theme?>" style="border:0px;" class="responsive" alt="Server Load Graph" title="Server Load Graph" /></a>
			  </td>
			</tr>
			</table>
					
	<?php
			printserverstats($server_id);

		} // for servers
		echo '</div>';

if ($g_options['slider'] == 1 && count($servers) > 1) {
?>
<script>
(function() {
    var openRow = null;

    function getSliderEls(row) {
        var els = [];

        var next = row.nextElementSibling;
        while (next && !next.classList.contains('hlstats-server-row')) {
            els.push(next);
            next = next.nextElementSibling;
        }

        var serverTable = row.closest('table');
        if (serverTable) {
            var sib = serverTable.nextElementSibling;
            if (sib && sib.classList.contains('livestats-table-players')) {
                els.push(sib);
            }
        }
        return els;
    }

    function closeSliders(row) {
        getSliderEls(row).forEach(function(el) { el.classList.add('hlstats-slider'); });
    }

    function openSliders(row) {
        getSliderEls(row).forEach(function(el) { el.classList.remove('hlstats-slider'); });
    }

    // hide all player tables on load
    document.querySelectorAll('.livestats-table-players').forEach(function(t) {
        t.classList.add('hlstats-slider');
    });

    document.querySelectorAll('.hlstats-server-row').forEach(function(row) {
        row.style.cursor = 'pointer';
        row.title = 'Click to expand';
        row.addEventListener('click', function(e) {
            if (e.target.closest('a')) return;
            if (openRow === row) {
                closeSliders(row);
                row.classList.remove('is-open');
                openRow = null;
            } else {
                if (openRow) { closeSliders(openRow); openRow.classList.remove('is-open'); }
                openSliders(row);
                row.classList.add('is-open');
                openRow = row;
            }
        });
    });
})();
</script>

<?php }

	if ($g_options['gamehome_show_awards'] == 1) {
		$resultAwards = $db->query("
                SELECT
                    a.awardId,
                    a.awardType,
                    a.code,
                    a.name,
                    a.verb,
                    a.d_winner_id,
                    a.d_winner_count,
                    p.lastName AS d_winner_name,
                    p.flag,
                    p.country,
                    uid.uniqueId AS steamID
                FROM hlstats_Awards a
                LEFT JOIN hlstats_Players p ON p.playerId = a.d_winner_id
                LEFT JOIN hlstats_PlayerUniqueIds uid ON p.playerId = uid.playerId
                WHERE a.game = '$game'
                ORDER BY a.name;
		");

		$result = $db->query("
			SELECT
				IFNULL(value, 1)
			FROM
				hlstats_Options
			WHERE
				keyname='awards_numdays'
		");

		if ($db->num_rows($result) == 1)
			list($awards_numdays) = $db->fetch_row($result);
		else
			$awards_numdays = 1;

		$result = $db->query("
			SELECT
				DATE_FORMAT(value, '%W %e %b'),
				DATE_FORMAT( DATE_SUB( value, INTERVAL $awards_numdays DAY ) , '%W %e %b' )
			FROM
				hlstats_Options
			WHERE
				keyname='awards_d_date'
		");
		list($awards_d_date, $awards_s_date) = $db->fetch_row($result);

		if ($db->num_rows($resultAwards) > 0 && $awards_d_date) {
?>


<?php
	printSectionTitle((($awards_numdays == 1) ? 'Daily' : "$awards_numdays Day")." Awards ($awards_d_date)");
?>


		<table>

<?php
			$c = 0;
			while ($awarddata = $db->fetch_array($resultAwards))
			{
                if (!$awarddata['d_winner_id']) continue;
				$c++;
?>

<tr>
	<td class="left hlstats-main-task"><?php
    
    
    if ($image = getImage("/games/$game/dawards/".strtolower($awarddata['awardType'].'_'.$awarddata['code'])))
    {
        $img = $image['url'];
    }
    elseif ($image = getImage("/games/$realgame/dawards/".strtolower($awarddata['awardType'].'_'.$awarddata['code'])))
    {
        $img = $image['url'];
    }
    else
    {
        $img = IMAGE_PATH.'/award.png';
    }
    
    echo '<span class="hlstats-icon"><img src="'.$img.'" alt="'.$awarddata['code'].'" /></span>';
				echo '<a href="'.$g_options['scripturl'].'?mode=dailyawardinfo&amp;award='.$awarddata['awardId']."&amp;game=$game\">".htmlspecialchars($awarddata['name']).'</a>';
?></td>
	<td class="left"><?php

				if ($awarddata['d_winner_id']) {
                    if (preg_match("/^BOT:/", $awarddata['steamID'] ?? '')) {
                        $awarddata['flag'] = 'bot';
                        $awarddata['country'] = 'I\'m a bot';
                    }
                    if ($g_options['countrydata']) {
                        echo "<span class=\"hlstats-flag\"><img src=\"".getFlag($awarddata['flag'])."\" alt=\"".$awarddata['country']."\" title=\"".$awarddata['country']."\" /></span>";
                    }
					echo "<a href=\"{$g_options['scripturl']}?mode=playerinfo&amp;player={$awarddata['d_winner_id']}\"><span class=\"hlstats-name\">" . htmlspecialchars($awarddata['d_winner_name'] ?? '', ENT_COMPAT) . "</span></a> ({$awarddata['d_winner_count']} " . htmlspecialchars($awarddata['verb']) . ")";
				}
				else
				{
					echo '<em>No Award Winner</em>';
				}
?></td>
</tr>

<?php
			}
            if (!$c) {
                echo '<tr><td class="left"><em>No Award Winner</em></td></tr>';
            }
?></table>

<?php
		}
	}
?>
