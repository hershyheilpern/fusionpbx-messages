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
	Portions created by the Initial Developer are Copyright (C) 2018
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes
	require_once "root.php";
	require_once "resources/require.php";
//signalwire library
	require "vendor/autoload.php";
	use SignalWire\Rest\Client;

//check permissions
	require_once "resources/check_auth.php";
	if (!permission_exists('message_add') && !permission_exists('message_edit')) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get http post variables and set them to php variables
	if (is_array($_POST)) {
		$from = explode(":", $_POST['message_from']);
		$message_number_uuid = $from[0];
		$message_from = $from[1];
		$message_to = $_POST["message_to"];
		$message_text = $_POST["message_text"];
		$message_media = $_FILES["message_media"];
	}
	
//process the user data and save it to the database
	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {
		
		//santize the from and to
			if (
				!is_numeric($message_from) ||
				!is_numeric($message_to) ||
				$message_text == '') {
					exit;
			}
	
		//signal wire requires E.164 format
			$message_from = preg_replace('/\+?1?/', '+1', $message_from,1);
			$message_to = preg_replace('/\+?1?/', '+1', $message_to,1);
		
		// handle media (if any)
		    if (is_array($message_media) && @sizeof($message_media) != 0) {
			// reorganize media array, ignore errored files
			$f = 0;
			foreach ($message_media['error'] as $index => $error) {
			    if ($error == 0) {
				$tmp_media[$f]['uuid'] = uuid();
				$tmp_media[$f]['name'] = $message_media['name'][$index];
				$tmp_media[$f]['type'] = $message_media['type'][$index];
				$tmp_media[$f]['tmp_name'] = $message_media['tmp_name'][$index];
				$tmp_media[$f]['size'] = $message_media['size'][$index];
				$f++;
			    }
			}
			$message_media = $tmp_media;
			unset($tmp_media, $f);
		    }
		    $message_type = is_array($message_media) && @sizeof($message_media) != 0 ? 'mms' : 'sms';
				
		//save to db without the + sign
			$message_from = preg_replace('{[\D]}', '', $message_from);
			$message_to = preg_replace('{[\D]}', '', $message_to);

		//get the contact uuid
			$sql = "select c.contact_uuid ";
			$sql .= "from v_contacts as c, v_contact_phones as p ";
			$sql .= "where p.contact_uuid = c.contact_uuid ";
			$sql .= "and p.phone_number like :message_to ";
			$sql .= "and c.domain_uuid = :domain_uuid ";
			$parameters['message_to'] = '%'.ltrim($message_to, '+1');
			$parameters['domain_uuid'] = $domain_uuid;
			$database = new database;
			$contact_uuid = $database->select($sql, $parameters, 'column');
			unset($sql, $parameters);

		//build the message array
			//$message_uuid = uuid(); //we use signalwire's uuid
			$array['messages'][0]['message_uuid'] = $message_uuid;
			$array['messages'][0]['domain_uuid'] = $_SESSION["domain_uuid"];
			$array['messages'][0]['message_number_uuid'] = $message_number_uuid;
			$array['messages'][0]['contact_uuid'] = $contact_uuid;
			$array['messages'][0]['message_type'] = $message_type;
			$array['messages'][0]['message_direction'] = 'outbound';
			$array['messages'][0]['message_date'] = 'now()';
			$array['messages'][0]['message_from'] = $message_from;
			$array['messages'][0]['message_to'] = $message_to;
			$array['messages'][0]['message_text'] = $message_text;

		//build message media array (if necessary)
		    $p = new permissions;
		    if (is_array($message_media) && @sizeof($message_media) != 0) {
			foreach($message_media as $index => $media) {
			    $array['message_media'][$index]['message_media_uuid'] = $media['uuid'];
			    $array['message_media'][$index]['message_uuid'] = $message_uuid;
			    $array['message_media'][$index]['domain_uuid'] = $_SESSION["domain_uuid"];
			    $array['message_media'][$index]['message_number_uuid'] = $message_number_uuid;
			    $array['message_media'][$index]['message_media_type'] = strtolower(pathinfo($media['name'], PATHINFO_EXTENSION));
			    $array['message_media'][$index]['message_media_url'] = $media['name'];
			    $array['message_media'][$index]['message_media_content'] = base64_encode(file_get_contents($media['tmp_name']));
			}

			$p->add('message_media_add', 'temp');
		    }

		//save to the data
		    $database = new database;
		    $database->app_name = 'messages';
		    $database->app_uuid = '4a20815d-042c-47c8-85df-085333e79b87';
		    $database->save($array);
		    unset($array);

		//remove any temporary permissions
		    $p->delete('message_media_add', 'temp');
                
                if (is_array($message_media) && @sizeof($message_media) != 0) {
			$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
			foreach ($message_media as $index => $media) {
				$path = $protocol.$_SERVER['HTTP_HOST'].'/app/messages/message_media.php?mnuuid='.$message_number_uuid.'&id='.$media['uuid'].'&action=download&.'.strtolower(pathinfo($media['name'], PATHINFO_EXTENSION));
				$message['media'][] = $path;
			}
		}
		
		//set the header's and data to send
		    $http_destination = str_replace("\${account_sid}", $_SESSION['message']['http_auth_user']['text'], $_SESSION['message']['http_destination']['text']);
		    $http_method = $_SESSION['message']['http_method']['text'];
		    $http_auth_user = $_SESSION['message']['http_auth_user']['text'];
	            $http_auth_password = $_SESSION['message']['http_auth_password']['text'];
		    $headers[] = "Authorization: Basic ".base64_encode($http_auth_user.':'.$http_auth_password);
		    $headers[] = "Content-type: application/x-www-form-urlencoded";
	            $data["From"] = $message_from;
                    $data["To"] =   $message_to;
	            $data["Body"] = $message_text;
                    $content = http_build_query($data);
                //send the message
                    $response = http_request($http_destination, $http_method, $headers, $content);

		//redirect the user
			return true;

	}

?>
