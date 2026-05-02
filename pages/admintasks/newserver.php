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

	if ($auth->userdata["acclevel"] < 80) {
        die ("Access denied!");
	}
	echo '<div class="panel">';

	if ( count($_POST) > 0 ) {
		$error          = [];
		$server_address = clean_data($_POST['server_address']);
		$server_port    = clean_data($_POST['server_port']);
		$server_name    = clean_data($_POST['server_name']);
		$public_addres  = clean_data($_POST['public_address']);
		$server_rcon    = mystripslashes($_POST['server_rcon']);
		$game_mod       = mystripslashes($_POST['game_mod']);

		if (empty($server_address) || filter_var($server_address, FILTER_VALIDATE_IP) === false) {
				$error[]= "IP: ".$server_address." is not valid";
		}

		if (empty($server_port) || !is_numeric($server_port)) {
				$error[]= "PORT: ".$server_port." is not valid";
		}

		if (empty($server_port) || !is_numeric($server_port)) {
				$error[]= "PORT: ".$server_port." is not valid";
		}

		if (empty($game_mod) || $game_mod == 'PLEASESELECT') {
				$error[]= "Admin Mod is not valid";
		}

		if (empty($error)) {

			$db->query("SELECT * FROM `hlstats_Servers` WHERE `address` = '" . $db->escape(clean_data($_POST['server_address'])) . "' AND `port` = '" . $db->escape(clean_data($_POST['server_port'])) . "'");

			if ( $row = $db->fetch_array() ) {
				$error[]= "Server [" . $row['name'] . "] already exists";
			}
		}
		if (empty($error)) {

			$db->query("SELECT `realgame` FROM `hlstats_Games` WHERE `code` = '" . $db->escape($selGame) . "'");
			if ( list($game) = $db->fetch_row() )
			{
				$db->query(sprintf("INSERT INTO `hlstats_Servers` (`address`, `port`, `name`, `game`, `publicaddress`, `rcon_password`) VALUES ('%s', '%d', '%s', '%s', '%s', '%s')",
					$db->escape(clean_data($_POST['server_address'])),
					$db->escape(clean_data($_POST['server_port'])),
					$db->escape(clean_data($_POST['server_name'])),
					$db->escape($selGame),
					$db->escape(clean_data($_POST['public_address'])),
					$db->escape(mystripslashes($_POST['server_rcon']))
				));
				$insert_id = $db->insert_id();
				$db->query("INSERT INTO `hlstats_Servers_Config` (`serverId`, `parameter`, `value`)
						SELECT '" . $insert_id . "', `parameter`, `value`
						FROM `hlstats_Mods_Defaults` WHERE `code` = '" . $db->escape(mystripslashes($_POST['game_mod'])) . "';");
				$db->query("INSERT INTO `hlstats_Servers_Config` (`serverId`, `parameter`, `value`) VALUES
						('" . $insert_id . "', 'Mod', '" . $db->escape(mystripslashes($_POST['game_mod'])) . "');");
				$db->query("INSERT INTO `hlstats_Servers_Config` (`serverId`, `parameter`, `value`)
						SELECT '" . $insert_id . "', `parameter`, `value`
						FROM `hlstats_Games_Defaults` WHERE `code` = '" . $db->escape($game) . "'
						ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);");
				$db->query("UPDATE hlstats_Servers_Config
							SET `value` = '" . $db->escape($script_path) . "'
							WHERE serverId = '" . $insert_id . "' AND `parameter` = 'HLStatsURL'");
				$_POST = array();
				
				// psychonic - worst. redirect. ever.
				//   but we can't just use header() since admin.php already started part of the page and hacking it in before would be even messier
				echo "<script type=\"text/javascript\"> window.location.href=\"".$g_options['scripturl']."?mode=admin&game=$selGame&task=serversettings&key=$insert_id#startsettings\"; </script>";
				exit;
			}
		} else {
			message("warning", implode(" - ", $error));
		}
	}
	
	function clean_data($data)
	{
		return trim(htmlspecialchars(mystripslashes($data)));
	}

    $server_ip = (!empty($_POST['server_address'])) ? clean_data($_POST['server_address']) : "";
    $server_port = (!empty($_POST['server_port'])) ? clean_data($_POST['server_port']) : "" ;
    $server_name = (!empty($_POST['server_name'])) ? clean_data($_POST['server_name']) : "";
    $server_rcon = (!empty($_POST['server_rcon'])) ? clean_data($_POST['server_rcon']) : "";
    $server_public_address = (!empty($_POST['public_address'])) ? clean_data($_POST['public_address']) : "";
?>
<div class="hlstats-admin-note">
<p>
Enter the address of a server that you want to accept data from.
</p>
<p>
The "Public Address" should be the address you want shown to users. If left blank, it will be generated from the IP Address and Port. If you are using any kind of log relaying utility (i.e. hlstats.pl will not be receiving data directly from the game servers), you will want to set the IP Address and Port to the address of the log relay program, and set the Public Address to the real address of the game server. You will need a separate log relay for each game server. You can specify a hostname (or anything at all) in the Public Address.
</p>
</div>
		<table class="responsive-task">
			<tr>
				<td class="left">Server IP Address</td>
				<td class="left"><input type="text" name="server_address" maxlength="15" size="15" value="<?=$server_ip;?>" /></td>
			</tr>
			<tr>
				<td class="left">Server Port</td>
				<td class="left"><input type="text" name="server_port" maxlength="5" size="5" value="<?=$server_port;?>" /></td>
			</tr>
			<tr>
				<td class="left">Server Name</td>
				<td class="left"><input type="text" name="server_name" maxlength="255" size="35" value="<?=$server_name;?>" /></td>
			</tr>
			<tr>
				<td class="left">Rcon Password</td>
				<td class="left"><input type="text" name="server_rcon" maxlength="128" size="15" value="<?=$server_rcon;?>" /></td>
			</tr>
			<tr>
				<td class="left">Public Address</td>
				<td class="left"><input type="text" name="public_address" maxlength="128" size="15" value="<?=$server_public_address;?>" /></td>
			</tr>
			<tr>
				<td class="left">Admin Mod</td>
				<td class="left">
					<select name="game_mod">
					<option value="PLEASESELECT">PLEASE SELECT</option>
					<?php
                        $db->query("SELECT code, name FROM `hlstats_Mods_Supported`");

                        while ($row = $db->fetch_array()) {
                            echo '<option value="' . $row['code'] . '">' . $row['name'] . '</option>';
                        }
					?>
					</select>
				</td>
			</tr>
		</table>

<div class="hlstats-admin-apply">
  <input type="submit" value="Apply">
</div>
</div>
