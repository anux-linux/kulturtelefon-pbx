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
	Portions created by the Initial Developer are Copyright (C) 2019 - 2021
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

/**
 * fax_queue class
 */
	class fax_queue {

		/**
		* declare the variables
		*/
		private $app_name;
		private $app_uuid;
		private $name;
		private $table;
		private $toggle_field;
		private $toggle_values;
		private $location;

		/**
		 * called when the object is created
		 */
		public function __construct() {
			//assign the variables
				$this->app_name = 'fax_queue';
				$this->app_uuid = '3656287f-4b22-4cf1-91f6-00386bf488f4';
				$this->name = 'fax_queue';
				$this->table = 'fax_queue';
				$this->toggle_field = '';
				$this->toggle_values = ['true','false'];
				$this->location = 'fax_queue.php';
		}

		/**
		 * delete rows from the database
		 */
		public function delete($records) {
			if (permission_exists($this->name.'_delete')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->location);
						exit;
					}

				//delete multiple records
					if (is_array($records) && @sizeof($records) != 0) {
						//build the delete array
							$x = 0;
							foreach ($records as $record) {
								//add to the array
									if ($record['checked'] == 'true' && is_uuid($record['fax_queue_uuid'])) {
										$array[$this->table][$x]['fax_queue_uuid'] = $record['fax_queue_uuid'];
										$array[$this->table][$x]['domain_uuid'] = $_SESSION['domain_uuid'];
									}

								//increment the id
									$x++;
							}

						//delete the checked rows
							if (is_array($array) && @sizeof($array) != 0) {
								//execute delete
									$database = new database;
									$database->app_name = $this->app_name;
									$database->app_uuid = $this->app_uuid;
									$database->delete($array);
									unset($array);

								//set message
									message::add($text['message-delete']);
							}
							unset($records);
					}
			}
		}

		/**
		 * toggle a field between two values
		 */
		public function toggle($records) {
			if (permission_exists($this->name.'_edit')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->location);
						exit;
					}

				//toggle the checked records
					if (is_array($records) && @sizeof($records) != 0) {
						//get current toggle state
							foreach($records as $record) {
								if ($record['checked'] == 'true' && is_uuid($record['fax_queue_uuid'])) {
									$uuids[] = "'".$record['fax_queue_uuid']."'";
								}
							}
							if (is_array($uuids) && @sizeof($uuids) != 0) {
								$sql = "select ".$this->name."_uuid as uuid, ".$this->toggle_field." as toggle from v_".$this->table." ";
								$sql .= "where ".$this->name."_uuid in (".implode(', ', $uuids).") ";
								$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
								$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
								$database = new database;
								$rows = $database->select($sql, $parameters, 'all');
								if (is_array($rows) && @sizeof($rows) != 0) {
									foreach ($rows as $row) {
										$states[$row['uuid']] = $row['toggle'];
									}
								}
								unset($sql, $parameters, $rows, $row);
							}

						//build update array
							$x = 0;
							foreach($states as $uuid => $state) {
								//create the array
									$array[$this->table][$x][$this->name.'_uuid'] = $uuid;
									$array[$this->table][$x][$this->toggle_field] = $state == $this->toggle_values[0] ? $this->toggle_values[1] : $this->toggle_values[0];

								//increment the id
									$x++;
							}

						//save the changes
							if (is_array($array) && @sizeof($array) != 0) {
								//save the array
									$database = new database;
									$database->app_name = $this->app_name;
									$database->app_uuid = $this->app_uuid;
									$database->save($array);
									unset($array);

								//set message
									message::add($text['message-toggle']);
							}
							unset($records, $states);
					}
			}
		}

		/**
		 * copy rows from the database
		 */
		public function copy($records) {
			if (permission_exists($this->name.'_add')) {

				//add multi-lingual support
					$language = new text;
					$text = $language->get();

				//validate the token
					$token = new token;
					if (!$token->validate($_SERVER['PHP_SELF'])) {
						message::add($text['message-invalid_token'],'negative');
						header('Location: '.$this->location);
						exit;
					}

				//copy the checked records
					if (is_array($records) && @sizeof($records) != 0) {

						//get checked records
							foreach($records as $record) {
								if ($record['checked'] == 'true' && is_uuid($record['fax_queue_uuid'])) {
									$uuids[] = "'".$record['fax_queue_uuid']."'";
								}
							}

						//create the array from existing data
							if (is_array($uuids) && @sizeof($uuids) != 0) {
								$sql = "select * from v_".$this->table." ";
								$sql .= "where fax_queue_uuid in (".implode(', ', $uuids).") ";
								$sql .= "and (domain_uuid = :domain_uuid or domain_uuid is null) ";
								$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
								$database = new database;
								$rows = $database->select($sql, $parameters, 'all');
								if (is_array($rows) && @sizeof($rows) != 0) {
									$x = 0;
									foreach ($rows as $row) {
										//copy data
											$array[$this->table][$x] = $row;

										//add copy to the description
											$array[$this->table][$x][fax_queue.'_uuid'] = uuid();

										//increment the id
											$x++;
									}
								}
								unset($sql, $parameters, $rows, $row);
							}

						//save the changes and set the message
							if (is_array($array) && @sizeof($array) != 0) {
								//save the array
									$database = new database;
									$database->app_name = $this->app_name;
									$database->app_uuid = $this->app_uuid;
									$database->save($array);
									unset($array);

								//set message
									message::add($text['message-copy']);
							}
							unset($records);
					}
			}
		}

		/**
		 * Removes records from the v_fax_files and v_fax_logs tables. Called by the maintenance application.
		 * @param settings $settings Settings object
		 * @return void
		 */
		public static function database_maintenance(settings $settings): void {
			$database = $settings->database();
			$domains = maintenance_service::get_domains($database);
			foreach ($domains as $domain_uuid => $domain_name) {
				$domain_settings = new settings(['database'=>$database, 'domain_uuid'=>$domain_uuid]);
				$retention_days = $domain_settings->get('fax_queue', 'database_retention_days', '');
				//delete from v_fax_queue where fax_status = 'sent' and fax_date < NOW() - INTERVAL '$days_keep_fax_queue days'
				if (!empty($retention_days) && is_numeric($retention_days)) {
					$sql = "delete from v_fax_queue where fax_status = 'sent' and fax_date < NOW() - INTERVAL '$retention_days days'";
					$sql .= " and domain_uuid = '$domain_uuid'";
					$database->execute($sql);
					$code = $database->message['code'] ?? 0;
					if ($code == 200) {
						maintenance_service::log_write(self::class, "Successfully removed entries older than $retention_days", $domain_uuid);
					} else {
						$message = $database->message['message'] ?? "An unknown error has occurred";
						maintenance_service::log_write(self::class, "Unable to remove old database records. Error message: $message ($code)", $domain_uuid, maintenance_service::LOG_ERROR);
					}
				}
			}
		}
	}

?>
