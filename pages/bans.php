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

function qPlayersRank()
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

    $col = array("lastName","rank_position","skill","kills","deaths","kpd","headshots","hpk","acc","ban_date","connection_time");
    if (!in_array($sort, $col)) {
        $sort      = 'rank_position';
        $sortorder = 'ASC';
    }
    
    if ($sort == $rank_type2) {
        $sort2 = $rank_type1;
        $sortorder2 = 'DESC';
    } else { $sort2 = $rank_type1; }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;
    
            return "
                WITH RankedPlayers AS (
                    SELECT
                        RANK() OVER (ORDER BY $rank_type1 DESC, $rank_type2 $sortorder2) AS rank_position,
                        playerId,
                        FROM_UNIXTIME(last_event,'%Y.%m.%d %T') as ban_date,
                        connection_time,
                        lastName,
                        flag,
                        country,
                        kills,
                        deaths,
                        skill,
                        shots,
                        hits,
                        headshots,
                        last_skill_change,
                        kill_streak,
                        death_streak,
                        ROUND(IF(deaths=0, 0, kills/deaths), 2) AS kpd,
                        ROUND(IF(kills=0, 0, headshots/kills), 2) AS hpk,
                        ROUND(IF(shots=0, 0, hits/shots), 3) AS acc,
                        COUNT(*) OVER() AS total_rows
                    FROM hlstats_players
                    WHERE hideranking = 2
                      AND game = '".$game."'
                )
                SELECT *
                FROM RankedPlayers
                ORDER BY $sort $sortorder,
                         $sort2 $sortorder2
                LIMIT 30 OFFSET $start
            ";

}

// Player Rankings

if (!is_ajax()) {

?>

<?php printSectionTitle('Cheaters & Banned Players');	?>


<div id="players">
<?php
}

$query  = qPlayersRank();
$result = $db->query($query);
if ($db->num_rows($result)) {
echo '
    <div class="responsive-table">
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
        <th class="hide-2'. isSorted('acc', $sort, $sortorder) .'">'. headerUrl('acc',['sort','sortorder'],'players') .'Accuracy</a></th>
        <th class="hide'. isSorted('connection_time', $sort, $sortorder) .'">'. headerUrl('connection_time',['sort','sortorder'],'players') .'Connection Time</a></th>
        <th class="hide'. isSorted('ban_date', $sort, $sortorder) .'">'. headerUrl('ban_date',['sort','sortorder'],'players') .'Ban Date</a></th>
    </tr>';


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
                <td class="left'.$class.'" title="'.$sign.$res['last_skill_change'].'">';
                if ($g_options['countrydata']) {
                   echo '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" title="'.$res['country'].'" alt="'.$res['flag'].'"></span>';
                }
                   echo '<a href="?mode=playerinfo&amp;player='.$res['playerId'].'" title=""><span class="hlstats-name">'.htmlspecialchars($res['lastName']).'&nbsp;</span></a>
                </td>'
               .($g_options['rankingtype'] != 'kills' ? ('<td class="nowrap">'.nf($res['skill']).'</td>'):'').
                '<td class="nowrap">'.nf($res['kills']).'</td>
                <td class="nowrap hide">'.nf($res['deaths']).'</td>
                <td class="nowrap hide-2">'.$res['kpd'].'</td>
                <td class="nowrap hide-2">'.nf($res['headshots']).'</td>
                <td class="nowrap hide-2">'.$res['hpk'].'</td>
                <td class="nowrap hide-2">'.($res['acc']*100).'%</td>
                <td class="nowrap hide">'.$time.'</td>
                <td class="nowrap hide">'.str_replace(" ","<br>@",$res['ban_date']).'</td>
            </tr>';
        }

    echo  '</table></div>'.
          Pagination($total, $_GET['page'] ?? 1, 30, 'page');

if (is_ajax()) exit();
} else { echo '<p class="hlstats-no-data"><em>Not enough data</em></p>'; }
$updatedQuery = updateQueryKey(['rank_type' => '', 'page' => '']);
$baseUrl = $_SERVER['PHP_SELF'].'?'.$updatedQuery.'&rank_type=';

?>
</div>

<script>
Fetch.ini('players');
const selectElement = document.getElementById('selectType');
rank_type = '<?= $rank_type?>';
document.getElementById('rankType').addEventListener('click', function (e) {
    e.preventDefault();
    const rankType = selectElement.value;

    if (rank_type == rankType) return false;
    
    rank_type = rankType;
    Fetch.run('<?=$baseUrl?>'+rankType);
});
</script>
