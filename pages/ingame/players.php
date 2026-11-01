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

require_once INCLUDE_PATH . '/players_rank.php';

$rank_type = 0;
$sortorder = valid_request($_GET['sortorder'] ?? '', false);
$sort      = valid_request($_GET['sort'] ?? '', false);
$page      = max(1, (int)($_GET['page'] ?? 1));

$col = array("lastName","rank_position","skill","kills","deaths","kpd","headshots","hpk","acc","activity","connection_time");
if (!in_array($sort, $col)) {
    $sort      = rankColumns()[0];
    $sortorder = 'DESC';
}
$sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

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


// The page with each player's true rank (includes/players_rank.php)
$ranking = rankPlayers($game, $sort, $sortorder, $page);
$total   = $ranking['total'];

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

        foreach ($ranking['rows'] as $res)
        {
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
            if (preg_match("/^BOT:/", $res['uniqueId'] ?? '')) {
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
                echo '<a href="ingame.php?mode=statsme&amp;player='.$res['playerId'].'&game='.$game.'" title=""><span class="hlstats-name">'.htmlspecialchars(html_entity_decode($res['lastName'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_COMPAT).'&nbsp;</span></a>
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
    echo  '</table>'.
          Pagination($total, $page, 30, 'page', false);




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

