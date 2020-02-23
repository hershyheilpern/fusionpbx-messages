<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2019
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes
	include "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('message_number_add') || permission_exists('message_number_edit') || permission_exists('message_number_delete')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get the message_number and save it as a variable
	if (isset($_REQUEST["message_number"])) {
		$message_number = $_REQUEST["message_number"];
	}

//set the action as an add or an update
	if (is_uuid($_REQUEST["id"])) {
		$action = "update";
		$message_number_uuid = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}

//get the http post values and set them as php variables
	if (count($_POST) > 0) {
		//set the variables
		$message_number_name = $_POST["message_number_name"];
		$message_number = $_POST["message_number"];
		$message_number_enabled = $_POST["enabled"];
		$message_number_description = $_POST["message_number_description"];
		//$message_key = $_POST["message_key"];
		$message_provider = $_POST["message_provider"];
	}

//delete the user from the message_number users
	if (is_uuid($_REQUEST["user_uuid"]) && is_uuid($_REQUEST["id"]) && $_GET["a"] == "delete" && permission_exists("message_number_delete")) {
		//set the variables
			$user_uuid = $_REQUEST["user_uuid"];
			$message_number_uuid = $_REQUEST["id"];

		//delete the group from the users
			$array['message_users'][0]['domain_uuid'] = $_SESSION['domain_uuid'];
			$array['message_users'][0]['message_number_uuid'] = $message_number_uuid;
			$array['message_users'][0]['user_uuid'] = $user_uuid;

			$p = new permissions;
			$p->add('message_user_delete', 'temp');

			$database = new database;
			$database->app_name = 'message_number';
			$database->app_uuid = '4a20815d-042c-47c8-85df-085333e79b87';
			$database->delete($array);
			unset($array);

			$p->delete('message_user_delete', 'temp');

		//redirect the browser
			message::add($text['message-delete']);
			header("Location: message_number_edit.php?id=".$message_number_uuid);
			return;
	}

//add the user to the message_number users
	if (is_uuid($_REQUEST["user_uuid"]) && is_uuid($_REQUEST["id"]) && $_GET["a"] != "delete") {
		//set the variables
			$user_uuid = $_REQUEST["user_uuid"];
			$message_number_uuid = $_REQUEST["id"];
		//assign the user to the message number
			$array['message_users'][0]['message_user_uuid'] = uuid();
			$array['message_users'][0]['domain_uuid'] = $_SESSION['domain_uuid'];
			$array['message_users'][0]['message_number_uuid'] = $message_number_uuid;
			$array['message_users'][0]['user_uuid'] = $user_uuid;

			$p = new permissions;
			$p->add('message_user_add', 'temp');

			$database = new database;
			$database->app_name = 'message_number';
			$database->app_uuid = '4a20815d-042c-47c8-85df-085333e79b87';
			$database->save($array);
			unset($array);

			$p->delete('message_user_add', 'temp');

		//redirect the browser
			message::add($text['confirm-add']);
			header("Location: message_number_edit.php?id=".$message_number_uuid);
			return;
	}

