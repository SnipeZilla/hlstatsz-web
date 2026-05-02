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
    
    if (!file_exists("./updater")) {
        echo 'Updater directory is missing.';
        return;
    }
    
    define('IN_UPDATER', true);

    if (!isset($_POST['run']) && !isset($_POST['force']) && file_exists('./updater')) {
      checkVersion();
      return;
    }

    if (isset($_POST['force']) || !file_exists("./updater/" . ((int)$g_options['dbversion']) . ".php")) {
        $g_options['dbversion']=77;
    }
    echo '<div class="panel">
<div class="hlstats-admin-note">
        <span class="hlstats-name">HL<span class="z">Z</span></span> Database Updater log<br>';
    // Check version since updater wasn't implemented until version 1.6.2
    $versioncomp = version_compare($g_options['version'], '1.6.1');
    

	if ($versioncomp === -1)
	{
        // not yet at 1.6.1
        echo "You cannot upgrade from this version (".$g_options['version']."). You can only upgrade from 1.6.1.  Please manually apply the SQL updates found in the SQL folder through 1.6.1, then re-run this updater.</div>";
    }
    else if ($versioncomp === 0)
    {
        // at 1.6.1, up to 1.6.2
        include ("./updater/update161-162.php");        
    }

    if ($g_options['dbversion'] > 10)
    {
        // at 1.6.2 or higher, can update normally
        echo "Currently on database version ".$g_options['dbversion']."<br></div>";
        $i = $g_options['dbversion']+1;
        
        while (file_exists ("./updater/$i.php"))
        {
            echo "<br /><em>Running database update $i</em><br />\n";
            include ("./updater/$i.php");
            
            echo "<em>Database update for DB Version $i complete.</em><br />";
            $i++;
            
        }
        
        if ($i == $g_options['dbversion']+1)
        {
            message('success','Your database is already up to date ('.$g_options['dbversion'].')');
        }
        else
        {
            message('success','Successfully updated to database version "'.($i-1).'!');
        }
    }

?>

</div>