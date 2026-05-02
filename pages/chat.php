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

// Global Server Chat History
	$showserver = 0;
	if (isset($_GET['server_id'])) {
		$showserver = valid_request(strval($_GET['server_id']), true);
	}

	if ($showserver == 0) {
		$whereclause = "hlstats_Servers.game='$game'";
	} else {
		$whereclause = "hlstats_Servers.game='$game' AND hlstats_Events_Chat.serverId=$showserver";
	}

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

if (!is_ajax()){

    ob_flush();
    flush();;

	$servername = "(All Servers)";
	
	if ($showserver != 0)
	{
		$result=$db->fetch_array
		(
			$db->query
			("
				SELECT
					hlstats_Servers.name
				FROM
					hlstats_Servers
				WHERE
					hlstats_Servers.serverId = ".$db->escape($showserver)."
			")
		);
		$servername = "(" . $result['name'] . ")";
	}

     printSectionTitle("$gamename $servername Server Chat Log");

?>

		<div class="hlstats-filter-search">

			<form method="get" action="<?php echo $g_options['scripturl']; ?>">
				<input type="hidden" name="mode" value="chat" />
				<input type="hidden" name="game" value="<?php echo $game; ?>" />
                 <div class="hlstats-filter-row" style="margin-bottom: 10px;">
                 <label>Show Chat for:</label>
				<?php

					$result = $db->query
					("
						SELECT
							hlstats_Servers.serverId,
							hlstats_Servers.name
						FROM
							hlstats_Servers
						WHERE
							hlstats_Servers.game='$game'
						ORDER BY
							hlstats_Servers.sortorder,
							hlstats_Servers.name,
							hlstats_Servers.serverId ASC
						LIMIT
							0,
							50
					");

					echo '<select name="server_id">
                    <option value="0">All Servers</option>';
					$dates = array ();
					$serverids = array();
					while ($rowdata = $db->fetch_array())
					{
						$serverids[] = $rowdata['serverId'];
						$dates[] = $rowdata; 
						if ($showserver == $rowdata['serverId'])
							echo '<option value="'.$rowdata['serverId'].'" selected>'.htmlspecialchars($rowdata['name']).'</option>';
						else
							echo '<option value="'.$rowdata['serverId'].'">'.htmlspecialchars($rowdata['name']).'</option>';
					}
					echo '</select>';
					$filter=isset($_REQUEST['filter'])?$_REQUEST['filter']:"";
				?>
               </div>
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
			$whereclause2='';
			if(!empty($filter))
			{
				$whereclause2="AND MATCH (hlstats_Events_Chat.message) AGAINST ('" . $db->escape($filter) . "' in BOOLEAN MODE)";
			}
			$surl = $g_options['scripturl'];






    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';

    $col = array("eventTime","lastName","message","serverName","map");
    if (!in_array($sort, $col)) {
        $sort      = "eventTime";
        $sortorder = "DESC";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;



			$result = $db->query
			("
				SELECT
					hlstats_Events_Chat.eventTime AS eventTime,
					unhex(replace(hex(hlstats_Players.lastName), 'E280AE', '')) as lastName,
					IF(hlstats_Events_Chat.message_mode=2, CONCAT('(Team) ', hlstats_Events_Chat.message), IF(hlstats_Events_Chat.message_mode=3, CONCAT('(Squad) ', hlstats_Events_Chat.message), hlstats_Events_Chat.message)) AS message,
					hlstats_Servers.name AS serverName,
					hlstats_Events_Chat.playerId,
					hlstats_Players.flag,
					hlstats_Events_Chat.map
				FROM
					hlstats_Events_Chat
				INNER JOIN
					hlstats_Players
				ON
					hlstats_Players.playerId = hlstats_Events_Chat.playerId
				INNER JOIN 
					hlstats_Servers
				ON
					hlstats_Servers.serverId = hlstats_Events_Chat.serverId
				WHERE
					$whereclause $whereclause2
				ORDER BY
					$sort $sortorder
				LIMIT
					30 OFFSET $start
            ");

			$db->query
			("
				SELECT
		 			count(*)
				FROM
					hlstats_Events_Chat
				INNER JOIN
					hlstats_Players
				ON
					hlstats_Players.playerId = hlstats_Events_Chat.playerId
				INNER JOIN 
					hlstats_Servers
				ON
					hlstats_Servers.serverId = hlstats_Events_Chat.serverId
				WHERE
					$whereclause $whereclause2
			");
			if ($db->num_rows() < 1) $numitems = 0;
			else 
			{
				list($numitems) = $db->fetch_row();
			}
			$db->free_result();	
            if (!is_ajax()) {
               echo '<div id="chats">';
            }
if ($numitems) {
?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="left<?= isSorted('eventTime',$sort,$sortorder) ?>"><?= headerUrl('eventTime', ['sort','sortorder'], 'chats') ?>Date</a></th>
        <th class="left<?= isSorted('lastName',$sort,$sortorder) ?>"><?= headerUrl('lastName', ['sort','sortorder'], 'chats') ?>Player</a></th>
        <th class="left<?= isSorted('message',$sort,$sortorder) ?>"><?= headerUrl('message', ['sort','sortorder'], 'chats') ?>Message</a></th>
        <?php if ($showserver ) { ?>
        <th class="<?= isSorted('serverName',$sort,$sortorder) ?>"><?= headerUrl('serverName', ['sort','sortorder'], 'chats') ?>Server</a></th>
        <?php } ?>
        <th class="hide<?= isSorted('map',$sort,$sortorder) ?>"><?= headerUrl('map', ['sort','sortorder'], 'chats') ?>Map</a></th>
    </tr>
    <?php

        while ($res = $db->fetch_array($result))
        {
            $html ='<tr>
                  <td class="nowrap left">'.str_replace(" ","<br>@",$res['eventTime']).'</td>
                  <td class="left">';
                    if ($g_options['countrydata']) {
                    $html .= '<span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" alt="'.$res['flag'].'"></span>';
                    }
                    $html .= '<a href="?mode=playerinfo&amp;player='.$res['playerId'].'"><span class="hlstats-name">'.htmlspecialchars($res['lastName']).'&nbsp;</span></a>
                  </td>
                  <td class="left">'.htmlspecialchars(stripslashes($res['message']), ENT_QUOTES).'</td>';
            if ($showserver ) {
                $html .= '<td class="">'.htmlspecialchars($res['serverName']).'</td>';
            }
              $html .= '<td class="nowrap hide">'.htmlspecialchars($res['map']).'</td>
                </tr>';
              echo $html;
        }
   ?>
   </table></div>
   <?php
       echo Pagination($numitems, $_GET['page'] ?? 1, 30, 'page');
  if (is_ajax()) exit;
} else { echo '<p class="hlstats-no-data"><em>Not enough data</em></p>'; }
  ?>
</div>
<script>
Fetch.ini('chats');
</script>
<?php if ($g_options['DeleteDays']) { ?>
<div class="hlstats-note">
    Items above are generated from the most recent <?= $g_options['DeleteDays'] ?> days of activity.
</div>
<?php } ?>