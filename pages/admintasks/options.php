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

?>
	<div class="hlstats-admin-table-wrap panel">
<?php
message("warning","Options with an asterisk (*) beside them require a restart of the perl daemon to fully take effect.");
	class OptionGroup
	{
		var $title = '';
		var $options = array();

		function __construct($title)
		{
			$this->title = $title;
		}

		function draw ()
		{
			global $g_options;
?>

    <?php printSectionTitle($this->title); ?>
	<table>
		<?php
			foreach ($this->options as $opt)
			{
				$opt->draw();
			}
?>
	</table>
<?php
		}
		
		function update ()
		{
			global $db;
			
			foreach ($this->options as $opt)
			{
				if (($this->title == 'Fonts') || ($this->title == 'General') || ($this->title == 'Footer Links')) {
					$optval = $_POST[$opt->name];
					$search_pattern  = array('/script/i', '/;/', '/%/');
					$replace_pattern = array('', '', '');
					$optval = preg_replace($search_pattern, $replace_pattern, $optval);
				} else {
					$optval = valid_request($_POST[$opt->name] ?? '', false);
 	 			}
				
				$result = $db->query("
					SELECT
						value
					FROM
						hlstats_Options
					WHERE
						keyname='$opt->name'
				");
				
				if ($db->num_rows($result) == 1)
				{
					$result = $db->query("
						UPDATE
							hlstats_Options
						SET
							value='$optval'
						WHERE
							keyname='$opt->name'
					");
				}
				else
				{
					$result = $db->query("
						INSERT INTO
							hlstats_Options
							(
								keyname,
								value
							)
						VALUES
						(
							'$opt->name',
							'$optval'
						)
					");
				}
			}
		}
	}

	class Option
	{
		var $name;
		var $title;
		var $type;

		function __construct($name, $title, $type)
		{
			$this->name = $name;
			$this->title = $title;
			$this->type = $type;
		}

		function draw()
		{
			global $g_options, $optiondata, $db;
			
?>
					<tr>
						<td class="hlstats-main-task left">
                        <?= $this->title ?>
						</td>
						<td class="right"><?php
			switch ($this->type)
			{
				case 'textarea':
					echo "<textarea name=\"$this->name\" cols=\"35\" rows=\"4\" wrap=\"virtual\">";
					echo html_entity_decode($optiondata[$this->name]);
					echo '</textarea>';
					break;
					
				case 'styles':
					echo "<select name=\"$this->name\" style=\"width: 100%\">";
					$d = dir('styles/themes');
                      if ( "default" == $g_options['style'] ) {
                         echo "<option value=\"default\" selected=\"selected\">Default</option>";
                      } else {
                         echo "<option value=\"default\" >Default</option>";
                      }
					while (false !== ($e = $d->read()))  {
						if ($e === '.' || $e === '..' || $e === 'disabled') continue;
						if (is_dir("styles/themes/$e") && is_file("styles/themes/$e/$e.css")) {
							$ename = ucwords(strtolower(str_replace('_', ' ', $e)));
							$sel = ($e == $g_options['style']) ? ' selected="selected"' : '';
							echo "<option value=\"$e\"$sel>$ename</option>";
						}
					}
					$d->close();
					echo '</select>';
					break;
				
				case 'select':
					echo "<select name=\"$this->name\">";
					$result = $db->query("SELECT `value`,`text` FROM hlstats_Options_Choices WHERE keyname='$this->name' ORDER BY isDefault desc");
					while ($rowdata = $db->fetch_array($result)) {
						if ($rowdata['value'] == $optiondata[$this->name]) {
							echo '<option value="'.$rowdata['value'].'" selected="selected">'.$rowdata['text'];
						} else {
							echo '<option value="'.$rowdata['value'].'">'.$rowdata['text'];
						}
					}
					echo '</select>';
					break;
					
				default:
					echo "<input type=\"text\" name=\"$this->name\" value=\"";
					echo html_entity_decode($optiondata[$this->name]);
					echo '" maxlength="255" />';
			}
						?></td>
					</tr>
<?php
		}
	}

	$optiongroups = array();

	$optiongroups[0] = new OptionGroup('Site Settings');
	$optiongroups[0]->options[] = new Option('sitename', 'Site Name', 'text');
	$optiongroups[0]->options[] = new Option('siteurl', 'Site URL', 'text');
	$optiongroups[0]->options[] = new Option('nav_globalchat', 'Show Chat nav-link', 'select');
	$optiongroups[0]->options[] = new Option('nav_cheaters', 'Show Banned Players nav-link', 'select');
	$optiongroups[0]->options[] = new Option('sourcebans_address', 'SourceBans URL<br />Enter the relative or full path to your SourceBans web site, if you have one. Ex: http://www.yoursite.com/sourcebans/ or /sourcebans/', 'text');
	$optiongroups[0]->options[] = new Option('forum_address', 'Forum URL<br />Enter the relative or full path to your forum/message board, if you have one. Ex: http://www.yoursite.com/forum/ or /forum/', 'text');
	$optiongroups[0]->options[] = new Option('sigbackground', 'Default background for forum signature(Numbers 1-11 or random)<br />Look in sig folder to see background choices', 'text');
	$optiongroups[0]->options[] = new Option('map_dlurl', 'Map Download URL<br /><span class="hlstats-name">%GAME%</span> = gamecode (optional sub folder).<br>https://yoursite.com/fastdl/%GAME%/ &rarr; https://yoursite.com/fastdl/tf2/<br> Leave blank to suppress download link.', 'text');
	
	$optiongroups[30] = new OptionGroup('Visual style settings');
	$optiongroups[30]->options[] = new Option('style', 'Stylesheet filename to use', 'styles');
	$optiongroups[30]->options[] = new Option('display_style_selector', 'Display Style Selector?<br />Allow end users to change the style they are using.', 'select');
	$optiongroups[30]->options[] = new Option('bannerdisplay', 'Show Banner', 'select');
	$optiongroups[30]->options[] = new Option('bannerfile', 'Banner file name (in hlstatsimg/) or full banner URL', 'text');
	$optiongroups[30]->options[] = new Option('display_gamelist', 'Enable the game list navigation from the sub-menu navigation.', 'select');
	$optiongroups[30]->options[] = new Option('show_server_load_image', 'Show load summaries from all monitored servers', 'select');
	$optiongroups[30]->options[] = new Option('slider', 'Collapse server for each game (only affects games with more than one server)', 'select');
	$optiongroups[30]->options[] = new Option('gamehome_show_awards', 'Show daily award winners on Game Frontpage', 'select');

	$optiongroups[31] = new OptionGroup('Footer Links');
	$optiongroups[31]->options[] = new Option('footer_link1_label', 'Link 1 &rarr; Label', 'text');
	$optiongroups[31]->options[] = new Option('footer_link1_url',   'Link 1 &rarr; URL', 'text');
	$optiongroups[31]->options[] = new Option('footer_link2_label', 'Link 2 &rarr; Label', 'text');
	$optiongroups[31]->options[] = new Option('footer_link2_url',   'Link 2 &rarr; URL', 'text');
	$optiongroups[31]->options[] = new Option('footer_link3_label', 'Link 3 &rarr; Label', 'text');
	$optiongroups[31]->options[] = new Option('footer_link3_url',   'Link 3 &rarr; URL', 'text');

	$optiongroups[35] = new OptionGroup('GeoIP data & OpenStreetMap settings');
	$optiongroups[35]->options[] = new Option('show_google_map', 'Show World Map (OpenStreetMap)', 'select');
	$optiongroups[35]->options[] = new Option('countrydata', 'Show features (flag and country) requiring GeoIP data', 'select');
	$optiongroups[35]->options[] = new Option('UseGeoIPBinary', '<strong>*GeoCity2-Lite from binary file (newest)</strong> or GeoCity-Lite from mysql database(<strong>!!!deprecated!!!</strong>).<br>For binary, GeoLite2-City.dat goes in perl/GeoLiteCity and Geo::IP::PurePerl module is required', 'select');
	
	$optiongroups[40] = new OptionGroup('Daemon Settings');
	$optiongroups[40]->options[] = new Option('Mode', '*Sets the player-tracking mode.<br><ul><LI><b>Steam ID</b>     - Recommended for public Internet server use. Players will be tracked by Steam ID.<LI><b>Player Name</b>  - Useful for shared-PC environments, such as Internet cafes, etc. Players will be tracked by nickname. <LI><b>IP Address</b>        - Useful for LAN servers where players do not have a real Steam ID. Players will be tracked by IP Address. </UL>', 'select');
	$optiongroups[40]->options[] = new Option('AllowOnlyConfigServers', '*Allow only servers set up in admin panel to be tracked. Other servers will NOT automatically added and tracked! This is a big security thing', 'select');
	$optiongroups[40]->options[] = new Option('MinActivity', "&rarr; hlstats-awards.pl<br>HLstats will show last player meter activity on the server.<br>No data will be deleted. This is only a nice visual indication (column 'Activity').<br>Default 28 days.", 'text');
	$optiongroups[40]->options[] = new Option('DeleteDays', '&rarr; hlstats-awards.pl<br>HLstats automatically removes older events, keeping only each player\'s most recent days of activity. This is important for performance reasons and common sense.<br>Recommended: 365 - 730 days<br/>🚨 &rarr; Setting to <strong>0</strong> will never delete any events with <a href="https://github.com/SnipeZilla/HLSTATS-2" target="_blank">HLstatsZ</a> ≥ 2.5.4<br>💡 &rarr; <a href="?mode=admin&task=tools_reset"><strong>Full / Partial Reset</strong></a> allows you to reset any events table', 'text');
	$optiongroups[40]->options[] = new Option('Rcon', '*Allow HLstats to send Rcon commands to the game servers', 'select');
	$optiongroups[40]->options[] = new Option('RconIgnoreSelf', '*Ignore (do not log) Rcon commands originating from the same IP as the server being rcon-ed (useful if you run any kind of monitoring script which polls the server regularly by rcon)<br>&rarr; BindIP in hlstats.conf', 'select');
	$optiongroups[40]->options[] = new Option('RconRecord', '*Record Rcon commands to the Admin event table. This can be useful to see what your admins are doing, but if you run programs like PB <br>🚨 &rarr; It will fill your database up with a lot of useless junk', 'select');
	$optiongroups[40]->options[] = new Option('UseTimestamp', '*If no (default), use the current time on the database server for the timestamp when recording events. If yes, use the timestamp provided on the log data.<br>Unless you are processing old log files on STDIN or your game server is in a different timezone than webhost, you probably want to set this to no.', 'select');
	$optiongroups[40]->options[] = new Option('TrackStatsTrend', '*Save how many players, kills etc, are in the database each day and give access to graphical statistics', 'select');
	$optiongroups[40]->options[] = new Option('GlobalBanning', '*Make player bans available on all participating servers. Players who were banned permanently are automatic hidden from rankings', 'select');
	$optiongroups[40]->options[] = new Option('LogChat', '*Log player chat to database', 'select');
	$optiongroups[40]->options[] = new Option('LogChatAdmins', '*Log admin chat to database', 'select');
	$optiongroups[40]->options[] = new Option('GlobalChat', '*Broadcast chat messages through all particapting servers. To all, none, or admins only', 'select');

	$optiongroups[50] = new OptionGroup('Ranking & Point calculation settings');
	$optiongroups[50]->options[] = new Option('rankingtype', '*Ranking type', 'select');
	$optiongroups[50]->options[] = new Option('SkillMaxChange', '*Maximum number of skill points a player will gain from each frag. Default 25', 'text');
	$optiongroups[50]->options[] = new Option('SkillMinChange', '*Minimum number of skill points a player will gain from each frag. Default 2', 'text');
	$optiongroups[50]->options[] = new Option('PlayerMinKills', '*Number of kills a player must have before receiving regular points. (Before this threshold is reached, the killer and victim will only gain/lose the minimum point value) Default 50', 'text');
	$optiongroups[50]->options[] = new Option('SkillRatioCap', '*Cap killer\'s gained skill with ratio using *XYZ*SaYnt\'s method "designed such that an excellent player will have to get about a 2:1 ratio against noobs to hold steady in points"', 'select');

	$optiongroups[60] = new OptionGroup('Proxy Settings');
	$optiongroups[60]->options[] = new Option('Proxy_Key', '*Key to use when sending remote commands to Daemon, empty for disable', 'text');
	$optiongroups[60]->options[] = new Option('Proxy_Daemons', '*List of daemons to send PROXY events from (used by <b>proxy-daemon.pl</b>), use "," as delimiter, eg &lt;ip&gt;:&lt;port&gt;,&lt;ip&gt;:&lt;port&gt;,... ', 'text');
    
	if (!empty($_POST))
	{
			foreach ($optiongroups as $og)
			{
				$og->update();
			}
			message('success', 'Options updated successfully.');
	}
	
	
	$result = $db->query("SELECT keyname, value FROM hlstats_Options");
	while ($rowdata = $db->fetch_row($result))
	{
		$optiondata[$rowdata[0]] = $rowdata[1];
	}
	
	foreach ($optiongroups as $og)
	{
		$og->draw();
	}
?>

</table>

<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit">
</div>


</div>

