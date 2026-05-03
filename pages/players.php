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
        error("No such game.");
	}
$total     = 0;
$rank_type = (int)valid_request($_GET['rank_type'] ?? '', false);
$sortorder = valid_request($_GET['sortorder'] ?? '', false);
$sort      = valid_request($_GET['sort'] ?? '', false);

function qPlayersRank()
{
    global $game, $g_options, $sort, $sortorder, $rank_type;

    // Determine ranking columns
    if ($g_options['rankingtype'] !== 'kills') {
        $rank_type1 = 'skill';
        $rank_type2 = 'kills';
        $sortorder2 = 'DESC';
    } else {
        $rank_type1 = 'kills';
        $rank_type2 = 'deaths';
        $sortorder2 = 'ASC';
    }

    // Allowed sort columns
    $col = array("lastName","rank_position","skill","kills","deaths","kpd","headshots","hpk","acc","activity","connection_time");
    if (!in_array($sort, $col)) {
        $sort      = 'rank_position';
        $sortorder = 'ASC';
    }

    // Secondary sort
    if ($sort == $rank_type2) {
        $sort2 = $rank_type1;
        $sortorder2 = 'DESC';
    } else {
        $sort2 = $rank_type1;
        $sortorder2 = 'DESC';
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;

    // Date filter
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
        return "WITH Base AS (
                SELECT
                    p.playerId,
                    p.last_event,
                    p.connection_time,
                    p.lastName,
                    p.flag,
                    p.country,
                    p.kills,
                    p.deaths,
                    p.skill,
                    p.shots,
                    p.hits,
                    p.headshots,
                    p.last_skill_change,
                    p.kill_streak,
                    p.death_streak,
                    p.activity,
                    uid.uniqueId
                FROM hlstats_players p
                LEFT JOIN hlstats_PlayerUniqueIds uid ON uid.playerId = p.playerId
                WHERE p.hideranking = 0
                AND p.lastAddress <> ''
                AND p.game = '$game'
            ),
            Ranked AS (
                SELECT
                    *,
                    RANK() OVER (ORDER BY $rank_type1 DESC, $rank_type2 $sortorder2) AS rank_position,
                    COUNT(*) OVER() AS total_rows
                FROM Base
            )
            SELECT *,
                ROUND(IF(deaths=0, 0, kills/deaths), 2) AS kpd,
                ROUND(IF(kills=0, 0, headshots/kills), 2) AS hpk,
                ROUND(IF(shots=0, 0, hits/shots), 3) AS acc
            FROM Ranked
            ORDER BY $sort $sortorder,
                    $sort2 $sortorder2,
                    connection_time DESC
            LIMIT 30 OFFSET $start
        ";
    }

    // ---------------------------------------------------------
    // DATE-FILTERED RANKING (history table)
    // ---------------------------------------------------------
    return "WITH Aggregated AS (
            SELECT
                h.playerId,
                SUM(h.connection_time) AS connection_time,
                SUM(h.kills) AS kills,
                SUM(h.deaths) AS deaths,
                SUM(h.skill_change) AS skill,
                SUM(h.shots) AS shots,
                SUM(h.hits) AS hits,
                SUM(h.headshots) AS headshots,
                MAX(h.kill_streak) AS kill_streak,
                MAX(h.death_streak) AS death_streak
            FROM hlstats_players_history h
            WHERE $dateFilter
            AND h.game = '$game'
            GROUP BY h.playerId
        ),
        Base AS (
            SELECT
                a.playerId,
                p.last_event,
                a.connection_time,
                p.lastName,
                p.flag,
                p.country,
                uid.uniqueId,
                a.kills,
                a.deaths,
                a.skill,
                a.shots,
                a.hits,
                a.headshots,
                p.last_skill_change,
                a.kill_streak,
                a.death_streak,
                p.activity
            FROM Aggregated a
            JOIN hlstats_players p ON p.playerId = a.playerId
            LEFT JOIN hlstats_PlayerUniqueIds uid ON uid.playerId = a.playerId
            WHERE p.hideranking = 0
            AND p.lastAddress <> ''
        ),
        Ranked AS (
            SELECT
                *,
                RANK() OVER (ORDER BY $rank_type1 DESC, $rank_type2 $sortorder2) AS rank_position,
                COUNT(*) OVER() AS total_rows
            FROM Base
        )
        SELECT *,
            ROUND(IF(deaths=0, 0, kills/deaths), 2) AS kpd,
            ROUND(IF(kills=0, 0, headshots/kills), 2) AS hpk,
            ROUND(IF(shots=0, 0, hits/shots), 3) AS acc
        FROM Ranked
        ORDER BY $sort $sortorder,
                $sort2 $sortorder2,
                connection_time DESC
        LIMIT 30 OFFSET $start
    ";
}

