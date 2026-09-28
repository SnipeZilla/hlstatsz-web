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

/*
 * Imports the SQL files of sql/: install.sql (HLstatsZ, by the installer, pages/install.php) and install_sourcebans.sql
 * or install_amxbans.sql (the ban databases, by Admin > Bans > Bans Settings). They run over a connection of their
 * own, so what they set in their session (sql_mode...) stays there. An import never changes a table that was there
 * before it, and one that fails is undone: the tables it created are dropped.
 *
 * mysqli throws on errors since PHP 8.1: every call is caught here and gives (error number, message) instead.
 */

/**
 * The statements of an SQL file, its comments left out. It is split at the semicolons outside quotes: strings, with
 * their backslash escapes and doubled quotes, and `names`. MySQL runs the comments that start with /*! so they are
 * kept. DELIMITER (stored procedures) is not supported.
 */
function sqlStatements($sql)
{
    $statements = array();
    $current    = '';
    $len        = strlen($sql);
    $i          = 0;
    while ($i < $len) {
        $c = $sql[$i];
        if ($c === "'" || $c === '"' || $c === '`') {
            // To the closing quote: a backslash escapes the next character of a string, a doubled quote is a quote
            $j = $i + 1;
            while ($j < $len) {
                $j += strcspn($sql, $c === '`' ? '`' : $c . '\\', $j);
                if ($j >= $len) {
                    break;
                }
                if ($sql[$j] === '\\') {
                    $j += 2;
                } elseif ($j + 1 < $len && $sql[$j + 1] === $c) {
                    $j += 2;
                } else {
                    break;
                }
            }
            $current .= substr($sql, $i, $j + 1 - $i);
            $i = $j + 1;
        } elseif ($c === '#' || ($c === '-' && substr($sql, $i, 2) === '--' && ($i + 2 >= $len || ctype_space($sql[$i + 2])))) {
            $end = strpos($sql, "\n", $i);
            $i   = $end === false ? $len : $end;
        } elseif ($c === '/' && substr($sql, $i, 2) === '/*') {
            $end = strpos($sql, '*/', $i + 2);
            $end = $end === false ? $len : $end + 2;
            if (substr($sql, $i, 3) === '/*!') {
                $current .= substr($sql, $i, $end - $i);
            }
            $i = $end;
        } elseif ($c === ';') {
            if (trim($current) !== '') {
                $statements[] = trim($current);
            }
            $current = '';
            $i++;
        } else {
            $run      = max(1, strcspn($sql, "'\"`#-/;", $i));
            $current .= substr($sql, $i, $run);
            $i       += $run;
        }
    }
    if (trim($current) !== '') {
        $statements[] = trim($current);
    }
    return $statements;
}

/**
 * The table a statement creates or fills: array('create' or 'insert', its name), or null for any other statement.
 */
