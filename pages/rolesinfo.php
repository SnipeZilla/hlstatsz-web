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
	
	// Roles Details
	
	$role = valid_request($_GET['role'] ?? '', false) or error('No role ID specified.');
	
	$db->query("
		SELECT
			hlstats_Roles.name,
			hlstats_Roles.code
		FROM
			hlstats_Roles
		WHERE
			hlstats_Roles.code='$role'
			AND hlstats_Roles.game='$game'
	");
	
	if ($db->num_rows() != 1) {
		$role_name = ucfirst($role);
		$role_code = ucfirst($role);
	} else {
		$roledata = $db->fetch_array();
		$db->free_result();
		$role_name = $roledata['name'];
		$role_code = $roledata['code'];
	}

	$db->query("SELECT name FROM hlstats_Games WHERE code='$game'");
	if ($db->num_rows() != 1) {
		error('Invalid or no game specified.');
	} else {
		list($gamename) = $db->fetch_row();
	}

    $numitems       = 0;
    $totalkills     = 0;
    $totalheadshots = 0;

    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';
    $sort2     = "killerName";

    $sortby = $sort;
    $order  = $sortorder;

    $col = array("frags","killerName");
    if (!in_array($sort, $col)) {
        $sort      = "frags";
        $sortorder = "DESC";
    }

    if ($sort == "killerName") {
        $sort2 = "frags";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;
  
	$result = $db->query("
		SELECT
			hlstats_Events_Frags.killerId,
			hlstats_Players.lastName AS killerName,
			hlstats_Players.flag as flag,
			COUNT(hlstats_Events_Frags.killerRole) AS frags
		FROM
			hlstats_Events_Frags,
			hlstats_Players
		WHERE
			hlstats_Players.playerId = hlstats_Events_Frags.killerId
			AND hlstats_Events_Frags.killerRole='$role'
			AND hlstats_Players.game='$game'
			AND hlstats_Players.hideranking<>'1'
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
			SUM(hlstats_Events_Frags.killerRole='$role'),
			SUM(hlstats_Events_Frags.killerRole='$role' AND hlstats_Events_Frags.headshot=1)
		FROM
			hlstats_Events_Frags,
			hlstats_Servers
		WHERE
			hlstats_Servers.serverId = hlstats_Events_Frags.serverId
			AND hlstats_Events_Frags.killerRole='$role'
			AND hlstats_Servers.game='$game'
	");
	
	list($numitems, $totalkills, $totalheadshots) = $db->fetch_row($resultCount);

if (!is_ajax()){
printSectionTitle('Role Details');

echo '<div class="hlstats-cards-grid">
  <section class="hlstats-section hlstats-card">';

        $image = getImage("/games/$game/roles/$role");

        if ($image) {
            $imagestring = '<img src="'.$image['url'].'" style="width:' . $image['width'] . 'px;height:' . $image['height'] . 'px;" alt="'.htmlspecialchars($role_name).'" />';
        } elseif ($image = getImage("/games/$realgame/roles/$role")) {
            $imagestring = '<img src="'.$image['url'].'" style="width:' . $image['width'] . 'px;height:' . $image['height'] . 'px;" alt="'.htmlspecialchars($role_name).'" />';
        } else {
            $imagestring = '';
        }
    echo '<div class="hlstats-award'.($numitems>0?' has-winner':'').'">
            <div class="hlstats-award-title">'.htmlspecialchars($role_name).'</div>
            <div class="hlstats-award-icon">'.$imagestring.'</div>
            <div class="hlstats-award-count">From a total of '.nf(intval($totalkills)).' kills as '.htmlspecialchars($role_name).'</div>
          </div>
  </section>
</div>';
}



	if ($numitems) {
       if (!is_ajax()) {

?>
<div id="rolesinfo">
<?php
}
?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('killerName',$sort,$sortorder) ?>"><?= headerUrl('killerName', ['sort','sortorder'], 'rolesinfo') ?>Player</a></th>
        <th class="<?= isSorted('frags',$sort,$sortorder) ?>"><?= headerUrl('frags', ['sort','sortorder'], 'rolesinfo') ?><?= ucfirst($role_name) ?> kills</a></th>
    </tr>
    <?php
        $i= 1 + $start;
        while ($res = $db->fetch_array($result))
        {
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left">';
                  if ($g_options['countrydata']) {
                    echo '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" alt="'.$res['flag'].'"></span>';
                  }
                  echo '<a href="'.$g_options['scripturl'].'?mode=playerinfo&amp;player='.$res['killerId'].'" title=""><span class="hlstats-name">'.htmlspecialchars($res['killerName']).'&nbsp;</span></a>
                   </td>
                  </td>
                  <td class="nowrap">'.$res['frags'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($numitems, $_GET['page'] ?? 1, 30, 'page');

  if (is_ajax()) exit;
  ?>
</div>
<script>
Fetch.ini('rolesinfo');
</script>
<?php
 } else { echo '<p class="hlstats-no-data"><em>Not enough data</em></p>'; }
?>
 <div><a href="?mode=roles&amp;game=<?= $game ?>&amp;tab=ranks">&larr;&nbsp;Roles Statistics</a></div>
<?php if ($g_options['DeleteDays']) { ?>
<div class="hlstats-note">
    Items above are generated from the most recent <?= $g_options['DeleteDays'] ?> days of activity.
</div>
<?php } ?>
