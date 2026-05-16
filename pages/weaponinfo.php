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

	// Weapon Details
	if (!$game) {
        error("No such game.");
	}

	$weapon = valid_request($_GET['weapon'] ?? '', false) or error('No weapon ID specified.');
	
	$db->query("
		SELECT
			name
		FROM
			hlstats_Weapons
		WHERE
			code='$weapon'
			AND game='$game'
	");
	if (!$db->num_rows()) { error("No such weapon."); }
		
	if ($db->num_rows() != 1)
	{
		$wep_name = ucfirst($weapon);
	}
	else
	{
		$weapondata = $db->fetch_array();
		$db->free_result();
		$wep_name = $weapondata['name'];
	}

    $total          = 0;
    $totalkills     = 0;
    $totalheadshots = 0;

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
            SUM(hlstats_Events_Frags.weapon='$weapon'),
            SUM(hlstats_Events_Frags.weapon='$weapon' AND hlstats_Events_Frags.headshot=1)
        FROM
            hlstats_Events_Frags,
            hlstats_Servers
        WHERE
            hlstats_Servers.serverId = hlstats_Events_Frags.serverId
            AND hlstats_Events_Frags.weapon='$weapon'
            AND hlstats_Servers.game='$game'
    ");

    list($numitems, $totalkills, $totalheadshots) = $db->fetch_row($resultCount);

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
            WHERE f.weapon = '$weapon'
                AND p.game = '$game'
                AND p.hideranking = 0
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
printSectionTitle('Weapon Details');


           $image = getImage("/games/$game/weapons/$weapon");

            if ($image) {
                $weapimg = '<img src="' . $image['url'] . '" style="width:' . $image['width'] . 'px;height:' . $image['height'] . 'px;"  alt="' . htmlspecialchars($wep_name) . '" title="' . htmlspecialchars($wep_name) . '">';
            } elseif ($image = getImage("/games/$realgame/weapons/" . $weapon)) {
                $weapimg = '<img src="' . $image['url'] . '" style="width:' . $image['width'] . 'px;height:' . $image['height'] . 'px;" alt="' . htmlspecialchars($wep_name) . '" title="' . htmlspecialchars($wep_name) . '">';
            } else {
                $weapimg = '';
            }
?>
<div class="hlstats-cards-grid">
    <section class="hlstats-section hlstats-card">
    <div class="hlstats-award has-winner">
    <div class="hlstats-award-title"><?= htmlspecialchars($wep_name) ?></div>
    <div class="hlstats-award-icon"><?= $weapimg ?></div>
    <div class="hlstats-award-count">From a total of <b><?= nf(intval($totalkills)) ?></b> kills with <b><?= nf($totalheadshots) ?></b> headshots</div>
    </div>
    </section>
</div>

<div id="weaponinfo">
<?php
}
if ($db->num_rows($result)) {
?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="hlstats-ranking nowrap<?= isSorted('rank_position',$sort,$sortorder) ?>"><?= headerUrl('rank_position', ['sort','sortorder'], 'weaponinfo') ?>Rank</a></th>
        <th class="hlstats-main-description left<?= isSorted('killerName',$sort,$sortorder) ?>"><?= headerUrl('killerName', ['sort','sortorder'], 'weaponinfo') ?>Player</a></th>
        <th class="<?= isSorted('frags',$sort,$sortorder) ?>"><?= headerUrl('frags', ['sort','sortorder'], 'weaponinfo') ?>Kills</a></th>
        <th class="hide<?= isSorted('headshots',$sort,$sortorder) ?>"><?= headerUrl('headshots', ['sort','sortorder'], 'weaponinfo') ?>Headshots</a></th>
        <th class="hide-1<?= isSorted('hpk',$sort,$sortorder) ?>"><?= headerUrl('hpk', ['sort','sortorder'], 'weaponinfo') ?>HS:K</a></th>
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
                    echo '<a href="'.$g_options['scripturl'].'?mode=playerinfo&amp;player='.$res['killerId'].'" title=""><span class="hlstats-name">'.htmlspecialchars(html_entity_decode($res['killerName'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_COMPAT).'&nbsp;</span></a>
                   </td>
                  <td class="nowrap">'.$res['frags'].'</td>
                  <td class="nowrap hide">'.$res['headshots'].'</td>
                  <td class="nowrap hide-1">'.round($res['hpk'], 2).'</td>
                  </tr>';
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($total, $_GET['page'] ?? 1, 30, 'page', true, 'weaponinfo');

  if (is_ajax()) exit;
}
  ?>
</div>
<div class="hlstats-note">
    <a href="?mode=weapons&amp;game=<?= $game ?>">&larr;&nbsp;Weapon Statistics</a>
</div>
<?php if ($g_options['DeleteDays']) { ?>
<div class="hlstats-note">
    Items above are generated from the most recent <?= $g_options['DeleteDays'] ?> days of activity.
</div>
<?php } ?>
