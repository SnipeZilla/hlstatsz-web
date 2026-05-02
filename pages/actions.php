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

// Action Statistics
// Addon Created by Rufus (rufus@nonstuff.de)
	$db->query
	("
		SELECT
			hlstats_Games.name
		FROM
			hlstats_Games
		WHERE
			hlstats_Games.code = '$game'
	");

	if ($db->num_rows() < 1) {
        error("No such game '$game'.");
	}

	list($gamename) = $db->fetch_row();
	$db->free_result();

    $sortorder = $_GET['obj_sortorder'] ?? '';
    $sort      = $_GET['obj_sort'] ?? '';
    $sort2     = "description";

    $sortby = $sort;
    $order  = $sortorder;

    $col = array("description","obj_count","obj_bonus");
    if (!in_array($sort, $col)) {
        $sort      = "obj_count";
        $sortorder = "DESC";
    }

    if ($sort == "description") {
        $sort2 = "obj_count";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['obj_page']) ? ((int)$_GET['obj_page'] - 1) * 30 : 0;

	$result = $db->query("
		SELECT
			hlstats_Actions.code,
			hlstats_Actions.description,
			hlstats_Actions.count AS obj_count,
			hlstats_Actions.reward_player AS obj_bonus
		FROM
			hlstats_Actions
		WHERE
			hlstats_Actions.game = '$game'
			AND hlstats_Actions.count > 0
		GROUP BY
			hlstats_Actions.id
		ORDER BY
			$sort $sortorder,
			$sort2 $sortorder
        LIMIT 30 OFFSET $start
	");
	$db->query
	("
		SELECT
			SUM(count),
            COUNT(DISTINCT id)
		FROM
			hlstats_Actions
		WHERE
			hlstats_Actions.game = '$game'
            AND hlstats_Actions.count > 0
	");
	list($totalactions,$numitems) = $db->fetch_row();
if (!is_ajax()) {

printSectionTitle('Action Statistics'); ?>
<div class="hlstats-cards-grid">
<section class="hlstats-section hlstats-card">
  <div class="hlstats-card-foot">From a total of <strong><?= nf($totalactions) ?></strong> earned actions</div>
</section>
</div>
<div id="actions">
<?php
}
if ($numitems) {
?>
<div  class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-column left<?= isSorted('description',$sort,$sortorder) ?>"><?= headerUrl('description', ['obj_sort','obj_sortorder'], 'actions') ?>Action</a></th>
        <th class="<?= isSorted('obj_count',$sort,$sortorder) ?>"><?= headerUrl('obj_count', ['obj_sort','obj_sortorder'], 'actions') ?>Earned</a></th>
        <th class="<?= isSorted('obj_bonus',$sort,$sortorder) ?>"><?= headerUrl('obj_bonus', ['obj_sort','obj_sortorder'], 'actions') ?>Reward</a></th>
    </tr>
    <?php
        $i= 1 + $start;
        while ($res = $db->fetch_array($result))
        {
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="left">
                       <a href="?mode=actioninfo&amp;action='.$res['code'].'&amp;game='.$game.'" title=""><span class="hlstats-name">'.$res['description'].'</span></a>
                   </td>
                  </td>
                  <td class="nowrap">'.$res['obj_count'].' times</td>
                  <td class="nowrap">'.$res['obj_bonus'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($numitems, $_GET['obj_page'] ?? 1, 30, 'obj_page');

  if (is_ajax()) exit;
} else { echo '<p class="hlstats-no-data"><em>Not enough data</em></p>'; }
  ?>
</div>
<script>
Fetch.ini('actions');
</script>