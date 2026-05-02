<?php
if (!defined('IN_UPDATER')) {
    die('Do not access this file directly.');
}

echo "Converting all tables to InnoDB + utf8mb4<br /><br />";

$res = $db->query("
    SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = '" . $db->escape($db->db_name) . "'
");

if (!$res) {
    $db->error("Could not fetch table list.");
}

$tables = $db->fetch_row_set($res);

foreach ($tables as $row) {

    $table  = $row['TABLE_NAME'];
    $engine = strtoupper($row['ENGINE']);
    $coll   = $row['TABLE_COLLATION'];

    ob_flush();
    flush();

    echo "<b>Table:</b> $table<br />";

    // ---------------------------------------------------------
    // ENGINE CONVERSION
    // ---------------------------------------------------------
    if (strtoupper($table) === 'HLSTATS_LIVESTATS') {
        if ($engine !== 'MEMORY') {

            $ok = @$db->query("ALTER TABLE `$table` ENGINE=MEMORY", false);

            echo $ok
                ? "→ Engine converted to MEMORY<br />"
                : "→ Engine conversion FAILED (continuing)<br />";

        } else {
            echo "→ Engine already MEMORY<br />";
        }
    } else {
        if ($engine !== 'INNODB') {

            $ok = @$db->query("ALTER TABLE `$table` ENGINE=InnoDB", false);

            echo $ok
                ? "→ Engine converted to InnoDB<br />"
                : "→ Engine conversion FAILED (continuing)<br />";

        } else {
            echo "→ Engine already InnoDB<br />";
        }
    }
    // ---------------------------------------------------------
    // COLLATION / CHARSET CONVERSION
    // Only convert if NOT utf8mb4_general_ci
    // ---------------------------------------------------------
    if ($coll !== 'utf8mb4_general_ci') {

        echo "→ Attempting utf8mb4_general_ci…<br />";

        try {
            $ok = @$db->query(
                "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci",
                false
            );

            if ($ok) {
                echo "→ Converted to utf8mb4_general_ci<br /><br />";
                continue;
            }
            // -----------------------------------------------------
            // FALLBACK: utf8mb4_0900_bin
            // -----------------------------------------------------
            echo "→ utf8mb4_general_ci FAILED, applying fallback utf8mb4_0900_bin…<br />";
            
            $ok = @$db->query(
                "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin",
                false
            );
            
            echo $ok
                ? "→ Converted to utf8mb4_0900_bin<br /><br />"
                : "→ Fallback conversion FAILED (continuing)<br /><br />";
                
        } catch (Exception $e) {
            // -----------------------------------------------------
            // FALLBACK: utf8mb4_0900_bin
            // -----------------------------------------------------
            echo "→ utf8mb4_general_ci FAILED, applying fallback utf8mb4_0900_bin…<br />";
            
            $ok = @$db->query(
                "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin",
                false
            );
            
            echo $ok
                ? "→ Converted to utf8mb4_0900_bin<br /><br />"
                : "→ Fallback conversion FAILED (continuing)<br /><br />";
        }
    } else {
        echo "→ Collation already utf8mb4_general_ci<br /><br />";
    }
}

$dbversion = 84;

echo "Updating database schema numbers.<br />";
$db->query("UPDATE hlstats_Options SET `value` = '$dbversion' WHERE `keyname` = 'dbversion'");
?>
