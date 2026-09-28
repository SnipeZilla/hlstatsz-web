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

    if (!empty($_GET['ajax']) && $_GET['ajax'] === 'voicecomm') {
        global $db;
        $resultVoices = $db->query("
            SELECT serverId, name, addr, password, descr, queryPort, UDPPort, serverType
            FROM hlstats_Servers_VoiceComm
        ");
        include(PAGE_PATH . '/voicecomm_serverlist.php');
        exit;
    }

    if (($num_games == 1 && $num_voices == 0) || !empty($_GET['game'])) {
        include(PAGE_PATH . '/game.php');

    } else {
      if ($num_games) {
        unset($_SESSION['game']);

        // Load all visible games up front: the stats section (rendered first) needs the full game list
        $games = [];
        $gamecodes = [];
        while ($gamedata = $db->fetch_row($resultGames)) {
            $games[] = $gamedata;
            $gamecodes[] = "'" . $db->escape($gamedata[0]) . "'";
        }
        $nonhiddengamestring = '(' . implode(',', $gamecodes) . ')';

        // Per-game online/slots + global server, kill and online totals in a single query
        $serverstats = [];
        $num_servers = 0;
        $num_kills   = 0;
        $num_online  = 0;
        $num_busy    = 0;
        $result = $db->query("
            SELECT
                game,
                COUNT(serverId) AS servers,
                SUM(kills) AS kills,
                SUM(act_players) AS act_players,
                SUM(max_players) AS max_players,
                SUM(act_players > 0) AS busy
            FROM
                hlstats_Servers
            WHERE
                game IN $nonhiddengamestring
            GROUP BY
                game
        ");
        while ($row = $db->fetch_array($result)) {
            $serverstats[$row['game']] = $row;
            $num_servers += (int)$row['servers'];
            $num_kills   += (int)$row['kills'];
            $num_online  += (int)$row['act_players'];
            $num_busy    += (int)$row['busy'];
        }

        $result = $db->query("
            SELECT
                (SELECT COUNT(playerId) FROM hlstats_Players WHERE game IN $nonhiddengamestring),
                (SELECT COUNT(clanId) FROM hlstats_Clans WHERE game IN $nonhiddengamestring),
                (SELECT eventTime FROM hlstats_Events_Frags ORDER BY id DESC LIMIT 1)
        ");
        list($num_players, $num_clans, $lastevent) = $db->fetch_row($result);

        printSectionTitle(t('title.stats'));

        // Headline tiles, as the Ban Statistics ones: label, number, note
        $tiles = [
            [t('players'), $num_players, t('contents.kpi.online', ['{n}' => nf($num_online)])],
            [t('clans'), $num_clans, t('contents.kpi.games', ['{n}' => nf($num_games)])],
            [t('contents.kpi.servers'), $num_servers, t('contents.kpi.busy', ['{n}' => nf($num_busy)])],
            [t('th.kills'), $num_kills, $lastevent ? t('contents.last_kill', ['{date}' => formatDate(strtotime($lastevent), IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT)]) : ''],
        ];
?>
        <div class="hlstats-cards-grid hlstats-kpis">
            <?php foreach ($tiles as [$label, $value, $note]) { ?>
            <div class="hlstats-card hlstats-kpi">
                <div class="hlstats-kpi-label"><?= $label ?></div>
                <div class="hlstats-kpi-value"><?= nf($value) ?></div>
                <div class="hlstats-kpi-note"><?= $note ?></div>
            </div>
            <?php } ?>
        </div>

<?php
        printSectionTitle(t('title.games'));

if ($g_options['show_google_map'] == 1) {
?>
    <table class="hlstats-map">
        <tr>
            <td><div id="map"></div></td>
        </tr>
</table>
<?php
}
?>
            <table>
                <tr>
                    <th class="hlstats-main-description left responsive"><?= t('game.servers') ?></th>
                    <th class="hide-2"></th>
                    <th class="hide-2"></th>
                    <th><?= t('players') ?></th>
                    <th class="hide"><?= t('top.player') ?></th>
                    <th class="hide-2"><?= t('top.clan') ?></th>
                </tr>
<?php
        foreach ($games as $gamedata)
        {
            $gamecode = $db->escape($gamedata[0]);
            $result = $db->query("
                SELECT
                    playerId,
                    lastName,
                    activity
                FROM
                    hlstats_Players
                WHERE
                    game='$gamecode'
                    AND hideranking=0
                    AND lastAddress <> ''
                ORDER BY
                    ".$g_options['rankingtype']." DESC,
                    (kills/IF(deaths=0,1,deaths)) DESC
                LIMIT 1
            ");

            if ($db->num_rows($result) == 1)
            {
                $topplayer = $db->fetch_row($result);
            }
            else
            {
                $topplayer = false;
            }

            $result = $db->query("
            SELECT
                c.clanId,
                c.name,
                AVG(p.skill) AS skill,
                AVG(p.kills) AS kills,
                COUNT(p.playerId) AS numplayers
            FROM
                hlstats_Clans AS c
            INNER JOIN
                hlstats_Players AS p
                    ON p.clan = c.clanId
                    AND p.game = c.game
                    AND p.hideranking = 0
            WHERE
                c.game = '$gamecode'
                AND c.hidden = 0
            GROUP BY
                c.clanId
            HAVING
                numplayers >= 2
            ORDER BY
                ".$g_options['rankingtype']." DESC
            LIMIT 1;
            ");

            if ($db->num_rows($result) == 1)
            {
                $topclan = $db->fetch_row($result);
            }
            else
            {
                $topclan = false;
            }

            $numplayers = $serverstats[$gamedata[0]] ?? null;
            if ($numplayers && ($numplayers['act_players'] > 0 || $numplayers['max_players'] > 0)) {
                $player_string = (int)$numplayers['act_players'].'/'.(int)$numplayers['max_players'];
            } else {
                $player_string = '-';
            }
?>
                <tr>
                    <td class="hlstats-main-column left">
                <a href="<?= $g_options['scripturl'] . "?game=$gamedata[0]" ?>">

            <?php $image = getImage("/games/$gamedata[0]/game");
                  if (!$image) $image = getImage("/games/$gamedata[2]/game"); ?>

                <span class="hlstats-icon"><img src="<?php echo ($image ? $image['url'] : IMAGE_PATH . '/game.png' ); ?>" alt="Game" /></span>
                <span class="hlstats-name"><?= $gamedata[1] ?></span></a>
                </td>
                        <td class="hide-2">
                <a href="<?= $g_options['scripturl'] . "?mode=players&amp;game=$gamedata[0]" ?>">🎮 <?= t('players') ?></a>
                        </td>
                        <td class="hide-2">
                <a href="<?= $g_options['scripturl'] . "?mode=clans&amp;game=$gamedata[0]" ?>">⚔️ <?= t('clans') ?></a>
                        </td>
                <td><?= $player_string ?></td>
                <td class="hide">
            <?php if ($topplayer) { ?>
                    <a href="<?= $g_options['scripturl'] . "?mode=playerinfo&amp;player=" . $topplayer[0] . "&amp;game=" . $gamedata[0] ?>"><?= htmlspecialchars(html_entity_decode($topplayer[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_COMPAT) ?></a>
            <?php } else { echo '-'; } ?>
                </td>
                <td class="hide-2">
            <?php if ($topclan) { ?>
                    <a href="<?= $g_options['scripturl'] . "?mode=claninfo&amp;clan=" . $topclan[0] . "&amp;game=" . $gamedata[0] ?>"><?= htmlspecialchars($topclan[1]) ?></a>
            <?php } else { echo '-'; } ?>
                </td>
            </tr>
<?php
        }
?>
            </table>

<?php
      }
        if ($num_voices) {
            $voicecomm_url = htmlspecialchars($_SERVER['PHP_SELF'] . '?mode=contents&ajax=voicecomm');
            echo '<div id="voicecomm-container" data-fetch-url="' . $voicecomm_url . '"></div>';
?>
            <script>
            (function () {
                var url = <?= json_encode($_SERVER['PHP_SELF'] . '?mode=contents&ajax=voicecomm') ?>;
                var el  = document.getElementById('voicecomm-container');
                el.addEventListener('fetch:loaded', function (e) {
                    if (e.detail.html.indexOf('data-voicecomm-stale') !== -1) {
                        fetch(url + '&refresh=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(function (r) { return r.text(); })
                            .then(function (html) { el.innerHTML = html; });
                    }
                });
                Fetch.run(url, el, false);
            })();
            </script>
<?php
        }
?>
<div class="hlstats-note">
    <p><?= t('contents.realtime') ?>
        <?php if ($g_options['DeleteDays']) { ?>
        <?= t('contents.history', ['{days}' => '<strong>'.$g_options['DeleteDays'].'</strong>']) ?>
        <?php } ?>
    </p>
</div>
<?php
    }
?>
