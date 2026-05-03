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

// Map Statistics
	if (!$game) {
        error("No such game.");
	}
	$db->query("
	 	SELECT
			SUM(hlstats_Maps_Counts.kills),
			SUM(hlstats_Maps_Counts.headshots)
		FROM
			hlstats_Maps_Counts
		WHERE
			hlstats_Maps_Counts.game = '$game'
	");

	list($realkills, $realheadshots) = $db->fetch_row();


    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';
    $sort2     = "headshots";

    $col = array("map","kills","kpercent","headshots","hpercent");
    if (!in_array($sort, $col)) {
        $sort      = "kills";
        $sortorder = "DESC";
    }

    if ($sort == "headshots") {
        $sort2 = "kills";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;

    // kpercent/hpercent are monotonic in kills/headshots, so map them to the real
    // columns so the ORDER BY can use the (game, kills, ...) / (game, headshots, ...) indexes.
    $sortColMap = [
        'map'       => 'map',
        'kills'     => 'kills',
        'kpercent'  => 'kills',
        'headshots' => 'headshots',
        'hpercent'  => 'headshots',
    ];
    $sortCol  = $sortColMap[$sort];
    $sort2Col = $sortColMap[$sort2];

    $db->query("
        SELECT COUNT(*)
        FROM hlstats_Maps_Counts
        WHERE game = '$game'
    ");
    list($total) = $db->fetch_row();
    $db->free_result();

    $result = $db->query("
        SELECT
            map,
            kills,
            headshots
        FROM hlstats_Maps_Counts
        WHERE game = '$game'
        ORDER BY
            $sortCol $sortorder,
            $sort2Col $sortorder
        LIMIT 30 OFFSET $start
    ");
    
if (!is_ajax()) {
    printSectionTitle('Map Statistics');
?>

<div class="hlstats-cards-grid">
<section class="hlstats-section hlstats-card">
  <div class="hlstats-card-foot">From a total of <strong><?php echo nf($realkills); ?></strong> kills with <strong><?php echo nf($realheadshots); ?></strong> headshots</div>
</section>
</div>

<div id="mapinfo">

<?php
}
if ($total) {
?>
<div  class="responsive-table">
  <table class="maps-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-column left<?= isSorted('map',$sort,$sortorder) ?>"><?= headerUrl('map', ['sort','sortorder'], 'mapinfo') ?>Map</a></th>
        <th class="<?= isSorted('kills',$sort,$sortorder) ?>"><?= headerUrl('kills', ['sort','sortorder'], 'mapinfo') ?>Kills</a></th>
        <th class="meter-ratio hide-1<?= isSorted('kpercent',$sort,$sortorder) ?>"><?= headerUrl('kpercent', ['sort','sortorder'], 'mapinfo') ?>Ratio</a></th>
        <th class="hide<?= isSorted('kills',$sort,$sortorder) ?>"><?= headerUrl('kills', ['sort','sortorder'], 'mapinfo') ?>Headshots</a></th>
        <th class="meter-ratio hide-2<?= isSorted('hpercent',$sort,$sortorder) ?>"><?= headerUrl('hpercent', ['sort','sortorder'], 'mapinfo') ?>Ratio</a></th>
    </tr>
    <?php

        $i = 1+$start;

        while ($res = $db->fetch_array($result))
        {
            $mapName  = $res['map'] === '' ? '(Unaccounted)' : $res['map'];
            $kpercent = $realkills > 0      ? round($res['kills']     / $realkills     * 100, 2) : 0;
            $hpercent = $realheadshots > 0  ? round($res['headshots'] / $realheadshots * 100, 2) : 0;
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="left"><span class="hlstats-name"><a href="?mode=mapinfo&amp;map='.$mapName.'&amp;game='.$game.'"></span><span class="hlstats-name">'.$mapName.'</span></a></td>
                  <td class="nowrap">'.nf($res['kills']).'</td>
                  <td class="nowrap hide-1">
                      <div class="meter-container">
                        <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$kpercent.'"></meter>
                        <div class="meter-value" id="meterText">'.$kpercent.'%</div>
                      </div>
                  </td>
                  <td class="nowrap hide">'.nf($res['headshots']).'</td>
                  <td class="nowrap hide-2">
                      <div class="meter-container">
                        <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$hpercent.'"></meter>
                        <div class="meter-value" id="meterText">'.$hpercent.'%</div>
                      </div>
                  </td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($total, $_GET['page'] ?? 1, 30, 'page');

  if (is_ajax()) exit;
} else { echo '<p class="hlstats-no-data"><em>Not enough data</em></p>'; }
  ?>
</div>
<script>
Fetch.ini('mapinfo');
</script>