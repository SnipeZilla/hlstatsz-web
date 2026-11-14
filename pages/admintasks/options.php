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

	// While the seasonal themes' days are typed (the script at the end): the dates they make, as the schedule reads them,
	// for every theme (name => the dates beside it), as the days of one can change which one shows today
	if (isset($_GET['season_days'])) {
		$seasons = array();
		foreach (array_keys(themeList(true)) as $name) {
			$days = array();
			foreach (array('on', 'from', 'to') as $field) {
				$value = $_GET[$field][$name] ?? '';
				$days[] = is_string($value) ? str_replace('|', '', $value) : '';
			}
			$seasons[$name] = themeSeason($name, implode('|', $days));
		}
		echo json_encode(array('days' => optionSeasonsDays($seasons, date('Y-m-d'))));
		exit;
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
					// By date (empty), off, or a seasonal theme whatever the date
					$value = themeName($_POST[$opt->name] ?? '');
					$optval = ($value === 'off' || isset(themeList(true)[$value])) ? $value : '';
				} elseif ($opt->type == 'seasons') {
					optionSeasons();
					continue;
				} elseif (($this->title == 'Fonts') || ($this->title == 'General') || str_contains($this->title, 'Links')) {
					// as typed (the links of the menu at the top and of the footer: valid_request() would make & &amp;)
					$optval = (string) ($_POST[$opt->name] ?? '');
					$search_pattern  = array('/script/i', '/;/', '/%/');
					$replace_pattern = array('', '', '');
					$optval = preg_replace($search_pattern, $replace_pattern, $optval);
				} else {
					$optval = valid_request($_POST[$opt->name] ?? '', false);
 	 			}
				
				optionSave($opt->name, $optval, $opt->opttype, $this->title . ': ' . strip_tags(explode('<br', $opt->title)[0]));
			}
		}
	}

	// An option's value: its row updated, or added when the install or an update did not create it (who reads it as
	// install.sql has it). 128 characters at most (hlstats_Options.value): a longer one is left as it was
	function optionSave($name, $value, $opttype = 2, $title = '')
	{
		global $db;
		$value = (string) $value;
		if (mb_strlen($value) > 128) {
			message('warning', ($title !== '' ? $title : htmlspecialchars($name)) . ': longer than 128 characters, it was not saved.');
			return;
		}
		$value = $db->escape($value);
		$result = $db->query("SELECT value FROM hlstats_Options WHERE keyname='$name'");
		if ($db->num_rows($result) == 1) {
			$db->query("UPDATE hlstats_Options SET value='$value' WHERE keyname='$name'");
		} else {
			$db->query("INSERT INTO hlstats_Options (keyname, value, opttype) VALUES ('$name', '$value', " . (int) $opttype . ")");
		}
	}

	// The schedule of the seasonal themes (option season_<name>: on|first day|last day). A day that is the theme's own
	// is saved empty: a new version of the theme brings its days. A day that cannot be read keeps the one of before
	function optionSeasons()
	{
		global $g_options;
		$year = (int) date('Y');
		$on = (array) ($_POST['season_on'] ?? array());
		foreach (themeList(true) as $e => $ename) {
			$key = "season_$e";
			if (strlen($key) > 32) {
				continue;
			}
			$before = array_pad(explode('|', (string) ($g_options[$key] ?? '')), 3, '');
			$own = themeSeason($e)['own'];
			$days = array();
			foreach (array('from', 'to') as $i => $field) {
				$day = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($_POST["season_$field"][$e] ?? ''))));
				$day = substr(preg_replace('#[^a-z0-9 /.,+-]#', '', $day), 0, 60);
				if ($day === strtolower($own[$i])) {
					$day = '';
				} elseif ($day !== '' && seasonDay($day, $year) === false) {
					message('warning', htmlspecialchars("$ename: \"$day\" is not a day it can read, the day of before is kept."));
					$day = $before[$i + 1];
				}
				$days[] = $day;
			}
			optionSave($key, (in_array($e, $on, true) ? '1' : '0') . "|$days[0]|$days[1]");
		}
	}

	// The dates beside each seasonal theme, name => html. $seasons: name => its days, as themeSeason() gives them
	function optionSeasonsDays($seasons, $today)
	{
		$showing = themeSeasonOn($today, $seasons);
		$html = array();
		foreach ($seasons as $name => $season) {
			$html[$name] = optionSeasonDays($name, $season, $today, $showing);
		}
		return $html;
	}

	// The dates of a theme's season now or next, for its row: 'Now · Nov 10 – Nov 12, 2026' on the one that shows
	// today By date; on another one whose days have today, the one that shows instead under them; or which of its days
	// cannot be read. $showing: the theme that shows today By date
	function optionSeasonDays($name, $season, $today, $showing)
	{
		$year = (int) substr($today, 0, 4);
		foreach (array('from' => 'first', 'to' => 'last') as $field => $which) {
			if (seasonDay($season[$field], $year) === false) {
				return "<span class=\"hlstats-admin-error\">Its $which day cannot be read</span>";
			}
		}
		list($first, $last) = optionSeasonNext($season, $today);
		if ($first === false) {
			return '<span class="hlstats-admin-error">Its days cannot be read</span>';
		}
		// Jul 3, 2027 for one day. A narrow row cuts the span between its dates, never in one: Dec 1, 2026 – / Jan 5, 2027
		$date = fn($day, $format) => str_replace(' ', '&nbsp;', date($format, strtotime($day)));
		$span = ($first === $last ? '' : $date($first, substr($first, 0, 4) !== substr($last, 0, 4) ? 'M j, Y' : 'M j') . ' &ndash; ')
			. $date($last, 'M j, Y');
		if (!$season['on'] || $first > $today || $showing === '') {
			return $span;
		}
		if ($showing === $name) {
			return '<strong>Now</strong>&nbsp;&middot; ' . $span;
		}
		return $span . '<span class="hlstats-season-instead">' . htmlspecialchars(themeList(true)[$showing] ?? $showing) . ' shows today</span>';
	}

	// The days of a theme's season now or next ('Y-m-d'), false, false when they cannot be read
	function optionSeasonNext($season, $today)
	{
		$year = (int) substr($today, 0, 4);
		foreach (array($year - 1, $year, $year + 1) as $y) {
			list($first, $last) = seasonDays($season, $y);
			if ($first === false) {
				break;
			}
			if ($last >= $today) {
				return array($first, $last);
			}
		}
		return array(false, false);
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
					echo htmlspecialchars(html_entity_decode($optiondata[$this->name] ?? ''));
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

				// By date (empty): each seasonal theme on its days (the schedule, 'seasons'); off; or one whatever the date
				case 'seasonal':
					$value = (string) ($optiondata[$this->name] ?? '');
					$choices = array('' => '📅 By date', 'off' => 'Off');
					foreach (themeList(true) as $e => $ename) {
						$choices[$e] = "$ename, whatever the date";
					}
					echo "<select name=\"$this->name\" id=\"opt_$this->name\" style=\"width: 100%\">";
					foreach ($choices as $e => $ename) {
						$sel = ((string) $e === $value) ? ' selected="selected"' : '';
						echo '<option value="' . htmlspecialchars((string) $e) . "\"$sel>" . htmlspecialchars($ename) . '</option>';
					}
					echo '</select>';
					break;

				// The schedule: per seasonal theme, a switch (in By date), its first and last day (empty: the theme's
				// own, the placeholder) and the days they make, now or next
				case 'seasons':
					$list = themeList(true);
					$today = date('Y-m-d');
					$seasons = array();
					foreach ($list as $e => $ename) {
						$seasons[$e] = themeSeason($e, $optiondata["season_$e"] ?? '');
					}
					$dates = optionSeasonsDays($seasons, $today);
					echo "<div class=\"hlstats-season-list\" id=\"opt_$this->name\">";
					foreach ($list as $e => $ename) {
						$saved = array_pad(explode('|', (string) ($optiondata["season_$e"] ?? '')), 3, '');
						$season = $seasons[$e];
						$days = $dates[$e];
						$on = $season['on'] ? ' checked' : '';
						echo '<div class="hlstats-season">'
							. "<label class=\"hlstats-switch\"><input type=\"checkbox\" name=\"season_on[]\" value=\"$e\"$on><span class=\"hlstats-switch-ui\"></span>$ename</label>";
						foreach (array('from' => 'First day', 'to' => 'Last day') as $field => $what) {
							$i = $field === 'from' ? 0 : 1;
							echo "<input type=\"text\" name=\"season_{$field}[$e]\" value=\"" . htmlspecialchars(trim($saved[$i + 1])) . '"'
								. ' placeholder="' . htmlspecialchars($season['own'][$i]) . "\" maxlength=\"60\" aria-label=\"$ename: $what\" />";
						}
						echo "<span class=\"hlstats-season-days\">$days</span></div>";
					}
					echo $list ? '</div>' : 'None installed</div>';
					if ($list) {
						$year = (int) date('Y');
						$help = array(
							'december 1'                  => 'A date, its month in letters (<b>dec 1</b>, <b>1 december</b>)',
							'fourth thursday of november' => 'A weekday of a month: <b>first</b>, <b>second</b>, <b>third</b>, <b>fourth</b> or <b>last</b>',
							'easter'                      => 'Easter Sunday, a different day every year',
							'easter -7 days'              => 'Days or weeks before (<b>-</b>) or after (<b>+</b>) any of these',
							'july 4 +1 week'              => '',
						);
						echo '<div class="hlstats-season-help"><div class="hlstats-season-help-title">How to write a day</div><dl>';
						foreach ($help as $rule => $what) {
							echo "<dt>$rule</dt><dd>$what</dd><dd class=\"hlstats-season-help-date\">" . date('M j, Y', strtotime(seasonDay($rule, $year))) . '</dd>';
						}
						echo '</dl><p>Empty: the theme\'s own day, in grey. A last day before the first is in the next year: '
							. '<b>december 1</b> to <b>january 5</b>. The dates beside each theme change as you type.</p></div>';
					}
					break;

				// The signature's accent: a swatch per color (sigAccents(), by number), or random
				case 'accent':
					$value = (string) ($optiondata[$this->name] ?? '');
					$accents = sigAccents();
					if (!isset($accents[$value])) {
						$value = 'random';
					}
					echo "<div class=\"hlstats-accent-list\" id=\"opt_$this->name\" role=\"radiogroup\">";
					foreach ($accents + array('random' => '') as $n => $hex) {
						$on = ((string) $n === $value) ? ' checked' : '';
						$dot = $hex !== '' ? " style=\"background: #$hex\"" : '';
						echo "<label class=\"hlstats-accent\"><input type=\"radio\" name=\"$this->name\" value=\"$n\"$on>"
							. '<span class="hlstats-accent-dot' . ($hex !== '' ? '' : ' is-random') . "\"$dot></span>" . ($hex !== '' ? $n : 'Random') . '</label>';
					}
					echo '</div>';
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
					// a quote in the value stays in it; 128 characters, as hlstats_Options.value keeps
					echo "<input type=\"text\" name=\"$this->name\" value=\"";
					echo htmlspecialchars(html_entity_decode($optiondata[$this->name] ?? ''));
					echo '" maxlength="128" />';
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
	$optiongroups[0]->options[] = new Option('sigbackground', '🏛️ Default accent color of the forum signature<br />The card shows the banner art of the game; the accent is the color of the rank. Random: one of the 11, picked each time the card is drawn', 'accent');
	
	$optiongroups[30] = new OptionGroup('🤩 Visual style settings');
    if (isset($g_options['Language']))
	$optiongroups[30]->options[] = new Option('Language', '🔤 Languages Selection<br />Semicolon-separated list of language codes to enable (e.g. <strong>us;fr;es</strong>). Max 15.<br /> Leave blank for English only or write your preferred language.', 'text');
	$optiongroups[30]->options[] = new Option('style', '🎨 Site theme<br />What visitors see until they pick another one in the style selector', 'styles');
	$optiongroups[30]->options[] = new Option('display_style_selector', '🎨 Style selector<br />Visitors can pick their own theme in the header', 'switch');
	$optiongroups[30]->options[] = new Option('styles_hidden', '🎨 Themes in the style selector<br />A theme switched off is no longer offered. The site theme stays on', 'themes');
	$optiongroups[30]->options[] = new Option('style_seasonal', '🎃 Seasonal theme<br />Every visitor sees it, with a <strong>Seasonal theme</strong> switch in the footer to go back to their own theme. <strong>By date</strong>: each one on its days, below. Or one now, whatever the date', 'seasonal');
	$optiongroups[30]->options[] = new Option('seasons', '📅 Seasonal theme days<br />Its first and last day, every year (how to write them: below). A theme switched off is left out of <strong>By date</strong>. When two have the same day, the one that began last shows (the shorter one when they began the same day), then the other one again until its last day', 'seasons');
	$optiongroups[30]->options[] = new Option('bannerdisplay', '🖼️ Show Banner', 'select');
	$optiongroups[30]->options[] = new Option('bannerfile', '🖼️ Banner file name (in /hlstatsimg/) or full banner URL', 'text');
	$optiongroups[30]->options[] = new Option('chart', '📈 Charting Library Options<br />⚠️ pChart (deprecated)<br />&rarr; Chart.js (charting library for the modern web)', 'select');
	$optiongroups[30]->options[] = new Option('show_server_load_image', '📈 Show load summaries from all monitored servers', 'select');
	$optiongroups[30]->options[] = new Option('display_gamelist', '🎮 Enable the game list navigation from the sub-menu navigation.', 'select');
	$optiongroups[30]->options[] = new Option('slider', '🎮 Collapse server for each game (only affects games with more than one server)', 'select');
	$optiongroups[30]->options[] = new Option('gamehome_show_awards', '🏆 Show daily award winners on Game Frontpage', 'select');

	// The site's own links in the menu at the top, after Forum (header.php)
	$optiongroups[32] = new OptionGroup('🔗 Top Menu Links');
	$optiongroups[32]->options[] = new Option('topnav_link1_label', 'Link 1 &rarr; Label<br />In the menu at the top, after Forum: <strong>Donate</strong>, for example. A link shows when it has both a label and a URL. A label can be a translation key, shown in each visitor\'s language: <span class="hlstats-name">link.donate</span>, <span class="hlstats-name">link.rules</span>, <span class="hlstats-name">link.store</span>, <span class="hlstats-name">link.support</span>, <span class="hlstats-name">link.join</span>, <span class="hlstats-name">link.website</span>. Your own keys: <span class="hlstats-name">lang/custom/us.json</span>, <span class="hlstats-name">fr.json</span>... (an update keeps them)', 'text');
	$optiongroups[32]->options[] = new Option('topnav_link1_url',   'Link 1 &rarr; URL', 'text');
	$optiongroups[32]->options[] = new Option('topnav_link2_label', 'Link 2 &rarr; Label', 'text');
	$optiongroups[32]->options[] = new Option('topnav_link2_url',   'Link 2 &rarr; URL', 'text');

	$optiongroups[31] = new OptionGroup('🔗 Footer Links');
	$optiongroups[31]->options[] = new Option('footer_link1_label', 'Link 1 &rarr; Label<br />A label can be a translation key, shown in each visitor\'s language: <span class="hlstats-name">link.donate</span>, <span class="hlstats-name">link.rules</span>, <span class="hlstats-name">link.store</span>, <span class="hlstats-name">link.support</span>, <span class="hlstats-name">link.join</span>, <span class="hlstats-name">link.website</span>. Your own keys: <span class="hlstats-name">lang/custom/us.json</span>, <span class="hlstats-name">fr.json</span>... (an update keeps them)', 'text');
	$optiongroups[31]->options[] = new Option('footer_link1_url',   'Link 1 &rarr; URL', 'text');
	$optiongroups[31]->options[] = new Option('footer_link2_label', 'Link 2 &rarr; Label', 'text');
	$optiongroups[31]->options[] = new Option('footer_link2_url',   'Link 2 &rarr; URL', 'text');
	$optiongroups[31]->options[] = new Option('footer_link3_label', 'Link 3 &rarr; Label', 'text');
	$optiongroups[31]->options[] = new Option('footer_link3_url',   'Link 3 &rarr; URL', 'text');

	$optiongroups[35] = new OptionGroup('🌍 GeoIP data & OpenStreetMap settings');
	$optiongroups[35]->options[] = new Option('show_google_map', '🗺️ Show World Map (OpenStreetMap)', 'select');
	$optiongroups[35]->options[] = new Option('countrydata', '🚩 Show features (flag and country) requiring GeoIP data', 'select');
	
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
    // The site theme's switch stays on; the theme list only matters with the style selector
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
    // The seasonal themes' days only matter By date
    const seasonal = document.getElementById('opt_style_seasonal');
    const seasons  = document.getElementById('opt_seasons');
    const dimSeasons = () => seasons?.classList.toggle('is-off', seasonal.value !== '');
    seasonal?.addEventListener('change', dimSeasons);
    if (seasonal) dimSeasons();

    // The dates beside the themes, read by the schedule (season_days, at the top) as days are typed or a theme is
    // switched: all of them, as the days of one can change which one shows today
    seasons?.addEventListener('input', e => {
        if (!e.target.closest('.hlstats-season')) return;
        clearTimeout(seasons.timer);
        seasons.timer = setTimeout(async () => {
            const turn = seasons.turn = (seasons.turn || 0) + 1;
            const url  = new URL(seasons.closest('form')?.action || location.href, location.href);
            const rows = new Map();
            url.searchParams.set('season_days', '1');
            seasons.querySelectorAll('.hlstats-season').forEach(row => {
                const theme = row.querySelector('input[name="season_on[]"]');
                rows.set(theme.value, row);
                url.searchParams.set(`on[${theme.value}]`, theme.checked ? '1' : '0');
                url.searchParams.set(`from[${theme.value}]`, row.querySelector('input[name^="season_from"]').value);
                url.searchParams.set(`to[${theme.value}]`, row.querySelector('input[name^="season_to"]').value);
            });
            try {
                const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (turn !== seasons.turn) return;
                rows.forEach((row, name) => {
                    if (typeof data.days?.[name] === 'string') row.querySelector('.hlstats-season-days').innerHTML = data.days[name];
                });
            } catch (err) {}
        }, 300);
    });
})();
</script>

</table>

<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit">
</div>


</div>

