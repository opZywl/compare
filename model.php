<?php
// old
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

	foreach ($text as $key => $value)
	{
		$text[$key] = $value[$_SESSION['domain']['language']['code']];
	}

	require_once "resources/header.php";
	
	$db_name = "cdr_".$domain_uuid;

	/**
		storing  request (ie, get/post) global array to a variable
	*/
	
	$requestData = $_REQUEST;
	
	$survey = $_POST['SURVEY'];
	$cc_queue = $_POST['CC_QUEUE'];
	$cc_agent = $_POST['CC_AGENT'];
	$protocol = $_POST['PROTOCOL'];
	$question = $_POST['QUESTION'];
	$start_stamp_begin = $_POST['DATA_INI'];
	$start_stamp_end = $_POST['DATA_END'];
	$survey_digit = $_POST['SURVEY_DIGIT'];
	
	if (strlen($start_stamp_begin) == 0 )
	{
		$start_stamp_begin = date('Y-m-d');
		$tmp_start_stamp_begin = date('Y-m-d 00:00:00');		
		$start_stamp_begin_epoch = strtotime($tmp_start_stamp_begin);
	}
	else
	{
		$start_stamp_begin_epoch = strtotime($start_stamp_begin);
	}
	
	if (strlen($start_stamp_end) == 0 )
	{
		$start_stamp_end = date('Y-m-d');
		$tmp_start_stamp_end = date('Y-m-d 23:59:59');
		$start_stamp_end_epoch = strtotime($tmp_start_stamp_end);			
	}
	else
	{
		$start_stamp_end_epoch = strtotime($start_stamp_end);
	}
		
	$sql_where_ands[] = "r_a.start_epoch BETWEEN ".$start_stamp_begin_epoch." AND ".$start_stamp_end_epoch;

	if (strlen($cc_agent) > 0)
	{
		$sql_where_ands[] = "cc.cc_agent = '".$cc_agent."@".$_SESSION["domain_name"]."'";
	}
	
	if (strlen($cc_queue) > 0)
	{
		$sql_where_ands[] = "cc.cc_queue = '".$cc_queue."@".$_SESSION["domain_name"]."'";
	}

	if (strlen($survey) > 0)
	{
		$sql_where_ands[] = "su.survey_uuid = '$survey'";
	}

	if (strlen($protocol) > 0)
	{
		$sql_where_ands[] = "v_a.protocol = '$protocol'";
	}
	
	if (strlen($question) > 0)
	{
		$sql_where_ands[] = "su.param = '$question'";
	}
	
	$sql_where_digit = ' WHERE TRUE ';
	if(strlen($survey_digit) > 0)
	{
		if($survey_digit == 'true')
		{
			$sql_where_digit .= " and digits <> '-1' ";
			$sql_where_ands[] .= " su.value <> '-1' ";
		}
		elseif($survey_digit == 'false')
		{
			$sql_where_digit .= " and digits = '-1' ";
			$sql_where_ands[] .= " su.value = '-1' ";
		}
		else
		{
			$sql_where_digit .= " and digits = '$survey_digit' ";
			$sql_where_ands[] .= " su.value = '$survey_digit' ";
		}
	}

	if (sizeof($sql_where_ands) > 0)
	{
		$sql_where = " and ".implode(" and ", $sql_where_ands);
	}

	$sql_where = "where true " . $sql_where;


	$sql = "SELECT SUM(recordsTotal) AS recordsTotal FROM (
						SELECT
						COUNT(1) AS recordsTotal
						FROM `cdr_$domain_uuid`.`cdr_survey` su
						INNER JOIN `cdr_$domain_uuid`.`cdr_refer_uuid` r_b ON r_b.uuid = su.uuid
						INNER JOIN `cdr_$domain_uuid`.`cdr_variables`  v_b ON v_b.uuid = su.uuid
						LEFT JOIN `cdr_$domain_uuid`.`cdr_refer_uuid` r_a ON r_a.uuid = r_b.bridge_uuid
						LEFT JOIN `cdr_$domain_uuid`.`cdr_variables`  v_a ON v_a.uuid = r_b.bridge_uuid
						LEFT JOIN  `cdr_$domain_uuid`.`cdr_cc` 		  cc ON cc.uuid = r_a.uuid
						$sql_where
						AND r_b.bridge_uuid IS NOT NULL
						AND LENGTH(v_a.caller_id_number) < 8
						UNION
						SELECT 
						COUNT(1) AS recordsTotal
						FROM `cdr_$domain_uuid`.`cdr_survey` su
						INNER JOIN `cdr_$domain_uuid`.`cdr_refer_uuid` r_a ON r_a.uuid = su.uuid
						INNER JOIN `cdr_$domain_uuid`.`cdr_variables`  v_a ON v_a.uuid = su.uuid
						LEFT JOIN `cdr_$domain_uuid`.`cdr_refer_uuid` r_b ON r_b.bridge_uuid = r_a.uuid
						LEFT JOIN `cdr_$domain_uuid`.`cdr_variables`  v_b ON r_a.bridge_uuid = v_b.uuid
						LEFT JOIN  `cdr_$domain_uuid`.`cdr_cc` cc ON cc.uuid = r_a.uuid
						LEFT JOIN  `cdr_$domain_uuid`.`cdr_cc` cc_b ON cc_b.uuid = r_b.uuid
						$sql_where
						AND (`cc`.`cc_side` = 'member')
						AND (cc_b.cc_queue_inc =  cc.cc_queue_inc)
				) a";
		
	$prep_statement = $db->prepare(check_sql($sql));
	$prep_statement->execute();
	$row = $prep_statement->fetch(PDO::FETCH_ASSOC);
	
	if ($row['recordsTotal'] > 0)
	{
		$recordsTotal = $row['recordsTotal'];
	}
	else
	{
		$recordsTotal = '0';
	}

	$sql = "
