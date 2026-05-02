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

    // Player History -> Sessions & Skill change
	$player = valid_request(intval($_GET["player"] ?? 0), true) or error("No player ID specified.");

	$db->query("
		SELECT
			hlstats_Players.lastName,
			hlstats_Players.game
		FROM
			hlstats_Players
		WHERE
			playerId = $player
	");

	if ($db->num_rows() != 1) {
		error("No such player '$player'.");
	}

	$playerdata = $db->fetch_array();
	$pl_name = $playerdata['lastName'];

    if (strlen($pl_name) > 10) {
		$pl_shortname = substr($pl_name, 0, 8) . '...';
	} else {
		$pl_shortname = $pl_name;
	}

	$pl_name = htmlspecialchars($pl_name, ENT_COMPAT);
	$pl_shortname = htmlspecialchars($pl_shortname, ENT_COMPAT);
	$game = $playerdata['game'];

    $db->query("
		SELECT
			hlstats_Games.name
		FROM
			hlstats_Games
		WHERE
			hlstats_Games.code = '$game'
	");

	if ($db->num_rows() != 1) {
		$gamename = ucfirst($game);
	} else {
		list($gamename) = $db->fetch_row();
	}

	$surl = $g_options['scripturl'];
    
    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';

    $col = array("eventTime","skill_change","skill","connection_time","kills","deaths","kpd","headshots","hpk","teamkills","kill_streak","suicides");
    if (!in_array($sort, $col)) {
        $sort      = "eventTime";
        $sortorder = "DESC";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;

	$result = $db->query
	("
		SELECT
			hlstats_Players_History.eventTime,
			hlstats_Players_History.skill_change,
			hlstats_Players_History.skill,
			hlstats_Players_History.kills,
			hlstats_Players_History.deaths,
			hlstats_Players_History.headshots,
			hlstats_Players_History.suicides,
			hlstats_Players_History.connection_time,
			ROUND(hlstats_Players_History.kills/(IF(hlstats_Players_History.deaths = 0, 1, hlstats_Players_History.deaths)), 2) AS kpd,
			ROUND(hlstats_Players_History.headshots/(IF(hlstats_Players_History.kills = 0, 1, hlstats_Players_History.kills)), 2) AS hpk,
			hlstats_Players_History.teamkills,
			hlstats_Players_History.kill_streak,
			hlstats_Players_History.death_streak,
			hlstats_Players_History.skill_change AS last_skill_change
		FROM
			hlstats_Players_History
		WHERE
			hlstats_Players_History.playerId = $player
		ORDER BY
			$sort $sortorder
		LIMIT 30 OFFSET $start
	");
    
	$resultCount = $db->query
	("
		SELECT
			COUNT(*)
		FROM
			hlstats_Players_History
		WHERE
			hlstats_Players_History.playerId = $player
	");
	list($numitems) = $db->fetch_row($resultCount);

if (!is_ajax()) {
	printSectionTitle($pl_name.'\'s Session History');

echo '<div id="sessions">';

}
if ($numitems > 0) {

?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="left<?= isSorted('eventTime',$sort,$sortorder) ?>"><?= headerUrl('eventTime', ['sort','sortorder'], 'sessions') ?>Date</a></th>
        <th class="<?= isSorted('skill',$sort,$sortorder) ?>"><?= headerUrl('skill', ['sort','sortorder'], 'sessions') ?>Points</a></th>
        <th class="hide left<?= isSorted('skill_change',$sort,$sortorder) ?>"><?= headerUrl('skill_change', ['sort','sortorder'], 'sessions') ?>Change</a></th>
        <th class="<?= isSorted('kills',$sort,$sortorder) ?>"><?= headerUrl('kills', ['sort','sortorder'], 'sessions') ?>Kills</a></th>
       <th class="hide-1<?= isSorted('deaths',$sort,$sortorder) ?>"><?= headerUrl('deaths', ['sort','sortorder'], 'sessions') ?>Deaths</a></th>
       <th class="hide-2<?= isSorted('kpd',$sort,$sortorder) ?>"><?= headerUrl('kpd', ['sort','sortorder'], 'sessions') ?>K:D</a></th>
       <th class="hide<?= isSorted('headshots',$sort,$sortorder) ?>"><?= headerUrl('headshots', ['sort','sortorder'], 'sessions') ?>Headshots</a></th>
      <th class="hide-2<?= isSorted('hpk',$sort,$sortorder) ?>"><?= headerUrl('hpk', ['sort','sortorder'], 'sessions') ?>HS:K</a></th>
      <th class="hide-3<?= isSorted('teamkills',$sort,$sortorder) ?>"><?= headerUrl('teamkills', ['sort','sortorder'], 'sessions') ?>TKs</a></th>
      <th class="hide-1<?= isSorted('suicides',$sort,$sortorder) ?>"><?= headerUrl('suicides', ['sort','sortorder'], 'sessions') ?>Suicides</a></th>
      <th class="hide-2<?= isSorted('kill_streak',$sort,$sortorder) ?>"><?= headerUrl('kill_streak', ['sort','sortorder'], 'sessions') ?>K-streak</a></th>
      <th class="hide<?= isSorted('connection_time',$sort,$sortorder) ?>"><?= headerUrl('connection_time', ['sort','sortorder'], 'sessions') ?>Time</a></th>
      
   </tr>
    <?php
        $i= 1 + $start;
        while ($res = $db->fetch_array($result))
        {
            $sign    = '';
            $class   = ' skill';
            if ($res['last_skill_change'] > 0) {
                $sign = '+';
                $class .= ' up green';
            }
            if ($res['last_skill_change'] < 0) {
                $class .= ' down red';
            }

            echo '<tr>
                  <td class="nowrap left">'.str_replace(" ","<br>@",$res['eventTime']).'</td>
                  <td class="nowrap">'.nf($res['skill']).'</td>
                  <td class="nowrap hide'.$class.'">'.$sign.$res['skill_change'].'</td>
                  <td class="nowrap">'.nf($res['kills']).'</td>
                  <td class="nowrap hide-1">'.nf($res['deaths']).'</td>
                  <td class="nowrap hide-2">'.$res['kpd'].'</td>
                  <td class="nowrap hide">'.nf($res['headshots']).'</td>
                  <td class="nowrap hide-2">'.$res['hpk'].'</td>
                  <td class="nowrap hide-3">'.$res['teamkills'].'</td>
                  <td class="nowrap hide-1">'.$res['suicides'].'</td>
                  <td class="nowrap hide-2">'.$res['kill_streak'].'</td>
                  <td class="nowrap hide">'.TimeStamp($res['connection_time']).'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
</div>
   <?php
       echo Pagination($numitems, $_GET['page'] ?? 1, 30, 'page', true, 'sessions');
  if (is_ajax()) exit;
  ?>
<?php
}
?>
</div>
<div>
    &larr;&nbsp;<a href="<?php echo $g_options['scripturl'] . "?mode=playerinfo&amp;player=$player"; ?>"><?php echo $pl_name; ?>'s Statistics</a>
</div>
<?php if ($g_options['DeleteDays']) { ?>
<div class="hlstats-note">
    Items above are generated from the most recent <?= $g_options['DeleteDays'] ?> days of activity.
</div>
<?php } ?>
