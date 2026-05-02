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

    function formatBytes($bytes) {
        if ($bytes === null) return '—';
        $bytes = (int)$bytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }

	echo '<div class="panel">';

    if (isset($_POST['confirm'])) {
        // Run Optimize & Analyze

        // Collect table names
        $dbtables = array();
        $result = $db->query("SHOW TABLES");
        while ($row = $db->fetch_row($result)) {
            $dbtables[] = $row[0];
        }
        $tableList = implode(', ', array_map(function($t) { return '`' . $t . '`'; }, $dbtables));

        // Optimize
        $optimizeResult = $db->query("OPTIMIZE TABLE " . $tableList);
        printSectionTitle('Optimize Results');


        // Group optimize results by table
        $optimizeGrouped = array();
        while ($row = mysqli_fetch_assoc($optimizeResult)) {
            $table = $row['Table'];
            if (!isset($optimizeGrouped[$table])) {
                $optimizeGrouped[$table] = array('Op' => $row['Op'], 'status' => '', 'note' => '');
            }
            if ($row['Msg_type'] === 'status') {
                $optimizeGrouped[$table]['status'] = $row['Msg_text'];
            } elseif ($row['Msg_type'] === 'note') {
                $optimizeGrouped[$table]['note'] = $row['Msg_text'];
            }
        }
?>
<div class="responsive-table">
<table>
<tr>
    <th class="left">Table</th>
    <th class="left">Operation</th>
    <th class="left">Status</th>
    <th class="left">Note</th>
</tr>
<?php
        foreach ($optimizeGrouped as $table => $info) {
            echo '<tr>';
            echo '<td class="left">' . htmlspecialchars($table) . '</td>';
            echo '<td class="left">' . htmlspecialchars($info['Op']) . '</td>';
            echo '<td class="left">' . htmlspecialchars($info['status']) . '</td>';
            echo '<td class="left">' . htmlspecialchars($info['note']) . '</td>';
            echo '</tr>';
        }
?>
</table>
</div>

<?php
        // Analyze
        $analyzeResult = $db->query("ANALYZE TABLE " . $tableList);
        printSectionTitle('Analyze Results');


        // Group analyze results by table
        $analyzeGrouped = array();
        while ($row = mysqli_fetch_assoc($analyzeResult)) {
            $table = $row['Table'];
            if (!isset($analyzeGrouped[$table])) {
                $analyzeGrouped[$table] = array('Op' => $row['Op'], 'status' => '', 'note' => '');
            }
            if ($row['Msg_type'] === 'status') {
                $analyzeGrouped[$table]['status'] = $row['Msg_text'];
            } elseif ($row['Msg_type'] === 'note') {
                $analyzeGrouped[$table]['note'] = $row['Msg_text'];
            }
        }
?>

<table>
<tr>
    <th class="left">Table</th>
    <th class="left">Operation</th>
    <th class="left">Status</th>
    <th class="left">Note</th>
</tr>
<?php
        foreach ($analyzeGrouped as $table => $info) {
            echo '<tr>';
            echo '<td class="left">' . htmlspecialchars($table) . '</td>';
            echo '<td class="left">' . htmlspecialchars($info['Op']) . '</td>';
            echo '<td class="left">' . htmlspecialchars($info['status']) . '</td>';
            echo '<td class="left">' . htmlspecialchars($info['note']) . '</td>';
            echo '</tr>';
        }

echo '</table>';
message('success','Optimization and analysis complete.');

echo '</div>';

    } else {
        // Show Table Status & Confirmation

        $statusResult = $db->query("SHOW TABLE STATUS");
        $tables = array();
        $totalDataSize = 0;
        $totalIndexSize = 0;
        $totalFree = 0;
        $totalRows = 0;

        while ($row = mysqli_fetch_assoc($statusResult)) {
            $tables[] = $row;
            $totalDataSize += (int)$row['Data_length'];
            $totalIndexSize += (int)$row['Index_length'];
            $totalFree += (int)$row['Data_free'];
            $totalRows += (int)$row['Rows'];
        }
?>

<form method="POST">
<div class="hlstats-admin-note">
    <p>
            This operation tells the MySQL server to clean up the database tables, optimizing and
             analyzing them for better performance.<br>
			It is recommended that you run this at least once a month.
	</p>
    <p>
        <strong>Summary:</strong>
        <?php echo count($tables); ?> tables,
        <?php echo nf($totalRows); ?> total rows,
        <?php echo formatBytes($totalDataSize); ?> data,
        <?php echo formatBytes($totalIndexSize); ?> indexes,
        <?php echo formatBytes($totalFree); ?> reclaimable (fragmentation).
    </p>
</div>
<?php
printSectionTitle('Current Table Status');
?>
<div class="responsive-table">
<table>
<tr>
    <th class="left">Table</th>
    <th class="left">Engine</th>
    <th class="right">Rows</th>
    <th class="right">Data Size</th>
    <th class="right">Index Size</th>
    <th class="right">Overhead</th>
    <th class="left">Collation</th>
</tr>
<?php
        $rowClass = 0;
        foreach ($tables as $row) {
            $overheadStyle = ((int)$row['Data_free'] > 0) ? ' style="color:#c00;"' : '';
            echo '<tr>';
            echo '<td class="left">' . htmlspecialchars($row['Name']) . '</td>';
            echo '<td class="left">' . htmlspecialchars($row['Engine'] ?? '—') . '</td>';
            echo '<td class="right">' . nf((int)$row['Rows']) . '</td>';
            echo '<td class="right">' . formatBytes($row['Data_length']) . '</td>';
            echo '<td class="right">' . formatBytes($row['Index_length']) . '</td>';
            echo '<td class="right"' . $overheadStyle . '>' . formatBytes($row['Data_free']) . '</td>';
            echo '<td class="left">' . htmlspecialchars($row['Collation'] ?? '—') . '</td>';
            echo '</tr>';
        }
?>
<tr>
    <td class="left"><strong>Total</strong></td>
    <td></td>
    <td class="right"><strong><?php echo nf($totalRows); ?></strong></td>
    <td class="right"><strong><?php echo formatBytes($totalDataSize); ?></strong></td>
    <td class="right"><strong><?php echo formatBytes($totalIndexSize); ?></strong></td>
    <td class="right"><strong><?php echo formatBytes($totalFree); ?></strong></td>
    <td></td>
</tr>
</table>
</div>

<div class="hlstats-admin-apply">
    <input type="hidden" name="confirm" value="1" />
    <input type="submit" value="  Optimize &amp; Analyze All Tables  " />
</div>
</form>
</div>
<?php
    }
?>
