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
	if (!$game) {
        error(t('error.no.game'));
	}
$total     = 0;
$rank_type = (int)valid_request($_GET['rank_type'] ?? '', false);
$sortorder = valid_request($_GET['sortorder'] ?? '', false);
$sort      = valid_request($_GET['sort'] ?? '', false);

function qClansRank()
{
    global $game, $g_options, $sort, $sortorder, $rank_type;

    // The ranking: average of the first column over the members, then the sum of the second (fewest deaths first)
    if ($g_options['rankingtype'] !== 'kills') {
        $rank_type1 = 'skill';
        $rank_type2 = 'kills';
        $rank_sort2 = 'DESC';
    } else {
        $rank_type1 = 'kills';
        $rank_type2 = 'deaths';
        $rank_sort2 = 'ASC';
    }

    $col = array("rank_position","name", "tag", "skill","kills","deaths","kpd","headshots","hpk","members","activity","connection_time");
    if (!in_array($sort, $col)) {
        $sort      = 'rank_position';
        $sortorder = 'ASC';
    }

    // Clans equal on the column: by the first rank column, then the time; the id keeps them in one order
    $sort2 = $rank_type1;

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = max(0, (int) ($_GET['page'] ?? 1) - 1) * 30;

    switch ($rank_type) {
        case 0: // Global
            $dateFilter = "1=1";
            break;
    
        case 1: // Yesterday
            $dateFilter = "h.eventTime >= CURDATE() - INTERVAL 1 DAY
                           AND h.eventTime < CURDATE()";
            break;
    
        case 2: // Last Weekend (Sat + Sun): the two days before this week's Monday, a range of the date index
            $dateFilter = "h.eventTime >= CURDATE() - INTERVAL (WEEKDAY(CURDATE()) + 2) DAY
                           AND h.eventTime < CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY";
            break;
    
        case 3: // Last 7 days
            $dateFilter = "h.eventTime >= NOW() - INTERVAL 7 DAY";
            break;
    
        case 4: // Last 28 days
            $dateFilter = "h.eventTime >= NOW() - INTERVAL ".(int)$g_options['MinActivity']." DAY";
            break;

        default:
            $rank_type  =0;
            $dateFilter = "1=1";
            break;
    }

    // ---------------------------------------------------------
    // GLOBAL RANKING (no history table)
    // The clans of two ranked members or more, summed from their members; their names joined after
    // ---------------------------------------------------------
    if ($rank_type == 0) {
    return "SELECT
               r.*,
               c.tag AS tag,
               c.name AS name
           FROM (
               SELECT
                   RANK() OVER (ORDER BY AVG(p.$rank_type1) DESC, SUM(p.$rank_type2) $rank_sort2) AS rank_position,
                   p.clan as clan,
                   SUM(p.kills) AS kills,
                   SUM(p.deaths) AS deaths,
                   SUM(p.last_skill_change) AS last_skill_change,
                   FLOOR(AVG(p.skill)) AS skill,
                   SUM(p.shots) AS shots,
                   SUM(p.hits) AS hits,
                   SUM(p.headshots) AS headshots,
                   MAX(p.activity) AS activity,
                   MAX(p.last_event) AS last_event,
                   SUM(p.connection_time) as connection_time,
                   COUNT(p.playerId) AS members,
                   ROUND(SUM(p.kills) / NULLIF(SUM(p.deaths), 0),2) AS kpd,
                   ROUND(SUM(p.headshots) / NULLIF(SUM(p.kills), 0),2) AS hpk,
                   COUNT(*) OVER() AS total_rows
               FROM
                   hlstats_Players AS p
               WHERE
                   p.hideranking = 0
                   AND p.lastAddress <> ''
                   AND p.game = '".$game."'
                   AND p.clan > 0
               GROUP BY
                   p.clan
               HAVING
                   COUNT(p.playerId) > 1
           ) AS r
           LEFT JOIN
               hlstats_Clans AS c ON c.clanId = r.clan
           ORDER BY
               $sort $sortorder,
               $sort2 DESC,
               connection_time DESC,
               clan
           LIMIT
               30 OFFSET $start
            ";
    }

    // ---------------------------------------------------------
    // DATE-FILTERED RANKING (history table)
    // The days of the view are read on the date index, then their players: each member's days are summed first, so
    // that he counts once in his clan. Read from the clan members instead, the history of every day they played would
    // be read.
    // ---------------------------------------------------------
    require_once INCLUDE_PATH . '/players_rank.php';
    $dateIndex = historyIndex();
    return "SELECT
               r.*,
               c.tag AS tag,
               c.name AS name
           FROM (
               SELECT
                   RANK() OVER (ORDER BY AVG(m.$rank_type1) DESC, SUM(m.$rank_type2) $rank_sort2) AS rank_position,
                   m.clan as clan,
                   SUM(m.kills) AS kills,
                   SUM(m.deaths) AS deaths,
                   SUM(m.skill_change) AS last_skill_change,
                   FLOOR(AVG(m.skill)) AS skill,
                   SUM(m.shots) AS shots,
                   SUM(m.hits) AS hits,
                   SUM(m.headshots) AS headshots,
                   MAX(m.activity) AS activity,
                   MAX(m.last_event) AS last_event,
                   SUM(m.connection_time) as connection_time,
                   COUNT(*) AS members,
                   ROUND(SUM(m.kills) / NULLIF(SUM(m.deaths), 0),2) AS kpd,
                   ROUND(SUM(m.headshots) / NULLIF(SUM(m.kills), 0),2) AS hpk,
                   COUNT(*) OVER() AS total_rows
               FROM (
                   SELECT
                       p.playerId,
                       p.clan,
                       p.skill,
                       p.activity,
                       p.last_event,
                       SUM(h.kills) AS kills,
                       SUM(h.deaths) AS deaths,
                       SUM(h.skill_change) AS skill_change,
                       SUM(h.shots) AS shots,
                       SUM(h.hits) AS hits,
                       SUM(h.headshots) AS headshots,
                       SUM(h.connection_time) AS connection_time
                   FROM
                       hlstats_Players_History AS h $dateIndex
                   STRAIGHT_JOIN
                       hlstats_Players AS p ON p.playerId = h.playerId
                   WHERE
                       $dateFilter
                       AND h.game = '".$game."'
                       AND p.hideranking = 0
                       AND p.lastAddress <> ''
                       AND p.game = '".$game."'
                       AND p.clan > 0
                   GROUP BY
                       p.playerId
               ) AS m
               GROUP BY
                   m.clan
               HAVING
                   COUNT(*) > 1
           ) AS r
           LEFT JOIN
               hlstats_Clans AS c ON c.clanId = r.clan
           ORDER BY
               $sort $sortorder,
               $sort2 $sortorder,
               connection_time DESC,
               clan
           LIMIT
               30 OFFSET $start
            ";

}

