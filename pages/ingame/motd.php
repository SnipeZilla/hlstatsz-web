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
	//
	// Message of the day
	//
  
	//
	// General
	//
  
	$db->query("SELECT name FROM hlstats_Games WHERE code='$game'");
	if ($db->num_rows() < 1) {
        error("No such game '$game'.");
	}
	
	list($gamename) = $db->fetch_row();
	$db->free_result();
	
	$minkills = 1;
	$minmembers = 3;
  
	$players = 10;  
	if (isset($_GET['players']) && is_numeric($_GET['players'])) {
		$players = valid_request($_GET['players'], true);
	}

	$clans = 5;  
	if (isset($_GET['clans']) && is_numeric($_GET['clans'])) {
		$clans = valid_request($_GET['clans'], true);
	}

	$servers = 5;  
	if (isset($_GET['servers']) && is_numeric($_GET['servers'])) {
		$servers = valid_request($_GET['servers'], true);
	}

$rank_type = 0;
$sortorder = 0;
$sort      = 0;

function qPlayersRank()
{
    global $game, $g_options, $sort, $sortorder, $rank_type;


    if ($g_options['rankingtype'] !== 'kills') {
        $rank_type1 = 'skill';
        $rank_type2 = 'kills';
        $sortorder2 = 'DESC';
    } else {
        $rank_type1 = 'kills';
        $rank_type2 = 'deaths';
        $sortorder2 = 'ASC';
    }

    $col = array("lastName","rank_position","skill","kills","deaths","kpd","headshots","hpk","acc","activity","connection_time");
    if (!in_array($sort, $col)) {
        $sort      = $rank_type1;
        $sortorder = 'DESC';
    }
    
    if ($sort == $rank_type2) {
        $sort2 = $rank_type1;
        $sortorder2 = 'DESC';
    } else { $sort2 = $rank_type1; }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 10 : 0;

    $dateFilter = "1=1"; // no date restriction

    return "
        WITH RankedPlayers AS (
            SELECT
                RANK() OVER (ORDER BY $rank_type1 DESC, $rank_type2 $sortorder2) AS rank_position,
                p.playerId,
                p.last_event,
                p.connection_time,
                p.lastName,
                p.flag,
                p.country,
                p.kills,
                p.deaths,
                p.skill,
                p.shots,
                p.hits,
                p.headshots,
                p.last_skill_change,
                p.kill_streak,
                p.death_streak,
                p.activity,
                uid.uniqueId,
                ROUND(IF(p.deaths=0, 0, p.kills/p.deaths), 2) AS kpd,
                ROUND(IF(p.kills=0, 0, p.headshots/p.kills), 2) AS hpk,
                ROUND(IF(p.shots=0, 0, p.hits/p.shots), 3) AS acc,
                COUNT(*) OVER() AS total_rows
            FROM hlstats_players p
            LEFT JOIN hlstats_PlayerUniqueIds uid ON uid.playerId = p.playerId
            WHERE p.hideranking = 0
              AND p.lastAddress <> ''
              AND p.game = '".$game."'
        )
        SELECT *
        FROM RankedPlayers
        ORDER BY $sort $sortorder,
                 $sort2 $sortorder2,
                 connection_time DESC
        LIMIT 10
    ";

}
	//
	// Top 10 Players
	//
	if($players > 0) {
        


printSectionTitle('Players - Top 10');
?>

<div id="players" class="respondive-table">
<?php


$query  = qPlayersRank();

echo '
<table class="players-table">
    <tr>
        <th>'. headerUrl('rank_position',['sort','sortorder'],'players') .'Rank</a></th>
        <th class="left">Player</th>';
        if ($g_options['rankingtype']!='kills') {
        echo '<th>Points</th>';
        }
        echo '
        <th>Kills</th>
        <th>Deaths</th>
        <th>K:D</th>
        <th>Headshots</th>
        <th>HS:K</th>
        <th>Accuracy</th>
        <th>Activity</th>
        <th>Connection Time</th>
    </tr>';

    if (!empty($query)) {
        $result = $db->query($query);

        while ($res = $db->fetch_array($result))
        {
            $page    = $_GET['page'] ?? 1;
            $total   = min($_GET['page']*10,$res['total_rows']);
            $time    =  TimeStamp($res['connection_time']);
            $sign    = '';
            $class   = ' skill';
            if ($res['last_skill_change'] > 0) {
                $sign = '+';
                $class .= ' up green';
            }
            if ($res['last_skill_change'] < 0) {
                $class .= ' down red';
            }
            if (preg_match("/^BOT:/", $res['uniqueId'])) {
                $res['flag'] = 'bot';
                $res['country'] = "i'm a bot";
            }
            echo '
            <tr>
                <td class="nowrap right">'.$res['rank_position'].'</td>
                <td class="left'.$class.'" title="'.$sign.$res['last_skill_change'].'">';
                  if ($g_options['countrydata']) {
                   echo '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" title="'.$res['country'].'" alt="'.$res['flag'].'"></span>';
                  }
                   echo '<a href="ingame.php?mode=statsme&amp;player='.$res['playerId'].'&game='.$game.'" title=""><span class="hlstats-name">'.htmlspecialchars($res['lastName']).'&nbsp;</span></a>
                </td>'
               .($g_options['rankingtype'] != 'kills' ? ('<td class="nowrap">'.nf($res['skill']).'</td>'):'').
                '<td class="nowrap">'.nf($res['kills']).'</td>
                <td class="nowrap">'.nf($res['deaths']).'</td>
                <td class="nowrap">'.$res['kpd'].'</td>
                <td class="nowrap">'.nf($res['headshots']).'</td>
                <td class="nowrap">'.$res['hpk'].'</td>
                <td class="nowrap">'.($res['acc']*100).'%</td>
                <td class="nowrap">'.$res['activity'].'%</td>
                <td class="nowrap">'.$time.'</td>
            </tr>';
        }
    }
    echo  '</table>';

?>
</div>
<?php
    }
  
	//
	// Top 5 Clans
	//
	if($clans > 0) {
$rank_type = 0;
$sortorder = 0;
$sort      = 0;

function qClansRank()
{
    global $game, $g_options, $sort, $sortorder, $rank_type;

    if ($g_options['rankingtype'] !== 'kills') {
        $rank_type1 = 'skill';
        $rank_type2 = 'kills';
        $rank_sort2 = 'DESC';
    } else {
        $rank_type1 = 'kills';
        $rank_type2 = 'deaths';
        $rank_sort2 = 'ASC';
    }

    $col = array("rank_position","name", "tag", "skill","kills","deaths","kpd","headshots","hpk","members","activity","connection_time");
    if (!in_array($sort, $col)) {
        $sort      = $rank_type1;
        $sortorder = "DESC";
    }
    
    $sort2 = ($sort == $rank_type2? $rank_type1: $rank_type2);

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['clans_page']) ? ((int)$_GET['clans_page'] - 1) * 5 : 0;

    $dateFilter = "1=1"; // no date restriction

    return "SELECT 
               RANK() OVER (ORDER BY AVG($rank_type1) DESC, SUM($rank_type2) $rank_sort2) AS rank_position,
               p.clan as clan,
               c.tag AS tag,
               c.name AS name,
               p.flag AS flag,
               p.country AS country,
               SUM(p.kills) AS kills,
               SUM(p.deaths) AS deaths,
               SUM(p.last_skill_change) AS last_skill_change,
               FLOOR(AVG(p.skill)) AS skill,
               SUM(p.shots) AS shots,
               SUM(p.hits) AS hits,
               SUM(p.headshots) AS headshots,
               MAX(p.activity) AS activity,
               SUM(p.connection_time) as connection_time,
               COUNT(p.playerId) AS members,
               ROUND(SUM(p.kills) / NULLIF(SUM(p.deaths), 0),2) AS kpd,
               ROUND(SUM(p.headshots) / NULLIF(SUM(p.kills), 0),2) AS hpk,
               COUNT(*) OVER() AS total_rows
           FROM
               hlstats_players AS p
           LEFT JOIN
               hlstats_clans AS c ON p.clan = c.clanId
           WHERE
               p.hideranking = 0 
               AND p.lastAddress <> ''
               AND p.game = '".$game."'
               AND p.clan > 0
           GROUP BY
               p.clan
           HAVING
               COUNT(p.playerId) > 1
           ORDER BY
               $sort $sortorder,
               $sort2 $sortorder,
               connection_time DESC
           LIMIT 
               5
            ";
}


    printSectionTitle('Clans - Top 5');
