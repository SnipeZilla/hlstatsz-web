<?php
if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

// ---------------------------------------------
// Player rankings: indexes for the true rank
// ---------------------------------------------
// includes/players_rank.php reads a page of the ranking and counts the players ranked above it on these indexes,
// instead of ranking every player of the game: one for the skill ranking, one for the kills ranking.
echo "<h3>Player rankings: ranking indexes</h3>";

$indexes = array(
    'ranking'       => '(game, hideranking, skill, kills, connection_time, playerId, lastAddress)',
    'ranking_kills' => '(game, hideranking, kills, deaths DESC, connection_time, playerId, lastAddress)',
);
foreach ($indexes as $name => $columns) {
    $db->query("SHOW INDEX FROM hlstats_Players WHERE Key_name = '$name'");
    if ($db->num_rows()) {
        echo "&rarr; <b>$name</b> already exists: skipped<br />";
        continue;
    }
    $db->query("ALTER TABLE hlstats_Players ADD INDEX `$name` $columns");
    echo "&rarr; <b>$name</b> added on hlstats_Players<br />";
}
ob_flush();
flush();

// ---------------------------------------------
// Events: the player's indexes hold what the clan and player pages read
// ---------------------------------------------
// The clan tabs (weapons, maps, roles, actions, teams) and the favorites read every event of every member: through an
// index of the player alone, each event's row is read too, scattered over the whole table. The same indexes, widened
// with the columns those pages read, are read alone. Widened in place (same name, the player first), so nothing that
// used them is lost. A big events table takes a while: run the updater again if the page stops, it goes on from there.
echo "<h3>Events: player indexes for the clan and player pages</h3>";

$events = array(
    'hlstats_Events_Frags'               => array('killerId' => '(killerId, weapon, map, killerRole, headshot)',
                                                  'victimId' => '(victimId, map, victimRole)'),
    'hlstats_Events_Entries'             => array('playerId' => '(playerId, serverId, map)'),
    'hlstats_Events_PlayerActions'       => array('playerId' => '(playerId, actionId, bonus)'),
    'hlstats_Events_PlayerPlayerActions' => array('playerId' => '(playerId, actionId, bonus)',
                                                  'victimId' => '(victimId, actionId, bonus)'),
    'hlstats_Events_ChangeRole'          => array('playerId' => '(playerId, role, serverId)'),
    'hlstats_Events_ChangeTeam'          => array('playerId' => '(playerId, team)'),
    'hlstats_Events_Statsme'             => array('playerId' => '(playerId, weapon, kills, hits, shots, headshots, deaths, damage)'),
    'hlstats_Events_Statsme2'            => array('playerId' => '(playerId, weapon, head, chest, stomach, leftarm, rightarm, leftleg, rightleg)'),
);
@set_time_limit(0);
foreach ($events as $table => $list) {
    // Tables of an old row format (COMPACT, REDUNDANT) take index keys of 767 bytes at most: kept as they are
    $db->query("SELECT ROW_FORMAT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table'");
    list($format) = $db->fetch_row() ?: array('');
    if (in_array(strtolower((string) $format), array('compact', 'redundant'), true)) {
        echo "&rarr; <b>$table</b> uses the $format row format: skipped (ALTER TABLE $table ROW_FORMAT=DYNAMIC, then run the updater again)<br />";
        continue;
    }
    $alter = array();
    $names = array();
    foreach ($list as $name => $columns) {
        // widened already: it has a second column
        $db->query("SHOW INDEX FROM $table WHERE Key_name = '$name' AND Seq_in_index = 2");
        if ($db->num_rows()) {
            echo "&rarr; <b>$table.$name</b> already widened: skipped<br />";
            continue;
        }
        $db->query("SHOW INDEX FROM $table WHERE Key_name = '$name'");
        if ($db->num_rows()) {
            $alter[] = "DROP INDEX `$name`";
        }
        $alter[] = "ADD INDEX `$name` $columns";
        $names[] = $name;
    }
    if ($alter) {
        $db->query("ALTER TABLE $table " . implode(', ', $alter));
        echo "&rarr; <b>$table</b>: " . implode(', ', $names) . " widened<br />";
    }
    ob_flush();
    flush();
}

echo "<h3>Indexes covered by another index</h3>";

// table => array(index to drop => the index that covers it)
$covered = array(
    'hlstats_Players'               => array('idx_players_skill_kills' => 'idx_players_skill_kills_conn'),
    'hlstats_Events_ChangeName'     => array('playerId' => 'playerId_eventTime'),
    'hlstats_Events_Chat'           => array('playerId' => 'playerId_eventTime'),
    'hlstats_Events_Connects'       => array('playerId' => 'playerId_eventTime'),
    'hlstats_Events_Latency'        => array('playerId' => 'playerId_eventTime'),
    'hlstats_Events_StatsmeLatency' => array('playerId' => 'playerId_eventTime'),
    'hlstats_Events_StatsmeTime'    => array('playerId' => 'playerId_eventTime'),
    'hlstats_Events_Suicides'       => array('playerId' => 'playerId_eventTime'),
    'hlstats_Events_TeamBonuses'    => array('playerId' => 'playerId_eventTime'),
    'hlstats_Events_Teamkills'      => array('killerId' => 'killer_eventTime'),
);

// The columns of an index, in order, each with its prefix length and direction: array(unique, list), or null when
// the table has no such index
$indexParts = function ($table, $name) use ($db) {
    $result = $db->query("SHOW INDEX FROM `$table` WHERE Key_name = '$name'", false);
    if (!$result) {
        return null;
    }
    $parts  = array();
    $unique = false;
    while ($row = $db->fetch_array($result)) {
        $parts[(int) $row['Seq_in_index']] = $row['Column_name'] . '/' . $row['Sub_part'] . '/' . $row['Collation'];
        $unique = $unique || !$row['Non_unique'] || strtoupper((string) $row['Index_type']) !== 'BTREE';
    }
    ksort($parts);
    return $parts ? array($unique, array_values($parts)) : null;
};

@set_time_limit(0);
foreach ($covered as $table => $list) {
    foreach ($list as $name => $by) {
        $index = $indexParts($table, $name);
        if (!$index) {
            echo "&rarr; <b>$table.$name</b> is not there: skipped<br />";
            continue;
        }
        $cover = $indexParts($table, $by);
        if ($index[0] || !$cover || array_slice($cover[1], 0, count($index[1])) !== $index[1]) {
            echo "&rarr; <b>$table.$name</b> is not covered by $by here: kept<br />";
            continue;
        }
        $db->query("ALTER TABLE `$table` DROP INDEX `$name`");
        echo "&rarr; <b>$table.$name</b> dropped: $by covers it<br />";
    }
    ob_flush();
    flush();
}

// ---------------------------------------------
// Top menu: two links of the site's own, such as Donate (HLstats Settings > Top Menu Links)
// ---------------------------------------------
echo "<h3>Top menu links</h3>";

foreach (array('topnav_link1_label', 'topnav_link1_url', 'topnav_link2_label', 'topnav_link2_url') as $key) {
    $db->query("INSERT IGNORE INTO hlstats_Options (keyname, value, opttype) VALUES ('$key', '', 2)");
    echo "&rarr; <b>$key</b> added (skipped if already exists)<br />";
}

$dbversion = 93;
echo "Updating database schema version.<br />";
$db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

?>
