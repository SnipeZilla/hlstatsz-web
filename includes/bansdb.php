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
 * The databases of SourceBans and AMXBans (DB_SBNAME and DB_AMXNAME in config.php) as Admin > Bans > Bans Settings
 * shows them, and their tables created when they are missing: from sql/install_sourcebans.sql or
 * sql/install_amxbans.sql, under the prefix of config.php plus "_", the names the bans pages and the plugins read.
 * Tables already there are never changed. A set found under another prefix is reported instead of creating one:
 * with a wrong prefix, the bans pages would read an empty second set while the game servers fill the first one.
 */

require_once INCLUDE_PATH . '/sqlimport.php';
require_once INCLUDE_PATH . '/amxbans.php';   // amxSteamId(), the STEAM_0:x:y form SourceBans stores too

// The ban databases: their settings in config.php, their SQL file, the tables that tell a set of theirs apart (both
// have a _bans and a _comments table), and the one a set of theirs always has, that the other has not
const BANS_DBS = array(
    'sb'  => array('title' => 'SourceBans', 'name' => 'DB_SBNAME', 'prefix' => 'DB_SBPREFIX', 'default' => 'sb',
                   'file' => 'install_sourcebans.sql', 'marks' => array('bans', 'banlog', 'admins'), 'core' => 'banlog'),
    'amx' => array('title' => 'AMXBans', 'name' => 'DB_AMXNAME', 'prefix' => 'DB_AMXPREFIX', 'default' => 'amx',
                   'file' => 'install_amxbans.sql', 'marks' => array('bans', 'amxadmins', 'serverinfo'), 'core' => 'serverinfo'),
);

// ADMIN_OWNER of SourceBans++ (web.json): every web permission, what its installer gives the first admin
const BANS_SB_OWNER = 16777216;

/**
 * The tables of a ban database, without their prefix, from its SQL file.
 */
function bansDbTables($kind)
{
    static $tables = array();

    if (!isset($tables[$kind])) {
        preg_match_all('/CREATE TABLE IF NOT EXISTS `\{prefix\}_(\w+)`/', (string) @file_get_contents(dirname(__DIR__) . '/sql/' . BANS_DBS[$kind]['file']), $m);
        $tables[$kind] = $m[1];
    }
    return $tables[$kind];
}

/**
 * Where a ban database stands, seen over $link (sqlConnect()): array('kind', 'state', 'name' => its database,
 * 'prefix', 'missing' => its missing tables, 'others' => the prefixes of a set found instead, 'errno', 'error').
 *   unset      not set in config.php (an empty name)
 *   invalid    its name or prefix has other characters than letters, digits and _ (and - in a name)
 *   connect    the database server does not answer
 *   nodb       the database does not exist: it can be created, with its tables
 *   noaccess   DB_USER may not use it
 *   elsewhere  none of its tables under this prefix, but a set under another one: the prefix in config.php is wrong
 *   clash      tables under this prefix that are another system's (the other ban system, HLstatsZ's hlstats_Servers
 *              for the prefix hlstats): another prefix is needed ('present' lists them)
 *   empty      none of its tables: they can be created
 *   partial    some of its tables are missing: they can be created
 *   ok         all there
 */
function bansDbInspect($kind, $link)
{
    $def   = BANS_DBS[$kind];
    $state = array(
        'kind'    => $kind,
        'name'    => defined($def['name']) ? (string) constant($def['name']) : '',
        'prefix'  => defined($def['prefix']) ? (string) constant($def['prefix']) : $def['default'],
        'missing' => array(),
        'present' => array(),
        'others'  => array(),
        'errno'   => 0,
        'error'   => '',
    );
    if ($state['name'] === '') {
        return array('state' => 'unset') + $state;
    }
    if (!sqlNameOk($state['name'], true) || !sqlNameOk($state['prefix'])) {
        return array('state' => 'invalid') + $state;
    }
    if (!$link) {
        return array('state' => 'connect') + $state;
    }
    list($state['errno'], $state['error']) = sqlUse($link, $state['name']);
    if ($state['errno']) {
        return array('state' => $state['errno'] === 1049 ? 'nodb' : 'noaccess') + $state;
    }
    $tables = sqlTables($link, $state['name']);
    if ($tables === null) {
        return array('state' => 'noaccess') + $state;
    }
    $nocase = sqlNoCase($link);
    foreach (bansDbTables($kind) as $table) {
        if (sqlFind($tables, $state['prefix'] . '_' . $table, $nocase) === null) {
            $state['missing'][] = $table;
        }
    }
    if (!$state['missing']) {
        return array('state' => 'ok') + $state;
    }
    $state['present'] = array_values(array_diff(bansDbTables($kind), $state['missing']));
    if ($state['present']) {
        // Some are there: this set's only with the table it always has, else another system uses this prefix
        return array('state' => in_array($def['core'], $state['present'], true) ? 'partial' : 'clash') + $state;
    }
    // None under this prefix: a set under another one, whatever its case? Its _bans table and the ones of its kind.
    foreach ($tables as $table) {
        if (strlen($table) > 5 && strcasecmp(substr($table, -5), '_bans') === 0) {
            $prefix = substr($table, 0, -5);
            $found  = true;
            foreach ($def['marks'] as $mark) {
                $found = $found && sqlFind($tables, $prefix . '_' . $mark, true) !== null;
            }
            if ($found) {
                $state['others'][] = $prefix;
            }
        }
    }
    return array('state' => $state['others'] ? 'elsewhere' : 'empty') + $state;
}