function sqlTarget($statement)
{
    if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_$]+)`?\s*\(/i', $statement, $m)) {
        return array('create', $m[1]);
    }
    if (preg_match('/^INSERT\s+(?:IGNORE\s+)?INTO\s+`?([A-Za-z0-9_$]+)`?[\s(]/i', $statement, $m)) {
        return array('insert', $m[1]);
    }
    return null;
}

/**
 * A connection of its own to the database server of config.php, in HLstatsZ's character set, no database selected:
 * array(the connection, '') or array(null, array(error number, message)).
 */
function sqlConnect()
{
    try {
        $link = @mysqli_connect(DB_ADDR, DB_USER, DB_PASS);
    } catch (\mysqli_sql_exception $e) {
        return array(null, array((int) $e->getCode(), $e->getMessage()));
    }
    if (!$link) {
        return array(null, array((int) mysqli_connect_errno(), (string) mysqli_connect_error()));
    }
    try {
        mysqli_set_charset($link, DB_CHARSET);
    } catch (\mysqli_sql_exception $e) {
        // the server's own character set then
    }
    return array($link, '');
}

/**
 * Runs a statement: array(0, '') when done, else array(error number, message).
 */
function sqlRun($link, $query)
{
    try {
        $result = @mysqli_query($link, $query);
    } catch (\mysqli_sql_exception $e) {
        return array((int) $e->getCode(), $e->getMessage());
    }
    if ($result === false) {
        return array((int) mysqli_errno($link), (string) mysqli_error($link));
    }
    if ($result instanceof mysqli_result) {
        mysqli_free_result($result);
    }
    return array(0, '');
}

/**
 * The rows of a query, as numbered arrays, or null when it fails.
 */
function sqlRows($link, $query)
{
    try {
        $result = @mysqli_query($link, $query);
    } catch (\mysqli_sql_exception $e) {
        return null;
    }
    if (!$result instanceof mysqli_result) {
        return null;
    }
    $rows = mysqli_fetch_all($result, MYSQLI_NUM);
    mysqli_free_result($result);
    return $rows;
}

/**
 * Makes a database the one of the connection: array(0, '') when done, else array(error number, message); 1049 when
 * it does not exist, 1044 when DB_USER may not use it.
 */
function sqlUse($link, $database)
{
    try {
        if (@mysqli_select_db($link, $database)) {
            return array(0, '');
        }
    } catch (\mysqli_sql_exception $e) {
        return array((int) $e->getCode(), $e->getMessage());
    }
    return array((int) mysqli_errno($link), (string) mysqli_error($link));
}

/**
 * The tables of a database as the server names them, or null when they cannot be read.
 */
function sqlTables($link, $database)
{
    $rows = sqlRows($link, "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = '"
        . mysqli_real_escape_string($link, $database) . "'");
    return $rows === null ? null : array_column($rows, 0);
}

/**
 * Whether the server matches table names whatever their case (lower_case_table_names 1 or 2: Windows, macOS).
 */
function sqlNoCase($link)
{
    $rows = sqlRows($link, 'SELECT @@lower_case_table_names');
    return $rows && (int) $rows[0][0] > 0;
}

/**
 * The table of $tables the server takes for $name: the same name, or the same in another case when it ignores case.
 */
function sqlFind(array $tables, $name, $nocase)
{
    foreach ($tables as $table) {
        if ($nocase ? strcasecmp($table, $name) === 0 : $table === $name) {
            return $table;
        }
    }
    return null;
}

/**
 * Whether a database name or a table prefix can go in the SQL as is: letters, digits and _, and also - in the name
 * of a database.
 */
function sqlNameOk($name, $database = false)
{
    return preg_match($database ? '/^[A-Za-z0-9_-]{1,64}$/' : '/^[A-Za-z0-9_]{1,32}$/', (string) $name) === 1;
}

/**
 * Creates a database in the character set and collation of HLstatsZ's tables: array(0, '') or array(error number,
 * message); 1007 when it exists, 1044 when DB_USER may not create it.
 */
function sqlCreateDatabase($link, $database)
{
    return sqlRun($link, 'CREATE DATABASE `' . $database . '` CHARACTER SET ' . preg_replace('/\W/', '', DB_CHARSET)
        . ' COLLATE ' . preg_replace('/\W/', '', DB_COLLATE));
}

/**
 * Imports an SQL file of sql/ into the database of the connection, its {placeholders} replaced by $vars ('{prefix}'
 * => 'sb'...). The rows of the tables already there ($existing) are left out: only what is missing is added. The
 * first error stops it, and the tables it created are dropped again.
 * array('ok' => bool, 'run' => statements run, 'created' => tables created, 'failed' => null, or when it failed
 * array('number' => the statement's number in the file, 'sql' => its beginning, 'errno' => .., 'error' => ..)).
 */
function sqlImport($link, $file, array $vars, array $existing, $nocase)
{
    $done = array('ok' => false, 'run' => 0, 'created' => array(), 'failed' => null);
    $sql  = @file_get_contents($file);
    if ($sql === false) {
        $done['failed'] = array('number' => 0, 'sql' => '', 'errno' => 0, 'error' => 'Cannot read ' . basename($file) . '.');
        return $done;
    }
    foreach (sqlStatements(strtr($sql, $vars)) as $n => $statement) {
        $target = sqlTarget($statement);
        $before = $target ? sqlFind($existing, $target[1], $nocase) !== null : false;
        if ($target && $target[0] === 'insert' && $before) {
            continue;
        }
        list($errno, $error) = sqlRun($link, $statement);
        if ($errno) {
            $done['failed'] = array('number' => $n + 1, 'sql' => mb_strimwidth(preg_replace('/\s+/', ' ', $statement), 0, 120, '…'),
                'errno' => $errno, 'error' => $error);
            sqlDrop($link, $done['created']);
            $done['created'] = array();
            return $done;
        }
        $done['run']++;
        if ($target && $target[0] === 'create' && !$before && !sqlExisted($link) && sqlFind($done['created'], $target[1], $nocase) === null) {
            $done['created'][] = $target[1];
        }
    }
    $done['ok'] = true;
    return $done;
}

/**
 * Whether the CREATE TABLE IF NOT EXISTS just run found its table there already (MySQL's note 1050): a second check,
 * after the list of tables, so that undoing an import never drops a table it did not create.
 */
function sqlExisted($link)
{
    if (!mysqli_warning_count($link)) {
        return false;
    }
    foreach (sqlRows($link, 'SHOW WARNINGS') ?? array() as $warning) {
        if ((int) $warning[1] === 1050) {
            return true;
        }
    }
    return false;
}

/**
 * Drops tables of the connection's database (the ones an import created), the last created first.
 */
function sqlDrop($link, array $tables)
{
    foreach (array_reverse($tables) as $table) {
        sqlRun($link, 'DROP TABLE IF EXISTS `' . str_replace('`', '', $table) . '`');
    }
}

/**
 * A line on a database error for the page: what the server's error number means for HLstatsZ, or its message.
 */
function sqlExplain($errno, $error)
{
    $known = array(
        1044 => 'DB_USER of config.php is not allowed to do this: give it the rights on this database (in your hosting panel), or create the database there.',
        1045 => 'The database server refuses DB_USER and DB_PASS of config.php.',
        1049 => 'This database does not exist.',
        1142 => 'DB_USER of config.php is not allowed to do this: give it the rights on this database (in your hosting panel).',
        1227 => 'DB_USER of config.php is not allowed to do this (it needs the CREATE right).',
        1273 => 'The database server does not know this collation: check DB_COLLATE in config.php.',
        2002 => 'The database server does not answer at DB_ADDR of config.php.',
        2003 => 'The database server does not answer at DB_ADDR of config.php.',
        2005 => 'The database server named by DB_ADDR in config.php cannot be found.',
        2006 => 'The database server closed the connection.',
        2054 => 'The database server refuses DB_USER of config.php: it does not exist, or it signs in with a method PHP cannot use (ed25519, GSSAPI). Give it a usual password.',
    );
    return $known[$errno] ?? ("MySQL error $errno: " . $error);
}
