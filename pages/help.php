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
if (!defined('IN_HLSTATS')) { die('Do not access this file directly'); }

$resultGames = $db->query("
    SELECT code, name, realgame
    FROM hlstats_Games
    WHERE hidden = '0'
    ORDER BY LOWER(name) ASC
");
$games = []; $gamesname = []; $realgames = [];
while ($res = $db->fetch_array($resultGames)) {
    $games[]     = $res['code'];
    $gamesname[] = $res['name'];
    $realgames[] = $res['realgame'];
}

// ── Actions & Point Bonuses ───────────────────────────────────────────────
if (!is_ajax()) { ?>
<div class="hlstats-paragraph">
    <?php printSectionTitle(t('help.title.hlstatsz'));
    echo t('help.hlstatsz');

    printSectionTitle(t('help.title.actions')); ?>
    <div id="act_help" tabindex="-1">
<?php }

if (!is_ajax() || ($_GET['ajax'] ?? '') === 'act_help') {

    $col = ['for_PlayerActions','for_PlayerPlayerActions','for_TeamActions','for_WorldActions','description','s_reward_player','s_reward_team'];
    $sort      = in_array($_GET['act_sort'] ?? '', $col) ? $_GET['act_sort'] : 'description';
    $sortorder = strtoupper($_GET['act_sortorder'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
    $page      = max(0, min(count($games) - 1, (int)($_GET['act_page'] ?? 1) - 1));
    $g         = $games[$page];

    $result = $db->query("
        SELECT hlstats_Actions.description,
               IF(SIGN(hlstats_Actions.reward_player) > 0,
                  CONCAT('+', hlstats_Actions.reward_player),
                  hlstats_Actions.reward_player) AS s_reward_player,
               IF(hlstats_Actions.team != '' AND hlstats_Actions.reward_team != 0,
                  IF(SIGN(hlstats_Actions.reward_team) >= 0,
                     CONCAT(hlstats_Teams.name, ' +', hlstats_Actions.reward_team),
                     CONCAT(hlstats_Teams.name, ' ',  hlstats_Actions.reward_team)), '') AS s_reward_team,
               IF(for_PlayerActions='1',       'Yes','No') AS for_PlayerActions,
               IF(for_PlayerPlayerActions='1', 'Yes','No') AS for_PlayerPlayerActions,
               IF(for_TeamActions='1',         'Yes','No') AS for_TeamActions,
               IF(for_WorldActions='1',        'Yes','No') AS for_WorldActions
        FROM hlstats_Actions
        INNER JOIN hlstats_Games ON hlstats_Games.code = hlstats_Actions.game AND hlstats_Games.hidden = '0'
        LEFT JOIN  hlstats_Teams ON hlstats_Teams.code = hlstats_Actions.team AND hlstats_Teams.game = hlstats_Actions.game
        WHERE hlstats_Games.code = '$g'
        ORDER BY $sort $sortorder
    ");

    echo '<div class="responsive-table"><table class="help-table"><tr>';
    echo '<tr><td colspan="7"><span class="hlstats-name big">' . htmlspecialchars($gamesname[$page]) . '</span></td></tr>';

    echo '<th class="left' . isSorted('description',$sort,$sortorder)             . '">' . headerUrl('description',             ['act_sort','act_sortorder'], 'act_help') . t('th.action') .'</a></th>';
    echo '<th class="'     . isSorted('for_PlayerActions',$sort,$sortorder)       . '">' . headerUrl('for_PlayerActions',       ['act_sort','act_sortorder'], 'act_help') . t('player') .'</a></th>';
    echo '<th class="'     . isSorted('for_PlayerPlayerActions',$sort,$sortorder) . '">' . headerUrl('for_PlayerPlayerActions', ['act_sort','act_sortorder'], 'act_help') . t('player').'↔'.t('player').'</a></th>';
    echo '<th class="'     . isSorted('for_TeamActions',$sort,$sortorder)         . '">' . headerUrl('for_TeamActions',         ['act_sort','act_sortorder'], 'act_help') . t('team') .'</a></th>';
    echo '<th class="'     . isSorted('for_WorldActions',$sort,$sortorder)        . '">' . headerUrl('for_WorldActions',        ['act_sort','act_sortorder'], 'act_help') . t('world') .'</a></th>';
    echo '<th class="'     . isSorted('s_reward_player',$sort,$sortorder)         . '">' . headerUrl('s_reward_player',         ['act_sort','act_sortorder'], 'act_help') . t('player.points') .'</a></th>';
    echo '<th class="'     . isSorted('s_reward_team',$sort,$sortorder)           . '">' . headerUrl('s_reward_team',           ['act_sort','act_sortorder'], 'act_help') . t('team.points') .'</a></th>';
    echo '</tr>';

    while ($res = $db->fetch_array($result)) {
        echo '<tr>
              <td class="left"><span class="hlstats-name">' . htmlspecialchars($res['description']) . '</span></td>
              <td>' . $res['for_PlayerActions']       . '</td>
              <td>' . $res['for_PlayerPlayerActions'] . '</td>
              <td>' . $res['for_TeamActions']         . '</td>
              <td>' . $res['for_WorldActions']        . '</td>
              <td>' . $res['s_reward_player']         . '</td>
              <td>' . $res['s_reward_team']           . '</td>
              </tr>';
    }
    echo '</table></div>';
    echo Pagination(count($games), $_GET['act_page'] ?? 1, 1, 'act_page', true, 'act_help');
    echo t('help.showing.game', ['{game}' => '<span class="hlstats-name">' . htmlspecialchars($gamesname[$page]) . '</span>']);

    if (is_ajax()) exit;
    echo '<br>' . t('help.note') . '<br>';
}

if (!is_ajax()) { ?>
    </div>

    <?php printSectionTitle(t('help.title.weapons')); ?>
    <div id="weap_help" tabindex="-1">
<?php }

// ── Weapon Points Modifiers ───────────────────────────────────────────────
if (!is_ajax() || ($_GET['ajax'] ?? '') === 'weap_help') {

    $col = ['code','name','modifier'];
    $sort      = in_array($_GET['weap_sort'] ?? '', $col) ? $_GET['weap_sort'] : 'code';
    $sortorder = strtoupper($_GET['weap_sortorder'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
    $page      = max(0, min(count($games) - 1, (int)($_GET['weap_page'] ?? 1) - 1));
    $g         = $games[$page];

    $result = $db->query("
        SELECT hlstats_Weapons.code, hlstats_Weapons.name, hlstats_Weapons.modifier
        FROM hlstats_Weapons
        INNER JOIN hlstats_Games ON hlstats_Games.code = hlstats_Weapons.game AND hlstats_Games.hidden = '0'
        WHERE hlstats_Games.code = '$g'
        ORDER BY $sort $sortorder
    ");

    echo '<div class="responsive-table"><table class="help-table"><tr>';
    echo '<tr><td colspan="7"><span class="hlstats-name big">' . htmlspecialchars($gamesname[$page]) . '</span></td></tr>';
    echo '<th class="left' . isSorted('code',$sort,$sortorder)     . '">' . headerUrl('code',     ['weap_sort','weap_sortorder'], 'weap_help') . t('th.weapon') .'</a></th>';
    echo '<th class="left' . isSorted('name',$sort,$sortorder)     . '">' . headerUrl('name',     ['weap_sort','weap_sortorder'], 'weap_help') . t('name') .'</a></th>';
    echo '<th class="'     . isSorted('modifier',$sort,$sortorder) . '">' . headerUrl('modifier', ['weap_sort','weap_sortorder'], 'weap_help') . t('points.modifier') .'</a></th>';
    echo '</tr>';

    while ($res = $db->fetch_array($result)) {
        $weapon  = strtolower($res['code']);
        $code    = htmlspecialchars($res['code']);
        $image   = getImage("/games/$g/weapons/$weapon")
                ?: getImage('/games/' . $realgames[$page] . '/weapons/' . $weapon);
        $weapimg = $image
            ? '<span class="hlstats-image"><img src="' . $image['url'] . '" ' . $image['size'] . ' alt="' . $code . '" data-tooltip="'.htmlspecialchars($code, ENT_QUOTES) . '"></span><span class="hlstats-name">' . $code . '</span>'
            : '<span class="hlstats-name">' . $code . '</span>';
        echo '<tr>
              <td class="left">' . $weapimg . '</td>
              <td class="left">' . htmlspecialchars($res['name']) . '</td>
              <td>' . $res['modifier'] . '</td>
              </tr>';
    }
    echo '</table></div>';
    echo Pagination(count($games), $_GET['weap_page'] ?? 1, 1, 'weap_page', true, 'weap_help');
    echo t('help.showing.game', ['{game}' => '<span class="hlstats-name">' . htmlspecialchars($gamesname[$page]) . '</span>']);

    if (is_ajax()) exit;
}

// ── In-game Commands ─────────────────────────────────────────────────────
if (!is_ajax()) { ?>
    </div>

    <?php printSectionTitle(t('help.title.commands')); ?>
    <div class="hlstats-help-commands">
        <p><?= t('help.command') ?></p>
        <ul>
            <li><code>rank</code></li>
            <li><code>top10</code></li>
            <li><code>next</li>
            <li><code>statsme</li>
            <li><code>session</li>
        </ul>
    </div>
</div>
<?php } ?>
