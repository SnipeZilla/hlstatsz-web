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
	
	$pl_name = $playerdata['lastName'];
	if (strlen($pl_name) > 10) {
		$pl_shortname = substr($pl_name, 0, 8) . '...';
	} else {
		$pl_shortname = $pl_name;
	}

	$pl_name = htmlspecialchars($pl_name, ENT_COMPAT);
	$pl_shortname = htmlspecialchars($pl_shortname, ENT_COMPAT);
	$pl_urlname = urlencode($playerdata['lastName']);

	$game = $playerdata['game'];
	$db->query("SELECT name FROM hlstats_Games WHERE code='$game'");
	if ($db->num_rows() != 1) {
		$gamename = ucfirst($game);
	} else {
		list($gamename) = $db->fetch_row();
	}

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



	if (!isset($_GET['killLimit'])) {
		$killLimit = 1;
	} else {
		$killLimit = valid_request($_GET['killLimit'], true);
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

printSectionTitle($pl_name.'\'s Weapons');

    if ($db->num_rows($result)) {
?>
<div id="weapons">
<div class="responsive-table">
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
                  <td class="hlstats-main-description left"><a href="?mode=weaponinfo&weapon='.$res['weapon'].'&game='.$game.'"><span class="hlstats-image">'.$weapimg.'</span></a></td>
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
<?php } else { echo '<p>No weapon statistics found for this player.</p>'; } ?>

<div>
<div style="float:left;">
  <a href="?mode=kills&amp;game=<?=$game?>&amp;player=<?=$player?>">&larr;&nbsp;Kill Statistics</a>
</div>
<div style="float:right;">
    <a href="?mode=targets&amp;game=<?=$game?>&amp;player=<?=$player?>">Target Statistics&nbsp;&rarr;</a>
</div>
</div>
