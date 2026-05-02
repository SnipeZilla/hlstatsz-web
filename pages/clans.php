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

$total     = 0;
$rank_type = (int)valid_request($_GET['rank_type'] ?? '', false);
$sortorder = valid_request($_GET['sortorder'] ?? '', false);
$sort      = valid_request($_GET['sort'] ?? '', false);

function qClansRank()
{
    global $game, $g_options, $sort, $sortorder, $rank_type;

    if ($g_options['rankingtype'] !== 'kills') {
        $rank_type1 = 'skill';
        $rank_type2 = 'kills';
        $sortorder2 = 'DESC';
    } else {
        $rank_type1 = 'kills';
        $rank_type2 = 'deaths';
        $sortorder2 = 'ASC';
    }

    $col = array("rank_position","name", "tag", "skill","kills","deaths","kpd","headshots","hpk","members","activity","connection_time");
    if (!in_array($sort, $col)) {
        $sort      = 'rank_position';
        $sortorder = 'ASC';
    }
    
    if ($sort == $rank_type2) {
        $sort2 = $rank_type1;
        $sortorder2 = 'DESC';
    } else {
        $sort2 = $rank_type1;
        $sortorder2 = 'DESC';
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;

    switch ($rank_type) {
        case 0: // Global
            $dateFilter = "1=1";
            break;
    
        case 1: // Yesterday
            $dateFilter = "h.eventTime >= CURDATE() - INTERVAL 1 DAY
                           AND h.eventTime < CURDATE()";
            break;
    
        case 2: // Last Weekend (Sat + Sun)
            $dateFilter = "YEARWEEK(h.eventTime, 1) = YEARWEEK(CURDATE() - INTERVAL 1 WEEK, 1)
                           AND WEEKDAY(h.eventTime) IN (5,6)";
            break;
    
        case 3: // Last 7 days
            $dateFilter = "h.eventTime >= NOW() - INTERVAL 7 DAY";
            break;
    
        case 4: // Last 28 days
            $dateFilter = "h.eventTime >= NOW() - INTERVAL ".$g_options['MinActivity']." DAY";
            break;

        default:
            $rank_type  =0;
            $dateFilter = "1=1";
            break;
    }

    // ---------------------------------------------------------
    // GLOBAL RANKING (no history table)
    // ---------------------------------------------------------
    if ($rank_type == 0) {
    return "SELECT
               RANK() OVER (ORDER BY AVG(p.$rank_type1) DESC, SUM(p.$rank_type2) $sortorder2) AS rank_position,
               p.clan as clan,
               c.tag AS tag,
               c.name AS name,
               p.flag AS flag,
               p.country AS country,
               SUM(p.kills) AS kills,
               SUM(p.deaths) AS deaths,
               SUM(p.last_skill_change) AS last_skill_change,
               FLOOR(AVG(p.skill)) AS skill,
               SUM(p.shots) AS shots,
               SUM(p.hits) AS hits,
               SUM(p.headshots) AS headshots,
               MAX(p.activity) AS activity,
               SUM(p.connection_time) as connection_time,
               COUNT(p.playerId) AS members,
               ROUND(SUM(p.kills) / NULLIF(SUM(p.deaths), 0),2) AS kpd,
               ROUND(SUM(p.headshots) / NULLIF(SUM(p.kills), 0),2) AS hpk,
               COUNT(*) OVER() AS total_rows
           FROM
               hlstats_Players AS p
           LEFT JOIN
               hlstats_Clans AS c ON p.clan = c.clanId
           WHERE
               p.hideranking = 0 
               AND p.lastAddress <> ''
               AND p.game = '".$game."'
               AND p.clan > 0
           GROUP BY
               p.clan
           HAVING
               COUNT(p.playerId) > 1
           ORDER BY
               $sort $sortorder,
               $sort2 $sortorder2,
               connection_time DESC
           LIMIT 
               30 OFFSET $start
            ";
    }

    // ---------------------------------------------------------
    // DATE-FILTERED RANKING (history table)
    // ---------------------------------------------------------
    $rank1Col = ($rank_type1 === 'skill') ? 'p.skill' : 'h.'.$rank_type1;
    $rank2Col = ($rank_type2 === 'skill') ? 'p.skill' : 'h.'.$rank_type2;
    return "SELECT
               RANK() OVER (ORDER BY AVG($rank1Col) DESC, SUM($rank2Col) $sortorder2) AS rank_position,
               p.clan as clan,
               c.tag AS tag,
               c.name AS name,
               p.flag AS flag,
               p.country AS country,
               SUM(h.kills) AS kills,
               SUM(h.deaths) AS deaths,
               SUM(h.skill_change) AS last_skill_change,
               FLOOR(AVG(p.skill)) AS skill,
               SUM(h.shots) AS shots,
               SUM(h.hits) AS hits,
               SUM(h.headshots) AS headshots,
               MAX(p.activity) AS activity,
               SUM(h.connection_time) as connection_time,
               COUNT(p.playerId) AS members,
               ROUND(SUM(h.kills) / NULLIF(SUM(h.deaths), 0),2) AS kpd,
               ROUND(SUM(h.headshots) / NULLIF(SUM(h.kills), 0),2) AS hpk,
               COUNT(*) OVER() AS total_rows
           FROM
               hlstats_Players AS p
           INNER JOIN
               hlstats_Players_History AS h ON h.playerId = p.playerId
           LEFT JOIN
               hlstats_Clans AS c ON p.clan = c.clanId
           WHERE
               p.hideranking = 0
               AND p.lastAddress <> ''
               AND p.game = '".$game."'
               AND $dateFilter
               AND p.clan > 0
           GROUP BY
               p.clan
           HAVING
               COUNT(p.playerId) > 1
           ORDER BY
               $sort $sortorder,
               $sort2 $sortorder,
               connection_time DESC
           LIMIT 
               30 OFFSET $start
            ";

}

// Clan Rankings
$db->query("
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

if (!is_ajax()) {

?>

<?php printSectionTitle('Clan Rankings');	?>
<div class="hlstats-select">
    <form method="get" id="rankForm">
        <label for="selectType">Ranking View:</label>
        <select name="rank_type" id="selectType">
            <option value="0"<?php echo ($rank_type == 0?' selected':'') ?>>Global</option>
            <option value="1"<?php echo ($rank_type == 1?' selected':'') ?>>Yesterday</option>
            <option value="2"<?php echo ($rank_type == 2?' selected':'') ?>>Last Weekend</option>
            <option value="3"<?php echo ($rank_type == 3?' selected':'') ?>>Last 7 days</option>
            <option value="4"<?php echo ($rank_type == 4?' selected':'') ?>>Last <?=$g_options['MinActivity']?> days</option>
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
        <th class="<?= isSorted('rank_position', $sort, $sortorder) ?>"><?= headerUrl('rank_position',['sort','sortorder'],'clans') ?>Rank</a></th>
        <th class="hlstats-main-column left<?= isSorted('name', $sort, $sortorder) ?>"><?= headerUrl('name',['sort','sortorder'],'clans') ?>Clan</a></th>
        <th class="hide-3<?= isSorted('tag', $sort, $sortorder) ?>"><?= headerUrl('tag',['sort','sortorder'],'clans') ?>Tag</a></th>
        <?php  if ($g_options['rankingtype']!='kills') { ?>
        <th class="hide<?= isSorted('skill', $sort, $sortorder) ?>"><?= headerUrl('skill',['sort','sortorder'],'clans') ?>Points</a></th>
        <?php } ?>
        <th class="<?= isSorted('kills', $sort, $sortorder) ?>"><?= headerUrl('kills',['sort','sortorder'],'clans') ?>Kills</a></th>
        <th class="hide<?= isSorted('deaths', $sort, $sortorder) ?>"><?= headerUrl('deaths',['sort','sortorder'],'clans') ?>Deaths</a></th>
        <th class="hide-2<?= isSorted('kpd', $sort, $sortorder) ?>"><?= headerUrl('kpd',['sort','sortorder'],'clans') ?>K:D</a></th>
        <th class="hide-2<?= isSorted('headshots', $sort, $sortorder) ?>"><?= headerUrl('headshots',['sort','sortorder'],'clans') ?>Headshots</a></th>
        <th class="hide-2<?= isSorted('hpk', $sort, $sortorder) ?>"><?= headerUrl('hpk',['sort','sortorder'],'clans') ?>HS:K</a></th>
        <th class="<?= isSorted('members', $sort, $sortorder) ?>"><?= headerUrl('members',['sort','sortorder'],'clans') ?>Members</a></th>
        <th class="hide-3<?= isSorted('activity', $sort, $sortorder) ?>"><?= headerUrl('activity',['sort','sortorder'],'clans') ?>Activity</a></th>
        <th class="hide-1<?= isSorted('connection_time', $sort, $sortorder) ?>"><?= headerUrl('connection_time',['sort','sortorder'],'clans') ?>Connection Time</a></th>
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
                <td class="left'.$class.'" title="'.$sign.$res['last_skill_change'].'">⚔️ <a href="'.$g_options['scripturl'].'?mode=claninfo&amp;clan='.$res['clan'].'" title=""></span><span class="hlstats-name">'.htmlspecialchars($res['name']).'</span></a></td>
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
                   <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['activity'].'"></meter>
                   <div class="meter-value" id="meterText">'.$res['activity'].'%</div>
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