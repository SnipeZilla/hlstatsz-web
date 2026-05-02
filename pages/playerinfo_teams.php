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

$asterisk = $g_options['DeleteDays'] ? ' *' : '';

if (empty($_GET['ajax']) || $_GET['ajax'] == 'teams') {

    $sortorder = $_GET['teams_sortorder'] ?? '';
    $sort      = $_GET['teams_sort'] ?? '';
    $sort2     = "name";

    $col = array("name","teamcount","percent");
    if (!in_array($sort, $col)) {
        $sort      = "teamcount";
        $sortorder = "DESC";
    }

    if ($sort == "name") {
        $sort2 = "teamcount";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";
    
	$db->query
	("
		SELECT
			COUNT(*)
		FROM
			hlstats_Events_ChangeTeam
		WHERE
			hlstats_Events_ChangeTeam.playerId = $player 
	");
	list($numteamjoins) = $db->fetch_row();

	if($numteamjoins == 0) {
		$numteamjoins = 1;
	}

	$result = $db->query
	("
		SELECT
			IFNULL(hlstats_Teams.name, hlstats_Events_ChangeTeam.team) AS name,
			COUNT(hlstats_Events_ChangeTeam.id) AS teamcount,
			ROUND((COUNT(hlstats_Events_ChangeTeam.id) / $numteamjoins) * 100, 2) AS percent
		FROM
			hlstats_Events_ChangeTeam
		LEFT JOIN
			hlstats_Teams
		ON
			hlstats_Events_ChangeTeam.team = hlstats_Teams.code
		WHERE
			hlstats_Teams.game = '$game'
			AND hlstats_Events_ChangeTeam.playerId = $player
			AND
			(
				hidden <> '1'
				OR hidden IS NULL
			)
		GROUP BY
			hlstats_Events_ChangeTeam.team
		ORDER BY
			$sort $sortorder,
			$sort2 $sortorder
	");

	if ($db->num_rows($result))
	{
       if (empty($_GET['ajax'])) {

		printSectionTitle('Team Selection'.$asterisk);

?>
<div id="teams" class="responsice-table">
<?php
}
?>
  <table class="players-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>Rank</span></th>
        <th class="hlstats-main-description left<?= isSorted('name',$sort,$sortorder) ?>"><?= headerUrl('name', ['teams_sort','teams_sortorder'], 'teams') ?>Team</a></th>
        <th class="<?= isSorted('teamcount',$sort,$sortorder) ?>"><?= headerUrl('teamcount', ['teams_sort','teams_sortorder'], 'teams') ?>Joined</a></th>
        <th class="nowrap hide<?= isSorted('percent',$sort,$sortorder) ?>"><?= headerUrl('percent', ['teams_sort','teams_sortorder'], 'teams') ?>Ratio</a></th>
    </tr>
    <?php
        $i= 1;
        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><span class="hlstats-name">'.htmlspecialchars($res['name']).'</span></td>
                  <td class="nowrap">'.nf($res['teamcount']).' times</td>
                  <td class="nowrap hide">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['percent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['percent'].'%</div>
                    </div>
                  </td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   <?php

  if (!empty($_GET['ajax'])) exit;
  ?>
</div>
<?php
    }
}


if (empty($_GET['ajax']) || $_GET['ajax'] == 'roles') {

    $sortorder = $_GET['roles_sortorder'] ?? '';
    $sort      = $_GET['roles_sort'] ?? '';
    $sort2     = "name";

    $col = array("name","rolecount","role","killsTotal","deathsTotal","kpd","percent");
    if (!in_array($sort, $col)) {
        $sort      = "rolecount";
        $sortorder = "DESC";
    }

    if ($sort == "name") {
        $sort2 = "rolecount";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";
    
	$result = $db->query
	("
		SELECT
			hlstats_Roles.code,
			hlstats_Roles.name
		FROM
			hlstats_Roles
		WHERE
			hlstats_Roles.game = '$game'
	");
	while ($rowdata = $db->fetch_row($result))
	{
		$code = preg_replace("/[ \r\n\t]+/", "", $rowdata[0]);
		$fname[strToLower($code)] = htmlspecialchars($rowdata[1]);
	}

	$db->query("DROP TABLE IF EXISTS hlstats_Frags_as");

	$sql_create_temp_table = "
		CREATE TEMPORARY TABLE hlstats_Frags_as
		(
			playerId INT(10),
			kills INT(10),
			deaths INT(10),
			role varchar(128) NOT NULL default ''
		) DEFAULT CHARSET=" . DB_CHARSET . " DEFAULT COLLATE=" . DB_COLLATE . ";
	";

	$db->query($sql_create_temp_table);

	$db->query
	("
		INSERT INTO
			hlstats_Frags_as
			(
				playerId,
				kills,
				role
			)
		SELECT
			hlstats_Events_Frags.victimId,
			hlstats_Events_Frags.killerId,
			hlstats_Events_Frags.killerRole
		FROM
			hlstats_Events_Frags
		WHERE 
			hlstats_Events_Frags.killerId = $player
	");
	$db->query
	("
		INSERT INTO
			hlstats_Frags_as
			(
				playerId,
				deaths,
				role
			)
		SELECT
			hlstats_Events_Frags.killerId,
			hlstats_Events_Frags.victimId,
			hlstats_Events_Frags.victimRole
		FROM
			hlstats_Events_Frags
		WHERE 
			hlstats_Events_Frags.victimId = $player 
	");

	$db->query("DROP TABLE IF EXISTS hlstats_Frags_as_res");

	$sql_create_temp_table = "
		CREATE TEMPORARY TABLE hlstats_Frags_as_res
		(
			killsTotal INT(10),
			deathsTotal INT(10),
			role varchar(128) NOT NULL default ''
		) DEFAULT CHARSET=" . DB_CHARSET . " DEFAULT COLLATE=" . DB_COLLATE . ";
	";

	$db->query($sql_create_temp_table);

	$db->query
	("
		INSERT INTO
			hlstats_Frags_as_res
			(
				killsTotal,
				deathsTotal,
				role
			)
		SELECT
			COUNT(hlstats_Frags_as.kills) AS kills, 
			COUNT(hlstats_Frags_as.deaths) AS deaths,
			hlstats_Frags_as.role
		FROM
			hlstats_Frags_as
		GROUP BY
			hlstats_Frags_as.role
	");
	$db->query
	("
		SELECT
			COUNT(*)
		FROM
			hlstats_Events_ChangeRole
		WHERE
			hlstats_Events_ChangeRole.playerId = $player
	");
	list($numrolejoins) = $db->fetch_row();
	$result = $db->query
	("
		SELECT
			IFNULL(hlstats_Roles.name, hlstats_Events_ChangeRole.role) AS name,
			IFNULL(hlstats_Roles.code, hlstats_Events_ChangeRole.role) AS code,
			COUNT(hlstats_Events_ChangeRole.id) AS rolecount,
			ROUND(COUNT(hlstats_Events_ChangeRole.id) / IF($numrolejoins = 0, 1, $numrolejoins) * 100, 2) AS percent,
			hlstats_Frags_as_res.killsTotal,
			hlstats_Frags_as_res.deathsTotal,
			ROUND(hlstats_Frags_as_res.killsTotal / IF(hlstats_Frags_as_res.deathsTotal = 0, 1, hlstats_Frags_as_res.deathsTotal), 2) AS kpd
		FROM
			hlstats_Events_ChangeRole
		LEFT JOIN
			hlstats_Roles
		ON
			hlstats_Events_ChangeRole.role = hlstats_Roles.code
		LEFT JOIN
			hlstats_Frags_as_res
		ON
			hlstats_Frags_as_res.role = hlstats_Events_ChangeRole.role
		WHERE
			hlstats_Events_ChangeRole.playerId = $player
			AND
			(
				hidden <> '1'
				OR hidden IS NULL
			)
			AND hlstats_Roles.game = '$game'
		GROUP BY
			hlstats_Events_ChangeRole.role,
			hlstats_Frags_as_res.killsTotal,
			hlstats_Frags_as_res.deathsTotal
		ORDER BY
			$sort $sortorder,
			$sort2 $sortorder
	");

	if ($db->num_rows($result))
	{
       if (empty($_GET['ajax'])) {
		printSectionTitle('Role Selection'.$asterisk);
?>
<div id="roles">
<?php
}
?>
<div class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="nowarp left" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('name',$sort,$sortorder) ?>"><?= headerUrl('name', ['roles_sort','roles_sortorder'], 'roles') ?>Roles</a></th>
        <th class="<?= isSorted('rolecount',$sort,$sortorder) ?>"><?= headerUrl('rolecount', ['roles_sort','roles_sortorder'], 'roles') ?>Joined</a></th>
        <th class="meter-ratio nowarp hide-2<?= isSorted('percent',$sort,$sortorder) ?>"><?= headerUrl('percent', ['roles_sort','roles_sortorder'], 'roles') ?>Ratio</a></th>
        <th class="<?= isSorted('killsTotal',$sort,$sortorder) ?>"><?= headerUrl('killsTotal', ['roles_sort','roles_sortorder'], 'roles') ?>Kills</a></th>
        <th class="hide<?= isSorted('deathsTotal',$sort,$sortorder) ?>"><?= headerUrl('deathsTotal', ['roles_sort','roles_sortorder'], 'roles') ?>Deaths</a></th>
        <th class="hide-1<?= isSorted('kpd',$sort,$sortorder) ?>"><?= headerUrl('kpd', ['roles_sort','roles_sortorder'], 'roles') ?>K:D</a></th>
    </tr>
    <?php
        $i= 1;
        while ($res = $db->fetch_array($result))
        {
            $code = strtolower($res['code']);
            $image = getImage("/games/$game/roles/" . $code);
            // check if image exists for game -- otherwise check realgame
            if ($image) {
                $img = '<span class="hlstats-image"><img src="' . $image['url'] . '" alt="' . $code . '" title="' . $res['name'] . '" /></span>';
            } elseif ($image = getImage("/games/$realgame/roles/" . $code)) {
                $img = '<span class="hlstats-image"><img src="' . $image['url'] . '" alt="' . $code . '" title="' . $res['name'] . '" /></span>';
            } else { $img=''; }

            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><a href="?mode=rolesinfo&role='.$res['code'].'&game='.$game.'">'.$img.'<span class="hlstats-name">'.htmlspecialchars($res['name']).'</span></a></td>
                  <td class="nowrap">'.$res['rolecount'].' times</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['percent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['percent'].'%</div>
                    </div>
                  </td>
                  <td class="nowrap">'.nf($res['killsTotal']).'</td>
                  <td class="nowrap hide">'.nf($res['deathsTotal']).'</td>
                  <td class="nowrap hide-1">'.$res['kpd'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table>
   </div>
   <?php

  if (!empty($_GET['ajax'])) exit;
  ?>
</div>
<?php
    }
}
