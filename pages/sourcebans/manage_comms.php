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

// Mute and/or gag a player; existing blocks are managed from their details in the Comm Blocks list
printSectionTitle('Block a Player');
?>
<div class="sba">
<form class="hlstats-section hlstats-card sba-card" data-sba-action="comm.add" novalidate>
	<?php sbCommFields(); ?>
	<div class="sba-form-foot">
		<span class="sba-form-error" role="alert"></span>
		<button type="submit" class="sba-btn is-primary">Block player</button>
	</div>
</form>
<p class="sba-muted sba-form-narrow">If the player is online, the block applies at once on their server. To edit, lift or delete a block, open it in the <a href="?mode=sourcebans&amp;task=sourcebans_comms">Comm Blocks list</a>.</p>
</div>