// Clan Rankings
if (!is_ajax()) {

?>

<?php printSectionTitle(t('title.clan.rankings'));	?>
<div class="hlstats-select">
    <form method="get" action="" id="rankForm">
        <label for="selectType"><?= t('ranking.view') ?></label>
        <select name="rank_type" id="selectType">
            <option value="0"<?php echo ($rank_type == 0?' selected':'') ?>><?= t('global') ?></option>
            <option value="1"<?php echo ($rank_type == 1?' selected':'') ?>><?= t('yesterday') ?></option>
            <option value="2"<?php echo ($rank_type == 2?' selected':'') ?>><?= t('last.weekend') ?></option>
            <option value="3"<?php echo ($rank_type == 3?' selected':'') ?>><?= t('last.7days') ?></option>
            <option value="4"<?php echo ($rank_type == 4?' selected':'') ?>><?= t('last.activity.days', ["{activity}" => $g_options['MinActivity']]) ?></option>
        </select>
    </form>
</div>
<div id="clans">
<?php
}
$query  = qClansRank();
?>
<div  class="responsive-table">
<table class="clans-table">
    <thead>
        <th class="hlstats-ranking nowrap<?= isSorted('rank_position', $sort, $sortorder) ?>"><?= headerUrl('rank_position',['sort','sortorder'],'clans').t('th.rank') ?></a></th>
        <th class="hlstats-main-column left<?= isSorted('name', $sort, $sortorder) ?>"><?= headerUrl('name',['sort','sortorder'],'clans').t('th.clan') ?></a></th>
        <th class="hide-3<?= isSorted('tag', $sort, $sortorder) ?>"><?= headerUrl('tag',['sort','sortorder'],'clans').t('th.tag') ?></a></th>
        <?php  if ($g_options['rankingtype']!='kills') { ?>
        <th class="hide<?= isSorted('skill', $sort, $sortorder) ?>"><?= headerUrl('skill',['sort','sortorder'],'clans').t('th.points') ?></a></th>
        <?php } ?>
        <th class="<?= isSorted('kills', $sort, $sortorder) ?>"><?= headerUrl('kills',['sort','sortorder'],'clans').t('th.kills') ?></a></th>
        <th class="hide<?= isSorted('deaths', $sort, $sortorder) ?>"><?= headerUrl('deaths',['sort','sortorder'],'clans').t('th.deaths') ?></a></th>
        <th class="hide-2<?= isSorted('kpd', $sort, $sortorder) ?>"><?= headerUrl('kpd',['sort','sortorder'],'clans').t('th.kd') ?></a></th>
        <th class="hide-2<?= isSorted('headshots', $sort, $sortorder) ?>"><?= headerUrl('headshots',['sort','sortorder'],'clans').t('th.headshots') ?></a></th>
        <th class="hide-2<?= isSorted('hpk', $sort, $sortorder) ?>"><?= headerUrl('hpk',['sort','sortorder'],'clans').t('th.hsk') ?></a></th>
        <th class="<?= isSorted('members', $sort, $sortorder) ?>"><?= headerUrl('members',['sort','sortorder'],'clans').t('th.members') ?></a></th>
        <th class="hide-3<?= isSorted('activity', $sort, $sortorder) ?>"><?= headerUrl('activity',['sort','sortorder'],'clans').t('th.activity') ?></a></th>
        <th class="hide-1<?= isSorted('connection_time', $sort, $sortorder) ?>"><?= headerUrl('connection_time',['sort','sortorder'],'clans').t('th.connection.time') ?></a></th>
    </thead>
    <tbody>
    <?php
    if (!empty($query)) {
        $result = $db->query($query);
        while ($res = $db->fetch_array($result))
        {
            $total   = $res['total_rows'];
            $time    =  TimeStamp($res['connection_time']);
            $sign    = '';
            $class   = ' skill';
            if ($res['last_skill_change'] > 0) {
                $sign = '+';
                $class .= ' up green';
            }
            if ($res['last_skill_change'] < 0) {
                $class .= ' down red';
            }
            echo '
            <tr>
                <td class="nowrap right">'.$res['rank_position'].'</td>
                <td class="left'.$class.'" data-tooltip="'.htmlspecialchars($sign.$res['last_skill_change'], ENT_QUOTES)."&nbsp;".strtolower(t('th.points')).'">⚔️ <a href="'.$g_options['scripturl'].'?mode=claninfo&amp;clan='.$res['clan'].'"></span><span class="hlstats-name">'.htmlspecialchars($res['name']).'</span></a></td>
                <td class="hide-3 nowrap">'.htmlspecialchars($res['tag']).'</td>'
             .($g_options['rankingtype'] != 'kills' ?
                ('<td class="nowrap hide">'.nf($res['skill']).'</td>'):'').
                '<td class="nowrap">'.nf($res['kills']).'</td>
                <td class="nowrap hide">'.nf($res['deaths']).'</td>
                <td class="nowrap hide-2">'.$res['kpd'].'</td>
                <td class="nowrap hide-2">'.nf($res['headshots']).'</td>
                <td class="nowrap hide-2">'.$res['hpk'].'</td>
                <td class="nowrap">'.$res['members'].'</td>
                <td class="nowrap hide-3">
                 <div class="meter-container">
                   <meter min="0" max="100" low="25" high="50" optimum="75" value="'.max(0, (int) $res['activity']).'" data-tooltip="'.htmlspecialchars(formatDate($res['last_event']), ENT_QUOTES).'"></meter>
                   <div class="meter-value" id="meterText">'.max(0, (int) $res['activity']).'%</div>
                 </div>
                </td>
                <td class="nowrap hide-1">'.$time.'</td>
            </tr>';
        }
    }
    ?>
</tbody>
</table>
</div>
	<?php
    echo Pagination($total, $_GET['page'] ?? 1, 30, 'page');
if (is_ajax()) exit();

$updatedQuery = updateQueryKey(['rank_type' => '', 'page' => '']);
$baseUrl = $_SERVER['PHP_SELF'].'?'.$updatedQuery.'&rank_type=';

?>
</div>

<script>
Fetch.ini('clans');
const selectElement = document.getElementById('selectType');
rank_type = '<?= $rank_type?>';
selectElement.addEventListener('change', function (e) {
    e.preventDefault();
    const rankType = selectElement.value;

    if (rank_type == rankType) return false;
    
    rank_type = rankType;
    Fetch.run('<?=$baseUrl?>'+rankType);
});
</script>