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

	// Daily Award Statistics
	$award = valid_request($_GET['award'] ?? '', true) or error(t('error.no.uniqueid'));

	$db->query("
		SELECT
			awardType,
			code,
			name,
			verb
		FROM
			hlstats_Awards
		WHERE
			hlstats_Awards.awardid=$award
	");
	
	$awarddata = $db->fetch_array();
	$db->free_result();
	$awardname = $awarddata['name'] ?? '';
	$awardverb = $awarddata['verb'] ?? '';
	$awardtype = $awarddata['awardType'] ?? '';
	$awardcode = $awarddata['code'] ?? '';

   if (!$awardcode) error(t('error.uniqueid',["{uniqueid}" => $award]));

    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';
    $sort2     = "awardTime";

    $sortby = $sort;
    $order  = $sortorder;

    $col = array("rank_position","count","awardTime","lastName");
    if (!in_array($sort, $col)) {
        $sort      = "rank_position";
        $sortorder = "ASC";
    }

    if ($sort == "awardTime") {
        $sort2 = "count";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;

	$result = $db->query("
		WITH award_data AS (
			SELECT
				hlstats_Players_Awards.playerId,
				awardTime,
				lastName,
				flag,
				country,
				count
			FROM
				hlstats_Players_Awards
			LEFT JOIN
				hlstats_Players
			ON
				hlstats_Players_Awards.playerId = hlstats_Players.playerId
			WHERE
				awardid=$award
		),
		ranked AS (
			SELECT *,
				RANK() OVER (ORDER BY count DESC, awardTime DESC) AS rank_position,
				COUNT(*) OVER() AS total_rows
			FROM award_data
		)
		SELECT * FROM ranked
		ORDER BY
			$sort $sortorder,
			$sort2 $sortorder
		LIMIT 30 OFFSET $start
	");

if (!is_ajax()) {
?>

	<?php printSectionTitle(t('title.daily.award.details')); ?>
<div class="hlstats-cards-grid">
  <section class="hlstats-section hlstats-card">


<div class="hlstats-award has-winner">
  <div class="hlstats-award-title"><?= htmlspecialchars($awardname)?></div>
  <div class="hlstats-award-icon">
	<?php
	$img = IMAGE_PATH."/games/$game/dawards/".strtolower($awardtype).'_'.strtolower($awardcode).'.png';
	if (!is_file($img))
	{
		$img = IMAGE_PATH.'/award.png';
	}
	echo "<img src=\"$img\" alt=\"$awardcode\" />";
    ?></div>
</div>

  </section>
</div>



<div id="dailyawardinfo">
<?php
}
?>
<div  class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="hlstats-ranking nowrap<?= isSorted('rank_position',$sort,$sortorder) ?>"><?= headerUrl('rank_position', ['sort','sortorder'], 'dailyawardinfo') ?>Rank</a></th>
        <th class="<?= isSorted('awardTime',$sort,$sortorder) ?>"><?= headerUrl('awardTime', ['sort','sortorder'], 'dailyawardinfo') ?>Day</a></th>
        <th class="hlstats-main-description left<?= isSorted('lastName',$sort,$sortorder) ?>"><?= headerUrl('lastName', ['sort','sortorder'], 'dailyawardinfo') ?>Player</a></th>
        <th class="nowrap<?= isSorted('count',$sort,$sortorder) ?>"><?= headerUrl('count', ['sort','sortorder'], 'dailyawardinfo') ?>Daily Count</a></th>
    </tr>
    <?php
        while ($res = $db->fetch_array($result))
        {
            $total = $res['total_rows'];
            echo '<tr>
                  <td class="nowrap right">'.$res['rank_position'].'</td>
                  <td class="nowrap"><span class="hlstats-name">'.$res['awardTime'].'</span></a></td>
                  <td class="hlstats-main-description left">
                      <span class="hlstats-flag"><img src="'.getFlag($res['flag']).'" data-tooltip="'.htmlspecialchars($res['country'] ?? '', ENT_QUOTES).'" alt="'.$res['flag'].'"></span>
                      <a href="?mode=playerinfo&amp;player='.$res['playerId'].'"></span><span class="hlstats-name">'.htmlspecialchars($res['lastName'] ?? '').'&nbsp;</span></a>
                  </td>
                  <td class="nowrap">'.$res['count'].' times</td>
                  </tr>';
        }
   ?>
   </table>
   </div>
   <?php
       echo Pagination($total ?? 0, $_GET['page'] ?? 1, 30, 'page', true, 'dailyawardinfo');

  if (is_ajax()) exit;
  ?>
</div>
<div class="hlstats-note">
<a href="<?php echo $g_options['scripturl'] . "?mode=awards&amp;game=$game"; ?>">&larr;&nbsp<?= t('goto.daily.awards') ?></a>
</div>
