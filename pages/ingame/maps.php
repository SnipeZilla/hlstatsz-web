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

	// Player Details
	
	$player = valid_request(intval($_GET['player'] ?? 0), true);
	$uniqueid  = valid_request(strval($_GET['uniqueid'] ?? ''), false);
	$game = valid_request(strval($_GET['game'] ?? ''), false);
	
    if (!$player && $uniqueid) {
        if (!$game) {
            header('Location: ' . $g_options['scripturl'] . "&mode=search&st=uniqueid&q=$uniqueid");
            exit;
        }
    }

    if (!$player && $game) {
        $db->query("
            SELECT
                playerId
            FROM
                hlstats_PlayerUniqueIds
            WHERE
                uniqueId='$uniqueid'
                AND game='$game'
        ");        
        if ($db->num_rows() < 1) {
            error("No players found matching uniqueId '$uniqueid'");
        }
        list($player) = $db->fetch_row();
        $player = intval($player);
    }

    if ($player) {
        $db->query("SELECT game,
                    lastName
                    FROM hlstats_players
                    WHERE playerId = '$player'
                    LIMIT 1
                ");
        list($game,$pl_name) = $db->fetch_row();
    } else {
        error("No players found matching uniqueId");
    }

	$db->free_result();

	if (strlen($pl_name) > 10) {
		$pl_shortname = substr($pl_name, 0, 8) . '...';
	} else {
		$pl_shortname = $pl_name;
	}

	$pl_name = htmlspecialchars($pl_name, ENT_COMPAT);
	$pl_shortname = htmlspecialchars($pl_shortname, ENT_COMPAT);
	$pl_urlname = urlencode($playerdata['lastName']);

$db->query("
		SELECT
			COUNT(hlstats_Events_Frags.killerId)
		FROM
			hlstats_Events_Frags
		WHERE
			hlstats_Events_Frags.killerId = '$player'
			AND hlstats_Events_Frags.headshot = 1
	");

	list($realheadshots) = $db->fetch_row();

    $db->query("
		SELECT
			COUNT(hlstats_Events_Frags.killerId)
		FROM
			hlstats_Events_Frags
		WHERE
			hlstats_Events_Frags.killerId = '$player'
	");

	list($realkills) = $db->fetch_row();

    $db->query("
		SELECT
			COUNT(hlstats_Events_Frags.victimId)
		FROM
			hlstats_Events_Frags
		WHERE
			hlstats_Events_Frags.victimId = '$player'
	");

	list($realdeaths) = $db->fetch_row();

    $db->query("
		SELECT
			COUNT(hlstats_Events_Teamkills.killerId)
		FROM
			hlstats_Events_Teamkills
		WHERE
			hlstats_Events_Teamkills.killerId = '$player'
	");

	list($realteamkills) = $db->fetch_row();

	if (!isset($_GET['killLimit'])) {
		$killLimit = 1;
	} else {
		$killLimit = valid_request($_GET['killLimit'], true);
	}

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
        WITH map_data AS (
            SELECT
                IF(f.map = '', '(Unaccounted)', f.map) AS map,
                SUM(f.killerId = $player) AS kills,
                SUM(f.victimId = $player) AS deaths,
                IFNULL(
                    ROUND(
                        SUM(f.killerId = $player) /
                        IF(SUM(f.victimId = $player) = 0, 1, SUM(f.victimId = $player)),
                    2),
                '-') AS kpd,
                ROUND(SUM(f.killerId = $player) / $realkills * 100, 2) AS kpercent,
                ROUND(SUM(f.victimId = $player) / $realdeaths * 100, 2) AS dpercent,
                SUM(f.killerId = $player AND f.headshot = 1) AS headshots,
                IFNULL(
                    ROUND(
                        SUM(f.killerId = $player AND f.headshot = 1) /
                        SUM(f.killerId = $player),
                    2),
                '-') AS hpk,
                ROUND(
                    SUM(f.killerId = $player AND f.headshot = 1) /
                    $realheadshots * 100,
                2) AS hpercent
            FROM hlstats_Events_Frags f
            WHERE f.killerId = $player
               OR f.victimId = $player
            GROUP BY f.map
        )
        SELECT
            *,
            COUNT(*) OVER() AS total_rows
        FROM map_data
        ORDER BY
            $sort $sortorder,
            $sort2 $sortorder
        LIMIT 30 OFFSET $start;
    ");
           printSectionTitle($pl_name.'\'s Map Performance');

    if ($db->num_rows($result)) {
 
?>
<div id="maps">
<div class="responsive-table">
  <table class="maps-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('map',$sort,$sortorder) ?>"><?= headerUrl('map', ['maps_sort','maps_sortorder'], 'maps') ?>Map</a></th>
        <th class="<?= isSorted('kills',$sort,$sortorder) ?>"><?= headerUrl('kills', ['maps_sort','maps_sortorder'], 'maps') ?>Kills</a></th>
        <th class="meter-ratio <?= isSorted('kpercent',$sort,$sortorder) ?>"><?= headerUrl('kpercent', ['maps_sort','maps_sortorder'], 'maps') ?>Ratio</a></th>
        <th class="<?= isSorted('deaths',$sort,$sortorder) ?>"><?= headerUrl('deaths', ['maps_sort','maps_sortorder'], 'maps') ?>Deaths</a></th>
        <th class="meter-ratio <?= isSorted('dpercent',$sort,$sortorder) ?>"><?= headerUrl('dpercent', ['maps_sort','maps_sortorder'], 'maps') ?>Ratio</a></th>
        <th class="<?= isSorted('kpd',$sort,$sortorder) ?>"><?= headerUrl('kpd', ['maps_sort','maps_sortorder'], 'maps') ?>K:D</a></th>
        <th class="<?= isSorted('headshots',$sort,$sortorder) ?>"><?= headerUrl('headshots', ['maps_sort','maps_sortorder'], 'maps') ?>Headshots</a></th>
       <th class="meter-ratio <?= isSorted('hpercent',$sort,$sortorder) ?>"><?= headerUrl('hpercent', ['maps_sort','maps_sortorder'], 'maps') ?>Ratio</a></th>
        <th class="<?= isSorted('hpk',$sort,$sortorder) ?>"><?= headerUrl('hpk', ['maps_sort','maps_sortorder'], 'maps') ?>HS:K</a></th>
    </tr>
    <?php

            $i = 1+$start;

        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><a href="?mode=mapinfo&map='.$res['map'].'&game='.$game.'&player='.$player.'"><span class="hlstats-name">'.htmlspecialchars($res['map']).'</span></a></td>
                  <td class="nowrap">'.nf($res['kills']).'</td>
                  <td class="nowrap">'.$res['kpercent'].'%</td>
                  <td class="nowrap">'.nf($res['deaths']).'</td>
                  <td class="nowrap">'.$res['dpercent'].'%</td>
                  <td class="nowrap">'.$res['kpd'].'</td>
                  <td class="nowrap">'.nf($res['headshots']).'</td>
                  <td class="nowrap">'.$res['hpercent'].'%</td>
                  <td class="nowrap">'.$res['hpk'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($total, $_GET['maps_page'] ?? 1, 30, 'maps_page', false, 'maps');

  ?>
</div>
<?php } else { echo '<p>No map statistics found for this player.</p>'; } ?>
<div>
<div style="float:left;">
   <a href="?mode=accuracy&amp;game=<?=$game?>&amp;player=<?=$player?>">&larr;&nbsp;Accuracy</a>
</div>
</div>
