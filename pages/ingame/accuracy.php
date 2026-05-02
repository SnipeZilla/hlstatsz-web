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
    $realgame = $_SESSION['realgame'];
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
    } else {
        error("No players found matching uniqueId");
    }

	$playerdata = $db->fetch_array();
	$db->free_result();
	
	$pl_name = $playerdata['lastName'];
	if (strlen($pl_name) > 10) {
		$pl_shortname = substr($pl_name, 0, 8) . '...';
	} else {
		$pl_shortname = $pl_name;
	}

	$pl_name = htmlspecialchars($pl_name, ENT_COMPAT);
	$pl_shortname = htmlspecialchars($pl_shortname, ENT_COMPAT);
	$pl_urlname = urlencode($playerdata['lastName']);



    $sortorder = $_GET['sm_sortorder'] ?? '';
    $sort      = $_GET['sm_sort'] ?? '';
    $sort2     = "smweapon";

    $col = array("smweapon","smkills","smhits","smshots","smheadshots","smdeaths","smdamage","smdhr","smkdr","smaccuracy","smspk");
    if (!in_array($sort, $col)) {
        $sort      = "smkills";
        $sortorder = "DESC";
    }

    if ($sort == "smweapon") {
        $sort2 = "smkills";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['sm_page']) ? ((int)$_GET['sm_page'] - 1) * 10 : 0;


    $result = $db->query("

    WITH sm_data AS (
        SELECT
            s.weapon AS smweapon,
            SUM(s.kills) AS smkills,
            SUM(s.hits) AS smhits,
            SUM(s.shots) AS smshots,
            SUM(s.headshots) AS smheadshots,
            SUM(s.deaths) AS smdeaths,
            SUM(s.damage) AS smdamage,
            ROUND(SUM(s.damage) / IF(SUM(s.hits) = 0, 1, SUM(s.hits)), 1) AS smdhr,
            SUM(s.kills) / IF(SUM(s.deaths) = 0, 1, SUM(s.deaths)) AS smkdr,
            ROUND(SUM(s.hits) / SUM(s.shots) * 100, 1) AS smaccuracy,
            ROUND(
                IF(SUM(s.kills) = 0, 0, SUM(s.shots)) /
                IF(SUM(s.kills) = 0, 1, SUM(s.kills)),
            1) AS smspk
        FROM hlstats_Events_Statsme s
        WHERE s.PlayerId = $player
        GROUP BY s.weapon
        HAVING SUM(s.shots) > 0
    )
    SELECT
        *,
        COUNT(*) OVER() AS total_rows
    FROM sm_data
    ORDER BY
        $sort $sortorder,
        $sort2 $sortorder
    LIMIT 10 OFFSET $start;
    ");

	printSectionTitle($pl_name.'\'s weapon Performance');

    if ($db->num_rows($result)) {

?>
<div id="statsme">
<div  class="responsive-table">
  <table class="statsme-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('smweapon',$sort,$sortorder) ?>"><?= headerUrl('smweapon', ['sm_sort','sm_sortorder'], 'statsme') ?>Weapon</a></th>
        <th class="<?= isSorted('smshots',$sort,$sortorder) ?>"><?= headerUrl('smshots', ['sm_sort','sm_sortorder'], 'statsme') ?>Shots</a></th>
        <th class="<?= isSorted('smhits',$sort,$sortorder) ?>"><?= headerUrl('smhits', ['sm_sort','sm_sortorder'], 'statsme') ?>Hits</a></th>
        <th class="<?= isSorted('smdamage',$sort,$sortorder) ?>"><?= headerUrl('smdamage', ['sm_sort','sm_sortorder'], 'statsme') ?>Damage</a></th>
        <th class="<?= isSorted('smheadshots',$sort,$sortorder) ?>"><?= headerUrl('smheadshots', ['sm_sort','sm_sortorder'], 'statsme') ?>Headshots</a></th>
        <th class="<?= isSorted('smkills',$sort,$sortorder) ?>"><?= headerUrl('smkills', ['sm_sort','sm_sortorder'], 'statsme') ?>Kills</a></th>
        <th class="<?= isSorted('smkdr',$sort,$sortorder) ?>"><?= headerUrl('smkdr', ['sm_sort','sm_sortorder'], 'statsme') ?>K:D</a></th>
        <th class="<?= isSorted('smaccuracy',$sort,$sortorder) ?>"><?= headerUrl('smaccuracy', ['sm_sort','sm_sortorder'], 'statsme') ?>Accuracy</a></th>
        <th class="<?= isSorted('smdhr',$sort,$sortorder) ?>"><?= headerUrl('smdhr', ['sm_sort','sm_sortorder'], 'statsme') ?>Damage:Hit</a></th>
        <th class="<?= isSorted('smspk',$sort,$sortorder) ?>"><?= headerUrl('smspk', ['sm_sort','sm_sortorder'], 'statsme') ?>Shots:Kills</a></th>
    </tr>
    <?php

        $i = 1+$start;

        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            $weapon = strtolower($res['smweapon']);
            $image = getImage("/games/$realgame/weapons/" . $weapon);

            if ($image) {
                $weapimg = '<img src="' . $image['url'] . '" ' . $image['size'] . ' alt="' . $fname[$weapon] . '" title="' . $fname[$weapon] . '" />';
            } elseif ($image = getImage("/games/$realgame/weapons/" . $weapon)) {
                $weapimg = '<img src="' . $image['url'] . '" ' . $image['size'] . ' alt="' . $fname[$weapon] . '" title="' . $fname[$weapon] . '" />';
            } else {
                $weapimg = '<span class="hlstats-name">' . ucwords(preg_replace('/_/', ' ', $weapon)) . '</span>';
            }
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><a href="?mode=weaponinfo&weapon='.$weapon.'&game='.$game.'"><span class="hlstats-image">'.$weapimg.'</span></a></td>
                  <td class="nowrap">'.$res['smshots'].' times</td>
                  <td class="nowrap">'.$res['smhits'].'</td>
                  <td class="nowrap">'.$res['smdamage'].'</td>
                  <td class="nowrap">'.$res['smheadshots'].'</td>
                  <td class="nowrap">'.$res['smkills'].'</td>
                  <td class="nowrap">'.$res['smkdr'].'</td>
                  <td class="nowrap">'.$res['smaccuracy'].'</td>
                  <td class="nowrap">'.$res['smdhr'].'</td>
                  <td class="nowrap">'.$res['smspk'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($total, $_GET['sm_page'] ?? 1, 10, 'sm_page', false, 'statsme');

  ?>
</div>
<?php

} else { echo '<p>No accuracy statistics found for this player.</p>'; }
?>

<div>
<div style="float:left;">
   <a href="?mode=targets&amp;game=<?=$game?>&amp;player=<?=$player?>">&larr;&nbsp;Target Statistics</a>
</div>
<div style="float:right;">
    <a href="?mode=maps&amp;game=<?=$game?>&amp;player=<?=$player?>">Map Statistics&nbsp;&rarr;</a>
</div>
</div>