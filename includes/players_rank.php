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
if (!defined('IN_HLSTATS')) { die('Do not access this file directly'); }

/*
Player rankings with the true rank, RANK() OVER (ORDER BY skill DESC, kills DESC) (kills DESC, deaths ASC when the
ranking type is kills), without ranking every player of the game for each page.

Global ranking: a page reads its players on the ranking index, then gets their rank by counting the players ranked
above them on that same index. Sorted by rank, the ranks follow from the page's place: a count is needed only when a
tie runs over from the previous page. The number of players in the ranking is counted once a minute.

Date rankings sum the history first, so they still rank with RANK() OVER, but on narrow rows: names and ids are read
for the players of the page only.

A rank's players (rankinfo.php): a small rank is ranked whole, a big one like the global ranking.

A clan's members (claninfo): the page is read from the clan's members alone, then the ranks of its players are counted
like any page of the global ranking.

Indexes (updater/93.php):
    ranking       (game, hideranking, skill, kills, connection_time, playerId, lastAddress)
    ranking_kills (game, hideranking, kills, deaths DESC, connection_time, playerId, lastAddress)
*/

// The ranking: [first column, second column, order of the second, its index]
function rankColumns(): array
{
    global $g_options;
    return ($g_options['rankingtype'] ?? '') === 'kills'
        ? ['kills', 'deaths', 'ASC', 'ranking_kills']
        : ['skill', 'kills', 'DESC', 'ranking'];
}

// ORDER BY of a page: by rank, else by the column then the rank's first column; the id keeps the order of equal
// players the same from page to page. By rank, it is the order of the ranking index read backward. $p prefixes the
// columns.
function rankOrder(string $sort, string $sortorder, string $p = ''): string
{
    [$r1, $r2, $dir2] = rankColumns();
    if ($sort === 'rank_position') {
        return $sortorder === 'ASC'
            ? "$p$r1 DESC, $p$r2 $dir2, {$p}connection_time DESC, {$p}playerId DESC"
            : "$p$r1 ASC, $p$r2 " . ($dir2 === 'DESC' ? 'ASC' : 'DESC') . ", {$p}connection_time DESC, {$p}playerId DESC";
    }
    $column = [
        'kpd' => "ROUND(IF({$p}deaths=0, 0, {$p}kills/{$p}deaths), 2)",
        'hpk' => "ROUND(IF({$p}kills=0, 0, {$p}headshots/{$p}kills), 2)",
        'acc' => "ROUND(IF({$p}shots=0, 0, {$p}hits/{$p}shots), 3)",
    ][$sort] ?? $p . $sort;
    return "$column $sortorder, $p$r1 DESC, {$p}connection_time DESC, {$p}playerId DESC";
}

// SQL: the players ranked above the rank [first, second] ($above), or not above it. $cols: the ranking's columns,
// [first, second, order of the second], rankColumns() by default.
function rankAbove(array $key, bool $above = true, ?array $cols = null): string
{
    [$r1, $r2, $dir2] = $cols ?? rankColumns();
    $v1 = (int) $key[0];
    $v2 = (int) $key[1];
    if ($above) {
        return "($r1 > $v1 OR ($r1 = $v1 AND $r2 " . ($dir2 === 'DESC' ? '>' : '<') . " $v2))";
    }
    return "($r1 < $v1 OR ($r1 = $v1 AND $r2 " . ($dir2 === 'DESC' ? '<=' : '>=') . " $v2))";
}

// The ranking index ($index, by default the one of the ranking type), named for a query: without the name MySQL
// picks the game index, or the other ranking index. Nothing until the updater has added it.
function rankIndex(?string $index = null): string
{
    global $db;
    static $hints = [];
    $index ??= rankColumns()[3];
    if (!isset($hints[$index])) {
        $db->query("SHOW INDEX FROM hlstats_Players WHERE Key_name = '$index'");
        $hints[$index] = $db->num_rows() ? "FORCE INDEX ($index)" : '';
    }
    return $hints[$index];
}