?>

<div id="clans">
<?php

$query  = qClansRank();
?>
<div  class="responsive-table">
<table class="clans-table">
    <thead>
        <th class="sticky">Rank</th>
        <th class="left">Clan</th>
        <th>Tag</th>
        <?php  if ($g_options['rankingtype']!='kills') { ?>
        <th>Points</th>
        <?php } ?>
        <th>Kills</th>
        <th>Deaths</th>
        <th>K:D</th>
        <th>Headshots</th>
        <th>HS:K</th>
        <th>Members</th>
        <th>Activity</th>
        <th>Connection Time</th>
    </thead>
    <tbody>
    <?php
    if (!empty($query)) {
        $result = $db->query($query);
        while ($res = $db->fetch_array($result))
        {
            $total   = $res['total_rows'];
            $time    = TimeStamp($res['connection_time']);
            $sign    = '';
            $class   = ' skill';
            if ($res['last_skill_change'] > 0) {
                $sign = '+';
                $class .= ' up green';
            }
            if ($res['last_skill_change'] < 0) {
                $class .= ' down red';
            }
            echo '
            <tr>
                <td class="nowrap right">'.$res['rank_position'].'</td>
                <td class="left'.$class.'" title="'.$sign.$res['last_skill_change'].'"><a href="'.$g_options['scripturl'].'?mode=claninfo&amp;clan='.$res['clan'].'" title=""></span><span class="hlstats-name">'.htmlspecialchars($res['name']).'</span></a></td>
                <td class="nowrap">'.htmlspecialchars($res['tag']).'</td>'
             .($g_options['rankingtype'] != 'kills' ?
                ('<td class="nowrap">'.nf($res['skill']).'</td>'):'').
                '<td class="nowrap">'.nf($res['kills']).'</td>
                <td class="nowrap">'.nf($res['deaths']).'</td>
                <td class="nowrap">'.$res['kpd'].'</td>
                <td class="nowrap">'.nf($res['headshots']).'</td>
                <td class="nowrap">'.$res['hpk'].'</td>
                <td class="nowrap">'.$res['members'].'</td>
                <td class="nowrap">'.$res['activity'].'%</td>
                <td class="nowrap">'.$time.'</td>
            </tr>';
        }
    }
    ?>
</tbody>
</table>
</div>

</div>

<?php
	}
  
	//
	// Servers
	//
	if ($servers > 0) {
printSectionTitle('Participating Servers');

        ?>
	<table>
		<tr>
			<th class="hlstats-main-column left">Server</th>
			<th>Address</th>
			<th>Map</th>
			<th>Played</th>
			<th>Players</th>
		</tr>
        
<?php
    $query= "
            SELECT
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
                act_players, name, serverId
            LIMIT 5 OFFSET 0
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
				$stamp = time()-$rowdata['map_started'];
				$hours = sprintf('%02d', floor($stamp / 3600));
				$min   = sprintf('%02d', floor(($stamp % 3600) / 60));
				$sec   = sprintf('%02d', floor($stamp % 60)); 
				echo "$hours:$min:$sec";
			?>
            </td>
			<td><?= $player_string ?></td>
		</tr>
<?php } ?>
	</table>

<?php  } ?>
