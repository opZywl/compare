<?php

	include "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";
	
	if (permission_exists('call_center_active_view')) 
	{
		//access granted
	}
	else 
	{
		echo "access denied";
		exit;
	}

	/**
		add multi-lingual support
	*/
	
	require_once "app_languages.php";
	foreach($text as $key => $value) 
	{
		$text[$key] = $value[$_SESSION['domain']['language']['code']];
	}
	
	/**
		get the queue_name and set it as a variable
	*/

	if(isset($_POST["queue_name"]))
	{
		$cc_queue = $_POST["queue_name"];
	}
	
	if(isset($_GET["queue_name"]))
	{
		$cc_queue = $_GET["queue_name"];
	}
	
	$queue_name = $cc_queue.'@'. $_SESSION['domains'][$domain_uuid]['domain_name'];

	/**
		create an event socket connection
	*/
	
	$fp = event_socket_create($_SESSION['event_socket_ip_address'], $_SESSION['event_socket_port'], $_SESSION['event_socket_password']);

	/** 
		get the call center queue, agent and tiers list
	*/

	if (!$fp) 
	{
		echo "Connection to Event Socket failed.";
	}
	else
	{

		/**
			get the tier list
		*/
		
		$switch_cmd = 'callcenter_config queue list tiers '.$queue_name;
		$event_socket_str = trim(event_socket_request($fp, 'api '.$switch_cmd));
		$result = str_to_named_array($event_socket_str, '|');			
		
		/**
			prepare the result for array_multisort
		*/
		
		$x = 0;
		foreach ($result as $row) 
		{
			$tier_result[$x]['level'] = $row['level'];
			$tier_result[$x]['position'] = $row['position'];
			$tier_result[$x]['agent'] = $row['agent'];
			$tier_result[$x]['state'] = trim($row['state']);
			$tier_result[$x]['queue'] = $row['queue'];
			$x++;
		}			

		array_multisort($tier_result, SORT_ASC);
	
		$switch_cmd = 'callcenter_config queue list agents '.$queue_name;
		$event_socket_str = trim(event_socket_request($fp, 'api '.$switch_cmd));
		$agent_result = str_to_named_array($event_socket_str, '|');
		
	
		/**
			get the queue member list
		*/
		
		$switch_cmd = 'callcenter_config queue list members '.$queue_name;
		$event_socket_str = trim(event_socket_request($fp, 'api '.$switch_cmd));
		$result_members = str_to_named_array($event_socket_str, '|');
	
		fclose($fp);
	}
	
	/**
		Break list
	*/
	
	$sql = "select call_center_break_break_uuid, break_name, break_timeout from v_call_center_break_breaks ";
	$sql.= "where domain_uuid = '".$domain_uuid."' ";
	$prep_statement = $db->prepare(check_sql($sql));
	$prep_statement->execute();
	$break_list = $prep_statement->fetchAll(PDO::FETCH_NAMED);
	unset($prep_statement);

	/**
		logged agent list
	*/
	
	$agent_count = 0;
	
	foreach ($tier_result as $tier_row) 
	{
		foreach ($agent_result as $agent_row) 
		{
			if ($tier_row['agent'] == $agent_row['name']) 
			{
				$tmp_name = $agent_row['name'];
				$tmp_name = str_replace('@'.$_SESSION['domain_name'], '', $tmp_name);
				if ($agent_row['status'] != "Logged Out") 
				{						
					$agent_logged[$tier_row['agent']]['status'] = $agent_row['status'];
					$agent_logged[$tier_row['agent']]['state'] = $tier_row['state'];
					$agent_logged[$tier_row['agent']]['level'] = $tier_row['level'];
					$agent_logged[$tier_row['agent']]['position'] = $tier_row['position'];
					$agent_logged[$tier_row['agent']] = $agent_row;
					$agent_logged[$tier_row['agent']]['name'] = $tmp_name;
					
					if ($agent_row['status'] == "On Break")
					{
						foreach ($break_list as $row_break)
						{
							if ($row_break['call_center_break_break_uuid'] == $agent_row['state'])
							{
								$agent_logged[$tier_row['agent']]['break_name'] = $row_break['break_name'];
								$agent_logged[$tier_row['agent']]['break_timeout'] = $row_break['break_timeout'] * 60;
								
								if (strlen($agent_logged[$tier_row['agent']]['break_timeout']) > 0 )
								{
									$tmp_break_timeout = gmdate("H:i:s", ($agent_logged[$tier_row['agent']]['break_timeout']));
								}
								else
								{
									$tmp_break_timeout = '';
								}
								
								$agent_logged[$tier_row['agent']]['break_name'] = $agent_logged[$tier_row['agent']]['break_name'];
								
								break;
							}
						}
					}
					
					$agent_count++;					
				}
				else
				{
					unset($_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$tmp_name]['logged_epoch']);
				}
			}
		}
	}
	
	foreach($agent_array as $key => $agent_arr) 
	{
		foreach($agent_arr as $key2 => $row) 
		{
			if (strtolower($row['status']) == 'em pausa') 
			{
				foreach ($result as $row_break) 
				{
					if ($row_break['call_center_break_break_uuid'] == $row['state']) 
					{
						$agent_array[$key][$key2]['break_name'] = $row_break['break_name'];
						break;
					}
				}
			}
		}
	}
		
	/**
		get memcache infos
	*/
	
	$memcache = new Memcache;
	$res = $memcache->connect('localhost', 11211);
	if (!$res) 
	{
		echo "event socket failed!";
		die;
	}
	else
	{
		/**
			get last numbers
		*/
		
		$key = "v_xml_cdr_last_numbers:".$domain_uuid.":".$cc_queue.'@'. $_SESSION['domains'][$domain_uuid]['domain_name'];
		$switch_result = $memcache->get($key);
		$memcache_last_numbers = json_decode($switch_result);
				
		/** 
			get queue
		*/
		
		$key = "v_xml_cdr_cc_queue_status:".$queue_name.":".date('Y-m-d');
		$switch_result = $memcache->get($key);
		$memcache_queue = json_decode($switch_result);

		/** 
			agent memcache data
		*/
		
		foreach ($agent_logged as $name => $row)
		{				
			$key = "v_xml_cdr_cc_agent_status:".$domain_uuid.":".$queue_name.":".$row['name'].'@'. $_SESSION['domains'][$domain_uuid]['domain_name'];
			$switch_result = $memcache->get($key);
			$agent_logged[$name]['memcache'] = json_decode($switch_result);
		}
				
		/** 
			close connection
		*/
		
		$memcache->close();
		
		/** 
			geral tma entrada de toda operacao
		*/
		
		$tmp_total_count    = $memcache_queue[0]->answered_count + $memcache_queue[0]->outbound_answered_count;
		$tmp_total_duration = $memcache_queue[0]->answered_duration + $memcache_queue[0]->outbound_answered_duration;					
		$tmp_total_tma = $tmp_total_duration / $tmp_total_count;
		$tmp_total_tma = gmdate("H:i:s", $tmp_total_tma);
		
		/** 
			outbound
		*/
		
		$tmp_outbound_total_count	    = check_str0($memcache_queue[0]->outbound_count);
		$tmp_outbound_answered_count    = check_str0($memcache_queue[0]->outbound_answered_count);
		$tmp_outbound_answered_duration = check_str0($memcache_queue[0]->outbound_answered_duration);
		$tmp_outbound_answered_tma      = check_str0($tmp_outbound_answered_duration / $tmp_outbound_answered_count);
		$tmp_outbound_answered_tma      = gmdate("H:i:s", $tmp_outbound_answered_tma);				
		
		/** 
			inbound
		*/
		
		$tmp_inbound_answered_count    = check_str0($memcache_queue[0]->answered_count);
		$tmp_inbound_answered_duration = check_str0($memcache_queue[0]->answered_duration);
		$tmp_inbound_answered_tma      = check_str0($tmp_inbound_answered_duration / $tmp_inbound_answered_count);
		$tmp_inbound_answered_tma      = gmdate("H:i:s", $tmp_inbound_answered_tma);
		
		$tme_queue_duration = check_str0($memcache_queue[0]->queue_duration); //tempo fila das atendidas
	
		/** 
			canceled
		*/
		
		$tmp_canceled_count    = check_str0($memcache_queue[0]->canceled_count);
		$tmp_canceled_duration = check_str0($memcache_queue[0]->canceled_duration);
		$tmp_canceled_tma      = check_str0($tmp_canceled_duration / $tmp_canceled_count);
		$tmp_canceled_tma      = gmdate("H:i:s", $tmp_canceled_tma);
		
		$tmp_canceled_10          = check_str0($memcache_queue[0]->canceled_10);
		$tmp_canceled_10_duration = check_str0($memcache_queue[0]->canceled_10_duration);
		$tmp_canceled_10_tma      = check_str0($tmp_canceled_10_duration / $tmp_canceled_10_duration);
		$tmp_canceled_10_tma      = gmdate("H:i:s", $tmp_canceled_10_tma);
		
		$tmp_canceled_10_up          = check_str0($memcache_queue[0]->canceled_10_up);
		$tmp_canceled_10_up_duration = check_str0($memcache_queue[0]->canceled_10_up_duration);
		$tmp_canceled_10_up_tma      = check_str0($tmp_canceled_10_duration / $tmp_canceled_10_up);
		$tmp_canceled_10_up_tma      = gmdate("H:i:s", $tmp_canceled_10_up_tma);

		/** 
			Droped count (issue sound)
		*/
		
		$tmp_droped_count = check_str0($memcache_queue[0]->issue_sound_count);

		/** 
			Total inbound
		*/
		
		$inbound_count = check_str0($tmp_inbound_answered_count + $tmp_canceled_count + $tmp_droped_count);

		/**
			TME
		*/
		
		$tme_queue_duration = gmdate("H:i:s", $tme_queue_duration / $tmp_inbound_answered_count);

		/** 
			Max in a queue and answered
		*/
		
		$tmp_max_time_queue_answered = check_str0($memcache_queue[0]->max_time_queue_answered);
		$tmp_max_time_queue_answered = gmdate("H:i:s", $tmp_max_time_queue_answered);
		
		/** 
			Max in a queue and not answered
		*/
	
		$tmp_max_time_queue_canceled = check_str0($memcache_queue[0]->max_time_queue_canceled);
		$tmp_max_time_queue_canceled = gmdate("H:i:s", $tmp_max_time_queue_canceled);
		
		$tmp_tma = check_str0($memcache_queue[0]->max_time_queue_canceled);
		$tmp_tma = gmdate("H:i:s", $tmp_tma);
		
		$ns_5  = check_str0($memcache_queue[0]->ns_5);
		$ns_10 = check_str0($memcache_queue[0]->ns_10);
		$ns_15 = check_str0($memcache_queue[0]->ns_15);
		$ns_20 = check_str0($memcache_queue[0]->ns_20);
		$ns_25 = check_str0($memcache_queue[0]->ns_25);
		$ns_30 = check_str0($memcache_queue[0]->ns_30);
		$ns_35 = check_str0($memcache_queue[0]->ns_35);
		$ns_40 = check_str0($memcache_queue[0]->ns_40);
		$ns_45 = check_str0($memcache_queue[0]->ns_45);
		$ns_50 = check_str0($memcache_queue[0]->ns_50);
		$ns_55 = check_str0($memcache_queue[0]->ns_55);
		$ns_60 = check_str0($memcache_queue[0]->ns_60);
		$ns_60_up = check_str0($memcache_queue[0]->ns_60_up);
		
		$tmp_value_ns = $ns_5 + $ns_10;
		$tmp_perc_ns_10 = sprintf("%.0d%%", ($tmp_value_ns / $tmp_inbound_answered_count * 100));
		
		$tmp_value_ns = $tmp_value_ns + $ns_15 + $ns_20;
		$tmp_perc_ns_20 = sprintf("%.0d%%", ($tmp_value_ns / $tmp_inbound_answered_count) * 100);					
		
		$tmp_value_ns = $tmp_value_ns + $ns_25 + $ns_30;
		$tmp_perc_ns_30 = sprintf("%.0d%%", ($tmp_value_ns / $tmp_inbound_answered_count) * 100);
		
		$tmp_value_ns = $tmp_value_ns + $ns_35 + $ns_40 + $ns_45 + $ns_50 + $ns_55 + $ns_60;
		$tmp_perc_ns_60 = sprintf("%.0d%%", ($tmp_value_ns / $tmp_inbound_answered_count) * 100);
	}

	$c_queue_agents_agents = 0;
	$c_queue_agents_on_break = 0;
	$c_queue_agents_available = 0;
	$c_queue_agents_in_queue_call = 0;
	$c_queue_agents_logged_out = 0;
	$c_queue_agents_attend = 0;
	
	foreach ($agent_logged as $key_name => $agent_row) 
	{		
		$state = $agent_row['state'];
		$status = $agent_row['status'];

		if (strtolower($status) == "on outbound")
		{
			$c_queue_agents_in_queue_call++;
		}

		if (strtolower($status) == "on local_call")
		{
			$c_queue_agents_in_queue_call++;
		}
		
		$c_status = 1;
		if ($status == "Logged Out") 
		{
			$c_queue_agents_logged_out++;
			$status = $text['label-logged-out'];
			$c_status = 1;
		}
		
		if ($status == "Available") 
		{
			$c_queue_agents_attend++;
			$status = $text['label-waiting'];
			$c_status = 2;								
		}

		if ($state == "Waiting") 
		{
			$c_queue_agents_available++;
			$state = $text['label-waiting'];
			$c_state = 6;
		}
		
		if ($state == "Receiving") 
		{
			$c_queue_agents_in_queue_call++;
			$state = $text['label-receiving'];
			$c_state = 4;
		}
		
		if ($state == "In a queue call")
		{
			$c_queue_agents_in_queue_call++;
			$state = $text['label-in-a-queue-call'];
			$c_state = 2;
		}

		if(strtolower($status) == 'on break') 
		{
			$c_queue_agents_agents++;
			$c_queue_agents_on_break++;
		}
		else 
		{
			$c_queue_agents_agents++;
		}
	}
	
	$joined_seconds_aux = 0;
	$joined_menbers_count = 0;
	
	foreach ($result_members as $row) 
	{
		$queue = $row['queue'];
		$uuid = $row['uuid'];
		$caller_number = $row['cid_number'];
		$caller_name = $row['cid_name'];
		$system_epoch = $row['system_epoch'];
		$joined_epoch = $row['joined_epoch'];
		$state = $row['state'];

		if ($state == "Answered") 
		{			
			$state = "Chamada Atendida";
			$c_state = 1;
		}
		
		if ($state == "Waiting") 
		{
			$joined_seconds = time() - $joined_epoch;
			$joined_seconds_aux = $joined_seconds_aux + $joined_seconds;
			$joined_menbers_count++;
			$state = "Em Fila";
			$c_state = 1;
		}
		
		if ($state == "Trying") 
		{
			$joined_seconds = time() - $joined_epoch;
			$joined_seconds_aux = $joined_seconds_aux + $joined_seconds;
			$joined_menbers_count++;
			$state_pt_br = "Em Fila";
			$c_state = 1;
		}
	}
	
	$joined_seconds_aux = intval($joined_seconds_aux) / intval($joined_menbers_count);
	$queue_joined_length_hour = floor($joined_seconds_aux/3600);
	$queue_joined_length_min = floor($joined_seconds_aux/60 - ($queue_joined_length_hour * 60));
	$queue_joined_length_sec = $joined_seconds_aux - (($queue_joined_length_hour * 3600) + ($queue_joined_length_min * 60));
	$queue_joined_length_min = sprintf("%02d", $queue_joined_length_min);
	$queue_joined_length_sec = sprintf("%02d", $queue_joined_length_sec);
	$queue_joined_length = $queue_joined_length_hour.':'.$queue_joined_length_min.':'.$queue_joined_length_sec;
	
	/**
		convert the string to a named array
	*/
		
	function str_to_named_array($tmp_str, $tmp_delimiter) 
	{
		$tmp_array = explode ("\n", $tmp_str);
		$result = '';
		if (trim(strtoupper($tmp_array[0])) != "+OK") 
		{
			$tmp_field_name_array = explode ($tmp_delimiter, $tmp_array[0]);
			$x = 0;
			foreach ($tmp_array as $row) 
			{
				if ($x > 0) 
				{
					$tmp_field_value_array = explode ($tmp_delimiter, $tmp_array[$x]);
					$y = 0;
					foreach ($tmp_field_value_array as $tmp_value) 
					{
						$tmp_name = $tmp_field_name_array[$y];
						if (trim(strtoupper($tmp_value)) != "+OK") 
						{
							$result[$x][$tmp_name] = $tmp_value;
						}
						$y++;
					}
				}
				$x++;
			}
			unset($row);
		}
		return $result;
	}
	
	$call_center_queue = array();
	$call_center_agents = array();

	$call_center_queue["queue_name"] = strtoupper($inf[0]["queue_name"]);

	$row_style_dashboard_["0"] = "row_style_dashboard_0"; // "#e5e9f0"; //cinza //background
	$row_style_dashboard_["1"] = "row_style_dashboard_1"; //"#fff"; //branco // preparacao
	$row_style_dashboard_["2"] = "row_style_dashboard_2"; //"#11EE11"; //verde //em ligacao 
	$row_style_dashboard_["3"] = "row_style_dashboard_3"; //"#F4F42C"; //amarelo //pausa
	$row_style_dashboard_["4"] = "row_style_dashboard_4"; //"#F22BEE"; //rosa //ringando
	$row_style_dashboard_["5"] = "row_style_dashboard_5"; //"#DD0D0D"; //vermelho //pausa excedida
	$row_style_dashboard_["6"] = "row_style_dashboard_6"; //"#11EE11"; // verde //aguardando/Livre
	$row_style_dashboard_["7"] = "row_style_dashboard_7"; //"#FF9933"; // laranja //aguardando/Livre
	
	$x = 0;

	$call_center_queue = array();
	
	foreach ($agent_logged as $key_name => $agent_row)
	{
		/**
			Array com as informações dos agentes
		*/
		
		$agents = array();
		
		/**
			executa somente uma vez apenas
		*/
		
		$name = $agent_row['name'];
		
		if (strlen($_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['logged_epoch']) == 0)
		{
			/**
				pega o último logout
			*/
			
			$sql = "select date_time from v_call_center_agent_status ";
			$sql .= "where agent_name = '".$agent_row['name']."' ";
			$sql .= "and agent_status = 'Logged Out' ";
			$sql .= "and domain_uuid = '$domain_uuid' ";
			$sql .= "order by date_time desc  ";
			$sql .= "limit 1";		
			$prep_statement = $db->prepare(check_sql($sql));
			$prep_statement->execute();
			$result = $prep_statement->fetchAll(PDO::FETCH_NAMED);		
			
			$last_logged_out = $result[0]["date_time"];				
			
			/**
				pega o primeiro login, depois do último logout, 
				para contar o tempo q ele está logado
			*/
			
			unset ($prep_statement, $sql);				
			$sql = "select date_time from v_call_center_agent_status ";
			$sql .= "where agent_name = '".$agent_row['name']."' ";
			$sql .= "and agent_status = 'Available' ";
			if (strlen($last_logged_out) > 0) {$sql .= "and date_time > '$last_logged_out' ";} 								
			$sql .= "and domain_uuid = '$domain_uuid' ";
			$sql .= "order by date_time asc  ";
			$sql .= "limit 1";					
			$prep_statement = $db->prepare(check_sql($sql));
			$prep_statement->execute();
			$result = $prep_statement->fetchAll(PDO::FETCH_NAMED);										
			$logged_time = strtotime($result[0]["date_time"]);
			$_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['logged_epoch'] = $logged_time;
			unset ($prep_statement);
		}
		
		/**
			get phone number
		*/
		
		$phone = "";
		if (strpos($agent_row['state'], "att_xfer@") === false)
		{
			$att_xfer = false;
		}
		else
		{
			$att_xfer = true;
		}
		if ($agent_row['state'] == "In a queue call")
		{					
			foreach ($result_members as $members_row)
			{
				if ($members_row["serving_agent"] == $agent_row['name']."@".$_SESSION['domain_name'])
				{
					$phone = $members_row["cid_number"];
					break;
				}
			}
		}	
		
		$a_uuid = $agent_row['uuid'];
		$contact = $agent_row['contact'];
		//jive 
		//{cc_agent_extension=user/8005@onjive.myuc2b.com,originate_timeout=15,sip_cid_type=pid}sofia/gateway/59e88e32-9efd-4189-8099-3b51eb85afc5/8005
		//$contact = "{cc_agent_extension=user/8005@onjive.myuc2b.com,originate_timeout=15,sip_cid_type=pid}sofia/gateway/59e88e32-9efd-4189-8099-3b51eb85afc5/8005";
		
		//normal 
		//{call_timeout=30}user/2008@calliope.myuc2b.com
		
		$pattern = "/.*user\/(.*)@.*/i";
		if (preg_match($pattern, $contact, $matches) === 1)
		{
			$a_exten = $matches[1];
		}elseif (strpos($contact, 'verto_contact') > 0)
		{
			$tmp_array = explode ("@", $contact);
			$a_exten = $tmp_array[0];
			$tmp_array = explode ("(", $a_exten);
			$a_exten = $tmp_array[1];
		}
		else
		{
			$a_exten = preg_replace("/user\//", "", $contact);
			$a_exten = preg_replace("/@.*/", "", $a_exten);
			$a_exten = preg_replace("/{.*}/", "", $a_exten);
			$a_exten = $contact;
		}
		
		
		$state = $agent_row['state'];
		$status = $agent_row['status'];
		$break_name = $agent_row['break_name'];
		$break_timeout = $agent_row['break_timeout'];
		$max_no_answer = $agent_row['max_no_answer'];
		$wrap_up_time = $agent_row['wrap_up_time'];
		$reject_delay_time = $agent_row['reject_delay_time'];
		$busy_delay_time = $agent_row['busy_delay_time'];
		$no_answer_delay_time = $agent_row['no_answer_delay_time'];
		$last_bridge_start = $agent_row['last_bridge_start'];
		$last_bridge_end = $agent_row['last_bridge_end'];
		$last_offered_call = $agent_row['last_offered_call'];
		$last_status_change = $agent_row['last_status_change'];
		$no_answer_count = check_str0($agent_row['memcache'][0]->rejected_count);
		$outbound_count = check_str0($agent_row['memcache'][0]->outbound_count);
		$answered_count = check_str0($agent_row['memcache'][0]->answered_count);
		$not_answered_count = check_str0($agent_row['memcache'][0]->not_answered_count);
		$answered_duration = check_str0($agent_row['memcache'][0]->answered_duration);
		$first_login = check_str0($agent_row['memcache'][0]->first_login);
		$calls_answered = $agent_row['calls_answered'];
		$talk_time = $agent_row['talk_time'];
		$ready_time = $agent_row['ready_time'];
		$delay_time = $ready_time - $last_status_change;
		$delay_status = null;
		
		if ($delay_time > 0 && $ready_time > time())
		{
			if ($delay_time == $wrap_up_time) {$delay_status = "Em Preparação";}
			if ($delay_time == $reject_delay_time) {$delay_status = "Chamada Rejeitada";}
			if ($delay_time == $busy_delay_time) {$delay_status = "Ramal Ocupado";}
			if ($delay_time == $no_answer_delay_time) {$delay_status = "Não Atendida";}
			if ($delay_time == 5) {$delay_status = "Ramal Ocupado";}
		}
		
		$tmp_asw_duration = check_str0($agent_row['memcache'][0]->answered_duration) + check_str0($agent_row['memcache'][0]->outbound_answered_duration);
		$tmp_asw_count = check_str0($agent_row['memcache'][0]->answered_count) + check_str0($agent_row['memcache'][0]->outbound_answered_count);
		$tma_agent =  check_str0($tmp_asw_duration / $tmp_asw_count);
		$tma_agent = gmdate("H:i:s", $tma_agent);
		
		$last_offered_call_seconds = $last_bridge_start;
		$last_offered_call_length_hour = floor($last_offered_call_seconds/3600);
		$last_offered_call_length_min = floor($last_offered_call_seconds/60 - ($last_offered_call_length_hour * 60));
		$last_offered_call_length_sec = $last_offered_call_seconds - (($last_offered_call_length_hour * 3600) + ($last_offered_call_length_min * 60));
		$last_offered_call_length_min = sprintf("%02d", $last_offered_call_length_min);
		$last_offered_call_length_sec = sprintf("%02d", $last_offered_call_length_sec);
		$last_bridge_start_length = $last_offered_call_length_hour.':'.$last_offered_call_length_min.':'.$last_offered_call_length_sec;
		$last_bridge_start_length = date('H:i:s', $last_bridge_start);
		
		$last_offered_call_seconds = time() - $last_bridge_end;
		$last_offered_call_length_hour = floor($last_offered_call_seconds/3600);
		$last_offered_call_length_min = floor($last_offered_call_seconds/60 - ($last_offered_call_length_hour * 60));
		$last_offered_call_length_sec = $last_offered_call_seconds - (($last_offered_call_length_hour * 3600) + ($last_offered_call_length_min * 60));
		$last_offered_call_length_min = sprintf("%02d", $last_offered_call_length_min);
		$last_offered_call_length_sec = sprintf("%02d", $last_offered_call_length_sec);
		$last_bridge_end_length = $last_offered_call_length_hour.':'.$last_offered_call_length_min.':'.$last_offered_call_length_sec;
		$last_bridge_end_length = date('H:i:s', $last_bridge_end);
		
		$last_offered_call_seconds = time() - $last_offered_call;
		$last_offered_call_length_hour = floor($last_offered_call_seconds/3600);
		$last_offered_call_length_min = floor($last_offered_call_seconds/60 - ($last_offered_call_length_hour * 60));
		$last_offered_call_length_sec = $last_offered_call_seconds - (($last_offered_call_length_hour * 3600) + ($last_offered_call_length_min * 60));
		$last_offered_call_length_min = sprintf("%02d", $last_offered_call_length_min);
		$last_offered_call_length_sec = sprintf("%02d", $last_offered_call_length_sec);
		$last_offered_call_length = $last_offered_call_length_hour.':'.$last_offered_call_length_min.':'.$last_offered_call_length_sec;
		$last_offered_call_length = date('H:i:s', $last_offered_call);
		
		$last_status_change_seconds = time() - $last_status_change;
		$last_status_change_length_hour = floor($last_status_change_seconds/3600);
		$last_status_change_length_min = floor($last_status_change_seconds/60 - ($last_status_change_length_hour * 60));
		$last_status_change_length_sec = $last_status_change_seconds - (($last_status_change_length_hour * 3600) + ($last_status_change_length_min * 60));
		$last_status_change_length_min = sprintf("%02d", $last_status_change_length_min);
		$last_status_change_length_sec = sprintf("%02d", $last_status_change_length_sec);
		$last_status_change_length_hour = sprintf("%02d", $last_status_change_length_hour);
		$last_status_change_length = $last_status_change_length_hour.':'.$last_status_change_length_min.':'.$last_status_change_length_sec;
		
		$direction = "fas fa-circle";								
		
		if (strtolower($state) == "in a queue call")
		{
			$direction = "fas fa-arrow-left";								
		}
		
		if (strtolower($status) == "on outbound")
		{
			$direction = "fas fa-arrow-right";
			$tmp_array = explode ("@", $state);
			$phone = $tmp_array[0];
			$a_uuid = $tmp_array[1];
			
			if ($att_xfer == true) {
				fclose($fp);
				$fp = event_socket_create($_SESSION['event_socket_ip_address'], $_SESSION['event_socket_port'], $_SESSION['event_socket_password']);
				if ($fp) {
					$switch_cmd = "uuid_getvar $a_uuid callee_id_number";
					$phone = trim(event_socket_request($fp, 'api '.$switch_cmd));
					if ($phone  == "_undef_" || strpos($phone, "-ERR No such channel!") == 1) {
						$phone = "BLANK";
					}
					fclose($fp);
				}
				unset($fp);
			}
			
		}				

		if (strtolower($status) == "on local_call")
		{
			$direction = "fas fa-arrows-alt-h";
			$phone = $state;
			$tmp_array = explode ("@", $state);
			$phone = $tmp_array[0];
			$a_uuid = $tmp_array[1];
		}
		
		if (strtolower($status) == "on break" && strpos($state, 'ramal_local:') == 0)
		{
			$direction = "fas fa-square";
			$phone = substr($state, 12);
		}
		
		$c_status = 1;
		if ($status == "Logged Out")
		{
			$status = $text['label-logged-out'];
			$c_status = 1;
		}	
		
		if ($status == "Available")
		{
			$status = $text['label-available'];
			$c_status = 2;								
		}

		if ($state == "Waiting")
		{
			$state = $text['label-waiting'];
			$c_state = 6;
			
			if (($last_bridge_end + $wrap_up_time) > Time())
			{
				$c_state = 1;
				$wrap_time = ($last_bridge_end + $wrap_up_time) - Time();
				$state = $text['label-in-preparation'] . " " . $wrap_time . " seg.";
			}
		}
		
		if ($state == "Receiving")
		{
			$state = $text['label-receiving'];
			$c_state = 4;
		}
		
		if ($state == "In a queue call")
		{
			$state = $text['label-in-a-queue-call'];
			$c_state = 2;
		}
		
		if ($_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['last_status'] == null)
		{
			if ($state == "Livre")
			{
				if ($last_bridge_end < $last_status_change)
				{
					$_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['last_status_change'] = $last_status_change;
				}
				else
				{
					$_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['last_status_change'] = $last_bridge_end;
				}
				
				$_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['last_status'] = $state;
			}
			
			if (strtolower($status) == "on break")
			{
				$_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['last_status_change'] = $last_status_change;
				$_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['last_status'] = $state;
			}
		}
		else
		{
			if ($last_status_change > $_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['last_status_change'])
			{						
				$_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['last_status_change'] = $last_status_change;
				$_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['last_status'] = $state;
			}
		}
		
		if ($_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['last_status'] != $state)
		{
			$_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['last_status'] = $state;
			$_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['last_status_change'] = time();
		}
		
		$last_status_change_seconds = time() - $_SESSION['domains'][$domain_uuid]['callcenter'][$queue_name]['agent'][$name]['last_status_change'];
		$last_status_change_length_hour = floor($last_status_change_seconds/3600);
		$last_status_change_length_min = floor($last_status_change_seconds/60 - ($last_status_change_length_hour * 60));
		$last_status_change_length_sec = $last_status_change_seconds - (($last_status_change_length_hour * 3600) + ($last_status_change_length_min * 60));
		$last_status_change_length_min = sprintf("%02d", $last_status_change_length_min);
		$last_status_change_length_sec = sprintf("%02d", $last_status_change_length_sec);
		$last_status_change_length_hour = sprintf("%02d", $last_status_change_length_hour);
		$last_status_change_length = $last_status_change_length_hour.':'.$last_status_change_length_min.':'.$last_status_change_length_sec;
		
		if (strtolower($status) == "on break" && is_uuid($state) && $break_timeout > 0)
		{
			if($last_status_change_seconds > $break_timeout)
			{
				$pausa_excedida = true;
			}
			else
			{
				$pausa_excedida = false;
			}
		}
		
		if ($tier_state == "Ready") $tier_state = $text['label-ready'];
		if ($tier_state == "Offering") $tier_state = $text['label-offering'];
		if ($tier_state == "No Answer") $tier_state = $text['label-no-answer'];
		if ($tier_state == "Active Inbound") $tier_state = $text['label-active-inbound'];
			
		/**
			Agent Name
		*/
		
		$agents["name"] = $name;
		
		/**
			Extension
		*/
		
		$tmp_arr = explode('/', $a_exten);
		$extension = $tmp_arr[count($tmp_arr)-1];
		$extension = strlen($extension) == 0 ? $a_exten : $extension;
		$agents["extension"] = $extension;
		
		/**
			State
		*/
		
		if (strlen($delay_status) > 0)
		{
			$agents["class"] = $row_style_dashboard_[3];
			$agents["state"] = $delay_status." ".($ready_time - time())." Seg.";
		}
		elseif(strtolower($status) == 'on break')
		{
			$agents["class"] = $pausa_excedida ? $row_style_dashboard_[5] : $row_style_dashboard_[3];
			
			$break_time = ($last_status_change_seconds - $break_timeout);
			
			if($pausa_excedida)
			{
				$break_length_hour = floor($break_time/3600);
				$break_length_min = floor($break_time/60 - ($break_length_hour * 60));
				$break_length_sec = $break_time - (($break_length_hour * 3600) + ($break_length_min * 60));
				$break_length_min = sprintf("%02d", $break_length_min);
				$break_length_sec = sprintf("%02d", $break_length_sec);
				$break_length = $break_length_hour.':'.$break_length_min.':'.$break_length_sec;
				$agents["state"] = $break_name . " (" . $break_length . ")";
			}
			else
			{
				$break_length_hour = floor($break_timeout/3600);
				$break_length_min = floor($break_timeout/60 - ($break_length_hour * 60));
				$break_length_sec = $break_timeout - (($break_length_hour * 3600) + ($break_length_min * 60));
				$break_length_min = sprintf("%02d", $break_length_min);
				$break_length_sec = sprintf("%02d", $break_length_sec);
				$break_length = $break_length_hour.':'.$break_length_min.':'.$break_length_sec;
				
				$agents["state"] = $break_name . " (" . $break_length . ")";
			}
		}
		elseif(strlen($phone) > 0)
		{
			$phone_formated = $phone;
			$phone_formated = format_phone_number($phone);
			$agents["class"] = $row_style_dashboard_[2];
			$agents["state"] = $phone_formated;
		}
		else
		{
			$agents["class"] = $row_style_dashboard_[$c_state];
			$agents["state"] = $state;
		}
		
		/**
			State Time
		*/
		
		$agents["last_status_change_length"] = $last_status_change_length;							
		
		/**
			Tempo Logado
		*/

		if(strlen($first_login) > 0)
		{
			$sql  = "select * ";
			$sql .= "from v_xml_cdr_call_center_agent_consolidate ";
			$sql .= "where domain_uuid = '".$domain_uuid."' ";
			$sql .= "and cc_queue = '".$queue_name."' ";
			$sql .= "and cc_agent_name = '".$agent_row['name']."' ";
			$sql .= "limit 1;";
			$prep_statement = $db->prepare(check_sql($sql));
			$prep_statement->execute();
			$result = $prep_statement->fetchAll(PDO::FETCH_NAMED);
			unset($sql, $prep_statement);
			$first_login = $result[0]['first_login'];
		}
		
		$loged_time = time() - $first_login;
		$loged_time = gmdate("H:i:s", $loged_time);

		$agents["loged_time"] = $loged_time;
		$agents["direction"] = $direction;
		$agents["answered_count"] = $answered_count;
		$agents["not_answered_count"] = $not_answered_count;
		$agents["outbound_count"] = $outbound_count;
		
		/**
			TMA de Entrada
		*/
		
		$agents["tma_agent"] = $tma_agent;
		
		$commands_agents = '';
		
		if (strlen($phone) == 0)
		{
			if (permission_exists('call_center_active_options'))
			{
				if($tier_state == "Offering" || $tier_state == "Active Inbound" || $tier_state == "Oferecendo Ligação" || $tier_state == "Ligação de Entrada")
				{
					$orig_command="{origination_caller_id_name=eavesdrop,origination_caller_id_number=".$a_exten."}user/".$_SESSION['user']['extension'][0]['user']."@".$_SESSION['domain_name']." %26eavesdrop(".$a_uuid.")";
					$commands_agents .= "<a href='javascript:void(0);' onclick=\"confirm_response = confirm('".$text['message-confirm']."');if (confirm_response){send_cmd('call_center_exec.php?cmd=originate+".$orig_command.")');}\"><i class=\"fas fa-assistive-listening-systems\"></i></a>";
					$xfer_command = $a_uuid." -bleg ".$_SESSION['user']['extension'][0]['user']." XML ".$_SESSION['domain_name'];
					$xfer_command = urlencode($xfer_command);
					$commands_agents .= "<a href='javascript:void(0);' onclick=\"confirm_response = confirm('".$text['message-confirm']."');if (confirm_response){send_cmd('call_center_exec.php?cmd=uuid_transfer+".$xfer_command."');}\"><i class=\"fas fa-exchange\"></i></a>";
				}
				else
				{
					$orig_call="{origination_caller_id_name=c2c-".urlencode($name).",origination_caller_id_number=".$a_exten."}user/".$_SESSION['user']['extension'][0]['user']."@".$_SESSION['domain_name']." %26bridge(user/".$a_exten."@".$_SESSION['domain_name'].")";
					$commands_agents .= "<a style=\"display: inline-block;\" href='javascript:void(0);' onclick=\"confirm_response = confirm('".$text['message-confirm']."');if (confirm_response){send_cmd('call_center_exec.php?cmd=originate+".$orig_call.")');}\"><i class=\"fas fa-phone\"></i></a>";
				}
			}
		}
		else
		{
			if (permission_exists('call_center_active_options'))
			{
				$q_caller_number = urlencode($phone);						
				$orig_command="{origination_caller_id_name=eavesdrop,origination_caller_id_number=".$q_caller_number."}user/".$_SESSION['user']['extension'][0]['user']."@".$_SESSION['domain_name']." %26eavesdrop(".$a_uuid.")";
				$commands_agents .= "<a style=\"display: inline-block;\" href='javascript:void(0);' onclick=\"confirm_response = confirm('".$text['message-confirm']."');if (confirm_response){send_cmd('call_center_exec.php?cmd=originate+".$orig_command.")');}\"><i class=\"fas fa-headphones\"></i></a>";
			}
			
			if (permission_exists('call_center_active_options_hangup'))
			{
				$commands_agents .= "<a style=\"display: inline-block;\" href='javascript:void(0);' onclick=\"confirm_response = confirm('".$text['confirm-hangup']."');if (confirm_response){send_cmd('call_center_exec.php?cmd=uuid_kill%20'+(escape('".$a_uuid."')));}\"><i class=\"fas fa-phone-slash\"></i></a>";
			}
		}
		
		$agents["commands"] = $commands_agents;
		
		$call_center_queue["agents"][$x] = $agents;
				
		$x++;
	}
	
	/**
		Fila de Atendimento
	*/
	
	$x = 0;
	$q_waiting=0;
	$q_trying=0;
	$q_answered=0;

	$fp = event_socket_create($_SESSION['event_socket_ip_address'], $_SESSION['event_socket_port'], $_SESSION['event_socket_password']);
	
	foreach ($result_members as $row)
	{
		$queue = $row['queue'];
		$system = $row['system'];
		$uuid = $row['uuid'];
		$session_uuid = $row['session_uuid'];
		$caller_number = $row['cid_number'];
		$caller_name = $row['cid_name'];
		$system_epoch = $row['system_epoch'];
		$joined_epoch = $row['joined_epoch'];
		$rejoined_epoch = $row['rejoined_epoch'];
		$bridge_epoch = $row['bridge_epoch'];
		$abandoned_epoch = $row['abandoned_epoch'];
		$base_score = $row['base_score'];
		$skill_score = $row['skill_score'];
		$serving_agent = $row['serving_agent'];
		$serving_system = $row['serving_system'];
		$state = $row['state'];
		
		if ($state=="Trying") {$q_trying = $q_trying + 1;}
		if ($state=="Waiting") {$q_waiting = $q_waiting + 1;}
		if ($state=="Answered") {$q_answered = $q_answered + 1;}
		if ($row["state"] == "Answered" ) { continue; } //pq está sendo exibida já na linha do agente
		
		$joined_seconds = time() - $joined_epoch;
		$joined_length_hour = floor($joined_seconds/3600);
		$joined_length_min = floor($joined_seconds/60 - ($joined_length_hour * 60));
		$joined_length_sec = $joined_seconds - (($joined_length_hour * 3600) + ($joined_length_min * 60));
		$joined_length_min = sprintf("%02d", $joined_length_min);
		$joined_length_sec = sprintf("%02d", $joined_length_sec);
		$joined_length = $joined_length_hour.':'.$joined_length_min.':'.$joined_length_sec;

		if ($state == "Answered")
		{
			$state = "Chamada Atendida";
			$c_state = 1;
		}
		
		if ($state == "Waiting")
		{
			$state = "Em Fila";
			$c_state = 1;
		}
		
		if ($state == "Trying")
		{
			$state_pt_br = "Em Fila";
			$c_state = 1;
		}
		
		/**
			do call back soh pega variaveis do paramsA
			get the call center queue, agent and tiers list
		*/
		
		if($fp)
		{
			$switch_cmd = "uuid_getvar $session_uuid origination_src_name";
			$origination_src_name = trim(event_socket_request($fp, 'api '.$switch_cmd));
			if ($origination_src_name == "_undef_" || $origination_src_name == "-ERR") {$origination_src_name = "";}
			
			$switch_cmd = "uuid_getvar $session_uuid cc_base_score";
			$level_priority = trim(event_socket_request($fp, 'api '.$switch_cmd));
			$level_priority = $level_priority > 0 ? round($level_priority / 1000) : '0';
		}
		
		$queues_list = array();
		$queues_list["joined_length"] = $joined_length;
		$queues_list["caller_number"] = format_phone_number($caller_number);
		$queues_list["origination"] = $origination_src_name;
		$queues_list["level_priority"] = $level_priority;
		
		/**
			add queue list
		*/
		
		$call_center_queue["queues_list"][$x] = $queues_list;
		$x++;
	}
	
	fclose($fp);
	
	/**
		Fila de Atendimento
		End
	*/
	
	/**
		Last number
	*/
	
	$x=0;
	foreach ($memcache_last_numbers as $row)
	{
		$last_numbers_list = array();
		if($row->count >= 3)
		{
			$last_numbers_list["number"] = "<span class='badge badge-severe'>" . format_phone_number($row->caller_id_number) . "</span>";
			$last_numbers_list["count"] = "<span class='badge badge-severe'>" . $row->count . "</span>";
		}
		else
		{
			$last_numbers_list["number"] = "<span class='badge badge-normal'>" . format_phone_number($row->caller_id_number) . "</span>";
			$last_numbers_list["count"] = "<span class='badge badge-normal'>" . $row->count . "</span>";
		}
		$last_numbers_list["date"] = date('H:i:s', $row->start_epoch_new_call);
		
		$call_center_queue["lastnumber_list"][$x] = $last_numbers_list;
		$x++;
	}
	
	/**
		Last number End
	*/
	
	/**
		Atendidas X Abandonadas
	*/
	
	$call_center_queue["queue"]["label"]["answered"] = $text['label-answered'];
	$call_center_queue["queue"]["label"]["canceled"] = $text['label-canceled_count'];

	$call_center_queue["queue"]["total"]["answered"] = $tmp_inbound_answered_count;
	$call_center_queue["queue"]["total"]["canceled"] = $tmp_canceled_10 + $tmp_canceled_10_up;

	/**
		Resumo das discagens ativas
	*/
	
	$call_center_queue["queue"]["total"]["tmp_outbound_total_count"] = $tmp_outbound_total_count;
	$call_center_queue["queue"]["total"]["tmp_outbound_answered_count"] = $tmp_outbound_answered_count;
	$call_center_queue["queue"]["total"]["tmp_outbound_answered_tma"] = $tmp_outbound_answered_tma;

	/**
		Status Agents
	*/
	
	$call_center_queue["queue"]["total"]["c_queue_agents_agents"] = intval($c_queue_agents_agents);
	$call_center_queue["queue"]["total"]["c_queue_agents_on_break"] = intval($c_queue_agents_on_break);
	$call_center_queue["queue"]["total"]["c_queue_agents_available"] = intval($c_queue_agents_available);
	$call_center_queue["queue"]["total"]["c_queue_agents_in_queue_call"] = intval($c_queue_agents_in_queue_call);
	$call_center_queue["queue"]["total"]["c_queue_agents_logged_out"] = intval($c_queue_agents_logged_out);
	$call_center_queue["queue"]["total"]["c_queue_agents_attend"] = intval($c_queue_agents_attend);
	
	$call_center_queue["queue"]["label"]["c_queue_agents_agents"] = $text['label-agent'];
	$call_center_queue["queue"]["label"]["c_queue_agents_on_break"] = $text['label-in-pause'];
	$call_center_queue["queue"]["label"]["c_queue_agents_available"] = $text['label-available'];
	$call_center_queue["queue"]["label"]["c_queue_agents_in_queue_call"] = $text['label-in-call'];
	$call_center_queue["queue"]["label"]["c_queue_agents_logged_out"] = $text['label-logout'];
	$call_center_queue["queue"]["label"]["c_queue_agents_attend"] = $text['label-in-call'];
	
	$call_center_queue["queue"]["label"]["agents"] = $text['label-agent'];
	$call_center_queue["queue"]["label"]["answered_abandoned"] = $text['label-answered_abandoned'];
	$call_center_queue["queue"]["label"]["total"] = " " . $text['label-total'];
	$call_center_queue["queue"]["label"]["time_queue"] = $text['label-time-queue'];
	$call_center_queue["queue"]["label"]["service_level"] = $text['label-service-level'];
	$call_center_queue["queue"]["label"]["call_answer_level"] = $text['label-call-answer-level'];
	$call_center_queue["queue"]["label"]["goal"] = $text['label-goal'];
	
	/**
		Resumo do dia da Fila
		Resumo Geral.
	*/
	
	$call_center_queue["queue"]["inbound_count"] = $inbound_count;
	$call_center_queue["queue"]["tmp_inbound_answered_count"] = $tmp_inbound_answered_count;
	$call_center_queue["queue"]["tmp_canceled_10"] = $tmp_canceled_10;
	$call_center_queue["queue"]["tmp_canceled_10_up"] = $tmp_canceled_10_up;
	$call_center_queue["queue"]["tmp_droped_count"] = $tmp_droped_count;
	$call_center_queue["queue"]["tmp_issue_sound_count"] = $tmp_issue_sound_count;
	$call_center_queue["queue"]["tmp_issue_sound_count"] = $tmp_issue_sound_count;
	$call_center_queue["queue"]["tmp_inbound_tme"] = $tmp_inbound_tme;
	$call_center_queue["queue"]["tmp_canceled_tma"] = $tmp_canceled_tma;
	$call_center_queue["queue"]["tmp_canceled_10_up_tma"] = $tmp_canceled_10_up_tma;
	$call_center_queue["queue"]["tmp_max_time_queue_answered"] = $tmp_max_time_queue_answered;
	$call_center_queue["queue"]["tmp_max_time_queue_canceled"] = $tmp_max_time_queue_canceled;
	
	/**
		Nível de Serviço
		Verifique se o nível de atendimento do Call Center está dentro da meta estipulada
	*/
	
	$call_center_queue["perce"]["perc_ns_10"] = $tmp_perc_ns_10;
	$call_center_queue["goal"]["perc_ns_10_goal"] = '90';
	$call_center_queue["label"]["perc_ns_10"] = $text['label-served-within-10-sec-of-queue'];
	$call_center_queue["perce"]["perc_ns_20"] = $tmp_perc_ns_20;
	$call_center_queue["goal"]["perc_ns_20_goal"] = '80';
	$call_center_queue["label"]["perc_ns_20"] = $text['label-up-to-20-sec'];
	$call_center_queue["perce"]["perc_ns_30"] = $tmp_perc_ns_30;
	$call_center_queue["goal"]["perc_ns_30_goal"] = '60';
	$call_center_queue["label"]["perc_ns_30"] = $text['label-up-to-30-sec'];
	$call_center_queue["perce"]["perc_ns_60"] = $tmp_perc_ns_60;
	$call_center_queue["goal"]["perc_ns_60_goal"] = '30';
	$call_center_queue["label"]["perc_ns_60"] = $text['label-up-to-1-min'];
	
	$call_center_queue["time"]["queue"]["ns_5"] = $ns_5;
	$call_center_queue["time"]["queue"]["ns_10"] = $ns_10;
	$call_center_queue["time"]["queue"]["ns_15"] = $ns_15;
	$call_center_queue["time"]["queue"]["ns_20"] = $ns_20;
	$call_center_queue["time"]["queue"]["ns_25"] = $ns_25;
	$call_center_queue["time"]["queue"]["ns_30"] = $ns_30;
	$call_center_queue["time"]["queue"]["ns_35"] = $ns_35;
	$call_center_queue["time"]["queue"]["ns_40"] = $ns_40;
	$call_center_queue["time"]["queue"]["ns_45"] = $ns_45;
	$call_center_queue["time"]["queue"]["ns_50"] = $ns_50;
	$call_center_queue["time"]["queue"]["ns_55"] = $ns_55;
	$call_center_queue["time"]["queue"]["ns_60"] = $ns_60;
	$call_center_queue["time"]["queue"]["ns_60_up"] = $ns_60_up;
	
	$call_center_queue["label"]["queue"]["ns_5"]  	 = $text['label-from-1-to-5'];
	$call_center_queue["label"]["queue"]["ns_10"] 	 = $text['label-from-6-to-10'];
	$call_center_queue["label"]["queue"]["ns_15"] 	 = $text['label-from-11-to-15'];
	$call_center_queue["label"]["queue"]["ns_20"] 	 = $text['label-from-16-to-20'];
	$call_center_queue["label"]["queue"]["ns_25"] 	 = $text['label-from-21-to-25'];
	$call_center_queue["label"]["queue"]["ns_30"]	 = $text['label-from-26-to-30'];
	$call_center_queue["label"]["queue"]["ns_35"] 	 = $text['label-from-31-to-35'];
	$call_center_queue["label"]["queue"]["ns_40"]	 = $text['label-from-36-to-40'];
	$call_center_queue["label"]["queue"]["ns_45"] 	 = $text['label-from-41-to-45'];
	$call_center_queue["label"]["queue"]["ns_50"]	 = $text['label-from-46-to-50'];
	$call_center_queue["label"]["queue"]["ns_55"] 	 = $text['label-from-51-to-55'];
	$call_center_queue["label"]["queue"]["ns_60"] 	 = $text['label-from-56-to-60'];
	$call_center_queue["label"]["queue"]["ns_60_up"] = $text['label-over-1-min'];
	
	$call_center_queue["joined_menbers_count"] = $joined_menbers_count;
	$call_center_queue["queue_joined_length"] = $queue_joined_length;
	$call_center_queue["TMA"] = $tmp_total_tma;
	$call_center_queue["TME"] = $tme_queue_duration;
	
	die(json_encode($call_center_queue));
?>
