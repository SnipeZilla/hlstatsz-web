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

    function setdefaults($key)
    {
        global $db;
        // get default values
        $db->query("DELETE FROM hlstats_Servers_Config WHERE serverId=$key;");
        $db->query("INSERT INTO hlstats_Servers_Config (serverId, parameter, value) SELECT $key,parameter,value FROM hlstats_Servers_Config_Default");
        // get server ip and port
        $db->query("SELECT CONCAT(address, ':', port) AS addr FROM hlstats_Servers WHERE serverId=$key;");
        $r = $db->fetch_array();
    }
	
	if (isset($_GET['key'])) {
		$key = valid_request(intval($_GET['key']),true);
	} else {
		if (isset($_POST['key'])) {
			$key = valid_request(intval($_POST['key']),true);
		} else {
			$key = 0;
		}
	}
	
	if ($key==0)
		die('Server ID not set!');
	
	if (isset($_POST['sourceId'])) {
		$sourceId = valid_request(intval($_POST['sourceId']),true);
	} else {
		$sourceId = 0;
	}
	echo '<div class="panel">';
	message('warning','Note: For changes on this page to take effect, you <strong>must</strong> <a href="' . $g_options['scripturl'] . '?mode=admin&amp;task=tools_perlcontrol">reload</a> or restart the HLX:CE daemon.');

	// get available help texts
	$db->query("SELECT parameter,description FROM hlstats_Servers_Config_Default");
	$helptexts = array();
	while ($r = $db->fetch_array())
		$helptexts[strtolower($r['parameter'])] = $r['description'];
	
	$edlist = new EditList('serverConfigId', 'hlstats_Servers_Config','', false);
	
	$footerscript = $edlist->setHelp('helpdiv','parameter',$helptexts);

	$edlist->columns[] = new EditListColumn('serverId', 'Server ID', 0, true, 'hidden', $key);
	$edlist->columns[] = new EditListColumn('parameter', 'Server parameter name', 30, true, 'readonly', '', 50);
	$edlist->columns[] = new EditListColumn('value', 'Parameter value', 60, false, 'text', '', 128);
	
    if (!empty($_POST)) {  
		if (isset($_POST['setdefaults']) && $_POST['setdefaults']=='defaults') {
			setdefaults($key);
		}
		if (isset($_POST['sourceId']) && $_POST['sourceId']!='0') {
			// copy server settings from another server
			$db->query("DELETE FROM hlstats_Servers_Config WHERE serverId=$key");
			$db->query("INSERT INTO hlstats_Servers_Config (serverId, parameter, value) SELECT $key,parameter,value FROM hlstats_Servers_Config WHERE serverId=$sourceId");
			// get server ip and port
			$db->query("SELECT CONCAT(address, ':', port) AS addr FROM hlstats_Servers WHERE serverId=$key;");
			$r = $db->fetch_array();
		}
		if ($edlist->update()) {
			message('success', 'Operation successful.');
		} else {
			message('warning', $edlist->error());
		}
    }
?>
<p>These are the actual server parameters used by the hlstats.pl script.</p>

<?php

	$result = $db->query("
		SELECT
			*
		FROM
			hlstats_Servers_Config
		WHERE
			serverId=$key
		ORDER BY
			parameter ASC
	");
	if ($db->num_rows($result) == 0) {
		setdefaults($key);
		$result = $db->query("
			SELECT
				*
			FROM
				hlstats_Servers_Config
			WHERE
				serverId=$key
			ORDER BY
				parameter ASC
		");
	}
	
	$edlist->draw($result);

	echo $footerscript;

	// get all other server id's
	$sourceIds = '';
	$db->query("SELECT CONCAT(name,' (',address,':',port,')') AS name, serverId FROM hlstats_Servers WHERE serverId<>$key ORDER BY name, address, port");
	while ($r = $db->fetch_array())
		$sourceIds .= '<OPTION VALUE="'.$r['serverId'].'">'.$r['name'];
   
?>

<INPUT TYPE="hidden" NAME="key" VALUE="<?php echo $key ?>">

<div>
	<INPUT TYPE="checkbox" NAME="setdefaults" VALUE="defaults" style="height:auto"> Reset all settings to default!<br>
	Set all options like existing server configuration: 
  <SELECT NAME="sourceId">
	 <OPTION VALUE="0">Select a server
	 <?php echo $sourceIds; ?>
	</SELECT><br> 
<div class="hlstats-admin-apply">

  <input type="submit" value="  Apply  " class="submit">
</div>
</div>