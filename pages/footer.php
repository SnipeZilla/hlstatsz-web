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

    global $db;
?>
</div>
<div style="clear:both;"></div>


<footer class="hlstats-footer">
  <div class="hlstats-footer-inner">

    <div class="hlstats-footer-top">
      <div>
        <a href="https://github.com/SnipeZilla/HLSTATS-2" target="_blank">
<svg version="1.2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4800 2002" width="108" height="45">
	<style>
		.s0 { fill: var(--logo-color); } 
		.s1 { fill: var(--logo-color-z); } 
	</style>
	<path class="s0" d="m957 1h88v1h18v1h15v1h11v1h12v1h11v1h8v1h9v1h7v1h7v1h7v1h7v1h6v1h6v1h6v1h7v1h4v1h5v1h6v1h5v1h5v1h4v1h5v1h4v1h5v1h5v1h3v1h4v1h4v1h4v1h4v1h4v1h4v1h4v1h3v1h4v1h4v1h3v1h4v1h3v1h3v1h4v1h3v1h4v1h3v1h3v1h3v1h3v1h3v1h4v1h3v1h3v1h3v1h3v1h3v1h2v1h3v1h3v1h3v1h3v1h3v1h2v1h3v1h3v1h2v1h3v1h2v1h3v1h3v1h2v1h3v1h2v1h2v1h3v1h2v1h3v1h2v1h3v1h2v1h2v1h3v1h2v1h2v1h2v1h3v1h2v1h2v1h3v1h2v1h2v1h2v1h2v1h2v1h3v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h2v1h1v1h2v1h2v1h2v1h2v1h1v1h2v1h2v1h2v1h2v1h1v1h2v1h2v1h2v1h1v1h2v1h2v1h2v1h1v1h2v1h2v1h1v1h2v1h2v1h1v1h2v1h2v1h1v1h2v1h1v1h2v1h2v1h1v1h2v1h1v1h2v1h2v1h1v1h2v1h1v1h2v1h1v1h2v1h1v1h2v1h1v1h2v1h2v1h1v1h2v1h1v1h2v1h1v1h1v1h2v1h1v1h2v1h1v1h2v1h1v1h1v1h2v1h1v1h2v1h1v1h2v1h1v1h1v1h2v1h1v1h1v1h2v1h1v1h2v1h1v1h1v1h2v1h1v1h1v1h2v1h1v1h1v1h2v1h1v1h1v1h2v1h1v1h1v1h2v1h1v1h1v1h1v1h2v1h1v1h1v1h2v1h1v1h1v1h1v1h2v1h1v1h1v1h1v1h2v1h1v1h1v1h1v1h2v1h1v1h1v1h1v1h1v1h2v1h1v1h1v1h1v1h1v1h1v1h2v1h1v1h1v1h1v1h1v1h2v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h2v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h2v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h2v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v2h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v2h1v1h1v1h1v1h1v1h1v1h1v1h1v2h1v1h1v1h1v1h1v1h1v1h1v1h1v1h1v2h1v1h1v1h1v1h1v1h1v1h1v2h1v1h1v1h1v1h1v1h1v2h1v1h1v1h1v1h1v1h1v2h1v1h1v1h1v1h1v1h1v2h1v1h1v1h1v1h1v2h1v1h1v1h1v1h1v2h1v1h1v1h1v2h1v1h1v1h1v1h1v2h1v1h1v1h1v2h1v1h1v1h1v2h1v1h1v1h1v1h1v2h1v1h1v2h1v1h1v1h1v2h1v1h1v2h1v1h1v1h1v2h1v1h1v1h1v2h1v1h1v2h1v1h1v2h1v1h1v1h1v2h1v1h1v2h1v1h1v2h1v1h1v2h1v1h1v2h1v1h1v2h1v1h1v2h1v1h1v2h1v1h1v2h1v1h1v2h1v1h1v2h1v2h1v1h1v2h1v1h1v2h1v2h1v1h1v2h1v1h1v2h1v2h1v1h1v2h1v2h1v1h1v2h1v2h1v1h1v2h1v2h1v1h1v2h1v2h1v2h1v1h1v2h1v2h1v2h1v1h1v2h1v2h1v2h1v2h1v1h1v2h1v2h1v2h1v2h1v2h1v1h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v2h1v3h1v2h1v2h1v2h1v2h1v2h1v3h1v2h1v2h1v2h1v3h1v2h1v2h1v3h1v2h1v2h1v3h1v2h1v3h1v2h1v2h1v3h1v2h1v3h1v2h1v3h1v3h1v2h1v3h1v2h1v3h1v3h1v2h1v3h1v3h1v3h1v3h1v3h1v2h1v3h1v3h1v3h1v3h1v3h1v3h1v3h1v3h1v3h1v4h1v3h1v3h1v4h1v3h1v3h1v4h1v3h1v4h1v4h1v3h1v4h1v4h1v3h1v4h1v4h1v4h1v5h1v4h1v4h1v4h1v5h1v4h1v5h1v5h1v4h1v6h1v5h1v5h1v5h1v6h1v6h1v6h1v6h1v7h1v7h1v7h1v7h1v9h1v9h1v9h1v12h1v11h1v15h1v20h1v86h-1v20h-1v15h-1v11h-1v12h-1v9h-1v9h-1v8h-1v8h-1v7h-1v7h-1v7h-1v6h-1v6h-1v6h-1v6h-1v5h-1v5h-1v5h-1v6h-1v4h-1v5h-1v5h-1v4h-1v5h-1v4h-1v4h-1v4h-1v5h-1v4h-1v4h-1v4h-1v3h-1v4h-1v4h-1v3h-1v4h-1v4h-1v3h-1v4h-1v3h-1v3h-1v4h-1v3h-1v3h-1v4h-1v3h-1v3h-1v3h-1v3h-1v3h-1v3h-1v3h-1v3h-1v3h-1v2h-1v3h-1v3h-1v3h-1v3h-1v2h-1v3h-1v3h-1v2h-1v3h-1v3h-1v2h-1v3h-1v3h-1v2h-1v3h-1v2h-1v3h-1v2h-1v2h-1v3h-1v2h-1v3h-1v2h-1v2h-1v3h-1v2h-1v2h-1v3h-1v2h-1v2h-1v2h-1v2h-1v3h-1v2h-1v2h-1v2h-1v2h-1v3h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v2h-1v1h-1v2h-1v2h-1v2h-1v2h-1v2h-1v1h-1v2h-1v2h-1v2h-1v2h-1v1h-1v2h-1v2h-1v2h-1v1h-1v2h-1v2h-1v2h-1v1h-1v2h-1v2h-1v1h-1v2h-1v2h-1v1h-1v2h-1v2h-1v1h-1v2h-1v2h-1v1h-1v2h-1v1h-1v2h-1v2h-1v1h-1v2h-1v1h-1v2h-1v2h-1v1h-1v2h-1v1h-1v2h-1v1h-1v2h-1v1h-1v2h-1v1h-1v2h-1v1h-1v2h-1v1h-1v2h-1v1h-1v2h-1v1h-1v2h-1v1h-1v2h-1v1h-1v1h-1v2h-1v1h-1v2h-1v1h-1v2h-1v1h-1v1h-1v2h-1v1h-1v1h-1v2h-1v1h-1v1h-1v2h-1v1h-1v2h-1v1h-1v1h-1v2h-1v1h-1v1h-1v2h-1v1h-1v1h-1v1h-1v2h-1v1h-1v1h-1v2h-1v1h-1v1h-1v2h-1v1h-1v1h-1v2h-1v1h-1v1h-1v1h-1v2h-1v1h-1v1h-1v1h-1v1h-1v2h-1v1h-1v1h-1v1h-1v2h-1v1h-1v1h-1v1h-1v1h-1v2h-1v1h-1v1h-1v1h-1v1h-1v2h-1v1h-1v1h-1v1h-1v1h-1v1h-1v2h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v2h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v2h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v2h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-2v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-2v2h-2v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-1v1h-2v1h-1v1h-1v1h-1v1h-1v1h-1v1h-2v2h-2v1h-1v1h-1v1h-1v1h-1v1h-2v1h-1v1h-1v1h-1v1h-1v1h-1v1h-2v1h-1v1h-1v1h-1v1h-1v1h-2v1h-1v1h-1v1h-1v1h-2v1h-1v1h-1v1h-1v1h-2v1h-1v1h-1v1h-1v1h-2v1h-1v1h-1v1h-2v1h-1v1h-1v1h-1v1h-2v1h-1v1h-1v1h-2v1h-1v1h-1v1h-2v1h-1v1h-2v1h-1v1h-1v1h-1v1h-2v1h-1v1h-1v1h-2v1h-1v1h-2v1h-1v1h-1v1h-2v1h-1v1h-2v1h-1v1h-1v1h-2v1h-1v1h-2v1h-1v1h-1v1h-2v1h-1v1h-2v1h-1v1h-2v1h-1v1h-2v1h-1v1h-1v1h-2v1h-1v1h-2v1h-2v1h-1v1h-2v1h-1v1h-2v1h-1v1h-2v1h-1v1h-2v1h-1v1h-2v1h-2v1h-1v1h-2v1h-1v1h-2v1h-2v1h-1v1h-2v1h-1v1h-2v1h-2v1h-1v1h-2v1h-2v1h-1v1h-2v1h-2v1h-1v1h-2v1h-2v1h-2v1h-1v1h-2v1h-2v1h-2v1h-1v1h-2v1h-2v1h-2v1h-2v1h-1v1h-2v1h-2v1h-2v1h-2v1h-1v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-2v1h-3v1h-2v1h-2v1h-2v1h-2v1h-2v1h-3v1h-2v1h-2v1h-3v1h-2v1h-2v1h-2v1h-3v1h-2v1h-2v1h-3v1h-2v1h-3v1h-2v1h-3v1h-2v1h-2v1h-3v1h-2v1h-3v1h-3v1h-2v1h-3v1h-2v1h-3v1h-3v1h-2v1h-3v1h-3v1h-3v1h-3v1h-3v1h-2v1h-3v1h-3v1h-3v1h-3v1h-3v1h-4v1h-3v1h-3v1h-3v1h-3v1h-3v1h-4v1h-3v1h-4v1h-3v1h-3v1h-4v1h-3v1h-4v1h-4v1h-3v1h-4v1h-4v1h-4v1h-4v1h-4v1h-4v1h-4v1h-4v1h-4v1h-5v1h-4v1h-5v1h-5v1h-4v1h-5v1h-6v1h-5v1h-5v1h-6v1h-6v1h-6v1h-7v1h-6v1h-7v1h-7v1h-7v1h-9v1h-10v1h-9v1h-12v1h-11v1h-15v1h-18v1h-88v-1h-18v-1h-15v-1h-11v-1h-12v-1h-9v-1h-10v-1h-9v-1h-7v-1h-7v-1h-7v-1h-6v-1h-7v-1h-6v-1h-6v-1h-6v-1h-5v-1h-5v-1h-6v-1h-5v-1h-4v-1h-5v-1h-5v-1h-4v-1h-5v-1h-4v-1h-4v-1h-4v-1h-4v-1h-4v-1h-4v-1h-4v-1h-4v-1h-4v-1h-3v-1h-4v-1h-4v-1h-3v-1h-4v-1h-3v-1h-3v-1h-4v-1h-3v-1h-4v-1h-3v-1h-3v-1h-3v-1h-3v-1h-3v-1h-4v-1h-3v-1h-3v-1h-3v-1h-3v-1h-3v-1h-2v-1h-3v-1h-3v-1h-3v-1h-3v-1h-3v-1h-2v-1h-3v-1h-3v-1h-2v-1h-3v-1h-2v-1h-3v-1h-3v-1h-2v-1h-3v-1h-2v-1h-2v-1h-3v-1h-2v-1h-3v-1h-2v-1h-3v-1h-2v-1h-2v-1h-3v-1h-2v-1h-2v-1h-2v-1h-3v-1h-2v-1h-2v-1h-3v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-3v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-2v-1h-1v-1h-2v-1h-2v-1h-2v-1h-2v-1h-1v-1h-2v-1h-2v-1h-2v-1h-2v-1h-1v-1h-2v-1h-2v-1h-2v-1h-1v-1h-2v-1h-2v-1h-2v-1h-1v-1h-2v-1h-2v-1h-1v-1h-2v-1h-2v-1h-1v-1h-2v-1h-2v-1h-1v-1h-2v-1h-1v-1h-2v-1h-2v-1h-1v-1h-2v-1h-2v-1h-1v-1h-2v-1h-1v-1h-2v-1h-1v-1h-2v-1h-1v-1h-2v-1h-1v-1h-2v-1h-1v-1h-2v-1h-2v-1h-1v-1h-2v-1h-1v-1h-1v-1h-2v-1h-1v-1h-2v-1h-1v-1h-2v-1h-1v-1h-2v-1h-1v-1h-1v-1h-2v-1h-1v-1h-2v-1h-1v-1h-1v-1h-2v-1h-1v-1h-2v-1h-1v-1h-1v-1h-2v-1h-1v-1h-2v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-2v-2h-2v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-2v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-2h-1v-1h-1v-2h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-2h-1v-1h-1v-1h-1v-2h-1v-1h-1v-2h-1v-1h-1v-2h-1v-1h-1v-1h-1v-2h-1v-1h-1v-2h-1v-1h-1v-2h-1v-1h-1v-2h-1v-1h-1v-2h-1v-1h-1v-2h-1v-1h-1v-2h-1v-1h-1v-2h-1v-1h-1v-2h-1v-1h-1v-2h-1v-1h-1v-2h-1v-2h-1v-1h-1v-2h-1v-1h-1v-2h-1v-2h-1v-1h-1v-2h-1v-1h-1v-2h-1v-2h-1v-1h-1v-2h-1v-2h-1v-1h-1v-2h-1v-2h-1v-1h-1v-2h-1v-2h-1v-1h-1v-2h-1v-2h-1v-2h-1v-1h-1v-2h-1v-2h-1v-2h-1v-1h-1v-2h-1v-2h-1v-2h-1v-2h-1v-1h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-1h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-2h-1v-3h-1v-2h-1v-2h-1v-2h-1v-2h-1v-3h-1v-2h-1v-2h-1v-2h-1v-2h-1v-3h-1v-2h-1v-2h-1v-3h-1v-2h-1v-2h-1v-3h-1v-2h-1v-3h-1v-2h-1v-2h-1v-3h-1v-2h-1v-3h-1v-2h-1v-3h-1v-3h-1v-2h-1v-3h-1v-3h-1v-2h-1v-3h-1v-3h-1v-2h-1v-3h-1v-3h-1v-3h-1v-3h-1v-2h-1v-3h-1v-3h-1v-3h-1v-3h-1v-3h-1v-3h-1v-3h-1v-3h-1v-3h-1v-4h-1v-3h-1v-3h-1v-4h-1v-3h-1v-3h-1v-4h-1v-3h-1v-4h-1v-4h-1v-3h-1v-4h-1v-4h-1v-3h-1v-4h-1v-4h-1v-4h-1v-5h-1v-4h-1v-4h-1v-4h-1v-5h-1v-4h-1v-5h-1v-5h-1v-4h-1v-6h-1v-5h-1v-5h-1v-5h-1v-6h-1v-6h-1v-6h-1v-6h-1v-7h-1v-7h-1v-7h-1v-8h-1v-8h-1v-9h-1v-9h-1v-12h-1v-11h-1v-15h-1v-20h-1v-86h1v-20h1v-15h1v-11h1v-12h1v-9h1v-9h1v-9h1v-7h1v-7h1v-7h1v-7h1v-6h1v-6h1v-6h1v-6h1v-5h1v-5h1v-5h1v-6h1v-4h1v-5h1v-5h1v-4h1v-5h1v-4h1v-4h1v-4h1v-5h1v-4h1v-4h1v-4h1v-3h1v-4h1v-4h1v-3h1v-4h1v-4h1v-3h1v-4h1v-3h1v-3h1v-4h1v-3h1v-3h1v-4h1v-3h1v-3h1v-3h1v-3h1v-3h1v-3h1v-3h1v-3h1v-3h1v-3h1v-2h1v-3h1v-3h1v-3h1v-3h1v-2h1v-3h1v-3h1v-2h1v-3h1v-2h1v-3h1v-3h1v-2h1v-3h1v-2h1v-3h1v-2h1v-2h1v-3h1v-2h1v-3h1v-2h1v-2h1v-3h1v-2h1v-2h1v-3h1v-2h1v-2h1v-2h1v-3h1v-2h1v-2h1v-2h1v-2h1v-2h1v-3h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-2h1v-1h1v-2h1v-2h1v-2h1v-2h1v-2h1v-1h1v-2h1v-2h1v-2h1v-2h1v-1h1v-2h1v-2h1v-2h1v-1h1v-2h1v-2h1v-2h1v-1h1v-2h1v-2h1v-1h1v-2h1v-2h1v-1h1v-2h1v-2h1v-1h1v-2h1v-2h1v-1h1v-2h1v-1h1v-2h1v-2h1v-1h1v-2h1v-1h1v-2h1v-2h1v-1h1v-2h1v-1h1v-2h1v-1h1v-2h1v-1h1v-2h1v-1h1v-2h1v-1h1v-2h1v-1h1v-2h1v-1h1v-2h1v-1h1v-2h1v-1h1v-2h1v-1h1v-1h1v-2h1v-1h1v-2h1v-1h1v-2h1v-1h1v-1h1v-2h1v-1h1v-1h1v-2h1v-1h1v-2h1v-1h1v-1h1v-2h1v-1h1v-2h1v-1h1v-1h1v-1h1v-2h1v-1h1v-1h1v-2h1v-1h1v-1h1v-2h1v-1h1v-1h1v-1h1v-2h1v-1h1v-1h1v-2h1v-1h1v-1h1v-1h1v-2h1v-1h1v-1h1v-1h1v-2h1v-1h1v-1h1v-1h1v-1h1v-2h1v-1h1v-1h1v-1h1v-1h1v-2h1v-1h1v-1h1v-1h1v-1h1v-2h1v-1h1v-1h1v-1h1v-1h1v-1h1v-2h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-2h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-2h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-2h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h2v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h2v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h2v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h2v-1h1v-1h1v-1h1v-1h1v-1h2v-1h1v-1h1v-1h1v-1h1v-1h1v-1h2v-1h1v-1h1v-1h1v-1h1v-1h2v-1h1v-1h1v-1h1v-1h2v-1h1v-1h1v-1h1v-1h2v-1h1v-1h1v-1h1v-1h2v-1h1v-1h1v-1h2v-1h1v-1h1v-1h1v-1h2v-1h1v-1h1v-1h2v-1h1v-1h1v-1h2v-1h1v-1h1v-1h2v-1h1v-1h1v-1h2v-1h1v-1h1v-1h2v-1h1v-1h2v-1h1v-1h1v-1h2v-1h1v-1h1v-1h2v-1h1v-1h2v-1h1v-1h2v-1h1v-1h1v-1h2v-1h1v-1h2v-1h1v-1h2v-1h1v-1h1v-1h2v-1h1v-1h2v-1h1v-1h2v-1h2v-1h1v-1h2v-1h1v-1h2v-1h1v-1h2v-1h1v-1h2v-1h1v-1h2v-1h2v-1h1v-1h2v-1h1v-1h2v-1h2v-1h1v-1h2v-1h1v-1h2v-1h2v-1h1v-1h2v-1h2v-1h1v-1h2v-1h2v-1h1v-1h2v-1h2v-1h2v-1h1v-1h2v-1h2v-1h2v-1h1v-1h2v-1h2v-1h2v-1h2v-1h1v-1h2v-1h2v-1h2v-1h2v-1h1v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h2v-1h3v-1h2v-1h2v-1h2v-1h2v-1h2v-1h3v-1h2v-1h2v-1h3v-1h2v-1h2v-1h2v-1h3v-1h2v-1h2v-1h3v-1h2v-1h3v-1h2v-1h3v-1h2v-1h2v-1h3v-1h2v-1h3v-1h3v-1h2v-1h3v-1h2v-1h3v-1h3v-1h2v-1h3v-1h3v-1h3v-1h3v-1h3v-1h2v-1h3v-1h3v-1h3v-1h3v-1h3v-1h4v-1h3v-1h3v-1h3v-1h3v-1h3v-1h4v-1h3v-1h4v-1h3v-1h3v-1h4v-1h3v-1h4v-1h4v-1h3v-1h4v-1h4v-1h4v-1h4v-1h4v-1h4v-1h4v-1h3v-1h5v-1h5v-1h4v-1h5v-1h4v-1h5v-1h5v-1h6v-1h5v-1h4v-1h7v-1h6v-1h6v-1h6v-1h7v-1h7v-1h7v-1h7v-1h9v-1h8v-1h11v-1h12v-1h11v-1h15v-1h18zm116 329v2h-2v893h1v1h274v-894h-1v-2zm-360 286v607h275v-607zm-381 223v1h-2v740h2v1h2v1h1105v89h6v-2h2v-2h1v-1h1v-1h1v-1h2v-2h1v-1h2v-2h2v-2h1v-1h2v-2h1v-1h2v-2h2v-2h1v-1h2v-2h1v-1h1v-1h1v-1h2v-2h1v-1h2v-2h1v-1h1v-1h1v-1h2v-2h1v-1h2v-2h1v-1h1v-1h1v-1h2v-2h1v-1h2v-2h1v-1h1v-1h1v-1h2v-2h1v-1h2v-2h1v-1h1v-1h1v-1h2v-2h1v-1h2v-2h1v-1h1v-1h1v-1h2v-2h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h2v-2h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h2v-2h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h2v-2h1v-1h1v-1h1v-1h2v-2h1v-1h2v-2h1v-1h1v-1h1v-1h2v-2h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h2v-2h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h2v-2h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h2v-2h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h2v-2h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-1h1v-7h-2v-1h-1v-2h-2v-2h-1v-1h-2v-2h-2v-1h-1v-2h-2v-2h-1v-1h-2v-2h-2v-1h-1v-2h-2v-2h-1v-1h-2v-2h-2v-2h-1v-1h-2v-2h-1v-1h-2v-2h-2v-2h-1v-1h-2v-2h-1v-1h-2v-2h-2v-2h-1v-1h-2v-2h-1v-1h-2v-2h-2v-2h-1v-1h-2v-2h-1v-1h-2v-2h-2v-2h-1v-1h-2v-2h-1v-1h-1v-1h-1v-1h-2v-3h-3v-2h-1v-1h-1v-1h-1v-1h-2v-3h-3v-2h-1v-1h-1v-2h-3v-3h-3v-2h-1v-1h-1v-2h-3v-3h-3v-2h-1v-1h-1v-2h-3v-3h-2v-1h-1v-1h-1v-1h-1v-2h-2v-1h-1v-2h-2v-2h-2v-1h-1v-2h-2v-1h-1v-2h-2v-2h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-1h-1v-2h-2v-1h-1v-2h-2v-2h-2v-1h-1v-2h-2v-1h-1v-2h-2v-2h-2v-1h-1v-2h-2v-2h-1v-1h-2v-2h-1v-1h-2v-2h-2v-2h-1v-1h-2v-2h-1v-1h-1v-1h-1v-1h-2v-2h-1v-1h-2v-2h-1v-1h-1v-1h-1v-1h-2v-2h-1v-1h-2v-2h-1v-1h-1v-1h-1v-1h-2v-2h-1v-1h-1v-1h-1v-1h-1v-1h-1v-2h-3v-3h-2v-1h-1v-1h-1v-1h-1v-2h-3v-3h-2v-1h-1v-1h-1v-1h-1v-2h-2v-1h-1v-2h-2v-2h-2v-1h-1v-2h-2v-1h-1v-2h-2v-1h-1v-1h-3v1h-1v94h-832v-467z"/>
	<path id="HLZ" class="s0" aria-label="HLZ"  d="m3249.6 1464v-925.6h-180.7v378.3h-313.3v-378.3h-180.7v925.6h180.7v-387.4h313.3v387.4zm815.9 0v-161.2h-419.9v-764.4h-180.7v925.6z"/>
	<path id="HLZ" class="s1" aria-label="HLZ"  d="m4765.4 1464v-161.2h-396.5l396.5-621.4v-143h-591.5v161.2h378.3l-395.2 618.8v145.6z"/>
