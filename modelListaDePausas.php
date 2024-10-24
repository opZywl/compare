<?php
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";
	require_once "app_languages.php";

	if (permission_exists('xml_cdr_view'))
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

	//variáveis de sessão
    $_language = $_SESSION['domain']['language']['code'];
    $_domain_name = $_SESSION['domain_name'];
    $_username = $_SESSION['username'];
    $_vendor = $_SESSION['switch']['vendor']['txt'];

	foreach ($text as $key => $value)
	{
		$text[$key] = $value[$_SESSION['domain']['language']['code']];
	}

	require_once "resources/header.php";
	
	/*** variaveis do db***/
	$db_name = "cdr_".$domain_uuid;
	$view_cdr_breaks = "`$db_name`.`view_cdr_breaks`";
	
	/**
		storing  request (ie, get/post) global array to a variable
	*/
	
	$requestData = $_REQUEST;
	
	$cc_agent = $_POST['CC_AGENT'];
	$cc_queue = $_POST['CC_QUEUE'];
	$start_stamp_begin = $_POST['DATA_INI'];
	$start_stamp_end = $_POST['DATA_END'];
	$domain_name = $_SESSION['domain_name'];


	$filter = $filter;
	$cc_queue = $cc_queue;
	$cc_agent = $cc_agent;
	$start_stamp_begin = $_POST['DATA_INI'];
	$start_stamp_end = $_POST['DATA_END'];
	$direction = $direction_tmp;	
	$caller_id_number = "";
	$destination_number = "";
	$extension = "";
	$finalization = "";
	$finalization_member = "";
	$finalization_agent = $finalization_agent;
	$uuid = "";
	$ring_duration = "";
	$ring_duration = "";
	$domain_name = $_SESSION['domain_name'];

	//$cc_state_in_breaks = '["on break","break_return"]';

	$cc_state_in = "'on break','break_return'";
	
	$state_only = true;

	//pega o ${recording_dir}
	
	$fp = event_socket_create($_SESSION['event_socket_ip_address'], $_SESSION['event_socket_port'], $_SESSION['event_socket_password']);
	if ($fp)
	{
		$switch_cmd = "eval \${recordings_dir}";
		$_recordings_dir = trim(event_socket_request($fp, 'api '.$switch_cmd));
	}
	fclose($fp);

	//verificar data_ini e data_end para pegar db_date
	$db_date_ini_tmp_epoch = strtotime($start_stamp_begin);
	$db_date_end_tmp_epoch = strtotime($start_stamp_end);
	$db_date_ini_tmp = date('Y-m-d', $db_date_ini_tmp_epoch);
	$db_date_end_tmp = date('Y-m-d', $db_date_end_tmp_epoch);
	$db_date = '';
	if($db_date_ini_tmp == $db_date_end_tmp)
	{
		$db_date = $db_date_ini_tmp;
	}

	require_once "../xml_cdr_call_center_agent_historico/model_agent_historico.php";
	
	
	$tmp_file.= $sql;
	
	/*write file*/
	$file_sql = fopen('xml_cdr_breaks.sql','w');
	fwrite($file_sql, $tmp_file);
	fclose($file_sql);
	
	
	$prep_statement = $db->prepare($sql);
	$prep_statement->execute();
	$result = $prep_statement->fetchAll(PDO::FETCH_NAMED);
	$recordsFiltered = count($result);
	
	if(isset($_GET["file"]) && $_GET["file"] == "eCSV")
	{
		header('Set-Cookie: fileDownload=true; path=/');
		header('Cache-Control: max-age=60, must-revalidate');
		header("Content-type: text/csv");
		$from = date('Y-m-d-H-i-s', $start_stamp_begin_epoch);
		$to = date('Y-m-d-H-i-s', $start_stamp_end_epoch);
		$file_name = "Lista_Pausas"."_".$text['from']."_".$from."_".$text['to']."_".$to."_".time();
		header('Content-Disposition: attachment; filename="' . $file_name . '.csv"');
		
		
		$t = array();
		
		array_push($t, $text['label-agent']);
		array_push($t, $text['label-pause_name']);
		array_push($t, $text['label-queue']);
		array_push($t, $text['label-date']);
		array_push($t, $text['label-time']);
		array_push($t, $text['label-break_timeout']);
		array_push($t, $text['label-duration']);
		array_push($t, $text['label-break_exceeded']);
		
		$fh = fopen('php://temp', 'rw');
		
		fputs($fh, $bom = chr(0xEF) . chr(0xBB) . chr(0xBF));
		
		fputcsv($fh, $t, ";");

		foreach ($result_agent_historico as $row)
		{
			$a = array();

			//agente
            $a[] = $row["agent_name"];
            
            //pausa nome
            if($row['break_name'] == 'break_return')
            {
                $a[] = 'PAUSA - MAX SEM RESPOSTA';
            }
            else
            {
                $a[] = $row["break_name"];
            }

            //FILA 
            $a[] = $row["queue_name"];

            //data
            $a[] = date('Y-m-d', $row["status_epoch"]);

            //hora
            $a[] = date('H:i:s', $row["status_epoch"]);


                //Tempo maximo permitido
            $a[] = sprintf('%02d:%02d:%02d', ($row["Max_Time_Allowed"]/ 3600),($row["Max_Time_Allowed"]/ 60 % 60), $row["Max_Time_Allowed"]% 60);
            
            if(intval($row["Break_Exceeded"]) > 0)
            {
                $a[] = gmdate("H:i:s", $row["break_duration"]);
                $a[] = sprintf('%02d:%02d:%02d', ($row["Break_Exceeded"]/ 3600),($row["Break_Exceeded"]/ 60 % 60), $row["Break_Exceeded"]% 60);
            }
            else
            {
                
                $a[] = gmdate("H:i:s", $row["break_duration"]);
                $a[] = "00:00:00";
            }

			fputcsv($fh, $a, ";");
		}
		
		rewind($fh);
		
		$csv = stream_get_contents($fh);
		
		fclose($fh);
		
		echo $csv;
		
		return;
	}
	
	$nested = array();
	foreach($result_agent_historico as $row)
	{
		$a = array();
		
		//agente
		$a[] = $row["agent_name"];
		
		//pausa nome
		if($row['break_name'] == 'break_return')
		{
			$a[] = 'PAUSA - MAX SEM RESPOSTA';
		}
		else
		{
			$a[] = $row["break_name"];
		}

		//FILA 
		$a[] = $row["queue_name"];

		//data
		$a[] = date('Y-m-d', $row["status_epoch"]);

		//hora
		$a[] = date('H:i:s', $row["status_epoch"]);
		
		//HORA FIM
		//$a[] = date('H:i:s', $break_duration);
		
		//Tempo maximo permitido
		$a[] = "<span class='badge badge-warning'>".sprintf('%02d:%02d:%02d', ($row["Max_Time_Allowed"]/ 3600),($row["Max_Time_Allowed"]/ 60 % 60), $row["Max_Time_Allowed"]% 60)."</span>";
		
		if(intval($row["Break_Exceeded"]) > 0)
		{
			$a[] = "<span class='badge badge-important'>".gmdate("H:i:s", $row["break_duration"])."</span>";
			$a[] = "<span class='badge badge-important'>".sprintf('%02d:%02d:%02d', ($row["Break_Exceeded"]/ 3600),($row["Break_Exceeded"]/ 60 % 60), $row["Break_Exceeded"]% 60)."</span>";
		}
		else
		{
			
			$a[] = "<span class='badge badge-success'>".gmdate("H:i:s", $row["break_duration"])."</span>";
			$a[] = "<span class='badge badge-success'>00:00:00</span>";
		}
		$nested[] = $a;
	}

	/**
		draw
		
		for every request/draw by clientside , 
		they send a number as a parameter, when 
		they recieve a response/data they first check the draw number, 
		so we are sending same number in draw.
	*/
	
	/**
		recordsTotal
		total number of records
	*/
	
	/**
		recordsFiltered
		total number of records after searching, 
		if there is no searching then totalFiltered = totalData
	*/
	
	/**
		data
		total data array
	*/
	
	$json_data = array
	(
		"draw" 				=> intval($requestData['draw']),
		"recordsTotal" 		=> intval($recordsFiltered),
		"recordsFiltered" 	=> intval($recordsTotal),
		"data" 				=> $nested
	);
	
	echo json_encode($json_data);

	function getQueuesCcManager($username)
	{
		global $domain_uuid;
		global $domain_name;
		global $db;
		$sql = "select CONCAT(\"'\",queue_name,\"'\") as queue_name from v_call_center_queues ";
		$sql .= "where domain_uuid = '$domain_uuid' ";
		$sql .= "and queue_cc_manager LIKE '%@".$username."@%' ";
		$sql .= "order by ";
		$sql .= "queue_extension asc ";
		$prep_statement = $db->prepare(check_sql($sql));
		$prep_statement->execute();
		$result_e = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		$queueIn = '';
		foreach($result_e as $queue)
		{
			$queueIn .= $queue['queue_name'] . ',';
		}
		$queueIn = substr($queueIn, 0, -1);
		
		return $queueIn;
	}
?>
