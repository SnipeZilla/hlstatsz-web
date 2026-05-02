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

    ob_flush();
    flush();
    
$asterisk = $g_options['DeleteDays'] ? ' *' : '';

if (empty($_GET['ajax']) || $_GET['ajax'] == 'playeractions') {


    $sortorder = $_GET['obj_sortorder'] ?? '';
    $sort      = $_GET['obj_sort'] ?? '';
    $sort2     = "obj_bonus";

    $sortby = $sort;
    $order  = $sortorder;

    $col = array("obj_count","description","obj_bonus");
    if (!in_array($sort, $col)) {
        $sort      = "obj_count";
        $sortorder = "DESC";
    }

    if ($sort == "obj_bonus") {
        $sort2 = "obj_count";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['obj_page']) ? ((int)$_GET['obj_page'] - 1) * 10 : 0;
  
	$result = $db->query
	("
         WITH actions_union AS (
             SELECT
                 a.code,
                 a.description,
                 COUNT(e1.id) AS obj_count,
                 SUM(e1.bonus) AS obj_bonus
             FROM hlstats_Actions a
             LEFT JOIN hlstats_Events_PlayerActions e1
                 ON e1.actionId = a.id
             WHERE e1.playerId = $player
             GROUP BY a.id
         
             UNION ALL
         
             SELECT
                 a.code,
                 a.description,
                 COUNT(e2.id) AS obj_count,
                 SUM(e2.bonus) AS obj_bonus
             FROM hlstats_Actions a
             LEFT JOIN hlstats_Events_PlayerPlayerActions e2
                 ON e2.actionId = a.id
             WHERE e2.playerId = $player
             GROUP BY a.id
         )
         SELECT
             *,
             COUNT(*) OVER() AS total_rows
         FROM actions_union
         ORDER BY
             $sort $sortorder,
             $sort2 $sortorder
         LIMIT 10 OFFSET $start;
	");

	if ($db->num_rows($result))
	{
       if (empty($_GET['ajax']) || $_GET['ajax'] != 'playeractions') {

		printSectionTitle('Player Actions'.$asterisk);
?>
<div id="playeractions">
<?php
}
?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('description',$sort,$sortorder) ?>"><?= headerUrl('description', ['obj_sort','obj_sortorder'], 'playeractions') ?>Action</a></th>
        <th class="<?= isSorted('obj_count',$sort,$sortorder) ?>"><?= headerUrl('obj_count', ['obj_sort','obj_sortorder'], 'playeractions') ?>Earned</a></th>
        <th class="hide<?= isSorted('obj_bonus',$sort,$sortorder) ?>"><?= headerUrl('obj_bonus', ['obj_sort','obj_sortorder'], 'playeractions') ?>Accumulated Points</a></th>
    </tr>
    <?php
        $i= 1 + $start;
        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><a href="?mode=actioninfo&action='.$res['code'].'&game='.$game.'"><span class="hlstats-name">'.htmlspecialchars($res['description']).'</span></a></td>
                  <td class="nowrap">'.$res['obj_count'].' times</td>
                  <td class="nowrap hide">'.$res['obj_bonus'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($total, $_GET['obj_page'] ?? 1, 10, 'obj_page', true, 'playeractions');

  if (!empty($_GET['ajax']) && $_GET['ajax'] == 'playeractions') exit;
  ?>
</div>
<?php
    }
}

if (empty($_GET['ajax']) || $_GET['ajax'] == 'playerplayeractions') {

    $sortorder = $_GET['ppa_sortorder'] ?? '';
    $sort      = $_GET['ppa_sort'] ?? '';
    $sort2     = "obj_bonus";

    $col = array("obj_count","description","obj_bonus");
    if (!in_array($sort, $col)) {
        $sort      = "obj_count";
        $sortorder = "DESC";
    }

    if ($sort == "obj_bonus") {
        $sort2 = "obj_count";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['ppa_page']) ? ((int)$_GET['ppa_page'] - 1) * 10 : 0;

	$result = $db->query
	("WITH victim_actions AS (
           SELECT
               a.code,
               a.description,
               COUNT(e.id) AS obj_count,
               SUM(e.bonus) * -1 AS obj_bonus
           FROM hlstats_Actions a
           LEFT JOIN hlstats_Events_PlayerPlayerActions e
               ON e.actionId = a.id
           WHERE e.victimId = $player
           GROUP BY a.id
       )
       SELECT
           *,
           COUNT(*) OVER() AS total_rows
       FROM victim_actions
       ORDER BY
           $sort $sortorder,
           $sort $sortorder
       LIMIT 5 OFFSET $start;

	");

	if ($db->num_rows($result)) {
       if (empty($_GET['ajax']) || $_GET['ajax'] != 'playerplayeractions') {

    printSectionTitle('Victims of Player-Player Actions'.$asterisk);
?>
<div id="playerplayeractions">
<?php
}
?>
<div class="responsive-table">
 <table class="playersplay-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('description',$sort,$sortorder) ?>"><?= headerUrl('description', ['ppa_sort','ppa_sortorder'], 'playerplayeractions') ?>Action</a></th>
        <th class="<?= isSorted('obj_count',$sort,$sortorder) ?>"><?= headerUrl('obj_count', ['ppa_sort','ppa_sortorder'], 'playerplayeractions') ?>Earned</a></th>
        <th class="hide<?= isSorted('obj_bonus',$sort,$sortorder) ?>"><?= headerUrl('obj_bonus', ['ppa_sort','ppa_sortorder'], 'playerplayeractions') ?>Accumulated Points</a></th>
    </tr>
    <?php
        $i= 1 + $start;
        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><a href="?mode=actioninfo&action='.$res['code'].'&game='.$game.'"><span class="hlstats-name">'.htmlspecialchars($res['description']).'</span></a></td>
                  <td class="nowrap">'.$res['obj_count'].' times</td>
                  <td class="nowrap hide">'.$res['obj_bonus'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($total, $_GET['ppa_page'] ?? 1, 10, 'ppa_page', true, 'playerplayeractions');

  if (!empty($_GET['ajax']) && $_GET['ajax'] == 'playerplayeractions') exit;
  ?>
</div>
<?php
    }
}
    ob_flush();
    flush();
?>