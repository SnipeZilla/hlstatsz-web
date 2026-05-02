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

$asterisk = $g_options['DeleteDays'] ? ' *' : '';

if (empty($_GET['ajax']) || $_GET['ajax'] == 'maps') {

    $sortorder = $_GET['maps_sortorder'] ?? '';
    $sort      = $_GET['maps_sort'] ?? '';
    $sort2     = "kills";

    $col = array("map","kills","kpercent","deaths","dpercent","kpd","headshots","hpercent","hpk");
    if (!in_array($sort, $col)) {
        $sort      = "map";
        $sortorder = "DESC";
    }

    if ($sort == "kills") {
        $sort2 = "map";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['maps_page']) ? ((int)$_GET['maps_page'] - 1) * 30 : 0;

    $result = $db->query("
        WITH clan_kills AS (
            SELECT
                CASE WHEN f.map = '' THEN '(Unaccounted)' ELSE f.map END AS map,
                COUNT(*) AS kills,
                SUM(f.headshot = 1) AS headshots
            FROM hlstats_Events_Frags f
            JOIN hlstats_Players p ON p.playerId = f.killerId
            WHERE p.clan = $clan
            GROUP BY map
        ),
        clan_deaths AS (
            SELECT
                CASE WHEN f.map = '' THEN '(Unaccounted)' ELSE f.map END AS map,
                COUNT(*) AS deaths
            FROM hlstats_Events_Frags f
            JOIN hlstats_Players p ON p.playerId = f.victimId
            WHERE p.clan = $clan
            GROUP BY map
        ),
        combined AS (
            SELECT
                k.map,
                k.kills,
                d.deaths,
                k.headshots,
                IFNULL(k.kills / NULLIF(d.deaths, 0), 0) AS kpd,
                IFNULL(k.headshots / NULLIF(k.kills, 0), 0) AS hpk,
                ROUND(d.deaths / $realkills * 100, 2) AS dpercent,
                ROUND(k.kills / $realkills * 100, 2) AS kpercent,
                ROUND(k.headshots / $realheadshots * 100, 2) AS hpercent
            FROM clan_kills k
            INNER JOIN clan_deaths d ON d.map = k.map
        )
        SELECT
            *,
            COUNT(*) OVER() AS total_rows
        FROM combined
        ORDER BY
            $sort $sortorder,
            $sort2 $sortorder
        LIMIT 30 OFFSET $start;
    ");
    

    if ($db->num_rows($result)) {
        if (empty($_GET['ajax'])) {
        printSectionTitle('Map Performance'.$asterisk);

?>
<div id="maps">
<?php
}
?>
  <div  class="responsive-table">
  <table class="maps-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('map',$sort,$sortorder) ?>"><?= headerUrl('map', ['maps_sort','maps_sortorder'], 'maps') ?>Map</a></th>
        <th class="<?= isSorted('kills',$sort,$sortorder) ?>"><?= headerUrl('kills', ['maps_sort','maps_sortorder'], 'maps') ?>Kills</a></th>
        <th class="hide-2 meter-ratio <?= isSorted('kpercent',$sort,$sortorder) ?>"><?= headerUrl('kpercent', ['maps_sort','maps_sortorder'], 'maps') ?>Ratio</a></th>
        <th class="hide<?= isSorted('deaths',$sort,$sortorder) ?>"><?= headerUrl('deaths', ['maps_sort','maps_sortorder'], 'maps') ?>Deaths</a></th>
        <th class="hide-2 meter-ratio <?= isSorted('dpercent',$sort,$sortorder) ?>"><?= headerUrl('dpercent', ['maps_sort','maps_sortorder'], 'maps') ?>Ratio</a></th>
        <th class="hide-1<?= isSorted('kpd',$sort,$sortorder) ?>"><?= headerUrl('kpd', ['maps_sort','maps_sortorder'], 'maps') ?>K:D</a></th>
        <th class="hide<?= isSorted('headshots',$sort,$sortorder) ?>"><?= headerUrl('headshots', ['maps_sort','maps_sortorder'], 'maps') ?>Headshots</a></th>
       <th class="hide-2 meter-ratio <?= isSorted('hpercent',$sort,$sortorder) ?>"><?= headerUrl('hpercent', ['maps_sort','maps_sortorder'], 'maps') ?>Ratio</a></th>
        <th class="hide-3<?= isSorted('hpk',$sort,$sortorder) ?>"><?= headerUrl('hpk', ['maps_sort','maps_sortorder'], 'maps') ?>HS:K</a></th>
    </tr>
    <?php
            $i = 1+$start;

        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><a href="?mode=mapinfo&map='.$res['map'].'&game='.$game.'"><span class="hlstats-name">'.htmlspecialchars($res['map']).'</span></a></td>
                  <td class="nowrap">'.nf($res['kills']).'</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['kpercent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['kpercent'].'%</div>
                    </div>
                  <td class="nowrap hide">'.nf($res['deaths']).'</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['dpercent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['dpercent'].'%</div>
                    </div>
                  </td>
                  <td class="nowrap hide-1">'.$res['kpd'].'</td>
                  <td class="nowrap hide">'.nf($res['headshots']).'</td>
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
       echo Pagination($total, $_GET['maps_page'] ?? 1, 30, 'maps_page', true, 'maps');

  if (!empty($_GET['ajax'])) exit;
  ?>
</div>
<?php
    }
}
?>