</svg>
        </a>
      </div>

      <div class="hlstats-footer-meta">
        <?php
          echo 'Generated by <a href="https://github.com/SnipeZilla/HLSTATS-2" target="_blank">HLstats<span class="z">Z</span> Real-Time Statistics '.$g_options['version'].'</a>';
        ?>
        <div class="hlstats-footer-small">
          All images are copyrighted by their respective owners.
        </div>
      </div>
    </div>

    <div class="hlstats-footer-links">
      <?php if (!defined('STEAM_API') || empty(STEAM_API) ||
                !defined('STEAM_ADMIN') || empty(STEAM_ADMIN)) {
      echo '<a href="?mode=admin">Admin</a>';
        if (isset($_SESSION['loggedin'])) {
          echo '<a href="hlstats.php?logout=1">Logout</a>';
        }
      } ?>
      <?php
      for ($i = 1; $i <= 3; $i++) {
          $label = trim($g_options["footer_link{$i}_label"] ?? '');
          $url   = trim($g_options["footer_link{$i}_url"]   ?? '');
          if ($label !== '' && $url !== '') {
              echo '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank">' . htmlspecialchars($label, ENT_QUOTES) . '</a>';
          }
      }
      ?>
    </div>

  </div>
</footer>
<?php
ob_flush();
flush();
global $mode, $game;
if (($g_options["show_google_map"] == 1) && (($mode == "contents") || ($mode == "claninfo")))
{
    $type = $mode == "contents" ? 'main' : 'clan';
    include(INCLUDE_PATH . '/openstreetmap.php');
?>
    <script src="<?= INCLUDE_PATH ?>/js/leaflet.js"></script>
    <script src="<?= INCLUDE_PATH ?>/js/markercluster.js"></script>
    <script>
    const imagePath = "<?= IMAGE_PATH ?>";
    const countrydata = <?= $g_options['countrydata'] ?>;
    var markers;
    var OpenMap;
    const DetailPage = <?= empty($game) ? 1 : 0 ?>;

    function setupMap() {
        if (!document.querySelector("#map")) return;

        if (OpenMap) { OpenMap.remove(); OpenMap = null; }

        LeafIcon = L.Icon.extend({
            options: {
                shadowUrl: imagePath + "/marker-shadow.png",
                iconSize: [48,48],
                iconAnchor: [24,43],
                popupAnchor: [0,-24],
                shadowSize: [64,64],
                shadowAnchor: [20, 64]
            }
        });

        iniMap();

        let jsonEl = document.querySelector("#servers-json");
        if (jsonEl) createServers(JSON.parse(jsonEl.textContent || "[]"));

        jsonEl = document.querySelector("#players-json");
        if (jsonEl) createPlayer(JSON.parse(jsonEl.textContent || "[]"));
    }

    waitForElement("#map", setupMap, { timeout: 50 });

    document.getElementById('hlstats-contents')
        ?.addEventListener('fetch:loaded', setupMap);
    </script>
<?php
}
if ($g_options['display_style_selector'] == 1) {
  global $selectedStyle;
  $d = dir('styles/themes');
  while (false !== ($e = $d->read())) {
      if ($e === '.' || $e === '..' || $e === 'disabled') continue;
      if (is_dir("styles/themes/$e") && is_file("styles/themes/$e/$e.css")) {
          $ename = ucwords(strtolower(str_replace('_', ' ', $e)));
          $styles[$e] = $ename;
      }
  }
  $d->close(); 
  asort($styles); 
  if ( "default" == $selectedStyle ) {
      $stylesheets = '<span>Default ✓</span>';
  } else {
      $query = updateQueryKey(["stylesheet" => 'default']);
      $stylesheets = '<a href=\"?'.$query.'\">Default</a>';
  }
  foreach ($styles as $e => $ename) {
     if ( $e == $selectedStyle ) {
        $stylesheets .= '<span>'. $ename . ' ✓</span>';
     } else {
         $query = updateQueryKey(["stylesheet" => $e]);
        $stylesheets .= '<a href=\"?'.$query.'\">'.$ename.'</a>';
     }  
  }
  echo '<script>document.getElementById("theme-menu").innerHTML = "'.$stylesheets.'"</script>';
}

if ($g_options['display_gamelist'] == 1 && !empty($game) && $mode != 'admin' && $mode != 'help') {
    $resultGames = $db->query("
    SELECT
        code,
        name
    FROM
        hlstats_Games
    WHERE
        hidden='0'
    ORDER BY
        name ASC
    ");
    $mode = array('players','clans','awards','chat','actions','weapons','maps','bans','countryclans');
    $html = '';
        while ($gamedata = $db->fetch_row($resultGames)) {
            if ($gamedata[0] == $game) continue;
            $query = isset($_GET['mode']) && in_array($_GET['mode'], $mode) && !empty($_GET['game']) ?
                     updateQueryKey(["game" => $gamedata[0]]) : 'game='.$gamedata[0];
            $html .= '<li><a href=\"?'.$query.'\">'.$gamedata[1].'</a></li>';
        }
        if (!empty($html)) {
            echo '<script>document.getElementById("gamelist-menu").innerHTML = "'.$html.'";document.getElementById("game-dropdown").classList.add("has-games");</script>';
        }
}
?>
</body>
</html>
