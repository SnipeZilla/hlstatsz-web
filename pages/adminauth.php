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


if  (!is_ajax())
	include (PAGE_PATH . '/header.php');
echo '<div class="admin panel" style="margin:0 auto">';

    printSectionTitle('Authorization Required');
    if ($this->error)
    {

        echo '<div class="hlstats-error">
                <span class="warning">⚠️ </span><span>Invalid credential</span>
             </div>';
	}
?>
<form method="post" name="auth" class="hlstats-form">
<div class="hlstats-cards-grid">
<section class="hlstats-section hlstats-card">
<div class="hlstats-card-body hlstats-card-grid">
        <div class="label">Username:</div><div class="value"><input type="text" name="authusername" size="20" maxlength="16" ></div>
        <div class="label">Password:</div><div class="value"><input type="password" name="authpassword" size="20" maxlength="16" ></div>
</div>
  <div class="hlstats-card-foot">
    <button type="submit">Login</button>
  </div>
<section>
</div>
</form>
</div>
