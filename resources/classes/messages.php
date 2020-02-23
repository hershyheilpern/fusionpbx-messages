<?php

/**
 * call_recordings class
 *
 * @method null download
 */
if (!class_exists('messages')) {
	class messages {

		/**
		 * Called when the object is created
		 */
		public function __construct() {

		}

		/**
		 * Called when there are no references to a particular object
		 * unset the variables used in the class
		 */
		public function __destruct() {
			foreach ($this as $key => $value) {
				unset($this->$key);
			}
		}

		/**
		 * delete messages
		 */
		public function delete($messages) {
			if (permission_exists('message_delete')) {

				//delete multiple messages
					if (is_array($messages)) {
						//get the action
							foreach($messages as $record) {
								if ($record['action'] == 'delete') {
									$action = 'delete';
									break;
								}
							}
						//delete the checked rows
							if ($action == 'delete') {
								$x = 0;
								foreach($messages as $record) {
									if ($record['action'] == 'delete' or $record['checked'] == 'true') {
										//build delete array
											$array['messages'][$x]['message_uuid'] = $record['message_uuid'];
											$x++;
									}
								}
								if (is_array($array) && @sizeof($array) != 0) {
									//grant temporary permissions
										$p = new permissions;
										$p->add('message_delete', 'temp');

									//execute delete
										$database = new database;
										$database->app_name = 'messages';
										$database->app_uuid = '4a20815d-042c-47c8-85df-085333e79b87';
										$database->delete($array);
										unset($array);

									//revoke temporary permissions
										$p->delete('message_delete', 'temp');
								}
								unset($messages);
							}
					}
			}
		} //end the delete function

		
		/**
		 * delete message numbers
		 */
		public function message_number_delete($message_numbers) {
			
			//assign private variables
				$this->permission_prefix = 'message_number_';
				$this->list_page = 'message_numbers.php';
				$this->table = 'message_numbers';
				$this->uuid_prefix = 'message_number_';

			if (permission_exists($this->permission_prefix.'delete')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->list_page);
						exit;
					}

				//delete multiple messages
					if (is_array($message_numbers)) {				
						//delete the checked rows
							$x = 0;
							foreach($message_numbers as $record ) {
								if ($record['checked'] == 'true' && is_uuid($record['uuid'])) {
									//build delete array
									$array[$this->table][$x][$this->uuid_prefix.'uuid'] = $record['uuid'];
									$x++;
								}
							}
							
							if (is_array($array) && @sizeof($array) != 0) {

								//execute delete
									$database = new database;
									$database->app_name = 'messages';
									$database->app_uuid = '4a20815d-042c-47c8-85df-085333e79b87';
									$database->delete($array);
									unset($array);
							}
							unset($message_numbers);
					}
				}
				
		} //end the message_number_delete function

		
		/**
		 *  process and save incoming messages
		 */
		public function save_message($vendor) {
			if ($vendor == "signalwire") {
				//send LaML response
					echo "<response></response>";

				//get the message
					$this->message_to = $this->sanitize_number($_POST['To']);
					$this->message_from = $this->sanitize_number($_POST['From']);
					$this->message_uuid = $_POST['MessageSid'];
					$this->message_text = $_POST['Body'];
					$num_media = $_POST['NumMedia'];
					$this->message_type = $num_media > 0 ? "mms" : "sms";

				//get the message media if any
					if ($num_media > 0) {
						for($i=0; $i < $num_media; $i++) {
							$this->message_media[$i]["media_url"] = $_POST['MediaUrl'.$i];
							$media_type = explode("/", $_POST['MediaContentType'.$i]);
							$this->message_media[$i]["media_type"] = "." . $media_type[1];
						}
					}

			}
			elseif ($vendor == "skyetel") {

			}

			//get the info of the sender and receiver
				$this->message_number($this->message_to);
				$this->contact_uuid($this->message_from);
			//set uuid
				if (!$this->message_uuid) {
					$this->message_uuid = uuid();
				}
			
			//build the arrray
				$array['messages'][0]['message_uuid'] = $this->message_uuid;
				$array['messages'][0]['domain_uuid'] = $this->domain_uuid;
				$array['messages'][0]['message_number_uuid'] = $this->message_number_uuid;
				$array['messages'][0]['contact_uuid'] = $this->contact_uuid;
				$array['messages'][0]['message_type'] = $this->message_type;
				$array['messages'][0]['message_direction'] = 'inbound';
				$array['messages'][0]['message_date'] = 'now()';
				$array['messages'][0]['message_from'] = $this->message_from ;
				$array['messages'][0]['message_to'] = $this->message_to ;
				$array['messages'][0]['message_text'] = $this->message_text;
				$array['messages'][0]['message_read'] = 'false';
				$array['messages'][0]['message_json'] = $this->json;
				//file_put_contents("/tmp/test.txt", print_r($array,1));
			//add the required permission
				$p = new permissions;
				$p->add("message_add", "temp");

			//build message media array (if necessary)
				if (is_array($this->message_media)) {
					foreach($this->message_media as $index => $media) {
						if ($media["media_type"] !== 'xml') {
							$array['message_media'][$index]['message_media_uuid'] = uuid();
							$array['message_media'][$index]['message_uuid'] = $this->message_uuid;
							$array['message_media'][$index]['domain_uuid'] = $this->domain_uuid;
							$array['message_media'][$index]['message_number_uuid'] = $this->message_number_uuid;
							$array['message_media'][$index]['message_media_type'] = $media["media_type"];
							$array['message_media'][$index]['message_media_url'] = $media["media_url"];
							$array['message_media'][$index]['message_media_content'] = base64_encode(file_get_contents($media["media_url"]));
						}
					}
					//add the required permission
						$p->add("message_media_add", "temp");
				}
			file_put_contents("/tmp/test.txt", print_r($array,1));
			//save message to the database
				$database = new database;
				$database->app_name = 'messages';
				$database->app_uuid = '4a20815d-042c-47c8-85df-085333e79b87';
				$database->save($array);
				
			//remove the temporary permission
				$p->delete("message_add", "temp");

		} //end of save_message function


		public function message_number($message_to) {
			$sql = "select domain_uuid, message_number_uuid from v_message_numbers ";
			$sql .= "where message_number like :message_number ";
			$parameters['message_number'] = '%'.ltrim($message_from, '+1');
			$database = new database;
			$row = $database->select($sql, $parameters, 'row');
			if (is_array($row) && @sizeof($row) != 0 ) {
				$this->domain_uuid = $row['domain_uuid'];
				$this->message_number_uuid = $row['message_number_uuid'];
			}
			unset($sql, $parameters);
		}

		public function contact_uuid($message_from) {
			$sql = "select c.contact_uuid ";
			$sql .= "from v_contacts as c, v_contact_phones as p ";
			$sql .= "where p.contact_uuid = c.contact_uuid ";
			$sql .= "and p.phone_number like :message_from ";
			$sql .= "and c.domain_uuid = :domain_uuid ";
			$parameters['message_from'] = '%'.ltrim($message_from, '+1');
			$parameters['domain_uuid'] = $this->domain_uuid;
			$database = new database;
			$this->contact_uuid = $database->select($sql, $parameters, 'column');
			unset($sql, $parameters);
		}

		public function sanitize_number($number) {
			$sanitized_number = preg_replace('{[\D]}', '', $number);
			return $sanitized_number;
		}

	}  //end the class
}

/*
$obj = new messages;
$obj->delete();
*/

?>