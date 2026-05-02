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

	$edlist = new EditList('serverId', 'hlstats_Servers_VoiceComm', '', false);
	$edlist->columns[] = new EditListColumn('name', 'Server Name', 45, true, 'text', '', 64);
	$edlist->columns[] = new EditListColumn('addr', 'Address / Guild ID', 20, true, 'text', '', 64);
	$edlist->columns[] = new EditListColumn('password', 'Password', 20, false, 'text', '', 64);
	$edlist->columns[] = new EditListColumn('UDPPort', 'UDP Port (TS only)', 6, false, 'numeric', '8767', 64);
	$edlist->columns[] = new EditListColumn('queryPort', 'Query/Connect Port', 6, false, 'numeric', '51234', 64);
	$edlist->columns[] = new EditListColumn('descr', 'Notes', 40, false, 'text', '', 64);
	$edlist->columns[] = new EditListColumn('serverType', 'Server Type', 20, true, 'select', '0/Teamspeak;1/Ventrilo;2/Discord');
	echo '<div class="panel">';
	message('warning','Important: The Discord server must have Server Widget enabled (Server Settings > Engagement > Enable Server Widget) for the channel/member data to load');
	if ($_POST) {
		if ($edlist->update())
			message('success', 'Operation successful.');
		else
			message('warning', $edlist->error());
	}
	echo '</div>';
	
	$result = $db->query("
		SELECT
			serverId,
			name,
			addr,
			password,
			UDPPort,
			queryPort,
			descr,
			serverType
		FROM
			hlstats_Servers_VoiceComm
		ORDER BY
			serverType,
			name
	");
	
	$edlist->draw($result);
?>

<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit" onclick="return checkMod();">
</div>

<script>
function checkMod() {
	var errors = [];
	var rows = document.querySelectorAll('table tbody tr, table tfoot tr');
	for (var r = 0; r < rows.length; r++) {
		var row = rows[r];
		var typeSelect = row.querySelector('select[name$="_serverType"]');
		if (!typeSelect) continue;

		var prefix = typeSelect.name.replace('_serverType', '');
		var isNew = (prefix === 'new');
		var type = typeSelect.value;

		var name = row.querySelector('input[name="' + prefix + '_name"]');
		var addr = row.querySelector('input[name="' + prefix + '_addr"]');
		var queryPort = row.querySelector('input[name="' + prefix + '_queryPort"]');

		if (isNew) {
			if ((!name || !name.value.trim()) && (!addr || !addr.value.trim())) continue;
		}

		var label = isNew ? 'New row' : 'Row ' + prefix;

		if (!name || !name.value.trim()) {
			errors.push(label + ': Server Name is required');
		}
		if (!addr || !addr.value.trim()) {
			errors.push(label + ': Address / Guild ID is required');
		} else if (type === '2' && !/^\d+$/.test(addr.value.trim())) {
			errors.push(label + ': Discord Guild ID must be numeric');
		}
		if ((type === '0' || type === '1') && (!queryPort || !queryPort.value.trim())) {
			errors.push(label + ': Query/Connect Port is required for Teamspeak/Ventrilo');
		}
	}
	if (errors.length > 0) {
		alert(errors.join('\n'));
		return false;
	}
}

function toggleVoiceCommFields(selectEl) {
	var row = selectEl.closest('tr');
	if (!row) return;
	var type = selectEl.value;
	var prefix = selectEl.name.replace('_serverType', '');
	var inputs = row.querySelectorAll('input, select');
	for (var i = 0; i < inputs.length; i++) {
		var name = inputs[i].name;
		if (!name) continue;
		var field = name.replace(prefix + '_', '');
		if (field === 'password' || field === 'UDPPort' || field === 'queryPort') {
			if (type === '2') {
				inputs[i].disabled = true;
				inputs[i].style.opacity = '0.3';
			} else if (type === '1' && field === 'UDPPort') {
				inputs[i].disabled = true;
				inputs[i].style.opacity = '0.3';
			} else {
				inputs[i].disabled = false;
				inputs[i].style.opacity = '1';
			}
		}
	}
}
(function() {
	var selects = document.querySelectorAll('select[name$="_serverType"]');
	for (var i = 0; i < selects.length; i++) {
		toggleVoiceCommFields(selects[i]);
		selects[i].addEventListener('change', function() {
			toggleVoiceCommFields(this);
		});
	}
})();
</script>

