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

    // Player History
	$player = valid_request(intval($_GET['player'] ?? 0), true) or error('No player ID specified.');

	$db->query("
		SELECT
			hlstats_Players.lastName,
			hlstats_Players.game
		FROM
			hlstats_Players
		WHERE
			hlstats_Players.playerId = $player
	");

	if ($db->num_rows() != 1) {
		error("No such player '$player'.");
	}

	$playerdata = $db->fetch_array();
	$pl_name = $playerdata['lastName'];

    if (strlen($pl_name) > 10) {
		$pl_shortname = substr($pl_name, 0, 8) . '...';
	} else {
		$pl_shortname = $pl_name;
	}

	$total = 0; 
	$pl_name = htmlspecialchars($pl_name, ENT_COMPAT);
	$pl_shortname = htmlspecialchars($pl_shortname, ENT_COMPAT);
	$game = $playerdata['game'];

	$surl = $g_options['scripturl'];


    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';


    $col = array("eventTime","eventType","eventDesc","message","serverName","map");
    if (!in_array($sort, $col)) {
        $sort      = "eventTime";
        $sortorder = "DESC";
    }

    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $start = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * 30 : 0;


$sql = "
WITH EventHistory AS (

    /* Team Bonuses */
    SELECT
        'Team Bonus' AS eventType,
        t.eventTime,
        CONCAT(
            'My team received a points bonus of ', t.bonus,
            ' for triggering \"', IFNULL(a.description,'Unknown'), '\"'
        ) AS eventDesc,
        IFNULL(s.name, 'Unknown') AS serverName,
        t.map
    FROM hlstats_Events_TeamBonuses t
    LEFT JOIN hlstats_Actions a ON t.actionId = a.id
    LEFT JOIN hlstats_Servers s ON s.serverId = t.serverId
    WHERE t.playerId = $player AND a.game = '$game'

    UNION ALL

    /* Connects */
    SELECT
        'Connect',
        c.eventTime,
        'I connected to the server',
        IFNULL(s.name, 'Unknown'),
        c.map
    FROM hlstats_Events_Connects c
    LEFT JOIN hlstats_Servers s ON s.serverId = c.serverId
    WHERE c.playerId = $player

    UNION ALL

    /* Disconnects */
    SELECT
        'Disconnect',
        d.eventTime,
        'I left the game',
        IFNULL(s.name, 'Unknown'),
        d.map
    FROM hlstats_Events_Disconnects d
    LEFT JOIN hlstats_Servers s ON s.serverId = d.serverId
    WHERE d.playerId = $player

    UNION ALL

    /* Entries */
    SELECT
        'Entry',
        e.eventTime,
        'I entered the game',
        IFNULL(s.name, 'Unknown'),
        e.map
    FROM hlstats_Events_Entries e
    LEFT JOIN hlstats_Servers s ON s.serverId = e.serverId
    WHERE e.playerId = $player

    UNION ALL

    /* Kills (non-headshot) */
    SELECT
        'Kill',
        f.eventTime,
        CONCAT(
            'I killed %A%$surl?mode=playerinfo&player=', f.victimId, '%',
            IFNULL(p.lastName,'Unknown'), '%/A% with ', f.weapon
        ),
        IFNULL(s.name, 'Unknown'),
        f.map
    FROM hlstats_Events_Frags f
    LEFT JOIN hlstats_Servers s ON s.serverId = f.serverId
    LEFT JOIN hlstats_Players p ON p.playerId = f.victimId
    WHERE f.killerId = $player AND f.headshot = 0

    UNION ALL

    /* Kills (headshot) */
    SELECT
        'Kill',
        f.eventTime,
        CONCAT(
            'I killed %A%$surl?mode=playerinfo&player=', f.victimId, '%',
            IFNULL(p.lastName,'Unknown'), '%/A% with a headshot from ', f.weapon
        ),
        IFNULL(s.name, 'Unknown'),
        f.map
    FROM hlstats_Events_Frags f
    LEFT JOIN hlstats_Servers s ON s.serverId = f.serverId
    LEFT JOIN hlstats_Players p ON p.playerId = f.victimId
    WHERE f.killerId = $player AND f.headshot = 1

    UNION ALL

    /* Deaths */
    SELECT
        'Death',
        f.eventTime,
        CONCAT(
            '%A%$surl?mode=playerinfo&player=', f.killerId, '%',
            IFNULL(p.lastName,'Unknown'), '%/A% killed me with ', f.weapon
        ),
        IFNULL(s.name, 'Unknown'),
        f.map
    FROM hlstats_Events_Frags f
    LEFT JOIN hlstats_Servers s ON s.serverId = f.serverId
    LEFT JOIN hlstats_Players p ON p.playerId = f.killerId
    WHERE f.victimId = $player

    UNION ALL

    /* Teamkills (you killed teammate) */
    SELECT
        'Team Kill',
        t.eventTime,
        CONCAT(
            'I killed teammate %A%$surl?mode=playerinfo&player=', t.victimId, '%',
            IFNULL(p.lastName,'Unknown'), '%/A% with ', t.weapon
        ),
        IFNULL(s.name, 'Unknown'),
        t.map
    FROM hlstats_Events_Teamkills t
    LEFT JOIN hlstats_Servers s ON s.serverId = t.serverId
    LEFT JOIN hlstats_Players p ON p.playerId = t.victimId
    WHERE t.killerId = $player

    UNION ALL

    /* Teamkills (teammate killed you) */
    SELECT
        'Friendly Fire',
        t.eventTime,
        CONCAT(
            'My teammate %A%$surl?mode=playerinfo&player=', t.killerId, '%',
            IFNULL(p.lastName,'Unknown'), '%/A% killed me with ', t.weapon
        ),
        IFNULL(s.name, 'Unknown'),
        t.map
    FROM hlstats_Events_Teamkills t
    LEFT JOIN hlstats_Servers s ON s.serverId = t.serverId
    LEFT JOIN hlstats_Players p ON p.playerId = t.killerId
    WHERE t.victimId = $player

    UNION ALL

    /* Change Role */
    SELECT
        'Role',
        r.eventTime,
        CONCAT('I changed role to ', r.role),
        IFNULL(s.name, 'Unknown'),
        r.map
    FROM hlstats_Events_ChangeRole r
    LEFT JOIN hlstats_Servers s ON s.serverId = r.serverId
    WHERE r.playerId = $player

    UNION ALL

    /* Change Name */
    SELECT
        'Name',
        n.eventTime,
        CONCAT('I changed my name from \"', n.oldName, '\" to \"', n.newName, '\"'),
        IFNULL(s.name, 'Unknown'),
        n.map
    FROM hlstats_Events_ChangeName n
    LEFT JOIN hlstats_Servers s ON s.serverId = n.serverId
    WHERE n.playerId = $player

    UNION ALL

    /* Player Actions */
    SELECT
        'Action',
        a.eventTime,
        CONCAT(
            'I received a points bonus of ', a.bonus,
            ' for triggering \"', IFNULL(ac.description,'Unknown'), '\"'
        ),
        IFNULL(s.name, 'Unknown'),
        a.map
    FROM hlstats_Events_PlayerActions a
    LEFT JOIN hlstats_Servers s ON s.serverId = a.serverId
    LEFT JOIN hlstats_Actions ac ON ac.id = a.actionId
    WHERE a.playerId = $player AND ac.game = '$game'

    UNION ALL

    /* PlayerPlayerActions (you acted on someone) */
    SELECT
        'Action',
        a.eventTime,
        CONCAT(
            'I received a points bonus of ', a.bonus,
            ' for triggering \"', IFNULL(ac.description,'Unknown'),
            '\" against %A%$surl?mode=playerinfo&player=', a.victimId, '%',
            IFNULL(p.lastName,'Unknown'), '%/A%'
        ),
        IFNULL(s.name, 'Unknown'),
        a.map
    FROM hlstats_Events_PlayerPlayerActions a
    LEFT JOIN hlstats_Servers s ON s.serverId = a.serverId
    LEFT JOIN hlstats_Actions ac ON ac.id = a.actionId
    LEFT JOIN hlstats_Players p ON p.playerId = a.victimId
    WHERE a.playerId = $player AND ac.game = '$game'

    UNION ALL

    /* PlayerPlayerActions (someone acted on you) */
    SELECT
        'Action',
        a.eventTime,
        CONCAT(
            '%A%$surl?mode=playerinfo&player=', a.playerId, '%',
            IFNULL(p.lastName,'Unknown'),
            '%/A% triggered \"', IFNULL(ac.description,'Unknown'), '\" against me'
        ),
        IFNULL(s.name, 'Unknown'),
        a.map
    FROM hlstats_Events_PlayerPlayerActions a
    LEFT JOIN hlstats_Servers s ON s.serverId = a.serverId
    LEFT JOIN hlstats_Actions ac ON ac.id = a.actionId
    LEFT JOIN hlstats_Players p ON p.playerId = a.playerId
    WHERE a.victimId = $player AND ac.game = '$game'

    UNION ALL

    /* Suicides */
    SELECT
        'Suicide',
        su.eventTime,
        CONCAT('I committed suicide with \"', su.weapon, '\"'),
        IFNULL(s.name, 'Unknown'),
        su.map
    FROM hlstats_Events_Suicides su
    LEFT JOIN hlstats_Servers s ON s.serverId = su.serverId
    WHERE su.playerId = $player

    UNION ALL

    /* Change Team */
    SELECT
        'Team',
        t.eventTime,
        IF(
            tm.name IS NULL,
            CONCAT('I joined team \"', t.team, '\"'),
            CONCAT('I joined team \"', t.team, '\" (', tm.name, ')')
        ),
        IFNULL(s.name, 'Unknown'),
        t.map
    FROM hlstats_Events_ChangeTeam t
    LEFT JOIN hlstats_Servers s ON s.serverId = t.serverId
    LEFT JOIN hlstats_Teams tm ON tm.code = t.team AND tm.game = '$game'
    WHERE t.playerId = $player
)
SELECT
    eventTime,
    eventType,
    eventDesc,
    serverName,
    map,
    COUNT(*) OVER() AS total_rows
FROM EventHistory
ORDER BY
    $sort $sortorder
LIMIT 30 OFFSET $start
";
$result = $db->query($sql);

if (!is_ajax()) {

	printSectionTitle($pl_name.'\'s Event History');

?>

<div id="events">
<?php
}
?>
<div  class="responsive-table">
  <table class="players-table">
    <tr>
        <th class="left<?= isSorted('eventTime',$sort,$sortorder) ?>"><?= headerUrl('eventTime', ['sort','sortorder'], 'events') ?>Date</a></th>
        <th class="left<?= isSorted('eventType',$sort,$sortorder) ?>"><?= headerUrl('eventType', ['sort','sortorder'], 'events') ?>Type</a></th>
        <th class="hlstats-main-description left<?= isSorted('eventDesc',$sort,$sortorder) ?>"><?= headerUrl('eventDesc', ['sort','sortorder'], 'events') ?>Description</a></th>
        <th class="hide<?= isSorted('serverName',$sort,$sortorder) ?>"><?= headerUrl('serverName', ['sort','sortorder'], 'events') ?>Server</a></th>
       <th class="hide-2<?= isSorted('map',$sort,$sortorder) ?>"><?= headerUrl('map', ['sort','sortorder'], 'events') ?>Map</a></th>
    </tr>
    <?php
        $i= 1 + $start;
        while ($res = $db->fetch_array($result))
        {
            $desc = preg_replace(array('/%A%([^ %]+)%/','/%\/A%/'), array("<a href=\"$1\"><span class=\"hlstats-name\">", '</span></a>'), $res['eventDesc']);
            $total = $res['total_rows'];
            echo '<tr>
                  <td class="nowrap left">'.str_replace(" ","<br>@",$res['eventTime']).'</td>
                  <td class="nowrap">'.$res['eventType'].'</td>
                  <td class="hlstats-main-description left">'.$desc.'</td>
                  <td class="hide">'.htmlspecialchars($res['serverName']).'</td>
                  <td class="nowrap hide-2">'.$res['map'].'</td>
                  </tr>'; $i++;
        }
   ?>
   </table></div>
   <?php
       echo Pagination($total, $_GET['page'] ?? 1, 30, 'page');
  if (is_ajax()) exit;
  ?>
</div>
<script>
Fetch.ini('events');
</script>
<div>
    <a href="<?php echo $g_options['scripturl'] . "?mode=playerinfo&amp;player=$player"; ?>">&larr;&nbsp;<?php echo $pl_name; ?>'s Statistics</a>
</div>
<?php if ($g_options['DeleteDays']) { ?>
<div class="hlstats-note">
    Items above are generated from the most recent <?= $g_options['DeleteDays'] ?> days of activity.
</div>
<?php } ?>