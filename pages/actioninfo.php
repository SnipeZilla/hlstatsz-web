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

// Action Details
	if (!$game) {
        error("No such game.");
	}
// Addon created by Rufus (rufus@nonstuff.de)
$asterisk = $g_options['DeleteDays'] ? ' *' : '';
$action = valid_request($_GET['action'] ?? '', false) or error('No action ID specified.');

$action_escaped=$db->escape($action);
$game_escaped=$db->escape($game);

$db->query("
    SELECT
        for_PlayerActions,for_PlayerPlayerActions, description
    FROM
        hlstats_Actions
    WHERE
        code='{$action_escaped}'
        AND game='{$game_escaped}'
");

if ($db->num_rows() != 1)
{
    $act_name = ucfirst($action);
    $actiondata['for_PlayerActions']=1; // dummy these out, this should never happen?
    $actiondata['for_PlayerPlayerActions']=0;
}
else
{
    $actiondata = $db->fetch_array();
    $db->free_result();
    $act_name = $actiondata['description'];
}

if (!is_ajax() || $_GET['ajax'] == 'actioninfo') {
    $sortorder = $_GET['obj_sortorder'] ?? '';
    $sort      = $_GET['obj_sort'] ?? '';
    $sort2     = "obj_bonus";

    $sortby = $sort;
    $order  = $sortorder;

    $col = array("playerName","obj_count","obj_bonus");
    if (!in_array($sort, $col)) {
        $sort      = "obj_count";
        $sortorder = "DESC";
    }

    if ($sort == "obj_bonus") {
        $sort2 = "obj_count";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['obj_page']) ? ((int)$_GET['obj_page'] - 1) * 30 : 0;

    $result = $db->query("
    WITH base AS (
        SELECT
            p.playerId,
            p.lastName AS playerName,
            p.flag,
            COUNT(e.id) AS obj_count,
            COUNT(e.id) * a.reward_player AS obj_bonus
        FROM hlstats_Events_PlayerActions e
        JOIN hlstats_Players p ON p.playerId = e.playerId
        JOIN hlstats_Actions a ON a.id = e.actionId
        WHERE a.code = '{$action_escaped}'
        AND p.game = '{$game_escaped}'
        AND p.hideranking = '0'
        GROUP BY p.playerId
    
        UNION ALL
    
        SELECT
            p.playerId,
            p.lastName AS playerName,
            p.flag,
            COUNT(e.id) AS obj_count,
            COUNT(e.id) * a.reward_player AS obj_bonus
        FROM hlstats_Events_PlayerPlayerActions e
        JOIN hlstats_Players p ON p.playerId = e.playerId
        JOIN hlstats_Actions a ON a.id = e.actionId
        WHERE a.code = '{$action_escaped}'
        AND p.game = '{$game_escaped}'
        AND p.hideranking = '0'
        GROUP BY p.playerId
    
        UNION ALL
    
        SELECT
            p.playerId,
            p.lastName AS playerName,
            p.flag,
            COUNT(e.id) AS obj_count,
            COUNT(e.id) * a.reward_player AS obj_bonus
        FROM hlstats_Events_TeamBonuses e
        JOIN hlstats_Players p ON p.playerId = e.playerId
        JOIN hlstats_Actions a ON a.id = e.actionId
        WHERE a.code = '{$action_escaped}'
        AND p.game = '{$game_escaped}'
        AND p.hideranking = '0'
        GROUP BY p.playerId
    ),
    final AS (
        SELECT
            playerId,
            playerName,
            flag,
            SUM(obj_count) AS obj_count,
            SUM(obj_bonus) AS obj_bonus
        FROM base
        GROUP BY playerId
    )
    SELECT
        *,
        COUNT(*) OVER() AS total_rows
    FROM final
    ORDER BY
        $sort $sortorder,
        $sort2 $sortorder
    LIMIT 30 OFFSET $start
    ");

		$resultCount = $db->query("
			SELECT SUM(cnt) FROM (
				SELECT COUNT(*) AS cnt
				FROM hlstats_Events_PlayerActions e
				JOIN hlstats_Actions a ON a.id = e.actionId
				JOIN hlstats_Players p ON p.playerId = e.playerId
				WHERE a.code = '{$action_escaped}'
				AND p.game = '{$game_escaped}'
				AND p.hideranking = '0'
				UNION ALL
				SELECT COUNT(*) AS cnt
				FROM hlstats_Events_PlayerPlayerActions e
				JOIN hlstats_Actions a ON a.id = e.actionId
				JOIN hlstats_Players p ON p.playerId = e.playerId
				WHERE a.code = '{$action_escaped}'
				AND p.game = '{$game_escaped}'
				AND p.hideranking = '0'
				UNION ALL
				SELECT COUNT(*) AS cnt
				FROM hlstats_Events_TeamBonuses e
				JOIN hlstats_Actions a ON a.id = e.actionId
				JOIN hlstats_Players p ON p.playerId = e.playerId
				WHERE a.code = '{$action_escaped}'
				AND p.game = '{$game_escaped}'
				AND p.hideranking = '0'
			) AS combined
		");
		list($totalact) = $db->fetch_row($resultCount);
        

    if (!is_ajax()) {
        printSectionTitle('Action Details '.$asterisk);
        echo '<div class="hlstats-cards-grid">
        <section class="hlstats-section hlstats-card">
        <div class="hlstats-card-foot">
            <span class="hlstats-name">'.$act_name.'</span> from a total of <strong>'.nf(intval($totalact)).'</strong> achievements.
        </div>
        </section>
        </div>
        <div id="actioninfo">';
   }

if ($db->num_rows($result)) {

    $first = $db->fetch_array($result);
    $total = $first['total_rows'];


    echo '<div  class="responsive-table">
        <table class="maps-table">
        <tr>
            <th class="nowrap left" style="width:1%"><span>#</span></th>
            <th class="left'.isSorted('playerName',$sort,$sortorder).'">'.headerUrl('playerName',['obj_sort','obj_sortorder'],'actioninfo').'Player</a></th>
            <th class="'.isSorted('obj_count',$sort,$sortorder).'">'.headerUrl('obj_count',['obj_sort','obj_sortorder'],'actioninfo').'Achieved</a></th>
            <th class="hide-1'.isSorted('obj_bonus',$sort,$sortorder).'">'.headerUrl('obj_bonus',['obj_sort','obj_sortorder'],'actioninfo').'Skill Bonus Total</a></th>
        </tr>';

    $i = 1 + $start;
    $res = $first;

    do {
        echo '<tr>
            <td class="nowrap right">'.$i.'</td>
            <td class="left"><a href="?mode=playerinfo&player='.$res['playerId'].'">';
            if ($g_options['countrydata']) {
               echo '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" alt="'.$res['flag'].'"></span>';
            }
            echo '<a href="?mode=playerinfo&amp;player='.$res['playerId'].'" title=""></span><span class="hlstats-name">'.htmlspecialchars($res['playerName'] ?? '').'</span></a>
            </td>
            <td class="nowrap right">'.$res['obj_count'].'</td>
            <td class="nowrap right hide-1">'.$res['obj_bonus'].'</td>
        </tr>';

        $i++;

    } while ($res = $db->fetch_array($result));
    echo '</table></div>';

    echo Pagination($total, $_GET['obj_page'] ?? 1, 30, 'obj_page', true, 'actioninfo');
    if (is_ajax()) exit;
  }

    echo '</div>';

}

if (!is_ajax() || $_GET['ajax'] == 'vpage') {
    $sortorder = $_GET['vpage_sortorder'] ?? '';
    $sort      = $_GET['vpage_sort'] ?? '';
    $sort2     = "obj_bonus";

    $sortby = $sort;
    $order  = $sortorder;

    $col = array("playerName","obj_count","obj_bonus");
    if (!in_array($sort, $col)) {
        $sort      = "obj_count";
        $sortorder = "DESC";
    }

    if ($sort == "obj_bonus") {
        $sort2 = "obj_count";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['vpage_page']) ? ((int)$_GET['vpage_page'] - 1) * 30 : 0;


    $result = $db->query("
        WITH victim AS (
            SELECT
                p.playerId AS victimId,
                p.lastName AS playerName,
                p.flag,
                COUNT(e.id) AS obj_count,
                COUNT(e.id) * a.reward_player * -1 AS obj_bonus
            FROM hlstats_Events_PlayerPlayerActions e
            JOIN hlstats_Players p ON p.playerId = e.victimId
            JOIN hlstats_Actions a ON a.id = e.actionId
            WHERE a.code = '{$action_escaped}'
            AND p.game = '{$game_escaped}'
            AND p.hideranking = '0'
            GROUP BY p.playerId
        )
        SELECT
            *,
            COUNT(*) OVER() AS total_rows
        FROM victim
        ORDER BY
            $sort $sortorder,
            $sort2 $sortorder
        LIMIT 30 OFFSET $start;
    ");


if ($db->num_rows($result)) {
    if (empty($_GET['ajax'])) {
        printSectionTitle('Action Victim Details *');

        echo '<div class="hlstats-cards-grid">
        <section class="hlstats-section hlstats-card">
        <div class="hlstats-card-foot">
            <span class="hlstats-name">Victims of <?= $act_name ?></span> (Last '.$g_options['DeleteDays'].' Days)
        </div>
        </section>
        </div>
        <div id="vpage">';
    }

    $first = $db->fetch_array($result);
    $total = $first['total_rows'];

    echo '<div  class="responsive-table">
        <table class="maps-table">
        <tr>
            <th class="nowrap left" style="width:1%"><span>#</span></th>
            <th class="left'.isSorted('playerName',$sort,$sortorder).'">'.
                headerUrl('playerName',['vpage_sort','vpage_sortorder'],'vpage').'Player</a></th>
            <th class="'.isSorted('obj_count',$sort,$sortorder).'">'.
                headerUrl('obj_count',['vpage_sort','vpage_sortorder'],'vpage').'Times Victimized</a></th>
            <th class="hide-1'.isSorted('obj_bonus',$sort,$sortorder).'">'.
                headerUrl('obj_bonus',['vpage_sort','vpage_sortorder'],'vpage').'Skill Bonus Total</a></th>
        </tr>';

    $i = 1 + $start;
    $res = $first;

    do {
        echo '<tr>
            <td class="nowrap right">'.$i.'</td>
            <td class="left">
               <span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" alt="'.$res['flag'].'"></span>
               <a href="?mode=playerinfo&player='.$res['victimId'].'">'.htmlspecialchars($res['playerName']).'</a></td>
            <td class="nowrap right">'.$res['obj_count'].'</td>
            <td class="nowrap right hide-1">'.$res['obj_bonus'].'</td>
        </tr>';

        $i++;

    } while ($res = $db->fetch_array($result));

    echo '</table></div>';

    echo Pagination($total, $_GET['vpage'] ?? 1, $start, 'vpage', true, 'vpage');
    if (is_ajax()) exit;

    echo '</div>';
  }
}
?>
<div>
    <a href="?mode=actions&amp;game=<?= $game ?>">&larr;&nbsp;Action Statistics</a>
</div>
<?php if ($g_options['DeleteDays']) { ?>
<div class="hlstats-note">
    Items above are generated from the most recent <?= $g_options['DeleteDays'] ?> days of activity.
</div>
<?php } ?>