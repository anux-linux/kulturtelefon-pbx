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
	Portions created by the Initial Developer are Copyright (C) 2008-2025
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	Luis Daniel Lucio Quiroz <dlucio@okay.com.mx>

	Original version of Call Block was written by Gerrit Visser <gerrit308@gmail.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (!permission_exists('call_block_edit') && !permission_exists('call_block_add')) {
		echo "access denied"; exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//initialize the database object
	$database = new database;

//set the defaults
	$call_block_name = '';
	$call_block_country_code = '';
	$call_block_number = '';
	$call_block_description = '';

//action add or update
	if (!empty($_REQUEST["id"]) && is_uuid($_REQUEST["id"])) {
		$action = "update";
		$call_block_uuid = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}

//get order and order by and sanitize the values
	$order_by = $_GET["order_by"] ?? '';
	$order = $_GET["order"] ?? '';

//get http post variables and set them to php variables
	if (!empty($_POST)) {
		//get the variables from the http post
		$domain_uuid = permission_exists('call_block_domain') ? $_POST["domain_uuid"] : $_SESSION['domain_uuid'];
		$call_block_direction = $_POST["call_block_direction"];
		$extension_uuid = $_POST["extension_uuid"];
		$call_block_name = $_POST["call_block_name"] ?? null;
		$call_block_country_code = $_POST["call_block_country_code"] ?? null;
		$call_block_number = $_POST["call_block_number"] ?? null;
		$call_block_enabled = $_POST["call_block_enabled"] ?? 'false';
		$call_block_description = $_POST["call_block_description"] ?? null;

		//get the call block app and data
		$action_array = explode(':', $_POST["call_block_action"]);
		$call_block_app = $action_array[0];
		$call_block_data = $action_array[1] ?? null;

		//sanitize the data
		$extension_uuid = preg_replace("#[^a-fA-F0-9./]#", "", $extension_uuid);
		$call_block_country_code = preg_replace('#[^0-9./]#', '', $call_block_country_code ?? '');
		$call_block_number = preg_replace('#[^0-9./]#', '', $call_block_number ?? '');
	}

//handle the http post
	if (!empty($_POST) && empty($_POST["persistformvar"])) {

		//handle action
			if (!empty($_POST['action'])) {
				switch ($_POST['action']) {
					case 'delete':
						if (permission_exists('call_block_delete') && is_uuid($call_block_uuid)) {
							//prepare
								$array[0]['checked'] = 'true';
								$array[0]['uuid'] = $call_block_uuid;
							//delete
								$obj = new call_block;
								$obj->delete($array);
						}
						break;
					case 'add':
						$xml_cdrs = $_POST['xml_cdrs'] ?? null;
						if (!empty($xml_cdrs) && permission_exists('call_block_add')) {
							$obj = new call_block;
							$obj->call_block_direction = $call_block_direction;
							$obj->extension_uuid = $extension_uuid;
							$obj->call_block_app = $call_block_app;
							$obj->call_block_data = $call_block_data;
							$obj->add($xml_cdrs);
						}
						break;
				}

				header('Location: call_block.php');
				exit;
			}

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: call_block.php');
				exit;
			}

		//check for all required data
			$msg = '';
			//if (empty($call_block_name)) { $msg .= $text['label-provide-name']."<br>\n"; }
			//if (empty($call_block_number)) { $msg .= $text['label-provide-number']."<br>\n"; }
			if (empty($call_block_enabled)) { $msg .= $text['label-provide-enabled']."<br>\n"; }
			if (!empty($msg) && empty($_POST["persistformvar"])) {
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
			if (!empty($_POST) && empty($_POST["persistformvar"])) {

				//ensure call block is enabled in the dialplan
					if ($action == "add" || $action == "update") {
						$sql = "select dialplan_uuid from v_dialplans where true ";
						if (!empty($domain_uuid) && is_uuid($domain_uuid)) {
							$sql .= "and domain_uuid = :domain_uuid ";
						}
						$sql .= "and app_uuid = 'b1b31930-d0ee-4395-a891-04df94599f1f' ";
						$sql .= "and dialplan_enabled <> 'true' ";
						if (!empty($domain_uuid) && is_uuid($domain_uuid)) {
							$parameters['domain_uuid'] = $domain_uuid;
						}
						$rows = $database->select($sql, $parameters);

						if (!empty($rows)) {
							foreach ($rows as $index => $row) {
								$array['dialplans'][$index]['dialplan_uuid'] = $row['dialplan_uuid'];
								$array['dialplans'][$index]['dialplan_enabled'] = 'true';
							}

							$p = permissions::new();
							$p->add('dialplan_edit', 'temp');

							$database->save($array);
							unset($array);

							$p->delete('dialplan_edit', 'temp');
						}
					}

				//if user doesn't have call block all then use the assigned extension_uuid
					if (!permission_exists('call_block_extension')) {
						$extension_uuid = $_SESSION['user']['extension'][0]['extension_uuid'];
					}

				//save the data to the database
					if ($action == "add") {
						$array['call_block'][0]['call_block_uuid'] = uuid();
						$array['call_block'][0]['domain_uuid'] = $domain_uuid;
						$array['call_block'][0]['call_block_direction'] = $call_block_direction;
						if (!empty($extension_uuid) && is_uuid($extension_uuid)) {
							$array['call_block'][0]['extension_uuid'] = $extension_uuid;
						}
						$array['call_block'][0]['call_block_name'] = $call_block_name;
						$array['call_block'][0]['call_block_country_code'] = $call_block_country_code;
						$array['call_block'][0]['call_block_number'] = $call_block_number;
						$array['call_block'][0]['call_block_count'] = 0;
						$array['call_block'][0]['call_block_app'] = $call_block_app;
						$array['call_block'][0]['call_block_data'] = $call_block_data;
						$array['call_block'][0]['call_block_enabled'] = $call_block_enabled;
						$array['call_block'][0]['date_added'] = time();
						$array['call_block'][0]['call_block_description'] = $call_block_description;

						$database->app_name = 'call_block';
						$database->app_uuid = '9ed63276-e085-4897-839c-4f2e36d92d6c';
						$database->save($array);
						unset($array);

						message::add($text['label-add-complete']);
						header("Location: call_block.php");
						return;
					}
					if ($action == "update") {
						if (!empty($domain_uuid) && is_uuid($domain_uuid)) {
							$sql = "select c.call_block_country_code, c.call_block_number, d.domain_name ";
							$sql .= "from v_call_block as c ";
							$sql .= "join v_domains as d on c.domain_uuid = d.domain_uuid ";
							$sql .= "where c.domain_uuid = :domain_uuid ";
							$sql .= "and c.call_block_uuid = :call_block_uuid ";
							$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
						}
						else {
							$sql = "select c.call_block_country_code, c.call_block_number, domain_name as 'global' ";
							$sql .= "from v_call_block as c ";
							$sql .= "where c.domain_uuid is null ";
							$sql .= "and c.call_block_uuid = :call_block_uuid ";
						}
						$parameters['call_block_uuid'] = $call_block_uuid;
						$result = $database->select($sql, $parameters);
						if (!empty($result)) {
							//set the domain_name
							$domain_name = $result[0]["domain_name"];

							//clear the cache
							$cache = new cache;
							$cache->delete("app:call_block:".$domain_name.":".$call_block_country_code.$call_block_number);
						}
						unset($sql, $parameters);

						$array['call_block'][0]['call_block_uuid'] = $call_block_uuid;
						$array['call_block'][0]['domain_uuid'] = $domain_uuid;
						$array['call_block'][0]['call_block_direction'] = $call_block_direction;
						if (!empty($extension_uuid) && is_uuid($extension_uuid)) {
							$array['call_block'][0]['extension_uuid'] = $extension_uuid;
						}
						$array['call_block'][0]['call_block_name'] = $call_block_name;
						$array['call_block'][0]['call_block_country_code'] = $call_block_country_code;
						$array['call_block'][0]['call_block_number'] = $call_block_number;
						$array['call_block'][0]['call_block_app'] = $call_block_app;
						$array['call_block'][0]['call_block_data'] = $call_block_data;
						$array['call_block'][0]['call_block_enabled'] = $call_block_enabled;
						$array['call_block'][0]['date_added'] = time();
						$array['call_block'][0]['call_block_description'] = $call_block_description;

						$database->app_name = 'call_block';
						$database->app_uuid = '9ed63276-e085-4897-839c-4f2e36d92d6c';
						$database->save($array);
						unset($array);

						message::add($text['label-update-complete']);
						header("Location: call_block.php");
						return;
					}
			}
	}

//pre-populate the form
	if (!empty($_GET) && empty($_POST["persistformvar"])) {
		$call_block_uuid = $_GET["id"];
		$sql = "select * from v_call_block ";
		$sql .= "where ( ";
		$sql .= "	domain_uuid = :domain_uuid ";
		if (permission_exists('call_block_domain')) {
			$sql .= "	or domain_uuid is null ";
		}
		$sql .= ") ";
		$sql .= "and call_block_uuid = :call_block_uuid ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$parameters['call_block_uuid'] = $call_block_uuid;
		$row = $database->select($sql, $parameters, 'row');
		if (!empty($row)) {
			$domain_uuid = $row["domain_uuid"];
			$call_block_direction = $row["call_block_direction"];
			$extension_uuid = $row["extension_uuid"];
			$call_block_name = $row["call_block_name"];
			$call_block_country_code = $row["call_block_country_code"];
			$call_block_number = $row["call_block_number"];
			$call_block_app = $row["call_block_app"];
			$call_block_data = $row["call_block_data"];
			$call_block_enabled = $row["call_block_enabled"];
			$call_block_description = $row["call_block_description"];
		}
		unset($sql, $parameters, $row);
	}

//set the defaults
	if (empty($call_block_enabled)) { $call_block_enabled = 'true'; }

//get the extensions
	if (permission_exists('call_block_all') || permission_exists('call_block_extension')) {
		$sql = "select extension_uuid, extension, number_alias, user_context, description from v_extensions ";
		$sql .= "where ( ";
		$sql .= "	domain_uuid = :domain_uuid ";
		if (permission_exists('call_block_domain')) {
			$sql .= "	or domain_uuid is null ";
		}
		$sql .= ") ";
		$sql .= "and enabled = 'true' ";
		$sql .= "order by extension asc ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$extensions = $database->select($sql, $parameters);
	}

//get the ivr's
if (permission_exists('call_block_all') || permission_exists('call_block_ivr')) {
	$sql = "select ivr_menu_uuid,ivr_menu_name, ivr_menu_extension, ivr_menu_description from v_ivr_menus ";
	$sql .= "where ( ";
	$sql .= "	domain_uuid = :domain_uuid ";
	if (permission_exists('call_block_domain')) {
		$sql .= "	or domain_uuid is null ";
	}
	$sql .= ") ";
	// $sql .= "and enabled = 'true' ";
	$sql .= "order by ivr_menu_extension asc ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$ivrs = $database->select($sql, $parameters);
}

//get the ring groups
if (permission_exists('call_block_all') || permission_exists('call_block_ring_group')) {
	$sql = "select ring_group_uuid,ring_group_name, ring_group_extension, ring_group_description from v_ring_groups ";
	$sql .= "where ( ";
	$sql .= "	domain_uuid = :domain_uuid ";
	if (permission_exists('call_block_domain')) {
		$sql .= "	or domain_uuid is null ";
	}
	$sql .= ") ";
	// $sql .= "and ring_group_enabled = 'true' ";
	$sql .= "order by ring_group_extension asc ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$ring_groups = $database->select($sql, $parameters);
}

//get the voicemails
	$sql = "select voicemail_uuid, voicemail_id, voicemail_description ";
	$sql .= "from v_voicemails ";
	$sql .= "where ( ";
	$sql .= "	domain_uuid = :domain_uuid ";
	if (permission_exists('call_block_domain')) {
		$sql .= "	or domain_uuid is null ";
	}
	$sql .= ") ";
	$sql .= "and voicemail_enabled = 'true' ";
	$sql .= "order by voicemail_id asc ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$voicemails = $database->select($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//show the header
	$document['title'] = $text['title-call_block'];
	require_once "resources/header.php";

//show the content
	echo "<form method='post' name='frm' id='frm'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>";
	if ($action == "add") {
		echo "<b>".$text['label-edit-add']."</b>\n";
	}
	if ($action == "update") {
		echo "<b>".$text['label-edit-edit']."</b>\n";
	}

	echo 	"</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$settings->get('theme', 'button_icon_back'),'id'=>'btn_back','collapse'=>'hide-xs','style'=>'margin-right: 15px;','link'=>'call_block.php']);
	if ($action == 'update' && permission_exists('call_block_delete')) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$settings->get('theme', 'button_icon_delete'),'name'=>'btn_delete','collapse'=>'hide-xs','style'=>'margin-right: 15px;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$settings->get('theme', 'button_icon_save'),'id'=>'btn_save','collapse'=>'hide-xs']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if ($action == 'update' && permission_exists('call_block_delete')) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'submit','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','name'=>'action','value'=>'delete','onclick'=>"modal_close();"])]);
	}

	if ($action == "add") {
		echo $text['label-add-note']."\n";
	}
	if ($action == "update") {
		echo $text['label-edit-note']."\n";
	}
	echo "<br /><br />\n";

	echo "<div class='card'>\n";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-direction']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='call_block_direction'>\n";
	echo "		<option value='inbound'>".$text['label-inbound']."</option>\n";
	echo "		<option value='outbound' ".(!empty($call_block_direction) && $call_block_direction == "outbound" ? "selected" : null).">".$text['label-outbound']."</option>\n";
	echo "	</select>\n";
	echo "<br />\n";
	echo $text['description-direction']."\n";
	echo "\n";
	echo "</td>\n";
	echo "</tr>\n";

	if (permission_exists('call_block_extension')) {
		echo "<tr>\n";
		echo "<td width='30%' class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-extension']."\n";
		echo "</td>\n";
		echo "<td width='70%' class='vtable' align='left'>\n";
		echo "	<select class='formfld' name='extension_uuid'>\n";
		echo "		<option value=''>".$text['label-all']."</option>\n";
		if (!empty($extensions)) {
			foreach ($extensions as $row) {
				$selected = !empty($extension_uuid) && $extension_uuid == $row['extension_uuid'] ? "selected='selected'" : null;
				echo "	<option value='".urlencode($row["extension_uuid"])."' ".$selected.">".escape($row['extension'])." ".escape($row['description'])."</option>\n";
			}
		}
		echo "	</select>\n";
		echo "<br />\n";
		echo $text['description-extension']."\n";
		echo "\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-name']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='call_block_name' maxlength='255' value=\"".escape($call_block_name)."\">\n";
	echo "<br />\n";
	echo $text['description-call_block_name']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-number']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='call_block_country_code' maxlength='6' style='width: 60px;' value=\"".escape($call_block_country_code)."\">\n";
	echo "	<input class='formfld' type='text' name='call_block_number' maxlength='255' value=\"".escape($call_block_number)."\">\n";
	echo "<br />\n";
	echo $text['description-call_block_number']."\n";
	echo "<br />\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-action']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	function call_block_action_select($label = false) {
		global $select_margin, $text, $call_block_app, $call_block_data, $extensions, $ivrs, $voicemails, $ring_groups;
		echo "<select class='formfld' style='".$select_margin."' name='call_block_action'>\n";
		if ($label) {
			echo "	<option value='' disabled='disabled'>".$text['label-action']."</option>\n";
		}
		if ($call_block_app == "reject") {
			echo "	<option value='reject' selected='selected'>".$text['label-reject']."</option>\n";
		}
		else {
			echo "	<option value='reject' >".$text['label-reject']."</option>\n";
		}
		if ($call_block_app == "busy") {
			echo "	<option value='busy' selected='selected'>".$text['label-busy']."</option>\n";
		}
		else {
			echo "	<option value='busy'>".$text['label-busy']."</option>\n";
		}
		if ($call_block_app == "hold") {
			echo "	<option value='hold' selected='selected'>".$text['label-hold']."</option>\n";
		}
		else {
			echo "	<option value='hold'>".$text['label-hold']."</option>\n";
		}
		if (permission_exists('call_block_extension')) {
			if (!empty($extensions)) {
				echo "	<optgroup label='".$text['label-extension']."'>\n";
				foreach ($extensions as $row) {
					$selected = ($call_block_app == 'extension' && $call_block_data == $row['extension']) ? "selected='selected'" : null;
					echo "		<option value='extension:".urlencode($row["extension"])."' ".$selected.">".escape($row['extension'])." ".escape($row['description'])."</option>\n";
				}
				echo "	</optgroup>\n";
			}
		}
		if (permission_exists('call_block_ivr')) {
			if (!empty($ivrs)) {
				echo "	<optgroup label='".$text['label-ivr_menus']."'>\n";
				foreach ($ivrs as $row) {
					$selected = ($call_block_app == 'ivr' && $call_block_data == $row['ivr_menu_extension']) ? "selected='selected'" : null;
					echo "		<option value='ivr:".urlencode($row["ivr_menu_extension"])."' ".$selected.">".escape($row['ivr_menu_name'])." ".escape($row['ivr_menu_extension'])."</option>\n";
				}
				echo "	</optgroup>\n";
			}
		}
		if (permission_exists('call_block_ring_group')) {
			if (!empty($ring_groups)) {
				echo "	<optgroup label='".$text['label-ring_groups']."'>\n";
				foreach ($ring_groups as $row) {
					$selected = ($call_block_app == 'ring_group' && $call_block_data == $row['ring_group_extension']) ? "selected='selected'" : null;
					echo "		<option value='ring_group:".urlencode($row["ring_group_extension"])."' ".$selected.">".escape($row['ring_group_name'])." ".escape($row['ring_group_extension'])."</option>\n";
				}
				echo "	</optgroup>\n";
			}
		}
		if (permission_exists('call_block_voicemail')) {
			if (!empty($voicemails)) {
				echo "	<optgroup label='".$text['label-voicemail']."'>\n";
				foreach ($voicemails as $row) {
					$selected = ($call_block_app == 'voicemail' && $call_block_data == $row['voicemail_id']) ? "selected='selected'" : null;
					echo "		<option value='voicemail:".urlencode($row["voicemail_id"])."' ".$selected.">".escape($row['voicemail_id'])." ".escape($row['voicemail_description'])."</option>\n";
				}
				echo "	</optgroup>\n";
			}
		}
		echo "	</select>";
	}
	call_block_action_select();
	echo "<br />\n";
	echo $text['description-action']."\n";
	echo "\n";
	echo "</td>\n";
	echo "</tr>\n";

	if (permission_exists('call_block_domain')) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
		echo "	".$text['label-domain']."\n";
		echo "</td>\n";
		echo "<td class='vtable' align='left'>\n";
		echo "    <select class='formfld' name='domain_uuid'>\n";
		echo "		<option value=''>".$text['label-global']."</option>\n";
		foreach ($_SESSION['domains'] as $row) {
			echo "	<option value='".escape($row['domain_uuid'])."' ".($row['domain_uuid'] == $domain_uuid ? "selected='selected'" : null).">".escape($row['domain_name'])."</option>\n";
		}
		echo "    </select>\n";
		echo "<br />\n";
		echo $text['description-domain_name']."\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-enabled']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	if (substr($_SESSION['theme']['input_toggle_style']['text'], 0, 6) == 'switch') {
		echo "	<label class='switch'>\n";
		echo "		<input type='checkbox' id='call_block_enabled' name='call_block_enabled' value='true' ".($call_block_enabled == 'true' ? "checked='checked'" : null).">\n";
		echo "		<span class='slider'></span>\n";
		echo "	</label>\n";
	}
	else {
		echo "	<select class='formfld' id='call_block_enabled' name='call_block_enabled'>\n";
		echo "		<option value='true' ".($call_block_enabled == 'true' ? "selected='selected'" : null).">".$text['option-true']."</option>\n";
		echo "		<option value='false' ".($call_block_enabled == 'false' ? "selected='selected'" : null).">".$text['option-false']."</option>\n";
		echo "	</select>\n";
	}
	echo "<br />\n";
	echo $text['description-enable']."\n";
	echo "\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-description']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='call_block_description' maxlength='255' value=\"".escape($call_block_description)."\">\n";
	echo "<br />\n";
	echo $text['description-description']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>";
	echo "</div>\n";
	echo "<br><br>";

	if ($action == "update") {
		echo "<input type='hidden' name='call_block_uuid' value='".escape($call_block_uuid)."'>\n";
	}
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//get recent calls from the db (if not editing an existing call block record)
	if (empty($_REQUEST["id"])) {

		//without block all permission, limit to assigned extension(s)
		if (!permission_exists('call_block_extension') && !empty($_SESSION['user']['extension'])) {
			foreach ($_SESSION['user']['extension'] as $assigned_extension) {
				$assigned_extensions[$assigned_extension['extension_uuid']] = $assigned_extension['user'];
			}
			if (!empty($assigned_extensions)) {
				$x = 0;
				foreach ($assigned_extensions as $assigned_extension_uuid => $assigned_extension) {
					$sql_where_array[] = "extension_uuid = :extension_uuid_".$x;
					$parameters['extension_uuid_'.$x] = $assigned_extension_uuid;
					$x++;
				}
				if (!empty($sql_where_array)) {
					$sql_where .= "and (".implode(' or ', $sql_where_array).") ";
				}
				unset($sql_where_array);
			}
		}

		//get the recent calls
		$sql = "select caller_id_name, caller_id_number, caller_destination, start_epoch, direction, hangup_cause, duration, billsec, xml_cdr_uuid ";
		$sql .= "from v_xml_cdr where domain_uuid = :domain_uuid ";
		$sql .= "and direction <> 'local' ";
		$sql .= $sql_where ?? null;
		$sql .= "order by start_stamp desc ";
		$sql .= limit_offset($_SESSION['call_block']['recent_call_limit']['text']);
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$recent_calls = $database->select($sql, $parameters);
		unset($sql, $parameters);

		echo "<form id='form_list' method='post'>\n";
		echo "<input type='hidden' id='action' name='action' value='add'>\n";

		echo "<div class='action_bar' id='action_bar_sub'>\n";
		echo "	<div class='heading'>";
		echo "		<b id='heading_sub'>".$text['heading-recent_calls']."</b>";
		echo "		<select class='formfld' name='call_block_direction' style='margin-bottom: 6px; margin-left: 15px;' onchange=\"show_direction(this.options[this.selectedIndex].value);\">\n";
		echo "			<option value='' disabled='disabled'>".$text['label-direction']."</option>\n";
		echo "			<option value='inbound'>".$text['label-inbound']."</option>\n";
		echo "			<option value='outbound'>".$text['label-outbound']."</option>\n";
		echo "		</select>\n";
		echo "	</div>\n";
		echo "	<div class='actions'>\n";
		echo button::create(['type'=>'button','id'=>'action_bar_sub_button_back','label'=>$text['button-back'],'icon'=>$settings->get('theme', 'button_icon_back'),'collapse'=>'hide-xs','style'=>'display: none;','link'=>'call_block.php']);
		if ($recent_calls) {
			$select_margin = 'margin-left: 15px;';
			if (permission_exists('call_block_extension')) {
				echo 	"<select class='formfld' style='".$select_margin."' name='extension_uuid'>\n";
				echo "		<option value='' disabled='disabled'>".$text['label-extension']."</option>\n";
				echo "		<option value='' selected='selected'>".$text['label-all']."</option>\n";
				if (!empty($extensions)) {
					foreach ($extensions as $row) {
						$selected = !empty($extension_uuid) && $extension_uuid == $row['extension_uuid'] ? "selected='selected'" : null;
						echo "	<option value='".urlencode($row["extension_uuid"])."' ".$selected.">".escape($row['extension'])." ".escape($row['description'])."</option>\n";
					}
				}
				echo "	</select>";
				unset($select_margin);
			}
			call_block_action_select(true);
			echo button::create(['type'=>'button','label'=>$text['button-block'],'icon'=>'ban','collapse'=>'hide-xs','onclick'=>"modal_open('modal-block','btn_block');"]);
		}
		echo 	"</div>\n";
		echo "	<div style='clear: both;'></div>\n";
		echo "</div>\n";

		if ($recent_calls) {
			echo modal::create(['id'=>'modal-block','type'=>'general','message'=>$text['confirm-block'],'actions'=>button::create(['type'=>'button','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_block','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"modal_close(); list_form_submit('form_list');"])]);
		}

		echo "<div class='card'>\n";

		foreach (['inbound','outbound'] as $direction) {
			echo "<table class='list' id='list_".$direction."' ".($direction == 'outbound' ? "style='display: none;'" : null).">\n";
			echo "<tr class='list-header'>\n";
			echo "	<th class='checkbox'>\n";
			echo "		<input type='checkbox' id='checkbox_all_".$direction."' name='checkbox_all' onclick=\"list_all_toggle('".$direction."');\" ".(empty($result) ? "style='visibility: hidden;'" : null).">\n";
			echo "	</th>\n";
			echo "<th style='width: 1%;'>&nbsp;</th>\n";
			echo th_order_by('caller_id_name', $text['label-name'], $order_by, $order);
			echo th_order_by('caller_id_number', $text['label-number'], $order_by, $order);
			echo th_order_by('caller_destination', $text['label-destination'], $order_by, $order);
			echo th_order_by('start_stamp', $text['label-called'], $order_by, $order);
			echo th_order_by('duration', $text['label-duration'], $order_by, $order, null, "class='right hide-sm-dn'");
			echo "</tr>";

			if (!empty($recent_calls)) {
				foreach ($recent_calls as $x => $row) {
					if ($row['direction'] == $direction) {
						$list_row_onclick_uncheck = "if (!this.checked) { document.getElementById('checkbox_all_".$direction."').checked = false; }";
						$list_row_onclick_toggle = "onclick=\"document.getElementById('checkbox_".$x."').checked = document.getElementById('checkbox_".$x."').checked ? false : true; ".$list_row_onclick_uncheck."\"";
						if (strlen($row['caller_id_number']) >= 7) {
							if (!empty($_SESSION['domain']['time_format']['text']) && $_SESSION['domain']['time_format']['text'] == '24h') {
								$tmp_start_epoch = date('j M Y', $row['start_epoch'])." <span class='hide-sm-dn'>".date('H:i:s', $row['start_epoch']).'</span>';
							}
							else {
								$tmp_start_epoch = date('j M Y', $row['start_epoch'])." <span class='hide-sm-dn'>".date('h:i:s a', $row['start_epoch']).'</span>';
							}
							echo "<tr class='list-row row_".$row['direction']."' href=''>\n";
							echo "	<td class='checkbox'>\n";
							echo "		<input type='checkbox' class='checkbox_".$row['direction']."' name='xml_cdrs[$x][checked]' id='checkbox_".$x."' value='true' onclick=\"".$list_row_onclick_uncheck."\">\n";
							echo "		<input type='hidden' name='xml_cdrs[$x][uuid]' value='".escape($row['xml_cdr_uuid'])."' />\n";
							echo "	</td>\n";
							if (
								file_exists($_SERVER["DOCUMENT_ROOT"]."/themes/".$_SESSION['domain']['template']['name']."/images/icon_cdr_inbound_voicemail.png") &&
								file_exists($_SERVER["DOCUMENT_ROOT"]."/themes/".$_SESSION['domain']['template']['name']."/images/icon_cdr_inbound_answered.png") &&
								file_exists($_SERVER["DOCUMENT_ROOT"]."/themes/".$_SESSION['domain']['template']['name']."/images/icon_cdr_outbound_failed.png") &&
								file_exists($_SERVER["DOCUMENT_ROOT"]."/themes/".$_SESSION['domain']['template']['name']."/images/icon_cdr_outbound_answered.png")
								) {
								$title_mod = null;
								echo "	<td class='center' ".$list_row_onclick_toggle.">";
								switch ($row['direction']) {
									case "inbound":
										if ($row['billsec'] == 0) {
											$title_mod = " ".$text['label-missed'];
											$file_mod = "_voicemail";
										}
										else {
											$file_mod = "_answered";
										}
										echo "<img src='/themes/".$_SESSION['domain']['template']['name']."/images/icon_cdr_inbound".$file_mod.".png' style='border: none;' title='".$text['label-inbound'].$title_mod."'>\n";
										break;
									case "outbound":
										if ($row['billsec'] == 0) {
											$title_mod = " ".$text['label-failed'];
											$file_mod = "_failed";
										}
										else {
											$file_mod = "_answered";
										}
										echo "<img src='/themes/".$_SESSION['domain']['template']['name']."/images/icon_cdr_outbound".$file_mod.".png' style='border: none;' title='".$text['label-outbound'].$title_mod."'>\n";
										break;
								}
								echo "	</td>\n";
							}
							else {
								echo "	<td ".$list_row_onclick_toggle.">&nbsp;</td>";
							}
							echo "	<td ".$list_row_onclick_toggle.">".$row['caller_id_name']." </td>\n";
							echo "	<td ".$list_row_onclick_toggle.">".format_phone($row['caller_id_number'])."</td>\n";
							echo "	<td ".$list_row_onclick_toggle.">".format_phone($row['caller_destination'])."</td>\n";
							echo "	<td class='no-wrap' ".$list_row_onclick_toggle.">".$tmp_start_epoch."</td>\n";
							$seconds = ($row['hangup_cause'] == "ORIGINATOR_CANCEL") ? $row['duration'] : $row['billsec'];  //if they cancelled, show the ring time, not the bill time.
							echo "	<td class='right hide-sm-dn' ".$list_row_onclick_toggle.">".gmdate("G:i:s", $seconds)."</td>\n";
							echo "</tr>\n";
						}
					}
				}
			}
			echo "</table>\n";
		}

		echo "</div>\n";

		echo "<br />\n";
		echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";
		echo "</form>\n";

		//handle hiding and showing of direction recent calls
		echo "<script>\n";
		echo "	function show_direction(direction) {\n";
		echo "		//determine other direction\n";
		echo "		direction_other = direction == 'inbound' ? 'outbound' : 'inbound';\n";

		echo "		//hide other direction list\n";
		echo "		document.getElementById('list_' + direction_other).style.display='none';\n";

		echo "		//uncheck all checkboxes\n";
		echo "		var checkboxes = document.querySelectorAll(\"input[type='checkbox']:not(#call_block_enabled)\")\n";
		echo "		if (checkboxes.length > 0) {\n";
		echo "			for (var i = 0; i < checkboxes.length; ++i) {\n";
		echo "				checkboxes[i].checked = false;\n";
		echo "			}\n";
		echo "		}\n";

		echo "		//show direction list\n";
		echo "		document.getElementById('list_' + direction).style.display='inline';\n";
		echo "	}\n";
		echo "</script>\n";

	}

//make sub action bar sticky
	echo "<script>\n";
	echo "	window.addEventListener('scroll', function(){\n";
	echo "		action_bar_scroll('action_bar_sub', 480, heading_modify, heading_restore);\n";
	echo "	}, false);\n";

	echo "	function heading_modify() {\n";
	echo "		document.getElementById('heading_sub').innerHTML = \"".$text['heading-block_recent_calls']."\";\n";
	echo "		document.getElementById('action_bar_sub_button_back').style.display = 'inline-block';\n";
	echo "	}\n";

	echo "	function heading_restore() {\n";
	echo "		document.getElementById('heading_sub').innerHTML = \"".$text['heading-recent_calls']."\";\n";
	echo "		document.getElementById('action_bar_sub_button_back').style.display = 'none';\n";
	echo "	}\n";

	echo "</script>\n";

//include the footer
	require_once "resources/footer.php";

?>