//process the data
	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {

		$msg = '';
		if ($action == "update" && is_uuid($_POST["message_number_uuid"]) && permission_exists('message_number_edit')) {
			$message_number_uuid = $_POST["message_number_uuid"];
		}

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: message_numbers.php');
				exit;
			}

		//check for all required data
			if (strlen($message_number) == 0) { $msg .= "".$text['confirm-ext']."<br>\n"; }
			if (strlen($message_number_name) == 0) { $msg .= "".$text['confirm-message_number']."<br>\n"; }
			if (strlen($msg) > 0 && strlen($_POST["persistformvar"]) == 0) {
				require_once "resources/header.php";
				require_once "resources/persist_form_var.php";
				echo "<div align='center'>\n";
				echo "<table><tr><td>\n";
				echo $msg."<br />";
				echo "</td></tr></table>\n";
				persistformvar($_POST);
				echo "</div>\n";
				require_once "resources/footer.php";
				return;
			}

		//add or update the database
			if ($_POST["persistformvar"] != "true") {

				if ($action == "add" && permission_exists('message_number_add')) {
					$message_number_uuid = uuid();
				}

				//begin update array
					$array['message_numbers'][0]['message_number_uuid'] = $message_number_uuid;
					$array['message_numbers'][0]['domain_uuid'] = $_SESSION['domain_uuid'];
					$array['message_numbers'][0]['message_number_name'] = $message_number_name;
					$array['message_numbers'][0]['message_number'] = $message_number;
					//$array['message_numbers'][0]['message_key'] = $message_key;
					$array['message_numbers'][0]['message_provider'] = $message_provider;
					$array['message_numbers'][0]['message_number_enabled'] = $message_number_enabled;
					$array['message_numbers'][0]['message_number_description'] = $message_number_description;
					
				if (is_array($array) && @sizeof($array) != 0) {		
					
						$database = new database;
						$database->app_name = 'messages';
						$database->app_uuid = '4a20815d-042c-47c8-85df-085333e79b87';
						$database->save($array);
						$message = $database->message;
						unset($array);
				}

				//redirect the browser
					if ($action == "update" && permission_exists('message_number_edit')) {
						message::add($text['confirm-update']);
					}
					if ($action == "add" && permission_exists('message_number_add')) {
						message::add($text['confirm-add']);
					}
					header("Location: message_number_edit.php?id=".$message_number_uuid);
					return;

			}
	}

//pre-populate the form
	if (is_uuid($_GET['id']) && $_POST["persistformvar"] != "true") {
		$message_number_uuid = $_GET["id"];
		$sql = "select * from v_message_numbers ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and message_number_uuid = :message_number_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$parameters['message_number_uuid'] = $message_number_uuid;
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && @sizeof($row) != 0) {
			$message_number_name = $row["message_number_name"];
			$message_number = $row["message_number"];
			$message_number_enabled = $row["message_number_enabled"];
			//$message_key = $row["message_key"];
			$message_provider = $row["message_provider"];
			$message_number_description = $row["message_number_description"];
		}
		unset($sql, $parameters, $row);
	}

//get the message_number users
	$sql = "select * from v_message_users as m, v_users as u ";
	$sql .= "where m.user_uuid = u.user_uuid  ";
	$sql .= "and m.domain_uuid = :domain_uuid ";
	$sql .= "and m.message_number_uuid = :message_number_uuid ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$parameters['message_number_uuid'] = $message_number_uuid;
	$database = new database;
	$message_users = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//get the users that are not assigned to this message_number
	$sql = "select * from v_users \n";
	$sql .= "where domain_uuid = :domain_uuid \n";
	$sql .= "and user_uuid not in (\n";
	$sql .= "	select user_uuid from v_message_users ";
	$sql .= "	where domain_uuid = :domain_uuid ";
	$sql .= "	and message_number_uuid = :message_number_uuid ";
	$sql .= "	and user_uuid is not null ";
	$sql .= ")\n";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$parameters['message_number_uuid'] = $message_number_uuid;
	$database = new database;
	$available_users = $database->select($sql, $parameters, 'all');
	$message = $database->message;
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//show the header
	require_once "resources/header.php";

