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
	if (!$game) {
        error("No such game.");
	}
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

    $total          = 0;
    $totalkills     = 0;
    $totalheadshots = 0;

    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';
    $sort2     = "killerName";

    $col = array("rank_position","killerName","frags");
    if (!in_array($sort, $col)) {
        $sort      = "rank_position";
        $sortorder = "ASC";
    }

    // Secondary sort
    if ($sort == "killerName") {
        $sort2 = "frags";
    } else {
        $sort2 = "killerName";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;

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

    $result = $db->query("
        WITH Base AS (
            SELECT
                f.killerId,
                p.lastName AS killerName,
                p.flag,
                COUNT(*) AS frags
            FROM hlstats_Events_Frags AS f
            INNER JOIN hlstats_Players AS p
                ON p.playerId = f.killerId
            WHERE f.killerRole = '$role'
                AND p.game = '$game'
                AND p.hideranking <> 1
            GROUP BY f.killerId, p.lastName, p.flag
        ),
        Ranked AS (
            SELECT
                *,
                RANK() OVER (ORDER BY frags DESC, killerName ASC) AS rank_position,
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


    if ($db->num_rows($result)) {
       if (!is_ajax()) {
?>
<div id="rolesinfo">
<?php
}
?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="hlstats-ranking nowrap<?= isSorted('rank_position',$sort,$sortorder) ?>"><?= headerUrl('rank_position', ['sort','sortorder'], 'rolesinfo') ?>Rank</a></th>
        <th class="hlstats-main-description left<?= isSorted('killerName',$sort,$sortorder) ?>"><?= headerUrl('killerName', ['sort','sortorder'], 'rolesinfo') ?>Player</a></th>
        <th class="<?= isSorted('frags',$sort,$sortorder) ?>"><?= headerUrl('frags', ['sort','sortorder'], 'rolesinfo') ?><?= ucfirst($role_name) ?> kills</a></th>
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
                  </tr>';
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($total, $_GET['page'] ?? 1, 30, 'page');

  if (is_ajax()) exit;
  ?>
</div>
<script>
Fetch.ini('rolesinfo');
</script>
<?php
 } else { echo '<p class="hlstats-no-data"><em>Not enough data</em></p>'; }
?>
<div class="hlstats-note"><a href="?mode=roles&amp;game=<?= $game ?>&amp;tab=ranks">&larr;&nbsp;Role Statistics</a></div>
<?php if ($g_options['DeleteDays']) { ?>
<div class="hlstats-note">
    Items above are generated from the most recent <?= $g_options['DeleteDays'] ?> days of activity.
</div>
<?php } ?>
