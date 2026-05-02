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

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;

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
               30 OFFSET $start
            ";
}

// Clan Rankings
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

$query  = qClansRank();
?>
<div id="clans">
<div  class="responsive-table">
<table class="clans-table">
    <thead>
        <th class="sticky <?= isSorted('rank_position', $sort, $sortorder) ?>"><?= headerUrl('rank_position',['sort','sortorder'],'clans') ?>Rank</a></th>
        <th class="left<?= isSorted('name', $sort, $sortorder) ?>"><?= headerUrl('name',['sort','sortorder'],'clans') ?>Clan</a></th>
        <th class="<?= isSorted('tag', $sort, $sortorder) ?>"><?= headerUrl('tag',['sort','sortorder'],'clans') ?>Tag</a></th>
        <?php  if ($g_options['rankingtype']!='kills') { ?>
        <th class="<?= isSorted('skill', $sort, $sortorder) ?>"><?= headerUrl('skill',['sort','sortorder'],'clans') ?>Points</a></th>
        <?php } ?>
        <th class="<?= isSorted('kills', $sort, $sortorder) ?>"><?= headerUrl('kills',['sort','sortorder'],'clans') ?>Kills</a></th>
        <th class="<?= isSorted('deaths', $sort, $sortorder) ?>"><?= headerUrl('deaths',['sort','sortorder'],'clans') ?>Deaths</a></th>
        <th class="<?= isSorted('kpd', $sort, $sortorder) ?>"><?= headerUrl('kpd',['sort','sortorder'],'clans') ?>K:D</a></th>
        <th class="<?= isSorted('headshots', $sort, $sortorder) ?>"><?= headerUrl('headshots',['sort','sortorder'],'clans') ?>Headshots</a></th>
        <th class="<?= isSorted('hpk', $sort, $sortorder) ?>"><?= headerUrl('hpk',['sort','sortorder'],'clans') ?>HS:K</a></th>
        <th class="<?= isSorted('members', $sort, $sortorder) ?>"><?= headerUrl('members',['sort','sortorder'],'clans') ?>Members</a></th>
        <th class="<?= isSorted('activity', $sort, $sortorder) ?>"><?= headerUrl('activity',['sort','sortorder'],'clans') ?>Activity</a></th>
        <th class="<?= isSorted('connection_time', $sort, $sortorder) ?>"><?= headerUrl('connection_time',['sort','sortorder'],'clans') ?>Connection Time</a></th>
    </thead>
    <tbody>
    <?php
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
<?php
    echo Pagination($total, $_GET['page'] ?? 1, 30, 'page', false, 'clans');

?>
</div>

<div>
<div style="float:left;">
  <a href="?mode=players&amp;game=<?=$game?>">&larr;&nbsp;Player Rankings</a>
</div>
<div style="float:right;">
    <a href="?mode=actions&amp;game=<?=$game?>">Action Statistics&nbsp;&rarr;</a>
</div>
</div>
