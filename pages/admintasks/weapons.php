<?php
/*
HLstatsZ - Real-time player and clan rankings and statistics
Weapons admin — direct SQL, no EditList dependency.
*/
if (!defined('IN_HLSTATS')) { die('Do not access this file directly'); }

if ($auth->userdata["acclevel"] < 80) {
	die("Access denied!");
}

// Count total
$total = $db->fetch_row(
	$db->query("SELECT COUNT(*) FROM hlstats_Weapons WHERE game='" . $db->escape($gamecode) . "'")
)[0] ?? 0;

$start = (isset($_GET['page']) && $total > 30) ? ((int)$_GET['page'] - 1) * 30 : 0;

// ── Process POST ──────────────────────────────────────────────────────────────
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$posted = $_POST['weapon'] ?? [];

	foreach ($posted as $rawId => $data) {
		$wid = (int)$rawId;
		if ($wid <= 0) continue;

		if (!empty($data['delete'])) {
			$db->query(
				"DELETE FROM hlstats_Weapons
				 WHERE weaponId='$wid'
				   AND game='" . $db->escape($gamecode) . "'",
				false
			);
			continue;
		}

		$code     = trim((string)($data['code']     ?? ''));
		$name     = trim((string)($data['name']     ?? ''));
		$modifier = trim((string)($data['modifier'] ?? '1.00'));

		if ($code === '') { $errors[] = "Weapon #$wid: code is required."; continue; }
		if ($name === '') { $errors[] = "Weapon #$wid: name is required."; continue; }
		if (!is_numeric($modifier)) { $errors[] = "Weapon #$wid: modifier must be a number."; continue; }

		$modifier = number_format((float)$modifier, 2, '.', '');

		$ok = $db->query(
			"UPDATE hlstats_Weapons SET
				code='"     . $db->escape($code) . "',
				name='"     . $db->escape($name) . "',
				modifier='$modifier'
			 WHERE weaponId='$wid'
			   AND game='" . $db->escape($gamecode) . "'",
			false
		);
		if (!$ok) $errors[] = "Weapon #$wid: database error.";
	}

	// Insert new
	$n       = $_POST['new'] ?? [];
	$newCode = trim((string)($n['code']     ?? ''));
	$newName = trim((string)($n['name']     ?? ''));
	$newMod  = trim((string)($n['modifier'] ?? '1.00'));

	if ($newCode !== '' || $newName !== '') {
		$ne = [];
		if ($newCode === '') $ne[] = "New weapon: code is required.";
		if ($newName === '') $ne[] = "New weapon: name is required.";
		if (!is_numeric($newMod)) $ne[] = "New weapon: modifier must be a number.";

		if ($ne) {
			$errors = array_merge($errors, $ne);
		} else {
			$newMod = number_format((float)$newMod, 2, '.', '');
			$ok = $db->query(
				"INSERT INTO hlstats_Weapons (game, code, name, modifier)
				 VALUES (
					'" . $db->escape($gamecode) . "',
					'" . $db->escape($newCode)  . "',
					'" . $db->escape($newName)  . "',
					'$newMod'
				 )",
				false
			);
			if (!$ok) $errors[] = "Could not add weapon (duplicate code?).";
		}
	}

	if ($errors) {
		message("warning", implode("<br>", $errors));
	} else {
		message("success", "Operation successful.");
	}
}

// ── Load weapons ──────────────────────────────────────────────────────────────
$weapons = [];
$result = $db->query(
	"SELECT weaponId, code, name, modifier
	 FROM   hlstats_Weapons
	 WHERE  game='" . $db->escape($gamecode) . "'
	 ORDER  BY code ASC
	 LIMIT 30 OFFSET $start"
);
while ($row = $db->fetch_array($result)) {
	$weapons[] = $row;
}

$helpUrl     = htmlspecialchars($g_options['scripturl'] . '?mode=help#points');
$formAction  = htmlspecialchars(
	$g_options['scripturl'] . '?mode=admin&task=weapons&game=' . urlencode($gamecode)
);
?>
<form method="post" action="<?= $formAction ?>">
<div class="panel">

<div class="hlstats-admin-note">
<p>
  The <i>points modifier</i> is a multiplier for points gained or lost when killing/being killed by that weapon.
  Baseline is <b>1.00</b>. A modifier of <b>0.00</b> means kills with that weapon have no effect on points.
  (<a href="<?= $helpUrl ?>">Help</a>)
</p>
</div>

<div class="responsive-table">
<table class="responsive-task">
<thead>
<tr>
  <th class="left">Weapon Code</th>
  <th class="left">Weapon Name</th>
  <th class="left">Points Modifier</th>
  <th>Delete</th>
</tr>
</thead>
<tbody>
<?php foreach ($weapons as $w):
	$wid  = (int)$w['weaponId'];
	$base = 'weapon[' . $wid . ']';
?>
<tr>
  <td data-label="Weapon Code">
    <input type="text" class="textbox"
           name="<?= $base ?>[code]"
           value="<?= htmlspecialchars($w['code']) ?>" size="18" maxlength="32">
  </td>
  <td data-label="Weapon Name">
    <input type="text" class="textbox"
           name="<?= $base ?>[name]"
           value="<?= htmlspecialchars($w['name']) ?>" size="25" maxlength="64">
  </td>
  <td data-label="Points Modifier">
    <input type="text" class="textbox"
           name="<?= $base ?>[modifier]"
           value="<?= htmlspecialchars($w['modifier']) ?>" size="6">
  </td>
  <td data-label="Delete" style="text-align:center">
    <input type="checkbox" name="<?= $base ?>[delete]" value="1">
  </td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr>
  <td data-label="Weapon Code">
    <input type="text" class="textbox"
           name="new[code]"
           value="" placeholder="weapon_code" size="18" maxlength="32">
  </td>
  <td data-label="Weapon Name">
    <input type="text" class="textbox"
           name="new[name]"
           value="" placeholder="Weapon name" size="25" maxlength="64">
  </td>
  <td data-label="Points Modifier">
    <input type="text" class="textbox"
           name="new[modifier]"
           value="1.00" size="6">
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
