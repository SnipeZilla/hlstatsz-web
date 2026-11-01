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

$asterisk = $g_options['DeleteDays'] ? ' *' : '';

  if (empty($_GET['ajax']) || $_GET['ajax'] == "obj") {
    $sortorder = $_GET['obj_sortorder'] ?? '';
    $sort      = $_GET['obj_sort'] ?? '';
    $sort2     = "code";

    $col = array("rank_position","description","obj_count","obj_bonus");
    if (!in_array($sort, $col)) {
        $sort      = "rank_position";
        $sortorder = "ASC";
    }

    if ($sort == "rank_position") {
        $sort2 = "obj_count";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = max(0, (int) ($_GET['obj_page'] ?? 1) - 1) * 30;

	$result = $db->query("
        WITH combined AS (
            SELECT
                a.code,
                a.description,
                COUNT(e.id) AS obj_count,
                SUM(e.bonus) AS obj_bonus
            FROM hlstats_Actions a
            LEFT JOIN hlstats_Events_PlayerActions e
                ON e.actionId = a.id
            LEFT JOIN hlstats_Players p
                ON p.playerId = e.playerId
            WHERE p.clan = $clan
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
            LEFT JOIN hlstats_Players p2
                ON p2.playerId = e2.playerId
            WHERE p2.clan = $clan
            GROUP BY a.id
        ),
        final AS (
            SELECT
                code,
                MIN(description) AS description,
                SUM(obj_count) AS obj_count,
                SUM(obj_bonus) AS obj_bonus
            FROM combined
            GROUP BY code
        ),
        ranked AS (
            SELECT *,
                RANK() OVER (ORDER BY obj_count DESC, code ASC) AS rank_position,
                COUNT(*) OVER() AS total_rows
            FROM final
        )
        SELECT * FROM ranked
        ORDER BY
            $sort $sortorder,
            $sort2 $sortorder,
            code ASC
        LIMIT 30 OFFSET $start;


    ");

  if ($db->num_rows($result)) {

    $first = $db->fetch_array($result);
    $total = $first['total_rows'];
    if (empty($_GET['ajax'])) {
        printSectionTitle(t('title.player.actions').$asterisk);
        echo '<div id="obj">';
    }
    echo '<div class="responsive-table"><table class="maps-table">
        <tr>
            <th class="hlstats-ranking nowrap'.isSorted('rank_position',$sort,$sortorder).'">'.
                headerUrl('rank_position', ['obj_sort','obj_sortorder'], 'obj').t('th.rank').'</a></th>
            <th class="left'.isSorted('description',$sort,$sortorder).'">'.
                headerUrl('description',['obj_sort','obj_sortorder'],'obj').t('th.action').'</a></th>
            <th class="'.isSorted('obj_count',$sort,$sortorder).'">'.
                headerUrl('obj_count',['obj_sort','obj_sortorder'],'obj').t('th.achieved').'</a></th>
            <th class="hide'.isSorted('obj_bonus',$sort,$sortorder).'">'.
                headerUrl('obj_bonus',['obj_sort','obj_sortorder'],'obj').t('th.points.bonus').'</a></th>
        </tr>';

    $res = $first;

    do {
        echo '<tr>
            <td class="nowrap right">'.$res['rank_position'].'</td>
            <td class="hlstats-main-description left"><a href="?mode=actioninfo&action='.$res['code'].'&game='.$game.'">'.
                htmlspecialchars($res['description']).'</a></td>
            <td class="nowrap right">'.$res['obj_count'].' times</td>
            <td class="nowrap right hide">'.$res['obj_bonus'].'</td>
        </tr>';

    } while ($res = $db->fetch_array($result));

    echo '</table></div>';

    echo Pagination($total, $_GET['obj_page'] ?? 1, 30, 'obj_page', true, 'obj');
    if (!empty($_GET['ajax'])) exit;
    echo '</div>';
  }
}

if (empty($_GET['ajax']) || $_GET['ajax'] == "ppa") {

    $sortorder = $_GET['ppa_sortorder'] ?? '';
    $sort      = $_GET['ppa_sort'] ?? '';
    $sort2     = "code";

    $col = array("rank_position","description","obj_count","obj_bonus");
    if (!in_array($sort, $col)) {
        $sort      = "rank_position";
        $sortorder = "ASC";
    }

    if ($sort == "obj_count") {
        $sort2 = "code";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['ppa_page']) ? ((int)$_GET['ppa_page'] - 1) * 30 : 0;

	$result = $db->query("
        WITH victim_actions AS (
            SELECT
                a.code,
                a.description,
                COUNT(e.id) AS obj_count,
                SUM(e.bonus) * -1 AS obj_bonus
            FROM hlstats_Actions a
            LEFT JOIN hlstats_Events_PlayerPlayerActions e
                ON e.actionId = a.id
            LEFT JOIN hlstats_Players p
                ON p.playerId = e.victimId
            WHERE p.clan = $clan
            GROUP BY a.id
        ),
        ranked AS (
            SELECT *,
                RANK() OVER (ORDER BY obj_count DESC, code ASC) AS rank_position,
                COUNT(*) OVER() AS total_rows
            FROM victim_actions
        )
        SELECT * FROM ranked
        ORDER BY
            $sort $sortorder,
            $sort2 $sortorder
    ");

  if ($db->num_rows($result)) {

    $first = $db->fetch_array($result);
    $total = $first['total_rows'];

    if (empty($_GET['ajax'])) {
        printSectionTitle(t('title.victims.pp.actions').$asterisk);
        echo '<div id="ppa">';
    }
    echo '<div class="responsive-table"><table class="maps-table">
        <tr>
            <th class="hlstats-ranking nowrap'.isSorted('rank_position',$sort,$sortorder).'">'.
                headerUrl('rank_position', ['ppa_sort','ppa_sortorder'], 'ppa').t('th.rank').'</a></th>
            <th class="left'.isSorted('description',$sort,$sortorder).'">'.
                headerUrl('description',['ppa_sort','ppa_sortorder'],'ppa').t('th.action').'</a></th>
            <th class="'.isSorted('obj_count',$sort,$sortorder).'">'.
                headerUrl('obj_count',['ppa_sort','ppa_sortorder'],'ppa').t('th.times.victimized').'</a></th>
            <th class="hide'.isSorted('obj_bonus',$sort,$sortorder).'">'.
                headerUrl('obj_bonus',['ppa_sort','ppa_sortorder'],'ppa').t('th.points.bonus').'</a></th>
        </tr>';

    $res = $first;

    do {
        echo '<tr>
            <td class="nowrap right">'.$res['rank_position'].'</td>
            <td class="hlstats-main-column left"><a href="?mode=actioninfo&action='.$res['code'].'&game='.$game.'#victims">'.
                htmlspecialchars($res['description']).'</a></td>
            <td class="nowrap right">'.$res['obj_count'].' times</td>
            <td class="nowrap right hide">'.$res['obj_bonus'].'</td>
        </tr>';

    } while ($res = $db->fetch_array($result));

    echo '</table></div>';

    if (!empty($_GET['ajax'])) exit;
    echo '</div>';
  }
}