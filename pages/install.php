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
 * The installer. hlstats.php shows it instead of the site as long as HLstatsZ's database is not installed. In one
 * step it creates the database when it is missing (when DB_USER may), imports sql/install.sql, sets the first admin
 * and runs the updates of updater/. It never changes a database that already holds HLstatsZ tables: then, as when the
 * database does not answer, it only says what is wrong and how to fix it.
 *
 * No admin exists yet, so the page is public: whoever installs proves the site is theirs with the database password
 * of config.php, or, when config.php has none, by opening the page on the web server itself. Wrong passwords are
 * limited per address and in all (cache/hlstatsz_install_*.json).
 */

require_once INCLUDE_PATH . '/sqlimport.php';

/**
 * Where HLstatsZ's database stands: array('state' => .., 'errno' => .., 'error' => .., 'tables' => its HLstatsZ
 * tables, 'others' => its other tables). The states:
 *   noname    DB_NAME is empty in config.php
 *   connect   the database server does not answer, or refuses DB_USER and DB_PASS
 *   nodb      DB_NAME does not exist: the installer creates it
 *   noaccess  DB_USER may not use DB_NAME
 *   empty     DB_NAME holds no HLstatsZ table (other tables maybe): the installer installs them
 *   case      its HLstatsZ tables are named in another case than HLstatsZ's (lowercase, from a Windows server)
 *   partial   some HLstatsZ tables but no hlstats_Options: an install that stopped, or a damaged database
 *   broken    hlstats_Options is there, but cannot be read or holds no setting
 */
function installState()
{
    global $db;

    $state = array('errno' => $db->last_error[0], 'error' => $db->last_error[1], 'tables' => 0, 'others' => 0);
    if ((string) DB_NAME === '') {
        return array('state' => 'noname') + $state;
    }
    if (!$db->link) {
        return array('state' => 'connect') + $state;
    }
    if ($db->db_name === null) {
        return array('state' => $db->last_error[0] === 1049 ? 'nodb' : 'noaccess') + $state;
    }
    $tables = sqlTables($db->link, DB_NAME);
    if ($tables === null) {
        return array('state' => 'noaccess') + $state;
    }
    $ours = array_values(array_filter($tables, fn($t) => stripos($t, 'hlstats_') === 0));
    $state['tables'] = count($ours);
    $state['others'] = count($tables) - count($ours);
    if (!$ours) {
        return array('state' => 'empty') + $state;
    }
    if (sqlFind($ours, 'hlstats_Options', sqlNoCase($db->link)) !== null) {
        return array('state' => 'broken') + $state;
    }
    return array('state' => sqlFind($ours, 'hlstats_Options', true) !== null ? 'case' : 'partial') + $state;
}

/**
 * How the visitor proves the site is theirs: 'password', the database password of config.php; 'local', nothing to
 * type, as config.php has no password and the page is opened on the web server itself (not through a proxy); or ''
 * when neither is possible.
 */
function installProof()
{
    if ((string) DB_PASS !== '') {
        return 'password';
    }
    $proxied = !empty($_SERVER['HTTP_X_FORWARDED_FOR']) || !empty($_SERVER['HTTP_X_REAL_IP']) || !empty($_SERVER['HTTP_FORWARDED']);
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', array('127.0.0.1', '::1'), true) && !$proxied ? 'local' : '';
}

/**
 * The counters of wrong passwords: file => array(most allowed, over how many seconds). 5 per address in 15 minutes,
 * 30 in all in an hour.
 */
function installCounters()
{
    $dir = dirname(__DIR__) . '/cache/';
    return array(
        $dir . 'hlstatsz_install_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? '')) . '.json' => array(5, 900),
        $dir . 'hlstatsz_install_all.json'                                                   => array(30, 3600),
    );
}

/**
 * The minutes to wait before another password may be tried, or 0.
 */
