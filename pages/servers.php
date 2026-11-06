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

require('livestats.php');
require_once INCLUDE_PATH . '/live.php';
$server_id = 1;

if ((isset($_GET['server_id'])) && (is_numeric($_GET['server_id']))) {
    $server_id = valid_request($_GET['server_id'], true);
} else {
    error(t('error.no.uniqueid'));
}

// A visit or a refresh: the page saved by the live refresh, when it still shows the current data (includes/live.php)
if (liveShowSaved()) return;

$query= "
        SELECT
            serverId,
            name,
            " . serverConnectAddressSql() . " AS addr,
            " . serverDisplayAddressSql() . " AS addr_text,
            CONCAT(address, ':', port) AS addr_local,
            statusurl,
            kills,
            players,
            rounds,
            suicides,
            headshots,
            bombs_planted,
            bombs_defused,
            ct_wins,
            ts_wins,
            ct_shots,
            ct_hits,
            ts_shots,
            ts_hits,
            act_players,
            max_players,
            act_map,
            map_started,
            map_ct_wins,
            map_ts_wins,
            last_event
        FROM
            hlstats_Servers
        WHERE
            serverId='$server_id'
    ";
$result = $db->query($query);
if (!$db->num_rows($result)) { error(t('error.uniqueid',["{$server_id}" => $server_id])); }

$server = array();
$server[] = $db->fetch_array($result);

if ($g_options['show_server_load_image'] == 1) {
	// The Servers title holds the graphs switch; the choice is kept in the browser and applied before the graphs show
?>
<script>try { if (localStorage.getItem('hlz-graphs') === 'off') document.documentElement.classList.add('hlz-graphs-off'); } catch (e) {}</script>
<h1 class="hlstats-title-bar"><span><?= t('title.server.live') ?></span>
	<button type="button" class="hlstats-graphs-toggle" aria-pressed="true" data-tooltip="<?= t('graphs.toggle') ?>" onclick="toggleGraphs(this)">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
		<span class="hlstats-graphs-label"><?= t('graphs') ?></span>
		<span class="hlstats-switch" aria-hidden="true"><span></span></span>
	</button>
</h1>
<?php
} else {
	printSectionTitle(t('title.server.live'));
}

$rowdata = $server[0]; 

if ($g_options['show_google_map'] == 1) {
?>
    <table class="hlstats-map">
        <tr>
            <td><div id="map"></div></td>
        </tr>
    </table>
<?php } ?>

