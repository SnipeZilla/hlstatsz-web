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

    // Weapon Statistics
	$db->query
	("
		SELECT
			hlstats_Games.name
		FROM
			hlstats_Games
		WHERE
			hlstats_Games.code = '$game'
	");
	if ($db->num_rows() < 1) error("No such game '$game'.");
	list($gamename) = $db->fetch_row();
	$db->free_result();
    
	$result = $db->query
	("
		SELECT
			hlstats_Weapons.code,
			hlstats_Weapons.name
		FROM
			hlstats_Weapons
		WHERE
			hlstats_Weapons.game = '$game'
	");
	while ($rowdata = $db->fetch_row($result))
	{ 
		$code = $rowdata[0];
		$fname[strToLower($code)] = $rowdata[1];
	}

    $sortorder = $_GET['weap_sortorder'] ?? '';
    $sort      = $_GET['weap_sort'] ?? '';
    $sort2     = "headshots";

    $col = array("weapon","modifier","kills","kpercent","headshots","hpercent","hpk");
    if (!in_array($sort, $col)) {
        $sort      = "kills";
        $sortorder = "DESC";
    }

    if ($sort == "headshots") {
        $sort2 = "kills";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['weap_page']) ? ((int)$_GET['weap_page'] - 1) * 30 : 0;

    $db->query
    ("
        SELECT
            COALESCE(SUM(kills), 0) AS realkills,
            COALESCE(SUM(headshots), 0) AS realheadshots
        FROM hlstats_Weapons
        WHERE game = '$game';
    ");
    list($realkills, $realheadshots) = $db->fetch_row();

    $db->query
    ("
        SELECT COUNT(*) AS total
        FROM hlstats_Weapons
        WHERE game = '$game'
          AND kills > 0;
    ");
    list($total) = $db->fetch_row();

    $result = $db->query
    ("
    SELECT
        w.code AS weapon,
        w.kills,
        ROUND(w.kills / $realkills * 100, 2) AS kpercent,
        w.headshots,
        ROUND(w.headshots / IF(w.kills = 0, 1, w.kills), 2) AS hpk,
        ROUND(w.headshots / $realheadshots * 100, 2) AS hpercent,
        w.modifier
    FROM hlstats_Weapons AS w
    WHERE
        w.game = '$game'
        AND w.kills > 0
    GROUP BY
        w.weaponId
    ORDER BY
        $sort $sortorder,
        $sort2 $sortorder
    LIMIT 30 OFFSET $start;

    ");
    
    
if (!is_ajax()) {
printSectionTitle('Weapon Statistics');
?>
<div class="hlstats-cards-grid">
<section class="hlstats-section hlstats-card">
  <div class="hlstats-card-foot">From a total of <strong><?php echo nf($realkills); ?></strong> kills with <strong><?php echo nf($realheadshots); ?></strong> headshots</div>
</section>
</div>

<div id="weapons">
<?php
}
if ($total) {
?>
<div class="responsive-table">
  <table class="weapons-table">
    <tr>
        <th class="nowarp right" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-column left<?= isSorted('weapon',$sort,$sortorder) ?>"><?= headerUrl('weapon', ['weap_sort','weap_sortorder'], 'weapons') ?>Weapons</a></th>
        <th class="hide-3<?= isSorted('modifier',$sort,$sortorder) ?>"><?= headerUrl('modifier', ['weap_sort','weap_sortorder'], 'weapons') ?>Modifier</a></th>
        <th class="<?= isSorted('kills',$sort,$sortorder) ?>"><?= headerUrl('kills', ['weap_sort','weap_sortorder'], 'weapons') ?>Kills</a></th>
        <th class="hide-1 meter-ratio<?= isSorted('kpercent',$sort,$sortorder) ?>"><?= headerUrl('kpercent', ['weap_sort','weap_sortorder'], 'weapons') ?>Ratio</a></th>
        <th class="hide<?= isSorted('headshots',$sort,$sortorder) ?>"><?= headerUrl('headshots', ['weap_sort','weap_sortorder'], 'weapons') ?>Headshots</a></th>
        <th class="hide-1 meter-ratio<?= isSorted('hpercent',$sort,$sortorder) ?>"><?= headerUrl('hpercent', ['weap_sort','weap_sortorder'], 'weapons') ?>Ratio</a></th>
        <th class="hide-2<?= isSorted('hpk',$sort,$sortorder) ?>"><?= headerUrl('hpk', ['weap_sort','weap_sortorder'], 'weapons') ?>HS:K</a></th>
    </tr>
    <?php

        $i = 1+$start;

        while ($res = $db->fetch_array($result))
        {
            $weapon = strtolower($res['weapon']);
            $image = getImage("/games/$game/weapons/" . $weapon);
            if ($image) {
                $weapimg = '<span class="hlstats-image"><img src="' . $image['url'] . '" alt="' . $fname[$weapon] . '" title="' . $fname[$weapon] . '"></span><span class="hlstats-name">' . ((!empty($fname[$weapon])) ? $fname[$weapon] : ucwords(preg_replace('/_/', ' ', $weapon))) . '</span>';
            } elseif ($image = getImage("/games/$realgame/weapons/" . $weapon)) {
                $weapimg = '<span class="hlstats-image"><img src="' . $image['url'] . '" alt="' . $fname[$weapon] . '" title="' . $fname[$weapon] . '"></span><span class="hlstats-name">' . ((!empty($fname[$weapon])) ? $fname[$weapon] : ucwords(preg_replace('/_/', ' ', $weapon))) . '</span>';
            } else {
                $weapimg = '<span class="hlstats-name">' . ((!empty($fname[$weapon])) ? $fname[$weapon] : ucwords(preg_replace('/_/', ' ', $weapon))) . '</span>';
            }
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="left"><a href="?mode=weaponinfo&weapon='.$res['weapon'].'&game='.$game.'">'.$weapimg.'</a></td>
                  <td class="nowrap hide-3">'.$res['modifier'].' times</td>
                  <td class="nowrap">'.$res['kills'].'</td>
                  <td class="nowrap hide-1">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['kpercent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['kpercent'].'%</div>
                    </div>
                  </td>
                  <td class="nowrap hide">'.$res['headshots'].'</td>
                  <td class="nowrap hide-1">
                    <div class="meter-container hide">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['hpercent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['hpercent'].'%</div>
                    </div>
                  </td>
                  <td class="nowrap hide-2">'.$res['hpk'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($total, $_GET['weap_page'] ?? 1, 30, 'weap_page', true, 'weapons');

  if (is_ajax()) exit;
} else { echo '<p class="hlstats-no-data"><em>Not enough data</em></p>'; }
  ?>
</div>