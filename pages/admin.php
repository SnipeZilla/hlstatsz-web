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

if ( empty($game) )
{
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
        LIMIT 0,1

	");
	list($game) = $db->fetch_row($resultGames);
}

class Auth
{
	var $ok = false;
	var $error = false;

	var $username, $password, $savepass;
	var $sessionStart, $session, $sessionHash;

	var $userdata = array();

    function __construct()
    {
        if (isset($_SESSION['ID64']) && defined('STEAM_ADMIN') && $_SESSION['ID64'] == STEAM_ADMIN) {
            $this->ok = true;
            $this->error = false;
            $this->session = true;
            $this->username = 'SteamAdmin';
            $this->userdata['acclevel'] = 100;
			if (empty($_SESSION['loggedin']) || ($_SESSION['username'] ?? '') !== $this->username || (int) ($_SESSION['acclevel'] ?? 0) !== 100) {
				startAdminSession(array(
					'loggedin' => 1,
					'username' => $this->username,
					'acclevel' => 100,
					'authsessionStart' => time(),
				));
			} else {
				$_SESSION['acclevel'] = 100;
			}
            return;
        }
        elseif (defined('STEAM_API') && !empty(STEAM_API) &&
                defined('STEAM_ADMIN') && !empty(STEAM_ADMIN)) {
            clearAdminSession();
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
        elseif (isset($_POST['authusername']) && isset($_POST['authpassword'])) {
            $this->username = valid_request($_POST['authusername'] ?? '', false);
            $this->password = valid_request($_POST['authpassword'] ?? '', false);
            $this->savepass = valid_request(isset($_POST['authsavepass'])?$_POST['authsavepass']:0, false);
            $this->sessionStart = 0;

            $this->session = false;

            if ($this->checkPass() === true) {
				startAdminSession(array(
					'username' => $this->username,
					'authpasswordhash' => $this->userdata['password'],
					'authsessionStart' => time(),
					'acclevel' => $this->userdata['acclevel'],
					'loggedin' => 1,
				));
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            }
        }
        elseif (!empty($_SESSION['loggedin'])) {
			$this->username     = $_SESSION['username'] ?? '';
			$this->sessionHash  = $_SESSION['authpasswordhash'] ?? '';
            $this->savepass     = 0;
			$this->sessionStart = $_SESSION['authsessionStart'] ?? 0;
			$this->ok           = false;
			$this->error        = false;
            $this->session      = true;

			$this->checkSession();
        }
        else {
            $this->ok      = false;
            $this->error   = false;
            $this->session = false;

            $this->printAuth();
        }
    }

	function checkSession()
	{
		global $db;

		if ($this->sessionStart <= (time() - 3600)) {
			clearAdminSession();
			$this->ok = false;
			$this->error = 'Your session has expired. Please try again.';
			$this->printAuth();
			return false;
		}

		if (empty($this->username) || empty($this->sessionHash)) {
			clearAdminSession();
			$this->ok = false;
			$this->error = false;
			$this->printAuth();
			return false;
		}

		$db->query("
			SELECT
				*
			FROM
				hlstats_Users
			WHERE
				username='" . $db->escape($this->username) . "'
			LIMIT 1
		");

		if ($db->num_rows() != 1) {
			clearAdminSession();
			$this->ok = false;
			$this->error = 'The username you supplied is not valid.';
			$this->printAuth();
			return false;
		}

		$this->userdata = $db->fetch_array();
		$db->free_result();

		if (!hash_equals((string) $this->userdata['password'], (string) $this->sessionHash)) {
			clearAdminSession();
			$this->ok = false;
			$this->error = false;
			$this->printAuth();
			return false;
		}

		$this->ok = true;
		$this->error = false;
		$_SESSION['acclevel'] = $this->userdata['acclevel'];

		return true;
	}

    function checkPass()
    {
        global $db;

        $db->query("
            SELECT
                *
            FROM
                hlstats_Users
            WHERE
                username='" . $db->escape($this->username) . "'
            LIMIT 1
        ");

        if ($db->num_rows() == 1) {
            $this->userdata = $db->fetch_array();
            $db->free_result();

            if (md5($this->password) == $this->userdata['password']) {
                $this->ok    = true;
                $this->error = false;

                if ($this->sessionStart > (time() - 3600)) {
                    $this->doCookies();
                    return true;
                }
                elseif ($this->sessionStart) {
                    if ($this->savepass) {
                        $this->doCookies();
                        return true;
                    } else {
                        $this->ok      = false;
                        $this->error   = 'Your session has expired. Please try again.';
                        $this->password = '';
                        $this->printAuth();
                        return false;
                    }
                }
                elseif (!$this->session) {
                    $this->doCookies();
                    return true;
                } else {
                    $this->printAuth();
                    return false;
                }
            } else {
                $this->ok = false;
                if ($this->session) {
                    $this->error = false;
                } else {
                    $this->error = 'The password you supplied is incorrect.';
                }
                $this->password = '';
                $this->printAuth();
            }
        } else {
            $this->ok    = false;
            $this->error = 'The username you supplied is not valid.';
            $this->printAuth();
        }
    }

	function doCookies()
	{
		return;
		setcookie('authusername', $this->username, time() + 31536000, '', '', 0);

		if ($this->savepass)
		{
			setcookie('authpassword', $this->password, time() + 31536000, '', '', 0);
		}
		else
		{
			setcookie('authpassword', $this->password, 0, '', '', 0);
		}
		setcookie('authsavepass', $this->savepass, time() + 31536000, '', '', 0);
		setcookie('authsessionStart', time(), 0, '', '', 0);
	}

	function printAuth()
	{
		global $g_options;

		include (PAGE_PATH . '/adminauth.php');
	}
}

class AdminTask
{
	var $title = '';
	var $acclevel = 0;
	var $type = '';
	var $description = '';
	var $group = '';

	function __construct($title, $acclevel, $type = 'general', $description = '', $group = '')
	{
		$this->title = $title;
		$this->acclevel = $acclevel;
		$this->type = $type;
		$this->description = $description;
		$this->group = $group;
	}
}

class EditList
{
	var $columns;
	var $keycol;
	var $table;
	var $deleteCallback;
	var $icon;
	var $showid;
	var $drawDetailsLink;
	var $DetailsLink;

	var $errors;
	var $newerror;

	var $helpTexts;
	var $helpKey;
	var $helpDIV;

	function __construct($keycol, $table, $icon, $showid = true, $drawDetailsLink = false, $DetailsLink = '', $deleteCallback = null)
	{
		$this->keycol = $keycol;
		$this->table = $table;
		$this->icon = $icon;
		$this->showid = $showid;
		$this->drawDetailsLink = $drawDetailsLink;
		$this->DetailsLink = $DetailsLink;
		$this->helpKey = '';
		$this->deleteCallback = $deleteCallback;
	}

	function setHelp($div, $key, $texts)
	{
		$this->helpDIV = $div;
		$this->helpKey = $key;
		$this->helpTexts = $texts;

		$returnstr = '';

		if ($this->helpKey != '')
		{
			$returnstr .= "<script type='text/javascript'>\n";
			$returnstr .= "var texts = new Array();\n";
			foreach (array_keys($this->helpTexts) as $key)
			{
				$value = $this->helpTexts[$key];
				$value = str_replace('"', "'", $value);
				$value = preg_replace("/[\r\n]/", " ", $value);
				$returnstr .= "texts[\"" . $key . "\"] = \"" . $value . "\";\n";
			}

			$returnstr .= "\n\nfunction showHelp (keyname) {\n";
			$returnstr .= "document.getElementById('" . $this->helpDIV . "').innerHTML=texts[keyname];\n";
			$returnstr .= "document.getElementById('" . $this->helpDIV . "').style.visibility='visible';\n";
			$returnstr .= "}\n";
			$returnstr .= "\n\nfunction hideHelp () {\n";
			$returnstr .= "document.getElementById('" . $this->helpDIV . "').style.visibility='hidden';\n";
			$returnstr .= "}\n";
			$returnstr .= "</script>\n";

			$returnstr .= '<div class="helpwindow" ID="' . $this->helpDIV . '">No help text available</div>';

		}
		return $returnstr;
	}

	function update()
	{
		global $db;

		$okcols = 0;
		$qcols ='';
		$qvals ='';
		foreach ($this->columns as $col) {
			$value = (isset($_POST["new_$col->name"]) && $_POST["new_$col->name"] !== '') ? mystripslashes($_POST["new_$col->name"]) : '';

			if ($value != '')
			{
				if ($col->type == 'ipaddress' && !preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $value))
				{
					$this->errors[] = "Column '$col->title' requires a valid IP address for new row";
					$this->newerror = true;
					$okcols++;
				}
				else
				{
					if ($qcols)
					{
						$qcols .= ', ';
					}
					$qcols .= $col->name;

					if ($qvals)
					{
						$qvals .= ', ';
					}

					if ($col->type == 'password' && $col->name != 'rcon_password')
					{
						$value = md5($value);
					}
					$qvals .= "'" . $db->escape($value) . "'";

					if ($col->type != 'select' && $col->type != 'hidden' && $value != $col->datasource)
					{
						$okcols++;
					}
				}
			}
			elseif ($col->required)
			{
				$this->errors[] = "Required column '$col->title' must have a value for new row";
				$this->newerror = true;
			}
		}

		if ($okcols > 0 && !$this->errors)
		{
			$db->query("
					INSERT INTO
						$this->table
						(
							$qcols
						)
					VALUES
					(
						$qvals
					)");
		}
		elseif ($okcols == 0)
		{
			$this->errors = array();
			$this->newerror = false;
		}

		if (!isset($_POST['rows']) || !is_array($_POST['rows']))
		{
			if ($this->error()) {
				return false;
			}
			return true;
		}

		foreach ($_POST['rows'] as $row)
		{
			if (!empty($_POST[$row . '_delete'])) {
				if (!empty($this->deleteCallback) && is_callable($this->deleteCallback)) {
					call_user_func($this->deleteCallback, $row);
				}

				$db->query("
					DELETE FROM
						$this->table
					WHERE
						$this->keycol='" . $db->escape($row) . "'
				");
			}
			else
			{
				$rowerror = false;

				$query = "UPDATE $this->table SET ";
				$i = 0;
				foreach ($this->columns as $col)
				{
					if ($col->type == 'readonly')
					{
						continue;
					}

					$value = (isset($_POST[$row . "_" . $col->name]) && $_POST[$row . "_" . $col->name] !== '') ? mystripslashes($_POST[$row . "_" . $col->name]) : null;

					if ($col->type == 'checkbox' && $value == ('' || null))
					{
						$value = '0';
					}
					if ($col->type == 'numeric' && $value == ('' || null))
					{
						$value = 0;
					}

					if ($col->type == 'password' && $value == '(encrypted)')
					{
						continue;
					}

					if ($value == '' && $col->required)
					{
						$this->errors[] = "Required column '$col->title' must have a value for row '$row'";
						$rowerror = true;
					}
					elseif ($col->type == "ipaddress" && !preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/", $value))
					{
						$this->errors[] = "Column '$col->title' requires a valid IP address for row '$row'";
						$rowerror = true;
					}

					if ($i > 0)
					{
						$query .= ', ';
					}

					if ($col->type == 'password' && $col->name != 'rcon_password')
					{
						$query .= $col->name . "='" . md5($value) . "'";
					}
					else
					{
						$query .= $col->name . "='" . $db->escape($value) . "'";
					}
					$i++;
				}
				$query .= " WHERE $this->keycol='" . $db->escape($row) . "'";

				if (!$rowerror)
				{
					$db->query($query);
				}
			}
		}

		if ($this->error()) {
			return false;
		}

        return true;
	}

	function draw($result, $draw_new = true)
	{
		global $g_options, $db;
?>
<div class="responsive-table">
<table class="responsive-task">
<thead>
<tr>
<?php
		if ($this->showid) echo '<th>ID</th>';

		foreach ($this->columns as $col) {
			if ($col->type == 'hidden') continue;
			echo '<th class="left">' . $col->title . "</th>\n";
		}

		if ($this->drawDetailsLink) echo '<th></th>';

		echo '<th>Delete</th>';
?>
</tr>
</thead>
<tbody>
<?php
		while ($rowdata = $db->fetch_array($result)) {
			echo '<tr>';

			if ($this->showid)
				echo '<td data-label="ID" class="nowrap">' . $rowdata[$this->keycol] . '</td>';

			$this->drawfields($rowdata, false, false);

			if ($this->drawDetailsLink) {
				global $gamecode;
				echo '<td class="nowrap" data-label="Details"><a href="' . $g_options['scripturl']
					. '?mode=admin&amp;game=' . $gamecode
					. '&amp;task=' . $this->DetailsLink
					. '&amp;key=' . $rowdata[$this->keycol]
					. '">Configure</a></td>';
			}

			echo '<td data-label="Delete"><input type="checkbox" name="'
				. htmlspecialchars($rowdata[$this->keycol])
				. '_delete" value="1"></td>';
			echo "</tr>\n";
		}
?>
</tbody>
<?php if ($draw_new): ?>
<tfoot>
<tr>
	<?php if ($this->showid) echo '<td>&nbsp;</td>'; ?>
	<?php
		if ($this->newerror)
			$this->drawfields($_POST, true, true);
		else
			$this->drawfields([], true);
		if ($this->drawDetailsLink) echo '<td></td>';
	?>
</tr>
</tfoot>
<?php endif; ?>
</table>
</div>
<?php
	}

	function drawfields($rowdata = array(), $new = false, $stripslashes = false)
	{
		global $g_options, $db;

		$i = 0;
		foreach ($this->columns as $col)
		{
			if ($new)
			{
				$keyval = 'new';
				$rowdata[$col->name] = $rowdata["new_$col->name"] ?? null;
				if ($stripslashes)
					$rowdata[$col->name] = mystripslashes($rowdata[$col->name]);
			}
			else
			{
				$keyval = $rowdata[$this->keycol];
				if ($stripslashes)
					$keyval = mystripslashes($keyval);

			}

			if ($col->type != 'hidden')
			{
				echo '<td class="left" data-label="' . htmlspecialchars($col->title) . '">';
			}

			if ($i == 0 && !$new)
			{
				echo '<input type="hidden" name="rows[]" value="' . htmlspecialchars($keyval) . '" />';
			}

			if ($col->maxlength < 1)
			{
				$col->maxlength = '';
			}

			switch ($col->type)
			{
				case 'select':
					unset($coldata);

					// for manual datasource in format "key/value;key/value" or "key;key"
					foreach (explode(';', $col->datasource) as $v)
					{
						$sections = preg_match_all('/\//', $v, $dsaljfdsaf);
						if ($sections == 2)
						{
							// for SQL datasource in format "table.column/keycolumn/where"
							list($col_table, $col_col) = explode('.', $v);
							list($col_col, $col_key, $col_where) = explode('/', $col_col);
							if ($col_where)
							{
								$col_where = "WHERE $col_where";
							}
							$col_result = $db->query("SELECT $col_key, $col_col FROM $col_table $col_where ORDER BY $col_col");
							$coldata = array();
							while (list($a, $b) = $db->fetch_row($col_result))
							{
								$coldata[$a] = $b;
							}
						}
						else if ($sections > 0)
						{
							list($a, $b) = explode('/', $v);
							$coldata[$a] = $b;
						}
						else
						{
							$coldata[$v] = $v;
						}
					}

					echo "<select name=\"" . $keyval . "_$col->name\">\n";

					if (!$col->required)
					{
						echo "<option value=\"\"></option>\n";
					}

					$gotcval = false;

					foreach ($coldata as $k => $v)
					{
						if ($rowdata[$col->name] == $k)
						{
							$selected = ' selected="selected"';
							$gotcval = true;
						}
						else
						{
							$selected = '';
						}

						echo "<option value=\"$k\"$selected>$v</option>\n";
					}

					if (!$gotcval)
					{
						echo '<option value="' . $rowdata[$col->name] . '" selected="selected">' . $rowdata[$col->name] . "</option>\n";
					}

					echo '</select>';
					break;

				case 'checkbox':
					$selectedval = '1';
					$value = $rowdata[$col->name];

					if ($value == $selectedval)
					{
						$selected = ' checked="checked"';
					}
					else
					{
						$selected = '';
					}

					echo '<center><input type="checkbox" name="' . $keyval . "_$col->name\" value=\"$selectedval\"$selected /></center>";
					break;

				case 'hidden':
					echo '<input type="hidden" name="' . $keyval . "_$col->name\" value=\"" . htmlspecialchars($col->datasource) . '" />';
					break;

				case 'readonly':
					if (!$new)
					{
						echo html_entity_decode($rowdata[$col->name]);
						break;
					}
					/* else fall through to default */

				default:
					$onclick = '';
					if ($col->type == 'password') {
						$onclick = " onclick=\"if (this.value == '(encrypted)') this.value='';\"";
					}

					if ($col->datasource != '' && !isset($rowdata[$col->name]))
					{
						$value = $col->datasource;
					}
					else
					{
						$value = $rowdata[$col->name];
					}

					$onClick = '';
					if (!empty($this->helpKey) && !empty($rowdata[$this->helpKey])) {
						$onClick = "onmouseover=\"javascript:showHelp('" . strtolower($rowdata[$this->helpKey]) . "')\" onmouseout=\"javascript:hideHelp()\"";
					}

					$input_value = $value;

					echo "<input $onClick type=\"text\" name=\"" . $keyval . "_$col->name\" size=$col->width " . "value=\"" . $input_value . "\" class=\"textbox\"" . " maxlength=\"$col->maxlength\"$onclick />";
			}

			if ($col->type != 'hidden')
			{
				echo "</td>\n";
			}

			$i++;
		}
	}

	function error()
	{
		if (is_array($this->errors))
		{
			return implode("<br /><br />\n\n", $this->errors);
		}
		else
		{
			return false;
		}
	}
}

class EditListColumn
{
	var $name;
	var $title;
	var $width;
	var $required;
	var $type;
	var $datasource;
	var $maxlength;

	function __construct($name, $title, $width = 20, $required = false, $type = 'text', $datasource = '', $maxlength = 0)
	{
		$this->name = $name;
		$this->title = $title;
		$this->width = $width;
		$this->required = $required;
		$this->type = $type;
		$this->datasource = $datasource;
		$this->maxlength = intval($maxlength);
	}
}

class PropertyPage
{
	var $table;
	var $keycol;
	var $keyval;
	var $propertygroups = array();

	function __construct($table, $keycol, $keyval, $groups)
	{
		$this->table = $table;
		$this->keycol = $keycol;
		$this->keyval = $keyval;
		$this->propertygroups = $groups;
	}

	function draw($data)
	{
		foreach ($this->propertygroups as $group)
		{
			$group->draw($data);
		}
	}

	function update()
	{
		global $db;

		$setstrings = array();
		foreach ($this->propertygroups as $group)
		{
			foreach ($group->properties as $prop)
			{
				$value = isset($_POST[$prop->name]) ? $_POST[$prop->name] : '';
				if ($prop->name == 'name')
				{
					$search_pattern = array('/script/i', '/;/', '/%/');
					$replace_pattern = array('', '', '');
					$value = preg_replace($search_pattern, $replace_pattern, $value);
					$setstrings[] = $prop->name . "='" . $db->escape($value) . "'";
				}
				else
				{
					$setstrings[] = $prop->name . "='" . $db->escape(valid_request($value, 0)) . "'";
				}
			}
		}

		return (bool) $db->query("
			UPDATE
				" . $this->table . "
			SET
				" . implode(",\n", $setstrings) . "
			WHERE
				" . $this->keycol . "='" . $db->escape($this->keyval) . "'
		");
	}
}

class PropertyPage_Group
{
	var $title = '';
	var $properties = array();

	function __construct($title, $properties)
	{
		$this->title = $title;
		$this->properties = $properties;
	}

	function draw($data)
	{
		global $g_options;
?>
<div class="hlstats-admin-propgroup">
<b><?php echo $this->title; ?></b>
<div class="responsive-table">
<table class="responsive-task">
<tbody>
<?php
		foreach ($this->properties as $prop)
		{
			$prop->draw($data[$prop->name]);
		}
?>
</tbody>
</table>
</div>
</div>
<?php
	}
}

class PropertyPage_Property
{
	var $name;
	var $title;
	var $type;
	var $datasource;

	function __construct($name, $title, $type, $datasource = '')
	{
		$this->name = $name;
		$this->title = $title;
		$this->type = $type;
		$this->datasource = $datasource;
	}

	function draw($value)
	{
		global $g_options;
?>
<tr>
	<td class="left"><?php echo $this->title . ':'; ?></td>
	<td class="left"><?php
		switch ($this->type)
		{
			case 'textarea':
				echo "<textarea name=\"$this->name\" cols=35 rows=4 wrap=\"virtual\">" . htmlspecialchars($value) . '</textarea>';
				break;

			case 'select':
				$coldata = array();
				foreach (explode(';', $this->datasource) as $v)
				{
					if ($v === '')
					{
						continue;
					}
					if (preg_match('/\//', $v))
					{
						list($a, $b) = explode('/', $v);
						$coldata[$a] = $b;
					}
					else
					{
						$coldata[$v] = $v;
					}
				}

				echo getSelect($this->name, $coldata, $value);
				break;

			default:
				echo "<input type=\"text\" name=\"$this->name\" size=35 value=\"" . htmlspecialchars($value ?? "") . "\" class=\"textbox\" />";
				break;
		}
?></td>
</tr>
<?php
	}
}

function checkVersion() {

    global $g_options;
    $needsupdate = false;
    echo '<div class="panel">';
    if (file_exists("./updater/" . ((int)$g_options['dbversion'] + 1) . ".php")) {
        message('warning','Your database needs an upgrade. To perform a Database Update, please go to the Updater page.');
        $needsupdate= true;
    } else {
        if (file_exists("./updater/" . ((int)$g_options['dbversion']) . ".php")) {
            message('success','Great. Your database is the latest version.');
    } else {
            message('warning','Your database needs an upgrade. To perform a Database Update, please go to the Updater page.');
            $needsupdate= true;
       }
    }

    if (empty($_SESSION['HLZ'])) {
        $fetched = getHLVersion('SnipeZilla', 'HLSTATS-2');
        if ($fetched) {
            $_SESSION['HLZ'] = $fetched;
        }
    }
    $latestVersion = $_SESSION['HLZ'] ?? null;

    $HLZ_version = '<div><span class="hlstats-name">✔️ Current Daemon Version:</span> <span>'. $g_options['version'] .'</span></div>';
    if ($latestVersion && version_compare($g_options['version'], $latestVersion, '<')) {
        $HLZ_version = '<div><span class="hlstats-name">⚠️Current Daemon Version:</span> <span>'. $g_options['version'] .'</span>
                        &rarr;
                        <a href="https://github.com/SnipeZilla/HLSTATS-2/releases/tag/'.$latestVersion.'" target="_blank">'.$latestVersion.'</a></div>';
    }
    $WEB_version = file_exists("./updater/" . ((int)$g_options['dbversion'] + 1) . ".php") ? '❌ ' : '✔️ ';

    echo $HLZ_version;
    echo '<div><span class="hlstats-name">'.$WEB_version.'Current DB version:</span> <span>'. $g_options['dbversion'] .'</span></div>';
    $version=phpversion();
    echo '<div style="margin:15px 0;">';
      echo '<div>'.(version_compare($version, '8.4.0', '<') ? "❌ PHP version $version isn't high enough" : "✔️ PHP version $version").'</div>';
      echo '<div>'.(!extension_loaded('curl') ? "❌ PHP extension 'curl' is not loaded" : "✔️ PHP extension 'curl' is loaded").'</div>';
      echo '<div>'.(!extension_loaded('gd') ? "❌ PHP extension 'gd' is not loaded" : "✔️ PHP extension 'gd' is loaded").'</div>';
      echo '<div>'.(!extension_loaded('mbstring') ? "❌ PHP extension 'mbstring' is not loaded" : "✔️ PHP extension 'mbstring' is loaded").'</div>';
      echo '<div>'.(!extension_loaded('sockets') ? "❌ PHP extension 'sockets' is not loaded" : "✔️ PHP extension 'sockets' is loaded").'</div>';
    echo '</div>';
    
    if ($needsupdate) {
        echo '<form method="post" action="?mode=admin&task=updater" name="updater" class="hlstats-updater">
            <input type="hidden" name="run" value="1">
            <div class="hlstats-admin-apply">
                <button type="submit" class="submit">Update</button>
            </div>
        </form>';
    }
    
    echo '<div class="hlstats-admin-note" style="margin-top:15px;">
            <p class="center">You can force an update (Useful if you are  coming from a different HLstats version)</p>
            <form method="post" action="?mode=admin&task=updater" name="updater" class="hlstats-updater">
                <input type="hidden" name="force" value="1">
                <div class="hlstats-admin-apply">
                    <button type="submit" class="submit">Force Update</button>
                </div>
            </form>
        </div>';
    echo '</div>';
}

function getHLVersion(string $owner, string $repo): ?string {
    $url = "https://api.github.com/repos/$owner/$repo/releases/latest";

    // Initialize cURL
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_USERAGENT       => 'hlstatsz-version-checker/1.0',
        CURLOPT_TIMEOUT         => 5,
        CURLOPT_FOLLOWLOCATION  => true,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_SSL_VERIFYHOST  => 0,
        CURLOPT_HTTPHEADER      => [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ],
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        if (DEBUG) { error_log("cURL Error: " . curl_error($ch) . PHP_EOL, 3, '_error.txt'); }
        $ch=null;
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ch=null;

    if ($httpCode !== 200) {
        if (DEBUG) { error_log("GitHub API returned HTTP code $httpCode" . PHP_EOL, 3, '_error.txt'); }
        return null;
    }

    $data = json_decode($response, true);

    return $data['tag_name'] ?? null;
}

function message($icon, $msg)
{
?>
<div class="hlstats-admin-msg <?php echo ($icon === 'success') ? 'success' : 'warning'; ?>">
	<?php echo ($icon === 'success') ? '✅ ' : '⚠️ '; echo $msg; ?>
</div>
<?php
}


$auth = new Auth;

if($auth->ok===false)
{
	return;
}

$selTask = valid_request(isset($_GET['task']) ? $_GET['task'] : '', false);
$selGame = valid_request(isset($_GET['game']) ? $_GET['game'] : '', false);
if ($selGame !== '') {
	$game = $selGame;
}

$adminTaskTitles = array(
	'options' => 'HLstats Settings',
	'adminusers' => 'Admin Users',
	'games' => 'Games',
	'clantags' => 'Clan Tag Patterns',
	'voicecomm' => 'Voice Servers',
	'newserver' => 'Add Server',
	'servers' => 'Edit Servers',
	'serversettings' => 'Server Details',
	'actions' => 'Actions',
	'teams' => 'Teams',
	'roles' => 'Roles',
	'weapons' => 'Weapons',
	'awards_weapons' => 'Weapon Awards',
	'awards_plyractions' => 'Plyr Action Awards',
	'awards_plyrplyractions' => 'PlyrPlyr Awards',
	'awards_plyrplyractions_victim' => 'PlyrPlyr Awards (Victim)',
	'ranks' => 'Ranks',
	'ribbons' => 'Ribbons',
	'tools_updater' => 'DB Updater',
	'tools_perlcontrol' => 'Daemon Control',
	'tools_editdetails' => 'Edit Player or Clan Details',
	'tools_editdetails_player' => 'Edit Player Details',
	'tools_editdetails_clan' => 'Edit Clan Details',
	'tools_adminevents' => 'Admin Event History',
	'tools_ipstats' => 'Host Statistics',
	'tools_optimize' => 'Optimize Database',
	'tools_reset' => 'Full / Partial Reset',
	'tools_reset_2' => 'Clean up Player Statistics',
	'tools_resetdbcollations' => 'Reset DB Collations',
	'tools_settings_copy' => 'Duplicate Game Settings',
	'tools_hostgroups' => 'Host Groups',
	'hostgroups' => 'Host Groups',
);
$task = (object) array(
	'title' => isset($adminTaskTitles[$selTask]) ? $adminTaskTitles[$selTask] : ucwords(str_replace('_', ' ', $selTask))
);

if (is_ajax() && !empty($_GET['task'])) {
    $taskCode = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['task']);
    if (file_exists(PAGE_PATH . '/admintasks/' . $taskCode . '.php')) {
        $gamecode = !empty($_GET['game']) ? valid_request($_GET['game'], false) : $game;
        $game     = $gamecode;

        ob_start();
        include PAGE_PATH . '/admintasks/' . $taskCode . '.php';
        $content = ob_get_clean();

        if (stripos($content, '<form') === false) {
            $formAction = htmlspecialchars($g_options['scripturl']
                . '?mode=admin&task=' . urlencode($taskCode)
                . (!empty($gamecode) ? '&game=' . urlencode($gamecode) : ''));
            echo '<form method="post" action="' . $formAction . '">';
            echo $content;
            echo '</form>';
        } else {
            echo $content;
        }
    }
    exit;
}
if (!is_ajax()) {
	include (PAGE_PATH . '/header.php');
}

$result = $db->query("
        SELECT
            name,
            code
        FROM
            hlstats_Games
        WHERE
            hidden = '0'
        ORDER BY
            name ASC
        ;
    ");
?>

<div class="hlstats-admin-layout" id="admin-layout">

  <!-- Back button (mobile only) -->
  <button class="hlstats-admin-back" id="admin-back">&#8592; Back to menu</button>

  <!-- Nav sidebar -->
  <div class="hlstats-admin-nav" id="admin-nav">
    <ul class="hlstats-task">

      <li>
        <a href="#general" class="hlstats-admin-task" data-url="">General Settings</a>
        <ul class="hlstats-sub-task">
          <li><a href="#" class="hlstats-admin-task" data-url="options">HLstats Settings</a></li>
          <li><a href="#" class="hlstats-admin-task" data-url="adminusers">Admin Users</a></li>
          <li><a href="#" class="hlstats-admin-task" data-url="games">Games</a></li>
          <li><a href="#" class="hlstats-admin-task" data-url="clantags">Clan Tag Patterns</a></li>
          <li><a href="#" class="hlstats-admin-task" data-url="voicecomm">Voice Servers</a></li>
        </ul>
      </li>

      <li>
        <a href="#game-settings" class="hlstats-admin-task" data-url="">Game Settings</a>
        <ul class="hlstats-sub-task">
          <?php while ($res = $db->fetch_array($result)):
            $gc = htmlspecialchars($res['code']);
            $gn = htmlspecialchars($res['name']);
          ?>
          <li>
            <a href="#<?= $gc ?>" class="hlstats-admin-task" data-url="" data-game="<?= $gc ?>"><?= $gn ?></a>
            <ul class="hlstats-sub-task">
              <li><a href="#" class="hlstats-admin-task" data-url="newserver"  data-game="<?= $gc ?>">Add Server</a></li>
              <li><a href="#" class="hlstats-admin-task" data-url="servers"    data-game="<?= $gc ?>">Edit Servers</a></li>
              <li><a href="#" class="hlstats-admin-task" data-url="actions"    data-game="<?= $gc ?>">Actions</a></li>
              <li><a href="#" class="hlstats-admin-task" data-url="teams"      data-game="<?= $gc ?>">Teams</a></li>
              <li><a href="#" class="hlstats-admin-task" data-url="roles"      data-game="<?= $gc ?>">Roles</a></li>
              <li><a href="#" class="hlstats-admin-task" data-url="weapons"    data-game="<?= $gc ?>">Weapons</a></li>
              <li><a href="#" class="hlstats-admin-task" data-url="awards_weapons"              data-game="<?= $gc ?>">Weapon Awards</a></li>
              <li><a href="#" class="hlstats-admin-task" data-url="awards_plyractions"          data-game="<?= $gc ?>">Plyr Action Awards</a></li>
              <li><a href="#" class="hlstats-admin-task" data-url="awards_plyrplyractions"      data-game="<?= $gc ?>">PlyrPlyr Awards</a></li>
              <li><a href="#" class="hlstats-admin-task" data-url="awards_plyrplyractions_victim" data-game="<?= $gc ?>">PlyrPlyr Awards (Victim)</a></li>
              <li><a href="#" class="hlstats-admin-task" data-url="ranks"      data-game="<?= $gc ?>">Ranks</a></li>
              <li><a href="#" class="hlstats-admin-task" data-url="ribbons"    data-game="<?= $gc ?>">Ribbons</a></li>
            </ul>
          </li>
          <?php endwhile; ?>
        </ul>
      </li>

      <li>
        <a href="#tools" class="hlstats-admin-task" data-url="">Tools</a>
        <ul class="hlstats-sub-task">
          <li><a href="#" class="hlstats-admin-task" data-url="updater">Updater</a></li>
          <li><a href="#" class="hlstats-admin-task" data-url="tools_perlcontrol">Daemon Control</a></li>
          <li><a href="#" class="hlstats-admin-task" data-url="tools_editdetails">Edit Player/Clan</a></li>
          <li><a href="#" class="hlstats-admin-task" data-url="tools_adminevents">Admin Event History</a></li>
          <li><a href="#" class="hlstats-admin-task" data-url="tools_optimize">Optimize Database</a></li>
          <li><a href="#" class="hlstats-admin-task" data-url="tools_reset">Full / Partial Reset</a></li>
          <li><a href="#" class="hlstats-admin-task" data-url="tools_reset_2">Clean up Player Statistics</a></li>
          <li><a href="#" class="hlstats-admin-task" data-url="tools_resetdbcollations">Reset DB Collations</a></li>
          <li><a href="#" class="hlstats-admin-task" data-url="tools_settings_copy">Duplicate Game Settings</a></li>
        </ul>
      </li>

    </ul>
  </div>

  <div class="hlstats-admin-panel" id="admin-panel">
    <div class="hlstats-admin-panel-title" id="admin-panel-title"></div>
<div id="admin-hint">

<?php
checkVersion();

?>


    </div>
  </div>

</div>
<script>
(function () {
    const layout     = document.getElementById('admin-layout');
    const panel      = document.getElementById('admin-panel');
    const backBtn    = document.getElementById('admin-back');
    const panelTitle = document.getElementById('admin-panel-title');
    const hint       = document.getElementById('admin-hint');
    const scriptUrl  = <?= json_encode($g_options['scripturl']) ?>;
    const baseGame   = <?= json_encode($game) ?>;
    const titles     = <?= json_encode($adminTaskTitles) ?>;
    let   activeLink = null;
    let   skipPush   = false;


    function clearPanel() {
        [...panel.children]
            .filter(el => el !== panelTitle && el !== hint)
            .forEach(el => el.remove());
    }

    function setPanelHtml(html) {
        clearPanel();
        hint.style.display = 'none';
        panelTitle.insertAdjacentHTML('afterend', html);

        // Re-execute inline scripts inserted via innerHTML
        panel.querySelectorAll('script').forEach(oldScript => {
            const s = document.createElement('script');
            Array.from(oldScript.attributes).forEach(a => s.setAttribute(a.name, a.value));
            s.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(s, oldScript);
        });
    }

    function showPanel(title) {
        panelTitle.textContent = title;
        layout.classList.add('panel-active');
        panel.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function showNav(push = false) {
        layout.classList.remove('panel-active');
        clearPanel();
        panelTitle.textContent = '';
        hint.style.display = '';
        if (activeLink) activeLink.classList.remove('hlstats-admin-active');
        activeLink = null;
        if (push) history.pushState({}, '', scriptUrl + '?mode=admin');
    }

    /** Build an AJAX fetch URL from params. Always includes mode=admin. */
    function buildFetchUrl(params) {
        const u = new URL(scriptUrl, window.location.href);
        u.searchParams.set('mode', 'admin');
        for (const [k, v] of Object.entries(params)) {
            if (k !== 'mode' && v) u.searchParams.set(k, v);
        }
        return u.toString();
    }

    /** Build a browser-visible URL from params. */
    function buildBrowserUrl(params) {
        const u = new URLSearchParams();
        u.set('mode', 'admin');
        for (const [k, v] of Object.entries(params)) {
            if (k !== 'mode' && v) u.set(k, v);
        }
        return scriptUrl + '?' + u.toString();
    }

    /** Highlight the sidebar link matching task+game. */
    function highlightNav(task, game) {
        if (activeLink) {
            activeLink.classList.remove('hlstats-admin-active');
            activeLink.closest('li')?.classList.remove('active');
        }
        activeLink = null;

        let match = document.querySelector(
            `.hlstats-admin-task[data-url="${CSS.escape(task)}"][data-game="${CSS.escape(game)}"]`
        );
        if (!match) {
            match = document.querySelector(`.hlstats-admin-task[data-url="${CSS.escape(task)}"]`);
        }
        if (match) {
            match.classList.add('hlstats-admin-active');
            activeLink = match;
            // Expand ancestors
            let p = match.closest('li');
            while (p) { p.classList.add('active'); p = p.parentElement?.closest('li'); }
        }
    }

    /**
     * Load a task into the panel.
     * @param {Object} params  - All query params (task, game, id, key, ...)
     * @param {string} title   - Panel title
     * @param {boolean} push   - Whether to pushState
     */
    async function loadTask(params, title, push) {
        const task = params.task || '';
        const game = params.game || baseGame;

        showPanel(title);
        highlightNav(task, game);

        // Update browser URL
        if (push && !skipPush) {
            history.pushState({ params, title }, '', buildBrowserUrl(params));
        }

        panel.classList.add('is-loading');
        try {
            const res = await fetch(buildFetchUrl(params), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error(await res.text());
            const reader  = res.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let html = '';
            let lastUpdate = 0;
            clearPanel();
            hint.style.display = 'none';
            const streamEl = document.createElement('div');
            panelTitle.insertAdjacentElement('afterend', streamEl);
            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                html += decoder.decode(value, { stream: true });
                const now = performance.now();
                if (now - lastUpdate > 50) {
                    streamEl.innerHTML = html;
                    lastUpdate = now;
                }
            }
            panel.classList.remove('is-loading');
            setPanelHtml(html);
            bindPanel(task, game);
        } catch (err) {
            panel.classList.remove('is-loading');
            setPanelHtml('<p class="hlstats-admin-error">Failed to load task. Please try again.</p>');
        }
    }

    function bindPanel(taskUrl, game) {
        bindForms(taskUrl, game);
        bindAdminLinks(game);
    }

    /** Intercept form submits so they stay in the panel. */
    function bindForms(taskUrl, game) {
        panel.querySelectorAll('form').forEach(form => {
            if (form.dataset.bound) return;
            form.dataset.bound = '1';

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                panel.classList.add('is-loading');

                const fd     = new FormData(form);
                const method = (form.method || 'GET').toUpperCase();
                let fetchUrl, fetchOpts;

                if (method === 'GET') {
                    const url = new URL(form.action || window.location.href, window.location.href);
                    fd.forEach((v, k) => url.searchParams.append(k, v));
                    fetchUrl  = url.toString();
                    fetchOpts = { headers: { 'X-Requested-With': 'XMLHttpRequest' } };
                } else {
                    fetchUrl  = form.action;
                    fetchOpts = {
                        method: 'POST',
                        body:   fd,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    };
                }

                try {
                    const res = await fetch(fetchUrl, fetchOpts);
                    if (!res.ok) throw new Error(await res.text());
                    const reader  = res.body.getReader();
                    const decoder = new TextDecoder('utf-8');
                    let html = '';
                    let lastUpdate = 0;
                    clearPanel();
                    hint.style.display = 'none';
                    const streamEl = document.createElement('div');
                    panelTitle.insertAdjacentElement('afterend', streamEl);
                    while (true) {
                        const { done, value } = await reader.read();
                        if (done) break;
                        html += decoder.decode(value, { stream: true });
                        const now = performance.now();
                        if (now - lastUpdate > 50) {
                            streamEl.innerHTML = html;
                            lastUpdate = now;
                        }
                    }
                    panel.classList.remove('is-loading');
                    if (taskUrl === 'games') {
                        window.location.href = buildBrowserUrl({ task: 'games' });
                        return;
                    }
                    setPanelHtml(html);
                    bindPanel(taskUrl, game);
                } catch (err) {
                    panel.classList.remove('is-loading');
                    setPanelHtml('<p class="hlstats-admin-error">Request failed. Please try again.</p>');
                }
            });
        });

        // Password eye-toggle
        panel.querySelectorAll('.toggle-eye').forEach(btn => {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', () => {
                const input = btn.closest('.password-wrapper')
                    ?.querySelector('input[type="password"],input[type="text"]');
                if (!input) return;
                const hide      = input.type === 'password';
                input.type      = hide ? 'text' : 'password';
                btn.textContent = hide ? '🔓' : '🔒';
            });
        });
    }

    /**
     * Intercept <a> links inside the panel that point to mode=admin&task=...
     * so they load via AJAX instead of full-page reload.
     */
    function bindAdminLinks(currentGame) {
        panel.querySelectorAll('a[href]').forEach(a => {
            if (a.dataset.bound) return;

            let url;
            try { url = new URL(a.href, window.location.href); } catch { return; }
            const p = url.searchParams;
            if (p.get('mode') !== 'admin' || !p.get('task')) return;

            a.dataset.bound = '1';
            a.addEventListener('click', e => {
                e.preventDefault();

                // Collect all params from the link
                const params = {};
                for (const [k, v] of p.entries()) {
                    if (k !== 'mode') params[k] = v;
                }
                if (!params.game) params.game = currentGame;

                const title = titles[params.task] || a.textContent.trim() || params.task;
                loadTask(params, title, true);
            });
        });
    }

    // Back button (mobile)
    backBtn.addEventListener('click', showNav);

    // Sidebar nav links
    document.querySelectorAll('.hlstats-admin-task').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();

            const li      = link.closest('li');
            const taskUrl = link.dataset.url  || '';
            const game    = link.dataset.game || baseGame;
            const title   = link.textContent.trim();

            // Branch nodes: toggle open/close (accordion)
            if (!taskUrl) {
                const isActive = li.classList.contains('active');
                li.parentElement?.querySelectorAll(':scope > li').forEach(sib => {
                    sib.classList.remove('active');
                    sib.querySelectorAll('li').forEach(desc => desc.classList.remove('active'));
                });
                if (!isActive) {
                    li.classList.add('active');
                } else {
                    showNav(true);
                }
                return;
            }

            // Expand ancestors
            let parent = li.parentElement?.closest('li');
            while (parent) {
                parent.classList.add('active');
                parent = parent.parentElement?.closest('li');
            }

            loadTask({ task: taskUrl, game: game }, title, true);
        });
    });

