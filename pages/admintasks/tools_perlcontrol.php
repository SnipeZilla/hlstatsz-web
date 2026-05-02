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
?>
<div class="panel">
<?php

$commands[0]["name"] = "Ping UDP";
$commands[0]["cmd"] = "UDP";
$commands[1]["name"] = "Ping HTTP";
$commands[1]["cmd"] = "HTTP";
$commands[2]["name"] = "Reload Configuration";
$commands[2]["cmd"] = "RELOAD";
$commands[3]["name"] = "Shut down the Daemon *";
$commands[3]["cmd"] = "KILL";

	if (isset($_POST['confirm'])) {
		$host = $_POST['masterserver'];
		$port = $_POST["port"];
		$cmd_index = (int)$_POST["command"];
		$command = $commands[$cmd_index]["cmd"];
		if (!$command) die ('Invalid command!');
		if (!$port) $port = "27500";

		// Check if we're contacting a remote host -- if so, need proxy_key configured for this to work (die and throw an error if we're missing it)
		if (($host != "127.0.0.1") && ($host != "localhost")) 
		{
			if ($g_options['Proxy_Key'] == "") 
			{
				echo "<p><strong>⚠️ Warning:</strong> You are connecting to a remote daemon and do not have a Proxy Key configured.</p>";
				
				echo "<p>Please visit the <a href=\"{$g_options['scripturl']}?mode=admin&task=options#options\"><strong>HLstats Settings page</strong></a> and configure a Proxy Key.  Once configured, manually restart your daemon.</p>";
				die();
			}
		}
		
		echo "<div><ul>\n";      
		echo "<li>Sending Command to HLstats Daemon at $host:$port &mdash; ";
		ob_flush();
		flush();
		$host = gethostbyname($host);
		$start = microtime(true);
		$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		$packet = "";
		if ($g_options['Proxy_Key'])
		{
			$packet = "PROXY Key={$g_options['Proxy_Key']} PROXY C;".$command.";";
		}
		else
		{
			$packet = "C;".$command.";";
		}
        if ($command == "HTTP") {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://$host:$port");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $packet);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_NOBODY, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1000);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 2000);
            curl_exec($ch);
            $info = curl_getinfo($ch);
            $ch=null;
            if ($info['http_code'] > 0) {
                $latency = round((float)($info['total_time']-$info['pretransfer_time']) * 1000, 2)/2;
                echo "<li>HTTP latency: <strong> ".$latency." ms</strong></li>";
            } else {
                echo "<li>HTTP ping failed</li>";
			}
        } else {
            $start = microtime(true);
			$bytes_sent = socket_sendto($socket, $packet, strlen($packet), 0, $host, $port);
		if (!$bytes_sent) {
			echo "<li>Failed to send packet to $host:$port</li>";
		}else{
			echo "<strong>".$bytes_sent."</strong> bytes <strong>OK</strong></li>";
		}
		echo "<li>Waiting for Backend Answer...";
		ob_flush();
		flush();
		$recv_bytes = 0;
		$buffer     = "";
		$timeout    = 5;
		$answer     = "";
		$packets    = 0;
		    $read       = [$socket];
		$write = NULL;
		$except = NULL;
           	$latency    = 0;
		while (socket_select($read, $write, $except, $timeout) > 0) {
			$recv_bytes += @socket_recvfrom($socket, $buffer, 2000, 0, $host, $port);
			$answer     .= $buffer;
			$buffer     = "";
			$timeout    = "1";
                if ($latency === 0 && $recv_bytes>0) $latency = round((microtime(true) - $start) * 1000, 2)/2; // in ms
			$packets++;
		}   

		if (!$recv_bytes) {
			echo "<li><em>No bytes received from $host:$port</em></li>";
		} else {
			echo "recieving <strong>$recv_bytes</strong> bytes in <strong>$packets</strong> packets...<strong>OK</strong></li>";
		}
		ob_flush();
		flush();
	  
		if ($packets>0 && $answer) {
			echo "<li>Backend Answer: ".$answer."</li>";
                if ( $command == "UDP" ) {
                    echo "<li>UDP latency: <strong>{$latency} ms</strong></li>";
                }
		} 
		else 
		{
			echo "<li><em>No packets received &mdash; check if backend dead or not listening on $host:$port</em></li>";
		    }
		}
	  
		echo "<li>Closing connection to backend...";
		socket_close($socket);
		echo "<strong>OK</strong></li>";
		echo "</ul></div>\n";
		
		echo "&larr;&nbsp;<a href=\"?mode=admin&task=tools_perlcontrol&masterserver=".urlencode($host)."&port=".urlencode($_POST['port'])."&command=".urlencode($cmd_index)."\">Return to Daemon Control</a>";
		}
		else
		{
		$form_host    = isset($_GET['masterserver']) ? htmlspecialchars($_GET['masterserver']) : 'localhost';
		$form_port    = isset($_GET['port'])         ? htmlspecialchars($_GET['port'])         : '27500';
		$form_command = isset($_GET['command'])      ? (int)$_GET['command']                  : 0;
?>
<div class="hlstats-admin-note">
<p>After every configuration change made in the Administration Center, you should reload the daemon configuration.  To do so, enter the hostname or IP address of your HLstats daemon and choose the reload option.  You can also shut down your daemon from this panel.<br>
	 <strong>NOTE: The daemon can not be restarted through the web interface!</strong></p>
</div>
<form method="POST">

	<table>
		<tr>
			<td class="left"><label for="masterserver">Daemon IP or Hostname:</label><p>Hostname or IP address of your HLstats Daemon<br>Normally 'localhost' or IP or Hostname listed in the "logaddress_add" line on your game server.<br />example: daemon1.yoursite.com <em>or</em> 0.0.0.0</p></td>
			<td class="right"><input type="text" name="masterserver" value="<?= $form_host ?>"></td>
		</tr>
		<tr>
			<td class="left"><label for="port">Daemon Port:</label><p>Port number the daemon (or proxy_daemon) is listening on.<br>Normally the port listed in the "logaddress_add" line on your game server configuration.<br />example: 27500</p></td>
			<td class="right"><input type="text" name="port" value="<?= $form_port ?>" size="6"></td>
		</tr>
		<tr>
			<td class="left"><label for="command">Command:</label><p>Select the operation to perform on the daemon<br /><strong>* Note: If you shut the daemon down through this page it can not be restarted through this interface!</strong></p></td>
			<td class="right"><select name="command"><?php
  foreach ($commands as $i => $cmd) {
    $sel = ($i === $form_command) ? ' selected' : '';
    echo "<option value=\"$i\"$sel>" . htmlspecialchars($cmd["name"]) . "</option>";
  }
?>
					</select></td>
	</table>
	
	<input type="hidden" name="confirm" value="1">
<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit">
</div>
</form>

<?php
	}
?>    