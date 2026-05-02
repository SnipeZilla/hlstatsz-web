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

    // Player Chat History
	$player = valid_request(intval($_GET['player'] ?? 0), true) or error('No player ID specified.');

	$db->query("
		SELECT
			unhex(replace(hex(hlstats_Players.lastName), 'E280AE', '')) as lastName,
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
		$pl_shortname = substr($pl_name, 0, 8) . '...';
	} else {
		$pl_shortname = $pl_name;
	}

	$pl_name = htmlspecialchars($pl_name, ENT_COMPAT);
	$pl_shortname = htmlspecialchars($pl_shortname, ENT_COMPAT);
	$game = $playerdata['game'];
	$db->query
	("
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


	$surl = $g_options['scripturl'];


    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';

    $sortby = $sort;
    $order  = $sortorder;

    $col = array("eventTime","message","serverName","map");
    if (!in_array($sort, $col)) {
        $sort      = "eventTime";
        $sortorder = "DESC";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;


	$whereclause="hlstats_Events_Chat.playerId = $player ";
	$filter=isset($_REQUEST['filter'])?$_REQUEST['filter']:"";
	if(!empty($filter))
	{
				$whereclause.="AND MATCH (hlstats_Events_Chat.message) AGAINST ('" . $db->escape($filter) . "' in BOOLEAN MODE)";
	}
	
	$result = $db->query
	("
		SELECT
			hlstats_Events_Chat.eventTime,
			IF(hlstats_Events_Chat.message_mode=2, CONCAT('(Team) ', hlstats_Events_Chat.message), IF(hlstats_Events_Chat.message_mode=3, CONCAT('(Squad) ', hlstats_Events_Chat.message), hlstats_Events_Chat.message)) AS message,
			hlstats_Servers.name AS serverName,
			hlstats_Events_Chat.map
		FROM
			hlstats_Events_Chat
		LEFT JOIN 
			hlstats_Servers
		ON
			hlstats_Events_Chat.serverId = hlstats_Servers.serverId
		WHERE
			$whereclause
		ORDER BY
			$sort $sortorder
		LIMIT 30 OFFSET $start
	");
	
	$resultCount = $db->query
	("
		SELECT
			COUNT(*)
		FROM
			hlstats_Events_Chat
		LEFT JOIN 
			hlstats_Servers
		ON
			hlstats_Events_Chat.serverId = hlstats_Servers.serverId
		WHERE
			$whereclause
	");
			
	list($numitems) = $db->fetch_row($resultCount);
	
?>

<?php
if (!is_ajax()) {
	printSectionTitle($pl_name.'\'s Chat History');
?>
<div class="hlstats-filter-search">
<form method="get" action="<?php echo $g_options['scripturl']; ?>">
    <input type="hidden" name="mode" value="chathistory" />
    <input type="hidden" name="player" value="<?php echo $player; ?>" />
    <div class="hlstats-filter-row">
      <label>Filter:</label>
      <div class="hlstats-filter-actions">
        <input type="text" name="filter" value="<?php echo htmlentities($filter); ?>" />
        <button class="search" type="submit">View</button>
      </div>
    </div>
 </form>
</div>

<?php
}
	if ($numitems > 0) {

if (!is_ajax()){
		?>

<div id="chats">
<?php
}
?>
  <div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="left<?= isSorted('eventTime',$sort,$sortorder) ?>"><?= headerUrl('eventTime', ['sort','sortorder'], 'chats') ?>Date</a></th>
        <th class="hlstats-main-description left<?= isSorted('message',$sort,$sortorder) ?>"><?= headerUrl('message', ['sort','sortorder'], 'chats') ?>Message</a></th>
        <th class="<?= isSorted('serverName',$sort,$sortorder) ?>"><?= headerUrl('serverName', ['sort','sortorder'], 'chats') ?>Server</a></th>
        <th class="hide<?= isSorted('map',$sort,$sortorder) ?>"><?= headerUrl('map', ['sort','sortorder'], 'chats') ?>Map</a></th>
    </tr>
    <?php
        $i= 1 + $start;
        while ($res = $db->fetch_array($result))
        {
            echo '<tr>
                  <td class="nowrap left">'.str_replace(" ","<br>@",$res['eventTime']).'</td>
                  <td class="hlstats-main-description nowrap left">'.htmlspecialchars($res['message'], ENT_COMPAT).'</td>
                  <td class="nowrap">'.htmlspecialchars($res['serverName']).'</td>
                  <td class="nowrap hide">'.htmlspecialchars($res['map']).'</td>
                </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($numitems, $_GET['page'] ?? 1, 30, 'page', true, 'chats');
  if (is_ajax()) exit;
  ?>
</div>

<?php
    } else { echo '<p class="hlstats-no-data"><em>Not enough data</em></p>'; }
?>
<div>
    <a href="<?php echo $g_options['scripturl'] . "?mode=playerinfo&amp;player=$player"; ?>">&larr;&nbsp;<?php echo $pl_name; ?>'s Statistics</a>
</div>
<?php if ($g_options['DeleteDays']) { ?>
<div class="hlstats-note">
    Items above are generated from the most recent <?= $g_options['DeleteDays'] ?> days of activity.
</div>
<?php } ?>