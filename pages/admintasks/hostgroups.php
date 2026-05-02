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
	
	$edlist = new EditList("id", "hlstats_HostGroups", "server", false);
	$edlist->columns[] = new EditListColumn("pattern", "Host Pattern", 30, true, "text", "", 128);
	$edlist->columns[] = new EditListColumn("name", "Group Name", 30, true, "text", "", 128);
	
	if ($_POST)
	{
		if ($edlist->update())
			message("success", "Operation successful.");
		else
			message("warning", $edlist->error());
	}
	
?>
Host Groups allow you to group, for example, all players from "...adsl.someisp.net" as "SomeISP ADSL", in the Host Statistics admin tool.<p>

The Host Pattern should look like the <b>end</b> of the hostname. For example a pattern ".adsl.someisp.net" will match "1234.ny.adsl.someisp.net". You can use asterisks "*" in the pattern, e.g. ".ny.*.someisp.net". The asterisk matches zero or more of any character except a dot ".".<p>

The patterns are sorted below in the order they will be applied. A more specific pattern should match before a less specific pattern.<p>

<b>Note</b> Run <b>hlstats-resolve.pl --regroup</b> to apply grouping changes to existing data.<p>
<?php $result = $db->query("
		SELECT
			id,
			pattern,
			name,
			LENGTH(pattern) AS patternlength
		FROM
			hlstats_HostGroups
		ORDER BY
			patternlength DESC,
			pattern ASC
	");
	
	$edlist->draw($result);
?>

<table width="75%" border=0 cellspacing=0 cellpadding=0>
<tr>
	<td align="center"><input type="submit" value="  Apply  " class="submit"></td>
</tr>
</table>

