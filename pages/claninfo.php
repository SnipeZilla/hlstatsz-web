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
	
	// Clan Details
	
	$clan = valid_request(intval($_GET["clan"] ?? 0), true) or error("No clan ID specified.");

	$db->query("
		SELECT
			hlstats_Clans.tag,
			hlstats_Clans.name,
			hlstats_Clans.homepage,
			hlstats_Clans.game,
			hlstats_Clans.mapregion,
			SUM(hlstats_Players.kills) AS kills,
			SUM(hlstats_Players.deaths) AS deaths,
			SUM(hlstats_Players.headshots) AS headshots,
			SUM(hlstats_Players.connection_time) AS connection_time,
			COUNT(hlstats_Players.playerId) AS nummembers,
			ROUND(AVG(hlstats_Players.skill)) AS avgskill,
			TRUNCATE(AVG(activity),2) as activity
		FROM
			hlstats_Clans
		LEFT JOIN
			hlstats_Players
		ON
			hlstats_Players.clan = hlstats_Clans.clanId
		WHERE
			hlstats_Clans.clanId=$clan
			
		GROUP BY
			hlstats_Clans.clanId
	");

	if ($db->num_rows() != 1) {
		error("No such clan '$clan'.");
	}
	
	$clandata = $db->fetch_array();

	$realkills     = ($clandata['kills'] == 0) ? 1 : $clandata['kills'];
	$realheadshots = ($clandata['headshots'] == 0) ? 1 : $clandata['headshots'];

	$db->free_result();
	
	$cl_name = preg_replace('/\s/', '&nbsp;', htmlspecialchars($clandata['name']));
	$cl_tag  = preg_replace('/\s/', '&nbsp;', htmlspecialchars($clandata['tag']));
	$cl_full = "$cl_tag $cl_name";
	
	$game = $clandata['game'];
	$db->query("SELECT name FROM hlstats_Games WHERE code='$game'");

    if ($db->num_rows() != 1) {
		$gamename = ucfirst($game);
	} else {
		list($gamename) = $db->fetch_row();
	}

    if (is_ajax()) {
		$tabs = explode('_', preg_replace('[^a-z]', '', $_GET['tab']));
		unset($_GET['type']);

		foreach ($tabs as $tab) {
			if (file_exists(PAGE_PATH . '/claninfo_' . $tab . '.php')) {
				@include(PAGE_PATH . '/claninfo_' . $tab . '.php');
			}
		}

		exit;
	}

    $members_page = (empty($_GET['members_page'])) ? "Unknown" : valid_request($_GET['members_page'], true);

?>

<div class="hlstats-tabs-bar">
<ul class="hlstats-tabs" id="tabs_playerinfo">
    <li class="active">
        <a href="#General" class="tab" data-url="general" data-target="tab1">General</a>
    </li>
    <li>
        <a href="#actions_teams" class="tab" data-url="actions_teams" data-target="tab2">Teams &amp; Actions</a>
    </li>
    <li>
        <a href="#weapons" class="tab" data-url="weapons" data-target="tab3">Weapons</a>
    </li>
    <li>
        <a href="#mapperformance" class="tab" data-url="mapperformance" data-target="tab4">Maps</a>
    </li>
</ul>
</div>

<!-- Tab content containers -->
<div id="tab1" class="hlstats-tab-content"></div>
<div id="tab2" class="hlstats-tab-content"></div>
<div id="tab3" class="hlstats-tab-content"></div>
<div id="tab4" class="hlstats-tab-content"></div>
<div id="tab5" class="hlstats-tab-content"></div>

<script type="text/javascript" src="<?= INCLUDE_PATH ?>/js/targets.js?<?= filemtime(INCLUDE_PATH .'/js/targets.js') ?>"></script>
<script>
Tabs.init({
    baseParams: {
        mode: "claninfo",
        game: "<?= $game ?>",
        clan: "<?= $clan ?>"
    }
});
</script>

<?php if ($g_options['DeleteDays']) { ?>
<div class="hlstats-note">
	Items marked "*" above are generated from the most recent <strong><?php echo $g_options['DeleteDays']; ?></strong> days of activity.
</div>
<?php } ?>

<?php
    if ((!empty($_SESSION['loggedin']) && (int)($_SESSION['acclevel'] ?? 0) >= 100) || STEAM_ADMIN === ($_SESSION['ID64'] ?? ''))
	{
		echo '<div style="float:right;">';
		echo 'Admin Options &rarr; <a href="'.$g_options['scripturl']."?mode=admin&amp;task=tools_editdetails_clan&amp;id=$clan\">Edit Clan Details</a>";
		echo '</div>';
	}
?>

