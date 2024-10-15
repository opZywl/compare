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
			Pega gw ext para saida do eavesdrop
		*/
		
		$switch_cmd = "global_getvar ext_gw-".$_SESSION['domains'][$domain_uuid]['domain_name'];
		$ext_gw = trim(event_socket_request($fp, 'api '.$switch_cmd));
		if ($ext_gw == "-ERR no reply") {unset($ext_gw);}
		
		/**
			lista as filas deste domínio			
		*/
		
		unset ($prep_statement, $sql);				
		$sql = "SELECT * FROM v_call_center_queues WHERE TRUE ";
		$sql.= "and domain_uuid = '".$domain_uuid."' ";
		if(if_group("cc_manager"))
		{
			$sql .= "and queue_cc_manager LIKE '%@".$_SESSION['username']."@%' ";
		}
		$sql.= "order by queue_name asc";
		$prep_statement = $db->prepare(check_sql($sql));
		$prep_statement->execute();
		$queue_list = $prep_statement->fetchAll(PDO::FETCH_NAMED);										
		unset ($prep_statement);

		// pegar os agentes fila a fila. Pois se pegar todos vai ser muita carga para servidor multi dominio
		// tb, pegando um a um, ja vem com o filtro "fila" que pode ser feito no início
		
		$result_members = array();
		$agent_result = array();
		$tier_arr = array();
		foreach ($queue_list as $_queue) 
		{
			$switch_cmd = 'callcenter_config queue list agents '.$_queue['queue_name']."@".$_SESSION['domains'][$domain_uuid]['domain_name'];
			// error_log($switch_cmd);
			$event_socket_str = trim(event_socket_request($fp, 'api '.$switch_cmd));
			$tmp_arr = str_to_named_array($event_socket_str, '|');
			
			foreach($tmp_arr as $key => $row) {
				$agent_result[$row['name']] = $row;
			}

			/**
				get the tier list
				tier list, tb para cada fila para naõ ter que gerar todos de uma vez
			*/
			
			$switch_cmd = 'callcenter_config queue list tiers '.$_queue['queue_name']."@".$_SESSION['domains'][$domain_uuid]['domain_name'];
			$event_socket_str = trim(event_socket_request($fp, 'api '.$switch_cmd));
			$tmp_arr = str_to_named_array($event_socket_str, '|');			
			
			foreach($tmp_arr as $key => $row) {
				array_push($tier_arr, $row);
			}
			
			/**
				get the queue member list
			*/
			
			//lista dos clientes que ligaram para a fila (em fila ou falando)
			$switch_cmd = 'callcenter_config queue list members '.$_queue['queue_name']."@".$_SESSION['domains'][$domain_uuid]['domain_name'];
			$event_socket_str = trim(event_socket_request($fp, 'api '.$switch_cmd));
			$tmp_arr = str_to_named_array($event_socket_str, '|');
			foreach($tmp_arr as $key => $row) {
				array_push($result_members, $row);
			}
			
		}
		
		/**
			prepare the result for array_multisort
		*/
		
		$x = 0;
		$tier_result = array();
		foreach ($tier_arr as $row) 
		{
			$tier_result[$x]['level'] = $row['level'];
			$tier_result[$x]['position'] = $row['position'];
			$tier_result[$x]['agent'] = $row['agent'];
			$tier_result[$x]['state'] = trim($row['state']);
			$tier_result[$x]['queue'] = $row['queue'];
			$x++;
		}			

		array_multisort($tier_result, SORT_ASC);
		
			
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
	
	/*para ser o mesmo valor utilizado por todos.*/
	$time_now = time();
	
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
			get queue
		*/
		
		$sql = " SELECT";
		$sql.= " SUM(answered_count) AS answered_count,";
		$sql.= " SUM(answered_duration) AS answered_duration,";
		$sql.= " SUM(outbound_answered_count) AS outbound_answered_count,";
		$sql.= " SUM(outbound_answered_duration) AS outbound_answered_duration,";
		$sql.= " SUM(outbound_count) AS outbound_count,";
		$sql.= " SUM(queue_duration) AS queue_duration,";
		$sql.= " SUM(canceled_count) AS canceled_count,";
		$sql.= " SUM(canceled_duration) AS canceled_duration,";
		$sql.= " SUM(canceled_10) AS canceled_10,";
		$sql.= " SUM(canceled_10_duration) AS canceled_10_duration,";
		$sql.= " SUM(canceled_10_up) AS canceled_10_up,";
		$sql.= " SUM(canceled_10_up_duration) AS canceled_10_up_duration,";
		$sql.= " SUM(issue_sound_count) AS issue_sound_count,";
		$sql.= " SUM(max_time_queue_answered) AS max_time_queue_answered,";
		$sql.= " SUM(max_time_queue_canceled) AS max_time_queue_canceled,";
		$sql.= " SUM(ns_5) AS ns_5,";
		$sql.= " SUM(ns_10) AS ns_10,";
		$sql.= " SUM(ns_15) AS ns_15,";
		$sql.= " SUM(ns_20) AS ns_20,";
		$sql.= " SUM(ns_25) AS ns_25,";
		$sql.= " SUM(ns_30) AS ns_30,";
		$sql.= " SUM(ns_35) AS ns_35,";
		$sql.= " SUM(ns_40) AS ns_40,";
		$sql.= " SUM(ns_45) AS ns_45,";
		$sql.= " SUM(ns_50) AS ns_50,";
		$sql.= " SUM(ns_55) AS ns_55,";
		$sql.= " SUM(ns_60) AS ns_60,";
		$sql.= " SUM(ns_60_up) AS ns_60_up";
		$sql.= " FROM v_xml_cdr_call_center_queue_consolidate c";
		$sql.= " WHERE c.start_stamp = '" . date("Y-m-d") . "'";
		$prep_statement = $db->prepare(check_sql($sql));
		$prep_statement->execute();
		$queue_consolidate = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		unset($prep_statement);
		
		/** 
			agent memcache data
		*/
		
		$sql = " SELECT";
		$sql.= " c.cc_agent_name,";
		$sql.= " SUM(outbound_count) AS outbound_count,";
		$sql.= " SUM(answered_count) AS answered_count,";
		$sql.= " SUM(not_answered_count) AS not_answered_count,";
		$sql.= " SUM(answered_duration) AS answered_duration,";
		$sql.= " first_login AS first_login,";
		$sql.= " SUM(answered_duration) AS answered_duration,";
		$sql.= " SUM(outbound_answered_duration) AS outbound_answered_duration,";
		$sql.= " SUM(outbound_answered_count) AS outbound_answered_count";
		$sql.= " FROM v_xml_cdr_call_center_agent_consolidate c";
		$sql.= " WHERE c.start_stamp > '" . date("Y-m-d") . " 00:00:00' AND c.start_stamp < '" . date("Y-m-d") . " 23:59:59'";
		$sql.= " AND c.cc_agent_name IS NOT NULL AND c.cc_agent_name <> ''";
		$sql.= " GROUP BY c.cc_agent_name";
		$prep_statement = $db->prepare(check_sql($sql));
		$prep_statement->execute();
		$agent_consolidate = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		unset($prep_statement);
		
		// error_log("sql = $sql/n");
		foreach ($agent_logged as $name => $row)
		{
			foreach ($agent_consolidate as $row2)
			{
				if($row['name'].'@'. $_SESSION['domains'][$domain_uuid]['domain_name'] == $row2["cc_agent_name"]."@".$_SESSION['domain_name'])
				{
					$agent_logged[$row2["cc_agent_name"]."@".$_SESSION['domain_name']]["outbound_count"] = $row2["outbound_count"];
					$agent_logged[$row2["cc_agent_name"]."@".$_SESSION['domain_name']]["answered_count"] = $row2["answered_count"];
					$agent_logged[$row2["cc_agent_name"]."@".$_SESSION['domain_name']]["not_answered_count"] = $row2["not_answered_count"];
					$agent_logged[$row2["cc_agent_name"]."@".$_SESSION['domain_name']]["answered_duration"] = $row2["answered_duration"];
					$agent_logged[$row2["cc_agent_name"]."@".$_SESSION['domain_name']]["first_login"] = $row2["first_login"];
					$agent_logged[$row2["cc_agent_name"]."@".$_SESSION['domain_name']]["outbound_answered_duration"] = $row2["outbound_answered_duration"];
					$agent_logged[$row2["cc_agent_name"]."@".$_SESSION['domain_name']]["outbound_answered_count"] = $row2["outbound_answered_count"];
				}
			}
		}
		
		/** 
			close connection
		*/
		
		$memcache->close();
		
		/** 
			geral tma entrada de toda operacao
		*/
		
		$tmp_total_count    = intval($queue_consolidate[0]["answered_count"]) + intval($queue_consolidate[0]["outbound_answered_count"]);
		$tmp_total_duration = intval($queue_consolidate[0]["answered_duration"]) + intval($queue_consolidate[0]["outbound_answered_duration"]);					
		$tmp_total_tma = $tmp_total_duration / $tmp_total_count;
		$tmp_total_tma = gmdate("H:i:s", intval($tmp_total_tma));

		/** 
			outbound
		*/
		
		$tmp_outbound_total_count	    = check_str0($queue_consolidate[0]["outbound_count"]);
		$tmp_outbound_answered_count    = check_str0($queue_consolidate[0]["outbound_answered_count"]);
		$tmp_outbound_answered_duration = check_str0($queue_consolidate[0]["outbound_answered_duration"]);
		$tmp_outbound_answered_tma      = check_str0($tmp_outbound_answered_duration / $tmp_outbound_answered_count);
		$tmp_outbound_answered_tma      = gmdate("H:i:s", $tmp_outbound_answered_tma);				
		
		/** 
			inbound
		*/
		
		$tmp_inbound_answered_count    = check_str0($queue_consolidate[0]["answered_count"]);
		$tmp_inbound_answered_duration = check_str0($queue_consolidate[0]["answered_duration"]);
		$tmp_inbound_answered_tma      = check_str0($tmp_inbound_answered_duration / $tmp_inbound_answered_count);
		$tmp_inbound_answered_tma      = gmdate("H:i:s", $tmp_inbound_answered_tma);
		
		$tme_queue_duration = check_str0($queue_consolidate[0]["queue_duration"]); //tempo fila das atendidas
	
		/** 
			canceled
		*/
		
		$tmp_canceled_count    = check_str0($queue_consolidate[0]["canceled_count"]);
		$tmp_canceled_duration = check_str0($queue_consolidate[0]["canceled_duration"]);
		$tmp_canceled_tma      = check_str0($tmp_canceled_duration / $tmp_canceled_count);
		$tmp_canceled_tma      = gmdate("H:i:s", $tmp_canceled_tma);
		
		$tmp_canceled_10          = check_str0($queue_consolidate[0]["canceled_10"]);
		$tmp_canceled_10_duration = check_str0($queue_consolidate[0]["canceled_10_duration"]);
		$tmp_canceled_10_tma      = check_str0($tmp_canceled_10_duration / $tmp_canceled_10_duration);
		$tmp_canceled_10_tma      = gmdate("H:i:s", $tmp_canceled_10_tma);
		
		$tmp_canceled_10_up          = check_str0($queue_consolidate[0]["canceled_10_up"]);
		$tmp_canceled_10_up_duration = check_str0($queue_consolidate[0]["canceled_10_up_duration"]);
		$tmp_canceled_10_up_tma      = check_str0($tmp_canceled_10_duration / $tmp_canceled_10_up);
		$tmp_canceled_10_up_tma      = gmdate("H:i:s", $tmp_canceled_10_up_tma);

		/** 
			Droped count (issue sound)
		*/
		
		$tmp_droped_count = check_str0($queue_consolidate[0]["issue_sound_count"]);

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
		
		$tmp_max_time_queue_answered = check_str0($queue_consolidate[0]["max_time_queue_answered"]);
		$tmp_max_time_queue_answered = gmdate("H:i:s", $tmp_max_time_queue_answered);
		
		/** 
			Max in a queue and not answered
		*/
	
		$tmp_max_time_queue_canceled = check_str0($queue_consolidate[0]["max_time_queue_canceled"]);
		$tmp_max_time_queue_canceled = gmdate("H:i:s", $tmp_max_time_queue_canceled);
		
		$tmp_tma = check_str0($queue_consolidate[0]["max_time_queue_canceled"]);
		$tmp_tma = gmdate("H:i:s", $tmp_tma);
		
		$ns_5  = check_str0($queue_consolidate[0]["ns_5"]);
		$ns_10 = check_str0($queue_consolidate[0]["ns_10"]);
		$ns_15 = check_str0($queue_consolidate[0]["ns_15"]);
		$ns_20 = check_str0($queue_consolidate[0]["ns_20"]);
		$ns_25 = check_str0($queue_consolidate[0]["ns_25"]);
		$ns_30 = check_str0($queue_consolidate[0]["ns_30"]);
		$ns_35 = check_str0($queue_consolidate[0]["ns_35"]);
		$ns_40 = check_str0($queue_consolidate[0]["ns_40"]);
		$ns_45 = check_str0($queue_consolidate[0]["ns_45"]);
		$ns_50 = check_str0($queue_consolidate[0]["ns_50"]);
		$ns_55 = check_str0($queue_consolidate[0]["ns_55"]);
		$ns_60 = check_str0($queue_consolidate[0]["ns_60"]);
		$ns_60_up = check_str0($queue_consolidate[0]["ns_60_up"]);
		
		$tmp_value_ns = $ns_5 + $ns_10;
		$tmp_perc_ns_10 = sprintf("%.0d%%", ($tmp_value_ns / $tmp_inbound_answered_count * 100));
		
		$tmp_value_ns = $tmp_value_ns + $ns_15 + $ns_20;
		$tmp_perc_ns_20 = sprintf("%.0d%%", ($tmp_value_ns / $tmp_inbound_answered_count) * 100);					
		
		$tmp_value_ns = $tmp_value_ns + $ns_25 + $ns_30;
		$tmp_perc_ns_30 = sprintf("%.0d%%", ($tmp_value_ns / $tmp_inbound_answered_count) * 100);
		
		$tmp_value_ns_60 = $tmp_value_ns + $ns_35 + $ns_40 + $ns_45 + $ns_50 + $ns_55 + $ns_60;
		$tmp_perc_ns_60 = sprintf("%.0d%%", ($tmp_value_ns_60 / $tmp_inbound_answered_count) * 100);

		$tmp_value_ns_60_up = $ns_60_up;
		$tmp_perc_ns_60_up = sprintf("%.0d%%", ($tmp_value_ns_60_up / $tmp_inbound_answered_count) * 100);
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

		if ($state == "Waiting" && $status != "On Break Max No Answer")  
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

		if ($status == "On Break Max No Answer")
		{
			$state = $text['label-state-on_break-max-no-answer'];
			$c_state = 7;
		}
							
		if(strtolower($status) == 'on break' || strtolower($status) == 'on break max no answer')
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
		// error_log("caller_number [tom]");
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
			$joined_seconds = $time_now - $joined_epoch;
			$joined_seconds_aux = $joined_seconds_aux + $joined_seconds;
			$joined_menbers_count++;
			$state = "Em Fila";
			$c_state = 1;
		}
		
		if ($state == "Trying") 
		{
			$joined_seconds = $time_now - $joined_epoch;
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
		$result = [];
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

	$row_style_dashboard_["0"] = "row_style_dashboard_0"; //"#e5e9f0"; //cinza //background
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

		/* lista das filas que os agentes estão logados */
		$tmp_queue_list = [];
		foreach($tier_result as $tier)
		{
			$tmp_tier_agent = explode('@', $tier['agent']);
			if($tmp_tier_agent[0] == $agent_row['name'])
			{
				$tmp_tier_queue = explode('@', $tier['queue']);
				$tmp_queue_list[] = $tmp_tier_queue[0];
			}
		}


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
		
		if (strtolower($agent_row['state']) == "in a queue call")
		{
			foreach ($result_members as $members_row)
			{
				if ($members_row["serving_agent"] == $agent_row['name']."@".$_SESSION['domain_name'])
				{
					$phone = $members_row["cid_number"];
					$queue = explode("@", $members_row["queue"]);
					$queue = $queue[0];
					break;
				}
			}
		}	
		
		$a_uuid = $agent_row['uuid'];
		$eavesdrop_uuid = $a_uuid;
		$contact = $agent_row['contact'];
		
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
		$no_answer_count = check_str0($agent_row["rejected_count"]);
		$outbound_count = check_str0($agent_row["outbound_count"]);
		$answered_count = check_str0($agent_row["answered_count"]);
		$not_answered_count = check_str0($agent_row["not_answered_count"]);
		$answered_duration = check_str0($agent_row["answered_duration"]);
		$first_login = check_str0($agent_row["first_login"]);
		$calls_answered = $agent_row['calls_answered'];
		$talk_time = $agent_row['talk_time'];
		$ready_time = $agent_row['ready_time'];
		$delay_time = $ready_time - $last_status_change;
		$delay_status = null;
		
		if ($delay_time > 0 && $ready_time > $time_now)
		{
			if ($delay_time == $wrap_up_time) {$delay_status = "Em Preparação";}
			if ($delay_time == $reject_delay_time) {$delay_status = "Chamada Rejeitada";}
			if ($delay_time == $busy_delay_time) {$delay_status = "Ramal Ocupado";}
			if ($delay_time == $no_answer_delay_time) {$delay_status = "Não Atendida";}
			if ($delay_time == 5) {$delay_status = "Ramal Ocupado";}
		}
		
		$tmp_asw_duration = check_str0($agent_row["answered_duration"][0]) + check_str0($agent_row["outbound_answered_duration"]);
		$tmp_asw_count = check_str0($agent_row["answered_count"]) + check_str0($agent_row["outbound_answered_count"]);
		$tma_agent =  check_str0($tmp_asw_duration) / check_str0($tmp_asw_count);
		$tma_agent = gmdate("H:i:s", intval($tma_agent));
		
		$last_offered_call_seconds = $last_bridge_start;
		$last_offered_call_length_hour = floor($last_offered_call_seconds/3600);
		$last_offered_call_length_min = floor($last_offered_call_seconds/60 - ($last_offered_call_length_hour * 60));
		$last_offered_call_length_sec = $last_offered_call_seconds - (($last_offered_call_length_hour * 3600) + ($last_offered_call_length_min * 60));
		$last_offered_call_length_min = sprintf("%02d", $last_offered_call_length_min);
		$last_offered_call_length_sec = sprintf("%02d", $last_offered_call_length_sec);
		$last_bridge_start_length = $last_offered_call_length_hour.':'.$last_offered_call_length_min.':'.$last_offered_call_length_sec;
		$last_bridge_start_length = date('H:i:s', $last_bridge_start);
		
		$last_offered_call_seconds = $time_now - $last_bridge_end;
		$last_offered_call_length_hour = floor($last_offered_call_seconds/3600);
		$last_offered_call_length_min = floor($last_offered_call_seconds/60 - ($last_offered_call_length_hour * 60));
		$last_offered_call_length_sec = $last_offered_call_seconds - (($last_offered_call_length_hour * 3600) + ($last_offered_call_length_min * 60));
		$last_offered_call_length_min = sprintf("%02d", $last_offered_call_length_min);
		$last_offered_call_length_sec = sprintf("%02d", $last_offered_call_length_sec);
		$last_bridge_end_length = $last_offered_call_length_hour.':'.$last_offered_call_length_min.':'.$last_offered_call_length_sec;
		$last_bridge_end_length = date('H:i:s', $last_bridge_end);
		
		$last_offered_call_seconds = $time_now - $last_offered_call;
		$last_offered_call_length_hour = floor($last_offered_call_seconds/3600);
		$last_offered_call_length_min = floor($last_offered_call_seconds/60 - ($last_offered_call_length_hour * 60));
		$last_offered_call_length_sec = $last_offered_call_seconds - (($last_offered_call_length_hour * 3600) + ($last_offered_call_length_min * 60));
		$last_offered_call_length_min = sprintf("%02d", $last_offered_call_length_min);
		$last_offered_call_length_sec = sprintf("%02d", $last_offered_call_length_sec);
		$last_offered_call_length = $last_offered_call_length_hour.':'.$last_offered_call_length_min.':'.$last_offered_call_length_sec;
		$last_offered_call_length = date('H:i:s', $last_offered_call);
		
		$last_status_change_seconds = $time_now - $last_status_change;
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
			$eavesdrop_uuid = $a_uuid;				 
			
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
			$eavesdrop_uuid = $a_uuid;				 
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
			
			if (($last_bridge_end + $wrap_up_time) > $time_now)
			{
				$c_state = 1;
				$wrap_time = ($last_bridge_end + $wrap_up_time) - $time_now;
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

		if ($status == "On Break Max No Answer")
		{
			$state = $text['label-state-on_break-max-no-answer'];
			$c_state = 7;
		}
		
		if ($_SESSION['domains'][$domain_uuid]['agent'][$name]['last_status'] == null)
		{
			if ($state == "Livre")
			{
				if ($last_bridge_end < $last_status_change)
				{
					$_SESSION['domains'][$domain_uuid]['agent'][$name]['last_status_change'] = $last_status_change;
				}
				else
				{
					$_SESSION['domains'][$domain_uuid]['agent'][$name]['last_status_change'] = $last_bridge_end;
				}
				
				$_SESSION['domains'][$domain_uuid]['agent'][$name]['last_status'] = $state;
			}				
			if (strtolower($status) == "on break")
			{						
				$_SESSION['domains'][$domain_uuid]['agent'][$name]['last_status_change'] = $last_status_change;
				$_SESSION['domains'][$domain_uuid]['agent'][$name]['last_status'] = $state;
			}
		}
		else
		{
			if ($last_status_change > $_SESSION['domains'][$domain_uuid]['agent'][$name]['last_status_change'])
			{						
				$_SESSION['domains'][$domain_uuid]['agent'][$name]['last_status_change'] = $last_status_change;
				$_SESSION['domains'][$domain_uuid]['agent'][$name]['last_status'] = $state;
			}
		}
		
		if ($_SESSION['domains'][$domain_uuid]['agent'][$name]['last_status'] != $state)
		{
			$_SESSION['domains'][$domain_uuid]['agent'][$name]['last_status'] = $state;
			$_SESSION['domains'][$domain_uuid]['agent'][$name]['last_status_change'] = $time_now;
		}
		
		$last_status_change_seconds = $time_now - $last_status_change;
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
			Queue Name
		*/
		$agents["queue"] = '';
		if ($c_state == 2 || $c_state == 4) 
		{
			if(strlen($queue) > 0)
			{
				$queue = explode("@", $queue);
				$agents["queue"] = $queue[0];
			}
		}
		
		/**
			Agent Name
		*/
		
		$agents["name"] = $name;
		$agents["queues_loggeds"] = implode(',',$tmp_queue_list);
		
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
			$agents["state"] = $delay_status." ".($ready_time - $time_now)." Seg.";
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
				
				$lsc = $time_now -  $last_status_change;
				$lsc = gmdate("H:i:s", $lsc);
				$agents["state"] = $break_name . " (" . $break_length . ")" . "<br>" . $lsc;
			}
		}
		elseif(strlen($phone) > 0)
		{
			$phone_formated = $phone;
			$phone_formated = format_phone_number($phone);
			$agents["class"] = $row_style_dashboard_[2];
			$agents["state"] = $phone_formated;
		}elseif($c_state == "1"){ //pos atd
			$agents["class"] = $row_style_dashboard_[$c_state];
			$agents["state"] = $state."<br>";
		}elseif($c_state == "2"){ //em chamada em outra fila
			$agents["class"] = $row_style_dashboard_[$c_state];
			$agents["state"] = $state;
		}elseif($c_state == "4"){ //ringando
			$agents["class"] = $row_style_dashboard_[$c_state];
			$agents["state"] = $state."<br>".ltrim(substr($last_status_change_length, 6, 2), '0')." seg.";
		} else { //livre
			$agents["class"] = $row_style_dashboard_[$c_state];
			$lscs_h = gmdate("H:i:s", $last_status_change_seconds);
			$lbe_h = gmdate("H:i:s", $time_now - $last_bridge_end - $wrap_up_time);
			$agents["state"] = "$state<br>$lscs_h";
			
			/*
				last_bridge_end funciona para quando a chamada encerra e o agente fica livre
				? Qdo se loga? 
				? Qdo volta de pausa? 
			*/
		}
		
		/**
			State Time
		*/
		
		//$agents["last_status_change_length"] = $last_status_change_length;		
		if ($c_state == 2 || strtolower($status) == "on outbound") {
			$lbe = $time_now -  $last_bridge_start;
			$lbe = gmdate("H:i:s", $lbe);
			$agents["last_status_change_length"] = $lbe;
		} else {
			$lbe = "--:--:--";
			$agents["last_status_change_length"] = $lbe;
		}
		
		if (strtolower($status) == 'on break')
		{
			$lsc = "--:--:--";
			$agents["last_status_change_length"] = $lsc;
			$lsc = $time_now -  $last_status_change;
		}
		
		/**
			Tempo Logado
		*/
		
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
			if (permission_exists('call_center_active_options') || if_group("admin") || if_group("superadmin") || if_group("supervisor") || if_group("Supervisor"))
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
			if (permission_exists('call_center_active_options') || if_group("admin") || if_group("superadmin") || if_group("cc_manager") || if_group("Supervisor"))
			{
				$q_caller_number = urlencode($phone);						
				if (!$ext_gw) {
					$orig_command="{origination_caller_id_name=eavesdrop,origination_caller_id_number=".$q_caller_number."}user/".$_SESSION['user']['extension'][0]['user']."@".$_SESSION['domain_name']." %26eavesdrop(".$a_uuid.")";
				} else {
					$ext = $_SESSION['user']['extension'][0]['user'];
					$ext = ltrim($ext,"X");
					$ext = ltrim($ext,"x");
					$orig_command="{origination_caller_id_name=eavesdrop,origination_caller_id_number=".$q_caller_number."}sofia/gateway/$ext_gw/".$ext." %26eavesdrop(".$eavesdrop_uuid.")";
				}
				$tmp = $text['label-eavesdrop'];
				if (strtolower($status) == 'on break') {
					$tmp = "";
				}
				$commands_agents .= "<a href='javascript:void(0);' onclick=\"confirm_response = confirm('".$text['message-confirm']."');if (confirm_response){send_cmd('call_center_exec.php?cmd=originate+".$orig_command.")');}\"><i class=\"fas fa-headphones\"></i></a>";
			}
		}
		
		$agents["commands"] = $commands_agents;

		$managerAgent = '';
		if (permission_exists('call_center_active_options') || if_group("admin") || if_group("superadmin") || if_group("supervisor") || if_group("Supervisor"))
		{
			$managerAgent = "<a class='uStatus' data-agent='".$agents["name"]."'><i class=\"fas fa-user\"></i></a>";
		}
		
		$agents['manager'] = $managerAgent;
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
		$queue = explode("@", $queue);
		$queue = $queue[0];
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
		
		$joined_seconds = $time_now - $joined_epoch;
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
		$queues_list["queue_name"] = $queue;
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
	$call_center_queue["queue"]["tmp_value_ns"] = $tmp_value_ns_60;
	$call_center_queue["queue"]["tmp_value_ns_up"] = $tmp_value_ns_60_up;
	$call_center_queue["queue"]["tmp_inbound_answered_tma"] = $tmp_inbound_answered_tma;
	
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
