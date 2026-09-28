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

/*
 * Repair of text saved in UTF-8 twice, or three times: "JosÃ©" for "José", "â™›" for "♛".
 * UTF-8 bytes read as Latin-1 / Windows-1252 and written again as UTF-8 give it: a latin1 table that held
 * UTF-8 converted with CONVERT TO CHARACTER SET, or a dump imported with the wrong character set.
 * Each such character stands for one byte of the original text, so the text comes back whole.
 * It repairs HLstatsZ's databases, or another database on any MySQL server (a forum's, for example).
 */
if ( !defined('IN_HLSTATS') ) { die('Do not access this file directly'); }

if ($auth->userdata['acclevel'] < 100) {
    die ('Access denied!');
}

// Windows-1252 characters of the bytes 0x80-0x9F (every other byte is its own code point, as in Latin-1)
const FIXENC_CP1252 = array(
    0x20AC => 0x80, 0x201A => 0x82, 0x0192 => 0x83, 0x201E => 0x84, 0x2026 => 0x85, 0x2020 => 0x86, 0x2021 => 0x87,
    0x02C6 => 0x88, 0x2030 => 0x89, 0x0160 => 0x8A, 0x2039 => 0x8B, 0x0152 => 0x8C, 0x017D => 0x8E, 0x2018 => 0x91,
    0x2019 => 0x92, 0x201C => 0x93, 0x201D => 0x94, 0x2022 => 0x95, 0x2013 => 0x96, 0x2014 => 0x97, 0x02DC => 0x98,
    0x2122 => 0x99, 0x0161 => 0x9A, 0x203A => 0x9B, 0x0153 => 0x9C, 0x017E => 0x9E, 0x0178 => 0x9F,
);

/**
 * The text with each layer of "UTF-8 read as Windows-1252" undone, or the text itself when it is not such text.
 */
function fixencText(string $text): string
{
    $fixed = $text;
    for ($layer = 0; $layer < 4; $layer++) {
        $bytes = '';
        // In pieces of 64 KB: an array item per character costs dozens of bytes, too much for a long post
        for ($pos = 0, $end = strlen($fixed); $pos < $end; $pos += strlen($piece)) {
            $piece = mb_strcut($fixed, $pos, 65536, 'UTF-8');
            if ($piece === '') {
                break 2;
            }
            foreach (mb_str_split($piece, 1, 'UTF-8') as $char) {
                $cp = mb_ord($char, 'UTF-8');
                if ($cp < 0x100) {
                    $bytes .= chr($cp);
                } elseif (isset(FIXENC_CP1252[$cp])) {
                    $bytes .= chr(FIXENC_CP1252[$cp]);
                } else {
                    break 3;   // a character no single byte gives: no (further) layer to undo
                }
            }
        }
        if ($bytes === $fixed) {
            break;         // plain ASCII
        }
        if (!mb_check_encoding($bytes, 'UTF-8')) {
            // A column cut the text in the middle of a character: without its incomplete end, the rest must be
            // valid UTF-8 and hold multi-byte characters (Latin-1 text such as "Café" does not: it stays)
            $cut = preg_replace('/[\xC0-\xFF][\x80-\xBF]{0,2}$/', '', $bytes);
            if ($cut === $bytes || !mb_check_encoding($cut, 'UTF-8') || !preg_match('/[\xC2-\xF4][\x80-\xBF]/', $cut)) {
                break;
            }
            $bytes = $cut;
        }
        $fixed = $bytes;
    }
    // Text never holds C1 control characters (U+0080-U+009F): a result with some was not double-encoded
    return $fixed !== $text && !preg_match('/[\x{80}-\x{9F}]/u', $fixed) ? $fixed : $text;
}

/**
 * PHP-serialized data, such as 'a:1:{i:0;s:6:"JosÃ©";}': its lengths count bytes, which a repair would change.
 */
function fixencSerialized(string $text): bool
{
    return preg_match('/^(?:[aOC]:\d+:[{"]|s:\d+:")/', $text) && @unserialize($text, array('allowed_classes' => false)) !== false;
}

