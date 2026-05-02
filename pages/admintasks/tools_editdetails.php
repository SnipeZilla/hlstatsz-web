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
<div class="hlstats-admin-note">
<p>You can enter a player or clan ID number directly,<br>
or you can search for a player or clan if you don't know the ID.</p>
</div>
<form method="GET" action="<?php echo $g_options["scripturl"]; ?>">
<input type="hidden" name="mode" value="admin">
<table>
<tr>
<td class="left">Type:</td>
<td class="left">
	<?php echo getSelect("task",
							array(
								"tools_editdetails_player"=>"Player",
									"tools_editdetails_clan"=>"Clan"
								)
						);
	?>
</td>
</tr>
<tr>
<td class="left">ID Number:</td>
<td class="left"><input type="text" name="id" size=15 maxlength=12 class="textbox"></td>
</tr>
</table>
<div class="hlstats-admin-apply">
<input type="submit" value="Edit">
</div>
</form>

<form method="GET" action="<?php echo $g_options['scripturl']; ?>">
<input type="hidden" name="mode" value="admin">
<input type="hidden" name="task" value="tools_editdetails">
<?php
			//if (is_array($getvars))
			//{
			//	foreach ($getvars as $key => $value)
			//	{
			//		echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . "\" />\n";
			//	}
			//}

			$games = array ();
			$games[''] = '(All)';
			$result = $db->query("
				SELECT
					hlstats_Games.code,
					hlstats_Games.name
				FROM
					hlstats_Games
				WHERE
					hlstats_Games.hidden = '0'
				ORDER BY
					hlstats_Games.name
			");
			while ($rowdata = $db->fetch_row($result))
			{
				$games[$rowdata[0]] = $rowdata[1];
			}

			$uniqueid_string = 'Steam ID';
			$uniqueid_string_plural = 'Steam IDs';
			if ($g_options['Mode'] == 'LAN')
			{
				$uniqueid_string = 'IP Address';
				$uniqueid_string_plural = 'IP Addresses';
			}

				$searchtypes = array(
					'player' => 'Player Names',
					'uniqueid' => 'Player ' . $uniqueid_string_plural
				);
				if ($g_options['Mode'] != 'LAN' && isset($_SESSION['loggedin']) && $_SESSION['acclevel'] >= 80) {
					$searchtypes['ip'] = 'Player IP Addresses';
				}
				$searchtypes['clan'] = 'Clan Names';

?>
<table>
<tr>
	<td class="left">Search For:</td>
	<td class="left"><input type="text" name="q" size="30" maxlength="255" value="" class="textbox" /></td>
</tr>
<tr>
	<td class="left">In:</td>
	<td class="left"><?php echo getSelect('st', $searchtypes, 'player'); ?></td>
</tr>
<tr>
	<td class="left">Game:</td>
	<td class="left"><?php echo getSelect('game', $games); ?></td>
</tr>
</table>
<div class="hlstats-admin-apply">
<input type="submit" value="Search">
</div>
</form>


<?php

	$sr_query = isset($_GET["q"])? $_GET["q"] : '';
    $search_pattern  = array("/script/i", "/;/", "/%/");
    $replace_pattern = array("", "", "");
    $sr_query = preg_replace($search_pattern, $replace_pattern, $sr_query);

	$sr_type = valid_request(isset($_GET["st"])? $_GET["st"] : '', false) or "player";
	$sr_game = valid_request($_GET["game"] ?? '', false);
	
	$search = new Search($sr_query, $sr_type, $sr_game);
	
	//$search->drawForm(array(
	//	"mode"=>"admin",
	//	"task"=>$selTask
	//));
	
	if ($sr_query)
	{
		$search->drawResults(
			"mode=admin&task=tools_editdetails_player&id=%k",
			"mode=admin&task=tools_editdetails_clan&id=%k"
		);
	}
?>