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
	if (!$game) {
        error("No such game.");
	}
    // Role Statistics
    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';
    $sort2     = "kills";

    $col = array("rank_position","code","picked","ppercent","kills","kpercent","deaths","dpercent","kpd");
    if (!in_array($sort, $col)) {
        $sort      = "rank_position";
        $sortorder = "ASC";
    }

    if ($sort == "rank_position") {
        $sort2 = "kills";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

	$db->query("
		SELECT
			IF(IFNULL(SUM(hlstats_Roles.kills), 0) = 0, 1, SUM(hlstats_Roles.kills)),
			IF(IFNULL(SUM(hlstats_Roles.deaths), 0) = 0, 1, SUM(hlstats_Roles.deaths)),
			IF(IFNULL(SUM(hlstats_Roles.picked), 0) = 0, 1, SUM(hlstats_Roles.picked))
		FROM
			hlstats_Roles
		WHERE
			hlstats_Roles.game = '$game'
			AND hlstats_Roles.hidden = '0'
	");
	list($realkills, $realdeaths, $realpicked) = $db->fetch_row();

	$result = $db->query("
        WITH Ranked AS (
            SELECT
                code,
                name,
                picked,
                ROUND(picked / $realpicked * 100, 2) AS ppercent,
                kills,
                ROUND(kills / $realkills * 100, 2) AS kpercent,
                deaths,
                ROUND(deaths / $realdeaths * 100, 2) AS dpercent,
                ROUND(kills / IF(deaths = 0, 1, deaths), 2) AS kpd,
                RANK() OVER (ORDER BY picked DESC, kills DESC) AS rank_position
            FROM hlstats_Roles
            WHERE game = '$game'
              AND kills > 0
              AND hidden = '0'
        )
        SELECT *
        FROM Ranked
        ORDER BY
            $sort $sortorder,
            $sort2 $sortorder,
            code ASC
	");

if (!is_ajax()) {

printSectionTitle('Role Statistics');
?>
<div class="hlstats-cards-grid">
<section class="hlstats-section hlstats-card">
  <div class="hlstats-card-foot">From a total of <strong><?= nf($realkills) ?></strong> kills with <strong><?= nf($realdeaths); ?></strong> deaths</div>
</section>
</div>


<div id="roles">
<?php
}
if ($db->num_rows($result)) {
?>
<div class="responsive-table">
  <table class="roles-table">
    <tr>
        <th class="hlstats-ranking nowrap right<?= isSorted('rank_position',$sort,$sortorder) ?>"><?= headerUrl('rank_position', ['sort','sortorder'], 'roles') ?>Rank</a></th>
        <th class="hlstats-main-column<?= isSorted('code',$sort,$sortorder) ?>"><?= headerUrl('code', ['sort','sortorder'], 'roles') ?>Role</a></th>
        <th class="nowrap<?= isSorted('picked',$sort,$sortorder) ?>"><?= headerUrl('picked', ['sort','sortorder'], 'roles') ?>Picked</a></th>
        <th class="meter-ratio nowrap hide-2<?= isSorted('ppercent',$sort,$sortorder) ?>"><?= headerUrl('ppercent', ['sort','sortorder'], 'roles') ?>Ratio</a></th>
        <th class="nowrap hide<?= isSorted('kills',$sort,$sortorder) ?>"><?= headerUrl('kills', ['sort','sortorder'], 'roles') ?>Kills</a></th>
        <th class="meter-ratio nowrap hide-2<?= isSorted('kpercent',$sort,$sortorder) ?>"><?= headerUrl('kpercent', ['sort','sortorder'], 'roles') ?>Ratio</a></th>
        <th class="nowrap hide<?= isSorted('deaths',$sort,$sortorder) ?>"><?= headerUrl('deaths', ['sort','sortorder'], 'roles') ?>Deaths</a></th>
        <th class="meter-ratio nowrap hide-2<?= isSorted('dpercent',$sort,$sortorder) ?>"><?= headerUrl('dpercent', ['sort','sortorder'], 'roles') ?>Ratio</a></th>
        <th class="nowrap hide-3<?= isSorted('kpd',$sort,$sortorder) ?>"><?= headerUrl('kpd', ['sort','sortorder'], 'roles') ?>K:D</a></th>
    </tr>
    <?php
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
                  <td class="nowrap right">'.$res['rank_position'].'</td>
                  <td class="left"><a href="?mode=rolesinfo&amp;role='.$icode.'&amp;game='.$game.'">'.$img.'<span class="hlstats-name">'.htmlspecialchars($res['name']).'</span></a></td>
                  <td class="nowrap">'.nf($res['picked']).' times</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['ppercent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['ppercent'].'%</div>
                    </div>
                  </td>
                  <td class="nowrap hide">'.nf($res['kills']).'</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['kpercent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['kpercent'].'%</div>
                    </div>
                  </td>
                  <td class="nowrap hide">'.nf($res['deaths']).'</td>
                  <td class="nowrap hide-2">
                    <div class="meter-container">
                      <meter min="0" max="100" low="25" high="50" optimum="75" value="'.$res['dpercent'].'"></meter>
                      <div class="meter-value" id="meterText">'.$res['dpercent'].'%</div>
                    </div>
                  </td>
                  <td class="nowrap hide-3">'.$res['kpd'].'</td>
                  </tr>';
        }
   ?>
   </table>
   </div>
   <?php

  if (is_ajax()) exit;
} else { echo '<p class="hlstats-no-data"><em>Not enough data</em></p>'; }
  ?>
</div>