SELECT
    *
FROM (
    SELECT
        (@cnt := @cnt + 1) AS rowNumber
        , b.*
    FROM (
        SELECT
            a.*
        FROM (
			/*SAÍDA*/
			SELECT
				'1' AS 'TAG'
				, su. survey_uuid uuid_su
				, r_a.uuid uuid_a
				, r_b.uuid uuid_b
				, v_b.destination_number
				, r_a.start_epoch start_epoch_a
                , r_b.start_epoch start_epoch_b
				, 'Saida' AS Direcao 
				, su.survey_name survey_name
				, v_a.caller_id_number Ramal
				, if(v_a.callee_id_number IS NULL, v_b.callee_id_number, v_a.callee_id_number) Numero
				, FROM_UNIXTIME(r_a.start_epoch, '%Y-%m-%d') 'Data'
				, FROM_UNIXTIME(r_a.start_epoch, '%H:%i:%s') 'Hora'
				, su.param survey_var_name
				, if (su.value IS NULL, '-1', su.value) digits
				, SUBSTRING_INDEX(cc.cc_agent,'@',1) agent_name
				, SUBSTRING_INDEX(cc.cc_queue,'@',1) queue_name
				, v_a.record_session
				, v_a.cc_record_filename
				, v_a.protocol Protocolo
				
				FROM `cdr_$domain_uuid`.`cdr_survey` su
				INNER JOIN `cdr_$domain_uuid`.`cdr_refer_uuid` r_b ON r_b.uuid = su.uuid
				INNER JOIN `cdr_$domain_uuid`.`cdr_variables`  v_b ON v_b.uuid = su.uuid
				LEFT JOIN `cdr_$domain_uuid`.`cdr_refer_uuid` r_a ON r_a.uuid = r_b.bridge_uuid
				LEFT JOIN `cdr_$domain_uuid`.`cdr_variables`  v_a ON v_a.uuid = r_b.bridge_uuid
				LEFT JOIN  `cdr_$domain_uuid`.`cdr_cc` 		  cc ON cc.uuid = r_a.uuid
				$sql_where
				AND r_b.bridge_uuid IS NOT NULL
				AND LENGTH(v_a.caller_id_number) < 8
				
				UNION
				
				/*ENTRADA*/
				SELECT 
				'2' AS 'TAG'
				, su. survey_uuid uuid_su
				, r_a.uuid uuid_a
				, r_b.uuid uuid_b
				, v_a.destination_number
				, r_a.start_epoch start_epoch_a
                , r_b.start_epoch start_epoch_b
				, 'Entrada' AS Direcao 
				, su.survey_name Pesquisa
				, if(v_a.callee_id_number IS NULL, v_b.callee_id_number, v_a.callee_id_number) Ramal
				, v_a.caller_id_number Numero
				, FROM_UNIXTIME(r_a.start_epoch, '%Y-%m-%d') 'Data'
				, FROM_UNIXTIME(r_a.start_epoch, '%H:%i:%s') 'Hora'
				, su.param Pergunta
				, if (su.value IS NULL, '-1', su.value) Digito
				, SUBSTRING_INDEX(cc.cc_agent,'@',1) Agente
				, SUBSTRING_INDEX(cc.cc_queue,'@',1) Fila
				, v_b.record_session
				, v_a.cc_record_filename
				, v_a.protocol Protocolo
				
				FROM `cdr_$domain_uuid`.`cdr_survey` su
				INNER JOIN `cdr_$domain_uuid`.`cdr_refer_uuid` r_a ON r_a.uuid = su.uuid
				INNER JOIN `cdr_$domain_uuid`.`cdr_variables`  v_a ON v_a.uuid = su.uuid
				LEFT JOIN `cdr_$domain_uuid`.`cdr_refer_uuid` r_b ON r_b.bridge_uuid = r_a.uuid
				LEFT JOIN `cdr_$domain_uuid`.`cdr_variables`  v_b ON r_a.bridge_uuid = v_b.uuid
				LEFT JOIN  `cdr_$domain_uuid`.`cdr_cc` cc ON cc.uuid = r_a.uuid
				LEFT JOIN  `cdr_$domain_uuid`.`cdr_cc` cc_b ON cc_b.uuid = r_b.uuid
				$sql_where
				AND (`cc`.`cc_side` = 'member')
				AND (cc_b.cc_queue_inc =  cc.cc_queue_inc)
		)a $sql_where_digit ";
		$sql.= "
		order by start_epoch_a DESC, start_epoch_b DESC
        )b order by rowNumber desc
	)c group by uuid_a, survey_name, survey_var_name, digits
	order by rowNumber desc
		;";
	
	if(isset($requestData['start']))
	{
		$sql .= "
	LIMIT " . $requestData['start'] . " ," . $requestData['length'] . "   ;";
	}

	$file_sql = fopen('survey.sql','w');
	fwrite($file_sql, $sql);
	fclose($file_sql);

	$prep_statement = $db->prepare(check_sql($sql));
	$prep_statement->execute();
	$result = $prep_statement->fetchAll(PDO::FETCH_ASSOC);
	
	$sql  = " select ";
	$sql .= " survey_item_label";
	$sql .= " ,survey_item_digits_format";
	$sql .= " from v_survey_items";
	$sql .= " where domain_uuid = '".$_SESSION['domain_uuid']."'";
	$sql .= " and survey_uuid = '$survey'";
	$sql .= " order by survey_item_label";
	$prep_statement = $db->prepare(check_sql($sql));
	$prep_statement -> execute();
	$result_survey = $prep_statement->fetchAll(PDO::FETCH_NAMED);	
	
	$survey_resposta = array();
	foreach($result_survey as $row)
	{
		$digitsFormat =  json_decode($row["survey_item_digits_format"]);
		
		$i=0;
		foreach($digitsFormat as $digitsName)
		{
			$survey_resposta[$row["survey_item_label"]][$i] = $digitsName;
			$i++;
		}
	}
	
	unset ($prep_statement, $sql);

	if(isset($_GET["file"]) && $_GET["file"] == "eCSV")
	{
		header('Set-Cookie: fileDownload=true; path=/');
		header('Cache-Control: max-age=60, must-revalidate');
		header("Content-type: text/csv");
		header('Content-Disposition: attachment; filename="' . uuid() .'.csv"');
		
		$t = array();
		array_push($t, $text['label-direction']);
		array_push($t, $text['label-number']);
		array_push($t, $text['label-protocol']);
		array_push($t, $text['label-date']);
		array_push($t, $text['label-queue']);
		array_push($t, $text['label-agent']);
		array_push($t, $text['label-survey']);
		array_push($t, $text['label-question']);
		array_push($t, $text['label-answer']);
		array_push($t, $text['label-digit']);
		
		$fh = fopen('php://temp', 'rw');
		
		fputs($fh, $bom = chr(0xEF) . chr(0xBB) . chr(0xBF));
		
		fputcsv($fh, $t, ";");

		foreach ($result as $row)
		{
			$a = array();
			$a[] = $row["Direcao"];
			$a[] = $row["Numero"];
			$a[] = $row["Protocolo"];
			$a[] = date('Y-m-d H:i:s', $row["start_epoch_a"]);
			$a[] = $row["queue_name"];
			$a[] = $row["agent_name"];
			$a[] = $row["survey_name"];
			$a[] = $row["survey_var_name"];
			
			if($row["digits"] != "-1")
			{
				$a[] = $survey_resposta[$row["survey_var_name"]][$row["digits"]];
			}
			else
			{
				$a[] = $text['label-no-reply'];
			}
		
			if($row["digits"] != "-1")
			{
				$a[] = $row["digits"];
			}
			else
			{
				$a[] = $text['label-no-reply'];
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
	$aUuid = '';
	$aUuidInc = 0;
	$x = $requestData['start'];
	foreach($result as $row)
	{
		$x++;
		if(strlen($aUuid) == 0)
		{
			$aUuid = $row["uuid_a"];
		}
		else
		{
			if($aUuid != $row["uuid_a"])
			{
				$aUuid = $row["uuid_a"];
				$aUuidInc++;
			}
		}
		
		$uuid = $aUuid;
		$bridge_uuid ="";
		$member_uuid = "";
		$start_epoch = $row["start_epoch_a"];
		$cc_record_filename = $row["cc_record_filename"];
		
		/**
			search recording
		*/
		
		$link_recordings = searchRecordings($uuid, $bridge_uuid, $member_uuid, $start_epoch, $cc_record_filename);
		$link_recordings = str_replace('~', '%', $link_recordings);
		
		if(strlen($link_recordings) == 0)
		{
			$cc_record_filename = $row["record_session"];
			$link_recordings = searchRecordings($uuid, $bridge_uuid, $member_uuid, $start_epoch, $cc_record_filename);
			$link_recordings = str_replace('~', '%', $link_recordings);
		}
		
		$a = array();
		
		if($aUuidInc % 2 == 0)
		{
			$a[] = "<span class='label'></span>";
		}
		else
		{
			$a[] = "<span class='label label-warning'></span>";
		}
		
		$a[] = $row["Direcao"];
		$a[] = $row["Numero"];
		$a[] = $row["Protocolo"];
		$a[] = date('Y-m-d H:i:s', $row["start_epoch_a"]);
		$a[] = $row["queue_name"];
		$a[] = $row["agent_name"];
		$a[] = $row["survey_name"];
		$a[] = $row["survey_var_name"];
		
		if($row["digits"] != "-1")
		{
			$a[] = $survey_resposta[$row["survey_var_name"]][$row["digits"]];
		}
		else
		{
			$a[] = $text['label-no-reply'];
		}
		
		if($row["digits"] != "-1")
		{
			$a[] = $row["digits"];
		}
		else
		{
			$a[] = $text['label-no-reply'];
		}
		
		if(strlen($link_recordings))
		{
			$a[] = "<div class='cRecording' ><a href='#' class='bPlay' data-down='../recordings/recording_play.php?a=download&type=rec&filename=". base64_encode($link_recordings) . "' data-play='../recordings/recording_play.php?a=download&type=rec&filename=" . base64_encode($link_recordings) . "' ><i class='fas fa-headphones'></i></a><a class='bDown' href='../recordings/recording_play.php?a=download&type=rec&t=bin&filename=" . base64_encode($link_recordings) . "' ><i class='fas fa-download'></i></a></div>";
		}
		else
		{
			$a[] = "";
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
?>
