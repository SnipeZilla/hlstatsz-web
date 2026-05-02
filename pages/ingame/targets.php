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
    $has = 0;
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

    if (!$game && $player) {
        $db->query("SELECT game
                    FROM hlstats_players
                    WHERE playerId = '$player'
                    LIMIT 1
                ");
        list($game) = $db->fetch_row();
    }

	
	$db->query("
		SELECT
			hlstats_Players.playerId,
			hlstats_Players.lastName,
			hlstats_Players.game
		FROM
			hlstats_Players
		WHERE
			playerId='$player'
	");

	if ($db->num_rows() != 1) {
		error("No such player '$player'.");
	}

	$playerdata = $db->fetch_array();
	$db->free_result();

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

    if (!isset($_GET['killLimit'])) {
        $killLimit = 1;
    } else {
        $killLimit = valid_request($_GET['killLimit'], true);
    }



	$pl_name = $playerdata['lastName'];
	if (strlen($pl_name) > 10) {
		$pl_shortname = substr($pl_name, 0, 8) . '...';
	} else {
		$pl_shortname = $pl_name;
	}

	$pl_name = htmlspecialchars($pl_name, ENT_COMPAT);
	$pl_shortname = htmlspecialchars($pl_shortname, ENT_COMPAT);
	$pl_urlname = urlencode($playerdata['lastName']);
printSectionTitle($pl_name.'\'s Targets');
    list($realgame,$realname) = getRealGame($game);
    $result = $db->query("
        SELECT
            hlstats_Weapons.code,
            hlstats_Weapons.name
        FROM
            hlstats_Weapons
        WHERE
            hlstats_Weapons.game = '$game'
    ");

    while ($rowdata = $db->fetch_row($result)) {
        $code = $rowdata[0];
        $fname[strToLower($code)] = htmlspecialchars($rowdata[1]);
    }

    $sortorder = $_GET['weap_sortorder'] ?? '';
    $sort      = $_GET['weap_sort'] ?? '';
    $sort2     = "kills";

    $col = array("weapon","modifier","kills","headshots","kpercent","hpercent","hpk");
    if (!in_array($sort, $col)) {
        $sort      = "kills";
        $sortorder = "DESC";
    }

    if ($sort == "kills") {
        $sort2 = "headshots";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['weap_page']) ? ((int)$_GET['weap_page'] - 1) * 10 : 0;

    $result = $db->query("
                WITH frag_data AS (
                    SELECT
                        f.weapon,
                        IFNULL(w.modifier, 1.00) AS modifier,
                        COUNT(f.weapon) AS kills,
                        ROUND(COUNT(f.weapon) / $realkills * 100, 2) AS kpercent,
                        SUM(f.headshot = 1) AS headshots,
                        ROUND(SUM(f.headshot = 1) / IF(COUNT(f.weapon) = 0, 1, COUNT(f.weapon)), 2) AS hpk,
                        ROUND(SUM(f.headshot = 1) / $realheadshots * 100, 2) AS hpercent
                    FROM hlstats_Events_Frags f
                    LEFT JOIN hlstats_Weapons w
                        ON w.code = f.weapon
                    WHERE
                        f.killerId = $player
                        AND (w.game = '$game' OR w.weaponId IS NULL)
                    GROUP BY
                        f.weapon, w.modifier
                )
                SELECT
                    *,
                    COUNT(*) OVER() AS total_rows
                FROM frag_data
                ORDER BY
                    $sort $sortorder,
                    $sort2 $sortorder
                LIMIT 10 OFFSET $start;
    ");

    if ($db->num_rows($result)) {
       $has++;

        printSectionTitle('Weapon Usage');

?>
<div id="weapons">
<div  class="responsive-table">
  <table class="weapons-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('weapon',$sort,$sortorder) ?>"><?= headerUrl('weapon', ['weap_sort','weap_sortorder'], 'weapons') ?>Weapons</a></th>
        <th class="<?= isSorted('modifier',$sort,$sortorder) ?>"><?= headerUrl('modifier', ['weap_sort','weap_sortorder'], 'weapons') ?>Modifier</a></th>
        <th class="<?= isSorted('kills',$sort,$sortorder) ?>"><?= headerUrl('kills', ['weap_sort','weap_sortorder'], 'weapons') ?>Kills</a></th>
        <th class="meter-ratio <?= isSorted('kpercent',$sort,$sortorder) ?>"><?= headerUrl('kpercent', ['weap_sort','weap_sortorder'], 'weapons') ?>Ratio</a></th>
        <th class="<?= isSorted('headshots',$sort,$sortorder) ?>"><?= headerUrl('headshots', ['weap_sort','weap_sortorder'], 'weapons') ?>Headshots</a></th>
        <th class="meter-ratio <?= isSorted('hpercent',$sort,$sortorder) ?>"><?= headerUrl('hpercent', ['weap_sort','weap_sortorder'], 'weapons') ?>Ratio</a></th>
        <th class="<?= isSorted('hpk',$sort,$sortorder) ?>"><?= headerUrl('hpk', ['weap_sort','weap_sortorder'], 'weapons') ?>HS:K</a></th>
    </tr>
    <?php

        $i = 1+$start;

        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            $weapon = strtolower($res['weapon']);
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
                  <td class="hlstats-main-description left"><a href="?mode=weaponinfo&weapon='.$res['weapon'].'&game='.$game.'&player='.$player.'"><span class="hlstats-image">'.$weapimg.'</span></a></td>
                  <td class="nowrap">'.$res['modifier'].' times</td>
                  <td class="nowrap">'.nf($res['kills']).'</td>
                  <td class="nowrap">'.$res['kpercent'].'%</td>
                  <td class="nowrap">'.nf($res['headshots']).'</td>
                  <td class="nowrap">'.$res['hpercent'].'%</td>
                  <td class="nowrap">'.$res['hpk'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($total, $_GET['weap_page'] ?? 1, 10, 'weap_page', false, 'weapons');

  ?>
</div>
<?php
    }

?>


<!-- Begin of StatsMe Addon 1.0 by JustinHoMi@aol.com -->
<?php
    ob_flush();
    flush();

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


    if ($db->num_rows($result)) {
            $has++;
            printSectionTitle('Weapon Statistics');


?>
<div id="statsme">
<div class="responsive-table">
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
                $weapimg = '<span class="hlstats-name">' . (!empty($fname[$weapon])) ? $fname[$weapon] : ucwords(preg_replace('/_/', ' ', $weapon)) . '</span>';
            }
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><a href="?mode=weaponinfo&weapon='.$weapon.'&game='.$game.'&player='.$player.'"><span class="hlstats-image">'.$weapimg.'</span></a></td>
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
    }

?>



<?php
    ob_flush();
    flush();

    $sortorder = $_GET['sm2_sortorder'] ?? '';
    $sort      = $_GET['sm2_sort'] ?? '';
    $sort2     = "smweapon";

    $col = array("smweapon","smhits","smleft","smmiddle","smright");
    if (!in_array($sort, $col)) {
        $sort      = "smhits";
        $sortorder = "DESC";
    }

    if ($sort == "smweapon") {
        $sort2 = "smhits";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['sm2_page']) ? ((int)$_GET['sm2_page'] - 1) * 10 : 0;

    $query = "
        WITH sm2 AS (
            SELECT
                s.weapon AS smweapon,
                SUM(s.head)      AS smhead,
                SUM(s.chest)     AS smchest,
                SUM(s.stomach)   AS smstomach,
                SUM(s.leftarm)   AS smleftarm,
                SUM(s.rightarm)  AS smrightarm,
                SUM(s.leftleg)   AS smleftleg,
                SUM(s.rightleg)  AS smrightleg,
        
                -- total hits
                SUM(s.head)
                + SUM(s.chest)
                + SUM(s.stomach)
                + SUM(s.leftarm)
                + SUM(s.rightarm)
                + SUM(s.leftleg)
                + SUM(s.rightleg) AS smhits,
        
                -- left side %
                IFNULL(
                    ROUND(
                        (SUM(s.leftarm) + SUM(s.leftleg)) /
                        NULLIF(
                            SUM(s.head) + SUM(s.chest) + SUM(s.stomach)
                            + SUM(s.leftarm) + SUM(s.rightarm)
                            + SUM(s.leftleg) + SUM(s.rightleg),
                        0) * 100,
                    1),
                0.0) AS smleft,
        
                -- right side %
                IFNULL(
                    ROUND(
                        (SUM(s.rightarm) + SUM(s.rightleg)) /
                        NULLIF(
                            SUM(s.head) + SUM(s.chest) + SUM(s.stomach)
                            + SUM(s.leftarm) + SUM(s.rightarm)
                            + SUM(s.leftleg) + SUM(s.rightleg),
                        0) * 100,
                    1),
                0.0) AS smright,
        
                -- middle %
                IFNULL(
                    ROUND(
                        (SUM(s.head) + SUM(s.chest) + SUM(s.stomach)) /
                        NULLIF(
                            SUM(s.head) + SUM(s.chest) + SUM(s.stomach)
                            + SUM(s.leftarm) + SUM(s.rightarm)
                            + SUM(s.leftleg) + SUM(s.rightleg),
                        0) * 100,
                    1),
                0.0) AS smmiddle
            FROM hlstats_Events_Statsme2 s
            WHERE s.PlayerId = $player
            GROUP BY s.weapon
            HAVING smhits > 0
        )
        SELECT
            *,
            COUNT(*) OVER() AS total_rows
        FROM sm2
        ORDER BY
            $sort $sortorder,
            $sort2 $sortorder
        LIMIT 10 OFFSET $start;
    ";
    $result = $db->query($query);
    if ($db->num_rows($result)) {
        $has++;
        printSectionTitle('Weapon Targets');
        

?>
<div class="responsive-table">
  <table class="statsme2-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('smweapon',$sort,$sortorder) ?>"><?= headerUrl('smweapon', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>Weapon</a></th>
        <th class="<?= isSorted('smhits',$sort,$sortorder) ?>"><?= headerUrl('smhits', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>Hits</a></th>
        <th class="<?= isSorted('smhead',$sort,$sortorder) ?>"><?= headerUrl('smhead', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>Head</a></th>
        <th class="<?= isSorted('smchest',$sort,$sortorder) ?>"><?= headerUrl('smchest', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>Chest</a></th>
        <th class="<?= isSorted('smstomach',$sort,$sortorder) ?>"><?= headerUrl('smstomach', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>Stomach</a></th>
        <th class="<?= isSorted('smleftarm',$sort,$sortorder) ?>"><?= headerUrl('smleftarm', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>L. Arm</a></th>
        <th class="<?= isSorted('smrightarm',$sort,$sortorder) ?>"><?= headerUrl('smrightarm', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>R. Arm</a></th>
        <th class="<?= isSorted('smleftleg',$sort,$sortorder) ?>"><?= headerUrl('smleftleg', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>L. Leg</a></th>
        <th class="<?= isSorted('smrightleg',$sort,$sortorder) ?>"><?= headerUrl('smrightleg', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>R. Leg</a></th>
        <th class="<?= isSorted('smleft',$sort,$sortorder) ?>"><?= headerUrl('smleft', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>Left</a></th>
        <th class="<?= isSorted('smmiddle',$sort,$sortorder) ?>"><?= headerUrl('smmiddle', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>Middle</a></th>
        <th class="<?= isSorted('smright',$sort,$sortorder) ?>"><?= headerUrl('smright', ['sm2_sort','sm2_sortorder'], 'statsme2') ?>Right</a></th>
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
                $weapimg = '<span class="hlstats-name">' . (!empty($fname[$weapon])) ? $fname[$weapon] : ucwords(preg_replace('/_/', ' ', $weapon)) . '</span>';
            }
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><a href="javascript:switch_weapon(\''.$res['smweapon'].'\');" onclick="switch_weapon(\''.$res['smweapon'].'\');return false;"><span class="hlstats-image">'.$weapimg.'</span></a></td>
                  <td class="nowrap">'.$res['smhits'].' times</td>
                  <td class="nowrap">'.$res['smhead'].' times</td>
                  <td class="nowrap">'.$res['smchest'].' times</td>
                  <td class="nowrap">'.$res['smstomach'].' times</td>
                  <td class="nowrap">'.$res['smleftarm'].' times</td>
                  <td class="nowrap">'.$res['smrightarm'].' times</td>
                  <td class="nowrap">'.$res['smleftleg'].' times</td>
                  <td class="nowrap">'.$res['smrightleg'].'</td>
                  <td class="nowrap">'.$res['smleft'].'</td>
                  <td class="nowrap">'.$res['smmiddle'].'</td>
                  <td class="nowrap">'.$res['smright'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($total, $_GET['sm2_page'] ?? 1, 10, 'sm2_page', false, 'statsme2');

}

if (!$has) {
    echo '<p>No target statistics found for this player.</p>';
}
  ?>


<div>
<div style="float:left;">
   <a href="?mode=weapons&amp;game=<?=$game?>&amp;player=<?=$player?>">&larr;&nbsp;Weapon Statistics</a>
</div>
<div style="float:right;">
    <a href="?mode=accuracy&amp;game=<?=$game?>&amp;player=<?=$player?>">Accuracy Statistics&nbsp;&rarr;</a>
</div>
</div>
