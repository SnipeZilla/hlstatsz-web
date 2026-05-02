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

	$resultGames = $db->query
	("
		SELECT
			hlstats_Games.code,
			hlstats_Games.name,
            hlstats_Games.realgame
		FROM
			hlstats_Games
		WHERE
			hlstats_Games.hidden = '0'
		ORDER BY
			LOWER(hlstats_Games.name) ASC 
	");
    $games =[];
    $gamesname = [];
    while ($res = $db->fetch_array($resultGames))
    {
      $games[]     = $res['code'];
      $gamesname[] = $res['name'];
      $realgames[] = $res['name'];
     }

// Help
if (!is_ajax()) {
?>
    <div class="hlstats-paragraph">
	<?php printSectionTitle('Questions'); ?>
	<ol>
		<li>
			<a href="#players">How are players tracked? Or, why is my name listed more than once?</a><br />
		</li>
		<li>
			<a href="#points">How is the "points" rating calculated?</a><br />
		</li>
		<li>
			<a href="#weaponmods">What are all the weapon points modifiers?</a><br />
		</li>
		<li>
			<a href="#set">How can I set my real name, e-mail address, and homepage?</a><br />
		</li>
		<li>
			<a href="#hideranking">My rank is embarrassing. How can I opt out?</a>
		</li>
	</ol>

	<?php printSectionTitle('Answers'); ?>


		<h3><a name="players">1. How are players tracked? Or, why is my name listed more than once?</a></h3>
			<?php
				if ($g_options['Mode'] == 'NameTrack')
				{
			?>
			Players are tracked by nickname. All statistics for any player using a particular name will be grouped under that name. It is not possible for a name to be listed more than once for each game.<br /><br />
			<?php
				}
				else
				{
					if ($g_options['Mode'] == 'LAN')
					{
						$uniqueid = 'IP Address';
						$uniqueid_plural = 'IP Addresses';
			?>
			Players are tracked by IP Address. IP addresses are specific to a computer on a network.<br /><br />
			<?php
					}
					else
					{
						$uniqueid = 'Unique ID';
						$uniqueid_plural = 'Unique IDs';
			?>
			Players are tracked by Unique ID. Your Unique ID is the last two sections of your Steam ID (X:XXXX).<br /><br />
			<?php
					}
			?>
			A player may have more than one name. On the Player Rankings pages, players are shown with the most recent name they used in the game. If you click on a player's name, the Player Details page will show you a list of all other names that this player uses, if any, under the Aliases section (if the player has not used any other names, the Aliases section will not be displayed).<br /><br />
			Your name may be listed more than once if somebody else (with a different <?php echo $uniqueid; ?>) uses the same name.<br /><br />
			You can use the <a href="<?php echo $g_options['scripturl']; ?>?mode=search">Search</a> function to find a player by name or <?php echo $uniqueid; ?>.<br /><br />
			<?php
				}
			?>
			<h3><a name="points">2. How is the "points" rating calculated?</a></h3>
			A new player has 1000 points. Every time you make a kill, you gain a certain amount of points depending on a) the victim's points rating, and b) the weapon you used. If you kill someone with a higher points rating than you, then you gain more points than if you kill someone with a lower points rating than you. Therefore, killing newbies will not get you as far as killing the #1 player. And if you kill someone with your knife, you gain more points than if you kill them with a rifle, for example.<br /><br />
			When you are killed, you lose a certain amount of points, which again depends on the points rating of your killer and the weapon they used (you don't lose as many points for being killed by the #1 player with a rifle than you do for being killed by a low ranked player with a knife). This makes moving up the rankings easier, but makes staying in the top spots harder.<br /><br />
			Specifically, the equations are:<br /><br />
			<em> Killer Points = Killer Points + (Victim Points / Killer Points)
				 &times; Weapon Modifier &times; 5

 Victim Points = Victim Points - (Victim Points / Killer Points)
				 &times; Weapon Modifier &times; 5</em><br /><br />
			Plus, the following point bonuses are available for completing objectives in some games:
			<a name="actions" />
			<?php
}
if (!is_ajax() || $_GET['ajax'] == 'act_help') {
                
                
    $sortorder = $_GET['act_sortorder'] ?? '';
    $sort      = $_GET['act_sort'] ?? '';

    $col = array("for_PlayerActions","for_PlayerPlayerActions","for_TeamActions","for_WorldActions","description","s_reward_player","s_reward_team");
    if (!in_array($sort, $col)) {
        $sort      = "description";
        $sortorder = "ASC";
    }


    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $page = isset($_GET['act_page']) ? ((int)$_GET['act_page'] - 1) * 1 : 0;
    if ($page > count($games) -1) $page = 0;
    $g = $games[$page];
				$result = $db->query
				("
					SELECT
						hlstats_Games.name AS gamename,
						hlstats_Actions.description,
						IF(SIGN(hlstats_Actions.reward_player) > 0, CONCAT('+', hlstats_Actions.reward_player), hlstats_Actions.reward_player) AS s_reward_player,
						IF(hlstats_Actions.team != '' AND hlstats_Actions.reward_team != 0,
						IF(SIGN(hlstats_Actions.reward_team) >= 0, CONCAT(hlstats_Teams.name, ' +', hlstats_Actions.reward_team), CONCAT(hlstats_Teams.name, ' ', hlstats_Actions.reward_team)), '') AS s_reward_team,
						IF(for_PlayerActions='1', 'Yes', 'No') AS for_PlayerActions,
						IF(for_PlayerPlayerActions='1', 'Yes', 'No') AS for_PlayerPlayerActions,
						IF(for_TeamActions='1', 'Yes', 'No') AS for_TeamActions,
						IF(for_WorldActions='1', 'Yes', 'No') AS for_WorldActions
					FROM
						hlstats_Actions
					INNER JOIN
						hlstats_Games
					ON
						hlstats_Games.code = hlstats_Actions.game
						AND hlstats_Games.hidden = '0'
					LEFT JOIN
						hlstats_Teams
					ON
						hlstats_Teams.code = hlstats_Actions.team
						AND hlstats_Teams.game = hlstats_Actions.game
                    WHERE hlstats_Games.code = '$g'
					ORDER BY
						$sort $sortorder
				");
if (!is_ajax()) {
?>
<div id="act_help">
<?php
}
?>
<h2><?=htmlspecialchars($gamesname[$page])?></h2>
<div class="responsive-table">
  <table class="help-table">
    <tr>
        <th class="left<?= isSorted('description',$sort,$sortorder) ?>"><?= headerUrl('description', ['act_sort','act_sortorder'], 'act_help') ?>Action</a></th>
        <th class="<?= isSorted('for_PlayerActions',$sort,$sortorder) ?>"><?= headerUrl('for_PlayerActions', ['act_sort','act_sortorder'], 'act_help') ?>Player Action</a></th>
        <th class="<?= isSorted('for_PlayerPlayerActions',$sort,$sortorder) ?>"><?= headerUrl('for_PlayerPlayerActions', ['act_sort','act_sortorder'], 'act_help') ?>PlayerPlayer Action</a></th>
        <th class="<?= isSorted('for_TeamActions',$sort,$sortorder) ?>"><?= headerUrl('for_TeamActions', ['act_sort','act_sortorder'], 'act_help') ?>Team Action</a></th>
        <th class="<?= isSorted('for_WorldActions',$sort,$sortorder) ?>"><?= headerUrl('for_WorldActions', ['act_sort','act_sortorder'], 'act_help') ?>World Action</a></th>
        <th class="<?= isSorted('s_reward_player',$sort,$sortorder) ?>"><?= headerUrl('s_reward_player', ['act_sort','act_sortorder'], 'act_help') ?>Player Reward</a></th>
        <th class="<?= isSorted('s_reward_team',$sort,$sortorder) ?>"><?= headerUrl('s_reward_team', ['act_sort','act_sortorder'], 'act_help') ?>Team Reward</a></th>
    </tr>
    <?php
        while ($res = $db->fetch_array($result))
        {
            echo '<tr>
                  <td class="left"><span class="hlstats-name">'.htmlspecialchars($res['description']).'</span></td>
                  <td>'.$res['for_PlayerActions'].'</td>
                  <td>'.$res['for_PlayerPlayerActions'].'</td>
                  <td>'.$res['for_TeamActions'].'</td>
                  <td>'.$res['for_WorldActions'].'</td>
                  <td>'.$res['s_reward_player'].'</td>
                  <td>'.$res['s_reward_team'].'</td>
                  </tr>';
        }

   echo '</table></div>';

       echo Pagination(count($games), $_GET['act_page'] ?? 1, 1, 'act_page', true, 'act_help');
  echo 'Showing: <span class="hlstats-name">'.htmlspecialchars($gamesname[$page]).'</span>';
  if (is_ajax()) exit;

echo '</div>';
                

			?>
			<strong>Note:</strong> The player who triggers an action may receive both the player reward and the team reward.<br /><br />
            
            
			<h3><a name="weaponmods">3. What are all the weapon points modifiers?</a></h3>
			Weapon points modifiers are used to determine how many points you should gain or lose when you make a kill or are killed by another player. Higher modifiers indicate that more points will be gained when killing with that weapon (and similarly, more points will be lost when being killed <em>by</em> that weapon). Modifiers generally range from 0.00 to 2.00.<br /><br />
<?php
}
if (!is_ajax() || $_GET['ajax'] == 'weap_help' ) {
?>
			<a name="weapons"></a>
			<?php

    $sortorder = $_GET['weap_sortorder'] ?? '';
    $sort      = $_GET['weap_sort'] ?? '';

    $col = array("code","name","modifier");
    if (!in_array($sort, $col)) {
        $sort      = "code";
        $sortorder = "ASC";
    }


    $sortorder = strtoupper($sortorder) === "ASC" ? "ASC" : "DESC";

    $page = isset($_GET['weap_page']) ? ((int)$_GET['weap_page'] - 1) * 1 : 0;
    if ($page > count($games) -1) $page = 0;
    $g = $games[$page];
				$result = $db->query
				("
					SELECT
						hlstats_Games.name AS gamename,
						hlstats_Weapons.code,
						hlstats_Weapons.name,
						hlstats_Weapons.modifier
					FROM
						hlstats_Weapons
					INNER JOIN
						hlstats_Games
					ON
						hlstats_Games.code = hlstats_Weapons.game
						AND hlstats_Games.hidden = '0'
                    WHERE hlstats_Games.code = '$g'
					ORDER BY
						$sort $sortorder
				");
				$numitems = $db->num_rows($result);
if (!is_ajax()) {
?>
<div id="weap_help">
<?php
}
?>
<h2><?=$gamesname[$page]?></h2>
<div  class="responsive-table">
  <table class="help-table">
    <tr>
        <th class="left<?= isSorted('code',$sort,$sortorder) ?>"><?= headerUrl('code', ['weap_sort','weap_sortorder'], 'weap_help') ?>Weapon</a></th>
        <th class="left<?= isSorted('name',$sort,$sortorder) ?>"><?= headerUrl('name', ['weap_sort','weap_sortorder'], 'weap_help') ?>Name</a></th>
        <th class="<?= isSorted('modifier',$sort,$sortorder) ?>"><?= headerUrl('modifier', ['weap_sort','weap_sortorder'], 'weap_help') ?>Points Modifier</a></th>
    </tr>
    <?php
        while ($res = $db->fetch_array($result))
        {
            $weapon = strtolower($res['code']);
            $code   = htmlspecialchars($res['code']);
            $image = getImage("/games/$g/weapons/" . $weapon);
            if ($image) {
                $weapimg = '<span class="hlstats-image"><img src="' . $image['url'] . '" ' . $image['size'] . ' alt="' . $code . '" title="' . $code . '"></span><span class="hlstats-name">' . $code . '</span>';
            } elseif ($image = getImage('/games/'.$realgames[$page].'/weapons/' . $weapon)) {
                $weapimg = '<span class="hlstats-image"><img src="' . $image['url'] . '" ' . $image['size'] . ' alt="' . $code . '" title="' . $code . '"></span><span class="hlstats-name">' . $code . '</span>';
            } else {
                $weapimg = '<span class="hlstats-name">' . $code . '</span>';
            }
            echo '<tr>
                  <td class="left"><span class="hlstats-name">'.$weapimg.'</span></td>
                  <td class="left">'.$res['name'].'</td>
                  <td>'.$res['modifier'].'</td>
                  </tr>';
        }

   echo '</table></div>';

       echo Pagination(count($games), $_GET['weap_page'] ?? 1, 1, 'weap_page', true, 'weap_help');
  echo 'Showing: <span class="hlstats-name">'.$gamesname[$page].'</span>';
  if (is_ajax()) exit;

echo '</div>';
                


}
			?>
            
			<h3><a name="set">4. How can I set my real name, e-mail address, and homepage?</a></h3>
            
			Player profile options can be configured by saying the appropriate <strong>HLX_SET</strong> command while you are playing on a participating game server. To say commands, push your chat key and type the command text.<br /><br />
			Syntax: say <strong>/hlx_set option value</strong>.<br /><br />
			Acceptable "options" are:
			<ul>
				<li><strong>realname</strong><br />
					Sets your Real Name as shown in your profile.<br />
					Example: &nbsp; <strong>/hlx_set realname Mick Mundy</strong><br /><br />
				</li>
			
				<li><strong>email</strong><br />
					Sets your E-mail Address as shown in your profile.<br />
					Example: &nbsp; <strong>/hlx_set email mick.mundy@sniper.tf</strong><br /><br />
				</li>
				
				<li><strong>homepage</strong><br />
					Sets your Home Page as shown in your profile.<br />
					Example: &nbsp; <strong>/hlx_set homepage https://snipezilla.com/</strong><br /><br />
				</li>
			</ul>
			<strong>Note:</strong> These are not standard Half-Life console commands. If you type them in the console, Half-Life will give you an error.<br /><br />For a full list of supported ingame commands, type the word help into ingame chat.<br /><br />
            
			<h3><a name="hideranking">5. My rank is embarrassing. How can I opt out?</a></h3>
			Say <b>/hlx_hideranking</b> while playing on a participating game server. This will toggle you between being visible on the Player Rankings and being invisible.<br /><br />
			<strong>Note:</strong> You will still be tracked and you can still view your Player Details page. Use the <a href="<?php echo $g_options['scripturl']; ?>?mode=search">Search</a> page to find yourself.
      </div>
	</div>
</div>
