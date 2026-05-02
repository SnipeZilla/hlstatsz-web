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

	$db->query("
		SELECT
			COUNT(*)
		FROM
			hlstats_Events_ChangeTeam
		LEFT JOIN hlstats_Players ON
			hlstats_Players.playerId=hlstats_Events_ChangeTeam.playerId
		WHERE
			clan=$clan
	");
	list($numteamjoins) = $db->fetch_row();

	$result = $db->query("
		SELECT
			IFNULL(hlstats_Teams.name, hlstats_Events_ChangeTeam.team) AS name,
			COUNT(hlstats_Events_ChangeTeam.id) AS teamcount,
			ROUND(COUNT(hlstats_Events_ChangeTeam.id) / IF($numteamjoins = 0, 1, $numteamjoins) * 100, 2) AS percent
		FROM
			hlstats_Events_ChangeTeam
		LEFT JOIN hlstats_Teams ON
			hlstats_Events_ChangeTeam.team=hlstats_Teams.code
		LEFT JOIN hlstats_Players ON
			hlstats_Players.playerId=hlstats_Events_ChangeTeam.playerId
		WHERE
			clan=$clan
			AND hlstats_Teams.game='$game' 
			AND (hidden <>'1' OR hidden IS NULL)
		GROUP BY
			hlstats_Events_ChangeTeam.team
		ORDER BY
			$sort $sortorder,
			$sort2 $sortorder
	");
	
	$numitems = $db->num_rows($result);
	
	if ($numitems > 0)
	{
       if (empty($_GET['ajax'])) {

		printSectionTitle('Team Selection'.$asterisk);

?>
<div id="teams">
<?php
}
?>
<div  class="responsive-table">
  <table class="teams-table">
    <tr>
        <th class="nowarp right" style="width:1%"><span>#</span></th>
        <th class="hlstats-main-description left<?= isSorted('name',$sort,$sortorder) ?>"><?= headerUrl('name', ['teams_sort','teams_sortorder'], 'teams') ?>Team</a></th>
        <th class="<?= isSorted('teamcount',$sort,$sortorder) ?>"><?= headerUrl('teamcount', ['teams_sort','teams_sortorder'], 'teams') ?>Joined</a></th>
        <th class="nowrap hide-2<?= isSorted('percent',$sort,$sortorder) ?>"><?= headerUrl('percent', ['teams_sort','teams_sortorder'], 'teams') ?>Ratio</a></th>
    </tr>
    <?php
        $i= 1;
        while ($res = $db->fetch_array($result))
        {
            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><span class="hlstats-name">'.htmlspecialchars($res['name']).'</span></td>
                  <td class="nowrap">'.nf($res['teamcount']).'</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['percent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['percent'].'%</div>
                    </div>
                  </td>
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

if (empty($_GET['ajax']) || $_GET['ajax'] == 'roles') {

    $sortorder = $_GET['roles_sortorder'] ?? '';
    $sort      = $_GET['roles_sort'] ?? '';
    $sort2     = "rolecount";

    $col = array("code","rolecount","percent","killsTotal","deathsTotal","kpd");
    if (!in_array($sort, $col)) {
        $sort      = "rolecount";
        $sortorder = "DESC";
    }

    if ($sort == "rolecount") {
        $sort2 = "code";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

	$db->query("
		SELECT
			COUNT(*)
		FROM
			hlstats_Events_ChangeRole
		LEFT JOIN hlstats_Players ON
			hlstats_Players.playerId=hlstats_Events_ChangeRole.playerId
		WHERE
			clan=$clan
	");
	list($numrolejoins) = $db->fetch_row();

$result = $db->query("
        SELECT
            agg.name,
            agg.code,
            agg.rolecount,
            LEAST(ROUND(agg.rolecount / IF($numrolejoins = 0, 1, $numrolejoins) * 100, 2),100) AS percent,
            agg.killsTotal,
            agg.deathsTotal,
            ROUND(agg.killsTotal / IF(agg.deathsTotal = 0, 1, agg.deathsTotal), 2) AS kpd
        FROM (
            SELECT
                IFNULL(r.name, e.role) AS name,
                IFNULL(r.code, e.role) AS code,
        
                COUNT(e.id) AS rolecount,
        
                SUM(CASE WHEN f.killerId = e.playerId THEN 1 ELSE 0 END) AS killsTotal,
                SUM(CASE WHEN f.victimId = e.playerId THEN 1 ELSE 0 END) AS deathsTotal
        
            FROM hlstats_Events_ChangeRole e
            LEFT JOIN hlstats_Roles r ON e.role = r.code
            LEFT JOIN hlstats_Servers s ON s.serverId = e.serverId
            LEFT JOIN hlstats_Players p ON p.playerId = e.playerId
        
            LEFT JOIN hlstats_Events_Frags f
                ON f.serverId = e.serverId
            AND s.game = '$game'
            AND (f.killerRole = e.role OR f.victimRole = e.role)
        
            WHERE
                s.game = '$game'
                AND p.clan = $clan
                AND r.game = '$game'
        
            GROUP BY e.role
        ) AS agg
        ORDER BY
            $sort $sortorder,
            $sort2 $sortorder
        
        ");
	
	$numitems = $db->num_rows($result);
	
	if ($db->num_rows($result)) {

       if (empty($_GET['ajax'])) {

		printSectionTitle('Role Selection'.$asterisk);

?>
<div id="roles">
<?php
}
?>
<div class="responsive-table">
  <table class="roles-table">
    <tr>
        <th class="nowrap right">#</th>
        <th class="<?= isSorted('code',$sort,$sortorder) ?>"><?= headerUrl('code', ['roles_sort','roles_sortorder'], 'roles') ?>Role</a></th>
        <th class="hlstats-main-description left<?= isSorted('rolecount',$sort,$sortorder) ?>"><?= headerUrl('rolecount', ['roles_sort','roles_sortorder'], 'roles') ?>Picked</a></th>
        <th class="meter-ratio nowrap hide-2<?= isSorted('percent',$sort,$sortorder) ?>"><?= headerUrl('percent', ['roles_sort','roles_sortorder'], 'roles') ?>Ratio</a></th>
        <th class="nowrap<?= isSorted('killsTotal',$sort,$sortorder) ?>"><?= headerUrl('killsTotal', ['roles_sort','roles_sortorder'], 'roles') ?>Kills</a></th>
        <th class="nowrap hide<?= isSorted('deathsTotal',$sort,$sortorder) ?>"><?= headerUrl('deathsTotal', ['roles_sort','roles_sortorder'], 'roles') ?>Deaths</a></th>
        <th class="nowrap hide-1<?= isSorted('kpd',$sort,$sortorder) ?>"><?= headerUrl('kpd', ['roles_sort','roles_sortorder'], 'roles') ?>K:D</a></th>
    </tr>
    <?php
        $i= 1 + $start;

        while ($res = $db->fetch_array($result))
        {
            $icode = strtolower($res['code']);
            $image = getImage("/games/$game/roles/" . $icode);
            // check if image exists for game -- otherwise check realgame
            if ($image) {
                $img = '<span class="hlstats-image"><img src="' . $image['url'] . '" alt="' . $icode . '" title="' . htmlspecialchars($res['name']) . '" /></span>';
            } elseif ($image = getImage("/games/$realgame/roles/" . $icode)) {
                $img = '<span class="hlstats-image"><img src="' . $image['url'] . '" alt="' . $icode . '" title="' . htmlspecialchars($res['name']) . '" /></span>';
            } elseif (!empty($res['name'])) {
                $img = '<span class="hlstats-name">' . $res['name'] . '</span>';
            } else {
                $img = '<span class="hlstats-name">' . ucwords(preg_replace('/_/', ' ', $icode)) . '</span>';
            }

            echo '<tr>
                  <td class="nowrap right">'.$i.'</td>
                  <td class="hlstats-main-description left"><a href="?mode=rolesinfo&amp;role='.$icode.'&amp;game='.$game.'">'.$img.'<span class="hlstats-name">'.htmlspecialchars($res['name']).'</span></a></td>
                  <td class="nowrap left">'.$res['rolecount'].' times</td>
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

  if ($_GET['ajax']) exit;
  ?>
</div>
<?php
    }
}
