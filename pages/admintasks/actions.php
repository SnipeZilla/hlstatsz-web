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
	die("Access denied!");
}

// Count total actions
$total = $db->fetch_row(
	$db->query("SELECT COUNT(*) FROM hlstats_Actions WHERE game='" . $db->escape($gamecode) . "'")
)[0] ?? 0;

$start = (isset($_GET['page']) && $total > 30) ? ((int)$_GET['page'] - 1) * 30 : 0;

// ── Teams for drop-down ───────────────────────────────────────────────────────
$teams = [];
$tr = $db->query(
	"SELECT code, name FROM hlstats_Teams
	 WHERE game='" . $db->escape($gamecode) . "'
	 ORDER BY name",
	false
);
if ($tr) {
	while ($row = $db->fetch_array($tr)) {
		$teams[$row['code']] = $row['name'];
	}
}

// ── Process POST ──────────────────────────────────────────────────────────────
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$posted = $_POST['action'] ?? [];

	foreach ($posted as $rawId => $data) {
		$aid = (int)$rawId;
		if ($aid <= 0) continue;

		// Delete
		if (!empty($data['delete'])) {
			$db->query(
				"DELETE FROM hlstats_Actions
				 WHERE id='$aid'
				   AND game='" . $db->escape($gamecode) . "'",
				false
			);
			continue;
		}

		// Update
		$code        = trim((string)($data['code']        ?? ''));
		$description = trim((string)($data['description'] ?? ''));
		$team        = trim((string)($data['team']        ?? ''));
		$rewardP     = (int)($data['reward_player']       ?? 0);
		$rewardT     = (int)($data['reward_team']         ?? 0);
		$forPA       = empty($data['for_PlayerActions'])       ? 0 : 1;
		$forPPA      = empty($data['for_PlayerPlayerActions']) ? 0 : 1;
		$forTA       = empty($data['for_TeamActions'])         ? 0 : 1;
		$forWA       = empty($data['for_WorldActions'])        ? 0 : 1;

		if ($code === '') { $errors[] = "Action #$aid: code is required."; continue; }

		$ok = $db->query(
			"UPDATE hlstats_Actions SET
				code='"        . $db->escape($code)        . "',
				description='" . $db->escape($description) . "',
				team='"        . $db->escape($team)        . "',
				reward_player='$rewardP',
				reward_team='$rewardT',
				for_PlayerActions='$forPA',
				for_PlayerPlayerActions='$forPPA',
				for_TeamActions='$forTA',
				for_WorldActions='$forWA'
			 WHERE id='$aid'
			   AND game='" . $db->escape($gamecode) . "'",
			false
		);
		if (!$ok) $errors[] = "Action #$aid: database error.";
	}

	// Insert new action
	$n       = $_POST['new'] ?? [];
	$newCode = trim((string)($n['code'] ?? ''));
	$newDesc = trim((string)($n['description'] ?? ''));
	$newTeam = trim((string)($n['team']        ?? ''));
	$newRP   = (int)($n['reward_player']       ?? 0);
	$newRT   = (int)($n['reward_team']         ?? 0);
	$newPA   = empty($n['for_PlayerActions'])       ? 0 : 1;
	$newPPA  = empty($n['for_PlayerPlayerActions']) ? 0 : 1;
	$newTA   = empty($n['for_TeamActions'])         ? 0 : 1;
	$newWA   = empty($n['for_WorldActions'])        ? 0 : 1;

	if ($newCode !== '') {
		$ok = $db->query(
			"INSERT INTO hlstats_Actions
				(game, code, description, team, reward_player, reward_team,
				 for_PlayerActions, for_PlayerPlayerActions, for_TeamActions, for_WorldActions)
			 VALUES (
				'" . $db->escape($gamecode) . "',
				'" . $db->escape($newCode)  . "',
				'" . $db->escape($newDesc)  . "',
				'" . $db->escape($newTeam)  . "',
				'$newRP', '$newRT',
				'$newPA', '$newPPA', '$newTA', '$newWA'
			 )",
			false
		);
		if (!$ok) $errors[] = "Could not add action (duplicate code?).";
	}

	if ($errors) {
		message("warning", implode("<br>", $errors));
	} else {
		message("success", "Operation successful.");
	}
}

// ── Load actions ──────────────────────────────────────────────────────────────
$actions = [];
$result = $db->query(
	"SELECT id, code, description, team, reward_player, reward_team,
	        for_PlayerActions, for_PlayerPlayerActions, for_TeamActions, for_WorldActions
	 FROM   hlstats_Actions
	 WHERE  game='" . $db->escape($gamecode) . "'
	 ORDER  BY code ASC
	 LIMIT 30 OFFSET $start"
);
while ($row = $db->fetch_array($result)) {
	$actions[] = $row;
}

