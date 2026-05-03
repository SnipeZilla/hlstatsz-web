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

	// Country Details
	if (!$game) {
        error("No such game.");
	}
	$flag = valid_request($_GET['flag'] ?? '', false) or error('No country ID specified.');

	$SQL = "
        SELECT
            c.flag,
            c.name,
            COUNT(DISTINCT p.playerId) AS nummembers,
            SUM(p.kills) AS kills,
            SUM(p.deaths) AS deaths,
            SUM(p.connection_time) AS connection_time,
            ROUND(AVG(p.skill)) AS avgskill,
            IFNULL(SUM(p.kills) / NULLIF(SUM(p.deaths), 0), '-') AS kpd,
            TRUNCATE(MAX(p.activity), 2) AS activity
        FROM hlstats_Countries c
        JOIN hlstats_Players p
            ON p.flag = c.flag
        WHERE p.game = '$game'
        AND p.flag = '$flag'
        AND p.hideranking = 0
        AND p.lastAddress <> ''
        GROUP BY c.flag;
	";
	
	$db->query($SQL);
	if ($db->num_rows() != 1)
		error("No such countryclan '$flag'.");
	
	$clandata = $db->fetch_array();
	$db->free_result();
	
	
	$cl_name = str_replace(' ', '&nbsp;', htmlspecialchars($clandata['name'] ?? ''));
	$cl_tag  = str_replace(' ', '&nbsp;', htmlspecialchars($clandata['tag'] ?? ''));
	$cl_full = "$cl_tag $cl_name";

	if (!is_ajax()) {

    printSectionTitle('Country Information');
?>
<div class="hlstats-cards-grid">
  <section class="hlstats-section hlstats-card">
    <div class="hlstats-card-title">Statistics Summary</div>
    <div class="hlstats-pname"><?= $clandata['name'] ?></div>
    <div class="hlstats-card-body hlstats-card-grid">
      <div class="label">Members:</div>
      <div class="value"><strong><?= $clandata['nummembers'] ?></strong><em> members</em></div>
      <div class="label">Activity</div>
      <div class="value meter-ratio">
        <div class="meter-container">
          <meter min="0" max="100" low="25" high="50" optimum="75" value="<?= $clandata['activity'] ?>"></meter>
          <div class="meter-value" id="meterText"><?= $clandata['activity'] ?>%</div>
        </div>
      </div>
      <div class="label">Total Kills:</div>
      <div class="value"><?= nf($clandata['kills']) ?></div>
      <div class="label">Total Deaths:</div>
      <div class="value"><?= nf($clandata['deaths']) ?></div>
      <div class="label">Avg. Kills:</div>
      <div class="value"><?php if ($clandata['nummembers'] > 0) { echo nf($clandata['kills'] / ($clandata['nummembers'])); } else { echo'-'; } ?></div>
      <div class="label">Kills per Death:</div>
      <div class="value"><?php
                          if ($clandata['deaths'] != 0)
                          {
                              printf('%0.2f', $clandata['kills'] / $clandata['deaths']);
                          }
                          else
                          {
                              echo '-';
                          }
                         ?>
      </div>
      <div class="label">Kills per Minute:</div>
      <div class="value"><?php
                          if ($clandata['connection_time'] > 0) {
                              echo sprintf('%.2f', ($clandata['kills'] / ($clandata['connection_time'] / 60)));
                          } else {
                              echo '-'; 
                          }
                         ?>
      </div>
      <div class="label">Avg. Member Points:</div>
      <div class="value"><?= nf($clandata['avgskill']) ?></div>
      <div class="label">Avg. Connection Time:</div>
      <div class="value"><?php
                          if ($clandata['connection_time'] > 0) {
                              echo TimeStamp($clandata['connection_time'] / ($clandata['nummembers']));
                          } else {
                              echo '-'; 
                          }
                         ?>
      </div>
      <div class="label">Total Connection Time:</div>
      <div class="value"><?= TimeStamp($clandata['connection_time']); ?></div>


  </div>
</section>
<?php
if (file_exists(IMAGE_PATH.'/flags/'.strtolower($flag).'_large.png')) {

  echo '<section class="hlstats-section hlstats-card">';
  echo '<img src="'.IMAGE_PATH.'/flags/'.strtolower($flag).'_large.png" style="border:0px;" alt="'.$flag.'" />';
  echo '</section>';

}
?>
</div>

<?php
    ob_flush();
    flush();

}
    if ($g_options['rankingtype'] !== 'kills') {
        $rank_type1 = 'skill';
        $rank_type2 = 'kills';
        $sortorder2 = 'DESC';
    } else {
        $rank_type1 = 'kills';
        $rank_type2 = 'deaths';
        $sortorder2 = 'ASC';
    }

    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';
    $sort2     = "kills";

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

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 20 : 0;

	$result = $db->query("WITH Base AS (
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
                WHERE flag = '$flag'
                ORDER BY $sort $sortorder,
                        $sort2 $sortorder2,
                        connection_time DESC
                LIMIT 20 OFFSET $start;
            ");

if ($db->num_rows($result)) {
    if (!is_ajax()){
	printSectionTitle('Members');
	echo '<div id="members">';
    }

echo '<div  class="responsive-table">
<table class="players-table">
    <tr>
        <th class="'. isSorted('rank_position', $sort, $sortorder). '">'. headerUrl('rank_position',['sort','sortorder'],'members') .'Rank</a></th>
        <th class="hlstats-main-column left'. isSorted('lastName', $sort, $sortorder) .'">'. headerUrl('lastName',['sort','sortorder'],'members') .'Player</a></th>';
        if ($g_options['rankingtype']!='kills') {
        echo '<th class="'. isSorted('skill', $sort, $sortorder) .'">'. headerUrl('skill',['sort','sortorder'],'members') .'Points</a></th>';
        }
        echo '
        <th class="hide'. isSorted('kills', $sort, $sortorder) .'">'. headerUrl('kills',['sort','sortorder'],'members') .'Kills</a></th>
        <th class="hide-1'. isSorted('deaths', $sort, $sortorder) .'">'. headerUrl('deaths',['sort','sortorder'],'members') .'Deaths</a></th>
        <th class="hide-3'. isSorted('kpd', $sort, $sortorder) .'">'. headerUrl('kpd',['sort','sortorder'],'members') .'K:D</a></th>
        <th class="hide'. isSorted('headshots', $sort, $sortorder) .'">'. headerUrl('headshots',['sort','sortorder'],'members') .'Headshots</a></th>
        <th class="hide-3'. isSorted('hpk', $sort, $sortorder) .'">'. headerUrl('hpk',['sort','sortorder'],'members') .'HS:K</a></th>
        <th class="hide-1'. isSorted('acc', $sort, $sortorder) .'">'. headerUrl('acc',['sort','sortorder'],'members') .'Accuracy</a></th>
        <th class="hide-2'. isSorted('activity', $sort, $sortorder) .'">'. headerUrl('activity',['sort','sortorder'],'members') .'Activity</a></th>
        <th class="hide-1'. isSorted('connection_time', $sort, $sortorder) .'">'. headerUrl('connection_time',['sort','sortorder'],'members') .'Connection Time</a></th>
    </tr>';


    while ($res = $db->fetch_array($result))
    {
        $time    = TimeStamp($res['connection_time']);
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
            <td class="left'.$class.'" title="'.$sign.$res['last_skill_change'].'">
                <span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" title="'.$res['country'].'" alt="'.$res['flag'].'"></span>
                <a href="?mode=playerinfo&amp;last_event='.$res['last_event'].'&amp;player='.$res['playerId'].'" title=""><span class="hlstats-name">'.htmlspecialchars($res['lastName']).'&nbsp;</span></a>
            </td>'
           .($g_options['rankingtype'] != 'kills' ? ('<td class="nowrap">'.nf($res['skill']).'</td>'):'').
            '<td class="nowrap hide">'.nf($res['kills']).'</td>
            <td class="nowrap hide-1">'.nf($res['deaths']).'</td>
            <td class="nowrap hide-3">'.$res['kpd'].'</td>
            <td class="nowrap hide">'.nf($res['headshots']).'</td>
            <td class="nowrap hide-3">'.$res['hpk'].'</td>
            <td class="nowrap hide-1">'.($res['acc']*100).'%</td>
            <td class="nowrap hide-2">
                <div class="meter-container">
                  <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['activity'].'" title="'.date('l, F j, Y @g:i A', $res['last_event']).'"></meter>
                  <div class="meter-value" id="meterText">'.$res['activity'].'%</div>
                </div>
            </td>
            <td class="nowrap hide-1">'.$time.'</td>
        </tr>';
    }

    echo  '</table></div>'.
          Pagination($clandata['nummembers'], $_GET['page'] ?? 1, 30, 'page', true,'members');

if (is_ajax()) exit();

echo '</div>';
}
?>