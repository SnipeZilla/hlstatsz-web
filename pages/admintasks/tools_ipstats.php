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

	if ($auth->userdata["acclevel"] < 100) {
        die ("Access denied!");
	}

	// Connects by host group, or by host within one group. "Unresolved" is its own flag rather than an empty
	// host group: the admin panel drops empty parameters from its links.
	$unresolved = !empty($_GET['unresolved']);
	$hostgroup  = $unresolved ? '' : (isset($_GET['hostgroup']) && $_GET['hostgroup'] !== '' ? (string) $_GET['hostgroup'] : null);
	$scope      = $unresolved ? array('unresolved' => 1) : ($hostgroup !== null ? array('hostgroup' => $hostgroup) : array());
	$groupLabel = fn($group) => $group === '' ? '(Unresolved IP Addresses)' : $group;
	$taskUrl    = fn(array $params) => htmlspecialchars($g_options['scripturl'] . '?' . http_build_query(array('mode' => 'admin', 'task' => 'tools_ipstats') + $params));

    $sortorder = $_GET['sortorder'] ?? '';
    $sort      = $_GET['sort'] ?? '';
    if (!in_array($sort, array('host', 'freq'))) {
        $sort      = 'freq';
        $sortorder = 'DESC';
    }
    $sortorder = strtoupper($sortorder) === 'ASC' ? 'ASC' : 'DESC';
    $sortUrl   = fn($col) => $taskUrl($scope + array('sort' => $col, 'sortorder' => ($col === $sort && $sortorder === 'DESC') ? 'asc' : 'desc'));

    $start = isset($_GET['page']) ? max(0, ((int) $_GET['page'] - 1) * 50) : 0;

	if ($hostgroup === null) {
		$hostExpr = 'hostgroup';
		$where    = '';
	} else {
		$hostExpr = "IF(hostname = '', ipAddress, hostname)";
		$where    = "WHERE hostgroup = '" . $db->escape($hostgroup) . "'";
	}

	$db->query("SELECT COUNT(*), COUNT(DISTINCT $hostExpr) FROM hlstats_Events_Connects $where");
	list($totalconnects, $numitems) = $db->fetch_row();

	$result = $db->query("
		SELECT
			$hostExpr AS host,
			COUNT(*) AS freq
		FROM
			hlstats_Events_Connects
		$where
		GROUP BY
			host
		ORDER BY
			$sort $sortorder,
			host ASC
		LIMIT
			50 OFFSET $start
	");
?>
<div class="panel">
<div class="hlstats-admin-note">
<p>
<?php if ($hostgroup === null): ?>
	Player connects by host group<?= $g_options['DeleteDays'] > 0 ? ', over the last ' . (int) $g_options['DeleteDays'] . ' days' : '' ?>.
	Host names come from the daemon's DNS lookups: without them, every connect is unresolved.
<?php else: ?>
	<a href="<?= $taskUrl(array()) ?>">All host groups</a> &rsaquo; <b><?= htmlspecialchars($groupLabel($hostgroup)) ?></b>
<?php endif; ?>
</p>
</div>

<div class="responsive-table">
  <table class="responsive-task">
    <thead>
      <tr>
        <th class="hlstats-ranking nowrap">#</th>
        <th class="hlstats-main-column left<?= isSorted('host', $sort, $sortorder) ?>"><a href="<?= $sortUrl('host') ?>">Host</a></th>
        <th class="<?= isSorted('freq', $sort, $sortorder) ?>"><a href="<?= $sortUrl('freq') ?>">Connects</a></th>
        <th class="nowrap">Percentage of Connects</th>
      </tr>
    </thead>
    <tbody>
    <?php
        $rank = $start;
        while ($res = $db->fetch_array($result))
        {
            $rank++;
            $percent = $totalconnects > 0 ? round($res['freq'] / $totalconnects * 100, 1) : 0;
            $host    = htmlspecialchars($hostgroup === null ? $groupLabel($res['host']) : $res['host']);
            if ($hostgroup === null) {
                $host = '<a href="' . $taskUrl($res['host'] === '' ? array('unresolved' => 1) : array('hostgroup' => $res['host'])) . '">' . $host . '</a>';
            }
            echo '<tr>
                  <td class="nowrap right" data-label="#">' . $rank . '</td>
                  <td class="left" data-label="Host"><span class="hlstats-name">' . $host . '</span></td>
                  <td class="nowrap" data-label="Connects">' . nf($res['freq']) . '</td>
                  <td class="nowrap" data-label="Percentage of Connects">
                    <div class="meter-container">
                      <meter min="0" max="100" value="' . $percent . '"></meter>
                      <div class="meter-value">' . $percent . '%</div>
                    </div>
                  </td>
                  </tr>';
        }
   ?>
    </tbody>
  </table>
</div>
<?php
    echo Pagination($numitems, $_GET['page'] ?? 1, 50, 'page', false);
?>
</div>
