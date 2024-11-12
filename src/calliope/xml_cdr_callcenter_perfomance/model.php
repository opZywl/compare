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

	ini_set('memory_limit', '-1');

	/**
		add multi-lingual support
	*/

	foreach ($text as $key => $value)
	{
		$text[$key] = $value[$_SESSION['domain']['language']['code']];
	}

	/**
		include
	*/
	
	require_once "resources/header.php";
	
	$requestData = $_REQUEST;
	
	$filter = $_POST['FILTER'];
	$cc_queue = $_POST['CC_QUEUE'];
	$cc_agent = $_POST['CC_AGENT'];
	$start_stamp_begin = $_POST['DATA_INI'];
	$start_stamp_end = $_POST['DATA_END'];
	$direction = $_POST['DIRECTION'];
	$caller_id_number = $_POST['CALLER_ID_NUMBER'];
	$destination_number = $_POST['DESTINATION_NUMBER'];
	$extension = $_POST['EXTENSION'];
	$finalization = $_POST['FINALIZATION'];
	$finalization_member = $_POST['FINALIZATION_MEMBER'];
	$finalization_agent = $_POST['FINALIZATION_AGENT'];
	$uuid = $_POST['UUID'];
	$protocol = $_POST['PROTOCOL'];
	$ring_duration = $_POST['RING_DURATION'];
	$ring_duration = numbers_only($ring_duration);
		
	/*QUERY DO CALLCENTER PARA PEGAR OS DADOS DOS AGENTES*/

		$filter = "summed_up";
		$cc_queue = $_POST['CC_QUEUE'];
		$cc_agent = "";
		$start_stamp_begin = $_POST['DATA_INI'];
		$start_stamp_end = $_POST['DATA_END'];
		$direction = "";	
		$caller_id_number = "";
		$destination_number = "";
		$extension = "";
		$finalization = "";
		$finalization_member = "";
		$finalization_agent = "";
		$uuid = "";
		$ring_duration = "";
		$ring_duration = "";

		require "../xml_cdr_call_center/model_callcenter.php";
		
	
		$sql = "
		DROP TABLE IF EXISTS tmp_cdr_cc_c_perfomance;
		CREATE TEMPORARY TABLE tmp_cdr_cc_c_perfomance AS 			
		SELECT * FROM tmp_cdr_cc_c;
		";
		$tmp_file.= $sql;
		$prep_statement = $db->prepare($sql);
		$prep_statement->execute();
		$prep_statement->fetchAll(PDO::FETCH_NAMED);
		unset($sql, $prep_statement);
			
		$sql = "
			
		SELECT
			days
			,SUM(total) 'total'
			,SUM(agent_answer_total) 'agent_answer_total'
			,SUM(agent_answer_duration_sec) 'agent_answer_duration_sec'
			,SUM(agent_answer_duration_sec) / SUM(agent_answer_total) AS 'answer_avg_time'
			,SUM(inbound_member_canceled_total) AS 'inbound_member_canceled_total'
			,SUM(inbound_member_canceled_total_duration) / SUM(inbound_member_canceled_total) 'inbound_avg_time_canceled'
			,SUM(inbound_member_canceled_total_duration) 'inbound_member_canceled_total_duration'
			,SUM(inbound_issue_sound_canceled_total_percent) 'inbound_issue_sound_canceled_total_percent'
			,SUM(inbound_issue_sound_canceled_total) 'inbound_issue_sound_canceled_total'
			,SUM(outbound_answered_total) 'outbound_answered_total'
			,SUM(outbound_canceled_total) 'outbound_canceled_total'		
			,SUM(outbound_answered_total_duration) 'outbound_answered_total_duration'
			,SUM(outbound_answered_avg_time) 'outbound_answered_avg_time'
			,SUM(inbound_answered_less_15) 'inbound_answered_less_15'
			,SUM(inbound_answered_less_20) 'inbound_answered_less_20'
			,SUM(inbound_answered_less_60) 'inbound_answered_less_60'
			,SUM(inbound_answered_above_60) 'inbound_answered_above_60'
			,SUM(inbound_answered_less_25) 'inbound_answered_less_25'
			,SUM(inbound_answered_less_30) 'inbound_answered_less_30'
			,SUM(inbound_answered_less_35) 'inbound_answered_less_35'
			,SUM(inbound_answered_less_40) 'inbound_answered_less_40'
			,SUM(inbound_answered_less_45) 'inbound_answered_less_45'
			,SUM(inbound_answered_less_50) 'inbound_answered_less_50'
			,SUM(inbound_answered_less_55) 'inbound_answered_less_55'
			,SUM(inbound_tme) AS inbound_tme
		FROM (
		
			/*days*/
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'1' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' as 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'canceleds'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			/*WHERE dir_member in ('inbound_member_answered', 'inbound_member_canceled', 'inbound_member_issue_sound', 'inbound_member_callback_answered')*/
			WHERE dir_member in ('inbound_member_answered', 'inbound_member_canceled')
			
			
			UNION ALL
			
			/*inbound_answered_total*/
			/* TOTAL INBOUND FILA ANSWERED*/
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'1' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,agent_answer_duration_sec AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE dir_member = 'inbound_member_answered'
			
			UNION ALL 
			
			/*INBOUND CANCELED TOTAL DURATION*/		
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d')as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' as 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '% TRANSBORDO'
				,'1' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,queue_duration_sec as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as '% inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE dir_member = 'inbound_member_canceled'
			
			UNION ALL
			
			/*TEMPO MÉDIO ABANDONO*/
			/*SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' as 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE dir_member in ('inbound_member_canceled')
			
			
			UNION ALL*/
			
			/*ISSUE SOUND*/
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' as 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'1' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE dir_member in ('inbound_member_issue_sound')
			
			UNION ALL 
			
			/*OUTBOUND ANSWERED CALLS*/
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' as 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'1' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,agent_answer_duration_sec as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE dir_member in ('outbound_answered')
			
			
			UNION ALL 
			
			
			/*OUTBOUND AVG*/
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' as 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,SUM(agent_answer_duration_sec )/ COUNT(1) as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE dir_member in ('outbound_answered')
			
			
			UNION ALL
			
			/*OUTBOUND CANCELED CALLS*/
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'1' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE dir_member in ('outbound_canceled')

			UNION ALL
		
			/*INBOUND ANSWERED BETWEEN 16 AND 20 */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'1' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec BETWEEN 16 AND 20
			AND dir_member = 'inbound_member_answered'

			UNION ALL
		
			/*INBOUND ANSWERED <= 15 SEG */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'1' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec <= 15
			AND dir_member = 'inbound_member_answered'

			UNION ALL
		
			/*INBOUND ANSWERED BETWEEN 21 AND 30 */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec BETWEEN 21 AND 30
			AND dir_member = 'inbound_member_answered'

			UNION ALL
		
			/*INBOUND ANSWERED BETWEEN 31 AND 60 */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'1' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec BETWEEN 55 AND 60
			AND dir_member = 'inbound_member_answered'

			UNION ALL 

			/*INBOUND ANSWERED > 60 SEG */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'1' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec > 60
			AND dir_member = 'inbound_member_answered'

			UNION ALL
		
			/*INBOUND ANSWERED BETWEEN 31 AND 60 */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'1' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec BETWEEN 21 AND 25
			AND dir_member = 'inbound_member_answered'
			
			UNION ALL
		
			/*INBOUND ANSWERED BETWEEN 31 AND 60 */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'1' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec BETWEEN 26 AND 30
			AND dir_member = 'inbound_member_answered'
			
			UNION ALL
		
			/*INBOUND ANSWERED BETWEEN 31 AND 60 */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'1' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec BETWEEN 31 AND 35
			AND dir_member = 'inbound_member_answered'
			
			UNION ALL
		
			/*INBOUND ANSWERED BETWEEN 31 AND 60 */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'1' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec BETWEEN 41 AND 45
			AND dir_member = 'inbound_member_answered'
			
			UNION ALL
		
			/*INBOUND ANSWERED BETWEEN 31 AND 60 */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'1' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec BETWEEN 41 AND 45
			AND dir_member = 'inbound_member_answered'
			
			UNION ALL
		
			/*INBOUND ANSWERED BETWEEN 31 AND 60 */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'1' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec BETWEEN 46 AND 50
			AND dir_member = 'inbound_member_answered'
			
			UNION ALL
		
			/*INBOUND ANSWERED BETWEEN 55 AND 60 */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'1' as 'inbound_answered_less_55'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec BETWEEN 51 AND 55
			AND dir_member = 'inbound_member_answered'
			
			UNION ALL
			
			/*TME */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_answered_less_25'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_35'
				,'0' as 'inbound_answered_less_40'
				,'0' as 'inbound_answered_less_45'
				,'0' as 'inbound_answered_less_50'
				,'0' as 'inbound_answered_less_55'
				,'1' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND dir_member in ('inbound_member_answered', 'inbound_member_callback_answered')
		)a
		WHERE days IS NOT null
		GROUP BY days;
		";
		unset($result_dias);
		$prep_statement = $db->prepare(check_sql($sql));
		$prep_statement->execute();
		$result_dias = $prep_statement->fetchAll(PDO::FETCH_ASSOC);
		$tmp_file.= "\n".$sql;
		
		$tmp_file.= "/*encode\n";
		$tmp_file.= "".json_decode($result_dias)."\n";
		$tmp_file.= "encode*/\n";
		
		$n= count($result_dias);
		
		$tmp_file.= "/*";
		$tmp_file.= "total [$n]\n";
		$tmp_file.= "total [$n]\n";
		$tmp_file.= "total [$n]\n";
		$tmp_file.= "total [$n]\n";
		$tmp_file.= "total [$n]\n";
		$tmp_file.= "total [$n]\n";
		$tmp_file.= "*/";	
		
		//atenidas pelo callcenter
		//$result_inbound_queue_answered = $result_inbound_queue_answered[0]['total'] + $result_callback_total[0]['total'];
		// $result_inbound_queue_answered = $result_inbound_queue_answered[0]['total'];
		
		//tempo médio atendimento 
		$result_queue_avg_time = $result_queue_avg_time[0]['queue_avg_time']; //já tem o gmd do model_callcenter
		
		//tempo total de atendimento
		$sql = "\n\n /*TEMPO TOTAL ATENDIMENTO*/
					SELECT
						SUM(c.agent_answer_duration_sec) AS answer_duration
						,AVG(c.agent_answer_duration_sec) AS answer_duration_avg
					FROM tmp_cdr_cc_c c
					WHERE dir_member in ('inbound_member_answered');";
		$tmp_file.= $sql;
		$prep_statement = $db->prepare($sql);
		$prep_statement->execute();
		$res = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		$result_answer_duration = gmdate("H:i:s",$res[0]['answer_duration']); 
		$result_answer_duration_avg = gmdate("H:i:s",$res[0]['answer_duration_avg']); 
		
		
		//tempo médio de abandono
		$sql = "\n\n /*TEMPO MÉDIO EM FILA ABANDONO*/
					SELECT SUM(c.queue_duration_sec) / COUNT(1) AS queue_avg_canceled_duration FROM tmp_cdr_cc_c c
					WHERE dir_member in ('inbound_member_canceled');";
		$tmp_file.= $sql;
		$prep_statement = $db->prepare($sql);
		$prep_statement->execute();
		$res = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		$result_queue_avg_canceled_duration = $res[0]['queue_avg_canceled_duration'];
		
		//abandonadas na fila até 10 seg
		$sql = "
			
		/*TOTAL INBOUND FILA CANCELED <= 10s */
			SELECT
				COUNT(1) AS total
				,AVG(c.queue_duration_sec) AS result_inbound_queue_canceled_10_avg_duration
			FROM tmp_cdr_cc_c c
			WHERE dir_member = 'inbound_member_canceled'
			AND queue_duration_sec <= 10;";
		$tmp_file.= $sql;
		$prep_statement = $db->prepare($sql);
		$prep_statement->execute();
		$res = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		$result_inbound_queue_canceled_10_total = $res[0]['total'];
		$result_inbound_queue_canceled_10_avg_duration = $res[0]['result_inbound_queue_canceled_10_avg_duration'];
		
		//abandonadas na fila > 10s
		//$result_inbound_queue_canceled_10_total;
		
		//tempo médio do abandono acima 10 seg
		$result_inbound_queue_canceled_10_avg_duration = gmdate("H:i:s", $result_inbound_queue_canceled_10_avg_duration);
		
		
		
		//abandonadas na fila acima 10 seg
		$sql = "
			
		/*TOTAL INBOUND FILA CANCELED > 10s */
			SELECT
				COUNT(1) AS total
				,AVG(c.queue_duration_sec) AS result_inbound_queue_canceled_mt_10_avg_duration
			FROM tmp_cdr_cc_c c
			WHERE dir_member = 'inbound_member_canceled'
			AND queue_duration_sec > 10;";
		$tmp_file.= $sql;
		$prep_statement = $db->prepare($sql);
		$prep_statement->execute();
		$res = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		$result_inbound_queue_canceled_mt_10_total = $res[0]['total'];
		$result_inbound_queue_canceled_mt_10_avg_duration = $res[0]['result_inbound_queue_canceled_mt_10_avg_duration'];
			

		//abandonadas na fila > 10s
		//$result_inbound_queue_canceled_mt_10_total;
		
		//tempo médio do abandono acima 10 seg
		$result_inbound_queue_canceled_mt_10_avg_duration = gmdate("H:i:s", $result_inbound_queue_canceled_mt_10_avg_duration);
		
		
		//issue sound
		// $result_issue_sound = $result_issue_sound[0]['total'];


		//pegar periodo mensal do filtro de data
		$startStampBeginEpochArr = explode('-', $start_stamp_begin);
		$startMonth = $startStampBeginEpochArr[1];
		$nextMonth = $startStampBeginEpochArr[1] + 1;
		$startYear= $startStampBeginEpochArr[0];
		$endStampNextMonth = "$startYear-$nextMonth-01";
		$endStamp = date('Y-m-d', strtotime($endStampNextMonth . " -1 day"));

		$start_stamp_begin = "$startYear-$startMonth-01 00:00";
		$start_stamp_end = "$endStamp 23:59";

		$tmp_file = "";

		require "../xml_cdr_call_center/model_callcenter.php";

		$sql = "
		DROP TABLE IF EXISTS tmp_cdr_cc_c_perfomance;
		CREATE TEMPORARY TABLE tmp_cdr_cc_c_perfomance AS 			
		SELECT * FROM tmp_cdr_cc_c;
		";
		$tmp_file.= $sql;
		$prep_statement = $db->prepare($sql);
		$prep_statement->execute();
		$prep_statement->fetchAll(PDO::FETCH_NAMED);
		unset($sql, $prep_statement);

		$sql = "
			
		SELECT
			days
			,SUM(total) 'total'
			,SUM(agent_answer_total) 'agent_answer_total'
			,SUM(agent_answer_duration_sec) 'agent_answer_duration_sec'
			,SUM(agent_answer_duration_sec) / SUM(agent_answer_total) AS 'answer_avg_time'
			,SUM(inbound_member_canceled_total) AS 'inbound_member_canceled_total'
			,SUM(inbound_member_canceled_total_duration) / SUM(inbound_member_canceled_total) 'inbound_avg_time_canceled'
			,SUM(inbound_member_canceled_total_duration) 'inbound_member_canceled_total_duration'
			,SUM(inbound_issue_sound_canceled_total_percent) 'inbound_issue_sound_canceled_total_percent'
			,SUM(inbound_issue_sound_canceled_total) 'inbound_issue_sound_canceled_total'
			,SUM(outbound_answered_total) 'outbound_answered_total'
			,SUM(outbound_canceled_total) 'outbound_canceled_total'		
			,SUM(outbound_answered_total_duration) 'outbound_answered_total_duration'
			,SUM(outbound_answered_avg_time) 'outbound_answered_avg_time'
			,SUM(inbound_answered_less_20) 'inbound_answered_less_20'
			,SUM(inbound_answered_less_15) 'inbound_answered_less_15'
			,SUM(inbound_answered_less_30) 'inbound_answered_less_30'
			,SUM(inbound_answered_less_60) 'inbound_answered_less_60'
			,SUM(inbound_answered_above_60) 'inbound_answered_above_60'
			,SUM(inbound_ns) 'inbound_ns'
			,SUM(inbound_tme) AS inbound_tme
		FROM (
		
			/*days*/
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'1' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' as 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'canceleds'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_ns'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			/*WHERE dir_member in ('inbound_member_answered', 'inbound_member_canceled', 'inbound_member_issue_sound', 'inbound_member_callback_answered')*/
			WHERE dir_member in ('inbound_member_answered', 'inbound_member_canceled')
			
			
			UNION ALL
			
			/*inbound_answered_total*/
			/* TOTAL INBOUND FILA ANSWERED*/
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'1' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,agent_answer_duration_sec AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_ns'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE dir_member = 'inbound_member_answered'
			
			UNION ALL 
			
			/*INBOUND CANCELED TOTAL DURATION*/		
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d')as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' as 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '% TRANSBORDO'
				,'1' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,queue_duration_sec as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as '% inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_ns'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE dir_member = 'inbound_member_canceled'
			
			UNION ALL
			
			/*TEMPO MÉDIO ABANDONO*/
			/*SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' as 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_ns'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE dir_member in ('inbound_member_canceled')
			
			
			UNION ALL*/
			
			/*ISSUE SOUND*/
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' as 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'1' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_ns'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE dir_member in ('inbound_member_issue_sound')
			
			UNION ALL 
			
			/*OUTBOUND ANSWERED CALLS*/
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' as 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'1' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,agent_answer_duration_sec as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_ns'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE dir_member in ('outbound_answered')
			
			
			UNION ALL 
			
			
			/*OUTBOUND AVG*/
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' as 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,SUM(agent_answer_duration_sec )/ COUNT(1) as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_ns'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE dir_member in ('outbound_answered')
			
			
			UNION ALL
			
			/*OUTBOUND CANCELED CALLS*/
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'1' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_ns'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE dir_member in ('outbound_canceled')

			UNION ALL
		
			/*INBOUND ANSWERED BETWEEN 16 AND 20 */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'1' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_ns'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec BETWEEN 16 AND 20
			AND dir_member = 'inbound_member_answered'

			UNION ALL
		
			/*INBOUND ANSWERED <= 15 SEG */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_20'
				,'1' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_ns'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec <= 15
			AND dir_member = 'inbound_member_answered'

			UNION ALL
		
			/*INBOUND ANSWERED BETWEEN 21 AND 30 */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_15'
				,'1' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_ns'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec BETWEEN 21 AND 30
			AND dir_member = 'inbound_member_answered'

			UNION ALL
		
			/*INBOUND ANSWERED BETWEEN 31 AND 60 */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_30'
				,'1' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_ns'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec BETWEEN 31 AND 60
			AND dir_member = 'inbound_member_answered'

			UNION ALL 

			/*INBOUND ANSWERED > 60 SEG */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_60'
				,'1' as 'inbound_answered_above_60'
				,'0' as 'inbound_ns'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec > 60
			AND dir_member = 'inbound_member_answered'
			
			/*INBOUND NS */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'1' as 'inbound_ns'
				,'0' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND queue_duration_sec <= 120
			AND dir_member = 'inbound_member_answered'
			
			
			/*TME */
			SELECT
				FROM_UNIXTIME(start_epoch_a, '%Y-%m-%d') as days
				,'0' as 'total'
				,'0' as 'agent_answer_total'
				,'0' as 'agent_answer_total_percent'
				,'0' AS 'agent_answer_duration_sec'
				,'0' as 'answer_avg_time'
				,'0' as 'TRANSBORDO'
				,'0' as '%TRANSBORDO'
				,'0' as 'inbound_member_canceled_total'
				,'0' as 'inbound_member_canceled_total_percent'
				,'0' as 'inbound_avg_time_canceled'
				,'0' as 'inbound_member_canceled_total_duration'
				,'0' as 'inbound_issue_sound_canceled_total'
				,'0' as 'inbound_issue_sound_canceled_total_percent'
				,'0' as 'outbound_answered_total'
				,'0' as 'outbound_canceled_total'
				,'0' as 'outbound_answered_total_duration'
				,'0' as 'outbound_answered_avg_time'
				,'0' as 'inbound_answered_less_20'
				,'0' as 'inbound_answered_less_15'
				,'0' as 'inbound_answered_less_30'
				,'0' as 'inbound_answered_less_60'
				,'0' as 'inbound_answered_above_60'
				,'0' as 'inbound_ns'
				,'1' as 'inbound_tme'
			FROM tmp_cdr_cc_c_perfomance
			WHERE TRUE 
			AND dir_member in ('inbound_member_answered', 'inbound_member_callback_answered')
				
		)a
		WHERE days IS NOT null
		GROUP BY days;
		";
		unset($result_dias_monthly);
		$prep_statement = $db->prepare(check_sql($sql));
		$prep_statement->execute();
		$result_dias_monthly = $prep_statement->fetchAll(PDO::FETCH_ASSOC);
		$tmp_file.= "\n".$sql;

		//atenidas pelo callcenter
		$result_inbound_queue_answeredMonthly = $result_inbound_queue_answered[0]['total'];

		
		//tempo médio atendimento 
		$result_queue_avg_timeMonthly = $result_queue_avg_time[0]['queue_avg_time']; //já tem o gmd do model_callcenter
		
		//tempo total de atendimento
		$sql = "\n\n /*TEMPO TOTAL ATENDIMENTO*/
					SELECT
						SUM(c.agent_answer_duration_sec) AS answer_duration
						,AVG(c.agent_answer_duration_sec) AS answer_duration_avg
					FROM tmp_cdr_cc_c c
					WHERE dir_member in ('inbound_member_answered');";
		$tmp_file.= $sql;
		$prep_statement = $db->prepare($sql);
		$prep_statement->execute();
		$res = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		$result_answer_durationMonthly = gmdate("H:i:s",$res[0]['answer_duration']); 
		$result_answer_duration_avgMonthly = gmdate("H:i:s",$res[0]['answer_duration_avg']); 
		
		
		//tempo médio de abandono
		$sql = "\n\n /*TEMPO MÉDIO EM FILA ABANDONO*/
					SELECT SUM(c.queue_duration_sec) / COUNT(1) AS queue_avg_canceled_duration FROM tmp_cdr_cc_c c
					WHERE dir_member in ('inbound_member_canceled');";
		$tmp_file.= $sql;
		$prep_statement = $db->prepare($sql);
		$prep_statement->execute();
		$res = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		$result_queue_avg_canceled_durationMonthly = $res[0]['queue_avg_canceled_duration'];
		
		
		
		//abandonadas na fila até 10 seg
		$sql = "
			
		/*TOTAL INBOUND FILA CANCELED <= 10s */
			SELECT
				COUNT(1) AS total
				,AVG(c.queue_duration_sec) AS result_inbound_queue_canceled_10_avg_duration
			FROM tmp_cdr_cc_c c
			WHERE dir_member = 'inbound_member_canceled'
			AND queue_duration_sec <= 10;";
		$tmp_file.= $sql;
		$prep_statement = $db->prepare($sql);
		$prep_statement->execute();
		$res = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		$result_inbound_queue_canceled_10_totalMonthly = $res[0]['total'];
		$result_inbound_queue_canceled_10_avg_durationMonthly = $res[0]['result_inbound_queue_canceled_10_avg_duration'];
		
		//abandonadas na fila > 10s
		//$result_inbound_queue_canceled_10_total;
		
		//tempo médio do abandono acima 10 seg
		$result_inbound_queue_canceled_10_avg_durationMonthly = gmdate("H:i:s", $result_inbound_queue_canceled_10_avg_durationMonthly);
		
		
		
		//abandonadas na fila acima 10 seg
		$sql = "
			
		/*TOTAL INBOUND FILA CANCELED > 10s */
			SELECT
				COUNT(1) AS total
				,AVG(c.queue_duration_sec) AS result_inbound_queue_canceled_mt_10_avg_duration
			FROM tmp_cdr_cc_c c
			WHERE dir_member = 'inbound_member_canceled'
			AND queue_duration_sec > 10;";
		$tmp_file.= $sql;
		$prep_statement = $db->prepare($sql);
		$prep_statement->execute();
		$res = $prep_statement->fetchAll(PDO::FETCH_NAMED);
		$result_inbound_queue_canceled_mt_10_totalMonthly = $res[0]['total'];
		$result_inbound_queue_canceled_mt_10_avg_durationMonthly = $res[0]['result_inbound_queue_canceled_mt_10_avg_duration'];
			

		//abandonadas na fila > 10s
		//$result_inbound_queue_canceled_mt_10_total;
		
		//tempo médio do abandono acima 10 seg
		$result_inbound_queue_canceled_mt_10_avg_durationMonthly = gmdate("H:i:s", $result_inbound_queue_canceled_mt_10_avg_durationMonthly);
		
		
		//issue sound
		$result_issue_soundMonthly = $result_issue_sound[0]['total'];
		
		if(isset($_GET["file"]) && $_GET["file"] == "eCSV")
		{
			header('Set-Cookie: fileDownload=true; path=/');
			header('Cache-Control: max-age=60, must-revalidate');
			header("Content-type: text/csv");
			header('Content-Disposition: attachment; filename="' . uuid() .'.csv"');
			
			$t = array();
			array_push($t, $text['label-days']);
			array_push($t, $text['label-total_inbound']);
			array_push($t, $text['label-total_answered']);
			array_push($t, $text['label-total_answered_15']);
			array_push($t, $text['label-total_answered_20']);
			array_push($t, $text['label-total_answered_25']);
			array_push($t, $text['label-total_answered_30']);
			array_push($t, $text['label-total_answered_35']);
			array_push($t, $text['label-total_answered_40']);
			array_push($t, $text['label-total_answered_45']);
			array_push($t, $text['label-total_answered_45']);
			array_push($t, $text['label-total_answered_55']);
			array_push($t, $text['label-total_answered_60']);
			array_push($t, $text['label-total_answered_above_60']);
			array_push($t, $text['label-ns']);
			array_push($t, $text['label-answered-percentage']);
			array_push($t, $text['label-total_answered_duration']);
			array_push($t, $text['label-tma']);
			array_push($t, $text['label-tme']);
			array_push($t, $text['label-total_canceled']);
			array_push($t, $text['label-canceled-percentag']);
			array_push($t, $text['label-av_canceled']);
			array_push($t, $text['label-total_canceled_time']);
			array_push($t, $text['label-cc_droped_issue_sound']);
			array_push($t, $text['label-cc_droped_issue_sound-percent']);				
			array_push($t, $text['label-total_outbound']);
			array_push($t, $text['label-total_outbound_answered']);
			array_push($t, $text['label-outbound-answered-percentage']);
			array_push($t, $text['label-outbound-total_answered_duration']);
			
			$fh = fopen('php://temp', 'rw');
			
			fputs($fh, $bom = chr(0xEF) . chr(0xBB) . chr(0xBF));
			
			fputcsv($fh, $t, ";");

			foreach($result_dias as $row)
			{
				$a = array();
				$a[] = $row['days'];
				$a[] = $row['total'];
				$a[] = $row['agent_answer_total'];
				$a[] = $row['inbound_answered_less_15'];
				$a[] = $row['inbound_answered_less_20'];
				$a[] = $row['inbound_answered_less_25'];
				$a[] = $row['inbound_answered_less_30'];
				$a[] = $row['inbound_answered_less_35'];
				$a[] = $row['inbound_answered_less_40'];
				$a[] = $row['inbound_answered_less_45'];
				$a[] = $row['inbound_answered_less_50'];
				$a[] = $row['inbound_answered_less_55'];
				$a[] = $row['inbound_answered_less_60'];
				$a[] = $row['inbound_answered_above_60'];
				
				$inbound_ns = $row['inbound_answered_less_15'] + $row['inbound_answered_less_20'];
				$ns_percent = $row['agent_answer_total'] == 0 ? 0 : $inbound_ns / $row['agent_answer_total'];
				$ns_percent = number_format($ns_percent * 100, 2) . '%'; 
				$a[] = $ns_percent;
				
				//% answereds		
				$percent = $row['agent_answer_total'] == 0 ? 0 : $row['agent_answer_total'] / $row['total'];
				$percent = number_format($percent * 100, 2) . '%';
				$a[] = $percent;
			
				//answered_duration 
				$a[] = gmdate("H:i:s", $row['agent_answer_duration_sec']);
				
				//answer_avg_time
				$a[] = gmdate("H:i:s", $row['answer_avg_time']);
				
				//inbound_tme 1756
				$tme = $row['inbound_tme'] == 0 ? 0 : $row['inbound_tme'] / $row['total'];
				$a[] = gmdate("H:i:s", $tme);
							
				//total canceled
				$a[] = $row['inbound_member_canceled_total'];
				
				//% canceleds	
				$percent = $row['inbound_member_canceled_total'] == 0 ? 0 : $row['inbound_member_canceled_total'] / $row['total'];
				$percent = number_format($percent * 100, 2) . '%';
				$a[] = $percent;
				
				//% tma canceled	
				$a[] = gmdate("H:i:s", $row['inbound_avg_time_canceled']);
				
				//% canceled total duration
				$a[] = gmdate("H:i:s", $row['inbound_member_canceled_total_duration']);
				
				//total issue sound
				$a[] = $row['inbound_issue_sound_canceled_total'];
				
				//% issue sound	
				$percent = $row['inbound_issue_sound_canceled_total'] == 0 ? 0 : $row[0]['inbound_issue_sound_canceled_total'] / $row[0]['inbound_member_total'];
				$percent = number_format($percent * 100, 2) . '%';
				$a[] = $percent;
				
				//total outbound
				$total_outbound = $row['outbound_answered_total'] + $row['outbound_canceled_total'];
				$a[] = $total_outbound;
				
				//outbound answered
				$a[] = $row['outbound_answered_total'];
				
				//% outbound
				$percent = $row['outbound_answered_total'] == 0 ? 0 : $row['outbound_answered_total'] / $total_outbound;
				$percent = number_format($percent * 100, 2) . '%';
				$a[] = $percent;
				
				
				//% total outbound avg			
				$res = $row['outbound_answered_total_duration'] == 0 ? 0 : $row['outbound_answered_total_duration'] / $row['outbound_answered_total'];
				$res = $res == 0 ? '00:00:00' : gmdate("H:i:s", $res);
				$a[] = $res;

				//total sum
				$totalDays += $row['total'];
				$totalInboundAnswered += $row['agent_answer_total'];
				$totalInboundLess15 += $row['inbound_answered_less_15'];
				$totalInboundLess20 += $row['inbound_answered_less_20'];
				$totalInboundLess25 += $row['inbound_answered_less_25'];
				$totalInboundLess30 += $row['inbound_answered_less_30'];
				$totalInboundLess35 += $row['inbound_answered_less_35'];
				$totalInboundLess40 += $row['inbound_answered_less_40'];
				$totalInboundLess45 += $row['inbound_answered_less_45'];
				$totalInboundLess50 += $row['inbound_answered_less_50'];
				$totalInboundLess55 += $row['inbound_answered_less_55'];
				$totalInboundLess60 += $row['inbound_answered_less_60'];
				$totalInboundAbove60 += $row['inbound_answered_above_60'];
				$totalNs += $inbound_ns;
				$totalTme += $row['inbound_tme'];
				$totalInboundDuration += $row['agent_answer_duration_sec'];
				$totalInboundAnswerAvgTime += $row['answer_avg_time'];
				$totalInboundCanceled += $row['inbound_member_canceled_total'];
				$totalInboundCanceledAvgTime += $row['inbound_avg_time_canceled'];
				$totalInboundCanceledDuration += $row['inbound_member_canceled_total_duration'];
				$totalInboundIssueSound += $row[0]['inbound_issue_sound_canceled_total'];
				$totalInboundMember += $row[0]['inbound_member_total'];
				$totalOutbound += $total_outbound;
				$totalOutboundAnswered += $row['outbound_answered_total'];
				$totalOutboundAnsweredDuration += $row['outbound_answered_total_duration'];

				fputcsv($fh, $a, ";");
			}
			
			$total = array();
			$total[] = 'Total';
			$total[] = $totalDays;
			$total[] = $totalInboundAnswered;
			$total[] = $totalInboundLess15;
			$total[] = $totalInboundLess20;
			$total[] = $totalInboundLess25;
			$total[] = $totalInboundLess30;
			$total[] = $totalInboundLess35;
			$total[] = $totalInboundLess40;
			$total[] = $totalInboundLess45;
			$total[] = $totalInboundLess50;
			$total[] = $totalInboundLess55;
			$total[] = $totalInboundLess60;
			$total[] = $totalInboundAbove60;

			$percent_ns = $totalInboundAnswered == 0 ? 0 : $totalNs / $totalInboundAnswered;
			$percent_ns = number_format($percent_ns * 100, 2) . '%';
			$total[] = $percent_ns;

			//% answereds		
			$percent = $totalInboundAnswered == 0 ? 0 : $totalInboundAnswered / $totalDays;
			$percent = number_format($percent * 100, 2) . '%';
			$total[] = $percent;

			$total[] = gmdate('H:i:s', $totalTme);
			$total[] = gmdate('H:i:s', $totalInboundDuration);
			$total[] = gmdate('H:i:s', $totalInboundAnswerAvgTime);
			$total[] = $totalInboundCanceled;

			//% canceleds	
			$percent = $totalInboundCanceled == 0 ? 0 : $totalInboundCanceled / $totalDays;
			$percent = number_format($percent * 100, 2) . '%';
			$total[] = $percent;

			$total[] = gmdate('H:i:s', $totalInboundCanceledAvgTime);
			$total[] = gmdate('H:i:s', $totalInboundCanceledDuration);
			$total[] = $totalInboundIssueSound;

			//% issue sound	
			$percent = $totalInboundIssueSound == 0 ? 0 : $totalInboundIssueSound / $totalInboundMember;
			$percent = number_format($percent * 100, 2) . '%';
			$total[] = $percent;

			$total[] = $totalOutbound;
			$total[] = $totalOutboundAnswered;

			//% outbound
			$percent = $totalOutboundAnswered == 0 ? 0 : $totalOutboundAnswered / $totalOutbound;
			$percent = number_format($percent * 100, 2) . '%';
			$total[] = $percent;

			//% total outbound avg			
			$res = $totalOutboundAnsweredDuration == 0 ? 0 : $totalOutboundAnsweredDuration / $totalOutboundAnswered;
			$res = $res == 0 ? '00:00:00' : gmdate("H:i:s", $res);
			$total[] = $res;
			
			fputcsv($fh, $total, ";");
			
			rewind($fh);
			
			$csv = stream_get_contents($fh);
			
			fclose($fh);
			
			echo $csv;
			
			return;
		}
		
		$nested = array();
		$totalDays = 0;
		$totalInboundAnswered = 0;
		$totalInboundLess15 = 0;
		$totalInboundLess20 = 0;
		$totalInboundLess30 = 0;
		$totalInboundLess60 = 0;
		$totalInboundAbove60 = 0;
		$totalNs = 0;
		$totalTme = 0;
		$totalInboundDuration = 0;
		$totalInboundAnswerAvgTime = 0;
		$totalInboundCanceled = 0;
		$totalInboundCanceledAvgTime = 0;
		$totalInboundCanceledDuration = 0;
		$totalInboundIssueSound = 0;
		$totalOutbound = 0;
		$totalOutboundAnswered = 0;
		
		foreach($result_dias as $row)
		{
			$a = array();
			
			$a[] = "<span>".$row['days']."</span>";
			$a[] = "<span class='badge badge-success'>".$row['total']."</span>";
			$a[] = "<span class='badge badge-success'>".$row['agent_answer_total']."</span>";
			$a[] = "<span class='badge badge-inverse'>".$row['inbound_answered_less_15']."</span>";
			$a[] = "<span class='badge badge-inverse'>".$row['inbound_answered_less_20']."</span>";
			$a[] = "<span class='badge badge-inverse'>".$row['inbound_answered_less_25']."</span>";
			$a[] = "<span class='badge badge-inverse'>".$row['inbound_answered_less_30']."</span>";
			$a[] = "<span class='badge badge-inverse'>".$row['inbound_answered_less_35']."</span>";
			$a[] = "<span class='badge badge-inverse'>".$row['inbound_answered_less_40']."</span>";
			$a[] = "<span class='badge badge-inverse'>".$row['inbound_answered_less_45']."</span>";
			$a[] = "<span class='badge badge-inverse'>".$row['inbound_answered_less_50']."</span>";
			$a[] = "<span class='badge badge-inverse'>".$row['inbound_answered_less_55']."</span>";
			$a[] = "<span class='badge badge-inverse'>".$row['inbound_answered_less_60']."</span>";
			$a[] = "<span class='badge badge-important'>".$row['inbound_answered_above_60']."</span>";
			
			$inbound_ns = $row['inbound_answered_less_15'] + $row['inbound_answered_less_20'];
			$ns_percent = $row['agent_answer_total'] == 0 ? 0 : $inbound_ns / $row['agent_answer_total'];
			$ns_percent = number_format($ns_percent * 100, 2) . '%'; 
			$a[] = "<span class='badge badge-success'>".$ns_percent. "</span>";
			
			//% answereds		
			$percent = $row['agent_answer_total'] == 0 ? 0 : $row['agent_answer_total'] / $row['total'];
			$percent = number_format($percent * 100, 2) . '%';
			$a[] = "<span class='badge badge-success'>".$percent."</span>";
		
			//answered_duration 
			$a[] = "<span class='badge badge-success'>".gmdate("H:i:s", $row['agent_answer_duration_sec'])."</span>";
			
			//answer_avg_time
			$a[] = "<span class='badge badge-important'>".gmdate("H:i:s", $row['answer_avg_time'])."</span>";
			
			//inbound_tme
			$a[] = "<span class='badge badge-important'>".gmdate("H:i:s", $row['inbound_tme'])."</span>"; 
						
			//total canceled
			$a[] = "<span class='badge badge-important'>".$row['inbound_member_canceled_total']."</span>";
			
			//% canceleds	
			$percent = $row['inbound_member_canceled_total'] == 0 ? 0 : $row['inbound_member_canceled_total'] / $row['total'];
			$percent = number_format($percent * 100, 2) . '%';
			$a[] = "<span class='badge badge-important'>".$percent."</span>";
			
			//% tma canceled	
			$a[] = "<span class='badge badge-important'>".gmdate("H:i:s", $row['inbound_avg_time_canceled'])."</span>";
			
			//% canceled total duration
			$a[] = "<span class='badge badge-important'>".gmdate("H:i:s", $row['inbound_member_canceled_total_duration'])."</span>";
			
			//total issue sound
			$a[] = "<span class='badge badge-inverse'>".$row['inbound_issue_sound_canceled_total']."</span>";
			
			//% issue sound	
			$percent = $row['inbound_issue_sound_canceled_total'] == 0 ? 0 : $row[0]['inbound_issue_sound_canceled_total'] / $row[0]['inbound_member_total'];
			$percent = number_format($percent * 100, 2) . '%';
			$a[] = "<span class='badge badge-inverse'>".$percent."</span>";
			
			//total outbound
			$total_outbound = $row['outbound_answered_total'] + $row['outbound_canceled_total'];
			$a[] = "<span class='badge badge-success'>".$total_outbound."</span>";
			
			//outbound answered
			$a[] = "<span class='badge badge-success'>".$row['outbound_answered_total']."</span>";
			
			//outbound canceled
			//$a[] = "<span class='badge badge-success'>".$row['outbound_canceled_total']."</span>";
			
			//% outbound
			$percent = $row['outbound_answered_total'] == 0 ? 0 : $row['outbound_answered_total'] / $total_outbound;
			$percent = number_format($percent * 100, 2) . '%';
			$a[] = "<span class='badge badge-success'>".$percent."</span>";
			
			
			//% total outbound avg			
			$res = $row['outbound_answered_total_duration'] == 0 ? 0 : $row['outbound_answered_total_duration'] / $row['outbound_answered_total'];
			$res = $res == 0 ? '00:00:00' : gmdate("H:i:s", $res);
			$a[] = "<span class='badge badge-success'>".$res."</span>";

			//total sum
			$totalDays += $row['total'];
			$totalInboundAnswered += $row['agent_answer_total'];
			$totalInboundLess15 += $row['inbound_answered_less_15'];
			$totalInboundLess20 += $row['inbound_answered_less_20'];
			$totalInboundLess25 += $row['inbound_answered_less_25'];
			$totalInboundLess30 += $row['inbound_answered_less_30'];
			$totalInboundLess35 += $row['inbound_answered_less_35'];
			$totalInboundLess40 += $row['inbound_answered_less_40'];
			$totalInboundLess45 += $row['inbound_answered_less_45'];
			$totalInboundLess50 += $row['inbound_answered_less_50'];
			$totalInboundLess55 += $row['inbound_answered_less_55'];
			$totalInboundLess60 += $row['inbound_answered_less_60'];
			$totalInboundAbove60 += $row['inbound_answered_above_60'];
			$totalNs += $inbound_ns;
			$totalTme += $row['inbound_tme'];
			$totalInboundDuration += $row['agent_answer_duration_sec'];
			$totalInboundAnswerAvgTime += $row['answer_avg_time'];
			$totalInboundCanceled += $row['inbound_member_canceled_total'];
			$totalInboundCanceledAvgTime += $row['inbound_avg_time_canceled'];
			$totalInboundCanceledDuration += $row['inbound_member_canceled_total_duration'];
			$totalInboundIssueSound += $row[0]['inbound_issue_sound_canceled_total'];
			$totalInboundMember += $row[0]['inbound_member_total'];
			$totalOutbound += $total_outbound;
			$totalOutboundAnswered += $row['outbound_answered_total'];
			$totalOutboundAnsweredDuration += $row['outbound_answered_total_duration'];

			$nested[] = $a;
			
		}

		//TOTAL
		$total = array();
		$total[] = 'Total';
		$total[] = "<span class='badge badge-success'>$totalDays</span>";
		$total[] = "<span class='badge badge-success'>$totalInboundAnswered</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundLess15</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundLess20</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundLess25</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundLess30</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundLess35</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundLess40</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundLess45</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundLess50</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundLess55</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundLess60</span>";
		$total[] = "<span class='badge badge-important'>$totalInboundAbove60</span>";
		
		// total nivel serviço 
		$percent_ns = $totalInboundAnswered == 0 ? 0 : $totalNs / $totalInboundAnswered;
		$percent_ns = number_format($percent_ns * 100, 2) . '%';
		$total[] = "<span class='badge badge-success'>$percent_ns</span>";
		//% answereds		
		$percent = $totalInboundAnswered == 0 ? 0 : $totalInboundAnswered / $totalDays;
		$percent = number_format($percent * 100, 2) . '%';
		$total[] = "<span class='badge badge-success'>".$percent."</span>";
		$total[] = "<span class='badge badge-success'>".gmdate('H:i:s',$totalInboundDuration)."</span>";
		$total[] = "<span class='badge badge-important'>".gmdate('H:i:s',$totalInboundAnswerAvgTime)."</span>";
		$total[] = "<span class='badge badge-important'>".gmdate('H:i:s',$totalTme)."</span>";
		$total[] = "<span class='badge badge-important'>$totalInboundCanceled</span>";
		//% canceleds	
		$percent = $totalInboundCanceled == 0 ? 0 : $totalInboundCanceled / $totalDays;
		$percent = number_format($percent * 100, 2) . '%';
		$total[] = "<span class='badge badge-important'>".$percent."</span>";
		$total[] = "<span class='badge badge-important'>".gmdate('H:i:s',$totalInboundCanceledAvgTime)."</span>";
		$total[] = "<span class='badge badge-important'>".gmdate('H:i:s',$totalInboundCanceledDuration)."</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundIssueSound</span>";
		//% issue sound	
		$percent = $totalInboundIssueSound == 0 ? 0 : $totalInboundIssueSound / $totalInboundMember;
		$percent = number_format($percent * 100, 2) . '%';
		$total[] = "<span class='badge badge-inverse'>".$percent."</span>";
		$total[] = "<span class='badge badge-success'>".$totalOutbound."</span>";
		$total[] = "<span class='badge badge-success'>".$totalOutboundAnswered."</span>";
		//% outbound
		$percent = $totalOutboundAnswered == 0 ? 0 : $totalOutboundAnswered / $totalOutbound;
		$percent = number_format($percent * 100, 2) . '%';
		$total[] = "<span class='badge badge-success'>".$percent."</span>";	
		//% total outbound avg			
		$res = $totalOutboundAnsweredDuration == 0 ? 0 : $totalOutboundAnsweredDuration / $totalOutboundAnswered;
		$res = $res == 0 ? '00:00:00' : gmdate("H:i:s", $res);
		$total[] = "<span class='badge badge-success'>".$res."</span>";

		$nested[] = $total;

		$result_inbound_queue_answered = $totalInboundAnswered;
		$result_issue_sound = $totalInboundIssueSound;
		
		/*write file*/
		$file_sql = fopen('xml_cdr_call_center.sql','w');
		fwrite($file_sql, $tmp_file);
		fclose($file_sql);

		$nestedMonthly = array();
		$totalDays = 0;
		$totalInboundAnswered = 0;
		$totalInboundLess15 = 0;
		$totalInboundLess20 = 0;
		$totalInboundLess30 = 0;
		$totalInboundLess60 = 0;
		$totalInboundAbove60 = 0;
		$totalInboundDuration = 0;
		$totalInboundAnswerAvgTime = 0;
		$totalInboundCanceled = 0;
		$totalInboundCanceledAvgTime = 0;
		$totalInboundCanceledDuration = 0;
		$totalInboundIssueSound = 0;
		$totalOutbound = 0;
		$totalOutboundAnswered = 0;
		//periodo mensal
		foreach($result_dias_monthly as $row)
		{
			$a = array();
			
			//DAYS /*fuso*/?
			$a[] = "<span>".$row['days']."</span>";
			
			//TOTAL
			$a[] = "<span class='badge badge-success'>".$row['total']."</span>";
			
			//answereds		
			$a[] = "<span class='badge badge-success'>".$row['agent_answer_total']."</span>";

			
			$a[] = "<span class='badge badge-inverse'>".$row['inbound_answered_less_15']."</span>";

			$a[] = "<span class='badge badge-inverse'>".$row['inbound_answered_less_20']."</span>";

			$a[] = "<span class='badge badge-inverse'>".$row['inbound_answered_less_30']."</span>";

			$a[] = "<span class='badge badge-inverse'>".$row['inbound_answered_less_60']."</span>";

			$a[] = "<span class='badge badge-important'>".$row['inbound_answered_above_60']."</span>";
			
			//% answereds		
			$percent = $row['agent_answer_total'] == 0 ? 0 : $row['agent_answer_total'] / $row['total'];
			$percent = number_format($percent * 100, 2) . '%';
			$a[] = "<span class='badge badge-success'>".$percent."</span>";
		
			//answered_duration 
			$a[] = "<span class='badge badge-success'>".gmdate("H:i:s", $row['agent_answer_duration_sec'])."</span>";
			
			//answer_avg_time
			$a[] = "<span class='badge badge-important'>".gmdate("H:i:s", $row['answer_avg_time'])."</span>";
			
			//total canceled
			$a[] = "<span class='badge badge-important'>".$row['inbound_member_canceled_total']."</span>";
			
			//% canceleds	
			$percent = $row['inbound_member_canceled_total'] == 0 ? 0 : $row['inbound_member_canceled_total'] / $row['total'];
			$percent = number_format($percent * 100, 2) . '%';
			$a[] = "<span class='badge badge-important'>".$percent."</span>";
			
			//% tma canceled	
			$a[] = "<span class='badge badge-important'>".gmdate("H:i:s", $row['inbound_avg_time_canceled'])."</span>";
			
			//% canceled total duration
			$a[] = "<span class='badge badge-important'>".gmdate("H:i:s", $row['inbound_member_canceled_total_duration'])."</span>";
			
			//total issue sound
			$a[] = "<span class='badge badge-inverse'>".$row['inbound_issue_sound_canceled_total']."</span>";
			
			//% issue sound	
			$percent = $row['inbound_issue_sound_canceled_total'] == 0 ? 0 : $row[0]['inbound_issue_sound_canceled_total'] / $row[0]['inbound_member_total'];
			$percent = number_format($percent * 100, 2) . '%';
			$a[] = "<span class='badge badge-inverse'>".$percent."</span>";
			
			//total outbound
			$total_outbound = $row['outbound_answered_total'] + $row['outbound_canceled_total'];
			$a[] = "<span class='badge badge-success'>".$total_outbound."</span>";
			
			//outbound answered
			$a[] = "<span class='badge badge-success'>".$row['outbound_answered_total']."</span>";
			
			//outbound canceled
			//$a[] = "<span class='badge badge-success'>".$row['outbound_canceled_total']."</span>";
			
			//% outbound
			$percent = $row['outbound_answered_total'] == 0 ? 0 : $row['outbound_answered_total'] / $total_outbound;
			$percent = number_format($percent * 100, 2) . '%';
			$a[] = "<span class='badge badge-success'>".$percent."</span>";
			
			
			//% total outbound avg			
			$res = $row['outbound_answered_total_duration'] == 0 ? 0 : $row['outbound_answered_total_duration'] / $row['outbound_answered_total'];
			$res = $res == 0 ? '00:00:00' : gmdate("H:i:s", $res);
			$a[] = "<span class='badge badge-success'>".$res."</span>";

			//total sum
			$totalDays += $row['total'];
			$totalInboundAnswered += $row['agent_answer_total'];
			$totalInboundLess15 += $row['inbound_answered_less_15'];
			$totalInboundLess20 += $row['inbound_answered_less_20'];
			$totalInboundLess30 += $row['inbound_answered_less_30'];
			$totalInboundLess60 += $row['inbound_answered_less_60'];
			$totalInboundAbove60 += $row['inbound_answered_above_60'];
			$totalInboundDuration += $row['agent_answer_duration_sec'];
			$totalInboundAnswerAvgTime += $row['answer_avg_time'];
			$totalInboundCanceled += $row['inbound_member_canceled_total'];
			$totalInboundCanceledAvgTime += $row['inbound_avg_time_canceled'];
			$totalInboundCanceledDuration += $row['inbound_member_canceled_total_duration'];
			$totalInboundIssueSound += $row[0]['inbound_issue_sound_canceled_total'];
			$totalInboundMember += $row[0]['inbound_member_total'];
			$totalOutbound += $total_outbound;
			$totalOutboundAnswered += $row['outbound_answered_total'];
			$totalOutboundAnsweredDuration += $row['outbound_answered_total_duration'];

			$nestedMonthly[] = $a;
		}

		//TOTAL
		$total = array();
		$total[] = 'Total';
		$total[] = "<span class='badge badge-success'>$totalDays</span>";
		$total[] = "<span class='badge badge-success'>$totalInboundAnswered</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundLess15</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundLess20</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundLess30</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundLess60</span>";
		$total[] = "<span class='badge badge-important'>$totalInboundAbove60</span>";
		//% answereds		
		$percent = $totalInboundAnswered == 0 ? 0 : $totalInboundAnswered / $totalDays;
		$percent = number_format($percent * 100, 2) . '%';
		$total[] = "<span class='badge badge-success'>".$percent."</span>";
		$total[] = "<span class='badge badge-success'>".gmdate('H:i:s',$totalInboundDuration)."</span>";
		$total[] = "<span class='badge badge-important'>".gmdate('H:i:s',$totalInboundAnswerAvgTime)."</span>";
		$total[] = "<span class='badge badge-important'>$totalInboundCanceled</span>";
		//% canceleds	
		$percent = $totalInboundCanceled == 0 ? 0 : $totalInboundCanceled / $totalDays;
		$percent = number_format($percent * 100, 2) . '%';
		$total[] = "<span class='badge badge-important'>".$percent."</span>";
		$total[] = "<span class='badge badge-important'>".gmdate('H:i:s',$totalInboundCanceledAvgTime)."</span>";
		$total[] = "<span class='badge badge-important'>".gmdate('H:i:s',$totalInboundCanceledDuration)."</span>";
		$total[] = "<span class='badge badge-inverse'>$totalInboundIssueSound</span>";
		//% issue sound	
		$percent = $totalInboundIssueSound == 0 ? 0 : $totalInboundIssueSound / $totalInboundMember;
		$percent = number_format($percent * 100, 2) . '%';
		$total[] = "<span class='badge badge-inverse'>".$percent."</span>";
		$total[] = "<span class='badge badge-success'>".$totalOutbound."</span>";
		$total[] = "<span class='badge badge-success'>".$totalOutboundAnswered."</span>";
		//% outbound
		$percent = $totalOutboundAnswered == 0 ? 0 : $totalOutboundAnswered / $totalOutbound;
		$percent = number_format($percent * 100, 2) . '%';
		$total[] = "<span class='badge badge-success'>".$percent."</span>";	
		//% total outbound avg			
		$res = $totalOutboundAnsweredDuration == 0 ? 0 : $totalOutboundAnsweredDuration / $totalOutboundAnswered;
		$res = $res == 0 ? '00:00:00' : gmdate("H:i:s", $res);
		$total[] = "<span class='badge badge-success'>".$res."</span>";

		$nestedMonthly[] = $total;

		$result_inbound_queue_answeredMonthly = $totalInboundAnswered;
		$result_issue_soundMonthly = $totalInboundIssueSound;
		
		$json_data = array
		(
			"recordsTotal" 												=> intval($recordsFiltered),
			"recordsFiltered" 											=> 0,
			"recordsInboundTotal" 										=> intval($result_inbound_queue_answered),
			"recordsAnswer_duration" 									=> $result_answer_duration,
			"recordsAnswer_duration_avg" 								=> $result_answer_duration_avg,
			"recordsInboundQueueCanceled_10" 							=> intval($result_inbound_queue_canceled_10_total),
			"recordsInboundQueueCanceled_10_avg" 						=> $result_inbound_queue_canceled_10_avg_duration,
			"recordsInboundQueueCanceled_mt_10" 						=> intval($result_inbound_queue_canceled_mt_10_total),
			"recordsInboundQueueCanceled_mt_10_avg_duration" 			=> $result_inbound_queue_canceled_mt_10_avg_duration,
			"recordsIssueSound" 										=> intval($result_issue_sound),
			"recordsInboundTotalMonthly" 								=> intval($result_inbound_queue_answeredMonthly),
			"recordsAnswer_durationMonthly" 							=> $result_answer_durationMonthly,
			"recordsAnswer_duration_avgMonthly" 						=> $result_answer_duration_avgMonthly,
			"recordsInboundQueueCanceled_10Monthly" 					=> intval($result_inbound_queue_canceled_10_totalMonthly),
			"recordsInboundQueueCanceled_10_avgMonthly" 				=> $result_inbound_queue_canceled_10_avg_durationMonthly,
			"recordsInboundQueueCanceled_mt_10Monthly" 					=> intval($result_inbound_queue_canceled_mt_10_totalMonthly),
			"recordsInboundQueueCanceled_mt_10_avg_durationMonthly" 	=> $result_inbound_queue_canceled_mt_10_avg_durationMonthly,
			"recordsIssueSoundMonthly" 									=> intval($result_issue_soundMonthly),
			"result"													=> json_encode($result_dias),
			"result_monthly"											=> json_encode($result_dias_monthly),
			"data" 														=> $nested,
			"dataMonthly" 												=> json_encode($nestedMonthly)
		);
		
		echo json_encode($json_data);
?>