function fixencId(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

/**
 * A connection to another database. Its errors are thrown, for the page to show, instead of ending the page.
 */
class FixencDb extends DB_mysql
{
    function error($message, $exit = true)
    {
        $reason = '';
        try {
            $reason = $this->link ? mysqli_error($this->link) : mysqli_connect_error();
        } catch (Throwable $e) {
            // closed after a failed database selection
        }
        throw new RuntimeException($reason ?: preg_replace('/ Check that .*/s', '', strip_tags($message)));
    }
}

/**
 * The columns of a database that can hold repaired text: [table => ['pk' => [columns], 'columns' => [column => bytes per character]]].
 * UTF-8 columns only: the bytes of text in another character set (latin1, …) never were UTF-8.
 */
function fixencTables($db, string $dbName): array
{
    $schema = $db->escape($dbName);
    $tables = array();
    $db->query("
        SELECT c.TABLE_NAME, c.COLUMN_NAME, IF(c.CHARACTER_SET_NAME = 'utf8mb4', 4, 3)
        FROM information_schema.COLUMNS AS c
        JOIN information_schema.TABLES AS t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
        WHERE c.TABLE_SCHEMA = '$schema'
          AND t.TABLE_TYPE IN ('BASE TABLE', 'SYSTEM VERSIONED')
          AND c.DATA_TYPE IN ('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext')
          AND c.CHARACTER_SET_NAME IN ('utf8', 'utf8mb3', 'utf8mb4')
        ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION
    ");
    while (list($table, $column, $bytes) = $db->fetch_row()) {
        $tables[$table] ??= array('pk' => array(), 'columns' => array());
        $tables[$table]['columns'][$column] = (int) $bytes;
    }
    $db->query("
        SELECT TABLE_NAME, COLUMN_NAME
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = '$schema' AND INDEX_NAME = 'PRIMARY'
        ORDER BY TABLE_NAME, SEQ_IN_INDEX
    ");
    while (list($table, $column) = $db->fetch_row()) {
        if (isset($tables[$table])) {
            $tables[$table]['pk'][] = $column;
        }
    }
    return $tables;
}

/**
 * The most bytes of values a query sends: half the server's max_allowed_packet, 8 MB at most.
 */
function fixencBudget($db): int
{
    $db->query("SELECT @@max_allowed_packet");
    list($packet) = $db->fetch_row();
    return (int) min(8 << 20, max(1 << 16, $packet / 2));
}

/**
 * Calls $each(keys, old, new) for every value of a column that a repair changes, reading the table in batches along
 * its primary key (a forum's posts do not fit in memory). $new is null for a value to leave as it is: PHP-serialized
 * data, a character (emoji, …) a utf8mb3 column cannot store, where UPDATE IGNORE would cut the text, or a value
 * too long for a query ($budget; escaped, it takes up to twice its length).
 */
function fixencEach($db, string $dbName, string $table, array $pk, string $column, int $bytes, int $budget, callable $each): void
{
    $keys  = implode(', ', array_map('fixencId', $pk));
    $from  = fixencId($dbName) . '.' . fixencId($table);
    // Double-encoded text always holds the byte C3 (a UTF-8 lead byte read as one of U+00C0-U+00FF)
    $where = 'INSTR(CAST(' . fixencId($column) . " AS BINARY), UNHEX('C3')) > 0";
    $after = '';
    do {
        $result = $db->query('SELECT ' . ($pk ? "$keys, " : '') . fixencId($column) . " FROM $from WHERE $where$after" . ($pk ? " ORDER BY $keys LIMIT 1000" : ''));
        $rows   = 0;
        while ($row = $db->fetch_row($result)) {
            $rows++;
            $old = (string) array_pop($row);
            $new = fixencText($old);
            if ($new !== $old) {
                if (fixencSerialized($old) || ($bytes < 4 && preg_match('/[\x{10000}-\x{10FFFF}]/u', $new))
                    || 2 * (strlen($old) + strlen($new)) + 64 > $budget) {
                    $new = null;
                }
                $each($row, $old, $new);
            }
            $last = $row;
        }
        $db->free_result($result);

        if ($pk && $rows) {
            // The rows after the last one read: (k1 > v1) OR (k1 = v1 AND k2 > v2) OR …
            $after = array();
            foreach ($pk as $i => $key) {
                $terms = array();
                foreach (array_slice($pk, 0, $i) as $j => $prev) {
                    $terms[] = fixencId($prev) . " = '" . $db->escape((string) $last[$j]) . "'";
                }
                $terms[] = fixencId($key) . " > '" . $db->escape((string) $last[$i]) . "'";
                $after[] = '(' . implode(' AND ', $terms) . ')';
            }
            $after = ' AND (' . implode(' OR ', $after) . ')';
        }
    } while ($pk && $rows === 1000);
}

/**
 * Repairs a database; returns [table => [column => [repaired, merged, left, error]]].
 * Two rows a repair would make equal are merged: an alias of a player (its counts added to the other one),
 * a clan tag in a game (its members moved to the other clan). Other clashes on a unique key are left as they are.
 */
function fixencApply($db, string $dbName, array $tables): array
{
    $q      = fn($s) => "'" . $db->escape((string) $s) . "'";
    $same   = fn($column, $value) => "CAST($column AS BINARY) = CAST(" . $q($value) . " AS BINARY)";
    $from   = fixencId($dbName) . '.';
    $report = array();

    // HLstatsZ's players table, for the clan merges
    $players = '';
    foreach (array_keys($tables) as $table) {
        if (strtolower($table) === 'hlstats_players') {
            $players = $table;
        }
    }

    // Old value -> repaired value, for one UPDATE per column (a table without a key would need one pass per value).
    // A forum post can be long: the map takes any length, and is filled in INSERTs the server accepts.
    $db->query("CREATE TEMPORARY TABLE IF NOT EXISTS fixenc_map (old LONGBLOB NOT NULL, new LONGTEXT NOT NULL, KEY old (old(191))) DEFAULT CHARSET=utf8mb4");
    $budget = fixencBudget($db);

    foreach ($tables as $table => $info) {
        $name = strtolower($table);
        $into = $from . fixencId($table);
        foreach ($info['columns'] as $column => $bytes) {
            $found  = $merged = $mapped = $size = 0;
            $values = array();
            $send   = function () use ($db, &$values, &$size) {
                if ($values) {
                    $db->query("INSERT INTO fixenc_map (old, new) VALUES " . implode(', ', $values));
                }
                $values = array();
                $size   = 0;
            };
            $db->query("DELETE FROM fixenc_map");

            fixencEach($db, $dbName, $table, $info['pk'], $column, $bytes, $budget, function ($keys, $old, $new)
                use ($db, $name, $column, $into, $players, $from, $q, $same, $budget, $send, &$found, &$merged, &$mapped, &$values, &$size) {
                $found++;
                if ($new === null) {
                    return;
                }
                if ($name === 'hlstats_playernames' && $column === 'name') {
                    // The player may already have the repaired alias: add this one's counts to it
                    $player = (int) $keys[0];
                    $db->query("
                        UPDATE $into AS t
                        JOIN $into AS o ON o.playerId = t.playerId AND " . $same('o.name', $old) . "
                        SET t.connection_time = t.connection_time + o.connection_time, t.numuses = t.numuses + o.numuses,
                            t.kills = t.kills + o.kills, t.deaths = t.deaths + o.deaths, t.suicides = t.suicides + o.suicides,
                            t.headshots = t.headshots + o.headshots, t.shots = t.shots + o.shots, t.hits = t.hits + o.hits,
                            t.lastuse = GREATEST(t.lastuse, o.lastuse)
                        WHERE t.playerId = $player AND t.name = " . $q($new) . " AND NOT " . $same('t.name', $old) . "
                    ", false);
                    if (mysqli_affected_rows($db->link) > 0) {
                        $db->query("DELETE FROM $into WHERE playerId = $player AND " . $same('name', $old), false);
                        $merged++;
                        return;
                    }
                } elseif ($name === 'hlstats_clans' && $column === 'tag' && $players !== '') {
                    // A clan with the repaired tag may exist in the game (made since): its members get the members of this one
                    $clan = (int) $keys[0];
                    $db->query("
                        SELECT o.clanId FROM $into AS c
                        JOIN $into AS o ON o.game = c.game AND o.tag = " . $q($new) . " AND o.clanId <> c.clanId
                        WHERE c.clanId = $clan LIMIT 1
                    ");
                    if (list($target) = $db->fetch_row()) {
                        $db->query("UPDATE " . $from . fixencId($players) . " SET clan = " . (int) $target . " WHERE clan = $clan", false);
                        $db->query("DELETE FROM $into WHERE clanId = $clan", false);
                        $merged++;
                        return;
                    }
                }

                $value = '(CAST(' . $q($old) . ' AS BINARY), ' . $q($new) . ')';
                if ($size + strlen($value) > $budget) {
                    $send();
                }
                $values[] = $value;
                $size    += strlen($value) + 2;
                $mapped++;
            });
            $send();

            // Every row still holding an old value gets its repaired one, in one pass over the table
            // (IGNORE: a repaired value another row of a unique key already holds stays as it was)
            $done  = 0;
            $error = '';
            if ($mapped) {
                if ($db->query("UPDATE IGNORE $into AS t JOIN fixenc_map AS m ON CAST(t." . fixencId($column) . " AS BINARY) = m.old SET t." . fixencId($column) . " = m.new", false)) {
                    $done = max(0, mysqli_affected_rows($db->link));
                } else {
                    $error = mysqli_error($db->link);
                }
            }
            if ($found) {
                $report[$table][$column] = array($done, $merged, max(0, $found - $merged - $done), $error);
            }
        }
    }
    return $report;
}

set_time_limit(0);   // a forum's database takes a while to scan

// HLstatsZ's databases, or another one given in the form below. Its server and credentials stay in the session
// between the preview and the repair only: this page clears them on every visit.
$pending = $_SESSION['fixenc'] ?? null;
unset($_SESSION['fixenc']);
$apply = isset($_POST['fixenc_apply'], $_POST['fixenc_token']) && is_array($pending) && hash_equals($pending['token'], (string) $_POST['fixenc_token']);
$other = $apply ? $pending['other'] : null;
if (!$apply && isset($_POST['fixenc_other'])) {
    $other = array(
        'host' => trim((string) ($_POST['other_host'] ?? '')),
        'name' => trim((string) ($_POST['other_name'] ?? '')),
        'user' => trim((string) ($_POST['other_user'] ?? '')),
        'pass' => (string) ($_POST['other_pass'] ?? ''),
    );
}
?>
<div class="panel">
<?php
if (!$apply) {
    message('warning', 'This repairs text saved in UTF-8 twice, such as <b>JosÃ©</b> for <b>José</b> or <b>â™›</b> for <b>♛</b>. Back up your database before you repair it.');
}

// [connection, database, heading]: HLstatsZ's, and SourceBans' when it is set up on the same server, or the other one
$targets = array();
if ($other === null) {
    $targets[] = array($db, $db->db_name, $db->db_name);
    if (defined('DB_SBNAME') && DB_SBNAME !== '' && DB_SBNAME !== $db->db_name) {
        $targets[] = array($db, DB_SBNAME, DB_SBNAME);
    }
} elseif ($other['name'] === '') {
    message('warning', 'Enter the name of the database to repair.');
} else {
    $host = $other['host'] !== '' ? $other['host'] : DB_ADDR;
    try {
        // No user given: HLstatsZ's credentials
        $link = $other['user'] !== ''
            ? new FixencDb($host, $other['user'], $other['pass'], $other['name'])
            : new FixencDb($host, DB_USER, DB_PASS, $other['name']);
        $targets[] = array($link, $other['name'], $other['name'] . ' on ' . $host);
    } catch (Throwable $e) {
        message('warning', 'Could not open <b>' . htmlspecialchars($other['name']) . '</b> on ' . htmlspecialchars($host) . ': ' . htmlspecialchars($e->getMessage()));
    }
}

$total = 0;
foreach ($targets as list($link, $dbName, $heading)) {
    echo '<h3>' . htmlspecialchars($heading) . '</h3>';
    try {
        $tables = fixencTables($link, $dbName);
        $budget = fixencBudget($link);

        if ($apply) {
            $report = fixencApply($link, $dbName, $tables);
            if (!$report) {
                echo '<p class="hlstats-admin-note">No double-encoded text.</p>';
                continue;
            }
            $errors = array();
            echo '<div class="hlstats-admin-table-wrap hlstats-scrollbar"><table><tr><th class="left">Table</th><th class="left">Column</th><th>Repaired</th><th>Merged</th><th>Left as is</th></tr>';
            foreach ($report as $table => $columns) {
                foreach ($columns as $column => list($done, $merged, $left, $error)) {
                    echo '<tr><td class="left">' . htmlspecialchars($table) . '</td><td class="left">' . htmlspecialchars($column) . '</td><td>' . nf($done) . '</td><td>' . nf($merged) . '</td><td>' . nf($left) . '</td></tr>';
                    if ($error !== '') {
                        $errors[] = htmlspecialchars("$table.$column: $error");
                    }
                }
            }
            echo '</table></div>';
            if ($errors) {
                message('warning', 'Some columns could not be repaired:<br />' . implode('<br />', $errors));
            }
            continue;
        }

        // [table => [column => [values to repair, values left as they are, examples]]]
        $preview = array();
        $count   = $left = 0;
        foreach ($tables as $table => $info) {
            foreach ($info['columns'] as $column => $bytes) {
                $stats = array(0, 0, array());
                fixencEach($link, $dbName, $table, $info['pk'], $column, $bytes, $budget, function ($keys, $old, $new) use (&$stats) {
                    if ($new === null) {
                        $stats[1]++;
                        return;
                    }
                    $stats[0]++;
                    if (count($stats[2]) < 3) {
                        $stats[2][] = htmlspecialchars(mb_strimwidth($old, 0, 48, '…')) . ' &rarr; <b>' . htmlspecialchars(mb_strimwidth($new, 0, 40, '…')) . '</b>';
                    }
                });
                if ($stats[0] || $stats[1]) {
                    $preview[$table][$column] = $stats;
                    $count += $stats[0];
                    $left  += $stats[1];
                }
            }
        }
        $total += $count;

        if (!$preview) {
            echo '<p class="hlstats-admin-note">No double-encoded text.</p>';
            continue;
        }
        echo '<div class="hlstats-admin-table-wrap hlstats-scrollbar"><table><tr><th class="left">Table</th><th class="left">Column</th><th>Values</th><th class="left">Examples (now &rarr; repaired)</th></tr>';
        foreach ($preview as $table => $columns) {
            foreach ($columns as $column => list($repair, $keep, $examples)) {
                echo '<tr><td class="left">' . htmlspecialchars($table) . '</td><td class="left">' . htmlspecialchars($column) . '</td>'
                    . '<td>' . nf($repair) . ($keep ? '<br /><small>+ ' . nf($keep) . ' left as is</small>' : '') . '</td>'
                    . '<td class="left">' . implode('<br />', $examples) . '</td></tr>';
            }
        }
        echo '</table></div>';
        if ($left) {
            echo '<p class="hlstats-admin-note"><b>Left as is:</b> ' . nf($left) . ' values in PHP-serialized data, whose lengths count bytes, with characters such as emoji that their utf8mb3 column cannot store, or longer than the server takes in one query (max_allowed_packet).</p>';
        }
    } catch (RuntimeException $e) {
        message('warning', htmlspecialchars($e->getMessage()));
    } finally {
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
}

if ($apply) {
    message('success', 'Repair done.' . ($other !== null ? ' If an application such as a forum uses this database, rebuild its caches so it shows the repaired text (XenForo: Tools &rsaquo; Rebuild caches, and the search index).' : ''));
    echo "&larr;&nbsp;<a href=\"?mode=admin\">Return to Admin</a>";
} else {
    if ($total) {
        // The other database's credentials wait here for the repair
        $_SESSION['fixenc'] = array('token' => bin2hex(random_bytes(16)), 'other' => $other);
        $question = 'Repair ' . nf($total) . ' values' . ($other !== null ? ' in ' . $other['name'] : '') . '? Back up your database first.';
?>
<form method="POST" onsubmit="return confirm(<?= htmlspecialchars(json_encode($question)) ?>);">
    <?php /* The flag is a hidden field: the admin panel posts new FormData(form), which leaves out the submit button,
             and a control named "confirm" would hide window.confirm() from the onsubmit handler */ ?>
    <input type="hidden" name="fixenc_apply" value="1" />
    <input type="hidden" name="fixenc_token" value="<?= $_SESSION['fixenc']['token'] ?>" />
    <div class="hlstats-admin-apply">
        <input type="submit" value="Repair <?= nf($total) ?> values" />
    </div>
</form>
<?php
    }
?>
<form method="POST">
<input type="hidden" name="fixenc_other" value="1" />
<div class="hlstats-admin-propgroup">
<b>Another database</b>
<p class="hlstats-admin-note">A forum's database, for example, on this server or another one. Leave the server empty for <?= htmlspecialchars(DB_ADDR) ?>, and the user empty to use the credentials of HLstatsZ.</p>
<div class="responsive-table">
<table class="responsive-task">
<tbody>
<tr>
    <td class="left">Server (host:port):</td>
    <td class="left"><input type="text" name="other_host" size="35" class="textbox" placeholder="<?= htmlspecialchars(DB_ADDR) ?>" value="<?= htmlspecialchars($other['host'] ?? '') ?>" /></td>
</tr>
<tr>
    <td class="left">Database:</td>
    <td class="left"><input type="text" name="other_name" size="35" class="textbox" value="<?= htmlspecialchars($other['name'] ?? '') ?>" /></td>
</tr>
<tr>
    <td class="left">User:</td>
    <td class="left"><input type="text" name="other_user" size="35" class="textbox" autocomplete="off" value="<?= htmlspecialchars($other['user'] ?? '') ?>" /></td>
</tr>
<tr>
    <td class="left">Password:</td>
    <td class="left"><input type="password" name="other_pass" size="35" class="textbox" autocomplete="new-password" /></td>
</tr>
</tbody>
</table>
</div>
</div>
<div class="hlstats-admin-apply">
    <input type="submit" value="Scan" />
</div>
</form>
<?php
}
?>
</div>
