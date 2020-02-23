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
	Portions created by the Initial Developer are Copyright (C) 2016-2019
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('message_view')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get selected number/contact
	$current_contact = $_GET['sel'];
	$number = explode(":", $_GET['message_number']);
	$message_number_uuid = $number[0];
	$message_number = $number[1];

//check if user is this message_user of this message_number
	$sql = "select user_uuid from v_message_users ";
	$sql .= "where user_uuid = :user_uuid ";
	$sql .= "and message_number_uuid = :message_number_uuid ";
	$sql .= "and domain_uuid = :domain_uuid ";
	$parameters['user_uuid'] = $_SESSION["user_uuid"];
	$parameters['message_number_uuid'] = $message_number_uuid;
	$parameters['domain_uuid'] = $domain_uuid;
	$database = new database;
	$message_user = $database->select($sql, $parameters, "column");
	unset($sql, $parameters);

	if (if_group("superadmin") || if_group("admin") || is_uuid($message_user)) {

		//get the contact list
			if (isset($_SESSION['message']['display_last']['text']) && $_SESSION['message']['display_last']['text'] != '') {
				$array = explode(' ',$_SESSION['message']['display_last']['text']);
				if (is_array($array) && is_numeric($array[0]) && $array[0] > 0) {
					if ($array[1] == 'messages') {
						$limit = limit_offset($array[0], 0);
					}
					else {
						$since = "and m.message_date >= :message_date ";
						$parameters['message_date'] = date("Y-m-d H:i:s", strtotime('-'.$_SESSION['message']['display_last']['text']));
					}
				}
			}
			//if ($limit == '' && $since == '') { $limit = limit_offset(25, 0); } //default (message count)
			$sql = "select message_from, message_to, message_direction, ";
			$sql .= "contact_uuid, message_read ";
			$sql .= "from v_messages  ";
			$sql .= "where ";
			$sql .= "message_number_uuid = :message_number_uuid ";
			$sql .= "and domain_uuid = :domain_uuid ";
			$sql .= $since;
			$sql .= "order by message_date desc ";
			$sql .= $limit;
			$parameters['message_number_uuid'] = $message_number_uuid;
			$parameters['domain_uuid'] = $domain_uuid;
			$database = new database;
			$message_contacts = $database->select($sql, $parameters, 'all');
			//echo "<pre>".print_r($database->message,1)."</pre>";
			unset($sql, $parameters);

		//build the messages contacts list
			if (is_array($message_contacts) && sizeof($message_contacts) != 0) {
				$x = 0;
				foreach ($message_contacts as $row) {
					if ($row['message_direction'] == "outbound") {
						if (!in_array($row['message_to'], $checked_contacts)) {
							$contact[$x]['number'] = $row['message_to'];
							$checked = $row['message_to'];
						}
					}
					elseif ($row['message_direction'] == "inbound") {
						if (!in_array($row['message_from'], $checked_contacts)) {
							$contact[$x]['number'] = $row['message_from'];
							$checked = $row['message_from'];
						}

						//increment number of unread messages for this contact
						if ($row['message_read'] == "false") {
							for ($i = 0; $i <= count($contact) - 1; $i++) {
								if ($contact[$i]['number'] == $row['message_from']) {
									$contact[$i]['unread_messages']++;;
								}
							}
						}
					}
					//get the senders contact
					if (!in_array($checked, $checked_contacts) && is_uuid($row['contact_uuid'])) {
						$sql = "select c.contact_name_given, c.contact_name_family, ";
						$sql .= "(select ce.email_address from v_contact_emails as ce where ce.contact_uuid = c.contact_uuid and ce.email_primary = 1) as contact_email ";
						$sql .= "from v_contacts as c ";
						$sql .= "where c.contact_uuid = :contact_uuid ";
						$sql .= "and (c.domain_uuid = :domain_uuid or c.domain_uuid is null) ";
						$parameters['contact_uuid'] = $row['contact_uuid'];
						$parameters['domain_uuid'] = $domain_uuid;
						$database = new database;
						$result = $database->select($sql, $parameters, 'row');
						if (is_array($result) && @sizeof($result) != 0) {
							$contact[$x]['contact_uuid'] = $row['contact_uuid'];
							$contact[$x]['contact_name_given'] = $result['contact_name_given'];
							$contact[$x]['contact_name_family'] = $result['contact_name_family'];
							$contact[$x]['contact_email'] = $result['contact_email'];
						}
						unset($sql, $parameters, $row);
					}
					$checked_contacts[] = $checked;
					$x++;
				}
				unset($checked_contacts);
			}
		
			//get contact (primary attachment) images and cache them
				if (is_array($contact) && @sizeof($contact) != 0) {
					foreach ($numbers as $number) {
						$contact_uuids[] = $contact['contact_uuid'];
					}
					if (is_array($contact_uuids) && @sizeof($contact_uuids) != 0) {
						$sql = "select contact_uuid as uuid, attachment_filename as filename, attachment_content as image ";
						$sql .= "from v_contact_attachments ";
						$sql .= "where domain_uuid = :domain_uuid ";
						$sql .= "and (";
						foreach ($contact_uuids as $index => $contact_uuid) {
							$sql_where[] = "contact_uuid = :contact_uuid_".$index;
							$parameters['contact_uuid_'.$index] = $contact_uuid;
						}
						$sql .= implode(' or ', $sql_where);
						$sql .= ") ";
						$sql .= "and attachment_primary = 1 ";
						$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
						$database = new database;
						$contact_ems = $database->select($sql, $parameters, 'all');
						if (is_array($contact_ems) && @sizeof($contact_ems) != 0) {
							foreach ($contact_ems as $contact_em) {
								$_SESSION['tmp']['messages']['contact_em'][$contact_em['uuid']]['filename'] = $contact_em['filename'];
								$_SESSION['tmp']['messages']['contact_em'][$contact_em['uuid']]['image'] = $contact_em['image'];
							}
						}
					}
					unset($sql, $sql_where, $parameters, $contact_uuids, $contact_ems, $contact_em);
				}
			}

	//display the contacts list
	if (is_array($contact) && @sizeof($contact) != 0) {
		echo "<div id='contact_list' style='min-height: 300px; overflow: auto;'>\n";
		echo "<table class='tr_hover' width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
		foreach($contact as $x=>$row) {
			if ($current_contact != '' && $row["number"] == $current_contact) {
				echo "<tr><td valign='top' class='row_style0 contact_selected' style='cursor: default;'>\n";
				$selected = true;
			} else {
				echo "<tr><td valign='top' class='row_style1' onclick=\"$('#contact_current_number').html('".escape($row['number'])."'); load_thread('".$row['number']."', '".$row['contact_uuid']."', '".$message_number_uuid."');\">\n";
				$selected = false;
			}
			
			//contact image
				if (is_array($_SESSION['tmp']['messages']['contact_em'][$row['contact_uuid']]) && sizeof($_SESSION['tmp']['messages']['contact_em'][$row['contact_uuid']]) != 0) {
					
				}
			//contact name/number
				if ($row['contact_name_given'] != '' || $row['contact_name_family'] != '') {
					echo "<div style=''>\n";
					echo "	<strong style='display: inline-block; margin: 8px 0 5px 0; white-space: nowrap; float: left'>".escape($row['contact_name_given'].' '.$row['contact_name_family']).'</strong>';
					if ($row['unread_messages'] > 0) {
						echo "<span style='white-space: nowrap; margin: 8px 25px 5px 25px; float: left;'><i class='fas fa-layers-counter fa-envelope-square'>&nbsp;".$row['unread_messages']."</i></span>";
					}
					echo "	<span style='font-size: 80%; white-space: nowrap; margin: 8px 25px 5px 0; float: right;' title='Call'><a href='callto:".escape($row['number'])."'><i class='fas fa-phone-alt' style='margin-right: 5px;'></i></a></span>";
					if (valid_email($row['contact_email'])) {
						echo "<span style='; white-space: nowrap; margin: 8px 25px 5px 0; float: right;' title='Send Email'><a href='mailto:".escape($row['contact_email'])."'><i class='fas fa-envelope' style='margin-right: 5px;'></i></a></span>";
					}
					echo "<span style='white-space: nowrap; margin: 8px 25px 5px 0; float: right;' title=\"".$text['label-view_contact']."\"><a href='/app/contacts/contact_edit.php?id=".$row['contact_uuid']."' target='_blank'><i class='fas fa-user'></i></a></span>\n";
					if ($selected) {
						echo "	<script>$('#contact_current_name').html('".escape($row['contact_name_given'].' '.$row['contact_name_family'])."');</script>\n";
					}
					echo "</div>\n";
				}
				else {
					echo "	<strong style='display: inline-block; margin: 8px 0 5px 0; white-space: nowrap; float: left'>".escape($row['number'])."</strong>";
					if ($row['unread_messages'] > 0) {
						echo "<span style='white-space: nowrap; margin: 8px 25px 5px 25px; float: left;'><i class='fas fa-layers-counter fa-envelope-square'>&nbsp;".$row['unread_messages']."</i></span>";
					}
					echo "	<span style='font-size: 80%; white-space: nowrap; margin: 8px 25px 5px 0; float: right;' title='Call'><a href='callto:".escape($row['number'])."'><i class='fas fa-phone-alt' style='margin-right: 5px;'></i></a></span>";
					echo "<span style='white-space: nowrap; margin: 8px 25px 5px 0; float: right;' title=\"".$text['label-add_contact']."\"><a href='/app/contacts/contact_edit.php' target='_blank'><i class='fas fa-user'></i></a></span>\n";
					if ($selected) {
						echo "	<script>$('#contact_current_name').html('".escape($row['number'])."');</script>\n";

					}
				}
			echo "</td></tr>\n";
		}
		echo "</table>\n";
		echo "</div>\n";

		echo "<script>\n";
		foreach ($numbers as $number) {
			if (is_array($_SESSION['tmp']['messages']['contact_em'][$row['contact_uuid']]) && @sizeof($_SESSION['tmp']['messages']['contact_em'][$row['contact_uuid']]) != 0) {
				echo "$('img#contact_image_".$row['contact_uuid']."').css('backgroundImage', 'url(' + $('img#src_message-bubble-image-em_".$row['contact_uuid']."').attr('src') + ')');\n";
			}
		}
		echo "</script>\n";
	}
	else {
		echo "<div style='padding: 15px;'><center>&middot;&middot;&middot;</center>";
	}

	echo "<center>\n";
	echo "	<span id='contacts_refresh_state'><img src='resources/images/refresh_active.gif' style='width: 16px; height: 16px; border: none; margin-top: 3px; cursor: pointer;' onclick=\"refresh_contacts_stop();\" alt=\"".$text['label-refresh_pause']."\" title=\"".$text['label-refresh_pause']."\"></span> ";
	echo "</center>\n";

?>