<div class="hlstats-table-server livestats">
    <table class="livestats-table-server">
        <tr>
            <th class="hlstats-main-server left"><?= t('th.server') ?></th>
            <th class="hide"><?= t('th.address') ?></th>
            <th><?= t('th.map') ?></th>
            <th class="hide-1"><?= t('th.played') ?></th>
            <th><?= t('players') ?></th>
            <th class="hide-1"><?= t('th.kills') ?></th>
            <th class="hide-2"><?= t('th.headshots') ?></th>
            <th class="hide-3"><?= t('th.hsk') ?></th>
        </tr>
        <tr data-live="server-<?= (int)$server_id ?>">
            <td class="left">
            <?php
                $image = getImage("/games/$game/game");
                echo '<span class="hlstats-icon"><img src="';
                if ($image) {
                    echo $image['url'];
                } elseif ($image = getImage("/games/$realgame/game")) {
                    echo $image['url'];
                } else {
                    echo IMAGE_PATH . '/game.png';
                }
                echo "\" alt=\"$game\" /></span>";
                echo "<a href=\"" . $g_options['scripturl'] . "?game=$game\" style=\"text-decoration:none;\"><span class=\"hlstats-name\">" . htmlspecialchars(html_entity_decode($rowdata['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_COMPAT) . "</span></a>";
            ?>
            </td>
            <td class="hide nowrap">
            <?php
                $addr = htmlspecialchars(serverConnectUrl($rowdata['addr'], $rowdata['addr_local']));
                $addr_text = htmlspecialchars($rowdata['addr_text']);
                echo "<a data-tooltip=\"".t('map.join')."\" href=\"steam://connect/$addr\">$addr_text</a>";
            ?>
            </td>
            <td><?= $rowdata['act_map'] ?></td>
            <td class="hide-1 nowrap">
            <?php
                $stamp = $rowdata['map_started']==0?0:time() - $rowdata['map_started'];
                echo TimeStamp($stamp);
            ?>
            </td>
            <td><?= $rowdata['act_players']."/".$rowdata['max_players'] ?></td>
            <td class="hide-1 nowrap"><?= nf($rowdata['kills']) ?></td>
            <td class="hide-2 nowrap"><?= nf($rowdata['headshots']) ?></td>
            <td class="hide-3 nowrap">
            <?php
            if ($rowdata['kills'] > 0) {
                echo sprintf('%.2f', ($rowdata['headshots'] / $rowdata['kills']));
            } else {
                sprintf('%.2f', 0);
            }
            ?>
            </td>
        </tr>
</table>

<table class="hlstats-table-fixed" data-graphs>
  <tr class="hlstats-graph hlstats-chart">
   <td style="text-align:center; padding:0;">
     <div class="responsive-table">
        <?php if (!isset($g_options['chart']) || $g_options['chart'] == 0) { //pChart ?>
         <img src="show_graph.php?type=0&amp;game=<?php echo $game; ?>&amp;server_id=<?=$server_id?>" style="border:0px;" class="responsive" alt="Server Load Graph"/>
         <?php } else { //Chart.js ?>
         <div class="hlstats-chart hlstats-chart-thumb servers"
             data-chart="server-load"
             data-server-id="<?= (int)$server_id ?>"
             data-realgame="<?= htmlspecialchars($realgame ?? '', ENT_QUOTES) ?>"
             data-range="1">
            <div class="hlstats-chart-canvas"><canvas></canvas></div>
         </div>
        <?php } ?>
      </div>
     </td>
  </tr>
</table>

<?php
    printserverstats($server_id, $server);

echo '</div>';

if ($g_options['show_server_load_image'] == 1) {
	// The graphs switch in the title hides the live graph only; Server Load History below always stays visible
?>
<script>
(function() {
    var toggle = document.querySelector('.hlstats-graphs-toggle');
    function syncToggle() {
        if (toggle) toggle.setAttribute('aria-pressed', document.documentElement.classList.contains('hlz-graphs-off') ? 'false' : 'true');
    }
    window.toggleGraphs = function() {
        var off = document.documentElement.classList.toggle('hlz-graphs-off');
        try { localStorage.setItem('hlz-graphs', off ? 'off' : 'on'); } catch (e) {}
        syncToggle();
    };
    syncToggle();
})();
</script>
<?php
}

printSectionTitle(t('title.server.load'));

if (!isset($g_options['chart']) || $g_options['chart'] == 0) { //pChart
 ?>

    <table class="hlstats-table-fixed">
        <tr>
            <td class="left"><?= t('last.week') ?></td>
        </tr>
        <tr class="hlstats-graph">
            <td style="text-align:center;padding:0;">
            <div class="responsive-table">
                <img src="show_graph.php?type=0&amp;game=<?php echo $game; ?>&amp;server_id=<?php echo $server_id ?>&amp;range=2" class="responsive" alt="Last Week" />
            </div>
            </td>
        </tr>
        <tr>
            <td class="left"><?= t('last.month') ?></td>
        </tr>
        <tr class="hlstats-graph">
            <td style="text-align:center;padding:0;">
            <div class="responsive-table">
                <img src="show_graph.php?type=0&amp;game=<?php echo $game; ?>&amp;server_id=<?php echo $server_id ?>&amp;range=3" class="responsive" alt="Last Month" />
            </div>
            </td>
        </tr>
        <tr>
            <td class="left"><?= t('last.year') ?></td>
        </tr>
        <tr class="hlstats-graph">
            <td style="text-align:center;padding:0;">
            <div class="responsive-table">
                <img src="show_graph.php?type=0&amp;game=<?php echo $game; ?>&amp;server_id=<?php echo $server_id ?>&amp;range=4" class="responsive" alt="Last Year" />
            </div>
            </td>
        </tr>
    </table>

<?php } else { //Chart.js ?>
    <table class="hlstats-table-fixed">
        <tr class="hlstats-graph hlstats-chart">
            <td style="text-align:center; padding:0;">
             <div class="responsive-table">
                <div class="hlstats-chart hlstats-chart-thumb servers"
                    data-chart="server-load"
                    data-server-id="<?= (int)$server_id ?>"
                    data-realgame="<?= htmlspecialchars($realgame ?? '', ENT_QUOTES) ?>"
                    data-range="2">
                    <div class="hlstats-chart-range" role="tablist" aria-label="Range">
                        <button type="button" data-range="2" class="active"><?= t('last.week') ?></button>
                        <button type="button" data-range="3"><?= t('last.month') ?></button>
                        <button type="button" data-range="4"><?= t('last.year') ?></button>
                    </div>
                    <div class="hlstats-chart-canvas"><canvas></canvas></div>
                </div>
              </div>
            </td>
        </tr>
    </table>
<?php } ?>
<?php liveStart(); // live refresh every LIVE_INTERVAL seconds (includes/live.php) ?>
