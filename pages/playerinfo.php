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

function makeClanTag(string $name): array{
    $words = preg_split('/\s+/', trim($name));
    $tag = '';

    if (count($words) > 1) {
        $tag  = $words[0] ?? '';
        $name =  $words[1] ?? '';
        
    } else {
        $tag = '[' . strtoupper(substr($name, 0, 3)) . ']';
    }

    return [$tag , $name];
}

    // Player Details
    $player = valid_request(intval($_GET['player'] ?? 0), true);
    $uniqueid = valid_request(strval($_GET['uniqueid'] ?? ''), false);
    $game = valid_request(strval($_GET['game'] ?? ''), false);

    if (!$player && $uniqueid) {
        if (!$game) {
            header("Location: " . $g_options['scripturl'] . "&mode=search&st=uniqueid&q=$uniqueid");
            exit;
        }

        $uniqueid = preg_replace('/^STEAM_\d+?\:/i','',$uniqueid);

        $db->query("
            SELECT
                hlstats_PlayerUniqueIds.playerId
            FROM
                hlstats_PlayerUniqueIds
            WHERE
                hlstats_PlayerUniqueIds.uniqueId = '$uniqueid'
        ");

        if ($db->num_rows() > 1) {
            header("Location: " . $g_options['scripturl'] . "&mode=search&st=uniqueid&q=$uniqueid&game=$game");
            exit;
        } elseif ($db->num_rows() < 1) {
            error("No players found matching uniqueId '$uniqueid'");
        } else {
            list($player) = $db->fetch_row();
            $player = intval($player);
        }
    } elseif (!$player && !$uniqueid) {
        error("No player ID specified.");
    }

    $db->query("
        SELECT
            hlstats_PlayerUniqueIds.uniqueId,
            CAST(LEFT(hlstats_PlayerUniqueIds.uniqueId,1) AS unsigned)
                + CAST('76561197960265728' AS unsigned)
                + CAST(MID(hlstats_PlayerUniqueIds.uniqueId, 3,10)*2 AS unsigned) AS communityId
        FROM
            hlstats_PlayerUniqueIds
        WHERE
            hlstats_PlayerUniqueIds.playerId = '$player'
    ");
    list($uqid, $coid) = $db->fetch_row();


    // Build rank comparison expression for correlated COUNT subquery.
    // This replaces RANK() OVER which forces a full-table scan + sort on every page load.
    if ($g_options['rankingtype'] !== 'kills') {
        $rank_above = "(p2.skill > p.skill OR (p2.skill = p.skill AND p2.kills > p.kills))";
    } else {
        $rank_above = "(p2.kills > p.kills OR (p2.kills = p.kills AND p2.deaths < p.deaths))";
    }

    $db->query("SELECT game
                FROM hlstats_players
                WHERE playerId = '$player'
                LIMIT 1
               ");

    list($game) = $db->fetch_row();

    $db->query("SELECT
                    p.playerId,
                    p.last_event,
                    p.connection_time,
                    p.game,
                    p.createdate,
                    p.lastName,
                    p.lastPing,
                    p.homepage,
                    p.flag,
                    p.country,
                    p.state,
                    p.city,
                    p.clan,
                    p.kills,
                    p.deaths,
                    p.skill,
                    p.shots,
                    p.hits,
                    p.headshots,
                    p.suicides,
                    p.last_skill_change,
                    p.kill_streak,
                    p.death_streak,
                    p.teamkills,
                    p.activity,
                    ROUND(IF(p.deaths=0, 0, p.kills/p.deaths), 2) AS kpd,
                    ROUND(IF(p.kills=0, 0, p.headshots/p.kills), 2) AS hpk,
                    ROUND(IF(p.shots=0, 0, p.hits/p.shots), 3) AS acc,
                    CONCAT(c.name) AS clan_name,
                    p.hideranking,
                    (SELECT COUNT(*) + 1
                     FROM hlstats_players p2
                     WHERE p2.game = p.game
                       AND p2.lastAddress <> ''
                       AND p2.hideranking = 0
                       AND $rank_above
                    ) AS rank_position
                FROM hlstats_players AS p
                LEFT JOIN hlstats_Clans AS c ON c.clanId = p.clan
                WHERE p.playerId = '$player'
                  AND p.lastAddress <> ''
                  AND p.hideranking <> 1
                LIMIT 1
               ");

    if ($db->num_rows() != 1) {
        error("No such player '$player'.");
    }

    $playerdata = $db->fetch_array();
    $db->free_result();

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['ID64']) && $_SESSION['ID64'] == $coid) {

        $new_homepage = trim($_POST['homepage'] ?? '');

        if ($new_homepage && !filter_var($new_homepage, FILTER_VALIDATE_URL)) {
            $error = "Invalid homepage URL.";
        } else {
            $new_clan_name = trim($_POST['clan_name'] ?? '');
            $clan_id = 0;
            if ($new_clan_name) {
                $result = $db->query("SELECT clanId FROM hlstats_Clans WHERE LOWER(name) = LOWER('$new_clan_name') OR LOWER(tag) = LOWER('$new_clan_name') LIMIT 1");
                list($clan_id) = $db->fetch_row($result);
                if (!$clan_id) {
                    list($clan_tag, $clan_name) = makeClanTag($new_clan_name);
                    $tag_esc  = $db->escape($clan_tag);
                    $name_esc = $db->escape($clan_name);
                    $game_esc = $db->escape($playerdata['game']);
                    $sql = "
                        INSERT INTO hlstats_Clans (tag, name, game)
                        VALUES ('$tag_esc', '$name_esc', '$game_esc')
                        ON DUPLICATE KEY UPDATE
                            name = VALUES(name),
                            clanId = LAST_INSERT_ID(clanId)
                    ";

                    $db->query($sql);

                    $clan_id = $db->insert_id();

                    if (!$clan_id) {
                        $error = "Failed to create clan.";
                    }
                }

            }

            if (!$error) {
                $db->query("UPDATE hlstats_Players SET homepage = '$new_homepage', clan = " . ($clan_id ? "'$clan_id'" : 0) . " WHERE playerId = '$player'");
                $playerdata['homepage'] = $new_homepage;
                $playerdata['clan'] = $clan_id;
                if ($clan_id) {
                    $result = $db->query("SELECT name FROM hlstats_Clans WHERE clanId = '$clan_id'");
                    list($playerdata['clan_name']) = $db->fetch_row($result);
                } else {
                    $playerdata['clan_name'] = '';
                }
            }
        }

    }


    $pl_name = $playerdata['lastName'];

    if (strlen($pl_name) > 10) {
        $pl_shortname = substr($pl_name, 0, 8) . '...';
    } else {
        $pl_shortname = $pl_name;
    }

    $pl_name = htmlspecialchars($pl_name, ENT_COMPAT);
    $pl_shortname = htmlspecialchars($pl_shortname);
    $pl_urlname = urlencode($playerdata['lastName']);
    $game = $playerdata['game'];

    $hideranking = $playerdata['hideranking'];

    if ($hideranking == 2) {
        $statusmsg = '<span class="hlstats-status banned;">banned</span>';
    } else {
        $statusmsg = '<span class="hlstats-status good;">In good standing</span>';
    }
// Required on a few pages, just decided to add it here
// May get moved in the future

$db->query("
        SELECT
            COUNT(hlstats_Events_Frags.killerId)
        FROM
            hlstats_Events_Frags
        WHERE
            hlstats_Events_Frags.killerId = '$player'
            AND hlstats_Events_Frags.headshot = 1
    ");

    list($realheadshots) = $db->fetch_row();

    $db->query("
        SELECT
            COUNT(hlstats_Events_Frags.killerId)
        FROM
            hlstats_Events_Frags
        WHERE
            hlstats_Events_Frags.killerId = '$player'
    ");

    list($realkills) = $db->fetch_row();

    $db->query("
        SELECT
            COUNT(hlstats_Events_Frags.victimId)
        FROM
            hlstats_Events_Frags
        WHERE
            hlstats_Events_Frags.victimId = '$player'
    ");

    list($realdeaths) = $db->fetch_row();

    $db->query("
        SELECT
            COUNT(hlstats_Events_Teamkills.killerId)
        FROM
            hlstats_Events_Teamkills
        WHERE
            hlstats_Events_Teamkills.killerId = '$player'
    ");

    list($realteamkills) = $db->fetch_row();

    if (!isset($_GET['killLimit'])) {
        $killLimit = 1;
    } else {
        $killLimit = valid_request($_GET['killLimit'], true);
    }

    if (is_ajax()) {
        $tabs = explode('_', preg_replace('[^a-z]', '', $_GET['tab']));

        foreach ($tabs as $tab) {
            if (file_exists(PAGE_PATH . "/playerinfo_$tab.php")) {
                @include(PAGE_PATH . "/playerinfo_$tab.php");
            }
        }
        exit;
    }


    if ($g_options['modrewrite'] == 0) {
       $imglink  = $script_path.'/sig.php?player_id='.$player.'&amp;background='.$g_options['sigbackground'];
        $jimglink = $script_path.'/sig.php?player_id='.$player.'&background='.$g_options['sigbackground'];
    } else {
        $imglink  = $script_path.'/sig-'.$player.'-'.$g_options['sigbackground'].'.png';
        $jimglink = $imglink;
    }

?>
<div class="hlstats-tabs-bar">
<ul class="hlstats-tabs" id="tabs_playerinfo">
    <li class="active">
        <a href="#general_aliases" class="tab" data-url="general_aliases" data-target="tab1">General</a>
    </li>
    <li>
        <a href="#playeractions_teams" class="tab" data-url="playeractions_teams" data-target="tab2">Teams &amp; Actions</a>
    </li>
    <li>
        <a href="#weapons" class="tab" data-url="weapons" data-target="tab3">Weapons</a>
    </li>
    <li>
        <a href="#mapperformance_servers" class="tab" data-url="mapperformance_servers" data-target="tab4">Maps &amp; Servers</a>
    </li>
    <li>
        <a href="#killstats" class="tab" data-url="killstats" data-target="tab5">Killstats</a>
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
const playerSteamProfileUrl = "<?= $g_options['scripturl'] ?>?mode=playerinfo&game=<?= urlencode($game) ?>&player=<?= (int)$player ?>&tab=steamprofile&ajax=steamprofile";

function loadPlayerSteamProfile() {
    const el = document.getElementById("steam-profile-<?= (int)$player ?>");
    if (!el || el.dataset.loaded === "true") {
        return;
    }

    el.dataset.loaded = "true";
    Fetch.run(playerSteamProfileUrl, el, false).then(() => {
        const memberSince = document.getElementById("steam-member-since-value-<?= (int)$player ?>");
        const target = document.getElementById("steam-member-since-<?= (int)$player ?>");

        if (memberSince && target) {
            target.textContent = memberSince.dataset.memberSince || memberSince.textContent || "Private";
        }
    }).catch(() => {
        const target = document.getElementById("steam-member-since-<?= (int)$player ?>");

        if (target) {
            target.textContent = "Unavailable";
        }

        el.classList.remove("is-loading");
    });
}

document.getElementById("tab1")?.addEventListener("fetch:loaded", event => {
    if (event.detail && typeof event.detail.url === "string" && event.detail.url.indexOf("tab=general_aliases") !== -1) {
        loadPlayerSteamProfile();
    }
});

Tabs.init({
    baseParams: {
        mode: "playerinfo",
        game: "<?= $game ?>",
        player: "<?= $player ?>"
    }
});

function setForumText(val) {
    var txtArea = document.getElementById('siglink');
    switch(val)
    {
        case 0:
            <?php echo "txtArea.value = '$jimglink'\n"; ?>
            break;
        case 1:
            <?php echo "txtArea.value = '[url=$script_path/hlstats.php?mode=playerinfo&player=$player"."][img]$jimglink"."[/img][/url]'\n"; ?>
            break;
        case 2:
            <?php echo "txtArea.value = '[url=\"$script_path/hlstats.php?mode=playerinfo&player=$player\"][img]$jimglink"."[/img][/url]'\n"; ?>
            break;
    }
}
</script>
<?php if ($g_options['DeleteDays']) { ?>
        <div class="hlstats-note">
            Items marked "*" above are generated from the most recent <strong><?php echo $g_options['DeleteDays']; ?></strong> days of activity.
        </div>
<?php }
    if ((!empty($_SESSION['loggedin']) && (int)($_SESSION['acclevel'] ?? 0) >= 100) || STEAM_ADMIN === ($_SESSION['ID64'] ?? ''))
    {
        echo '<div style="float:right;">';
        echo 'Admin Options &rarr; <a href="'.$g_options['scripturl']."?mode=admin&amp;task=tools_editdetails_player&amp;id=$player\">Edit Player Details</a>";
        echo '</div>';
    }
?>

</div>
