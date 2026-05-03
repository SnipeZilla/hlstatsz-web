<?php
/*
HLstatsZ - Real-time player and clan rankings and statistics
Awards (Player-Player Actions — Victim) admin — direct SQL, no EditList dependency.
*/
if (!defined('IN_HLSTATS')) { die('Do not access this file directly'); }

if ($auth->userdata['acclevel'] < 80) {
	die('Access denied!');
}

$awardType = 'V';

// Count total
$total = $db->fetch_row(
	$db->query("SELECT COUNT(*) FROM hlstats_Awards WHERE game='" . $db->escape($gamecode) . "' AND awardType='$awardType'")
)[0] ?? 0;

$start = (isset($_GET['page']) && $total > 30) ? ((int)$_GET['page'] - 1) * 30 : 0;

// ── Action codes for drop-down ────────────────────────────────────────────────
$codes = [];
$cr = $db->query(
	"SELECT code, description FROM hlstats_Actions
	 WHERE game='" . $db->escape($gamecode) . "' AND for_PlayerPlayerActions='1'
	 ORDER BY description",
	false
);
if ($cr) {
	while ($row = $db->fetch_array($cr)) {
		$codes[$row['code']] = $row['description'];
	}
}

// ── Process POST ──────────────────────────────────────────────────────────────
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$posted = $_POST['award'] ?? [];

	foreach ($posted as $rawId => $data) {
		$aid = (int)$rawId;
		if ($aid <= 0) continue;

		if (!empty($data['delete'])) {
			$db->query(
				"DELETE FROM hlstats_Awards
				 WHERE awardId='$aid'
				   AND game='" . $db->escape($gamecode) . "'
				   AND awardType='$awardType'",
				false
			);
			continue;
		}

		$code = trim((string)($data['code'] ?? ''));
		$name = trim((string)($data['name'] ?? ''));
		$verb = trim((string)($data['verb'] ?? ''));

		if ($code === '') { $errors[] = "Award #$aid: action is required."; continue; }
		if ($name === '') { $errors[] = "Award #$aid: name is required.";   continue; }

		$ok = $db->query(
			"UPDATE hlstats_Awards SET
				code='" . $db->escape($code) . "',
				name='" . $db->escape($name) . "',
				verb='" . $db->escape($verb) . "'
			 WHERE awardId='$aid'
			   AND game='" . $db->escape($gamecode) . "'
			   AND awardType='$awardType'",
			false
		);
		if (!$ok) $errors[] = "Award #$aid: database error.";
	}

	// Insert new
	$n       = $_POST['new'] ?? [];
	$newCode = trim((string)($n['code'] ?? ''));
	$newName = trim((string)($n['name'] ?? ''));
	$newVerb = trim((string)($n['verb'] ?? ''));

	if ($newCode !== '' || $newName !== '') {
		$ne = [];
		if ($newCode === '') $ne[] = "New award: action is required.";
		if ($newName === '') $ne[] = "New award: name is required.";

		if ($ne) {
			$errors = array_merge($errors, $ne);
		} else {
			$ok = $db->query(
				"INSERT INTO hlstats_Awards (game, awardType, code, name, verb)
				 VALUES (
					'" . $db->escape($gamecode) . "',
					'$awardType',
					'" . $db->escape($newCode) . "',
					'" . $db->escape($newName) . "',
					'" . $db->escape($newVerb) . "'
				 )",
				false
			);
			if (!$ok) $errors[] = "Could not add award (duplicate?).";
		}
	}

	if ($errors) {
		message("warning", implode("<br>", $errors));
	} else {
		message("success", "Operation successful.");
	}
}

// ── Load awards ───────────────────────────────────────────────────────────────
$awards = [];
$result = $db->query(
	"SELECT awardId, code, name, verb
	 FROM   hlstats_Awards
	 WHERE  game='" . $db->escape($gamecode) . "' AND awardType='$awardType'
	 ORDER  BY code ASC
	 LIMIT 30 OFFSET $start"
);
while ($row = $db->fetch_array($result)) {
	$awards[] = $row;
}

function award_code_select(string $name, string $selected, array $codes): string
{
	$out  = '<select name="' . htmlspecialchars($name) . '">';
	$out .= '<option value=""></option>';
	foreach ($codes as $code => $label) {
		$sel  = ($code === $selected) ? ' selected' : '';
		$out .= '<option value="' . htmlspecialchars($code) . '"' . $sel . '>'
			  . htmlspecialchars($label) . '</option>';
	}
	if ($selected !== '' && !isset($codes[$selected])) {
		$out .= '<option value="' . htmlspecialchars($selected) . '" selected>'
			  . htmlspecialchars($selected) . ' (current)</option>';
	}
	$out .= '</select>';
	return $out;
}

$currentPage = max(1, (int)($_GET['page'] ?? 1));
$formAction = htmlspecialchars(
	$g_options['scripturl'] . '?mode=admin&task=awards_plyrplyractions_victim&game=' . urlencode($gamecode) . '&page=' . $currentPage
);
?>
<form method="post" action="<?= $formAction ?>">
<div class="panel">

<div class="responsive-table">
<table class="responsive-task">
<thead>
<tr>
  <th class="left">Action</th>
  <th class="left">Award Name</th>
  <th class="left">Verb Plural</th>
  <th>Delete</th>
</tr>
</thead>
<tbody>
<?php foreach ($awards as $a):
	$aid  = (int)$a['awardId'];
	$base = 'award[' . $aid . ']';
?>
<tr>
  <td data-label="Action">
    <?= award_code_select($base . '[code]', $a['code'], $codes) ?>
  </td>
  <td data-label="Award Name">
    <input type="text" class="textbox"
           name="<?= $base ?>[name]"
           value="<?= htmlspecialchars($a['name']) ?>" size="25" maxlength="128">
  </td>
  <td data-label="Verb Plural">
    <input type="text" class="textbox"
           name="<?= $base ?>[verb]"
           value="<?= htmlspecialchars($a['verb']) ?>" size="20" maxlength="64">
  </td>
  <td data-label="Delete" style="text-align:center">
    <input type="checkbox" name="<?= $base ?>[delete]" value="1">
  </td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr>
  <td data-label="Action">
    <?= award_code_select('new[code]', '', $codes) ?>
  </td>
  <td data-label="Award Name">
    <input type="text" class="textbox"
           name="new[name]"
           value="" placeholder="Award name" size="25" maxlength="128">
  </td>
  <td data-label="Verb Plural">
    <input type="text" class="textbox"
           name="new[verb]"
           value="" placeholder="Verb plural" size="20" maxlength="64">
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
