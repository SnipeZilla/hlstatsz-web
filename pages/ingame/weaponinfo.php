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
	
	$db->query("SELECT name FROM hlstats_Games WHERE code='$game'");
	if ($db->num_rows() != 1)
	{
		error('Invalid or no game specified.');
	}
	else
	{
		list($gamename) = $db->fetch_row();
	}



    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';
    $sort2     = "headshots";

    $sortby = $sort;
    $order  = $sortorder;

    $col = array("killerName","frags","headshots","hpk");
    if (!in_array($sort, $col)) {
        $sort      = "frags";
        $sortorder = "DESC";
    }

    if ($sort == "frags") {
        $sort2 = "headshots";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;

	$result = $db->query("
		SELECT
			hlstats_Events_Frags.killerId,
			hlstats_Players.lastName AS killerName,
			hlstats_Players.flag as flag,
			COUNT(hlstats_Events_Frags.weapon) AS frags,
			SUM(hlstats_Events_Frags.headshot=1) as headshots,
			IFNULL(SUM(hlstats_Events_Frags.headshot=1) / Count(hlstats_Events_Frags.weapon), '-') AS hpk
		FROM
			hlstats_Events_Frags,
			hlstats_Players
		WHERE
			hlstats_Players.playerId = hlstats_Events_Frags.killerId
			AND hlstats_Events_Frags.weapon='$weapon'
			AND hlstats_Players.game='$game'
			AND hlstats_Players.hideranking = 0
		GROUP BY
			hlstats_Events_Frags.killerId
		ORDER BY
			$sort $sortorder,
			$sort2 $sortorder
		LIMIT 30 OFFSET $start
	");
	
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




$image = getImage("/games/$game/weapons/$weapon");

if ($image) {
    $wep_content = '<img src="' . $image['url'] . "\"  alt=\"$weapon\" />";
} else {
    $wep_content = "<span class=\"hstats-name\">$wep_name</span>: ";
}
?>
<div class="hlstats-award has-winner">
    <section class="hlstats-section hlstats-card">
    <div class="hlstats-image">
    <span class="hlstats-image"><?= $wep_content ?></span>
    <span class="hlstats-stats">From a total of <b><?= nf(intval($totalkills)) ?></b> kills with <b><?= nf($totalheadshots) ?></b> headshots</span>
    </div>
    </section>
</div>

<div id="weaponinfo">
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('killerName',$sort,$sortorder) ?>"><?= headerUrl('killerName', ['obj_sort','sortorder'], 'weaponinfo') ?>Player</a></th>
        <th class="<?= isSorted('frags',$sort,$sortorder) ?>"><?= headerUrl('frags', ['sort','sortorder'], 'weaponinfo') ?>Kills</a></th>
        <th class="<?= isSorted('headshots',$sort,$sortorder) ?>"><?= headerUrl('headshots', ['sort','sortorder'], 'weaponinfo') ?>Headshots</a></th>
        <th class="<?= isSorted('hpk',$sort,$sortorder) ?>"><?= headerUrl('hpk', ['sort','sortorder'], 'weaponinfo') ?>HS:K</a></th>
    </tr>
    <?php
        $i= 1 + $start;
        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left">';
                  if ($g_options['countrydata']) {
                   echo '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" alt="'.$res['flag'].'"></span>';
                  }
                   echo '<a href="?mode=statsme&amp;player='.$res['killerId'].'" title=""><span class="hlstats-name">'.$res['killerName'].'</span></a>
                   </td>
                  </td>
                  <td class="nowrap">'.$res['frags'].'</td>
                  <td class="nowrap">'.$res['headshots'].'</td>
                  <td class="nowrap">'.$res['hpk'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($numitems, $_GET['page'] ?? 1, 30, 'page', false, 'weaponinfo');

  ?>
</div>

<div>
<?php if (!empty($_GET['player'])) { ?>
   <a href="?mode=weapons&amp;game=<?=$game?>&amp;player=<?= $_GET['player'] ?>">&larr;&nbsp;Weapon Statistics</a>
<?php } else { ?>
   <a href="?mode=server&amp;game=<?=$game?>">&larr;&nbsp;Server List</a>
<?php } ?>
</div>
