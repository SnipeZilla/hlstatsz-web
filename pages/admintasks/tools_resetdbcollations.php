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

    if ($auth->userdata['acclevel'] < 80) {
        die ('Access denied!');
    }
?>
<div class="panel">
<?php

	if (isset($_POST['confirm'])){

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
		message('success','Reset DB Collations done!');
        echo "&larr;&nbsp;<a href=\"?mode=admin\">Return to Admin</a>";
    } else {
        
?>        

<form method="POST">
<?php
message('warning','You should not lose any data, but be sure to back up your database before running to be on the safe side');
?>
<div class="hlstats-admin-note">
<p>
HLSTATSZ is optimized to run on engine 'InnoDB' with charset 'utf8mb4' and collation 'utf8mb4_general_ci'<br>
<?php
if (DB_CHARSET == 'utf8mb4' && DB_COLLATE == 'utf8mb4_general_ci') {
  echo 'your config is up to date:
        <br> &rarr; DB_CHARSET = "utf8mb4"
        <br> &rarr; DB_COLLATE = "utf8mb4_general_ci"';
} else {
  echo 'After running the command, your config needs to be changed to:
        <br> &rarr; DB_CHARSET = "utf8mb4"
        <br> &rarr; DB_COLLATE = "utf8mb4_general_ci"';
}
?>
</p>
<p>
Resets DB Collations if you get collation errors after an upgrade from another HLstats(X)-based system. <br>
</p>
</div>
<input type="hidden" name="confirm" value="1">

<div class="hlstats-admin-apply">
  <input type="submit" value="Reset DB" class="submit">
</div>
</form>

<?php
    }
?>
</div>
