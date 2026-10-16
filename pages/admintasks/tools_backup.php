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
 * Backup of a database as an SQL file to download: HLstatsZ's databases, or another database on any MySQL server
 * (a forum's, for example). Nothing is written on the web server: the file goes to the browser as it is made, so a
 * database of any size fits, and gzip makes it about ten times smaller.
 *
 * The file holds the tables (structure and rows) and the views, without CREATE DATABASE or USE: it restores into
 * any database, an empty one or a copy of the site's (mysql -u root -p mydb < file.sql, HeidiSQL, phpMyAdmin…).
 * InnoDB tables are read in one transaction, so they come out as they were at the start of the download, while
 * the daemon keeps writing.
 *
 * The panel (fetch) shows the databases and their download forms; a download is a plain POST of one of them
 * (admin.php sends it here before any page is written): the panel's fetch would read the file as its HTML.
 */
if ( !defined('IN_HLSTATS') ) { die('Do not access this file directly'); }

if ($auth->userdata['acclevel'] < 100) {
    die ('Access denied!');
}

function backupId(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function backupBytes($bytes): string
{
    $bytes = (float) $bytes;
    if ($bytes < 1024)       return nf($bytes) . ' B';
    if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}

/**
 * A connection to a database of the backup. Its errors are thrown, for the page to show, instead of ending the page.
 */
class BackupDb extends DB_mysql
{
    function error($message, $exit = true)
    {
        $reason = '';
        try {
            $reason = $this->link ? mysqli_error($this->link) : mysqli_connect_error();
        } catch (Throwable $e) {
            // closed after a failed database selection
        }
        throw new RuntimeException($reason ?: (string) $this->last_error[1] ?: preg_replace('/ Check that .*/s', '', strip_tags($message)));
    }
}

/**
 * A target: ['name' => database, 'cfg' => true] for a database of config.php's server and credentials, or
 * ['name', 'host', 'user', 'pass'] for another one.
 */
function backupOpen(array $target): BackupDb
{
    if (!empty($target['cfg'])) {
        return new BackupDb(DB_ADDR, DB_USER, DB_PASS, $target['name']);
    }
    $host = $target['host'] !== '' ? $target['host'] : DB_ADDR;
    // No user given: HLstatsZ's credentials
    return $target['user'] !== ''
        ? new BackupDb($host, $target['user'], $target['pass'], $target['name'])
        : new BackupDb($host, DB_USER, DB_PASS, $target['name']);
}

/**
 * What a database holds, from information_schema (row counts and sizes of InnoDB are estimates), or null when the
 * server has no such database.
 */
function backupSummary($conn, string $dbName): ?array
{
    $schema = $conn->escape($dbName);
    $conn->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$schema'");
    list($found) = $conn->fetch_row();
    if (!$found) {
        return null;
    }
    $sum = array('tables' => 0, 'views' => 0, 'rows' => 0, 'bytes' => 0, 'other_engines' => 0, 'left_out' => 0);
    $conn->query("
        SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_ROWS, DATA_LENGTH + INDEX_LENGTH
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = '$schema'
    ");
    while (list($table, $type, $engine, $rows, $bytes) = $conn->fetch_row()) {
        if ($type === 'VIEW') {
            $sum['views']++;
            continue;
        }
        $sum['tables']++;
        $sum['rows']  += (int) $rows;
        $sum['bytes'] += (int) $bytes;
        if ($engine !== null && strcasecmp($engine, 'InnoDB') !== 0) {
            $sum['other_engines']++;
        }
    }
    // Triggers, stored procedures and functions, scheduled events: not in the file
    foreach (array("TRIGGERS WHERE TRIGGER_SCHEMA", "ROUTINES WHERE ROUTINE_SCHEMA", "EVENTS WHERE EVENT_SCHEMA") as $from) {
        if ($conn->query("SELECT COUNT(*) FROM information_schema.$from = '$schema'", false)) {
            list($count) = $conn->fetch_row();
            $sum['left_out'] += (int) $count;
        }
    }
    return $sum;
}

/**
 * The file's name: database-yyyymmdd-hhmm.sql (sorts by date in a folder), .sql.gz compressed.
 */
function backupFileName(string $dbName, bool $gzip): string
{
    return preg_replace('/[^A-Za-z0-9_.-]+/', '_', $dbName) . '-' . date('Ymd-Hi') . ($gzip ? '.sql.gz' : '.sql');
}

/**
 * Sends the file to the browser in pieces of 64 KB, compressed with gzip or not.
 */
class BackupWriter
{
    private $buffer = '';
    private $gzip;

    function __construct(bool $gzip)
    {
        $this->gzip = $gzip ? deflate_init(ZLIB_ENCODING_GZIP, array('level' => 6)) : null;
    }

    function write(string $text): void
    {
        $this->buffer .= $text;
        if (strlen($this->buffer) >= 65536) {
            $this->send(false);
        }
    }

    function finish(): void
    {
        $this->send(true);
    }

    private function send(bool $last): void
    {
        $out = $this->gzip ? deflate_add($this->gzip, $this->buffer, $last ? ZLIB_FINISH : ZLIB_NO_FLUSH) : $this->buffer;
        $this->buffer = '';
        if ($out !== '') {
            echo $out;
            flush();
        }
    }
}

/**
 * A query on the backup's connection; an error is thrown. MYSQLI_USE_RESULT reads the rows as they come (a table
 * of millions of events does not fit in memory); nothing else may run on the connection until the result is freed.
 */
function backupQuery(mysqli $link, string $sql, int $mode = MYSQLI_STORE_RESULT)
{
    try {
        $result = mysqli_query($link, $sql, $mode);
    } catch (mysqli_sql_exception $e) {
        throw new RuntimeException($e->getMessage());
    }
    if ($result === false) {
        throw new RuntimeException(mysqli_error($link));
    }
    return $result;
}

/**
 * Writes the database as SQL: [tables, rows].
 */
function backupDump(mysqli $link, string $dbName, BackupWriter $out): array
{
    // The file says SET NAMES utf8mb4: the text is read so, whatever the tables' own character sets
    mysqli_set_charset($link, 'utf8mb4');
    $schema = mysqli_real_escape_string($link, $dbName);

    // The rows as they are stored: TIMESTAMPs in UTC, no conversion of zero dates, names always quoted
    backupQuery($link, "SET SESSION time_zone = '+00:00'");
    backupQuery($link, "SET SESSION sql_mode = ''");
    backupQuery($link, "SET SESSION sql_quote_show_create = 1");
    // A slow download makes the server wait between rows: not a lost connection
    backupQuery($link, "SET SESSION net_write_timeout = 3600");

    list($version) = mysqli_fetch_row(backupQuery($link, "SELECT VERSION()"));

    // [table => [[column, kind]]] kind: 'n' a number, 'b' bytes (hex), 's' text; generated columns are left out
    $tables = $views = array();
    $result = backupQuery($link, "
        SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = '$schema'
        ORDER BY TABLE_NAME
    ");
    while (list($table, $type) = mysqli_fetch_row($result)) {
        if ($type === 'VIEW') {
            $views[] = $table;
        } else {
            $tables[$table] = array();
        }
    }
    $result = backupQuery($link, "
        SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, EXTRA FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '$schema'
        ORDER BY TABLE_NAME, ORDINAL_POSITION
    ");
    while (list($table, $column, $type, $extra) = mysqli_fetch_row($result)) {
        if (!isset($tables[$table]) || stripos((string) $extra, 'GENERATED') !== false) {
            continue;
        }
        $type = strtolower($type);
        if (in_array($type, array('tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'decimal', 'numeric', 'float', 'double', 'real', 'year', 'bit'), true)) {
            // BIT too: mysqli gives its value as a number (b'101' is 5)
            $kind = 'n';
        } elseif (in_array($type, array('binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'geometry', 'point', 'linestring', 'polygon', 'multipoint', 'multilinestring', 'multipolygon', 'geometrycollection', 'geomcollection'), true)) {
            $kind = 'b';
        } else {
            $kind = 's';
        }
        $tables[$table][] = array($column, $kind);
    }

    // One INSERT holds up to 1 MB of rows: any server takes it (max_allowed_packet), and it restores fast
    $budget = 1 << 20;

    $out->write(
        "-- HLstatsZ database backup\n" .
        "-- Database: $dbName\n" .
        "-- Server:   " . mysqli_get_host_info($link) . ", MySQL $version\n" .
        "-- Made:     " . date('Y-m-d H:i:s T') . "\n" .
        "--\n" .
        "-- Restores into any database, an empty one or a copy: mysql -u root -p mydb < this file\n" .
        "-- Its tables are replaced (DROP TABLE IF EXISTS): other tables of that database stay.\n\n" .
        "SET NAMES utf8mb4;\n" .
        "SET time_zone = '+00:00';\n" .
        "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\n" .
        "SET foreign_key_checks = 0;\n" .
        "SET unique_checks = 0;\n\n"
    );

    // InnoDB tables as they are at this moment, whatever the daemon writes during the download
    backupQuery($link, "SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
    backupQuery($link, "START TRANSACTION WITH CONSISTENT SNAPSHOT");

    $total = 0;
    foreach ($tables as $table => $columns) {
        $id = backupId($table);
        list(, $create) = mysqli_fetch_row(backupQuery($link, "SHOW CREATE TABLE " . backupId($dbName) . ".$id"));
        $out->write("--\n-- Table $table\n--\n\nDROP TABLE IF EXISTS $id;\n$create;\n\n");

        if (!$columns) {
            continue;
        }
        $names  = implode(', ', array_map(fn($c) => backupId($c[0]), $columns));
        $kinds  = array_column($columns, 1);
        $insert = "INSERT INTO $id ($names) VALUES\n";
        $result = backupQuery($link, "SELECT $names FROM " . backupId($dbName) . ".$id", MYSQLI_USE_RESULT);
        $size   = 0;
        $rows   = 0;
        while ($row = mysqli_fetch_row($result)) {
            foreach ($row as $i => &$value) {
                if ($value === null) {
                    $value = 'NULL';
                } elseif ($kinds[$i] === 'n') {
                    // as the server gives it: 12, -3.5, 1e-05
                } elseif ($kinds[$i] === 'b') {
                    $value = $value === '' ? "''" : '0x' . bin2hex($value);
                } else {
                    $value = "'" . mysqli_real_escape_string($link, $value) . "'";
                }
            }
            unset($value);
            $tuple = '(' . implode(',', $row) . ')';
            if ($size && $size + strlen($tuple) > $budget) {
                $out->write(";\n");
                $size = 0;
            }
            $out->write($size ? ",\n$tuple" : $insert . $tuple);
            $size += strlen($tuple) + 2;
            $rows++;
        }
        mysqli_free_result($result);
        if ($size) {
            $out->write(";\n");
        }
        $out->write("\n");
        $total += $rows;
    }

    // Views last: they read the tables. Without their DEFINER, whoever restores them owns them.
    foreach ($views as $view) {
        list(, $create) = mysqli_fetch_row(backupQuery($link, "SHOW CREATE VIEW " . backupId($dbName) . '.' . backupId($view)));
        $create = preg_replace('/\sDEFINER\s*=\s*(?:`(?:[^`]|``)*`|\S+)@(?:`(?:[^`]|``)*`|\S+)/i', '', $create, 1);
        $out->write("--\n-- View $view\n--\n\nDROP VIEW IF EXISTS " . backupId($view) . ";\n$create;\n\n");
    }

    backupQuery($link, "COMMIT");

    $out->write(
        "SET foreign_key_checks = 1;\n" .
        "SET unique_checks = 1;\n\n" .
        "-- Backup complete: " . count($tables) . " tables, " . count($views) . " views, $total rows, " . date('Y-m-d H:i:s T') . "\n"
    );
    return array(count($tables), $total);
}

$backupUrl = $g_options['scripturl'] . '?mode=admin&task=tools_backup';

////
//// The download (a plain POST from admin.php): the file, or back to the panel with what went wrong
////
if (!is_ajax()) {
    $state  = $_SESSION['backup'] ?? null;
    $index  = (int) ($_POST['backup_target'] ?? -1);
    $target = is_array($state) && hash_equals($state['token'], (string) ($_POST['backup_token'] ?? ''))
        ? ($state['targets'][$index] ?? null) : null;
    if ($target === null) {
        $_SESSION['backup_error'] = 'The page was out of date: download again.';
        header('Location: ' . $backupUrl);
        exit;
    }
    try {
        $conn = backupOpen($target);
    } catch (Throwable $e) {
        $_SESSION['backup_error'] = 'Could not open <b>' . htmlspecialchars($target['name']) . '</b>: ' . htmlspecialchars($e->getMessage());
        header('Location: ' . $backupUrl);
        exit;
    }

    $gzip = !empty($_POST['backup_gzip']) && function_exists('deflate_init');

    // The admin pages stay usable during a long download (the session file stays locked until it is closed)
    session_write_close();
    set_time_limit(0);
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: ' . ($gzip ? 'application/gzip' : 'application/sql; charset=utf-8'));
    header('Content-Disposition: attachment; filename="' . backupFileName($target['name'], $gzip) . '"');
    header('Cache-Control: no-store');
    header('X-Accel-Buffering: no');   // nginx: send it as it comes

    $out = new BackupWriter($gzip);
    try {
        backupDump($conn->link, $target['name'], $out);
    } catch (Throwable $e) {
        // The headers are gone: the file says it is incomplete, and the restore stops on it
        error_log('HLstatsZ backup of ' . $target['name'] . ' failed: ' . $e->getMessage());
        $out->write("\n-- BACKUP FAILED, THIS FILE IS INCOMPLETE: " . str_replace(array("\r", "\n"), ' ', $e->getMessage()) . "\nBACKUP FAILED;\n");
    }
    $out->finish();
    exit;
}

////
//// The panel
////

// The databases to offer, in the session for the download: HLstatsZ's, its ban databases on the same server,
// and another one given in the form below (its credentials stay in the session until this page opens again)
$error = $_SESSION['backup_error'] ?? '';
unset($_SESSION['backup_error'], $_SESSION['backup']);

$other = null;
if (isset($_POST['backup_other'])) {
    $other = array(
        'host' => trim((string) ($_POST['other_host'] ?? '')),
        'name' => trim((string) ($_POST['other_name'] ?? '')),
        'user' => trim((string) ($_POST['other_user'] ?? '')),
        'pass' => (string) ($_POST['other_pass'] ?? ''),
    );
}

// [target, connection for its summary, heading]
$targets = array();
$names   = array($db->db_name);
foreach (array('DB_SBNAME', 'DB_AMXNAME') as $const) {
    if (defined($const) && constant($const) !== '' && !in_array(constant($const), $names, true)) {
        $names[] = constant($const);
    }
}
foreach ($names as $name) {
    $targets[] = array(array('name' => $name, 'cfg' => true), $db, $name);
}
if ($other !== null) {
    if ($other['name'] === '') {
        $error = 'Enter the name of the database to back up.';
    } else {
        $host = $other['host'] !== '' ? $other['host'] : DB_ADDR;
        try {
            $targets[] = array($other, backupOpen($other), $other['name'] . ' on ' . $host);
        } catch (Throwable $e) {
            $error = 'Could not open <b>' . htmlspecialchars($other['name']) . '</b> on ' . htmlspecialchars($host) . ': ' . htmlspecialchars($e->getMessage());
        }
    }
}

$token  = bin2hex(random_bytes(16));
$stored = array();
$gzipOk = function_exists('deflate_init');
?>
<div class="panel">
<?php
if ($error !== '') {
    message('warning', $error);
}
?>
<p class="hlstats-admin-note">
    Downloads a database as an SQL file: its tables, with their rows, and its views. Nothing is kept on the web server.
    The file restores into any database, an empty one or a copy of the site's: <code>mysql -u root -p mydb &lt; file.sql</code>, or HeidiSQL, phpMyAdmin&hellip;
</p>
<?php
foreach ($targets as list($target, $conn, $heading)) {
    try {
        $sum = backupSummary($conn, $target['name']);
    } catch (Throwable $e) {
        message('warning', htmlspecialchars($heading) . ': ' . htmlspecialchars($e->getMessage()));
        continue;
    }
    if ($sum === null) {
        // A ban database of config.php not created yet
        continue;
    }
    $index    = count($stored);
    $stored[] = $target;
    $name     = backupFileName($target['name'], $gzipOk);
?>
<form method="POST" action="<?= htmlspecialchars($backupUrl) ?>" data-bound="1"
      onsubmit="var n=this.querySelector('.backup-started');n.hidden=false;setTimeout(function(){n.hidden=true;},15000);">
<?php /* data-bound: the panel leaves this form alone, the browser posts it and saves the file it gets */ ?>
<input type="hidden" name="backup_token" value="<?= $token ?>" />
<input type="hidden" name="backup_target" value="<?= $index ?>" />
<div class="hlstats-admin-propgroup">
<b><?= htmlspecialchars($heading) ?></b>
<p class="hlstats-admin-note">
    <?= nf($sum['tables']) ?> tables<?= $sum['views'] ? ', ' . nf($sum['views']) . ' views' : '' ?>,
    about <?= nf($sum['rows']) ?> rows, <?= backupBytes($sum['bytes']) ?> of data and indexes
    (the file is about as big; compressed, about ten times smaller).
    <br />File: <code><?= htmlspecialchars($name) ?></code>
<?php if ($sum['other_engines']) { ?>
    <br /><?= nf($sum['other_engines']) ?> tables are not InnoDB (MyISAM&hellip;): what is written to them during the download may be half in the file.
<?php } ?>
<?php if ($sum['left_out']) { ?>
    <br />Not in the file: <?= nf($sum['left_out']) ?> triggers, stored procedures or scheduled events.
<?php } ?>
</p>
<div class="responsive-table">
<table class="responsive-task">
<tbody>
<?php if ($gzipOk) { ?>
<tr>
    <td class="left"><label><input type="checkbox" name="backup_gzip" value="1" checked="checked" /> Compressed with gzip (<code>.sql.gz</code>)</label></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>
<div class="hlstats-admin-apply">
    <input type="submit" value="Download" />
</div>
<p class="hlstats-admin-note backup-started" hidden>Download started: your browser shows its progress. A whole file ends with <code>-- Backup complete</code>.</p>
</form>
<?php
}

if ($stored) {
    $_SESSION['backup'] = array('token' => $token, 'targets' => $stored);
}
?>
<form method="POST">
<input type="hidden" name="backup_other" value="1" />
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
    <input type="submit" value="Open" />
</div>
</form>
</div>