function installWait()
{
    $wait = 0;
    foreach (installCounters() as $file => $limit) {
        $count = json_decode((string) @file_get_contents($file), true);
        if (is_array($count) && ($count['until'] ?? 0) > time() && ($count['fails'] ?? 0) >= $limit[0]) {
            $wait = max($wait, (int) ceil(($count['until'] - time()) / 60));
        }
    }
    return $wait;
}

/**
 * Counts a wrong password, and makes its sender wait a second.
 */
function installFailed()
{
    foreach (installCounters() as $file => $limit) {
        $count = json_decode((string) @file_get_contents($file), true);
        if (!is_array($count) || ($count['until'] ?? 0) <= time()) {
            $count = array('fails' => 0, 'until' => time() + $limit[1]);
        }
        $count['fails']++;
        @file_put_contents($file, json_encode($count), LOCK_EX);
    }
    sleep(1);
}

/**
 * The database version the updates of updater/ lead to.
 */
function installLatest()
{
    $updates = array_map(fn($file) => (int) basename($file), glob(dirname(__DIR__) . '/updater/[0-9]*.php') ?: array());
    return $updates ? max($updates) : 0;
}

/**
 * What the server offers, before installing: a list of array(true = fine, false = a problem, null = a warning,
 * what, why it matters).
 */
function installChecks()
{
    global $db;

    $checks   = array();
    $checks[] = array(version_compare(PHP_VERSION, '8.3.0', '>='), 'PHP ' . PHP_VERSION, 'HLstatsZ needs PHP 8.3 or later.');
    $missing  = array_values(array_filter(array('mysqli', 'gd', 'curl', 'mbstring', 'intl', 'sockets', 'bcmath', 'xml'), fn($e) => !extension_loaded($e)));
    $checks[] = $missing
        ? array(false, 'PHP extensions missing: ' . implode(', ', $missing), 'Turn them on in php.ini, then reload this page.')
        : array(true, 'PHP extensions mysqli, gd, curl, mbstring, intl, sockets, bcmath and xml', '');

    $server = (string) mysqli_get_server_info($db->link);
    $maria  = stripos($server, 'mariadb') !== false;
    $number = preg_match('/^(?:5\.5\.5-)?(\d+\.\d+(?:\.\d+)?)/', $server, $m) ? $m[1] : '0';
    $checks[] = array(version_compare($number, $maria ? '10.2' : '8.0', '>='), ($maria ? 'MariaDB ' : 'MySQL ') . $number,
        'HLstatsZ needs MySQL 8.0 or MariaDB 10.2, or later.');

    foreach (array(IMAGE_PATH . '/progress', './cache') as $dir) {
        $checks[] = is_dir($dir) && is_writable($dir)
            ? array(true, 'Folder ' . ltrim($dir, './') . ' is writable', '')
            : array(false, 'Folder ' . ltrim($dir, './') . ' is not writable', 'The web server must be able to write there (graphs, signatures, caches).');
    }

    $key = defined('SECRET_KEY') ? (string) SECRET_KEY : '';
    $checks[] = strlen($key) >= 32
        ? array(true, 'SECRET_KEY is set', '')
        : array(null, 'SECRET_KEY is ' . ($key === '' ? 'empty' : 'short'), 'Set 64 random letters and digits in config.php before opening the site: it signs the Steam sign-in.');

    // Set, they turn the password login off: a wrong key or ID would then leave nobody able to sign in
    $api    = defined('STEAM_API') ? (string) STEAM_API : '';
    $admins = array_values(array_filter(array_map('strval', defined('STEAM_ADMIN') ? (array) STEAM_ADMIN : array())));
    if ($api === '' || !$admins) {
        $checks[] = array(null, 'Steam sign-in is off: admins sign in with a password',
            'A Steam Web API key (STEAM_API) and your SteamID64 (STEAM_ADMIN) in config.php turn it on: the safer way into the admin panel.');
    } elseif (!preg_match('/\A[a-f0-9]{32}\z/i', $api) || array_filter($admins, fn($a) => !preg_match('/\A7656119\d{10}\z/', $a))) {
        $checks[] = array(false, 'Steam sign-in is set wrong',
            'STEAM_API must be the 32-character key of steamcommunity.com/dev/apikey and STEAM_ADMIN SteamID64s (7656119…): as they are, nobody could sign in to the admin panel.');
    } else {
        $checks[] = array(true, 'Steam sign-in: ' . count($admins) . ' admin' . (count($admins) > 1 ? 's' : ''), '');
    }
    return $checks;
}

