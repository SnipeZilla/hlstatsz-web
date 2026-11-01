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

$dbversion = 93;
echo "Updating database schema version.<br />";
$db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");

?>