// ── Team select helper ────────────────────────────────────────────────────────
function act_team_select(string $name, string $selected, array $teams): string
{
	$out = '<select name="' . htmlspecialchars($name) . '">';
	$out .= '<option value=""></option>';
	foreach ($teams as $code => $label) {
		$sel  = ($code === $selected) ? ' selected' : '';
		$out .= '<option value="' . htmlspecialchars($code) . '"' . $sel . '>'
			  . htmlspecialchars($label) . '</option>';
	}
	if ($selected !== '' && !isset($teams[$selected])) {
		$out .= '<option value="' . htmlspecialchars($selected) . '" selected>'
			  . htmlspecialchars($selected) . ' (current)</option>';
	}
	$out .= '</select>';
	return $out;
}

$currentPage = max(1, (int)($_GET['page'] ?? 1));
$formAction = htmlspecialchars(
	$g_options['scripturl'] . '?mode=admin&task=actions&game=' . urlencode($gamecode) . '&page=' . $currentPage
);
?>
<form method="post" action="<?= $formAction ?>">
<div class="panel">

<div class="hlstats-admin-note">
<p>
  You can make an action map-specific by prepending the map name and an underscore to the Action Code.<br>
  For example: <b>rock2_goalitem</b> matches only on map "rock2", while <b>goalitem</b> matches all maps.
</p>
</div>

<div class="responsive-table">
<table class="responsive-task">
<thead>
<tr>
  <th class="left">Action Code</th>
  <th class="left">Description</th>
  <th class="left">Team</th>
  <th class="left">Player Pts</th>
  <th class="left">Team Pts</th>
  <th class="left">PA</th>
  <th class="left">PPA</th>
  <th class="left">TA</th>
  <th class="left">WA</th>
  <th>Delete</th>
</tr>
</thead>
<tbody>
<?php foreach ($actions as $a):
	$aid  = (int)$a['id'];
	$base = 'action[' . $aid . ']';
?>
<tr>
  <td data-label="Action Code">
    <input type="text" class="textbox"
           name="<?= $base ?>[code]"
           value="<?= htmlspecialchars($a['code']) ?>" size="20" maxlength="64">
  </td>
  <td data-label="Description">
    <input type="text" class="textbox"
           name="<?= $base ?>[description]"
           value="<?= htmlspecialchars($a['description']) ?>" size="25" maxlength="128">
  </td>
  <td data-label="Team">
    <?= act_team_select($base . '[team]', $a['team'], $teams) ?>
  </td>
  <td data-label="Player Pts">
    <input type="text" class="textbox"
           name="<?= $base ?>[reward_player]"
           value="<?= (int)$a['reward_player'] ?>" size="5">
  </td>
  <td data-label="Team Pts">
    <input type="text" class="textbox"
           name="<?= $base ?>[reward_team]"
           value="<?= (int)$a['reward_team'] ?>" size="5">
  </td>
  <td data-label="PA" style="text-align:center">
    <input type="checkbox" name="<?= $base ?>[for_PlayerActions]" value="1"
           <?= $a['for_PlayerActions'] ? 'checked' : '' ?>>
  </td>
  <td data-label="PPA" style="text-align:center">
    <input type="checkbox" name="<?= $base ?>[for_PlayerPlayerActions]" value="1"
           <?= $a['for_PlayerPlayerActions'] ? 'checked' : '' ?>>
  </td>
  <td data-label="TA" style="text-align:center">
    <input type="checkbox" name="<?= $base ?>[for_TeamActions]" value="1"
           <?= $a['for_TeamActions'] ? 'checked' : '' ?>>
  </td>
  <td data-label="WA" style="text-align:center">
    <input type="checkbox" name="<?= $base ?>[for_WorldActions]" value="1"
           <?= $a['for_WorldActions'] ? 'checked' : '' ?>>
  </td>
  <td data-label="Delete" style="text-align:center">
    <input type="checkbox" name="<?= $base ?>[delete]" value="1">
  </td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr>
  <td data-label="Action Code">
    <input type="text" class="textbox"
           name="new[code]"
           value="" placeholder="New action code" size="20" maxlength="64">
  </td>
  <td data-label="Description">
    <input type="text" class="textbox"
           name="new[description]"
           value="" placeholder="Description" size="25" maxlength="128">
  </td>
  <td data-label="Team">
    <?= act_team_select('new[team]', '', $teams) ?>
  </td>
  <td data-label="Player Pts">
    <input type="text" class="textbox"
           name="new[reward_player]"
           value="0" size="5">
  </td>
  <td data-label="Team Pts">
    <input type="text" class="textbox"
           name="new[reward_team]"
           value="0" size="5">
  </td>
  <td style="text-align:center">
    <input type="checkbox" name="new[for_PlayerActions]" value="1">
  </td>
  <td style="text-align:center">
    <input type="checkbox" name="new[for_PlayerPlayerActions]" value="1">
  </td>
  <td style="text-align:center">
    <input type="checkbox" name="new[for_TeamActions]" value="1">
  </td>
  <td style="text-align:center">
    <input type="checkbox" name="new[for_WorldActions]" value="1">
  </td>
  <td></td>
</tr>
</tfoot>
</table>
</div>
<?= Pagination($total, $_GET['page'] ?? 1, 30, 'page', false, 'actions') ?>

<div class="hlstats-admin-apply">
  <input type="submit" value="Apply" class="submit">
</div>

</div>
</form>