/**
 * Runs one update of updater/ as the admin's updater does, in a scope of its own.
 */
function installUpdate($file)
{
    global $db;

    include $file;
}

/**
 * Installs: the database when it is missing, sql/install.sql, the first admin (signed in), then the updates.
 * array('ok' => bool, 'error' => why it stopped (HTML), 'steps' => what was done (HTML lines), 'warning' => an update
 * that failed (HTML), 'log' => the updates' output).
 */
function installRun(array $state, array $admin)
{
    global $db, $g_options;

    @set_time_limit(300);
    ignore_user_abort(true);
    $h     = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
    $name  = '<b>' . $h(DB_NAME) . '</b>';
    $steps = array();

    list($link, $failed) = sqlConnect();
    if (!$link) {
        return array('ok' => false, 'error' => $h(sqlExplain($failed[0], $failed[1])));
    }

    $created = false;
    if ($state['state'] === 'nodb') {
        list($errno, $error) = sqlNameOk(DB_NAME, true) ? sqlCreateDatabase($link, DB_NAME) : array(-1, '');
        if ($errno) {
            return array('ok' => false, 'error' => "The database $name could not be created: "
                . ($errno === -1 ? 'its name has other characters than letters, digits, - and _.' : $h(sqlExplain($errno, $error)))
                . ' Create it in your hosting panel, in utf8mb4, give DB_USER all rights on it, then reload this page.');
        }
        $created = true;
        $steps[] = "Database $name created";
    }
    list($errno, $error) = sqlUse($link, DB_NAME);
    $before = $errno ? null : sqlTables($link, DB_NAME);
    if ($before === null) {
        return array('ok' => false, 'error' => "The database $name cannot be read: " . $h(sqlExplain($errno, $error)));
    }
    if (array_filter($before, fn($t) => stripos($t, 'hlstats_') === 0)) {
        return array('ok' => false, 'error' => "HLstatsZ tables appeared in $name meanwhile: it is being installed from elsewhere. Reload this page.");
    }

    $import = sqlImport($link, dirname(__DIR__) . '/sql/install.sql', array(), $before, sqlNoCase($link));
    if (!$import['ok']) {
        if ($created && sqlTables($link, DB_NAME) === array()) {
            sqlRun($link, 'DROP DATABASE `' . DB_NAME . '`');
        }
        $f = $import['failed'];
        return array('ok' => false, 'error' => 'sql/install.sql stopped at its statement ' . $f['number'] . ($f['sql'] !== '' ? ' (<code>' . $h($f['sql']) . '</code>)' : '')
            . ': ' . $h(sqlExplain($f['errno'], $f['error'])) . ' Nothing was kept: fix it, then install again.');
    }
    mysqli_close($link);
    $steps[] = '<b>' . count($import['created']) . '</b> tables created and filled from sql/install.sql';

    // The site's own connection from here
    $db->select_db(DB_NAME);
    if ($admin['username'] !== '') {
        // Hashed as the login form reads the password (valid_request)
        $hash = md5(valid_request($admin['password'], false));
        $db->query("DELETE FROM hlstats_Users");
        $db->query("INSERT INTO hlstats_Users (username, password, acclevel, playerId) VALUES ('" . $db->escape($admin['username']) . "', '$hash', 100, 0)");
        startAdminSession(array(
            'loggedin'         => 1,
            'username'         => $admin['username'],
            'authpasswordhash' => $hash,
            'authsessionStart' => time(),
            'acclevel'         => 100,
        ));
        $steps[] = 'Admin <b>' . $h($admin['username']) . '</b> created, and signed in';
    } else {
        // Admins sign in with Steam: no password login, not even the default one of install.sql
        $db->query("DELETE FROM hlstats_Users WHERE username = 'admin'");
        $steps[] = 'Admins sign in with Steam (STEAM_ADMIN): no password login was created';
    }

    // The updates, as Admin > Tools > Updater runs them. One that fails stops them: the database keeps the version
    // before it, for the updater to run it again.
    $g_options = getOptions(false) ?: array();
    $from      = $version = (int) ($g_options['dbversion'] ?? 0);
    $log       = '';
    $warning   = '';
    if (!defined('IN_UPDATER')) {
        define('IN_UPDATER', true);
    }
    $db->exit_on_error = false;
    for ($i = $from + 1; $from > 0 && is_file(dirname(__DIR__) . "/updater/$i.php"); $i++) {
        $db->failures = array();
        ob_start(function ($chunk) use (&$log) {
            $log .= $chunk;
            return '';
        });
        installUpdate(dirname(__DIR__) . "/updater/$i.php");
        ob_end_flush();
        if ($db->failures) {
            $db->query("UPDATE hlstats_Options SET `value` = '$version' WHERE `keyname` = 'dbversion'", false);
            $f       = $db->failures[0];
            $warning = "Update $i failed: " . $h(sqlExplain($f['errno'], $f['error'])) . " The database stays at version $version: run "
                . 'Admin &rsaquo; Tools &rsaquo; Updater once the cause is fixed.';
            break;
        }
        $version = $i;
    }
    $db->exit_on_error = true;
    if ($version > $from) {
        $steps[] = "Database updated from version $from to <b>$version</b>";
    }
    return array('ok' => true, 'steps' => $steps, 'warning' => $warning, 'log' => $log);
}

