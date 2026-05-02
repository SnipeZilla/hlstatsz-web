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

	$edlist = new EditList("username", "hlstats_Users", "user", false);
	$edlist->columns[] = new EditListColumn("username", "Username", 15, true, "text", "", 16);
	$edlist->columns[] = new EditListColumn("password", "Password", 15, true, "password", "", 16);
	$edlist->columns[] = new EditListColumn("acclevel", "Access Level", 25, true, "select", "0/No Access;80/Restricted;100/Administrator");
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

<?php message('warning','It is strongly recommended to use STEAM API for a secure login (OpenID) validated by Steam.'); ?>

<p>
Usernames and passwords can be set up for access to this HLstats Admin area. For most sites you will only want one admin user - yourself.<br>
Some sites may however need to give administration access to several people.
</p>
<p>
<b>Note</b><br>
Passwords are encrypted in the database and so cannot be viewed. However, you can change a user's password by entering a new plain text value in the Password field.
</p>
<p>
<b>Access Levels</b><br>

&#149; <i>Restricted</i> users only have access to the Host Groups, Clan Tag Patterns, Weapons, Teams, Awards and Actions configuration areas. This means these users cannot set Options or add new Games, Servers or Admin Users to HLstats, or use any of the admin Tools.<br>
&#149; <i>Administrator</i> users have full, unrestricted access.
</p>
</div>
<?php
	
	$result = $db->query("
		SELECT
			username,
			IF(password='','','(encrypted)') AS password,
			acclevel
		FROM
			hlstats_Users
		ORDER BY
			username
	");
	
	$edlist->draw($result);
?>

<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit">
</div>
</div>