// The date index of the history, named for a query on days: its rows are written day after day, so the days of a
// view are read together. Without the name MySQL reads the history in player order to sum it, every row of it.
function historyIndex(): string
{
    global $db;
    static $hint = null;
    if ($hint === null) {
        $db->query("SHOW INDEX FROM hlstats_Players_History WHERE Key_name = 'eventTime'");
        $hint = $db->num_rows() ? 'FORCE INDEX (eventTime)' : '';
    }
    return $hint;
}

// SQL: the players in the ranking of a game
function rankWhere(string $game): string
{
    global $db;
    return "game = '" . $db->escape($game) . "' AND hideranking = 0 AND lastAddress <> ''";
}

// [players in the ranking, rows of the players table], counted once a minute on the index $index (by default the one
// of the ranking type)
function rankTotals(string $where, ?string $index = null): array
{
    global $db;
    $index ??= rankColumns()[3];
    $file = './cache/hlstatsz_rank_' . md5($where . $index) . '.json';
    if (is_file($file) && time() - filemtime($file) < 60) {
        $saved = json_decode((string) @file_get_contents($file), true);
        if (isset($saved[0], $saved[1])) {
            return [(int) $saved[0], (int) $saved[1]];
        }
    }

    $db->query("SELECT COUNT(*) FROM hlstats_Players " . rankIndex($index) . " WHERE $where");
    list($total) = $db->fetch_row();
    $db->query("SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hlstats_Players'");
    list($rows) = $db->fetch_row() ?: array(0);
    $totals = [(int) $total, max((int) $total, (int) $rows)];

    if (!is_dir('./cache')) @mkdir('./cache', 0755, true);
    @file_put_contents($file, json_encode($totals), LOCK_EX);
    return $totals;
}

// The rank of a player: 1 + the players ranked above him ($player: his row, with the rank columns)
function rankOfPlayer(string $game, array $player): int
{
    global $db;
    [$r1, $r2] = rankColumns();
    $db->query("SELECT COUNT(*) + 1 FROM hlstats_Players " . rankIndex() . " WHERE " . rankWhere($game) . " AND " . rankAbove([$player[$r1], $player[$r2]]));
    list($rank) = $db->fetch_row();
    return (int) $rank;
}

// The ranks of a page of players among the players of $where: [playerId => rank]. $list: the page, in its order, each
// with playerId and the ranking's columns ($cols, rankColumns() by default); $use: the index hint for the counts.
// Sorted by rank ($walk), a player's rank is his place ($start: the page's offset), unless he ties the player before
// him ($prev: the last player of the previous page, null on the first page). In any other order, the ranks of the
// page, best first, each count the players between them and the rank before: together one pass down the index, to
// the lowest rank of the page.
function rankList(array $list, ?array $prev, int $start, bool $walk, string $where, string $use, ?array $cols = null): array
{
    global $db;
    [$r1, $r2, $dir2] = $cols ?? rankColumns();
    $key  = static fn(array $r): array => [(int) $r[$r1], (int) $r[$r2]];
    $rank = [];
    if (!$list) {
        return $rank;
    }

    if ($walk) {
        $first = $key($list[0]);
        if ($prev === null || $key($prev) !== $first) {
            $at = $start + 1;
        } else {
            $db->query("SELECT COUNT(*) FROM hlstats_Players $use WHERE $where AND " . rankAbove($first, true, $cols));
            list($above) = $db->fetch_row();
            $at = (int) $above + 1;
        }
        foreach ($list as $i => $r) {
            if ($i > 0 && $key($r) !== $key($list[$i - 1])) {
                $at = $start + $i + 1;
            }
            $rank[$r['playerId']] = $at;
        }
        return $rank;
    }

    $keys = [];
    foreach ($list as $r) {
        $keys[implode(':', $key($r))] = $key($r);
    }
    uasort($keys, static fn(array $a, array $b): int =>
        ($b[0] <=> $a[0]) ?: ($dir2 === 'DESC' ? $b[1] <=> $a[1] : $a[1] <=> $b[1]));

    $counts = [];
    $higher = null;
    foreach ($keys as $k) {
        $counts[] = "(SELECT COUNT(*) FROM hlstats_Players $use WHERE $where AND "
                  . rankAbove($k, true, $cols) . ($higher ? ' AND ' . rankAbove($higher, false, $cols) : '') . ')';
        $higher = $k;
    }
    $db->query('SELECT ' . implode(', ', $counts));
    $count  = $db->fetch_row();
    $above  = 0;
    $rankOf = [];
    foreach (array_keys($keys) as $i => $k) {
        $above += (int) $count[$i];
        $rankOf[$k] = $above + 1;
    }
    foreach ($list as $r) {
        $rank[$r['playerId']] = $rankOf[implode(':', $key($r))];
    }
    return $rank;
}

