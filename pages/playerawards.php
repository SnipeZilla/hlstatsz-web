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

    // Player Awards History
	$player = valid_request($_GET['player'] ?? '', true) or error("No player ID specified.");

	$db->query("
		SELECT
			hlstats_Players.lastName,
			hlstats_Players.game
		FROM
			hlstats_Players
		WHERE
			hlstats_Players.playerId = $player
	");

	if ($db->num_rows() != 1) {
		error("No such player '$player'.");
	}

	$playerdata = $db->fetch_array();
	$pl_name = $playerdata['lastName'];

	if (strlen($pl_name) > 10) {
		$pl_shortname = substr($pl_name, 0, 8) . "...";
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

    ob_flush();
    flush();
	$cnttext = 'Earned';
	$lnktext = '&link='.urlencode("mode=playerawards&player=".$player."&amp;awardId=%k");
	if (isset($_GET['awardId'])) {
		$awardId = valid_request($_GET['awardId'] ?? '', true) or error("No clan ID specified."); 
	}

	$cnttext = 'Kills on Day';
	$lnktext = '';

    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';

    $col = array("awardTime","name","verb","count");
    if (!in_array($sort, $col)) {
        $sort      = "awardTime";
        $sortorder = "DESC";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;
    
	if (isset($awardId))
	{
		$result = $db->query
		("
			SELECT
				hlstats_Players_Awards.awardTime,
                hlstats_Awards.name,
				hlstats_Awards.verb,
                hlstats_Awards.awardType,
				hlstats_Awards.code,
				hlstats_Players_Awards.count,
				hlstats_Awards.awardId
			FROM
				hlstats_Players_Awards
			INNER JOIN
				hlstats_Awards
			ON
				hlstats_Awards.awardId = hlstats_Players_Awards.awardId
			WHERE
				hlstats_Players_Awards.playerId = $player
				AND hlstats_Players_Awards.awardId = $awardId
			ORDER BY $sort $sortorder
			LIMIT 30 OFFSET $start
		");
		$resultCount = $db->query
		("
			SELECT
				COUNT(awardId)
			FROM
				hlstats_Players_Awards
			WHERE
				hlstats_Players_Awards.playerId = $player
				AND hlstats_Players_Awards.awardId = $awardId
		");
	}
	else
	{

         $result = $db->query("
             SELECT
                 MAX(pa.awardTime) AS awardTime,
                 a.name,
                 a.verb,
                 a.code,
                 a.awardType,
                 COUNT(a.verb) AS count,
                 a.awardId
             FROM hlstats_Players_Awards pa
             INNER JOIN hlstats_Awards a
                 ON a.awardId = pa.awardId
             WHERE pa.playerId = $player
             GROUP BY a.awardId, a.name, a.verb
             ORDER BY $sort $sortorder
             LIMIT 30 OFFSET $start
         ");

         $resultCount = $db->query("
             SELECT COUNT(*) AS numGroups
             FROM (
                 SELECT awardId
                 FROM hlstats_Players_Awards
                 WHERE playerId = $player
                 GROUP BY awardId
             ) AS sub
         ");
	}
	list($numitems) = $db->fetch_row($resultCount);

if (!is_ajax()) {
	printSectionTitle($pl_name.'\'s Awards History');
}
	if ($numitems > 0)
	{

   if (!is_ajax()) {

echo '<div id="playerawards">';

}
?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="nowarp left<?= isSorted('awardTime',$sort,$sortorder) ?>" style="width:1%"><?= headerUrl('awardTime', ['sort','sortorder'], 'playerawards') ?>Date</a></th>
        <th class="hlstats-main-description left<?= isSorted('name',$sort,$sortorder) ?>"><?= headerUrl('name', ['sort','sortorder'], 'playerawards') ?>Name</a></th>
        <th class="hlstats-main-description left hide<?= isSorted('verb',$sort,$sortorder) ?>"><?= headerUrl('verb', ['sort','sortorder'], 'playerawards') ?>Description</a></th>
        <th class="<?= isSorted('count',$sort,$sortorder) ?>"><?= headerUrl('count', ['sort','sortorder'], 'playerawards') ?>Count</a></th>
    </tr>
    <?php
        $i= 1 + $start;
        while ($res = $db->fetch_array($result))
        {
        if ($image = getImage("/games/$game/dawards/".strtolower($res['awardType'].'_'.$res['code'])))
        {
            $img = $image['url'];
        }
        elseif ($image = getImage("/games/$realgame/dawards/".strtolower($res['awardType'].'_'.$res['code'])))
        {
            $img = $image['url'];
        }
        else
        {
            $img = IMAGE_PATH.'/award.png';
        }
    
            echo '<tr>
                  <td class="nowrap left">'.$res['awardTime'].'</td>
                  <td class="hlstats-main-description left"><span class="hlstats-icon"><img src="'.$img.'" alt="'.$res['code'].'" /></span><span class="hlstats-name">'.htmlspecialchars($res['name']).'</span></td>
                  <td class="hlstats-main-description left hide">'.htmlspecialchars($res['verb']).'</td>
                  <td class="nowrap">'.$res['count'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($numitems, $_GET['page'] ?? 1, 30, 'page');

  if (is_ajax()) exit;
  ?>
</div>
<script>
Fetch.ini('playerawards');
</script>
<?php
    } else { echo '<p class="hlstats-no-data"><em>Not enough data</em></p>'; }
    ?>
<div>
    <a href="<?php echo $g_options['scripturl'] . "?mode=playerinfo&amp;player=$player"; ?>">&larr;&nbsp;<?php echo $pl_name; ?>'s Statistics</a>
</div>
