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

$rank_type = 0;
$sortorder = valid_request($_GET['sortorder'] ?? '', false);
$sort      = valid_request($_GET['sort'] ?? '', false);

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

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;

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
        LIMIT 30 OFFSET $start
    ";

}

// Player Rankings
$db->query("
    SELECT
        hlstats_Games.name
    FROM
        hlstats_Games
    WHERE
        hlstats_Games.code = '$game'
");

if ($db->num_rows() < 1) {
    error("No such game '$game'.");
}

list($gamename) = $db->fetch_row();

$db->free_result();


?>

<div id="players" class="respondive-table">
<?php


$query  = qPlayersRank();

echo '
<table class="players-table">
    <tr>
        <th class="'. isSorted('rank_position', $sort, $sortorder). '">'. headerUrl('rank_position',['sort','sortorder'],'players') .'Rank</a></th>
        <th class="left'. isSorted('lastName', $sort, $sortorder) .'">'. headerUrl('lastName',['sort','sortorder'],'players') .'Player</a></th>';
        if ($g_options['rankingtype']!='kills') {
        echo '<th class="'. isSorted('skill', $sort, $sortorder) .'">'. headerUrl('skill',['sort','sortorder'],'players') .'Points</a></th>';
        }
        echo '
        <th class="'. isSorted('kills', $sort, $sortorder) .'">'. headerUrl('kills',['sort','sortorder'],'players') .'Kills</a></th>
        <th class="'. isSorted('deaths', $sort, $sortorder) .'">'. headerUrl('deaths',['sort','sortorder'],'players') .'Deaths</a></th>
        <th class="'. isSorted('kpd', $sort, $sortorder) .'">'. headerUrl('kpd',['sort','sortorder'],'players') .'K:D</a></th>
        <th class="'. isSorted('headshots', $sort, $sortorder) .'">'. headerUrl('headshots',['sort','sortorder'],'players') .'Headshots</a></th>
        <th class="'. isSorted('hpk', $sort, $sortorder) .'">'. headerUrl('hpk',['sort','sortorder'],'players') .'HS:K</a></th>
        <th class="'. isSorted('acc', $sort, $sortorder) .'">'. headerUrl('acc',['sort','sortorder'],'players') .'Accuracy</a></th>
        <th class="'. isSorted('activity', $sort, $sortorder) .'">'. headerUrl('activity',['sort','sortorder'],'players') .'Activity</a></th>
        <th class="'. isSorted('connection_time', $sort, $sortorder) .'">'. headerUrl('connection_time',['sort','sortorder'],'players') .'Connection Time</a></th>
    </tr>';

    if (!empty($query)) {
        $result = $db->query($query);

        while ($res = $db->fetch_array($result))
        {
            $total   = $res['total_rows'];
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
    echo  '</table>'.
          Pagination($total, $_GET['page'] ?? 1, 30, 'page', false);




?>
</div>

<div>
<div style="float:left;">
   <a href="?mode=servers&amp;game=<?=$game?>">&larr;&nbsp;Server list</a>
</div>
<div style="float:right;">
    <a href="?mode=clans&amp;game=<?=$game?>">Clan Rankings&nbsp;&rarr;</a>
</div>
</div>

