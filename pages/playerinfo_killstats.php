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

    // Player Kill Statistics
    ob_flush();
    flush();;

if (empty($_GET['ajax']) || $_GET['ajax'] == 'playerkills') {

    if ($g_options['rankingtype'] !== 'kills') {
        $rank_type1 = 'skill';
        $rank_type2 = 'kills';
        $sort_type2 = 'DESC';
    } else {
        $rank_type1 = 'kills';
        $rank_type2 = 'deaths';
        $sort_type2 = 'ASC';
    }

    $sortorder = $_GET['playerkills_sortorder'] ?? '';
    $sort      = $_GET['playerkills_sort'] ?? '';
    $sort2     = "headshots";

    $col = array("rank_position","name","kills","kpercent","deaths","dpercent","kpd","headshots","hpercent","hpk");
    if (!in_array($sort, $col)) {
        $sort      = "rank_position";
        $sortorder = "ASC";
    }

    if ($sort == "headshots") {
        $sort2 = "kills";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['playerkills_page']) ? ((int)$_GET['playerkills_page'] - 1) * 30 : 0;

    $db->query
    ("
    WITH RankedPlayers AS (
        SELECT
            playerId,
            RANK() OVER (ORDER BY $rank_type1 DESC, $rank_type2 $sort_type2) AS global_rank
        FROM hlstats_players
        WHERE hideranking = 0
          AND lastAddress <> ''
          AND game = '$game'
    ),
    frags AS (
        SELECT
            f.killerId,
            f.victimId,
            f.headshot
        FROM hlstats_Events_Frags f
        WHERE f.killerId = $player
           OR f.victimId = $player
    ),

    agg AS (
        SELECT
            other.playerId,
            SUM(other.kills) AS kills,
            SUM(other.deaths) AS deaths,
            SUM(other.headshots) AS headshots
        FROM (
            -- Kills by player
            SELECT
                victimId AS playerId,
                1 AS kills,
                0 AS deaths,
                headshot AS headshots
            FROM frags
            WHERE killerId = $player

            UNION ALL

            -- Deaths by player
            SELECT
                killerId AS playerId,
                0 AS kills,
                1 AS deaths,
                0 AS headshots
            FROM frags
            WHERE victimId = $player
        ) AS other
        GROUP BY other.playerId
    ),

    totals AS (
        SELECT
            SUM(headshots) AS realheadshots
        FROM agg
        WHERE kills >= $killLimit
    )

    SELECT
        p.lastName AS name,
        p.flag,
        p.country,
        a.kills,
        a.deaths,
        ROUND(a.kills / $realkills * 100, 2) AS kpercent,
        ROUND(a.deaths / $realdeaths * 100, 2) AS dpercent,
        a.playerId AS victimId,
        ROUND(a.kills / IF(a.deaths = 0, 1, a.deaths), 2) AS kpd,
        a.headshots,
        ROUND(a.headshots / IF(a.kills = 0, 1, a.kills), 2) AS hpk,
        ROUND(a.headshots / t.realheadshots * 100, 2) AS hpercent,
        rp.global_rank AS rank_position,
        COUNT(*) OVER() AS total_rows
    FROM agg a
    JOIN hlstats_Players p ON p.playerId = a.playerId
    JOIN RankedPlayers rp ON rp.playerId = a.playerId
    CROSS JOIN totals t
    WHERE a.kills >= $killLimit
    ORDER BY
        $sort $sortorder,
        $sort2 $sortorder
    LIMIT 30 OFFSET $start;

");

    if ($db->num_rows($result)) {
        if (empty($_GET['ajax'])) {
        printSectionTitle('Player Kill Statistic *');
        ?>
<div id="playerkills">

<?php
}
?>
<div  class="responsive-table">
  <table class="maps-table">
    <tr>
        <th class="nowarp right<?= isSorted('rank_position',$sort,$sortorder) ?>" style="width:1%"><?= headerUrl('rank_position', ['playerkills_sort','playerkills_sortorder'], 'playerkills') ?>Rank</a></th>
        <th class="hlstats-main-description left<?= isSorted('name',$sort,$sortorder) ?>"><?= headerUrl('name', ['playerkills_sort','playerkills_sortorder'], 'playerkills') ?>Name</a></th>
        <th class="<?= isSorted('kills',$sort,$sortorder) ?>"><?= headerUrl('kills', ['playerkills_sort','playerkills_sortorder'], 'playerkills') ?>Kills</a></th>
        <th class="hide-2 meter-ratio <?= isSorted('kpercent',$sort,$sortorder) ?>"><?= headerUrl('kpercent', ['playerkills_sort','playerkills_sortorder'], 'playerkills') ?>Ratio</a></th>
        <th class="hide<?= isSorted('deaths',$sort,$sortorder) ?>"><?= headerUrl('deaths', ['playerkills_sort','playerkills_sortorder'], 'playerkills') ?>Deaths</a></th>
        <th class="hide-2 meter-ratio <?= isSorted('dpercent',$sort,$sortorder) ?>"><?= headerUrl('dpercent', ['playerkills_sort','playerkills_sortorder'], 'playerkills') ?>Ratio</a></th>
        <th class="hide-3<?= isSorted('kpd',$sort,$sortorder) ?>"><?= headerUrl('kpd', ['playerkills_sort','playerkills_sortorder'], 'playerkills') ?>K:D</a></th>
        <th class="hide-1<?= isSorted('headshots',$sort,$sortorder) ?>"><?= headerUrl('headshots', ['playerkills_sort','playerkills_sortorder'], 'playerkills') ?>Headshots</a></th>
        <th class="hide-2 meter-ratio <?= isSorted('hpercent',$sort,$sortorder) ?>"><?= headerUrl('hpercent', ['playerkills_sort','playerkills_sortorder'], 'playerkills') ?>Ratio</a></th>
        <th class="hide-3<?= isSorted('hpk',$sort,$sortorder) ?>"><?= headerUrl('hpk', ['playerkills_sort','playerkills_sortorder'], 'playerkills') ?>HS:K</a></th>
    </tr>
    <?php

        $i = 1+$start;

        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            echo '<tr>
                  <td class="nowrap right">'.$res['rank_position'].'</td>
                  <td class="hlstats-main-description left">';
            if ($g_options['countrydata']) {
                echo '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" title="'.$res['country'].'" alt="'.$res['flag'].'"></span>';
            }
             echo '<a href="?mode=playerinfo&amp;player='.$res['victimId'].'" title=""><span class="hlstats-name">'.htmlspecialchars($res['name']).'&nbsp;</span></a></td>
                  <td class="nowrap">'.nf($res['kills']).'</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['kpercent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['kpercent'].'%</div>
                    </div>
                  </td>
                  <td class="nowrap hide">'.nf($res['deaths']).'</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['dpercent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['dpercent'].'%</div>
                    </div>
                  </td>
                  <td class="nowrap hide-3">'.$res['kpd'].'</td>
                  <td class="nowrap hide-1">'.nf($res['headshots']).'</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['hpercent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['hpercent'].'%</div>
                    </div>
                  </td>
                  <td class="nowrap hide-3">'.$res['hpk'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
        echo Pagination($total, $_GET['playerkills_page'] ?? 1, 30, 'playerkills_page', true, 'playerkills');

        if (!empty($_GET['ajax'])) exit;

        echo '</div>';

    }
}
?>