// A page of the global ranking: ['rows' => the players with their rank_position, 'total' => players in the ranking]
function rankPlayers(string $game, string $sort, string $sortorder, int $page, int $perpage = 30): array
{
    global $db;
    [$r1, $r2, $dir2] = rankColumns();
    $where = rankWhere($game);
    [$total, $tableRows] = rankTotals($where);
    $use   = rankIndex();
    $start = max(0, $page - 1) * $perpage;

    // By rank, a page is the ranking index read in order, with the last player of the previous page: a tie running
    // over from it shares its rank
    $walk   = $sort === 'rank_position' && $sortorder === 'ASC';
    $before = $walk && $start > 0 ? 1 : 0;

    // The ranking index holds the rank, the time and the filter: sorted by those, the page is read on the index
    // alone. By another column the players themselves are read: the whole table when the game has most of it (one
    // scan beats a lookup per player), else through the index.
    $indexed = in_array($sort, ['rank_position', $r1, $r2, 'connection_time'], true);
    $hint    = !$indexed && $total * 3 > $tableRows ? 'USE INDEX ()' : $use;

    // The page, its ranks and its details are read from the same snapshot
    $db->query("START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY");
    $list = $db->fetch_row_set($db->query("
        SELECT playerId, $r1, $r2
        FROM hlstats_Players $hint
        WHERE $where
        ORDER BY " . rankOrder($sort, $sortorder) . "
        LIMIT " . ($perpage + $before) . " OFFSET " . ($start - $before)
    )) ?: [];
    $prev = $before && $list ? array_shift($list) : null;

    $rows = [];
    if ($list) {
        $rank = rankList($list, $prev, $start, $walk, $where, $use);

        $ids = implode(',', array_map('intval', array_column($list, 'playerId')));
        $result = $db->query("
            SELECT p.playerId, p.last_event, p.connection_time, p.lastName, p.flag, p.country,
                p.kills, p.deaths, p.skill, p.shots, p.hits, p.headshots, p.last_skill_change,
                p.kill_streak, p.death_streak, p.activity,
                (SELECT u.uniqueId FROM hlstats_PlayerUniqueIds u WHERE u.playerId = p.playerId LIMIT 1) AS uniqueId,
                ROUND(IF(p.deaths=0, 0, p.kills/p.deaths), 2) AS kpd,
                ROUND(IF(p.kills=0, 0, p.headshots/p.kills), 2) AS hpk,
                ROUND(IF(p.shots=0, 0, p.hits/p.shots), 3) AS acc
            FROM hlstats_Players p
            WHERE p.playerId IN ($ids)
        ");
        $details = [];
        while ($d = $db->fetch_array($result)) {
            $details[$d['playerId']] = $d;
        }
        foreach ($list as $r) {
            if (isset($details[$r['playerId']])) {
                $rows[] = $details[$r['playerId']] + ['rank_position' => $rank[$r['playerId']]];
            }
        }
    }
    $db->query("COMMIT");

    return ['rows' => $rows, 'total' => $total];
}

// A page of a rank's players (rankinfo.php): kills from $minKills to $maxKills, not hidden, ranked among themselves by
// RANK() OVER (ORDER BY skill DESC, kills DESC). Sorted by rank_position, skill, kills or playerName, equal players by
// name. ['rows' => playerId, skill, kills, flag, playerName, rank_position; 'total' => players of the rank]
function rankInfoPlayers(string $game, int $minKills, int $maxKills, string $sort, string $sortorder, int $page, int $perpage = 30): array
{
    global $db;
    // hideranking <> 1, written so that the ranges of skill and kills hold for each value of it on the indexes
    $where = "game = '" . $db->escape($game) . "' AND (hideranking IN (0, 2) OR hideranking > 2) AND kills BETWEEN $minKills AND $maxKills";
    [$total, $tableRows] = rankTotals($where, 'ranking_kills');
    $start = max(0, $page - 1) * $perpage;
    $by = [
        'rank_position' => $sortorder === 'ASC' ? 'skill DESC, kills DESC' : 'skill ASC, kills ASC',
        'skill'         => "skill $sortorder, kills $sortorder",
        'kills'         => "kills $sortorder, skill $sortorder",
        'playerName'    => "lastName $sortorder, kills $sortorder",
    ][$sort] . ', lastName ASC, playerId';

    $db->query("START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY");

    // Up to 20000 players, the rank is ranked whole, read through the kills index: quicker than counting each page's
    // ranks against all the players of the game
    if ($total <= 20000) {
        $list = $db->fetch_row_set($db->query("
            SELECT playerId, skill, kills, flag, lastName AS playerName, rank_position
            FROM (
                SELECT playerId, skill, kills, flag, lastName,
                    RANK() OVER (ORDER BY skill DESC, kills DESC) AS rank_position
                FROM hlstats_Players " . rankIndex('ranking_kills') . "
                WHERE $where
            ) AS r
            ORDER BY $by
            LIMIT $perpage OFFSET $start
        ")) ?: [];
        $db->query("COMMIT");
        return ['rows' => $list, 'total' => $total];
    }

    // Bigger ranks: equal players are sorted by name, so the page is read with the names, in one scan of the table when
    // the rank has most of it, else through the kills index; then the ranks are counted like rankPlayers() does
    $walk   = $sort === 'rank_position' && $sortorder === 'ASC';
    $before = $walk && $start > 0 ? 1 : 0;
    $hint   = $total * 3 > $tableRows ? 'USE INDEX ()' : rankIndex('ranking_kills');
    $list = $db->fetch_row_set($db->query("
        SELECT playerId, skill, kills, flag, lastName AS playerName
        FROM hlstats_Players $hint
        WHERE $where
        ORDER BY $by
        LIMIT " . ($perpage + $before) . " OFFSET " . ($start - $before)
    )) ?: [];
    $prev = $before && $list ? array_shift($list) : null;
    $rank = rankList($list, $prev, $start, $walk, $where, rankIndex('ranking'), ['skill', 'kills', 'DESC']);
    $db->query("COMMIT");

    foreach ($list as $i => $r) {
        $list[$i]['rank_position'] = $rank[$r['playerId']];
    }
    return ['rows' => $list, 'total' => $total];
}

// A page of a clan's members in the ranking, each with his rank_position among all the players of the game:
// ['rows' => the members, 'total' => members in the ranking]. $sort: rank_position, lastName, skill, kills, percent
// (his share of $clanKills, the clan's kills), deaths, kpd, activity or connection_time; members equal on it are sorted
// by the ranking's second column (its first when sorted by the second), then by id.
function rankClanMembers(string $game, int $clan, int $clanKills, string $sort, string $sortorder, int $page, int $perpage = 15): array
{
    global $db;
    [$r1, $r2] = rankColumns();
    $where = rankWhere($game);
    $start = max(0, $page - 1) * $perpage;
    $by    = $sort === 'rank_position'
        ? rankOrder($sort, $sortorder)
        : "$sort $sortorder, " . ($sort === $r2 ? $r1 : $r2) . " $sortorder, playerId DESC";

    // The page, its ranks and its details are read from the same snapshot
    $db->query("START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY");
    $list = $db->fetch_row_set($db->query("
        SELECT playerId, lastName, country, flag, skill, connection_time, kills, deaths, clan, last_event,
            last_skill_change, activity,
            ROUND(IF(deaths=0, 0, kills/deaths), 2) AS kpd,
            ROUND(kills / IF($clanKills = 0, 1, $clanKills) * 100, 2) AS percent,
            COUNT(*) OVER () AS total_rows
        FROM hlstats_Players " . rankIndex('playerclan') . "
        WHERE clan = $clan AND $where
        ORDER BY $by
        LIMIT $perpage OFFSET $start
    ")) ?: [];

    $rank = rankList($list, null, 0, false, $where, rankIndex());
    $db->query("COMMIT");

    foreach ($list as $i => $r) {
        $list[$i]['rank_position'] = $rank[$r['playerId']];
    }
    return ['rows' => $list, 'total' => $list ? (int) $list[0]['total_rows'] : 0];
}

// A page of a date ranking: the history summed over $dateFilter (on h.eventTime). Same result as rankPlayers().
function rankPlayersHistory(string $game, string $sort, string $sortorder, int $page, string $dateFilter, int $perpage = 30): array
{
    global $db;
    [$r1, $r2, $dir2] = rankColumns();
    $g     = $db->escape($game);
    $start = max(0, $page - 1) * $perpage;
    $name  = $sort === 'lastName' ? ', p.lastName' : '';

    $result = $db->query("
        WITH Aggregated AS (
            SELECT h.playerId,
                SUM(h.connection_time) AS connection_time, SUM(h.kills) AS kills, SUM(h.deaths) AS deaths,
                SUM(h.skill_change) AS skill, SUM(h.shots) AS shots, SUM(h.hits) AS hits,
                SUM(h.headshots) AS headshots, MAX(h.kill_streak) AS kill_streak, MAX(h.death_streak) AS death_streak
            FROM hlstats_Players_History h " . historyIndex() . "
            WHERE $dateFilter
            AND h.game = '$g'
            GROUP BY h.playerId
        ),
        Ranked AS (
            SELECT a.*, p.activity$name,
                RANK() OVER (ORDER BY a.$r1 DESC, a.$r2 $dir2) AS rank_position,
                COUNT(*) OVER () AS total_rows
            FROM Aggregated a
            JOIN hlstats_Players p ON p.playerId = a.playerId
            WHERE p.hideranking = 0
            AND p.lastAddress <> ''
        )
        SELECT r.playerId, r.connection_time, r.kills, r.deaths, r.skill, r.shots, r.hits, r.headshots,
            r.kill_streak, r.death_streak, r.activity, r.rank_position, r.total_rows,
            p.last_event, p.lastName, p.flag, p.country, p.last_skill_change,
            (SELECT u.uniqueId FROM hlstats_PlayerUniqueIds u WHERE u.playerId = r.playerId LIMIT 1) AS uniqueId,
            ROUND(IF(r.deaths=0, 0, r.kills/r.deaths), 2) AS kpd,
            ROUND(IF(r.kills=0, 0, r.headshots/r.kills), 2) AS hpk,
            ROUND(IF(r.shots=0, 0, r.hits/r.shots), 3) AS acc
        FROM (
            SELECT * FROM Ranked
            ORDER BY " . rankOrder($sort, $sortorder) . "
            LIMIT $perpage OFFSET $start
        ) AS r
        JOIN hlstats_Players p ON p.playerId = r.playerId
        ORDER BY " . rankOrder($sort, $sortorder, 'r.')
    );
    $rows = $db->fetch_row_set($result) ?: [];

    return ['rows' => $rows, 'total' => $rows ? (int) $rows[0]['total_rows'] : 0];
}