//message number form
	echo "<form method='post' name='frm' action=''>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "	<tr>\n";
	echo "	<td align='left' width='30%' valign='top' nowrap='nowrap'><b>".$text['header-message_number_server_settings']."</b><br><br></td>\n";
	echo "	<td width='70%' valign='top' align='right'>\n";
	echo "		<input type='button' class='btn' name='' alt=\"".$text['button-back']."\" onclick=\"window.location='message_numbers.php'\" value=\"".$text['button-back']."\">\n";
	echo "		<input type='submit' class='btn' name='submit' value='".$text['button-save']."'>\n";
	echo "	</td>\n";
	echo "	</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-name']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='message_number_name' maxlength='30' value=\"".escape($message_number_name)."\" required='required'>\n";
	echo "<br />\n";
	echo "".$text['description-name']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-message_number']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='message_number' maxlength='15' value=\"".escape($message_number)."\" required='required'>\n";
	echo "<br />\n";
	echo "".$text['description-message_number']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-message_provider']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='message_provider' maxlength='15' value=\"".escape($message_provider)."\" required='required'>\n";
	echo "<br />\n";
	echo "".$text['label-message_provider_description']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	//if (permission_exists('message_number_user_view')) {
		if ($action == "update") {
			echo "	<tr>";
			echo "		<td class='vncell' valign='top'>".$text['label-user-list']."</td>";
			echo "		<td class='vtable'>";

			if (is_array($message_users) && @sizeof($message_users) != 0) {
				echo "		<table width='52%'>\n";
				foreach($message_users as $field) {
					echo "		<tr>\n";
					echo "			<td class='vtable'>".escape($field['username'])."</td>\n";
					echo "			<td>\n";
					echo "				<a href='message_number_edit.php?id=".urlencode($message_number_uuid)."&domain_uuid=".urlencode($_SESSION['domain_uuid'])."&user_uuid=".urlencode($field['user_uuid'])."&a=delete' alt='delete' onclick=\"return confirm('".$text['confirm-delete']."')\">$v_link_label_delete</a>\n";
					echo "			</td>\n";
					echo "		</tr>\n";
				}
				echo "		</table>\n";
				echo "		<br />\n";
			}
			unset($message_users);

			echo "			<select name='user_uuid' class='formfld' style='width: auto;'>\n";
			echo "				<option value=''></option>\n";
			if (is_array($available_users) && @sizeof($available_users) != 0) {
				foreach($available_users as $field) {
					echo "		<option value='".escape($field['user_uuid'])."'>".escape($field['username'])."</option>\n";
				}
			}
			unset($available_users);
			echo "			</select>";
			echo "			<input type=\"submit\" class='btn' value=\"".$text['button-add']."\">\n";
			echo "			<br>\n";
			echo "			".$text['description-user-add']."\n";
			echo "			<br />\n";
			echo "		</td>";
			echo "	</tr>";
		}
	//}

/*
	echo "	<tr>";
	echo "		<td class='vncellreq' valign='top'>".$text['label-message_key']."</td>";
	echo "		<td class='vtable'>\n";
	echo "			<input type='text' class='formfld' name='message_key' id='message_key' value=\"".escape($message_key)."\" >";
	echo "			<input type='button' class='btn' value='".$text['button-generate']."' onclick=\"getElementById('message_key').value='".uuid()."';\">";
	if (strlen($text['description-message_key']) > 0) {
		echo "			<br />".$text['description-message_key']."<br />\n";
	}
	echo "		</td>";
	echo "	</tr>";
	*/

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "    ".$text['label-enabled']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "    <select class='formfld' name='enabled'>\n";
	if ($message_number_enabled == "true") {
		echo "    <option value='true' selected='selected'>".$text['label-true']."</option>\n";
	}
	else {
		echo "    <option value='true'>".$text['label-true']."</option>\n";
	}
	if ($message_number_enabled == "false") {
		echo "    <option value='false' selected='selected'>".$text['label-false']."</option>\n";
	}
	else {
		echo "    <option value='false'>".$text['label-false']."</option>\n";
	}
	echo "    </select>\n";
	echo "<br />\n";
	echo $text['description-enabled']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-description']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='message_number_description' maxlength='255' value=\"".escape($message_number_description)."\">\n";
	echo "<br />\n";
	echo "".$text['description-info']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "	<tr>\n";
	echo "		<td colspan='2' align='right'>\n";
	echo "			<br>";
	if ($action == "update") {
		echo "		<input type='hidden' name='message_number_uuid' value='".escape($message_number_uuid)."'>\n";
	}
	echo "			<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
	echo "			<input type='submit' name='submit' class='btn' value='".$text['button-save']."'>\n";
	echo "		</td>\n";
	echo "	</tr>";
	echo "</table>";
	echo "<br />\n";

	echo "</form>";

//show the footer
	require_once "resources/footer.php";

?>
