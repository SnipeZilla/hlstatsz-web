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

	if (!$game) {
        error("No such game.");
	}

    $total = 0;

    $sortorder = $_GET['obj_sortorder'] ?? '';
    $sort      = $_GET['obj_sort'] ?? '';
    $sort2     = "description";

    $col = array("rank_position","description","obj_count","obj_bonus");
    if (!in_array($sort, $col)) {
        $sort      = "rank_position";
        $sortorder = "ASC";
    }

    if ($sort == "description") {
        $sort2 = "obj_count";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['obj_page']) ? ((int)$_GET['obj_page'] - 1) * 30 : 0;

    $result = $db->query("
        WITH Ranked AS (
            SELECT
                code,
                description,
                count AS obj_count,
                reward_player AS obj_bonus,
                RANK() OVER (ORDER BY count DESC, reward_player DESC) AS rank_position,
                COUNT(*) OVER () AS total_rows
            FROM hlstats_Actions
            WHERE game = '$game'
              AND count > 0
        )
        SELECT *
        FROM Ranked
        ORDER BY
            $sort $sortorder,
            $sort2 $sortorder,
            description ASC
        LIMIT 30 OFFSET $start
    ");

    $db->query("
        SELECT SUM(count)
        FROM hlstats_Actions
        WHERE hlstats_Actions.game = '$game'
          AND hlstats_Actions.count > 0
    ");
    list($totalactions) = $db->fetch_row();

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
if ($db->num_rows($result)) {
?>
<div  class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="hlstats-ranking nowrap<?= isSorted('rank_position',$sort,$sortorder) ?>"><?= headerUrl('rank_position', ['obj_sort','obj_sortorder'], 'actions') ?>Rank</a></th>
        <th class="hlstats-main-column left<?= isSorted('description',$sort,$sortorder) ?>"><?= headerUrl('description', ['obj_sort','obj_sortorder'], 'actions') ?>Action</a></th>
        <th class="<?= isSorted('obj_count',$sort,$sortorder) ?>"><?= headerUrl('obj_count', ['obj_sort','obj_sortorder'], 'actions') ?>Earned</a></th>
        <th class="<?= isSorted('obj_bonus',$sort,$sortorder) ?>"><?= headerUrl('obj_bonus', ['obj_sort','obj_sortorder'], 'actions') ?>Reward</a></th>
    </tr>
    <?php
        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            echo '<tr>
                  <td class="nowrap right">'.$res['rank_position'].'</td>
                  <td class="left">
                       <a href="?mode=actioninfo&amp;action='.urlencode($res['code']).'&amp;game='.$game.'" title=""><span class="hlstats-name">'.htmlspecialchars($res['description']).'</span></a>
                   </td>
                  <td class="nowrap">'.$res['obj_count'].' times</td>
                  <td class="nowrap">'.$res['obj_bonus'].'</td>
                  </tr>';
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($total, $_GET['obj_page'] ?? 1, 30, 'obj_page');

  if (is_ajax()) exit;
} else { echo '<p class="hlstats-no-data"><em>Not enough data</em></p>'; }
  ?>
</div>
<script>
Fetch.ini('actions');
</script>
