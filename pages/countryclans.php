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

// Country Clan Rankings
	$db->query
	("
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

    $total      = 0;
    $rank_type1 = 'kills';
    $rank_type2 = 'deaths';
    $sort_type2 = 'ASC';

    
    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';
    $sort2     = "kills";

    $col = array("rank_position","name","skill","nummembers","activity","connection_time","kills","deaths","kpd");
    if (!in_array($sort, $col)) {
        $sort      = "kills";
        $sortorder = "DESC";
    }

    if ($sort == $rank_type2) {
        $sort2 = $rank_type1;
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 10 : 0;

    $result = $db->query("
         SELECT
             RANK() OVER (
                 ORDER BY $rank_type1 DESC, $rank_type2 $sort_type2
             ) AS rank_position,
             flag,
             name,
             nummembers,
             kills,
             deaths,
             connection_time,
             skill,
             last_skill_change,
             kpd,
             activity,
             COUNT(*) OVER() AS total_rows
         FROM (
             SELECT
                 c.flag,
                 c.name,
                 COUNT(p.playerId) AS nummembers,
                 SUM(p.kills) AS kills,
                 SUM(p.deaths) AS deaths,
                 SUM(p.connection_time) AS connection_time,
                 ROUND(AVG(p.skill)) AS skill,
                 ROUND(AVG(p.last_skill_change)) AS last_skill_change,
                 ROUND(
                     SUM(p.kills) / IF(SUM(p.deaths) = 0, 1, SUM(p.deaths)),
                     2
                 ) AS kpd,
                 TRUNCATE(MAX(p.activity), 2) AS activity
             FROM
                 hlstats_Countries AS c
             INNER JOIN
                 hlstats_Players AS p
                 ON p.flag = c.flag
                 AND p.game = '$game'
                 AND p.hideranking = 0
                 AND p.lastAddress <> ''
             GROUP BY
                 c.flag
             HAVING
                 nummembers >= 1
         ) AS t
         ORDER BY
             $sort $sortorder,
             $sort2 $sortorder,
             name ASC
         LIMIT 30 OFFSET $start;

    ");
    if (!is_ajax()){
    printSectionTitle('Country Rankings');

    echo '<div id="countries">';
        
    }   


if ($db->num_rows($result)) {
echo '<div class="responsive-table">
<table class="countries-table">
    <tr>
        <th class="'. isSorted('rank_position', $sort, $sortorder). '">'. headerUrl('rank_position',['sort','sortorder'],'countries') .'Rank</a></th>
        <th class="hlstats-main-column left'. isSorted('name', $sort, $sortorder) .'">'. headerUrl('name',['sort','sortorder'],'countries') .'Country</a></th>
        <th class="'. isSorted('nummembers', $sort, $sortorder) .'">'. headerUrl('nummembers',['sort','sortorder'],'countries') .'Members</a></th>'
        .($g_options['rankingtype'] != 'kills' ? ('<th class="hide'. isSorted('skill', $sort, $sortorder) .'">'. headerUrl('skill',['sort','sortorder'],'countries') .'Points</a></th>'):'').
        '<th class="hide'. isSorted('kills', $sort, $sortorder) .'">'. headerUrl('kills',['sort','sortorder'],'countries') .'Kills</a></th>
        <th class="hide-1'. isSorted('deaths', $sort, $sortorder) .'">'. headerUrl('deaths',['sort','sortorder'],'countries') .'Deaths</a></th>
        <th class="hide-2'. isSorted('hpd', $sort, $sortorder) .'">'. headerUrl('hpk',['sort','sortorder'],'countries') .'K:D</a></th>
        <th class="hide-3'. isSorted('activity', $sort, $sortorder) .'">'. headerUrl('activity',['sort','sortorder'],'countries') .'Activity</a></th>
        <th class="hide'. isSorted('connection_time', $sort, $sortorder) .'">'. headerUrl('connection_time',['sort','sortorder'],'countries') .'Connection Time</a></th>
    </tr>';

        while ($res = $db->fetch_array($result))
        {
            $total   = $res['total_rows'];
            $time    =  TimeStamp($res['connection_time']);
            echo '
            <tr>
                <td class="nowrap right">'.$res['rank_position'].'</td>
                <td class="left" >
                    <span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" title="'.$res['name'].'" alt="'.$res['flag'].'"></span>
                    <a href="?mode=countryclansinfo&amp;flag='.$res['flag'].'&amp;game='.$game.'" title=""><span class="hlstats-name">'.htmlspecialchars($res['name']).'</span></a>
                </td>
                <td class="nowrap right">'.$res['nummembers'].'</td>'
               .($g_options['rankingtype'] != 'kills' ? ('<td class="nowrap hide">'.nf($res['skill']).'</td>'):'').
                '<td class="nowrap hide">'.nf($res['kills']).'</td>
                <td class="nowrap hide-1">'.nf($res['deaths']).'</td>
                <td class="nowrap hide-2">'.$res['kpd'].'</td>
                <td class="nowrap hide-3">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['activity'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['activity'].'%</div>
                    </div>
                </td>
                <td class="nowrap hide">'.$time.'</td>
            </tr>';
        }

    echo  '</table></div>'.
          Pagination($total, $_GET['page'] ?? 1, 30, 'page');
} else { echo '<p class="hlstats-no-data"><em>Not enough data</em></p>'; }


if (is_ajax()) exit();
?>
</div>
<script>
Fetch.ini('countries');
</script>