$h      = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$inst   = installState();
$canRun = in_array($inst['state'], array('nodb', 'empty'), true);
$proof  = $canRun ? installProof() : '';
$steam  = defined('STEAM_API') && (string) STEAM_API !== '' && defined('STEAM_ADMIN') && !empty(STEAM_ADMIN);
$token  = $_SESSION['install_token'] ??= bin2hex(random_bytes(16));
$errors = array();
$done   = null;
$user   = 'admin';

// Headers first: the updates flush their output
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
if (!$canRun) {
    http_response_code(503);
    header('Retry-After: 300');
}

if ($canRun && ($_POST['install_do'] ?? '') === 'install') {
    $user     = is_string($_POST['install_user'] ?? null) ? trim($_POST['install_user']) : '';
    $password = is_string($_POST['install_pass'] ?? null) ? $_POST['install_pass'] : '';
    $confirm  = is_string($_POST['install_pass2'] ?? null) ? $_POST['install_pass2'] : '';
    $wait     = $proof === 'password' ? installWait() : 0;
    if (!is_string($_POST['install_token'] ?? null) || !hash_equals($token, $_POST['install_token'])) {
        $errors[] = 'This form has expired: send it again.';
    } elseif ($proof === '') {
        $errors[] = 'config.php has no database password: install from the web server itself.';
    } elseif ($wait) {
        $errors[] = "Too many wrong passwords: try again in $wait minute" . ($wait > 1 ? 's' : '') . '.';
    } elseif ($proof === 'password' && !hash_equals((string) DB_PASS, is_string($_POST['install_dbpass'] ?? null) ? $_POST['install_dbpass'] : '')) {
        installFailed();
        $errors[] = 'This is not the database password of config.php (DB_PASS).';
    } else {
        if (!$steam) {
            if (!preg_match('/^[A-Za-z0-9_.-]{1,16}$/', $user)) {
                $errors[] = 'Admin name: 1 to 16 letters, digits, dots, dashes or underscores.';
            }
            if (strlen($password) < 8 || strlen($password) > 16) {
                $errors[] = 'Admin password: 8 to 16 characters.';
            } elseif ($password !== $confirm) {
                $errors[] = 'The two admin passwords are not the same.';
            }
        }
        if (!$errors) {
            $done = installRun($inst, $steam ? array('username' => '', 'password' => '') : array('username' => $user, 'password' => $password));
            if (!$done['ok']) {
                $inst = installState();
            }
        }
    }
}