    // Browser back/forward
    window.addEventListener('popstate', (e) => {
        if (e.state && e.state.params && e.state.params.task) {
            skipPush = true;
            loadTask(e.state.params, e.state.title || '', false);
            skipPush = false;
        } else {
            showNav();
        }
    });

    // Bind hint-section forms (e.g. Update button) as AJAX POST
    hint.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            showPanel(titles['updater'] || 'DB Updater');
            highlightNav('updater', baseGame);
            history.pushState({ params: { task: 'updater' }, title: titles['updater'] || 'DB Updater' }, '', buildBrowserUrl({ task: 'updater' }));
            panel.classList.add('is-loading');
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) throw new Error(await res.text());
                const reader  = res.body.getReader();
                const decoder = new TextDecoder('utf-8');
                let html = '';
                let lastUpdate = 0;
                clearPanel();
                hint.style.display = 'none';
                const streamEl = document.createElement('div');
                panelTitle.insertAdjacentElement('afterend', streamEl);
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    html += decoder.decode(value, { stream: true });
                    const now = performance.now();
                    if (now - lastUpdate > 50) { streamEl.innerHTML = html; lastUpdate = now; }
                }
                panel.classList.remove('is-loading');
                setPanelHtml(html);
                bindPanel('updater', baseGame);
            } catch (err) {
                panel.classList.remove('is-loading');
                setPanelHtml('<p class="hlstats-admin-error">Update failed. Please try again.</p>');
            }
        });
    });

    // Auto-open from current URL on page load
    const urlParams = new URLSearchParams(window.location.search);
    const initTask  = urlParams.get('task');
    if (initTask) {
        const params = {};
        for (const [k, v] of urlParams.entries()) {
            if (k !== 'mode') params[k] = v;
        }
        if (!params.game) params.game = baseGame;

        const title = titles[initTask] || initTask;

        skipPush = true;
        history.replaceState({ params, title }, '', window.location.href);
        loadTask(params, title, false);
        skipPush = false;
    }
}());
</script>