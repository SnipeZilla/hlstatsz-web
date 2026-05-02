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
?>

&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width=9 height=6 class="imageformat"><b>&nbsp;<?php
	if (isset($_GET['hostgroup'])) {
        $hostgroup = $_GET['hostgroup'];
        
?><a href="<?php echo $g_options["scripturl"]; ?>?mode=admin&task=<?php echo $selTask; ?>"><?php
	}
	echo $task->title;
    if (isset($_GET['hostgroup'])) {
		echo "</a>";
	}

?></b> (Last <?php echo $g_options["DeleteDays"]; ?> Days)<?php
    if (isset($_GET['hostgroup']))
	{
?><br>
<img src="<?php echo IMAGE_PATH; ?>/spacer.gif" width=1 height=8 border=0><br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="<?php echo IMAGE_PATH; ?>/downarrow.gif" width=9 height=6 class="imageformat"><b>&nbsp;<?php echo $hostgroup; ?></b><p>
<?php
	}
	else
	{
		echo "<p>";
	}
?>

<?php
    if (isset($_GET['hostgroup'])) {
		$table = new Table(
			array(
				new TableColumn(
					"host",
					"Host",
					"width=41"
				),
				new TableColumn(
					"freq",
					"Connects",
					"width=12&align=right"
				),
				new TableColumn(
					"percent",
					"Percentage of Connects",
					"width=30&sort=no&type=bargraph"
				),
				new TableColumn(
					"percent",
					"%",
					"width=12&sort=no&align=right&append=" . urlencode("%")
				)
			),
			"host",			// keycol
			"freq",			// sort
			"host",			// sort2
			true,			// showranking
			50				// numperpage
		);
		
		if ($hostgroup == "(Unresolved IP Addresses)")
			$hostgroup = "";
		
		$result = $db->query("
			SELECT
				COUNT(*),
				COUNT(DISTINCT ipAddress)
			FROM
				hlstats_Events_Connects
			WHERE
				hostgroup='".$db->escape($hostgroup)."'
		");
		
		list($totalconnects, $numitems) = $db->fetch_row($result);
		
		$result = $db->query("
			SELECT
				IF(hostname='', ipAddress, hostname) AS host,
				COUNT(hostname) AS freq,
				(COUNT(hostname) / $totalconnects) * 100 AS percent
			FROM
				hlstats_Events_Connects
			WHERE
				hostgroup='".$db->escape($hostgroup)."'
			GROUP BY
				host
			ORDER BY
				$table->sort $table->sortorder,
				$table->sort2 $table->sortorder
			LIMIT
				$table->startitem,$table->numperpage
		");
		
		$table->draw($result, $numitems, 95, "center");
	}
	else
	{
		$table = new Table(
			array(
				new TableColumn(
					"hostgroup",
					"Host",
					"width=41&icon=server&link=" . urlencode("mode=admin&task=tools_ipstats&hostgroup=%k")
				),
				new TableColumn(
					"freq",
					"Connects",
					"width=12&align=right"
				),
				new TableColumn(
					"percent",
					"Percentage of Connects",
					"width=30&sort=no&type=bargraph"
				),
				new TableColumn(
					"percent",
					"%",
					"width=12&sort=no&align=right&append=" . urlencode("%")
				)
			),
			"hostgroup",	// keycol
			"freq",			// sort
			"hostgroup",	// sort2
			true,			// showranking
			50				// numperpage
		);
		
		$result = $db->query("
			SELECT
				COUNT(*),
				COUNT(DISTINCT hostgroup)
			FROM
				hlstats_Events_Connects
		");
		
		list($totalconnects, $numitems) = $db->fetch_row($result);
		
		$result = $db->query("
			SELECT
				IF(hostgroup='', '(Unresolved IP Addresses)', hostgroup) AS hostgroup,
				COUNT(hostgroup) AS freq,
				(COUNT(hostgroup) / $totalconnects) * 100 AS percent
			FROM
				hlstats_Events_Connects
			GROUP BY
				hostgroup
			ORDER BY
				$table->sort $table->sortorder,
				$table->sort2 $table->sortorder
			LIMIT
				$table->startitem,$table->numperpage
		");
		
		$table->draw($result, $numitems, 95, "center");
	}
?>
