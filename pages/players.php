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
require_once INCLUDE_PATH . '/players_rank.php';

$total     = 0;
$rank_type = (int)valid_request($_GET['rank_type'] ?? '', false);
$sortorder = valid_request($_GET['sortorder'] ?? '', false);
$sort      = valid_request($_GET['sort'] ?? '', false);
$page      = max(1, (int)($_GET['page'] ?? 1));

// Allowed sort columns
$col = array("lastName","rank_position","skill","kills","deaths","kpd","headshots","hpk","acc","activity","connection_time");
if (!in_array($sort, $col)) {
    $sort      = 'rank_position';
    $sortorder = 'ASC';
}
$sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

// Date filter
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
        $rank_type  = 0;
        $dateFilter = "1=1";
        break;
}

// The page with each player's true rank (includes/players_rank.php)
$ranking = $rank_type == 0
    ? rankPlayers($game, $sort, $sortorder, $page)
    : rankPlayersHistory($game, $sort, $sortorder, $page, $dateFilter);
$total = $ranking['total'];

if (!is_ajax()) {
    printSectionTitle(t('title.player.rankings'));
?>
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

<div id="players">
<?php
}

echo '<div class="responsive-table">
<table class="players-table">
    <tr>
        <th class="hlstats-ranking nowrap'. isSorted('rank_position', $sort, $sortorder). '">'. headerUrl('rank_position',['sort','sortorder'],'players') .t('th.rank').'</a></th>
        <th class="hlstats-main-column left'. isSorted('lastName', $sort, $sortorder) .'">'. headerUrl('lastName',['sort','sortorder'],'players') .t('player').'</a></th>';
        if ($g_options['rankingtype']!='kills') {
        echo '<th class="'. isSorted('skill', $sort, $sortorder) .'">'. headerUrl('skill',['sort','sortorder'],'players') .t('th.points').'</a></th>';
        }
        echo '
        <th class="'. isSorted('kills', $sort, $sortorder) .'">'. headerUrl('kills',['sort','sortorder'],'players') .t('th.kills').'</a></th>
        <th class="hide'. isSorted('deaths', $sort, $sortorder) .'">'. headerUrl('deaths',['sort','sortorder'],'players') .t('th.deaths').'</a></th>
        <th class="hide-2'. isSorted('kpd', $sort, $sortorder) .'">'. headerUrl('kpd',['sort','sortorder'],'players') .t('th.kd').'</a></th>
        <th class="hide-2'. isSorted('headshots', $sort, $sortorder) .'">'. headerUrl('headshots',['sort','sortorder'],'players') .t('th.headshots').'</a></th>
        <th class="hide-2'. isSorted('hpk', $sort, $sortorder) .'">'. headerUrl('hpk',['sort','sortorder'],'players') .t('th.hsk').'</a></th>
        <th class="hide-1'. isSorted('acc', $sort, $sortorder) .'">'. headerUrl('acc',['sort','sortorder'],'players') .t('th.accuracy').'</a></th>
        <th class="hide-3'. isSorted('activity', $sort, $sortorder) .'">'. headerUrl('activity',['sort','sortorder'],'players') .t('th.activity').'</a></th>
        <th class="hide-1'. isSorted('connection_time', $sort, $sortorder) .'">'. headerUrl('connection_time',['sort','sortorder'],'players') .t('th.connection.time').'</a></th>
    </tr>';

        foreach ($ranking['rows'] as $res)
        {
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
                <td class="left'.$class.'" data-tooltip="'.t('last.points',["{last}" => htmlspecialchars($sign.$res['last_skill_change'], ENT_QUOTES)]).'">';
                if ($g_options['countrydata']) {
                    echo '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" data-tooltip="'.htmlspecialchars($res['country'], ENT_QUOTES).'" alt="'.$res['flag'].'"></span>';
                }
                echo '<a href="?mode=playerinfo&amp;player='.$res['playerId'].'"><span class="hlstats-name">'.htmlspecialchars(html_entity_decode($res['lastName'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_COMPAT).'&nbsp;</span></a>
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
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.max(0, (int) $res['activity']).'" data-tooltip="'.htmlspecialchars(formatDate($res['last_event']), ENT_QUOTES).'"></meter>
                      <div class="meter-value" id="meterText">'.max(0, (int) $res['activity']).'%</div>
                    </div>
                </td>
                <td class="nowrap hide-1">'.$time.'</td>
            </tr>';
        }
    echo  '</table></div>'.
          Pagination($total, $page, 30, 'page');

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