/**
 * Creates what a ban database lacks ($state from bansDbInspect(): nodb, empty or partial): the database, then its
 * missing tables with their default rows. A new SourceBans admins table gets $owner (array('authid' => STEAM_0:x:y,
 * 'name')) as its first admin, an owner with immunity 100 and no in-game flags, as SourceBans++'s installer does.
 * The ban system's own log records it, done by $by (the HLstatsZ admin).
 * array('ok' => bool, 'created' => tables created, 'database' => whether the database was created, 'owner' => whether
 * the owner was added, 'error' => why it failed).
 */
function bansDbCreate(array $state, $link, array $owner, $by)
{
    $def  = BANS_DBS[$state['kind']];
    $name = $state['name'];
    $P    = $state['prefix'];
    $done = array('ok' => false, 'created' => array(), 'database' => false, 'owner' => false, 'error' => '');

    if ($state['state'] === 'nodb') {
        list($errno, $error) = sqlCreateDatabase($link, $name);
        if ($errno) {
            $done['error'] = 'The database could not be created: ' . sqlExplain($errno, $error);
            return $done;
        }
        $done['database'] = true;
    }
    list($errno, $error) = sqlUse($link, $name);
    $before = $errno ? null : sqlTables($link, $name);
    if ($before === null) {
        $done['error'] = 'The database cannot be read: ' . sqlExplain($errno, $error);
        return $done;
    }

    $nocase = sqlNoCase($link);
    $vars   = array('{prefix}' => $P, '{charset}' => preg_replace('/\W/', '', DB_CHARSET), '{collate}' => preg_replace('/\W/', '', DB_COLLATE));
    $import = sqlImport($link, dirname(__DIR__) . '/sql/' . $def['file'], $vars, $before, $nocase);
    if (!$import['ok']) {
        if ($done['database'] && sqlTables($link, $name) === array()) {
            sqlRun($link, "DROP DATABASE `$name`");
        }
        $f = $import['failed'];
        $done['database'] = false;
        $done['error']    = 'sql/' . $def['file'] . ' stopped at its statement ' . $f['number'] . ': ' . sqlExplain($f['errno'], $f['error'])
            . ' Nothing was kept.';
        return $done;
    }
    $done['created'] = $import['created'];

    $e    = fn($s) => "'" . mysqli_real_escape_string($link, (string) $s) . "'";
    $ip   = $e($_SERVER['REMOTE_ADDR'] ?? '');
    $what = $done['database'] ? 'Database and tables created' : (count($import['created']) === count(bansDbTables($state['kind'])) ? 'Tables created' : 'Missing tables created');
    if ($state['kind'] === 'sb') {
        // Steam sign-in only: the password is random and never shown, as for the admins added in HLstatsZ
        if ($owner['authid'] !== '' && sqlFind($import['created'], $P . '_admins', $nocase) !== null) {
            list($errno, $error) = sqlRun($link, "INSERT INTO `{$P}_admins` (user, authid, password, gid, email, extraflags, immunity) VALUES ("
                . $e($owner['name']) . ', ' . $e($owner['authid']) . ', ' . $e(password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT))
                . ", -1, '', " . BANS_SB_OWNER . ', 100)');
            if ($errno) {
                // Without it nobody could manage SourceBans from HLstatsZ: undone, to try again
                sqlDrop($link, $import['created']);
                if ($done['database'] && sqlTables($link, $name) === array()) {
                    sqlRun($link, "DROP DATABASE `$name`");
                }
                $done['created']  = array();
                $done['database'] = false;
                $done['error']    = 'The owner could not be added: ' . sqlExplain($errno, $error) . ' Nothing was kept.';
                return $done;
            }
            $done['owner'] = true;
        }
        sqlRun($link, "INSERT INTO `{$P}_log` (type, title, message, function, query, aid, host, created) VALUES ('m', " . $e($what) . ', '
            . $e($what . ' by HLstatsZ (' . $by . ')' . ($done['owner'] ? ', with ' . $owner['name'] . ' as owner' : '') . ': ' . implode(', ', $import['created']) . '.')
            . ", 'bans_settings.php', '', 0, $ip, " . time() . ')');
    } else {
        sqlRun($link, "INSERT INTO `{$P}_logs` (timestamp, ip, username, action, remarks) VALUES (UNIX_TIMESTAMP(), $ip, "
            . $e(mb_substr($by, 0, 32)) . ", 'Install', " . $e(mb_substr($what . ': ' . count($import['created']) . ' (HLstatsZ)', 0, 256)) . ')');
    }
    $done['ok'] = true;
    return $done;
}