$latest = installLatest();
$checks = $canRun && !($done['ok'] ?? false) ? installChecks() : array();
$icon   = fn($ok) => svgIcon($ok === true ? 'check' : ($ok === false ? 'x' : 'alert'), 16);
$admins = $steam ? count((array) STEAM_ADMIN) : 0;
$name   = '<b>' . $h(DB_NAME) . '</b>';
$tables = fn($n, $what = 'table') => $n . ' ' . $what . ($n === 1 ? '' : 's');

// What the page says when HLstatsZ cannot be installed from here: array(title, explanation (HTML))
$problems = array(
    'noname'   => array('No database is set', 'Set DB_NAME in config.php: the database of the HLstatsZ daemon (DBName in its hlstats.conf).'),
    'connect'  => array('The database does not answer', $h(sqlExplain($inst['errno'], $inst['error'])) . ' Check DB_ADDR, DB_USER and DB_PASS in config.php, and that the MySQL or MariaDB server is running.'),
    'noaccess' => array('HLstatsZ may not use its database', "DB_USER of config.php has no rights on the database $name. Give it all rights on it in your hosting panel (or create the database there), then reload this page."),
    'case'     => array('Its tables are named in lowercase', "The database $name holds " . $tables($inst['tables'], 'HLstatsZ table') . ' named in lowercase (hlstats_options instead of hlstats_Options), as a Windows server writes them in a backup, and this server tells case apart. Restore them with their real names, or set lower_case_table_names = 1 on the database server.'),
    'partial'  => array('The database is incomplete', "The database $name holds " . $tables($inst['tables'], 'HLstatsZ table') . ' but not hlstats_Options: an install that stopped halfway, or a damaged database. Restore it from a backup, or empty it (drop its hlstats_ tables) to install again.'),
    'broken'   => array('HLstatsZ cannot read its settings', "The table hlstats_Options of $name " . ($inst['errno'] ? 'cannot be read: ' . $h(sqlExplain($inst['errno'], $inst['error'])) : 'holds no settings.') . ' Restore it from a backup.'),
);
if (!$canRun && $inst['errno']) {
    error_log('HLstatsZ: database ' . $inst['state'] . ': (' . $inst['errno'] . ') ' . $inst['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $done['ok'] ?? false ? 'HLstatsZ is installed' : ($canRun ? 'Install HLstatsZ' : 'HLstatsZ') ?></title>
    <link rel="icon" type="image/svg+xml" href="hlstatsz-favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="hlstatsz-favicon-32.png">
    <link rel="stylesheet" type="text/css" href="styles/hlstatsz.css?<?= @filemtime('styles/hlstatsz.css') ?>">
    <style>
    .hlstats-install-bar .hlstats-inner{ height: 72px; }
    .hlstats-install-brand{ display: inline-flex; align-items: center; gap: 12px; color: var(--main-color); text-decoration: none; font-size: 22px; font-weight: 700; letter-spacing: .02em; }
    .hlstats-install-brand img{ border-radius: 9px; }
    .hlstats-install-brand .z{ font-weight: inherit; }
    .hlstats-install{ flex: 1 0 auto; width: 100%; max-width: 760px; margin: 0 auto; padding: 36px 16px 72px; }
    .hlstats-install-card{ background: var(--card-bg); border: var(--card-border); border-radius: 12px; box-shadow: var(--card-section-shadow); padding: 30px 32px; }
    .hlstats-install :is(h1, h2, h3){ background: none; border: 0; border-radius: 0; padding: 0; }
    .hlstats-install h1{ display: flex; align-items: center; gap: 12px; margin: 0 0 8px; font-size: 26px; font-weight: 600; line-height: 1.25; }
    .hlstats-install h1 svg{ flex: none; }
    .hlstats-install h1.is-ok svg{ color: var(--sba-ok); }
    .hlstats-install h1.is-warn svg{ color: var(--sba-warn); }
    .hlstats-install h2{ margin: 28px 0 10px; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--card-title-color); opacity: .75; }
    .hlstats-install p{ margin: 0 0 10px; line-height: 1.6; }
    .hlstats-install-lead{ color: rgb(from var(--main-color) r g b / 82%); }
    .hlstats-install code{ padding: 1px 5px; border-radius: 4px; background: var(--input-bg); font-size: 12.5px; word-break: break-all; }
    .hlstats-install-list{ list-style: none; margin: 0; padding: 0; border: var(--card-section-border); border-radius: 8px; background: var(--card-section-bg); }
    .hlstats-install-list li{ display: flex; gap: 12px; align-items: flex-start; padding: 10px 14px; border-top: var(--card-section-border); line-height: 1.45; }
    .hlstats-install-list li:first-child{ border-top: 0; }
    .hlstats-install-list li > svg{ flex: none; margin-top: 2px; }
    .hlstats-install-list small{ display: block; opacity: .72; }
    .hlstats-install-list .is-ok > svg{ color: var(--sba-ok); }
    .hlstats-install-list .is-bad > svg{ color: var(--sba-danger); }
    .hlstats-install-list .is-warn > svg{ color: var(--sba-warn); }
    .hlstats-install-field{ display: grid; gap: 6px; margin: 0 0 14px; }
    .hlstats-install-field label{ font-size: 13px; font-weight: 600; }
    .hlstats-install-field input{ width: 100%; height: 40px; border-radius: 6px; font-size: 15px; }
    .hlstats-install-field small{ font-size: 12px; opacity: .7; line-height: 1.45; }
    .hlstats-install-pair{ display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
    .hlstats-install-note{ display: flex; gap: 10px; padding: 11px 14px; border-radius: 8px; background: var(--card-section-bg); border: var(--card-section-border); font-size: 13.5px; line-height: 1.5; }
    .hlstats-install-note svg{ flex: none; margin-top: 2px; opacity: .8; }
    .hlstats-install-msg{ margin: 18px 0 0; padding: 12px 14px; border: 1px solid; border-radius: 8px; font-size: 14px; line-height: 1.55; }
    .hlstats-install-msg.is-bad{ color: #ffb1ab; background: rgba(229,83,75,.12); border-color: rgba(229,83,75,.4); }
    .hlstats-install-msg.is-warn{ color: #f0c674; background: rgba(210,153,34,.12); border-color: rgba(210,153,34,.4); }
    .hlstats-install-msg p:last-child{ margin: 0; }
    .hlstats-install-go{ width: 100%; height: 46px; margin-top: 22px; font-size: 15px; }
    .hlstats-install-go:disabled{ cursor: progress; }
    .hlstats-install-actions{ display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
    .hlstats-install-actions .hlstats-btn{ height: 42px; padding: 0 20px; font-size: 14px; }
    .hlstats-install-next{ margin: 0; padding-left: 20px; line-height: 1.6; }
    .hlstats-install-next li{ margin-bottom: 8px; }
    .hlstats-install details{ margin-top: 22px; font-size: 13px; }
    .hlstats-install summary{ cursor: pointer; opacity: .8; }
    .hlstats-install-log{ max-height: 320px; overflow: auto; margin-top: 10px; padding: 12px 14px; border-radius: 8px; background: var(--input-bg); font-size: 12px; line-height: 1.5; }
    .hlstats-install-log h3{ margin: 10px 0 4px; font-size: 12px; }
    .hlstats-install-foot{ margin-top: 22px; font-size: 12.5px; opacity: .65; text-align: center; }
    .hlstats-install-footer{ padding: 0 16px 28px; font-size: 12.5px; text-align: center; color: var(--footer-color); }
    .hlstats-install-footer a{ color: var(--footer-color-a); }
    @media (max-width: 600px){
        .hlstats-install{ padding-top: 20px; }
        .hlstats-install-card{ padding: 22px 16px; }
        .hlstats-install h1{ font-size: 22px; }
        .hlstats-install-pair{ grid-template-columns: 1fr; }
    }
    </style>
</head>
<body>
<div class="hlstats-header">
  <div class="hlstats-top hlstats-install-bar">
    <div class="hlstats-inner">
      <a class="hlstats-install-brand" href="hlstats.php"><img src="hlstatsz-favicon.svg" width="40" height="40" alt=""><span>HLstats<span class="z">Z</span></span></a>
    </div>
  </div>
</div>

<main class="hlstats-install">
  <div class="hlstats-install-card">
<?php if ($done['ok'] ?? false): ?>
    <h1 class="is-ok"><?= svgIcon('check', 28) ?>HLstatsZ is installed</h1>
    <p class="hlstats-install-lead">Its database is ready. What was done:</p>
    <ul class="hlstats-install-list">
<?php foreach ($done['steps'] as $step): ?>
      <li class="is-ok"><?= $icon(true) ?><span><?= $step ?></span></li>
<?php endforeach; ?>
    </ul>
<?php if ($done['warning'] !== ''): ?>
    <div class="hlstats-install-msg is-warn"><p><?= $done['warning'] ?></p></div>
<?php endif; ?>
    <h2>Next</h2>
    <ol class="hlstats-install-next">
      <li>Point the daemon at this database: in its <code>hlstats.conf</code>, DBHost, DBName, DBUsername and DBPassword are the values of config.php. Then start it.</li>
      <li>Add your game servers: <a href="hlstats.php?mode=admin">Admin</a> &rsaquo; Game Settings &rsaquo; your game &rsaquo; Add Server. Hide the games you don't run in General Settings &rsaquo; Games.</li>
      <li>Bans (optional): set DB_SBNAME for SourceBans and DB_AMXNAME for AMXBans in config.php. Admin &rsaquo; Bans &rsaquo; Bans Settings then creates their tables if they don't exist.</li>
<?php if (!$steam): ?>
      <li>Sign in with Steam instead of a password: set STEAM_API and STEAM_ADMIN in config.php.</li>
<?php endif; ?>
    </ol>
    <div class="hlstats-install-actions">
      <a class="hlstats-btn is-primary" href="hlstats.php?mode=admin">Open the admin panel</a>
      <a class="hlstats-btn" href="hlstats.php">Go to the site</a>
    </div>
<?php if ($done['log'] !== ''): ?>
    <details>
      <summary>Log of the updates</summary>
      <div class="hlstats-install-log"><?= $done['log'] ?></div>
    </details>
<?php endif; ?>
<?php elseif ($canRun): ?>
    <h1>Install HLstatsZ</h1>
    <p class="hlstats-install-lead"><?= $inst['state'] === 'nodb'
        ? "The database $name does not exist yet. The installer creates it, then its tables."
        : "The database $name has no HLstatsZ tables yet. The installer creates them." ?>
      Then it sets the first admin and updates the database to version <?= $latest ?>.<?= $inst['others'] ? ' Its ' . $tables($inst['others'], 'other table') . ($inst['others'] === 1 ? ' is' : ' are') . ' left as ' . ($inst['others'] === 1 ? 'it is.' : 'they are.') : '' ?></p>
<?php if ($done && !$done['ok']): ?>
    <div class="hlstats-install-msg is-bad"><p><?= $done['error'] ?></p></div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="hlstats-install-msg is-bad"><?php foreach ($errors as $error): ?><p><?= $h($error) ?></p><?php endforeach; ?></div>
<?php endif; ?>

    <h2>Server</h2>
    <ul class="hlstats-install-list">
<?php foreach ($checks as $check): ?>
      <li class="<?= $check[0] === true ? 'is-ok' : ($check[0] === false ? 'is-bad' : 'is-warn') ?>"><?= $icon($check[0]) ?><span><?= $h($check[1]) ?><?= $check[2] !== '' && $check[0] !== true ? '<small>' . $h($check[2]) . '</small>' : '' ?></span></li>
<?php endforeach; ?>
    </ul>

    <form method="post" action="hlstats.php" id="hlstats-install-form" autocomplete="off">
      <input type="hidden" name="install_do" value="install">
      <input type="hidden" name="install_token" value="<?= $h($token) ?>">

      <h2>Owner check</h2>
<?php if ($proof === 'password'): ?>
      <div class="hlstats-install-field">
        <label for="install_dbpass">Database password</label>
        <input type="password" id="install_dbpass" name="install_dbpass" required autocomplete="off">
        <small>DB_PASS in config.php. Until an admin exists this page is public: the password shows it is you.</small>
      </div>
<?php elseif ($proof === 'local'): ?>
      <div class="hlstats-install-note"><?= svgIcon('shield', 16) ?><span>config.php has no database password, and this page is open on the web server itself: nothing to prove.</span></div>
<?php else: ?>
      <div class="hlstats-install-msg is-bad"><p>config.php has no database password (DB_PASS), so the installer only runs on the web server itself: open this page there, at http://localhost/&hellip; Or give the database user a password, and put it in config.php.</p></div>
<?php endif; ?>

      <h2>First admin</h2>
<?php if ($steam): ?>
      <div class="hlstats-install-note"><?= svgIcon('user', 16) ?><span>Admins sign in with Steam: STEAM_ADMIN in config.php, <?= $admins ?> account<?= $admins > 1 ? 's' : '' ?>. No password login is created.</span></div>
<?php else: ?>
      <div class="hlstats-install-field">
        <label for="install_user">Admin name</label>
        <input type="text" id="install_user" name="install_user" value="<?= $h($user) ?>" maxlength="16" required autocomplete="username" spellcheck="false">
      </div>
      <div class="hlstats-install-pair">
        <div class="hlstats-install-field">
          <label for="install_pass">Password</label>
          <input type="password" id="install_pass" name="install_pass" minlength="8" maxlength="16" required autocomplete="new-password">
        </div>
        <div class="hlstats-install-field">
          <label for="install_pass2">Password again</label>
          <input type="password" id="install_pass2" name="install_pass2" minlength="8" maxlength="16" required autocomplete="new-password">
        </div>
      </div>
      <div class="hlstats-install-field"><small>8 to 16 characters. With a Steam Web API key (STEAM_API) and your SteamID64 (STEAM_ADMIN) in config.php, admins sign in with Steam instead, with no password to keep.</small></div>
<?php endif; ?>

      <button type="submit" class="hlstats-btn is-primary hlstats-install-go"<?= $proof === '' ? ' disabled' : '' ?>><?= svgIcon('database', 16) ?>Install HLstatsZ</button>
    </form>
    <p class="hlstats-install-foot">It takes a few seconds. Nothing is kept if it fails.</p>
<?php else: ?>
    <h1 class="is-warn"><?= svgIcon('alert', 26) ?><?= $h($problems[$inst['state']][0]) ?></h1>
    <p class="hlstats-install-lead"><?= $problems[$inst['state']][1] ?></p>
<?php if ($inst['errno']): ?>
    <p class="hlstats-install-foot">MySQL error <?= (int) $inst['errno'] ?>. The site shows again as soon as its database answers.</p>
<?php endif; ?>
<?php endif; ?>
  </div>
</main>

<footer class="hlstats-install-footer">HLstats<span class="z">Z</span> &middot; <a href="https://github.com/SnipeZilla/hlstatsz-web" target="_blank" rel="noopener">github.com/SnipeZilla/hlstatsz-web</a></footer>
<script>
document.getElementById('hlstats-install-form')?.addEventListener('submit', function () {
    const go = this.querySelector('.hlstats-install-go');
    go.disabled = true;
    go.lastChild.textContent = 'Installing…';
});
</script>
</body>
</html>
