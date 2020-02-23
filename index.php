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

//default authorized to false
	$authorized = false;

//set the get to php variables;
	$key = $_GET['key'];
	$provider = $_GET['provider'];

//check if sender is authorized
	$sql = "select * from v_domain_settings ";
	$sql .= "where domain_setting_category = 'message' ";
	$sql .= "and domain_setting_subcategory = '".$provider."_key' ";
	$sql .= "and domain_setting_value = :domain_setting_value ";
	$sql .= "and domain_setting_enabled = 'true' ";
	$parameters['domain_setting_value'] = $key;
	$database = new database;
	$row = $database->select($sql, $parameters, 'row');
	if (is_array($row) && @sizeof($row) != 0) {
		$authorized = true;
	}
	else {
		unset($sql, $parameters, $row);
		$sql = "select * from v_default_settings ";
		$sql .= "where default_setting_category = 'message' ";
		$sql .= "and default_setting_subcategory = '".$provider."_key' ";
		$sql .= "and default_setting_value = :default_setting_value ";
		$sql .= "and default_setting_enabled = 'true' ";
		$parameters['default_setting_value'] = $key;
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && @sizeof($row) != 0) {
			$authorized = true;
		}
	}
	unset($sql, $parameters, $row);

	
//authorization failed
	if (!$authorized) {
		//log the failed auth attempt to the system, to be available for fail2ban.
			openlog('FusionPBX', LOG_NDELAY, LOG_AUTH);
			syslog(LOG_WARNING, '['.$_SERVER['REMOTE_ADDR']."] authentication failed for ".$key);
			closelog();

		//send http 404
			header("HTTP/1.0 404 Not Found");
			echo "<html>\n";
			echo "<head><title>404 Not Found</title></head>\n";
			echo "<body bgcolor=\"white\">\n";
			echo "<center><h1>404 Not Found</h1></center>\n";
			echo "<hr><center>nginx/1.12.1</center>\n";
			echo "</body>\n";
			echo "</html>\n";
			exit();
	}
//save the message	
	$messages = new messages;
	$messages->save_message($provider);

?>
