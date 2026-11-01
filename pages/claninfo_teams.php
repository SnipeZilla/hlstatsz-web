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

    $col = array("rank_position","name","teamcount","percent");
    if (!in_array($sort, $col)) {
        $sort      = "rank_position";
        $sortorder = "ASC";
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
		WITH team_data AS (
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
		),
		ranked AS (
			SELECT *,
				RANK() OVER (ORDER BY teamcount DESC, name ASC) AS rank_position,
				COUNT(*) OVER() AS total_rows
			FROM team_data
		)
		SELECT * FROM ranked
		ORDER BY
			$sort $sortorder,
			$sort2 $sortorder
	");
	
	$numitems = $db->num_rows($result);
	
	if ($numitems > 0)
	{
       if (empty($_GET['ajax'])) {

		printSectionTitle(t('title.team.selection').$asterisk);

?>
<div id="teams">
<?php
}
?>
<div  class="responsive-table">
  <table class="teams-table">
    <tr>
        <th class="hlstats-ranking nowrap<?= isSorted('rank_position',$sort,$sortorder) ?>"><?= headerUrl('rank_position', ['teams_sort','teams_sortorder'], 'teams') .t('th.rank') ?></a></th>
        <th class="hlstats-main-description left<?= isSorted('name',$sort,$sortorder) ?>"><?= headerUrl('name', ['teams_sort','teams_sortorder'], 'teams') .t('th.team') ?></a></th>
        <th class="<?= isSorted('teamcount',$sort,$sortorder) ?>"><?= headerUrl('teamcount', ['teams_sort','teams_sortorder'], 'teams') .t('th.joined') ?></a></th>
        <th class="nowrap hide-2<?= isSorted('percent',$sort,$sortorder) ?>"><?= headerUrl('percent', ['teams_sort','teams_sortorder'], 'teams') .t('th.ratio') ?></a></th>
    </tr>
    <?php
        while ($res = $db->fetch_array($result))
        {
            echo '<tr>
                  <td class="nowrap right">'.$res['rank_position'].'</td>
                  <td class="hlstats-main-description left"><span class="hlstats-name">'.htmlspecialchars($res['name']).'</span></td>
                  <td class="nowrap">'.nf($res['teamcount']).'</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['percent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['percent'].'%</div>
                    </div>
                  </td>
                  </tr>';
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

    $col = array("rank_position","code","rolecount","percent","killsTotal","deathsTotal","kpd");
    if (!in_array($sort, $col)) {
        $sort      = "rank_position";
        $sortorder = "ASC";
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

// Each role: how many times the members picked it, and their kills and deaths in it (as playerinfo counts them for a
// player), each read from the members' own events
$result = $db->query("
        WITH members AS (
            SELECT playerId FROM hlstats_Players WHERE clan = $clan
        ),
        agg AS (
            SELECT
                IFNULL(MAX(r.name), e.role) AS name,
                IFNULL(MAX(r.code), e.role) AS code,
                COUNT(e.id) AS rolecount
            FROM members m
            JOIN hlstats_Events_ChangeRole e ON e.playerId = m.playerId
            JOIN hlstats_Servers s ON s.serverId = e.serverId
            JOIN hlstats_Roles r ON r.code = e.role
            WHERE
                s.game = '$game'
                AND r.game = '$game'
            GROUP BY e.role
        ),
        role_kills AS (
            SELECT f.killerRole AS role, COUNT(*) AS killsTotal
            FROM members m
            JOIN hlstats_Events_Frags f ON f.killerId = m.playerId
            GROUP BY f.killerRole
        ),
        role_deaths AS (
            SELECT f.victimRole AS role, COUNT(*) AS deathsTotal
            FROM members m
            JOIN hlstats_Events_Frags f ON f.victimId = m.playerId
            GROUP BY f.victimRole
        ),
        ranked AS (
            SELECT
                agg.name,
                agg.code,
                agg.rolecount,
                LEAST(ROUND(agg.rolecount / IF($numrolejoins = 0, 1, $numrolejoins) * 100, 2),100) AS percent,
                IFNULL(k.killsTotal, 0) AS killsTotal,
                IFNULL(d.deathsTotal, 0) AS deathsTotal,
                ROUND(IFNULL(k.killsTotal, 0) / IF(IFNULL(d.deathsTotal, 0) = 0, 1, d.deathsTotal), 2) AS kpd,
                RANK() OVER (ORDER BY agg.rolecount DESC, agg.code ASC) AS rank_position,
                COUNT(*) OVER() AS total_rows
            FROM agg
            LEFT JOIN role_kills k ON k.role = agg.code
            LEFT JOIN role_deaths d ON d.role = agg.code
        )
        SELECT * FROM ranked
        ORDER BY
            $sort $sortorder,
            $sort2 $sortorder
        ");
	
	$numitems = $db->num_rows($result);
	
	if ($db->num_rows($result)) {

       if (empty($_GET['ajax'])) {

		printSectionTitle(t('title.role.selection').$asterisk);

?>
<div id="roles">
<?php
}
?>
<div class="responsive-table">
  <table class="roles-table">
    <tr>
        <th class="hlstats-ranking nowrap<?= isSorted('rank_position',$sort,$sortorder) ?>"><?= headerUrl('rank_position', ['roles_sort','roles_sortorder'], 'roles') .t('th.rank') ?></a></th>
        <th class="<?= isSorted('code',$sort,$sortorder) ?>"><?= headerUrl('code', ['roles_sort','roles_sortorder'], 'roles') .t('th.role') ?></a></th>
        <th class="hlstats-main-description left<?= isSorted('rolecount',$sort,$sortorder) ?>"><?= headerUrl('rolecount', ['roles_sort','roles_sortorder'], 'roles') .t('th.picked') ?></a></th>
        <th class="meter-ratio nowrap hide-2<?= isSorted('percent',$sort,$sortorder) ?>"><?= headerUrl('percent', ['roles_sort','roles_sortorder'], 'roles') .t('th.ratio') ?></a></th>
        <th class="nowrap<?= isSorted('killsTotal',$sort,$sortorder) ?>"><?= headerUrl('killsTotal', ['roles_sort','roles_sortorder'], 'roles') .t('th.kills') ?></a></th>
        <th class="nowrap hide<?= isSorted('deathsTotal',$sort,$sortorder) ?>"><?= headerUrl('deathsTotal', ['roles_sort','roles_sortorder'], 'roles') .t('th.deaths') ?></a></th>
        <th class="nowrap hide-1<?= isSorted('kpd',$sort,$sortorder) ?>"><?= headerUrl('kpd', ['roles_sort','roles_sortorder'], 'roles') .t('th.kd') ?></a></th>
    </tr>
    <?php
        while ($res = $db->fetch_array($result))
        {
            $icode = strtolower($res['code']);
            $image = getImage("/games/$game/roles/" . $icode);
            // check if image exists for game -- otherwise check realgame
            if ($image) {
                $img = '<span class="hlstats-image"><img src="' . $image['url'] . '" alt="' . $icode . '" data-tooltip="'.htmlspecialchars($res['name'], ENT_QUOTES) . '" /></span>';
            } elseif ($image = getImage("/games/$realgame/roles/" . $icode)) {
                $img = '<span class="hlstats-image"><img src="' . $image['url'] . '" alt="' . $icode . '" data-tooltip="'.htmlspecialchars($res['name'], ENT_QUOTES) . '" /></span>';
            } elseif (!empty($res['name'])) {
                $img = '<span class="hlstats-name">' . $res['name'] . '</span>';
            } else {
                $img = '<span class="hlstats-name">' . ucwords(preg_replace('/_/', ' ', $icode)) . '</span>';
            }

            echo '<tr>
                  <td class="nowrap right">'.$res['rank_position'].'</td>
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
                  </tr>';
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
