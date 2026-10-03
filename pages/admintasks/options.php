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

	if ($auth->userdata['acclevel'] < 100) {
        die ('Access denied!');
	}

?>
	<div class="hlstats-admin-table-wrap hlstats-scrollbar panel">
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
				if ($opt->type == 'themes') {
					// The switches post the themes turned on: the option keeps the others, never the site's theme
					$on = array_merge((array) ($_POST[$opt->name] ?? array()), array(themeName($_POST['style'] ?? '')));
					$optval = implode(';', array_diff(array_keys(themeList()), $on));
					if (strlen($optval) > 128) {
						message('warning', 'Too many themes switched off to save them all (128 characters): the style selector was left as it was.');
						continue;
					}
				} elseif ($opt->type == 'seasonal') {
					$on = array_values(array_intersect((array) ($_POST[$opt->name] ?? array()), array_keys(themeList(true))));
					$optval = $on[0] ?? '';
				} elseif (($this->title == 'Fonts') || ($this->title == 'General') || (str_contains($this->title,'Footer Links'))) {
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
					// a row the install or an update did not create: who reads it as install.sql has it
					$result = $db->query("
						INSERT INTO
							hlstats_Options
							(
								keyname,
								value,
								opttype
							)
						VALUES
						(
							'$opt->name',
							'$optval',
							$opt->opttype
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
		var $opttype;

		// $opttype: who reads the option, 0 the daemon, 1 the daemon and the web, 2 the web (hlstats.pl reads <= 1,
		// getOptions() >= 1)
		function __construct($name, $title, $type, $opttype = 2)
		{
			$this->name = $name;
			$this->title = $title;
			$this->type = $type;
			$this->opttype = (int) $opttype;
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
					echo html_entity_decode($optiondata[$this->name] ?? '');
					echo '</textarea>';
					break;
					
				case 'styles':
					echo "<select name=\"$this->name\" id=\"hlzSiteTheme\" style=\"width: 100%\">";
					foreach (themeList() as $e => $ename) {
						$sel = ($e == optionTheme()) ? ' selected="selected"' : '';
						echo "<option value=\"$e\"$sel>$ename</option>";
					}
					echo '</select>';
					break;

				// On posts 1, off the hidden 0 before it
				case 'switch':
					$on = ($optiondata[$this->name] ?? '') == 1 ? ' checked' : '';
					echo "<input type=\"hidden\" name=\"$this->name\" value=\"0\">"
						. "<label class=\"hlstats-switch\"><input type=\"checkbox\" name=\"$this->name\" id=\"opt_$this->name\" value=\"1\"$on aria-label=\"" . htmlspecialchars(strip_tags(explode('<br', $this->title)[0])) . '">'
						. '<span class="hlstats-switch-ui"></span></label>';
					break;

				// A switch per theme: on = offered by the style selector. The site's theme is always on
				case 'themes':
					$hidden = explode(';', $optiondata[$this->name] ?? '');
					echo "<div class=\"hlstats-switch-list\" id=\"opt_$this->name\">";
					foreach (themeList() as $e => $ename) {
						$site = ($e == optionTheme());
						$on   = ($site || !in_array($e, $hidden)) ? ' checked' : '';
						echo "<label class=\"hlstats-switch\"><input type=\"checkbox\" name=\"{$this->name}[]\" value=\"$e\"$on" . ($site ? ' aria-disabled="true"' : '') . '>'
							. "<span class=\"hlstats-switch-ui\"></span>$ename</label>";
					}
					echo '</div>';
					break;

				// A switch per seasonal theme (styles/themes/seasonal): one on at a time
				case 'seasonal':
					$list = themeList(true);
					echo "<div class=\"hlstats-switch-list\" id=\"opt_$this->name\">";
					foreach ($list as $e => $ename) {
						$on = ($e == ($optiondata[$this->name] ?? '')) ? ' checked' : '';
						echo "<label class=\"hlstats-switch\"><input type=\"checkbox\" name=\"{$this->name}[]\" value=\"$e\"$on>"
							. "<span class=\"hlstats-switch-ui\"></span>$ename</label>";
					}
					echo $list ? '</div>' : 'None installed</div>';
					break;
				
				case 'select':
					echo "<select name=\"$this->name\">";
					$result = $db->query("SELECT `value`,`text` FROM hlstats_Options_Choices WHERE keyname='$this->name' ORDER BY isDefault desc");
					while ($rowdata = $db->fetch_array($result)) {
						if ($rowdata['value'] == ($optiondata[$this->name] ?? '')) {
							echo '<option value="'.$rowdata['value'].'" selected="selected">'.$rowdata['text'];
						} else {
							echo '<option value="'.$rowdata['value'].'">'.$rowdata['text'];
						}
					}
					echo '</select>';
					break;
					
				default:
					echo "<input type=\"text\" name=\"$this->name\" value=\"";
					echo html_entity_decode($optiondata[$this->name] ?? '');
					echo '" maxlength="255" />';
			}
						?></td>
					</tr>
<?php
		}
	}

	// The site's theme as saved (themeSite() reads the options of before the save)
	function optionTheme()
	{
		global $optiondata;
		$name = themeName($optiondata['style'] ?? '');
		return isset(themeList()[$name]) ? $name : 'default';
	}

	$optiongroups = array();

	// The SourceBans / AMXBans pages have their own settings (Bans > Bans Settings, bans_settings.php)
	$optiongroups[0] = new OptionGroup('⚙️ Site Settings');
	$optiongroups[0]->options[] = new Option('sitename', '🌐 Site Name', 'text');
	$optiongroups[0]->options[] = new Option('siteurl', '🌐 Site URL', 'text');
	$optiongroups[0]->options[] = new Option('nav_globalchat', '🔗 Show Chat nav-link', 'select');
	$optiongroups[0]->options[] = new Option('nav_cheaters', '🔗 Show Banned Players nav-link', 'select');
	$optiongroups[0]->options[] = new Option('forum_address', '🔗 Forum URL<br />Enter the relative or full path to your forum/message board, if you have one. Ex: http://www.yoursite.com/forum/ or /forum/', 'text');
	$optiongroups[0]->options[] = new Option('map_dlurl', '🔗 Map Download URL<br /><span class="hlstats-name">%GAME%</span> = gamecode (optional sub folder).<br>https://yoursite.com/fastdl/%GAME%/ &rarr; https://yoursite.com/fastdl/tf2/<br> Leave blank to suppress download link.', 'text');
	$optiongroups[0]->options[] = new Option('sigbackground', '🏛️ Default accent color of the forum signature (numbers 1-11, or random)<br />The card shows the banner art of the game; the number picks the color of the rank', 'text');
	
	$optiongroups[30] = new OptionGroup('🤩 Visual style settings');
    if (isset($g_options['Language']))
	$optiongroups[30]->options[] = new Option('Language', '🔤 Languages Selection<br />Semicolon-separated list of language codes to enable (e.g. <strong>us;fr;es</strong>). Max 15.<br /> Leave blank for English only or write your preferred language.', 'text');
	$optiongroups[30]->options[] = new Option('style', '🎨 Site theme<br />What visitors see until they pick another one in the style selector', 'styles');
	$optiongroups[30]->options[] = new Option('display_style_selector', '🎨 Style selector<br />Visitors can pick their own theme in the header', 'switch');
	$optiongroups[30]->options[] = new Option('styles_hidden', '🎨 Themes in the style selector<br />A theme switched off is no longer offered. The site theme stays on', 'themes');
	$optiongroups[30]->options[] = new Option('style_seasonal', '🎃 Seasonal theme<br />Switch one on and every visitor sees it, with a <strong>Seasonal theme</strong> switch in the footer to go back to their own theme. One at a time, from <span class="hlstats-name">styles/themes/seasonal/</span>', 'seasonal');
	$optiongroups[30]->options[] = new Option('bannerdisplay', '🖼️ Show Banner', 'select');
	$optiongroups[30]->options[] = new Option('bannerfile', '🖼️ Banner file name (in /hlstatsimg/) or full banner URL', 'text');
	$optiongroups[30]->options[] = new Option('chart', '📈 Charting Library Options', 'select');
	$optiongroups[30]->options[] = new Option('show_server_load_image', '📈 Show load summaries from all monitored servers', 'select');
	$optiongroups[30]->options[] = new Option('display_gamelist', '🎮 Enable the game list navigation from the sub-menu navigation.', 'select');
	$optiongroups[30]->options[] = new Option('slider', '🎮 Collapse server for each game (only affects games with more than one server)', 'select');
	$optiongroups[30]->options[] = new Option('gamehome_show_awards', '🏆 Show daily award winners on Game Frontpage', 'select');

	$optiongroups[31] = new OptionGroup('🔗 Footer Links');
	$optiongroups[31]->options[] = new Option('footer_link1_label', 'Link 1 &rarr; Label', 'text');
	$optiongroups[31]->options[] = new Option('footer_link1_url',   'Link 1 &rarr; URL', 'text');
	$optiongroups[31]->options[] = new Option('footer_link2_label', 'Link 2 &rarr; Label', 'text');
	$optiongroups[31]->options[] = new Option('footer_link2_url',   'Link 2 &rarr; URL', 'text');
	$optiongroups[31]->options[] = new Option('footer_link3_label', 'Link 3 &rarr; Label', 'text');
	$optiongroups[31]->options[] = new Option('footer_link3_url',   'Link 3 &rarr; URL', 'text');

	$optiongroups[35] = new OptionGroup('🌍 GeoIP data & OpenStreetMap settings');
	$optiongroups[35]->options[] = new Option('show_google_map', '🗺️ Show World Map (OpenStreetMap)', 'select');
	$optiongroups[35]->options[] = new Option('countrydata', '🚩 Show features (flag and country) requiring GeoIP data', 'select');
	$optiongroups[35]->options[] = new Option('UseGeoIPBinary', '💻 <strong>*GeoCity2-Lite from binary file (newest)</strong> or GeoCity-Lite from mysql database(<strong>!!!deprecated!!!</strong>).<br>For binary, GeoLite2-City.dat goes in perl/GeoLiteCity and Geo::IP::PurePerl module is required', 'select', 0);
	
	$optiongroups[40] = new OptionGroup('⚡Daemon Settings');
	$optiongroups[40]->options[] = new Option('Mode', '*Sets the player-tracking mode.<br><ul><LI><b>Steam ID</b>     - Recommended for public Internet server use. Players will be tracked by Steam ID.<LI><b>Player Name</b>  - Useful for shared-PC environments, such as Internet cafes, etc. Players will be tracked by nickname. <LI><b>IP Address</b>        - Useful for LAN servers where players do not have a real Steam ID. Players will be tracked by IP Address. </UL>', 'select', 1);
	$optiongroups[40]->options[] = new Option('AllowOnlyConfigServers', '*Allow only servers set up in admin panel to be tracked. Other servers will NOT automatically added and tracked! This is a big security thing', 'select', 0);
	$optiongroups[40]->options[] = new Option('MinActivity', "&rarr; hlstats-awards.pl<br>HLstats will show last player meter activity on the server.<br>No data will be deleted. This is only a nice visual indication (column 'Activity').<br>Default 28 days.", 'text');
	$optiongroups[40]->options[] = new Option('DeleteDays', '&rarr; hlstats-awards.pl<br>HLstats automatically removes older events, keeping only each player\'s most recent days of activity. This is important for performance reasons and common sense.<br>Recommended: 365 - 730 days<br/>🚨 &rarr; Setting to <strong>0</strong> will never delete any events with <a href="https://github.com/SnipeZilla/HLSTATS-2" target="_blank">HLstatsZ</a> ≥ 2.5.4<br>💡 &rarr; <a href="?mode=admin&task=tools_reset"><strong>Full / Partial Reset</strong></a> allows you to reset any events table', 'text', 1);
	$optiongroups[40]->options[] = new Option('Rcon', '*Allow HLstats to send Rcon commands to the game servers', 'select', 0);
	$optiongroups[40]->options[] = new Option('RconIgnoreSelf', '*Ignore (do not log) Rcon commands originating from the same IP as the server being rcon-ed (useful if you run any kind of monitoring script which polls the server regularly by rcon)<br>&rarr; BindIP in hlstats.conf', 'select', 0);
	$optiongroups[40]->options[] = new Option('RconRecord', '*Record Rcon commands to the Admin event table. This can be useful to see what your admins are doing, but if you run programs like PB <br>🚨 &rarr; It will fill your database up with a lot of useless junk', 'select', 0);
	$optiongroups[40]->options[] = new Option('UseTimestamp', '*If no (default), use the current time on the database server for the timestamp when recording events. If yes, use the timestamp provided on the log data.<br>Unless you are processing old log files on STDIN or your game server is in a different timezone than webhost, you probably want to set this to no.', 'select', 0);
	$optiongroups[40]->options[] = new Option('TrackStatsTrend', '*Save how many players, kills etc, are in the database each day and give access to graphical statistics', 'select', 0);
	$optiongroups[40]->options[] = new Option('GlobalBanning', '*Make player bans available on all participating servers. Players who were banned permanently are automatic hidden from rankings', 'select', 0);
	$optiongroups[40]->options[] = new Option('LogChat', '*Log player chat to database', 'select', 0);
	$optiongroups[40]->options[] = new Option('LogChatAdmins', '*Log admin chat to database', 'select', 0);
	$optiongroups[40]->options[] = new Option('GlobalChat', '*Broadcast chat messages through all particapting servers. To all, none, or admins only', 'select', 0);

	$optiongroups[50] = new OptionGroup('Ranking & Point calculation settings');
	$optiongroups[50]->options[] = new Option('rankingtype', '*Ranking type', 'select', 1);
	$optiongroups[50]->options[] = new Option('SkillMaxChange', '*Maximum number of skill points a player will gain from each frag. Default 25', 'text', 0);
	$optiongroups[50]->options[] = new Option('SkillMinChange', '*Minimum number of skill points a player will gain from each frag. Default 2', 'text', 0);
	$optiongroups[50]->options[] = new Option('PlayerMinKills', '*Number of kills a player must have before receiving regular points. (Before this threshold is reached, the killer and victim will only gain/lose the minimum point value) Default 50', 'text', 0);
	$optiongroups[50]->options[] = new Option('SkillRatioCap', '*Cap killer\'s gained skill with ratio using *XYZ*SaYnt\'s method "designed such that an excellent player will have to get about a 2:1 ratio against noobs to hold steady in points"', 'select', 0);

	$optiongroups[60] = new OptionGroup('Proxy Settings');
	$optiongroups[60]->options[] = new Option('Proxy_Key', '*Key to use when sending remote commands to Daemon, empty for disable', 'text', 1);
	$optiongroups[60]->options[] = new Option('Proxy_Daemons', '*List of daemons to send PROXY events from (used for <b>Daemon Control</b> and <b>proxy-daemon.pl</b>), <br>use "," as delimiter, eg &lt;ip&gt;:&lt;port&gt;,&lt;ip&gt;:&lt;port&gt;,... ', 'text');
    
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
<script>
(() => {
    // The site theme's switch stays on; the theme list only matters with the style selector; one seasonal theme at a time
    const site     = document.getElementById('hlzSiteTheme');
    const selector = document.getElementById('opt_display_style_selector');
    const themes   = document.getElementById('opt_styles_hidden');
    // aria-disabled, not disabled: the previous site theme is still posted on when the site theme changes
    const lock = () => themes?.querySelectorAll('input').forEach(i => {
        if (i.value === site.value) {
            i.checked = true;
            i.setAttribute('aria-disabled', 'true');
        } else {
            i.removeAttribute('aria-disabled');
        }
    });
    themes?.addEventListener('click', e => {
        if (e.target.matches?.('input[aria-disabled]')) e.preventDefault();
    });
    const dim = () => themes?.classList.toggle('is-off', !selector.checked);
    site?.addEventListener('change', lock);
    selector?.addEventListener('change', dim);
    if (selector) dim();
    document.getElementById('opt_style_seasonal')?.addEventListener('change', e => {
        if (e.target.checked) e.currentTarget.querySelectorAll('input').forEach(i => i.checked = i === e.target);
    });
})();
</script>

</table>

<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit">
</div>


</div>

