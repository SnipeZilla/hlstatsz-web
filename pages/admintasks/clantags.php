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

	$edlist = new EditList("id", "hlstats_ClanTags", "clan", false);
	$edlist->columns[] = new EditListColumn("pattern", "Pattern", 40, true, "text", "", 64);
	$edlist->columns[] = new EditListColumn("position", "Match Position", 0, true, "select", "EITHER/EITHER;START/START only;END/END only");
	echo '<div class="panel">';
	if ($_POST)
	{
		if ($edlist->update())
			message("success", "Operation successful.");
		else
			message("warning", $edlist->error());
	}
	
?>
<div class="hlstats-admin-note">
<p>
Here you can define the patterns used to determine what clan a player is in. These patterns are applied to players' names when they connect or change name.
</p>
<p>
Special characters in the pattern:
</p>
</div>
<table>

<tr>
	<th class="left">Character</td>
	<th class="left">Description</td>
</tr>

<tr>
	<td class="left"><tt>A</tt></td>
	<td class="left">Matches one character  (i.e. a character is required)</td>
</tr>

<tr>
	<td class="left"><tt>X</tt></td>
	<td class="left">Matches zero or one characters  (i.e. a character is optional)</td>
</tr>

<tr>
	<td class="left"><tt>a</tt></td>
	<td class="left">Matches literal A or a</td>
</tr>

<tr>
	<td class="left"><tt>x</tt></td>
	<td class="left">Matches literal X or x</td>
</tr>

</table><p>

Example patterns:<p>

<table border=0 cellspacing=0 cellpadding=4>

<tr>
	<th class="left">Pattern</th>
	<th class="left">Description</th>
	<th class="left">Example</th>
</tr>

<tr>
	<td class="left"><tt>[AXXXXX]</tt></td>
	<td class="left">Matches 1 to 6 characters inside square braces</td>
	<td class="left"><tt>[ZOOM]Player</tt></td>
</tr>

<tr>
	<td class="left"><tt>{AAXX}</tt></td>
	<td class="left">Matches 2 to 4 characters inside curly braces</td>
	<td class="left"><tt>{S3G}Player</tt></td>
</tr>

<tr>
	<td class="left"><tt>rex>></tt></td>
	<td class="left">Matches the string "rex>>", "REX>>", etc.</td>
	<td class="left"><tt>REX>>Tyranno</tt></td>
</tr>

</table>

<p>
Avoid adding patterns to the database that are too generic. Always ensure you have at least one literal (non-special) character in the pattern -- for example if you were to add the pattern "AXXA", it would match any player with 2 or more letters in their name!
</p>
<p>
The Match Position field sets which end of the player's name the clan tag is allowed to appear.
</p>

<?php
	
	$result = $db->query("
		SELECT
			id,
			pattern,
			position
		FROM
			hlstats_ClanTags
		ORDER BY
			id
	");
	
	$edlist->draw($result);
?>

<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit">
</div>
</div>
