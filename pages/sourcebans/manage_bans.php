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

// Ban a player by Steam ID or IP address; existing bans are managed from their details in the Bans list
printSectionTitle('Ban a Player');
?>
<div class="sba">
<form class="hlstats-section hlstats-card sba-card" data-sba-action="ban.add" novalidate>
	<?php sbBanFields(); ?>
	<div class="sba-form-foot">
		<span class="sba-form-error" role="alert"></span>
		<button type="submit" class="sba-btn is-primary">Ban player</button>
	</div>
</form>
<p class="sba-muted sba-form-narrow">
	<?= sbSetting('config.enablekickit') === '1'
		? 'After the ban, the servers are checked and the player is kicked if they are online.'
		: 'Kicking banned players at once is off in the settings; the ban applies the next time they join.' ?>
	To edit, unban or delete a ban, open it in the <a href="?mode=sourcebans&amp;task=sourcebans_bans">Bans list</a>.
</p>
</div>
