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

    $id = -1;
	if (isset($_GET['id']) && is_numeric($_GET['id'])) {
		$id = valid_request($_GET['id'], true);
	}
	$mapselect='';
	$result = $db->query("SELECT `value` FROM hlstats_Options_Choices WHERE `keyname` = 'google_map_region' ORDER BY `value`");
    while ($rowdata = $db->fetch_row($result)) {
        $mapselect.=";".$rowdata[0]."/".ucwords(strtolower($rowdata[0]));
    }

	$mapselect.=";";   
?>

<form method="post" action="<?php echo $g_options['scripturl'] . "?mode=admin&amp;task=$selTask&amp;id=$id&" . strip_tags(session_id()); ?>">
<?php
	$proppage = new PropertyPage("hlstats_Clans", "clanId", $id, array(
		new PropertyPage_Group("Profile", array(
			new PropertyPage_Property("name", "Clan Name", "text"),
			new PropertyPage_Property("tag", "Clan Tag", "text"),
			new PropertyPage_Property("homepage", "Homepage URL", "text"),
			new PropertyPage_Property("hidden", "1 = Hide from clan list", "text")
		))
	));

	if (isset($_POST['name'])) {
		$proppage->update();
		message("success", "Profile updated successfully.");
	}

	$result = $db->query("
		SELECT
			*
		FROM
			hlstats_Clans
		WHERE
			clanId='$id'
	");

	if ($db->num_rows() < 1) {
        die("No clan exists with ID #$id");
	}
	
	$data = $db->fetch_array($result);
	
	echo "<span class='fTitle'>";
	echo $data['tag'];
	echo "</span>";
	
	printSectionTitle('<span>'.$data['tag'].'</span><span>'.
						'<a href="' . $g_options['scripturl'] . '?mode=claninfo&amp;clan=$id&amp;' . strip_tags(session_id()) . '">'.
						'(View Clan Details)</a></span>');



		$proppage->draw($data);
?>

<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit">
</div>
</form>
