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

	// Map Details
	if (!$game) {
        error("No such game.");
	}
	$map = valid_request($_GET['map'] ?? '', false) or error('No map specified.');

    $total      = 0;
    $totalkills = 0;

    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';
    $sort2     = "headshots";

    $col = array("rank_position","killerName","frags","headshots","hpk");
    if (!in_array($sort, $col)) {
        $sort      = "rank_position";
        $sortorder = "ASC";
    }

    // Secondary sort
    if ($sort == "frags") {
        $sort2 = "headshots";
    } elseif ($sort == "headshots") {
        $sort2 = "frags";
    } elseif ($sort == "hpk") {
        $sort2 = "frags";
    } elseif ($sort == "killerName") {
        $sort2 = "frags";
    } else {
        // rank_position
        $sort2 = "frags";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;

    $resultCount = $db->query("
        SELECT
            COUNT(DISTINCT hlstats_Events_Frags.killerId),
            SUM(hlstats_Events_Frags.map='$map')
        FROM
            hlstats_Events_Frags,
            hlstats_Servers
        WHERE
            hlstats_Servers.serverId = hlstats_Events_Frags.serverId
            AND hlstats_Events_Frags.map='$map'
            AND hlstats_Servers.game='$game'
    ");

    list($numitems, $totalkills) = $db->fetch_row($resultCount);

    $result = $db->query("
        WITH Base AS (
            SELECT
                f.killerId,
                p.lastName AS killerName,
                p.flag,
                COUNT(*) AS frags,
                SUM(f.headshot = 1) AS headshots,
                IFNULL(SUM(f.headshot = 1) / COUNT(*), 0) AS hpk
            FROM hlstats_Events_Frags AS f
            INNER JOIN hlstats_Players AS p
                ON p.playerId = f.killerId
            WHERE f.map = '$map'
                AND p.game = '$game'
                AND p.hideranking <> 1
            GROUP BY f.killerId, p.lastName, p.flag
        ),
        Ranked AS (
            SELECT
                *,
                RANK() OVER (ORDER BY frags DESC, headshots DESC) AS rank_position,
                COUNT(*) OVER () AS total_rows
            FROM Base
        )
        SELECT *
        FROM Ranked
        ORDER BY $sort $sortorder,
                 $sort2 $sortorder,
                 killerName ASC
        LIMIT 30 OFFSET $start
    ");

if (!is_ajax()) {
?>


	<?php printSectionTitle('Map Details'); ?>
<div class="hlstats-cards-grid">
<section class="hlstats-section hlstats-card">
  <div class="hlstats-pname"><?= $map ?></div>
  <div class="hlstats-card-foot">From a total of <strong><?php echo nf(intval($totalkills)); ?></strong> kills</div>
</section>

<section class="hlstats-section hlstats-card">
<div style="margin: 0 auto;">

<?php // figure out URL and absolute path of image
	if ($mapimg = getImage("/games/$realgame/maps/$map"))
	{
		$mapimg = $mapimg['url'];
	}
	elseif ($mapimg = getImage("/games/$game/maps/$map"))
	{
		$mapimg = $mapimg['url'];
	}
	else
	{
		$mapimg = IMAGE_PATH."/nomap.png";
	}
	
	if ($mapimg)
	{
		echo "<a href=\"" . $mapimg . "\" rel=\"boxed\" title=\"$map\"><img src=\"$mapimg\" alt=\"$map\" style=\"max-width:100%;\"/></a>";
	}

	if ($g_options['map_dlurl'])
	{
		$map_dlurl = str_replace("%GAME%", $game, $g_options['map_dlurl']);
		if (substr($map_dlurl, -1) !== '/') {
			$map_dlurl .= '/';
		}
		$file=$map_dlurl.$map.'.bsp.bz2';
		$exist=stripos( get_headers($file)[0], "200 OK" )?true:false;
		if ( !$exist ) {
			$file=$map_dlurl.$map.'.bsp';
			$exist=stripos( get_headers($file)[0], "200 OK" )?true:false;
		}

		if ($exist) {
			echo "<p><a href=\"$file\">🔗 Download this map</a></p>";
		}
	}

	//if ($heatmap)
	//{
	//	echo "<a href=\"" . $heatmap['url'] . "\" rel=\"boxed\" title=\"Heatmap: $map\"><br /><img src=\"" . $heatmapthumb['url'] . "\" alt=\"$map\" /></a>";
	//}
?>
</div>

</section>
</div>

<?php
}

        if (!is_ajax()) {
        ?>
<div id="mapinfo">

<?php
}
if ($db->num_rows($result)) {
?>
<div  class="responsive-table">
  <table class="maps-table">
    <tr>
        <th class="hlstats-ranking nowrap<?= isSorted('rank_position',$sort,$sortorder) ?>"><?= headerUrl('rank_position', ['sort','sortorder'], 'mapinfo') ?>Rank</a></th>
        <th class="hlstats-main-description left<?= isSorted('killerName',$sort,$sortorder) ?>"><?= headerUrl('killerName', ['sort','sortorder'], 'mapinfo') ?>Player</a></th>
        <th class="<?= isSorted('frags',$sort,$sortorder) ?>"><?= headerUrl('frags', ['sort','sortorder'], 'mapinfo') ?>Kills</a></th>
        <th class="hide<?= isSorted('headshots',$sort,$sortorder) ?>"><?= headerUrl('headshots', ['sort','sortorder'], 'mapinfo') ?>Headshots</a></th>
        <th class="hide-1<?= isSorted('hpk',$sort,$sortorder) ?>"><?= headerUrl('hpk', ['sort','sortorder'], 'mapinfo') ?>HS:K</a></th>
    </tr>
    <?php
        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            echo '<tr>
                  <td class="nowrap right">'.$res['rank_position'].'</td>
                  <td class="hlstats-main-description left">';
            if ($g_options['countrydata']) {
                echo '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" alt="'.$res['flag'].'"></span>';
            }
            echo '<a href="?mode=playerinfo&amp;player='.$res['killerId'].'"><span class="hlstats-name">'.htmlspecialchars(html_entity_decode($res['killerName'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_COMPAT).'&nbsp;</span></a></td>
                  <td class="nowrap">'.nf($res['frags']).'</td>
                  <td class="nowrap hide">'.nf($res['headshots']).'</td>
                  <td class="nowrap hide-1">'.round($res['hpk'], 2).'</td>
                  </tr>';
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($total, $_GET['page'] ?? 1, 30, 'page');

  if (is_ajax()) exit;
}
  ?>
</div>

<div class="hlstats-note">
    <a href="<?php echo $g_options['scripturl'] . "?mode=maps&amp;game=$game"; ?>">&larr;&nbsp;Map Statistics</a>
</div>
<script>
Fetch.ini('mapinfo');
</script>

<?php if ($g_options['DeleteDays']) { ?>
<div class="hlstats-note">
    Items above are generated from the most recent <?= $g_options['DeleteDays'] ?> days of activity.
</div>
<?php } ?>