if (!is_ajax()) {
    printSectionTitle('Player Rankings');
?>
<div class="hlstats-select">
    <form method="get" action="" id="rankForm">
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

<div id="players">
<?php
}

$query  = qPlayersRank();

echo '<div class="responsive-table">
<table class="players-table">
    <tr>
        <th class="'. isSorted('rank_position', $sort, $sortorder). '">'. headerUrl('rank_position',['sort','sortorder'],'players') .'Rank</a></th>
        <th class="hlstats-main-column left'. isSorted('lastName', $sort, $sortorder) .'">'. headerUrl('lastName',['sort','sortorder'],'players') .'Player</a></th>';
        if ($g_options['rankingtype']!='kills') {
        echo '<th class="'. isSorted('skill', $sort, $sortorder) .'">'. headerUrl('skill',['sort','sortorder'],'players') .'Points</a></th>';
        }
        echo '
        <th class="'. isSorted('kills', $sort, $sortorder) .'">'. headerUrl('kills',['sort','sortorder'],'players') .'Kills</a></th>
        <th class="hide'. isSorted('deaths', $sort, $sortorder) .'">'. headerUrl('deaths',['sort','sortorder'],'players') .'Deaths</a></th>
        <th class="hide-2'. isSorted('kpd', $sort, $sortorder) .'">'. headerUrl('kpd',['sort','sortorder'],'players') .'K:D</a></th>
        <th class="hide-2'. isSorted('headshots', $sort, $sortorder) .'">'. headerUrl('headshots',['sort','sortorder'],'players') .'Headshots</a></th>
        <th class="hide-2'. isSorted('hpk', $sort, $sortorder) .'">'. headerUrl('hpk',['sort','sortorder'],'players') .'HS:K</a></th>
        <th class="hide-1'. isSorted('acc', $sort, $sortorder) .'">'. headerUrl('acc',['sort','sortorder'],'players') .'Accuracy</a></th>
        <th class="hide-3'. isSorted('activity', $sort, $sortorder) .'">'. headerUrl('activity',['sort','sortorder'],'players') .'Activity</a></th>
        <th class="hide-1'. isSorted('connection_time', $sort, $sortorder) .'">'. headerUrl('connection_time',['sort','sortorder'],'players') .'Connection Time</a></th>
    </tr>';

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
            if (preg_match("/^BOT:/", $res['uniqueId'] ?? '')) {
                $res['flag'] = 'bot';
                $res['country'] = "i'm a bot";
            }
            echo '
            <tr>
                <td class="nowrap right">'.$res['rank_position'].'</td>
                <td class="left'.$class.'" title="'.$sign.$res['last_skill_change'].'">';
                if ($g_options['countrydata']) {
                    echo '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" title="'.$res['country'].'" alt="'.$res['flag'].'"></span>';
                }
                echo '<a href="?mode=playerinfo&amp;player='.$res['playerId'].'" title=""><span class="hlstats-name">'.htmlspecialchars($res['lastName']).'&nbsp;</span></a>
                </td>'
               .($g_options['rankingtype'] != 'kills' ? ('<td class="nowrap">'.nf($res['skill']).'</td>'):'').
                '<td class="nowrap">'.nf($res['kills']).'</td>
                <td class="nowrap hide">'.nf($res['deaths']).'</td>
                <td class="nowrap hide-1">'.$res['kpd'].'</td>
                <td class="nowrap hide-2">'.nf($res['headshots']).'</td>
                <td class="nowrap hide-2">'.$res['hpk'].'</td>
                <td class="nowrap hide-2">'.($res['acc']*100).'%</td>
                <td class="meter-ratio nowrap hide-3">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['activity'].'" title="'.date('l, F j, Y @g:i A', $res['last_event']).'"></meter>
                      <div class="meter-value" id="meterText">'.$res['activity'].'%</div>
                    </div>
                </td>
                <td class="nowrap hide-1">'.$time.'</td>
            </tr>';
        }
    }
    echo  '</table></div>'.
          Pagination($total, $_GET['page'] ?? 1, 30, 'page');

if (is_ajax()) exit();

$updatedQuery = updateQueryKey(['rank_type' => '', 'page' => '']);
$baseUrl = $_SERVER['PHP_SELF'].'?'.$updatedQuery.'&rank_type=';

?>
</div>

<script>
Fetch.ini('players');
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