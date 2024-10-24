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
		
	$sql_where_ands[] = "`c`.`start_epoch` BETWEEN ".$start_stamp_begin_epoch." AND ".$start_stamp_end_epoch;

	if (strlen($cc_agent) > 0)
	{
		$sql_where_ands[] = "`c`.`cc_agent_name` = '".$cc_agent."'";
	}
	
	if (strlen($cc_queue) > 0)
	{
		$sql_where_ands[] = "`c`.`cc_queue` = '".$cc_queue."@".$_SESSION["domain_name"]."'";
	}
	
	if (sizeof($sql_where_ands) > 0)
	{
		$sql_where = " and ".implode(" and ", $sql_where_ands);
	}

	$sql_where = " and `c`.domain_uuid = '$domain_uuid' and `c`.break_uuid is not null and `c`.`agent_status` = 'on break' " . $sql_where;

	$sql  = " SELECT";
	$sql .= " count(1) as recordsTotal ";
	$sql .= " FROM ((`calliopedb`.`v_xml_cdr_call_center_agent` `c`";
	$sql .= " LEFT JOIN `calliopedb`.`v_call_center_break_breaks` `b` on((`b`.`call_center_break_break_uuid` = `c`.`break_uuid`)))";
	$sql .= " LEFT JOIN `calliopedb`.`v_xml_cdr` `x` on((`x`.`uuid` = `c`.`cdr_uuid`)))";
	$sql .= " WHERE ((`c`.`agent_status` IN ('on break', 'break_return'))";
	$sql .= " AND (`c`.`cc_queue` IS NOT NULL))";
	$sql .= $sql_where;
	$sql .= " ORDER BY `c`.`cc_agent_name`, `c`.`start_epoch`";
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
	
	
	$sql  = " 
		SELECT * FROM (
	SELECT";
	$sql .= " `c`.`domain_uuid` AS `domain_uuid`,";
	$sql .= " `c`.`break_uuid` AS `break_uuid`,";
	$sql .= " `c`.`start_epoch` AS `start_epoch`,";
	$sql .= " `c`.`cc_agent_name` AS `Agent`,";
	$sql .= " substring_index(`c`.`cc_queue`, '@', 1) AS `Queue`,";
	$sql .= " `c`.`agent_status` AS `Break`,";
	$sql .= " round((`b`.`break_timeout` * 60),0) AS `Max_Time_Allowed`,";
	$sql .= " `c`.`start_epoch` AS `Date`,";
	$sql .= " `c`.`start_epoch` AS `Hour`,";
	$sql .= " if((`c`.`agent_status` = 'call_start'),`x`.`end_epoch`, `c`.`end_epoch`) AS `End_Time`,";
	$sql .= " sec_to_time(if((`c`.`agent_status` = 'call_start'),(`x`.`end_epoch` - `x`.`start_epoch`),(`c`.`end_epoch` - `c`.`start_epoch`))) AS `Duration`,";
	$sql .= " `b`.`break_name` AS `Break_Name`,";
	$sql .= " (CASE `c`.`agent_status`";
	$sql .= " WHEN 'on break' THEN sec_to_time(`c`.`duration`)";
	$sql .= " ELSE ''";
	$sql .= " END) AS `Break_Duration`,";
	$sql .= " round(`c`.`duration`, 0) AS `Break_Minutes`,";
	$sql .= " (CASE";
	$sql .= " WHEN isnull(`c`.`duration`) THEN '0'";
	$sql .= " WHEN (`b`.`break_timeout` > 0) THEN (round(`c`.`duration`, 0) - round((`b`.`break_timeout` * 60),0))";
	$sql .= " ELSE '0'";
	$sql .= " END) AS `Break_Exceeded`";
	$sql .= " FROM ((`calliopedb`.`v_xml_cdr_call_center_agent` `c`";
	$sql .= " LEFT JOIN `calliopedb`.`v_call_center_break_breaks` `b` on((`b`.`call_center_break_break_uuid` = `c`.`break_uuid`)))";
	$sql .= " LEFT JOIN `calliopedb`.`v_xml_cdr` `x` on((`x`.`uuid` = `c`.`cdr_uuid`)))";
	$sql .= " WHERE ((`c`.`agent_status` IN ('on break', 'break_return'))";
	$sql .= " AND (`c`.`cc_queue` IS NOT NULL))";
	$sql .= $sql_where;
	$sql .= " ORDER BY `c`.`cc_agent_name`, `c`.`start_epoch`,  `c`.`end_epoch` DESC
	
	)a
	GROUP BY Agent, break_uuid, start_epoch
	ORDER BY start_epoch asc
	
	";	
	
	if(isset($requestData['start']))
	{
		$sql .= " LIMIT " . $requestData['start'] . " ," . $requestData['length'] . "   ";
	}
	
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
		header('Content-Disposition: attachment; filename="' . uuid() .'.csv"');
		
		$t = array();
		
		array_push($t, $text['label-agent']);
		array_push($t, $text['label-pause_name']);
		array_push($t, $text['label-date']);
		array_push($t, $text['label-time']);
		array_push($t, $text['label-end_time']);
		array_push($t, $text['label-break_timeout']);
		array_push($t, $text['label-duration']);
		array_push($t, $text['label-break_exceeded']);
		
		$fh = fopen('php://temp', 'rw');
		
		fputs($fh, $bom = chr(0xEF) . chr(0xBB) . chr(0xBF));
		
		fputcsv($fh, $t, ";");

		foreach ($result as $row)
		{
			$a = array();
			$a[] = $row["Agent"];
			$a[] = $row["Break_Name"];
			$a[] = date('Y-m-d', $row["Date"]);
			$a[] = date('H:i:s', $row["Hour"]);
			$a[] = date('H:i:s', $row["End_Time"]);
			$a[] = sprintf('%02d:%02d:%02d', ($row["Max_Time_Allowed"]/ 3600),($row["Max_Time_Allowed"]/ 60 % 60), $row["Max_Time_Allowed"]% 60);
			if(intval($row["Break_Exceeded"]) > 0)
			{
				$a[] = $row["Duration"];
				$a[] = sprintf('%02d:%02d:%02d', ($row["Break_Exceeded"]/ 3600),($row["Break_Exceeded"]/ 60 % 60), $row["Break_Exceeded"]% 60);
			}
			else
			{
				$a[] = $row["Duration"];
				$a[] = '00:00:00';
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
	foreach($result as $row)
	{
		$a = array();
		$a[] = $row["Agent"];
		$a[] = $row["Break_Name"];
		$a[] = date('Y-m-d', $row["Date"]);
		$a[] = date('H:i:s', $row["Hour"]);
		$a[] = date('H:i:s', $row["End_Time"]);
		$a[] = "<span class='badge badge-warning'>".sprintf('%02d:%02d:%02d', ($row["Max_Time_Allowed"]/ 3600),($row["Max_Time_Allowed"]/ 60 % 60), $row["Max_Time_Allowed"]% 60)."</span>";
		if(intval($row["Break_Exceeded"]) > 0)
		{
			$a[] = "<span class='badge badge-important'>".$row["Duration"]."</span>";
			$a[] = "<span class='badge badge-important'>".sprintf('%02d:%02d:%02d', ($row["Break_Exceeded"]/ 3600),($row["Break_Exceeded"]/ 60 % 60), $row["Break_Exceeded"]% 60)."</span>";
		}
		else
		{
			$a[] = "<span class='badge badge-success'>".$row["Duration"]."</span>";
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
?>
