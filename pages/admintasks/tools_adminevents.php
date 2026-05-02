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

	if ($auth->userdata["acclevel"] < 80) {
        die ("Access denied!");
	}

	$db->query("DROP TABLE IF EXISTS hlstats_AdminEventHistory");

	$sql_create_temp_table = "
		CREATE TEMPORARY TABLE hlstats_AdminEventHistory
		(
			eventType VARCHAR(64) NOT NULL,
			eventTime DATETIME NOT NULL,
			eventDesc VARCHAR(255) NOT NULL,
			serverName VARCHAR(255) NOT NULL,
			map VARCHAR(64) NOT NULL
		) DEFAULT CHARSET=" . DB_CHARSET . " DEFAULT COLLATE=" . DB_COLLATE . ";
	";

	$db->query($sql_create_temp_table);

	function insertEvents ($table, $select)
	{
		global $db;
		
		$select = str_replace("<table>", "hlstats_Events_$table", $select);
		$db->query("
			INSERT INTO
				hlstats_AdminEventHistory
				(
					eventType,
					eventTime,
					eventDesc,
					serverName,
					map
				)
			$select
		");
	}
	
	insertEvents("Rcon", "
		SELECT
			CONCAT(<table>.type, ' Rcon'),
			<table>.eventTime,
			CONCAT('\"', command, '\"\nFrom: %A%".$g_options['scripturl']."?mode=search&q=', remoteIp, '&st=ip&game=%', remoteIp, '%/A%', IF(password<>'',CONCAT(', password: \"', password, '\"'),'')),
			IFNULL(hlstats_Servers.name, 'Unknown'),
			<table>.map
		FROM
			<table>
		LEFT JOIN hlstats_Servers ON
			hlstats_Servers.serverId = <table>.serverId
	");
	
	insertEvents("Admin", "
		SELECT
			<table>.type,
			<table>.eventTime,
			IF(playerName != '',
				CONCAT('\"', playerName, '\": ', message),
				message
			),
			IFNULL(hlstats_Servers.name, 'Unknown'),
			<table>.map
		FROM
			<table>
		LEFT JOIN hlstats_Servers ON
			hlstats_Servers.serverId = <table>.serverId
	");

	$where = "";
    $select_type = "";

	if (isset($_GET['type']) && $_GET['type'] != '') {
		$select_type = $_GET['type'];
		$where = "WHERE eventType='" . $db->escape($_GET['type']) . "'";
	}

    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';

    $col = array("eventTime","eventType","eventDesc","serverName","map");
    if (!in_array($sort, $col)) {
        $sort      = "eventTime";
        $sortorder = "DESC";
    }
        $sort2      = "serverName";
        $sortorder2 = "DESC";

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;
    
	$result = $db->query("
		SELECT
			eventTime,
			eventType,
			eventDesc,
			serverName,
			map
		FROM
			hlstats_AdminEventHistory
		$where
		ORDER BY
			$sort $sortorder,
			$sort2 $sortorder2
		LIMIT
			30 OFFSET $start
	");
	
	$resultCount = $db->query("
		SELECT
			COUNT(*)
		FROM
			hlstats_AdminEventHistory
		$where
	");
	
	list($numitems) = $db->fetch_row($resultCount);
    if (!isset($_GET['ajax'])) {
        echo '';
    }
?>
<div class="panel">
<div id="adminEvents">

<div class="responsive-table">
  <table class="responsive-task">
    <thead>
        <th class="left<?= isSorted('eventTime',$sort,$sortorder) ?>"><?= headerUrl('eventTime', ['sort','sortorder'], 'adminEvents') ?>Date</a></th>
        <th class="left<?= isSorted('eventType',$sort,$sortorder) ?>"><?= headerUrl('eventType', ['sort','sortorder'], 'adminEvents') ?>Type</a></th>
        <th class="hlstats-main-column left<?= isSorted('eventDesc',$sort,$sortorder) ?>"><?= headerUrl('eventDesc', ['sort','sortorder'], 'adminEvents') ?>Description</a></th>
        <th class="<?= isSorted('serverName',$sort,$sortorder) ?>"><?= headerUrl('serverName', ['sort','sortorder'], 'adminEvents') ?>Server</a></th>
        <th class="<?= isSorted('map',$sort,$sortorder) ?>"><?= headerUrl('map', ['sort','sortorder'], 'adminEvents') ?>Map</a></th>
    </thead>
    <tbody>
    <?php
        while ($res = $db->fetch_array($result))
        {
            $description = preg_replace(array('/%A%([^ %]+)%/','/%\/A%/'), array("<a href=\"$1\">", '</a>'),$res['eventDesc']);
            $html = '<tr>
                     <td class="nowrap left" data-label="Date">'.str_replace(" ","<br>@",$res['eventTime']).'</td>
                     <td class="left" data-label="Type">
                      <span class="hlstats-name">'.htmlspecialchars($res['eventType']).' </span>
                      </td>
                      <td class="left" data-label="Description">'.$description.'</td>';
            $html .= '<td data-label="Server">'.htmlspecialchars($res['serverName']).'</td>';
            $html .= '<td class="nowrap" data-label="Map">'.htmlspecialchars($res['map']).'</td>
                     </tr>';
              echo $html;
        }
   ?>
   </tbody>
   </table>
   </div>
   <?php
       echo Pagination($numitems, $_GET['page'] ?? 1, 30, 'page', true, 'adminEvents');
  ?>
</div>
</div>