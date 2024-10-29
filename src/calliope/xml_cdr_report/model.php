<?php
	if (!function_exists('query_cache'))
	{
		function query_cache($db = nil, $sql = nil, $mem_key = nil, $mem_expires = nil, $arr = nil)
		{
			global $tmp_file;
			unset($query);
			
			if (strlen($mem_expires) == 0) {
				$mem_expires = 120;
			}
			$mem_expires = 1;
			
			if (strlen($mem_key) > 0) {
				$memcache = new Memcache;
				$res = $memcache->connect('localhost', 11211);
				$memcache->set('-I 128M');
				if (!$res) {
					error_log("event socket failed!");
					//die;
					$tmp_file.= "\n		/*FALHANA CONEXAO DO SOCKET MEMCACHE*/";
				}else{
					$tmp_file.= "\n		/*Conectou memcache*/";
					$tmp_file.= "\n		/*Vai consultar resultado com a chave [$mem_key]*/";
					
					/*se nao achar $switch_result vem vazio*/
					$switch_result = $memcache->get($mem_key);
					
					//$json_res = json_encode($switch_result);
					//$tmp_file.= "\n\njson_res [$json_res]\nswitch_result [$switch_result]";
					if($switch_result) {
						$query = $switch_result;
						$tmp_file.= "\n		/*ACHOU ACHOU ACHOU ACHOU com a chave [$mem_key]*/";
						//$tmp_file.= "\n		/*ACHOU ACHOU ACHOU ACHOU com a chave [$mem_key]*/";
						//$tmp_file.= "\n		/*ACHOU ACHOU ACHOU ACHOU com a chave [$mem_key]*/";
					}else{
						$tmp_file.= "\n		/*NAO ACHOU com a chave [$mem_key]*/";
						if (count($arr) > 0 ) {
							foreach ($arr as $value) {
								$tmp_file.= "\n /*vai limpar chache [".$value."]*/";
								if (strlen($value) > 0) {
									$switch_result = $memcache->delete($value);
								}else{
									$tmp_file.= "/*\n nao limpou, pois chave eh vazia*/";
								}
								$tmp_file.= "\n /*limpou chache [$value]*/";
							}
						}
					}
				}
			}else {
				$tmp_file.= "\n		/*\$mem_key vazio*/\n\n";
			}
			
			if (!$query && strlen($sql) > 0 && $res) {
				$tmp_file.= "\n\n		/*Vai realizar query da chave [$mem_key]*/";
				$prep_statement = $db->prepare($sql);
				$prep_statement->execute();
				$query = $prep_statement->fetchAll(PDO::FETCH_NAMED);
				$tmp_file.= "\n		/*REALIZOU A QUERY*/";
				$tmp_file.= "\n /*total de registros [".count($query)."]*/";
				unset($sql, $prep_statement);
				
				
				if ($query && strlen($mem_key) > 0) {
				//if (strlen($mem_key) > 0) {
					$tmp_file.= "\n		/*Vai inserir resultado com chave [$mem_key]*/";
					$switch_result = $memcache->set($mem_key, $query, 0, $mem_expires);
					
					//$tmp_file.= "\n		/*TESTE INSERÇÃO*/\n\n";
					
					$switch_result = $memcache->get($mem_key);
					
					if($switch_result) {
						//$json_res = json_encode($switch_result);
						//$tmp_file.= "\n\njson_res [$json_res]\nswitch_result [$switch_result]";
					}
					
				}else{
					$tmp_file.= "\n		/*NAO INSERIU NO MEMCACHE COM mem_key[$mem_key]*/";
				}
			}else{
				$tmp_file.= "\n		/*NAO FEZ CONSULTA NO BANCO [$mem_key]*/";
			}
			
			//close connection
			if ($res) {
				$tmp_file.= "\n		/*vai fechar conexao memcache*/\n\n";
				$memcache->close();
			}
			
		return $query;
		
		}
	}
	

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
	
	ini_set('memory_limit', '-1');
	//ini_set('max_execution_time', '0');
	ini_set('max_execution_time', 86400);

	foreach ($text as $key => $value)
	{
		$text[$key] = $value[$_SESSION['domain']['language']['code']];
	}

	require_once "resources/header.php";
	$is_jive = true;
	$is_jive = true;
	$is_jive = true;

	$requestData = $_REQUEST;
	$log.="\n".json_encode($_POST);
	
	$filter = $_POST['FILTER'];
	$cc_queue = $_POST['CC_QUEUE'];
	$cc_agent = $_POST['CC_AGENT'];
	$start_stamp_begin = $_POST['DATA_INI'];
	$start_stamp_end = $_POST['DATA_END'];
	$direction = ($_POST['DIRECTION']);
	if ( strlen($direction[0]) == 0 ) {
		unset($direction);
	}
	$direction_tmp = implode(",", $direction);
	$log.= "\ndirection[".json_encode($direction)."]";
	$caller_id_number = $_POST['CALLER_ID_NUMBER'];
	$destination_number = $_POST['DESTINATION_NUMBER'];
	$extension = $_POST['EXTENSION'];
	$finalization = $_POST['FINALIZATION'];
	$transferred = $_POST['TRANSFERRED'];
	$uuid_b_originator = $_POST['uuid_b_originator'];
	//$log.= "\ntransferred[$transferred]";
	
	
	
	/*$key for memcache*/
	$main_key = "xml_cdr_report";
	$main_key.= ":$start_stamp_begin~$start_stamp_end~$direction_tmp~$caller_id_number~$destination_number~$extension~$finalization~$transferred";
	$tmp_file.= "/*";
	$tmp_file.= "\n\$main_key [$main_key]";
	$tmp_file.= "\nkey LENGTH [".strlen($main_key)."]";
	$tmp_file.= "\n*/\n\n";
	$memcache_expires = 60;
	
	$file_sql = fopen('requestData.txt','w');
	fwrite($file_sql, $log);
	fclose($file_sql);
	
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
	
	if (strlen($start_stamp_end) == 0)
	{
		$start_stamp_end = date('Y-m-d');
		$tmp_start_stamp_end = date('Y-m-d 23:59:59');
		$start_stamp_end_epoch = strtotime($tmp_start_stamp_end);			
	}
	else
	{
		$start_stamp_end_epoch = strtotime($start_stamp_end);
	}
	
	//error_log("start_stamp_begin ..: " . $start_stamp_begin);
	//error_log("start_stamp_end   ..: " . $start_stamp_end);
	
	$dbname = "cdr_$domain_uuid";
	
	
	$sql = "\n
			/*## MAIN ##*/
			DROP TABLE IF EXISTS tmp_tbl;
			SET @is_ext = true; 
			SET @cnt = 0;
			CREATE TEMPORARY TABLE tmp_tbl AS
			SELECT
				 uuid
				,bridge_uuid
				,direction
				,direction_r
				,sofia_profile_name	
				,destination_number
				,sip_to_user
				,caller_id_number
				,dialed_user	
				,FROM_UNIXTIME(start_epoch)
				,start_epoch
				,originator
				,call_back_params
				,answer_epoch	
			FROM (	
				SELECT 
					 m_a.uuid
					,m_a.bridge_uuid
					,m_a.direction
					,m_a.sofia_profile_name	
					,m_a.destination_number
					,m_a.sip_to_user
					,m_a.caller_id_number
					,m_a.dialed_user	
					,FROM_UNIXTIME(m_a.start_epoch)
					,m_a.start_epoch
					,m_a.originator
					,m_a.call_back_params
					,m_a.answer_epoch
					
					,CASE
					
						/*RAMAL -> PUBLICA is_ext*/
						WHEN  m_a.direction  = 'inbound' AND @is_ext AND LENGTH(m_a.destination_number) >= 8 AND LENGTH(m_a.caller_id_number ) <= 7 /*RAMAL*/ THEN 'outbound_ext_pbx'
						/*RAMAL -> PUBLICA*/
						WHEN  m_a.direction  = 'inbound' AND NOT @is_ext AND LENGTH(m_a.sip_to_user ) >= 8 AND LENGTH(m_a.caller_id_number ) <= 7 /*RAMAL*/ THEN 'outbound_ext_pbx'
						
						
						/*PUBLICA -> PABX is_ext*/
						WHEN  m_a.direction  = 'inbound' AND @is_ext AND LENGTH(m_a.destination_number) >= 8 THEN 'inbound_car_pbx'
						/*PUBLICA -> PABX is_ext*/
						WHEN  m_a.direction  = 'inbound' AND @is_ext AND LENGTH(m_a.sip_from_user) >= 8 AND LENGTH(m_a.sip_from_user) >= 8 AND LENGTH(m_a.sip_to_user) >= 8 THEN 'inbound_car_pbx'
						/*PUBLICA -> PABX*/
						WHEN  m_a.direction  = 'inbound' AND NOT @is_ext AND LENGTH(m_a.sip_to_user ) >= 8 THEN 'inbound_car_pbx'
						
						/*RAMAL/PUBLICA -> PABX caso de chipeira com user <7, porém caller >= 8*/
						WHEN  m_a.direction  = 'inbound' AND @is_ext AND LENGTH(m_a.caller_id_number) >= 8 THEN 'inbound_car_pbx'
						
						
						/*RAMAL -> PABX is_ext*/
						WHEN  m_a.direction  = 'inbound' AND @is_ext AND LENGTH(m_a.destination_number) <= 7 AND (LENGTH(m_a.sip_from_user) <= 7 OR m_a.sip_from_user IS NULL) /*RAMAL*/ /*AND m_a.caller_id_number <> 'unknown'*/ THEN 'local_ext_pbx'
						/*RAMAL -> PABX*/
						WHEN  m_a.direction  = 'inbound' AND NOT @is_ext AND LENGTH(m_a.sip_to_user ) <= 7 AND (LENGTH(m_a.sip_from_user ) <= 7  OR m_a.sip_from_user IS NULL)/*RAMAL*/ /*AND m_a.caller_id_number <> 'unknown'*/ THEN 'local_ext_pbx'


						/*PABX -> PUBLICA is_ext*/
						/*u:e75f270d-9cea-4bf9-23ee-e171f12cd321 RAMAL VERTO*/
						WHEN  m_a.direction  = 'outbound' AND @is_ext AND LENGTH(m_a.destination_number) >= 8 AND LENGTH(m_a.destination_number) <> 38 THEN 'outbound_pbx_car' 
						/*PABX -> PUBLICA*/
						WHEN  m_a.direction  = 'outbound' AND (LENGTH(m_a.sip_to_user ) >= 8 OR LENGTH(m_a.destination_number) >= 8) AND LENGTH(m_a.destination_number) <> 38 /*PABX PUBLICA*/ THEN 'outbound_pbx_car'
						
						/*PABX -> RAMAL*/
						WHEN  m_a.direction  = 'outbound' THEN 'inbound_pbx_ext'
						
						/*PUBLICA -> PABX (uma chipeira registrada), e deu false nas regras acima*/
						WHEN  m_a.direction  = 'inbound' AND @is_ext AND LENGTH(m_a.caller_id_number) >= 8 THEN 'inbound_car_pbx'
						
					ELSE 'NONE'
					END direction_r
						
				FROM
						`$dbname`.cdr_main m_a
						FORCE INDEX (main_idx)
				WHERE TRUE
				AND m_a.start_epoch BETWEEN  $start_stamp_begin_epoch AND $start_stamp_end_epoch
				/*remove callback*/
				AND m_a.call_back_params IS null
			)a
			WHERE TRUE";
			
	if (count($direction) == 1)
	{
		
		if (in_array('inbound', $direction)) {
			$sql.="\n\n	/*INBOUND*/";
			$sql.="
				/*Incluido transferencia *1*/
				AND direction_r IN ('inbound_car_pbx', 'inbound_pbx_ext')";
		}
		
		if (in_array('outbound', $direction)){
			$sql.="\n\n	/*OUTBOUND*/";
			$sql.="\n	AND direction_r IN ('outbound_pbx_car', 'outbound_ext_pbx')";
		}
		
		if (in_array('internal', $direction)) {
			$sql.="\n\n	/*INTERNAL*/";
			$sql.="\n	AND direction_r IN ('local_ext_pbx')";
		}
		
	}
	
	if (count($direction) == 2)
	{
		
		if ( in_array('inbound', $direction) && in_array('outbound', $direction) )
		{
			$sql.="\n\n		/*INBOUND OUTBOUND*/";
			$sql.="\n		AND direction_r IN ('inbound_car_pbx', 'outbound_pbx_car', 'outbound_ext_pbx')";
		}
	}
	
	if (count($direction) == 2)
	{
		
		if ( in_array('inbound', $direction) && in_array('internal', $direction) )
		{
			$sql.="\n\n		/*INBOUND, INTERNAL*/";
			$sql.="\n			AND direction_r IN ('inbound_car_pbx', 'local_ext_pbx')";
		}
		
	}
	
	
	if (count($direction) == 2)
	{
		
		if ( in_array('outbound', $direction) && in_array('internal', $direction) )
		{
			$sql.="\n\n		/*OUTBOUND*/";
			$sql.="\n	AND direction_r IN ('outbound_pbx_car', 'local_ext_pbx', 'outbound_ext_pbx')";
		}
		
	}
	
	
	if ( count($direction) == 0 )
	{
		$direction = array("inbound", "outbound", "internal");
	}
	
	
	if (strlen($caller_id_number) > 0)
	{
		if (preg_match("/^[*%]|[*%]$/", $caller_id_number, $matches)) 
		{
			$sql.="\n	AND caller_id_number like '%".numbers_only($caller_id_number)."%'";
		}
		else
		{
			$sql.="\n	AND caller_id_number = '".numbers_only($caller_id_number)."'";
		}
	}
	
	if (strlen($destination_number) > 0) 
	{
		if ( in_array('internal', $direction) && count($direction) == 1)
		{
			if (preg_match("/^[*%]|[*%]$/", $destination_number, $matches)) 
			{
				$sql.="\n	AND destination_number like '%".numbers_only($destination_number)."%'";
			}
			else
			{
				$sql.="\n	AND destination_number = '".numbers_only($destination_number)."'";
			}
			
		}
		else
		{
			if (preg_match("/^[*%]|[*%]$/", $destination_number, $matches)) 
			{
				$sql.="\n	AND (sip_to_user like '%".numbers_only($destination_number)."%' or destination_number like '%".numbers_only($destination_number)."%' or dialed_user like '%".numbers_only($destination_number)."%')";
			}
			else
			{
				//$sql.="\n	AND sip_to_user = '".numbers_only($destination_number)."'";
				$sql.="\n	AND (sip_to_user = '".numbers_only($destination_number)."' OR destination_number = '".numbers_only($destination_number)."' OR dialed_user = '".numbers_only($destination_number)."')";
			}
		}
	}
	
	$sql.= ";";
	
	//NÃO FAZER OS TOTAIS EM CASO DE FILTROS DEPOIS DEPOIS DA QUERY DE JOIN
	//ALGUNS FILTROS SÃO POSSÍVEIS NA TABELA MAIN, O Q A TORNA MUITO MAIS RÁPIDA, OUTROS FILTROS APENAS SÃO POSSÍVEIS 
	//DEPOIS DE TODOS OS JOINS....ENTÃO, PARA ESTES CASOS PRECISAMOS REFAZER OS COUNTS.
	
	
	/*TABELAS TEMPORÁRIAS*/
	$sql.= "\n\n
			/*TABELAS TEMPORÁRIAS*/
			DROP TABLE IF EXISTS tmp_tbl_totais;
			CREATE TEMPORARY TABLE tmp_tbl_totais AS 			
			SELECT * FROM tmp_tbl
			/*LIMIT 10000*/;

			DROP TABLE IF EXISTS tmp_tbl_inbound;
			CREATE TEMPORARY TABLE tmp_tbl_inbound AS 			
			SELECT * FROM tmp_tbl;

			DROP TABLE IF EXISTS tmp_outbound_originator;
			CREATE TEMPORARY TABLE tmp_outbound_originator AS 			
			SELECT * FROM tmp_tbl;

			DROP TABLE IF EXISTS tmp_outbound_wo_originator;
			CREATE TEMPORARY TABLE tmp_outbound_wo_originator AS 			
			SELECT * FROM tmp_tbl;

			DROP TABLE IF EXISTS tmp_local_wo_originator;
			CREATE TEMPORARY TABLE tmp_local_wo_originator AS 			
			SELECT * FROM tmp_tbl;

			DROP TABLE IF EXISTS tmp_outbound_bridge;
			CREATE TEMPORARY TABLE tmp_outbound_bridge AS 			
			SELECT * FROM tmp_tbl;

			DROP TABLE IF EXISTS tmp_tbl_callback;
			CREATE TEMPORARY TABLE tmp_tbl_callback AS 			
			SELECT * FROM tmp_tbl;";

	
	
	/*SELECT A USANDO AS TEMPORÁRIAS*/
	$sql.= "\n\n\n\n
	/*## SELECT A PARTIR DAS TEMPORÁRIAS ##*/
	DROP TABLE IF EXISTS tmp_tbl_xml_cdr_report;
	CREATE TEMPORARY TABLE tmp_tbl_xml_cdr_report AS 			
			
			
SELECT * FROM	
( 	
	SELECT
		(@cnt := @cnt + 1) AS rowNumber, c.* FROM 
	(
			
		/*inbound, pq outbound usa originator para linkar as legs*/
		SELECT
			'1' AS TAG
			,v_a.uuid uuid_a
			,r_b.uuid uuid_b
			,r_b_L_B.uuid uuid_L_B
			,r_b_originator.uuid uuid_b_originator
			,r_a.call_back_params is_callback_a
			,r_b.call_back_params is_callback_b
	
			,CASE 
				WHEN r_a.direction = 'inbound'  AND LENGTH(m_a_caller_id_number) >= 8 AND r_b.uuid IS NOT NULL THEN 'TRUE' /*inbound*/
				WHEN r_a.direction = 'inbound'  AND LENGTH(m_a_caller_id_number) >= 8 AND r_b.uuid IS NULL THEN 'FALSE' /*inbound*/
				WHEN r_a.direction = 'outbound' AND LENGTH(m_a_caller_id_number) >= 8 AND r_b.answer_epoch > 0 THEN 'TRUE' /*outbound*/
				WHEN r_a.direction = 'outbound' AND LENGTH(m_a_caller_id_number) >= 8 THEN 'FALSE' /*outbound*/			
			END call_answered
			
			,'inbound' direction
			,direction_r
			,r_a.sofia_profile_name
			,v_a.caller_id_name caller_id_name_a
			,v_a.caller_id_number AS caller_id_number_a
			
			,CASE 
				WHEN v_a.sip_to_user is NULL THEN v_a.destination_number 
			ELSE v_a.sip_to_user
			END destination_number_a
			
			,CASE
				WHEN ((`v_b`.`pick_num` IS NOT NULL) OR (`v_b`.`picked_up_uuid` IS NOT NULL) OR (`v_b`.`pickup` IS NOT NULL)) THEN if (SUBSTR(`v_b`.`sip_from_user`, 1, 2) = 'u:', v_b.dialed_user, v_b.sip_from_user)
				WHEN (`v_b`.`last_app` = 'att_xfer') THEN if (SUBSTR(`v_b`.`sip_to_user`, 1, 2) = 'u:', v_b.dialed_user, v_b.sip_to_user)
			ELSE if (SUBSTR(v_b.destination_number, 1, 2) = 'u:', v_b.dialed_user, v_b.destination_number)
			END AS `extension`
			
			,CASE
				when v_b.last_app = 'transfer' then 'BLIND'
				when v_b.last_app = 'att_xfer' then 'ATT_TRANSFER'
				
				/*casos de *4 com loopback*/
				when r_b_originator.uuid IS NOT NULL AND v_b_originator.last_app = 'transfer' then 'BLIND'
				
				/*é a leg do ramal q fez transferencia *4*/				
				when r_b_originator.uuid IS NOT NULL AND v_b_originator.last_app = 'att_xfer' then 'ATT_TRANSFER'
			ELSE NULL
			END call_transferred
			
			,CASE				
				/*transferencia *4 com Loopback*/
				WHEN r_b_originator.uuid IS NOT NULL AND v_b_originator.last_app = 'att_xfer' and v_b_L_B.caller_id_number is not null then CONCAT('De: ', v_b_L_B.caller_id_number)
				
				WHEN r_b_originator.uuid IS NOT NULL AND v_b_originator.last_app = 'att_xfer' then CONCAT('De: ', v_b.caller_id_number)
				WHEN v_b.last_app = 'transfer' then CONCAT('Para: ', v_b.digits)
				WHEN v_b.last_app = 'att_xfer' then CONCAT('Para: ', v_b.digits)
				WHEN r_b_originator.uuid IS NOT NULL AND v_b_originator.last_app = 'att_xfer' then CONCAT('De: ', v_b.sip_from_user)
				
			ELSE NULL
			END call_transferred_to

			
			,r_a.start_epoch start_epoch_a
			,r_a.answer_epoch answer_epoch_a
			,FROM_UNIXTIME(r_a.start_epoch) start_stamp_a
			,'TRUE' AS answered_carrier
			,r_b.start_epoch start_epoch_b
			,r_b.answer_epoch answer_epoch_b
			,(r_b.end_epoch - r_b.answer_epoch) AS answer_duraction_b
			,r_a.end_epoch as end_epoch_a
			,r_b.end_epoch as end_epoch_b
			,v_a.hangup_cause
			
			,CASE
				WHEN (v_a.sip_hangup_disposition = 'send_bye' AND r_b.uuid IS NULL ) THEN 'PABX'
				WHEN (v_a.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_a.sip_hangup_disposition = 'recv_bye') THEN 'PUBLIC NETWORK'
				WHEN (v_a.sip_hangup_disposition = 'send_refuse') THEN 'PABX'
				WHEN (v_a.sip_hangup_disposition = 'recv_cancel') THEN 'PUBLIC NETWORK'
				WHEN (v_b.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_b.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_b.sip_hangup_disposition = 'recv_bye') THEN 'PUBLIC NETWORK'
				WHEN v_a.sip_hangup_disposition IS NOT NULL THEN v_a.sip_hangup_disposition
			ELSE ''
			END hangup_side
			
			,'' gateway_name
			,v_a.protocol protocol
			
			/*recordings*/
			,v_a.cc_record_filename AS `cc_record_filename_a`
			,v_a.record_session AS `record_session_a`
			
			/*Se for *4 com loopback:*/
			,CASE 
				WHEN v_b_L_B.record_session IS NOT NULL THEN v_b_L_B.record_session
			ELSE v_b.record_session
			END record_session_b
			
		FROM 
		(	
			SELECT
				 m_a.uuid uuid_a
				,m_a.bridge_uuid b_uuid_b
				,m_a.originator o_uuid_b	
				,m_a.caller_id_number m_a_caller_id_number
				,FROM_UNIXTIME(m_a.start_epoch)
				,m_a.direction_r
			FROM 
				tmp_tbl_inbound m_a
			WHERE TRUE";

if (in_array("inbound", $direction))
{
	$sql.="\n				AND 1";
}
else
{
	$sql.="\n				AND 0";
}
			
if ($is_jive)
{
	$sql.="\n				AND LENGTH(m_a.caller_id_number ) >= 8";
	$sql.="\n				AND m_a.direction = 'inbound'";
}
else
{
	$sql.="\n				AND m_a.direction = 'inbound' AND m_a.sofia_profile_name = 'external'";
}

$sql.="\n		)a
		INNER JOIN `$dbname`.cdr_refer_uuid r_a ON r_a.uuid = uuid_a
		LEFT  JOIN `$dbname`.cdr_variables  v_a ON v_a.uuid = uuid_a
		left  JOIN `$dbname`.cdr_refer_uuid r_b ON r_b.uuid = b_uuid_b
		LEFT  JOIN `$dbname`.cdr_variables v_b ON v_b.uuid = b_uuid_b
		
		/* *4 com loopback */
		
		/*encontra a leg B, que faz parse no dialplan*/
		LEFT JOIN `$dbname`.cdr_refer_uuid r_b_L_B ON r_b_L_B.uuid = r_b.other_loopback_leg_uuid /*loopbback Leg A*/
		LEFT JOIN `$dbname`.cdr_variables v_b_L_B ON v_b_L_B.uuid = r_b.other_loopback_leg_uuid /*loopbback Leg A*/
		
		/*encontra a leg do origiantor, que eh onde terá os dados de transferencia*/
		LEFT JOIN `$dbname`.cdr_refer_uuid r_b_originator ON r_b_originator.uuid = r_b.originator /*loopbback Leg A*/
		
		/*encontra as variaveis para ver o tipo de transferencia*/
		LEFT JOIN `$dbname`.cdr_variables v_b_originator ON v_b_originator.uuid = r_b.originator /*loopbback Leg A*/
			
		UNION
		
		
		/*transferencia *1 */
		/*transferencia *tb blind do microsip*/
		SELECT
			'1.1' AS TAG
			,v_a.uuid uuid_a
			,r_b.uuid uuid_b
			,r_b_L_B.uuid uuid_L_B
			,r_b_originator.uuid uuid_b_originator
			,r_a.call_back_params is_callback_a
			,r_b.call_back_params is_callback_b
	
			,CASE 
				WHEN r_a.direction = 'inbound'  AND LENGTH(m_a.caller_id_number) >= 8 AND r_b.uuid IS NOT NULL THEN 'TRUE' /*inbound*/
				WHEN r_a.direction = 'inbound'  AND LENGTH(m_a.caller_id_number) >= 8 AND r_b.uuid IS NULL THEN 'FALSE' /*inbound*/
				WHEN r_a.direction = 'outbound' AND LENGTH(m_a.caller_id_number) >= 8 AND r_b.answer_epoch > 0 THEN 'TRUE' /*outbound*/
				WHEN r_a.direction = 'outbound' AND LENGTH(m_a.caller_id_number) >= 8 THEN 'FALSE' /*outbound*/			
			END call_answered
			
			,'inbound' direction
			
			/*forço o inbound_car_pbx, pois vem inbound_pbx_ext*/
			,'inbound_car_pbx' direction_r
			,r_a.sofia_profile_name
			,v_a.caller_id_name caller_id_name_a
			,v_a.caller_id_number AS caller_id_number_a
			
			,CASE 
				WHEN v_a.sip_to_user is NULL THEN v_a.destination_number 
			ELSE v_a.sip_to_user
			END destination_number_a
			
			,CASE
				WHEN ((`v_b`.`pick_num` IS NOT NULL) OR (`v_b`.`picked_up_uuid` IS NOT NULL) OR (`v_b`.`pickup` IS NOT NULL)) THEN if (SUBSTR(`v_b`.`sip_from_user`, 1, 2) = 'u:', v_b.dialed_user, v_b.sip_from_user)
				WHEN (`v_b`.`last_app` = 'att_xfer') THEN if (SUBSTR(`v_b`.`sip_to_user`, 1, 2) = 'u:', v_b.dialed_user, v_b.sip_to_user)
			ELSE if (SUBSTR(v_b.destination_number, 1, 2) = 'u:', v_b.dialed_user, v_b.destination_number)
			END AS `extension`
			
			,CASE
				/* *1 */
				when v_b.last_app = 'transfer' then 'BLIND'
				when v_b.last_app = 'att_xfer' then 'ATT_TRANSFER'
				
				/*casos de *4 com loopback*/
				when r_b_originator.uuid IS NOT NULL AND v_b_originator.last_app = 'transfer' then 'BLIND'
				when r_b_originator.uuid IS NOT NULL AND v_b_originator.last_app = 'att_xfer' then 'ATT_TRANSFER'
				
				/* BLIND microsip */
				WHEN SUBSTRING_INDEX(v_b.transfer_destination ,':', 1) = 'blind' THEN 'BLIND'
			ELSE NULL
			END call_transferred
			
			/* *1 E BLIND VIA MICROSIP*/
			,CASE
				/*blind:383a6035-ca00-4cfa-ba13-551b71f0b6ee*/
				WHEN v_b.transfer_to IS NOT NULL THEN SUBSTRING_INDEX(v_b.transfer_destination ,':', -1)
			/*-bleg 9998 XML dmzbr.vocom.global*/
			ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(v_b.transfer_destination ,' ', 2),' ', -1)
			END call_transferred_to
		
			
			,r_a.start_epoch start_epoch_a
			,r_a.answer_epoch answer_epoch_a
			,FROM_UNIXTIME(r_a.start_epoch) start_stamp_a
			,'TRUE' AS answered_carrier
			,r_b.start_epoch start_epoch_b
			,r_b.answer_epoch answer_epoch_b
			,(r_b.end_epoch - r_b.answer_epoch) AS answer_duraction_b
			,r_a.end_epoch as end_epoch_a
			,r_b.end_epoch as end_epoch_b
			,v_a.hangup_cause
			
			,CASE
				WHEN (v_a.sip_hangup_disposition = 'send_bye' AND r_b.uuid IS NULL ) THEN 'PABX'
				WHEN (v_a.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_a.sip_hangup_disposition = 'recv_bye') THEN 'PUBLIC NETWORK'
				WHEN (v_a.sip_hangup_disposition = 'send_refuse') THEN 'PABX'
				WHEN (v_a.sip_hangup_disposition = 'recv_cancel') THEN 'PUBLIC NETWORK'
				WHEN (v_b.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_b.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_b.sip_hangup_disposition = 'recv_bye') THEN 'PUBLIC NETWORK'
				WHEN v_a.sip_hangup_disposition IS NOT NULL THEN v_a.sip_hangup_disposition
			ELSE ''
			END hangup_side
			
			,'' gateway_name
			,v_a.protocol protocol
			,v_a.cc_record_filename AS `cc_record_filename_a`
			,v_a.record_session AS `record_session_a`
			,v_b.record_session AS `record_session_b`
		FROM 
		(	
			SELECT
				 m_b.uuid uuid_b
				,m_b.bridge_uuid b_uuid_a
				-- ,m_a.originator o_uuid_b	
				-- ,m_a.caller_id_number m_a_caller_id_number
				,FROM_UNIXTIME(m_b.start_epoch)
				-- ,m_b.direction_r
				-- SELECT *
				,m_b.*
			FROM 
				tmp_tbl_inbound m_b
			WHERE TRUE";

if (in_array("inbound", $direction))
{
	$sql.="\n				AND 1";
}
else
{
	$sql.="\n				AND 0";
}

if (strlen($uuid_b_originator) > 0)
{
	$sql.="
				AND 0";
}
			
if ($is_jive)
{
	$sql.="\n				AND m_b.direction_r = 'inbound_pbx_ext'";
}
else
{
	$sql.="\n				AND m_a.direction = 'inbound' AND m_a.sofia_profile_name = 'external'";
}

$sql.="\n		)a
		INNER JOIN `$dbname`.cdr_refer_uuid r_b ON r_b.uuid = uuid_b
		LEFT  JOIN `$dbname`.cdr_variables  v_b ON v_b.uuid = uuid_b
		LEFT  JOIN `$dbname`.cdr_main m_a ON m_a.uuid = b_uuid_a
		left  JOIN `$dbname`.cdr_refer_uuid r_a ON r_a.uuid = b_uuid_a
		LEFT  JOIN `$dbname`.cdr_variables v_a ON v_a.uuid = b_uuid_a
		
		
		/* *4 com loopback */
		
		/*encontra a leg B, que faz parse no dialplan*/
		LEFT JOIN `$dbname`.cdr_refer_uuid r_b_L_B ON r_b_L_B.uuid = r_b.other_loopback_leg_uuid /*loopbback Leg A*/
		
		/*encontra a leg do origiantor, que eh onde terá os dados de transferencia*/
		LEFT JOIN `$dbname`.cdr_refer_uuid r_b_originator ON r_b_originator.uuid = r_b.originator /*loopbback Leg A*/
		
		/*encontra as variaveis para ver o tipo de transferencia*/
		LEFT JOIN `$dbname`.cdr_variables v_b_originator ON v_b_originator.uuid = r_b.originator /*loopbback Leg A*/
		
		WHERE TRUE	
			AND v_b.transfer_destination IS NOT NULL
		
			
		UNION 
		
		
		/*
			*4
			Cliente -> 9999 -> *4 para 9998
			O relatório (tag 1) exibe cliente -> 9998 com áudio completo 
			Este aqui exibe do Cliente -> 9999
		*/
		SELECT
			'1.2' AS TAG
			,v_a.uuid uuid_a
			,r_b.uuid uuid_b
			,r_b_L_B.uuid uuid_L_B
			,r_b_originator.uuid uuid_b_originator
			,r_a.call_back_params is_callback_a
			,r_b.call_back_params is_callback_b
	
			,CASE 
				WHEN r_a.direction = 'inbound'  AND LENGTH(m_a.caller_id_number) >= 8 AND r_b.uuid IS NOT NULL THEN 'TRUE' /*inbound*/
				WHEN r_a.direction = 'inbound'  AND LENGTH(m_a.caller_id_number) >= 8 AND r_b.uuid IS NULL THEN 'FALSE' /*inbound*/
				WHEN r_a.direction = 'outbound' AND LENGTH(m_a.caller_id_number) >= 8 AND r_b.answer_epoch > 0 THEN 'TRUE' /*outbound*/
				WHEN r_a.direction = 'outbound' AND LENGTH(m_a.caller_id_number) >= 8 THEN 'FALSE' /*outbound*/			
			END call_answered
			
			,'inbound' direction
			
			/*forço o inbound_car_pbx, pois vem inbound_pbx_ext*/
			,'inbound_car_pbx' direction_r
			,r_a.sofia_profile_name
			,v_a.caller_id_name caller_id_name_a
			,v_a.caller_id_number AS caller_id_number_a
			
			,CASE 
				WHEN v_a.sip_to_user is NULL THEN v_a.destination_number 
			ELSE v_a.sip_to_user
			END destination_number_a
			
			,CASE
				WHEN ((`v_b`.`pick_num` IS NOT NULL) OR (`v_b`.`picked_up_uuid` IS NOT NULL) OR (`v_b`.`pickup` IS NOT NULL)) THEN if (SUBSTR(`v_b`.`sip_from_user`, 1, 2) = 'u:', v_b.dialed_user, v_b.sip_from_user)
				WHEN (`v_b`.`last_app` = 'att_xfer') THEN if (SUBSTR(`v_b`.`sip_to_user`, 1, 2) = 'u:', v_b.dialed_user, v_b.sip_to_user)
			ELSE if (SUBSTR(v_b.destination_number, 1, 2) = 'u:', v_b.dialed_user, v_b.destination_number)
			END AS `extension`
			
			,CASE
				/* *1 */
				when v_b.last_app = 'transfer' then 'BLIND'
				
				/* *4 */
				when v_b.last_app = 'att_xfer' then 'ATT_TRANSFER'
				
				/*casos de *4 com loopback*/
				when r_b_originator.uuid IS NOT NULL AND v_b_originator.last_app = 'transfer' then 'BLIND'
				when r_b_originator.uuid IS NOT NULL AND v_b_originator.last_app = 'att_xfer' then 'ATT_TRANSFER'
				
				/* BLIND microsip */
				WHEN SUBSTRING_INDEX(v_b.transfer_destination ,':', 1) = 'blind' THEN 'BLIND'
			ELSE NULL
			END call_transferred
			
			
			/* *1 E BLIND VIA MICROSIP*/
			,CASE
				/* *4 */
				WHEN v_b.last_app = 'att_xfer' THEN CONCAT('Para: ', v_b.digits)
				
				
				/*blind:383a6035-ca00-4cfa-ba13-551b71f0b6ee*/
				WHEN v_b.transfer_to IS NOT NULL THEN SUBSTRING_INDEX(v_b.transfer_destination ,':', -1)
			/*-bleg 9998 XML dmzbr.vocom.global*/
			ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(v_b.transfer_destination ,' ', 2),' ', -1)
			END call_transferred_to
		
			
			,r_a.start_epoch start_epoch_a
			,r_a.answer_epoch answer_epoch_a
			,FROM_UNIXTIME(r_a.start_epoch) start_stamp_a
			,'TRUE' AS answered_carrier
			,r_b.start_epoch start_epoch_b
			,r_b.answer_epoch answer_epoch_b
			,(r_b.end_epoch - r_b.answer_epoch) AS answer_duraction_b
			,r_a.end_epoch as end_epoch_a
			,r_b.end_epoch as end_epoch_b
			,v_a.hangup_cause
			
			,CASE
				WHEN (v_a.sip_hangup_disposition = 'send_bye' AND r_b.uuid IS NULL ) THEN 'PABX'
				WHEN (v_a.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_a.sip_hangup_disposition = 'recv_bye') THEN 'PUBLIC NETWORK'
				WHEN (v_a.sip_hangup_disposition = 'send_refuse') THEN 'PABX'
				WHEN (v_a.sip_hangup_disposition = 'recv_cancel') THEN 'PUBLIC NETWORK'
				WHEN (v_b.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_b.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_b.sip_hangup_disposition = 'recv_bye') THEN 'PUBLIC NETWORK'
				WHEN v_a.sip_hangup_disposition IS NOT NULL THEN v_a.sip_hangup_disposition
			ELSE ''
			END hangup_side
			
			,'' gateway_name
			,v_a.protocol protocol
			,v_a.cc_record_filename AS `cc_record_filename_a`
			,v_a.record_session AS `record_session_a`
			
			,CASE 
				/*CASO DE * *4 */
				WHEN v_b.last_app = 'att_xfer' THEN v_b.record_session
			ELSE v_c.record_session 
			END record_session_b
			
		FROM 
		(	
			SELECT
				 m_b.uuid uuid_b
				/*,m_b.bridge_uuid b_uuid_a*/
				,m_b.bridge_uuid b_uuid_b
				,m_b.originator b_uuid_a
				-- ,m_a.originator o_uuid_b	
				-- ,m_a.caller_id_number m_a_caller_id_number
				,FROM_UNIXTIME(m_b.start_epoch)
				-- ,m_b.direction_r
				-- SELECT *
				,m_b.*
			FROM 
				tmp_tbl_inbound m_b
			WHERE TRUE";

if (in_array("inbound", $direction))
{
	$sql.="\n				AND 1";
}
else
{
	$sql.="\n				AND 0";
}
if (strlen($uuid_b_originator) > 0)
{
	$sql.="
				AND m_b.uuid = '$uuid_b_originator'";
}
else
{
	$sql.="
				AND 0";
}

if ($is_jive)
{
	$sql.="\n				AND m_b.direction_r = 'inbound_pbx_ext'";
}
else
{
	$sql.="\n				AND m_a.direction = 'inbound' AND m_a.sofia_profile_name = 'external'";
}

$sql.="\n		)a
		INNER JOIN `$dbname`.cdr_refer_uuid r_b ON r_b.uuid = uuid_b
		LEFT  JOIN `$dbname`.cdr_variables  v_b ON v_b.uuid = uuid_b
		LEFT  JOIN `$dbname`.cdr_main m_a ON m_a.uuid = b_uuid_a
		left  JOIN `$dbname`.cdr_refer_uuid r_a ON r_a.uuid = b_uuid_a
		LEFT  JOIN `$dbname`.cdr_variables v_a ON v_a.uuid = b_uuid_a
		
		
		/*gravacao*/
		LEFT  JOIN `$dbname`.cdr_variables v_c ON v_c.uuid = b_uuid_b
	
		
		
		/* *4 com loopback */
		
		/*encontra a leg B, que faz parse no dialplan*/
		LEFT JOIN `$dbname`.cdr_refer_uuid r_b_L_B ON r_b_L_B.uuid = r_b.other_loopback_leg_uuid /*loopbback Leg A*/
		
		/*encontra a leg do origiantor, que eh onde terá os dados de transferencia*/
		LEFT JOIN `$dbname`.cdr_refer_uuid r_b_originator ON r_b_originator.uuid = r_b.originator /*loopbback Leg A*/
		
		/*encontra as variaveis para ver o tipo de transferencia*/
		LEFT JOIN `$dbname`.cdr_variables v_b_originator ON v_b_originator.uuid = r_b.originator /*loopbback Leg A*/
		
		WHERE TRUE	
			/*AND v_b.transfer_destination IS NOT NULL*/
		
		
			
		UNION 		

		
		/*outbound, pq usa originator para linkar as legs*/

		SELECT
			'2' AS TAG
			,r_a.uuid uuid_a
			,r_b.uuid uuid_b
			,'' uuid_L_B
			,'' uuid_b_originator
			,r_b.call_back_params is_callback_b
			,r_a.call_back_params is_callback_a
	
			
			,CASE 
				WHEN r_b.answer_epoch > 0 THEN 'TRUE' /*outbound*/
			ELSE 'FALSE'
			END call_answered
		
		
			/*r_b.direction*/
			,CASE";
				/*fazer o outbound_bridge da Jive*/
				
if ($is_jive)
{
	//$sql.="\n				WHEN LENGTH(v_b.caller_id_number) >= 8 AND LENGTH(v_b.destination_number ) >= 8 THEN 'outbound_bridge'";
	$sql.="\n				WHEN FALSE AND LENGTH(v_b.caller_id_number) >= 8 AND LENGTH(v_b.destination_number ) >= 8 THEN 'outbound_bridge'";
}
else
{
	$sql.="\n				WHEN r_a.sofia_profile_name = 'external' AND r_b.sofia_profile_name = 'external' THEN 'outbound_bridge'";
}	
				
				
$sql.="\n			ELSE 'outbound'
			END AS direction
			,direction_r
			
			,r_b.sofia_profile_name
			,v_a.caller_id_name caller_id_name_a
			
			
			,CASE
				WHEN @is_ext THEN v_b.caller_id_number
				WHEN v_b.caller_id_number IS NOT NULL THEN v_b.sip_from_user
			ELSE v_b.sip_from_user
			END caller_id_number_b
			
			/*,v_b.caller_id_number AS caller_id_number_b*/
			
			/*em caso de verto, o destino fica no callee*/
			,CASE
				
				/*Em caso de Entrada pela Publica e que toma rota de Saída, usa o destination number da leg a*/
				WHEN r_a.sofia_profile_name = 'external' AND r_b.sofia_profile_name = 'external' THEN v_a.destination_number
				WHEN v_a.sip_to_user IS NOT NULL AND LENGTH(v_a.sip_to_user) > 7 then v_a.sip_to_user
				/*casos de siga-me para ramal q sai para rede externa*/
				WHEN LENGTH(v_a.destination_number) <= 7 AND LENGTH(v_b.sip_to_user) > 7 THEN v_b.sip_to_user
				WHEN v_a.destination_number IS NOT NULL THEN v_a.destination_number
				WHEN v_b.destination_number is not null THEN v_b.destination_number
				WHEN v_b.destination_number is null AND v_b.sip_to_user is not null THEN v_b.destination_number
				WHEN v_b.sip_to_user is null THEN v_b.caller_id_number
			ELSE v_b.sip_to_user
			END destination_number_b
			-- ,v_b.sip_to_user AS destination_number_b
			
			
			/*,CASE
				WHEN ((`v_b`.`pick_num` IS NOT NULL) OR (`v_b`.`picked_up_uuid` IS NOT NULL) OR (`v_b`.`pickup` IS NOT NULL)) THEN if (SUBSTR(`v_b`.`sip_from_user`, 1, 2) = 'u:', v_a.dialed_user, v_a.sip_from_user)
				WHEN (`v_b`.`last_app` = 'att_xfer') THEN if (SUBSTR(`v_b`.sip_from_user, 1, 2) = 'u:', v_a.sip_from_user, v_a.sip_from_user)
			ELSE if (SUBSTR(v_a.caller_id_number, 1, 2) = 'u:', v_a.sip_from_user, v_a.caller_id_number)
			END AS `extension`*/
		
			,CASE	
				WHEN v_a.caller_id_number IS NOT NULL  and LENGTH(v_a.caller_id_number) < 8 then v_a.caller_id_number
				WHEN v_a.caller_id_number IS NOT NULL THEN v_a.sip_from_user
			ELSE v_a.sip_from_user
			END extension 
		
			,CASE
				when r_b.answer_epoch = 0 OR r_b.answer_epoch IS NULL THEN '' /*para evitar casos onde tem transbordo de bridge*/
				when v_a.last_app = 'transfer' then 'BLIND'
				when v_a.last_app = 'att_xfer' then 'ATT_TRANSFER'
			ELSE NULL
			END call_transferred
			
			,CASE
				when r_b.answer_epoch = 0 OR r_b.answer_epoch IS NULL THEN '' /*para evitar casos onde tem transbordo de bridge*/
				when v_a.last_app = 'transfer' THEN CONCAT('Para: ', v_a.digits)
				when v_a.last_app = 'att_xfer' THEN CONCAT('Para: ', v_a.digits)
			ELSE NULL
			END call_transferred_to
			
			,r_a.start_epoch start_epoch_a
			,r_a.answer_epoch answer_epoch_a
			,FROM_UNIXTIME(r_a.start_epoch) start_stamp_a
			
			,CASE 
				WHEN r_b.answer_epoch > 0  THEN  'TRUE'
				ELSE 'FALSE'
			END AS answered_carrier
			
			,r_b.start_epoch start_epoch_b
			,r_b.answer_epoch answer_epoch_b
			
			,CASE 
				WHEN r_b.answer_epoch > 0  THEN r_b.end_epoch -  r_b.answer_epoch
			ELSE 0			
			END AS answer_duraction_a
			
			,r_b.end_epoch as end_epoch_b
			,r_a.end_epoch as end_epoch_a
			,v_a.hangup_cause
			
			,CASE
				/*v_a operadora, v_b ramal (inverter isto depois*/
				WHEN (v_b.sip_hangup_disposition = 'recv_refuse') THEN 'PUBLIC NETWORK'
				WHEN (v_b.sip_hangup_disposition = 'send_cancel') THEN 'EXTENSION'
				WHEN (v_b.sip_hangup_disposition = 'send_bye' AND r_a.uuid IS NULL ) THEN 'PABX'
				WHEN (v_b.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_b.sip_hangup_disposition = 'recv_bye') THEN 'PUBLIC NETWORK'
				WHEN (v_a.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_a.sip_hangup_disposition = 'recv_bye') THEN 'PUBLIC NETWORK'
			ELSE ''
			END hangup_side
			
			,'' gateway_name
			,v_a.protocol protocol
			,v_b.cc_record_filename AS cc_record_filename_b
			,v_b.record_session AS record_session_b
			,v_a.record_session AS record_session_a
		FROM 
		(	
			SELECT
				 m_b.uuid uuid_b
				,m_b.bridge_uuid b_uuid_a
				,m_b.originator o_uuid_a
				,m_b.caller_id_number m_b_caller_id_number
				,FROM_UNIXTIME(m_b.start_epoch)
				,m_b.direction_r
			FROM 
				tmp_outbound_originator m_b
			WHERE
				/*NOT m_b.is_callback*/ /*este campo está sendo preenchido errado. Esta usando o caller_id_name*/
				m_b.call_back_params IS NULL";
				
if (in_array("outbound", $direction))
{
	$sql.="\n				AND 1";
}
else
{
	$sql.="\n				AND 0";
}
			
if ($is_jive)
{
	$sql.="
				AND (LENGTH(m_b.destination_number ) >= 8 OR LENGTH(m_b.sip_to_user ) >= 8 )
				AND (LENGTH(m_b.dialed_user) > 8 OR m_b.dialed_user IS NULL OR m_b.dialed_user = '')	
				AND m_b.direction = 'outbound'";
}
else
{
	$sql.="\n				AND m_b.direction = 'inbound' AND m_b.sofia_profile_name = 'external'";
}				

$sql.="\n		)a
		INNER JOIN `$dbname`.cdr_refer_uuid r_b ON r_b.uuid = uuid_b
		LEFT  JOIN `$dbname`.cdr_variables  v_b ON v_b.uuid = uuid_b
		INNER JOIN `$dbname`.cdr_refer_uuid r_a ON r_a.uuid = o_uuid_a
		LEFT  JOIN `$dbname`.cdr_variables v_a ON v_a.uuid = o_uuid_a
		
		WHERE 
			r_a.other_loopback_leg_uuid IS NULL
			/*AND r_a.originator IS NOT NULL*/ /*tentar com e sem isto*/
			AND r_b.originator IS NOT NULL /*tentar com e sem isto*/

	UNION
	
	
	
			/*outbound, sem originator. Chamada morreu no calliope*/

		SELECT
			'3' AS TAG
			,r_a.uuid uuid_a
			,'' uuid_b
			,'' uuid_L_B
			,'' uuid_b_originator
			,'' is_callback_b
			,r_a.call_back_params is_callback_a
	
			,'FALSE' call_answered
			
			/*r_b.direction*/
			,CASE";
				/*fazer o outbound_bridge da Jive*/
				
if ($is_jive)
{
	$sql.="\n				WHEN LENGTH(v_a.caller_id_number) >= 8 AND LENGTH(v_a.destination_number ) >= 8 THEN 'outbound_bridge'";
}
else
{
	$sql.="\n				WHEN r_a.sofia_profile_name = 'external' AND r_b.sofia_profile_name = 'external' THEN 'outbound_bridge'";
}	
				
				
$sql.="\n			ELSE 'outbound'
			END AS direction
			,direction_r
			
			,'' sofia_profile_name
			,v_a.caller_id_name caller_id_name_a
			,'' AS caller_id_number_b
			
			/*em caso de verto, o destino fica no callee*/
			,v_a.destination_number destination_number_b
			-- ,v_b.sip_to_user AS destination_number_b
			
			
			/*,CASE
				WHEN ((`v_b`.`pick_num` IS NOT NULL) OR (`v_b`.`picked_up_uuid` IS NOT NULL) OR (`v_b`.`pickup` IS NOT NULL)) THEN if (SUBSTR(`v_b`.`sip_from_user`, 1, 2) = 'u:', v_a.dialed_user, v_a.sip_from_user)
				WHEN (`v_b`.`last_app` = 'att_xfer') THEN if (SUBSTR(`v_b`.sip_from_user, 1, 2) = 'u:', v_a.sip_from_user, v_a.sip_from_user)
			ELSE if (SUBSTR(v_a.caller_id_number, 1, 2) = 'u:', v_a.sip_from_user, v_a.caller_id_number)
			END AS `extension`*/
		
			,v_a.caller_id_number extension
		
			,'' call_transferred
			
			,'' call_transferred_to
			
			,r_a.start_epoch start_epoch_a
			,r_a.answer_epoch answer_epoch_a
			,FROM_UNIXTIME(r_a.start_epoch) start_stamp_a
			
			,'FALSE' answered_carrier
			
			,'' start_epoch_b
			,'' answer_epoch_b
			
			,CASE 
				WHEN r_a.answer_epoch > 0  THEN r_a.end_epoch -  r_b.answer_epoch
			ELSE 0			
			END AS answer_duraction_a
			
			/*,'' as end_epoch_b*/
			,r_a.end_epoch as end_epoch_b
			
			,r_a.end_epoch as end_epoch_a
			,v_a.hangup_cause
			
			,CASE
				/*v_a operadora, v_b ramal (inverter isto depois*/
				WHEN (v_a.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_a.sip_hangup_disposition = 'recv_bye') THEN 'PUBLIC NETWORK'
				WHEN (v_a.sip_hangup_disposition = 'recv_cancel') THEN 'EXTENSION'
			ELSE v_a.sip_hangup_disposition
			END hangup_side
			
			,'' gateway_name
			,v_a.protocol protocol
			,'' AS cc_record_filename_b
			,'' AS record_session_b
			,v_a.record_session AS record_session_a
		FROM 
		(	
			SELECT
				 m_a.uuid uuid_a
				,FROM_UNIXTIME(m_a.start_epoch)
				,m_a.direction_r
			FROM 
				tmp_outbound_wo_originator m_a
			WHERE TRUE
				/*NOT m_b.is_callback*/ /*este campo está sendo preenchido errado. Esta usando o caller_id_name*/
				AND m_a.call_back_params IS NULL";
				
if (in_array("outbound", $direction))
{
	$sql.="\n				AND 1";
}
else
{
	$sql.="\n				AND 0";
}
			
if ($is_jive)
{
	//$sql.="\n				AND LENGTH(m_a.destination_number ) >= 8";
	//$sql.="\n				AND (LENGTH(m_a.destination_number ) >= 8 OR LENGTH(m_a.sip_to_user ) >= 8 )";
	//$sql.="\n				AND m_a.direction = 'inbound'";
	$sql.="\n				AND m_a.direction_r = 'outbound_ext_pbx'";
}
else
{
	$sql.="\n				AND m_a.direction = 'inbound' AND m_a.sofia_profile_name = 'external'";
}				

$sql.="\n		)a
			INNER JOIN `$dbname`.cdr_refer_uuid r_a ON r_a.uuid = uuid_a
			LEFT  JOIN `$dbname`.cdr_variables  v_a ON v_a.uuid = uuid_a
			LEFT JOIN  `$dbname`.cdr_refer_uuid r_b ON r_b.originator = uuid_a /*criar este indice composto*/
		
		WHERE TRUE
			AND r_a.other_loopback_leg_uuid IS NULL
			/*AND r_a.originator IS NOT NULL*/ /*tentar com e sem isto*/
			AND r_b.originator IS NULL /*tentar com e sem isto*/
			
			
		UNION

			/*INTERNAS*/

		SELECT
			'4' AS TAG
			,r_a.uuid uuid_a
			,r_b.uuid uuid_b
			,'' uuid_L_B
			,r_b.originator uuid_b_originator
			,r_b.call_back_params is_callback_b
			,r_a.call_back_params is_callback_a
	
			,CASE 
				WHEN r_a.answer_epoch > 0 THEN 'TRUE'
			ELSE 'FALSE'
			END call_answered
			
			/*r_b.direction*/
			/*,CASE
				WHEN LENGTH(v_b.caller_id_number) >= 8 AND LENGTH(v_b.destination_number ) >= 8 THEN 'outbound_bridge'
				WHEN LENGTH(v_a.caller_id_number) <= 7 AND LENGTH(v_a.sip_req_user ) <= 7 THEN 'internal'
			ELSE 'outbound'
			END AS direction*/
			,'internal' direction
			,direction_r
			
			,r_b.sofia_profile_name
			,v_a.caller_id_name caller_id_name_a
			/*,v_a.caller_id_number AS caller_id_number_a*/
			,v_a.sip_from_user AS caller_id_number_a
			
			/*em caso de verto, o destino fica no callee*/
			,CASE
				
				/*Em caso de Entrada pela Publica e que toma rota de Saída, usa o destination number da leg a*/
				WHEN r_a.sofia_profile_name = 'external' AND r_b.sofia_profile_name = 'external' THEN v_a.destination_number
				/*WHEN v_a.sip_to_user is null THEN v_a.caller_id_number*/
				WHEN v_a.sip_to_user is null THEN v_a.destination_number
			ELSE v_a.destination_number
			END destination_number_b
			-- ,v_b.sip_to_user AS destination_number_b
			
			
			/*,CASE
				WHEN ((`v_b`.`pick_num` IS NOT NULL) OR (`v_b`.`picked_up_uuid` IS NOT NULL) OR (`v_b`.`pickup` IS NOT NULL)) THEN if (SUBSTR(`v_b`.`sip_from_user`, 1, 2) = 'u:', v_a.dialed_user, v_a.sip_from_user)
				WHEN (`v_b`.`last_app` = 'att_xfer') THEN if (SUBSTR(`v_b`.sip_from_user, 1, 2) = 'u:', v_a.sip_from_user, v_a.sip_from_user)
			ELSE if (SUBSTR(v_a.caller_id_number, 1, 2) = 'u:', v_a.sip_from_user, v_a.caller_id_number)
			END AS `extension`*/
		
			/*,v_a.caller_id_number extension*/
			,v_b.destination_number
		
			,CASE
				when r_b.answer_epoch = 0 OR r_b.answer_epoch IS NULL THEN '' /*para evitar casos onde tem transbordo de bridge*/
				when v_a.last_app = 'transfer' then 'BLIND'
				when v_a.last_app = 'att_xfer' then 'ATT_TRANSFER'
				when v_b.last_app = 'att_xfer' then 'ATT_TRANSFER' /* *4 */
			ELSE NULL
			END call_transferred
			
			,CASE
				when r_b.answer_epoch = 0 OR r_b.answer_epoch IS NULL THEN '' /*para evitar casos onde tem transbordo de bridge*/
				when v_a.last_app = 'transfer' then v_a.digits
				when v_a.last_app = 'att_xfer' then v_a.digits
				when v_b.last_app = 'att_xfer' then v_b.dialed_user /* *4 */
			ELSE NULL
			END call_transferred_to
			
			,r_a.start_epoch start_epoch_a
			,r_a.answer_epoch answer_epoch_a
			,FROM_UNIXTIME(r_a.start_epoch) start_stamp_a
			
			/*,CASE 
				WHEN r_b.answer_epoch > 0  THEN  'TRUE'
				ELSE 'FALSE'
			END AS answered_carrier*/
			,'' answered_carrier
			
			,r_b.start_epoch start_epoch_b
			,r_b.answer_epoch answer_epoch_b
			
			,CASE 
				WHEN r_a.answer_epoch > 0  THEN r_a.end_epoch -  r_a.answer_epoch
			ELSE 0			
			END AS answer_duraction_a
			
			,r_a.end_epoch as end_epoch_b
			,r_a.end_epoch as end_epoch_a
			,v_a.hangup_cause
			
			,CASE
				/*v_a operadora, v_b ramal (inverter isto depois*/
				WHEN (v_b.sip_hangup_disposition = 'recv_refuse') THEN 'PUBLIC NETWORK'
				WHEN (v_b.sip_hangup_disposition = 'send_cancel') THEN 'EXTENSION'
				WHEN (v_b.sip_hangup_disposition = 'send_bye' AND r_a.uuid IS NULL ) THEN 'PABX'
				WHEN (v_b.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_b.sip_hangup_disposition = 'recv_bye') THEN 'PUBLIC NETWORK'
				
				WHEN (v_a.sip_hangup_disposition = 'send_bye' AND v_b.sip_hangup_disposition IS NULL) THEN 'PABX'
				WHEN (v_a.sip_hangup_disposition = 'send_bye') THEN 'PUBLIC NETWORK'
				WHEN (v_a.sip_hangup_disposition = 'recv_bye') THEN 'EXTENSION'
			ELSE ''
			END hangup_side
			
			,'' gateway_name
			,v_a.protocol protocol
			,v_b.cc_record_filename AS cc_record_filename_b
			,v_b.record_session AS record_session_b
			,v_a.record_session AS record_session_a
		FROM 
		(	
			SELECT
				 m_a.uuid uuid_a
				,FROM_UNIXTIME(m_a.start_epoch)
				,m_a.direction_r
			FROM 
				tmp_local_wo_originator m_a
			WHERE TRUE
				/*NOT m_b.is_callback*/ /*este campo está sendo preenchido errado. Esta usando o caller_id_name*/
				AND m_a.call_back_params IS NULL";
				
if (in_array("internal", $direction))
{
	$sql.="\n				AND 1";
}
else
{
	$sql.="\n				AND 0";
}
			
if ($is_jive)
{
	$sql.="\n				AND LENGTH(m_a.caller_id_number ) <= 7";
	$sql.="\n				AND m_a.caller_id_number <> 'unknown'";
	//$sql.="\n				AND LENGTH(m_a.destination_number ) <= 7";
	$sql.="\n				AND m_a.direction_r = 'local_ext_pbx' /*AND (LENGTH(m_a.destination_number ) <= 7 OR LENGTH(m_a.sip_to_user ) <= 7 )*/";

}
else
{
	$sql.="\n				AND m_a.direction = 'inbound' AND m_a.sofia_profile_name = 'external'";
}				

$sql.="\n		)a
			INNER JOIN `$dbname`.cdr_refer_uuid r_a ON r_a.uuid = uuid_a
			LEFT  JOIN `$dbname`.cdr_variables v_a ON v_a.uuid = uuid_a
			LEFT  JOIN `$dbname`.cdr_refer_uuid r_b ON r_b.originator = uuid_a
			LEFT  JOIN `$dbname`.cdr_variables v_b ON v_b.uuid = r_b.uuid
		
		WHERE TRUE
			AND r_a.other_loopback_leg_uuid IS NULL
			/*casos de siga-me onde o destino é nr. externo*/
			/*AND (LENGTH(v_b.sip_to_user) > 7 OR v_b.sip_to_user IS NULL)*/
			/*Nâo estavam sendo exibidas chamadas entre ramais*/
			AND (LENGTH(v_b.sip_to_user) > 7 OR LENGTH(v_b.sip_to_user) <= 7 OR v_b.sip_to_user IS NULL)

	UNION
	
	
	/*Transferidas do *4. Passar paramentro call_transferred att_xfer para que possa entrar aqui e falhar as outras */

		SELECT
			'4.1' AS TAG
			,r_a.originator uuid_a /*para ter a mesma leg a que a primeira chamada, daí agrupamos e ela não é exibida. Assim como é feito com o *1*/
			,r_b.uuid uuid_b
			,'' uuid_L_B
			,r_b.originator uuid_b_originator
			,r_b.call_back_params is_callback_b
			,r_a.call_back_params is_callback_a
	
			,CASE 
				WHEN r_a.answer_epoch > 0 THEN 'TRUE'
			ELSE 'FALSE'
			END call_answered
			
			/*r_b.direction*/
			/*,CASE
				WHEN LENGTH(v_b.caller_id_number) >= 8 AND LENGTH(v_b.destination_number ) >= 8 THEN 'outbound_bridge'
				WHEN LENGTH(v_a.caller_id_number) <= 7 AND LENGTH(v_a.sip_req_user ) <= 7 THEN 'internal'
			ELSE 'outbound'
			END AS direction*/
			,'internal' direction
			,'local_ext_pbx' direction_r /*força devido ao *4 */
			
			,r_b.sofia_profile_name
			,v_a.caller_id_name caller_id_name_a
			/*,v_a.caller_id_number AS caller_id_number_a*/
			,v_a.sip_from_user AS caller_id_number_a
			
			/*em caso de verto, o destino fica no callee*/
			,CASE
				
				/*Em caso de Entrada pela Publica e que toma rota de Saída, usa o destination number da leg a*/
				WHEN r_a.sofia_profile_name = 'external' AND r_b.sofia_profile_name = 'external' THEN v_a.destination_number
				/*WHEN v_a.sip_to_user is null THEN v_a.caller_id_number*/
				WHEN v_a.sip_to_user is null THEN v_a.destination_number
			ELSE v_a.destination_number
			END destination_number_b
			-- ,v_b.sip_to_user AS destination_number_b
			
			
			/*,CASE
				WHEN ((`v_b`.`pick_num` IS NOT NULL) OR (`v_b`.`picked_up_uuid` IS NOT NULL) OR (`v_b`.`pickup` IS NOT NULL)) THEN if (SUBSTR(`v_b`.`sip_from_user`, 1, 2) = 'u:', v_a.dialed_user, v_a.sip_from_user)
				WHEN (`v_b`.`last_app` = 'att_xfer') THEN if (SUBSTR(`v_b`.sip_from_user, 1, 2) = 'u:', v_a.sip_from_user, v_a.sip_from_user)
			ELSE if (SUBSTR(v_a.caller_id_number, 1, 2) = 'u:', v_a.sip_from_user, v_a.caller_id_number)
			END AS `extension`*/
		
			/*,v_a.caller_id_number extension*/
			,v_b.destination_number
		
			,CASE
				when r_b.answer_epoch = 0 OR r_b.answer_epoch IS NULL THEN '' /*para evitar casos onde tem transbordo de bridge*/
				when v_a.last_app = 'transfer' then 'BLIND'
				/*when v_a.last_app = 'att_xfer' then 'ATT_TRANSFER'*/
				when v_b.last_app = 'att_xfer' then 'ATT_TRANSFER' /* *4 */
			ELSE NULL
			END call_transferred
			
			,CASE
				when r_b.answer_epoch = 0 OR r_b.answer_epoch IS NULL THEN '' /*para evitar casos onde tem transbordo de bridge*/
				when v_a.last_app = 'transfer' then v_a.digits
				/*when v_a.last_app = 'att_xfer' then v_a.digits*/
				when v_b.last_app = 'att_xfer' then v_b.dialed_user /* *4 */
			ELSE NULL
			END call_transferred_to
			
			,r_a.start_epoch start_epoch_a
			,r_a.answer_epoch answer_epoch_a
			,FROM_UNIXTIME(r_a.start_epoch) start_stamp_a
			
			/*,CASE 
				WHEN r_b.answer_epoch > 0  THEN  'TRUE'
				ELSE 'FALSE'
			END AS answered_carrier*/
			,'' answered_carrier
			
			,r_b.start_epoch start_epoch_b
			,r_b.answer_epoch answer_epoch_b
			
			,CASE 
				WHEN r_a.answer_epoch > 0  THEN r_a.end_epoch -  r_a.answer_epoch
			ELSE 0			
			END AS answer_duraction_a
			
			,r_a.end_epoch as end_epoch_b
			,r_a.end_epoch as end_epoch_a
			,v_a.hangup_cause
			
			,CASE
				/*v_a operadora, v_b ramal (inverter isto depois*/
				WHEN (v_b.sip_hangup_disposition = 'recv_refuse') THEN 'PUBLIC NETWORK'
				WHEN (v_b.sip_hangup_disposition = 'send_cancel') THEN 'EXTENSION'
				WHEN (v_b.sip_hangup_disposition = 'send_bye' AND r_a.uuid IS NULL ) THEN 'PABX'
				WHEN (v_b.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_b.sip_hangup_disposition = 'recv_bye') THEN 'PUBLIC NETWORK'
				
				WHEN (v_a.sip_hangup_disposition = 'send_bye' AND v_b.sip_hangup_disposition IS NULL) THEN 'PABX'
				WHEN (v_a.sip_hangup_disposition = 'send_bye') THEN 'PUBLIC NETWORK'
				WHEN (v_a.sip_hangup_disposition = 'recv_bye') THEN 'EXTENSION'
			ELSE ''
			END hangup_side
			
			,'' gateway_name
			,v_a.protocol protocol
			,v_b.cc_record_filename AS cc_record_filename_b
			,v_b.record_session AS record_session_b
			,v_a.record_session AS record_session_a
		FROM 
		(	
			SELECT
				 m_a.uuid uuid_a
				,FROM_UNIXTIME(m_a.start_epoch)
				,m_a.direction_r
			FROM 
				tmp_local_wo_originator m_a
			WHERE TRUE
				/*NOT m_b.is_callback*/ /*este campo está sendo preenchido errado. Esta usando o caller_id_name*/
				AND m_a.call_back_params IS NULL";
				
if (in_array("internal", $direction))
{
	$sql.="\n				AND 1";
}
else
{
	$sql.="\n				AND 0";
}
			
if ($is_jive)
{
	$sql.="\n				AND LENGTH(m_a.caller_id_number ) <= 7";
	$sql.="\n				AND m_a.caller_id_number <> 'unknown'";
	//$sql.="\n				AND LENGTH(m_a.destination_number ) <= 7";
	$sql.="\n				AND m_a.direction_r = 'inbound_pbx_ext'/*devido ao *4 */ /*AND (LENGTH(m_a.destination_number ) <= 7 OR LENGTH(m_a.sip_to_user ) <= 7 )*/";

}
else
{
	$sql.="\n				AND m_a.direction = 'inbound' AND m_a.sofia_profile_name = 'external'";
}				

$sql.="\n		)a
			INNER JOIN `$dbname`.cdr_refer_uuid r_a ON r_a.uuid = uuid_a
			LEFT  JOIN `$dbname`.cdr_variables v_a ON v_a.uuid = uuid_a
			LEFT  JOIN `$dbname`.cdr_refer_uuid r_b ON r_b.originator = uuid_a
			LEFT  JOIN `$dbname`.cdr_variables v_b ON v_b.uuid = r_b.uuid
		
		WHERE TRUE
			AND r_b.originator IS NOT NULL /*na transferencia *4 ela não é nula*/
			AND r_a.other_loopback_leg_uuid IS NULL
			/*casos de siga-me onde o destino é nr. externo*/
			/*AND (LENGTH(v_b.sip_to_user) > 7 OR v_b.sip_to_user IS NULL)*/
			/*Nâo estavam sendo exibidas chamadas entre ramais*/
			AND (LENGTH(v_b.sip_to_user) > 7 OR LENGTH(v_b.sip_to_user) <= 7 OR v_b.sip_to_user IS NULL)

	UNION
	
	/*Discador não tem originator e tem, sim, bridge_uuid*/
		SELECT
			'5' AS TAG
			,r_a.uuid uuid_a
			,r_b.uuid uuid_b
			,'' uuid_L_B
			,'' uuid_b_originator
			,r_a.call_back_params is_callback_a
			,r_b.call_back_params is_callback_b

			,CASE 
				WHEN r_a.direction = 'inbound' AND r_a.sofia_profile_name = 'external' AND r_b.uuid IS NOT NULL THEN 'TRUE' /*inbound*/
				WHEN r_a.direction = 'inbound' AND r_a.sofia_profile_name = 'external' AND r_b.uuid IS NULL THEN 'FALSE' /*inbound*/
				WHEN r_a.direction = 'outbound' AND r_a.sofia_profile_name = 'external' AND r_b.answer_epoch > 0 THEN 'TRUE' /*outbound*/
				WHEN r_a.direction = 'outbound' AND r_a.sofia_profile_name = 'external' THEN 'FALSE' /*outbound*/			
			END call_answered
			
			/*,r_a.direction*/ ,'outbound_bridge' direction
			,direction_r
			,r_a.sofia_profile_name
			,v_a.caller_id_name caller_id_name_a
			
			,CASE
				WHEN r_a.bridge_uuid IS NOT NULL THEN v_a.sip_from_user 
				WHEN r_a.bridge_uuid IS NULL AND (v_a.caller_id_name = 'Outbound Call' OR v_a.caller_id_name = 'Discador') THEN v_a.sip_from_user
				ELSE v_a.caller_id_number
			END AS caller_id_number_a
			-- ,v_a.caller_id_number caller_id_number_a
			
			,CASE
				WHEN r_a.bridge_uuid IS NULL AND (v_a.caller_id_name = 'Outbound Call' OR v_a.caller_id_name = 'Discador') THEN v_a.sip_to_user
				WHEN r_a.bridge_uuid IS NOT NULL THEN v_a.sip_to_user 
				ELSE v_a.destination_number
			END AS destination_number_a
			/*,v_a.destination_number AS destination_number_a*/
			
			,CASE
				WHEN ((`v_b`.`pick_num` IS NOT NULL) OR (`v_b`.`picked_up_uuid` IS NOT NULL) OR (`v_b`.`pickup` IS NOT NULL)) THEN if (SUBSTR(`v_b`.destination_number, 1, 2) = 'u:', v_b.dialed_user, v_b.destination_number)
				WHEN (`v_b`.`last_app` = 'att_xfer') THEN if (SUBSTR(`v_b`.sip_from_user, 1, 2) = 'u:', v_b.sip_from_user, v_b.sip_from_user)
			ELSE if (SUBSTR(v_b.destination_number, 1, 2) = 'u:', v_b.dialed_user, v_b.destination_number)
			END AS extension
			-- ,v_b.destination_number extension
			-- ,if (SUBSTR(`v_b`.destination_number, 1, 2) = 'u:', v_b.dialed_user, v_b.sip_from_user)

			,CASE
				when v_b.last_app = 'transfer' then 'BLIND'
				when v_b.last_app = 'att_xfer' then 'ATT_TRANSFER'
			ELSE NULL
			END call_transferred

			,CASE
				when r_b.answer_epoch = 0 OR r_b.answer_epoch IS NULL THEN '' /*para evitar casos onde tem transbordo de bridge*/
				when v_a.last_app = 'transfer' then v_a.digits
				when v_a.last_app = 'att_xfer' then v_a.digits
			ELSE NULL
			END call_transferred_to
			
			,r_a.start_epoch start_epoch_b
			,r_a.answer_epoch answer_epoch_b
			,FROM_UNIXTIME(r_a.start_epoch) start_stamp_b
			,'TRUE' AS answered_carrier
			,r_b.start_epoch start_epoch_a
			,r_b.answer_epoch answer_epoch_a
			,CASE 
				WHEN r_b.answer_epoch > 0 THEN (r_b.end_epoch - r_b.answer_epoch)
			 ELSE 0
			 END answer_duraction_a
			,r_a.end_epoch as end_epoch_b
			,r_b.end_epoch as end_epoch_a
			,v_a.hangup_cause
			
			,CASE
				/*v_a operadora, v_b ramal (inverter isto depois)*/
				WHEN (v_a.sip_hangup_disposition = 'send_cancel') THEN 'EXTENSION'
				WHEN (v_a.sip_hangup_disposition = 'send_bye' AND r_b.uuid IS NULL ) THEN 'PABX'
				WHEN (v_a.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_a.sip_hangup_disposition = 'recv_bye') THEN 'PUBLIC NETWORK'
				WHEN (v_b.sip_hangup_disposition = 'send_bye') THEN 'EXTENSION'
				WHEN (v_b.sip_hangup_disposition = 'recv_bye') THEN 'PUBLIC NETWORK'
				WHEN (v_a.sip_hangup_disposition = 'recv_refuse') THEN 'PUBLIC NETWORK'
			ELSE ''
			END hangup_side
			
			,'' gateway_name
			,v_b.protocol protocol
			,v_a.cc_record_filename AS cc_record_filename_b
			,v_a.cc_record_filename AS record_session_b
			,v_b.cc_record_filename AS record_session_a
			FROM 
		/*SELECT * from*/
		(	
			SELECT
				 m_a.uuid uuid_a
				,m_a.bridge_uuid b_uuid_a
				,m_a.originator o_uuid_a
				,FROM_UNIXTIME(m_a.start_epoch)
				,m_a.direction_r
			FROM 
				tmp_outbound_bridge m_a
			WHERE TRUE
				/*NOT m_a.is_callback*/
				AND m_a.call_back_params IS NULL
			 	AND m_a.originator IS NULL";

if (in_array("outbound", $direction))
{
	$sql.="\n				AND 1";
}
else
{
	$sql.="\n				AND 0";
}
			
if ($is_jive)
{
	$sql.="
				AND (LENGTH(m_a.destination_number ) >= 8 OR LENGTH(m_a.sip_to_user ) >= 8 )
				AND (LENGTH(m_a.dialed_user) > 8 OR m_a.dialed_user IS NULL OR m_a.dialed_user = '')	
				AND m_a.direction = 'outbound'";
}
else
{
	$sql.="\n				AND m_a.direction = 'inbound' AND m_a.sofia_profile_name = 'external'";
}

$sql.="\n		)a
		INNER JOIN `$dbname`.cdr_refer_uuid r_a ON r_a.uuid = uuid_a
		LEFT  JOIN `$dbname`.cdr_variables  v_a ON v_a.uuid = uuid_a
		LEFT  JOIN `$dbname`.cdr_refer_uuid r_b ON r_b.uuid = b_uuid_a
		LEFT  JOIN `$dbname`.cdr_variables  v_b ON v_b.uuid = b_uuid_a

		WHERE TRUE
			AND r_b.other_loopback_leg_uuid IS NULL
			AND r_b.originator IS NULL /*Chamadas q não tem o originator, como discador por exemplo*/

	UNION 

		
		/*outbound callback*/	
		SELECT
			'6' AS TAG
			,uuid_ope uuid_a
			,agent_r.uuid uuid_b
			,'' uuid_L_B
			,'' uuid_b_originator
			,OPE.call_back_params is_callback_a
			,agent_r.call_back_params is_callback_b		
			
			,CASE
				WHEN OPE.answer_epoch > 0 THEN 'TRUE'
			ELSE
				'FALSE'
			END call_answered	
			
			,'outbound_callback' direction
			,direction_r
			,OPE.sofia_profile_name
			,agent_var.caller_id_name caller_id_name_a
			,OPE_VAR.caller_id_number caller_id_number_a
			,OPE_VAR.destination_number destination_number_a
			,agent_var.destination_number extension
			
			,CASE
				when OPE.answer_epoch = 0 OR OPE.answer_epoch IS NULL THEN NULL /*para evitar casos onde tem transbordo de bridge*/
				when agent_var.last_app = 'transfer' then 'BLIND'
				when agent_var.last_app = 'att_xfer' then 'ATT_TRANSFER'
			ELSE NULL
			END call_transferred
			
			,CASE
				when OPE.answer_epoch = 0 OR OPE.answer_epoch IS NULL THEN NULL /*para evitar casos onde tem transbordo de bridge*/
				when agent_var.last_app = 'transfer' then agent_var.digits
				when agent_var.last_app = 'att_xfer' then agent_var.digits
			ELSE NULL
			END call_transferred_to
			
			,OPE.start_epoch start_epoch_a
			,OPE.answer_epoch answer_epoch_a
			,FROM_UNIXTIME(OPE.start_epoch)
			
			,CASE 
				WHEN OPE.answer_epoch > 0 THEN 'TRUE'
			ELSE 'FALSE'
			END answered_carrier
			
			,agent_r.start_epoch start_epoch_b
			,agent_r.answer_epoch answer_epoch_b
			
			,CASE
				WHEN OPE.answer_epoch IS NULL OR OPE.answer_epoch = 0 THEN 0
				WHEN agent_r.answer_epoch > 0 THEN (agent_r.end_epoch - agent_r.answer_epoch)
			ELSE
				' -- : -- : -- '
			END answer_duration_b
			
			,OPE.end_epoch end_epoch_a
			,agent_r.end_epoch end_epoch_b
			,OPE_VAR.hangup_cause
			
			,CASE 
				WHEN OPE_VAR.sip_hangup_disposition = 'recv_refuse' THEN 'PUBLIC NETWORK'
				WHEN OPE_VAR.sip_hangup_disposition = 'recv_bye' AND (agent_r.answer_epoch IS NULL OR agent_r.answer_epoch = 0 ) THEN 'PABX'
				WHEN OPE_VAR.sip_hangup_disposition = 'recv_bye' THEN 'EXTENSION'
				WHEN OPE_VAR.sip_hangup_disposition = 'send_bye' THEN 'PUBLIC NETWORK'
			ELSE
				'NONE'
			END hangup_side
			
			,'' gateway_name
			,OPE_VAR.protocol
			,'' cc_record_filename_a
			,OPE_VAR.record_session record_session_a
			,agent_var.record_session record_session_b
		
		FROM
		(
			SELECT
				 ope.bridge_uuid bridge_uuid_ope /*Tem q retornar o bridge_uuid pq o inner join sera com a chave primaria, senao a query fica lenta*/
				,ope.uuid uuid_ope
				,ope.originator o_ope
				,ope.direction_r
			FROM 
				tmp_tbl_callback ope
			ORDER BY ope.start_epoch asc
		)b
		INNER JOIN `$dbname`.cdr_refer_uuid OPE ON OPE.uuid = uuid_ope
		INNER JOIN `$dbname`.cdr_variables OPE_VAR ON OPE_VAR.uuid = uuid_ope
		left  JOIN `$dbname`.cdr_refer_uuid LB_B ON LB_B.uuid = o_ope
		left  JOIN `$dbname`.cdr_refer_uuid LB_A ON LB_A.uuid = LB_B.other_loopback_leg_uuid /*aqui garante q é tudo callback*/
		LEFT  JOIN `$dbname`.cdr_refer_uuid LA_A ON LA_A.uuid = LB_A.bridge_uuid
		LEFT  JOIN `$dbname`.cdr_refer_uuid LA_B ON LA_B.uuid = LA_A.other_loopback_leg_uuid
		LEFT  JOIN `$dbname`.cdr_refer_uuid agent_r ON agent_r.uuid = LA_B.bridge_uuid
		LEFT  JOIN `$dbname`.cdr_variables agent_var ON agent_var.uuid = LA_B.bridge_uuid
		
		WHERE TRUE
		AND LB_B.other_loopback_leg_uuid IS not null
		/*precisamos colocar alguma flag sobre callback, exemplo, is_callback = true. Pq com other_loopback_leg_uuid pode pegar chamadas q NÃO são callback*/
		AND LB_B.is_callback = 'params2'";

if (in_array("outbound", $direction))
{
	$sql.="\n			AND 1";
}
else
{
	$sql.="\n			AND 0";
}
		
$sql.="\n	)c";
		
$sql.="\nWHERE TRUE";

	if (strlen($extension) > 0)
	{
		$mod_extension = str_replace("*", "%", $extension);
		if (strpos($mod_extension, "%") === false)
		{
			$sql.= "\n	AND (extension = '$mod_extension')";
		}
		else{
			$sql.= "\n	AND (extension like '$mod_extension')";			
		}
	}
	
	
	if ($finalization == "TRUE")
	{
		$sql.="\n	AND call_answered = 'TRUE'";
	}
	if ($finalization == "FALSE")
	{
		$sql.="\n	AND call_answered = 'FALSE'";
	}
	
	if ($transferred == "TRUE")
	{
		$sql.="\n	AND call_transferred in ('BLIND', 'ATT_TRANSFER')";
	}
	
	$sql.="
	ORDER BY start_epoch_a ASC, start_epoch_b ASC
)d
GROUP BY uuid_a
ORDER BY start_epoch_a asc;";



	$tmp_file.= $sql;
	
	
	/*se realizou a query principal no banco de dados, precisamos limpar todas as keys das querys subsequentes, pq elas serão realizadas novamente*/
	
	$key_tmp_tbl_xml_cdr_report = "key_tmp_tbl_xml_cdr_report:".base64_encode($main_key);
	$key_total = "total:".base64_encode($main_key);
	$key_total_inbound = "key_total:".base64_encode($main_key);
	$key_total_inbound_answered = "key_total_inbound_answered:".base64_encode($main_key);
	$key_total_inbound_capillarity = "key_total_inbound_capillarity:".base64_encode($main_key);
	$key_total_internal = "key_total_internal:".base64_encode($main_key);
	$key_total_internal_answered = "key_total_internal_answered:".base64_encode($main_key);
	$key_total_outbound_answered = "key_total_outbound_answered:".base64_encode($main_key);
	$key_total_outbound_bridge = "key_total_outbound_bridge:".base64_encode($main_key);
	$key_registros = "key_registros:".base64_encode($main_key);
	$key_hangup_cause = "key_hangup_cause:".base64_encode($main_key);
	$key_registros_limit = "key_registros_limit:".base64_encode($main_key);
	unset($array);
	$array[] = $key_tmp_tbl_xml_cdr_report;
	$array[] = $key_total;
	$array[] = $key_total_inbound;
	$array[] = $key_total_inbound_answered;
	$array[] = $key_total_inbound_capillarity;
	$array[] = $key_total_internal;
	$array[] = $key_total_internal_answered;
	$array[] = $key_total_outbound_answered;
	$array[] = $key_total_outbound_bridge;
	$array[] = $key_registros;
	$array[] = $key_hangup_cause;
	$array[] = $key_registros_limit;
	
	
	/*SELECT NO BANCO OU NO MEMCACHE*/
	query_cache($db, $sql, $key_tmp_tbl_xml_cdr_report, $memcache_expires, $array);
	
	
	/*REGISTROS TOTAIS PARA GERAR O .CSV*/
	$sql = "\n\n
	/*TODOS OS REGISTROS*/
	SELECT
		*
	FROM tmp_tbl_xml_cdr_report;\n\n";
	$tmp_file.= $sql;
	$result_registros = query_cache($db, $sql, $key_tmp_tbl_xml_cdr_report, $memcache_expires);
	
	
	/* PAGINAÇÃO */
	unset($result_tmp);
	for($i= $requestData['start']; $i <  $requestData['start'] + $requestData['length']; $i++) {
		if ($result_registros[$i]){
			$result_tmp[] = $result_registros[$i];
		}
	}
	$result = $result_tmp;
	
	
	/*$sql= "\n\n";
	$sql.= "SELECT * FROM tmp_tbl_xml_cdr_report";
	if(isset($requestData['start']))
	{
		$sql.= " LIMIT " . $requestData['start'] . " ," . $requestData['length'] . " ";
	}
	$sql.= "\n\n";
		
	$prep_statement = $db->prepare($sql);
	$prep_statement->execute();
	$result = $prep_statement->fetchAll(PDO::FETCH_NAMED);*/
	
	$recordsFiltered = count($result);
	//$recordsFiltered = $recordsTotal;
		
	
	//NOVA TOTALIZACAO
	
	/*CHAMADAS TOTAIS*/
	$tmp_file.= "\n\n\n		### TOTAIS ###\n";
		

/* TOTAL */
	$sql = "\n\n	
/*TOTAL*/
	SELECT
		COUNT(1) as total
	FROM tmp_tbl_xml_cdr_report;";
	$tmp_file.= $sql;
    $result_total = query_cache($db, $sql, $key_total, $memcache_expires);
	$recordsFiltered = $result_total[0]['total'];
	$recordsTotal = $result_total[0]['total'];
		
	

/*TOTAL ENTRADA */
	$sql = "\n\n
/*TOTAL ENTRADA*/
	SELECT
		COUNT(1) as total
	FROM tmp_tbl_xml_cdr_report
	WHERE TRUE 
	/*AND direction = 'inbound'*/
	AND direction_r in ('inbound_car_pbx')
	;";
	$res = query_cache($db, $sql, $key_total_inbound, $memcache_expires);
	$result_total_direction_inbound = $res[0]['total'];
	$tmp_file.=  "\n\n".$sql;
	
	

/*TOTAL ENTRADA ATENDIDA*/
	$sql = "\n\n
/*TOTAL ENTRADA ATENDIDA*/
	SELECT
		COUNT(1) as total
	FROM tmp_tbl_xml_cdr_report
	WHERE TRUE 
		/*AND direction = 'inbound'*/
		AND direction_r in ('inbound_car_pbx')
		AND call_answered = 'TRUE';";
	$res = query_cache($db, $sql, $key_total_inbound_answered, $memcache_expires);
	$result_total_direction_inbound_answered = $res[0]['total'];
	$tmp_file.=  "\n\n".$sql;

	
	/*TOTAL_INBOUND CANCELED*/
	$result_inbound_canceled = $result_total_direction_inbound - $result_total_direction_inbound_answered;


/*TOTAL SAÍDA BRIDGE*/
	$sql = "\n\n
/*TOTAL SAÍDA BRIDGE*/
	SELECT
		COUNT(1) as total
	FROM tmp_tbl_xml_cdr_report
	WHERE TRUE
	/*AND direction in ('outbound', 'outbound_callback', 'outbound_bridge')*/
	AND direction_r in ('outbound_pbx_car', 'outbound_ext_pbx')
	;";
	$res = query_cache($db, $sql, $key_total_outbound_bridge, $memcache_expires);
	$result_total_direction_outbound = $res[0]['total'];
	$tmp_file.=  "\n\n".$sql;



/*TOTAL SAÍDA ATENDIDA*/
	$sql = "\n\n
/*TOTAL SAÍDA ATENDIDA*/
	SELECT
		COUNT(1) as total
	FROM tmp_tbl_xml_cdr_report
	WHERE TRUE 
		/*AND direction in ('outbound', 'outbound_callback', 'outbound_bridge')*/
		AND direction_r in ('outbound_pbx_car', 'outbound_ext_pbx')
		AND call_answered = 'TRUE';";
	$res = query_cache($db, $sql, $key_total_outbound_answered, $memcache_expires);
	$result_outbound_answered = $res[0]['total'];
	$tmp_file.=  "\n\n".$sql;
		
		

/*TOTAL OUTBOUND CANCELED*/
	$result_outbound_canceled = $result_total_direction_outbound - $result_outbound_answered;


/*CAPLARIDADE*/
	$sql = "\n\n
/*CAPLARIDADE*/
	SELECT
		COUNT(1) as total
	FROM(
		SELECT
			caller_id_number_a
		FROM tmp_tbl_xml_cdr_report
		WHERE TRUE 
		/*AND direction = 'inbound'*/
		AND direction_r in ('inbound_car_pbx')
		GROUP BY caller_id_number_a
	)a;";
	$res = query_cache($db, $sql, $key_total_inbound_capillarity, $memcache_expires);
	$result_inbound_capillarity = $res[0]['total'];
	$tmp_file.=  "\n\n".$sql;
	
	

/*TOTAL INTERNA*/
	$sql = "\n\n
/*TOTAL INTERNA*/
	SELECT COUNT(1) as total 
	FROM tmp_tbl_xml_cdr_report
	WHERE TRUE
	/*AND direction in ('internal')*/
	AND direction_r in ('local_ext_pbx')
	;";
	
	$res = query_cache($db, $sql, $key_total_internal, $memcache_expires);
	$result_total_direction_internal = $res[0]['total'];
	$tmp_file.=  "\n\n".$sql;
	
	
	
/*TOTAL INTERNA ATENDIDA*/
	$sql = "\n\n
/*TOTAL INTERNA ATENDIDA*/
	SELECT
		COUNT(1) as total
	FROM tmp_tbl_xml_cdr_report
	WHERE TRUE 
		/*AND direction in ('internal')*/
		AND direction_r in ('local_ext_pbx')
		AND call_answered = 'TRUE';";
	$res = query_cache($db, $sql, $key_total_internal_answered, $memcache_expires);
	$result_total_direction_internal_answered = $res[0]['total'];
	$tmp_file.=  "\n\n".$sql;
	
	
/*TOTAL INTERNA NÃO ATENDIDA*/
	$result_internal_canceled = $result_total_direction_internal - $result_total_direction_internal_answered;
		
		
/*OCUPACAO*/
	$sql = "\n\n
/*OCUPACAO*/
	SELECT
		*
	FROM tmp_tbl_xml_cdr_report";
	$result_ocupacao = query_cache($db, $sql, $key_registros, $memcache_expires);
	$tmp_file.=  "\n\n".$sql;
	
	//cria vetor
	//echo "intervalo [".($start_stamp_end_epoch - $start_stamp_begin_epoch)."]<br>";
	for ($x = $start_stamp_begin_epoch; $x <= $start_stamp_end_epoch; $x++) {
		$_epoch[$x] = 0;
	}
	// $tmp_file.=  "\ntotal periodo [". count($_epoch)."] $start_stamp_begin_epoch até $start_stamp_end_epoch";
		
		
		// que pocaliação seg
		foreach($result_ocupacao as $row) {
			//$tmp_file.=  "\n".$row['start_epoch_a']." - ".$row['end_epoch_a'];
			if (strlen($row['start_epoch_a']) > 0 && strlen($row['end_epoch_a']) > 0 && $row['end_epoch_a'] <= $start_stamp_end_epoch) {
				for ($x = $row['start_epoch_a']; $x < $row['end_epoch_a']; $x++) {					
					//$_epoch é preenchido com 1 e se tiver mais chamada no intervalo ele será incrementado.
					$_epoch[$x]++;
					//$tmp_file.=  "\n[$x - ". $_epoch[$x]."]";
				}
			}
		}
		
		
		
		
		// que pocaliação minuto
		/*$interval = 60;
		$p = 0;
		foreach($_epoch as $epoch => $total) {
			if ($p == 0) {
				$p = $epoch;
				//echo "tom $p<br>";
				//$tmp_file.=  "\n$p";
			} 
			
			//echo "[".($p + $interval)."] [".$epoch."]<Br>";
			//$tmp_file.=  "\n[".($p + $interval)."] [".$epoch."]";
			if ($p + $interval < $epoch) {
				$p = $p + $interval;
				//echo "tom2 $p<br>";
				//echo "$p";
			}
			if (strlen($_epoch_m[$p]) == 0 or $total > $_epoch_m[$p]) {
				$_epoch_m[$p] = $total;
				//$tmp_file.=  "\nADICIONOU [$p]= $total";
			}
		}*/
		
		
		//$_epoch = $_epoch_m;
		//$_epoch = $_epoch_h;
		//$_epoch = $_epoch_d;
		
		//totalização
		$max['epoch'] = 0;
		$max['total'] = 0;
		if (count($_epoch) > 0) {
			foreach($_epoch as $epoch => $total) {
				if ($total > $max['total']) {
					//$tmp_file.=  "\n$total > ".$max['total'];
					unset($max);
					$max['epoch'] = $epoch;
					$max['total'] = $total;
				} 
			}
		}
		
		$max_total = $max['total'];
		$max_epoch = $max['epoch'];
		$max_stamp = date('Y-m-d H:i:s', $max['epoch']);
		
		//$tmp_file.=  "\nmax_total [$max_total]";
		//$tmp_file.=  "\nmax_epoch [$max_epoch]";
		//$tmp_file.=  "\nmax_stamp [$max_stamp]";
		
		
		$file_sql = fopen('xml_cdr_report.sql','w');
		fwrite($file_sql, $tmp_file);
		fclose($file_sql);
		
	
	/*Select do detalhado*/
	
	if(isset($_GET["file"]) && $_GET["file"] == "eCSV")
	{
		header('Set-Cookie: fileDownload=true; path=/');
		header('Cache-Control: max-age=60, must-revalidate');
		header("Content-type: text/csv");
		$from = date('Y-m-d-H-i-s', $start_stamp_begin_epoch);
		$to = date('Y-m-d-H-i-s', $start_stamp_end_epoch);
		$file_name = $text['description2']."_".$text['from']."_".$from."_".$text['to']."_".$to;
		header('Content-Disposition: attachment; filename="' . $file_name .'.csv"');
		
		$t = array();
		//array_push($t, $text['label-item']);
		array_push($t, $text['label-protocol']);
		array_push($t, $text['label-state']);
		array_push($t, $text['label-direction']);
		array_push($t, $text['label-type']);
		array_push($t, $text['label-name']);
		array_push($t, $text['label-source']);
		array_push($t, $text['label-destination']);
		array_push($t, $text['label-extension']);
		array_push($t, $text['label-call_start_epoch']);
		array_push($t, $text['label-end_time']);
		array_push($t, $text['label-talk_time']);
		array_push($t, $text['label-transferation']);
		array_push($t, $text['label-hangup_cause']);
		array_push($t, $text['label-hangup_side']);

		$fh = fopen('php://temp', 'rw');
		
		fputs($fh, $bom = chr(0xEF) . chr(0xBB) . chr(0xBF));
		
		fputcsv($fh, $t, ";");

		foreach ($result_registros as $row)
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
			
			$uuid = $row["uuid_a"];
			$bridge_uuid ="";
			$member_uuid = "";
			$start_epoch = $row["start_epoch_a"];
			
			$a = array();
			
			//item
			/*
			if($aUuidInc % 2 == 0)
			{
				$a[] = $x;
			}
			else
			{
				$a[] = $x;
			}
			*/
			
			$a[] = $row['protocol'];
			
			//estado
			if($row['call_answered'] == "TRUE")
			{
				$a[] = $text['label-answered'];
			}
			else
			{
				$a[] = $text['label-not-answered'];
			}
			
			//direção
			if($row["direction"] == "inbound")
			{
				$a[] = $text['label-inbound'];
			}
			else if($row["direction"] == "outbound")
			{
				$a[] = $text['label-outbound'];
			}
			else if($row["direction"] == "outbound_bridge")
			{
				$a[] = $text['label-outbound'];
			}
			else if($row["direction"] == "internal")
			{
				$a[] = $text['label-internal'];
			}
			else
			{
				$a[] = $row["direction"];
			}
			
			//tipo
			if($row["direction"] == "outbound_callback")
			{
				$a[] = "CallBack";
			}
			if($row["direction"] == "outbound_bridge")
			{
				$a[] = $text['label-automatic'];
			}
			else
			{
				$a[] = "Manual";
			}
			
			//origem nome
			$a[] = $row["caller_id_name_a"];
		
			
			//origem
			//$a[] = numbers_only($row["caller_id_number_a"]);
			$a[] = $row["caller_id_number_a"];
			
			
			//destino
			//$a[] = numbers_only($row["destination_number_a"]);
			if ($is_jive) {
				if (preg_match('/(.*)(\+(.*))/', $row["destination_number_a"], $matches)) {
					$a[] = $matches[2];
				}
				else
				{
					$a[] = $row["destination_number_a"];
				}
			}
			else
			{
				$a[] = $row["destination_number_a"];
			}
			
			
			//ramal
			//$a[] = numbers_only($row["extension"]);
			$a[] = $row["extension"];
			
			
			//hora início chamada
			if ($row["start_epoch_a"] > 0)
			{
				$a[] = date('Y-m-d H:i:s', $row["start_epoch_a"]);
			}
			else
			{
				$a[] = $row["start_epoch_a"];
			}

			//data hora fim chamada
			if ($row["end_epoch_a"] > 0)
			{
				$a[] = date('Y-m-d H:i:s', $row["end_epoch_a"]);
			}
			else
			{
				$a[] = $row["end_epoch_a"];
			}			
			
			//Tempo Falado
			if($row['answer_duraction_b'] > 0)
			{
				$a[] = gmdate("H:i:s", $row["answer_duraction_b"]);
			}
			elseif($row["answer_duraction_b"] == 0 or strlen($row["answer_duraction_b"]) == 0)
			{
				$a[] = gmdate("H:i:s", 0);
			}
			else
			{
				$a[] = $row["answer_duraction_b"];
			}
			
			//call_transferred_type
			$a[] = Report_Languages($row["call_transferred"], $text);			
			
			//call_transferred_to
			$a[] = Report_Languages($row["call_transferred_to"], $text);
			
			//motivo desligamento
			$a[] = Report_Languages($row["hangup_cause"], $text);
		
			//lado desligamento
			if($row["hangup_side"] == "PUBLIC NETWORK")
			{
				$a[] = $text['label-sql_shutdown_side_public_net'];
			}
			else if($row["hangup_side"] == "RECEIVED TRANSFER - TURN OFF PUBLIC NETWORK")
			{
				$a[] = $text['label-sql_shutdown_side_r_t_p_n'];
			}
			else if($row["hangup_side"] == "RECEIVED TRANSFER - EXTENSION OFF")
			{
				$a[] = $text['label-sql_shutdown_side_r_t_e_o'];
			}
			else if($row["hangup_side"] == "EXTENSION")
			{
				$a[] = $text['label-sql_shutdown_side_extension'];
			}
			else if($row["hangup_side"] == "PABX - TRANSFER")
			{
				$a[] = $text['label-sql_shutdown_side_pabx_t'];
			}
			else if($row["hangup_side"] == "SIMULTANEOUS DISCONNECTION")
			{
				$a[] = $text['label-sql_shutdown_side_s_d'];
			}
			else if($row["hangup_side"] == "PABX - CALL WITHOUT AUDIO")
			{
				$a[] = $text['label-sql_shutdown_side_p_c_w_a'];
			}
			else
			{
				$a[] = $row["hangup_side"];
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
		
		$uuid = $row["uuid_a"];
		$bridge_uuid ="";
		$member_uuid = "";
		$start_epoch = $row["start_epoch_a"];
		if ($is_jive)
		{
			if ($row['direction'] == 'outbound') 
			{
				$cc_record_filename = $row["record_session_b"];
			}
			else
			{
				$cc_record_filename = $row["cc_record_filename_a"];
				
				/** Luiz 14-05-2021 */
				if(strlen($cc_record_filename) == 0)
				{
					$cc_record_filename = $row["record_session_b"];
				}
			}
		}
		else
		{
			$cc_record_filename = $row["record_session_b"];
		}
		
		if (strlen($row["record_session_b"]) == 0)
		{
			if ($is_jive)
			{
				/**/
			}
			else
			{
				$cc_record_filename = $row["record_session_a"];
			}
		}
		
		/*
			transferencia cega 
			O record_session_a tem todo o áudio
		*/
		if (TRUE)
		{
			/*
				/record/recordings/dmzbr.vocom.global/archive/2023/Jan/11/383a6035-ca00-4cfa-ba13-551b71f0b6ee~2023_01_11_14_16_22~2017~9999_1673457382030146_i.mp3
				/record/recordings/dmzbr.vocom.global/archive/2023/Jan/11/383a6035-ca00-4cfa-ba13-551b71f0b6ee~2023_01_11_14_16_22~2017~9999.mp3
			*/
			if ($row['call_transferred'] == 'BLIND') 
			{
				//$cc_record_filename = substr($row["record_session_b"], -2;
			}
		}
		
		/**
			search recording
		*/
		
		$link_recordings = searchRecordings($uuid, $bridge_uuid, $member_uuid, $start_epoch, $cc_record_filename);
		
		/** Tom 01-07-2021 */
		if(strlen($link_recordings) == 0)
		{
			$cc_record_filename = $row["record_session_a"];
		}
		$link_recordings = searchRecordings($uuid, $bridge_uuid, $member_uuid, $start_epoch, $cc_record_filename);
		
		$link_recordings = str_replace('~', '%', $link_recordings);
		
		///*transfer*/
		//if (strlen($row['call_transferred']) > 0)			
		//{
		//	$cc_record_filename = substr($row["record_session_b"], -2);
		//	
		//	$link_recordings_transfer = searchRecordings($uuid, $bridge_uuid, $member_uuid, $start_epoch, $cc_record_filename);
		//	
		//	/** Tom 01-07-2021 */
		//	if(strlen($link_recordings_transfer) == 0)
		//	{
		//		$cc_record_filename = $row["record_session_a"];
		//	}
		//	$link_recordings_transfer = searchRecordings($uuid, $bridge_uuid, $member_uuid, $start_epoch, $cc_record_filename);
		//	
		//	$link_recordings_transfer = str_replace('~', '%', $link_recordings_transfer);
		//}
		
		
		
		$a = array();
		
		//item
		if($aUuidInc % 2 == 0)
		{
			$a[] = "<span class=\"label selectLine\" id=\"tag-$x\" onclick=\"selectLine('tag-$x')\">".$x."</span>";
		}
		else
		{
			$a[] = "<span class=\"label label-warning selectLine\" id=\"tag-$x\" onclick=\"selectLine('tag-$x')\">".$x."</span>";
		}
		
		//	$a[] = $cc_record_filename;
		
		//$a[] = $row["uuid_a"];
		//$a[] = $row["uuid_b"];
		
		/*
			success verde calcinha
			warning amarelo
			danger vermelho
			important vermelho
			info preto
			inverse azul escuro
			white branco
			disable escuro
			primary azul fundo preto
			primary2 fundo branco
			success verde
		*/
		
		//protocol
		$a[] = $row['protocol'];
		
		//estado
		if($row['call_answered'] == "TRUE")
		{
			$a[] = "<span class='badge badge-success'>".$text['label-answered']."</span>";
		}
		else
		{
			//$a[] = "<span class='badge badge-outbound'>".$text['label-not-answered']."</span>";			
			$a[] = "<span class='badge badge-important'>".$text['label-not-answered']."</span>";			
		}
		
		//direction
		/*if($row["direction"] == "inbound")
		{
			$a[] = "<span class='badge badge-inbound'>".$text['label-inbound']."</span>";
		}
		else if($row["direction"] == "outbound")
		{
			$a[] = "<span class='badge badge-outbound'>".$text['label-outbound']."</span>";
		}
		else if($row["direction"] == "outbound_bridge")
		{
			$a[] = "<span class='badge badge-outbound'>".$text['label-outbound']."</span>";
		}
		else if($row["direction"] == "outbound_callback")
		{
			$a[] = "<span class='badge badge-outbound'>".$text['label-outbound']."</span>";
		}
		else if($row["direction"] == "internal")
		{
			$a[] = "<span class='badge badge-warning'>".$text['label-internal']."</span>";
		}
		else
		{
			$a[] = $row["direction"];
		}*/
		if($row["direction_r"] == "inbound_car_pbx")
		{
			$a[] = "<span class='badge badge-inbound'>".$text['label-inbound']."</span>";
			$transferDirection = 'inbound';
		}
		else if($row["direction_r"] == "outbound_pbx_car")
		{
			$a[] = "<span class='badge badge-outbound'>".$text['label-outbound']."</span>";
			$transferDirection = 'outbound';
		}
		else if($row["direction_r"] == "outbound_ext_pbx")
		{
			$a[] = "<span class='badge badge-outbound'>".$text['label-outbound']."</span>";
			$transferDirection = 'outbound';
		}
		else if($row["direction_r"] == "outbound_bridge")
		{
			$a[] = "<span class='badge badge-outbound'>".$text['label-outbound']."</span>";
			$transferDirection = 'outbound';
		}
		else if($row["direction_r"] == "outbound_callback")
		{
			$a[] = "<span class='badge badge-outbound'>".$text['label-outbound']."</span>";
			$transferDirection = 'outbound';
		}
		else if($row["direction_r"] == "local_ext_pbx")
		{
			$a[] = "<span class='badge badge-warning'>".$text['label-internal']."</span>";
			$transferDirection = 'internal';
		}
		else
		{
			$a[] = $row["direction_r"];
		}
		
		//tipo
		if($row["direction"] == "outbound_callback")
		{
			$a[] = "CallBack";
		}
		else if ($row["direction"] == "outbound_bridge")
		{
			$a[] = $text['label-automatic'];
		}
		else
		{
			$a[] = "Manual";
		}
		
		//origem nome
		$a[] = $row["caller_id_name_a"];
		
		//origem
		//$a[] = format_phone_number($row["caller_id_number_a"]);
		$a[] = $row["caller_id_number_a"];
		
		//destino
		//$a[] = format_phone_number($row["destination_number_a"]);
		if ($is_jive) {
			if (preg_match('/(.*)(\+(.*))/', $row["destination_number_a"], $matches)) {
				$a[] = $matches[2];
			}
			else
			{
				$a[] = $row["destination_number_a"];
			}
		}
		else
		{
			$a[] = $row["destination_number_a"];
		}
		
		//ramal
		//$a[] = format_phone_number($row["extension"]);
		$a[] = $row["extension"];
		
		//data hora início chamada
		if ($row["start_epoch_a"] > 0)
		{
			$a[] = date('Y-m-d H:i:s', $row["start_epoch_a"]);
		}
		else
		{
			$a[] = $row["start_epoch_a"];
		}		
		
		//data hora fim chamada
		if ($row["end_epoch_a"] > 0)
		{
			$a[] = date('Y-m-d H:i:s', $row["end_epoch_a"]);
		}
		else
		{
			$a[] = $row["end_epoch_a"];
		}		
		
		//Tempo falado
		if($row['answer_duraction_b'] > 0)
		{
			$a[] = "<span class='badge badge-success'>".gmdate("H:i:s", $row["answer_duraction_b"])."</span>";
		}
		elseif($row["answer_duraction_b"] == 0 or strlen($row["answer_duraction_b"]) == 0)
		{
			$a[] = "<span class='badge badge-important'>".gmdate("H:i:s", 0)."</span>";
		}
		else
		{
			$a[] = $row["answer_duraction_b"];
		}
		
		//call_transferred_type
		$a[] = Report_Languages($row["call_transferred"], $text);		
		
		//call_transferred_to
		$transferExtension = numbers_only($row["call_transferred_to"]);
		$a[] = Report_Languages($row["call_transferred_to"], $text);
				/*
				"<span 
					class=\"transferTab\" onclick=\"transferTab('$transferExtension', '$transferDirection', '$start_stamp_begin', '$start_stamp_end', '".$row["uuid_b_originator"]."')\" >".Report_Languages($row["call_transferred_to"], $text)."		
				</span>
				";
				*/
				
		
		//motivo desligamento
		$a[] = Report_Languages($row["hangup_cause"], $text);		
		
		//lado desligamento
		if($row["hangup_side"] == "PUBLIC NETWORK")
		{
			$a[] = $text['label-sql_shutdown_side_public_net'];
		}
		else if($row["hangup_side"] == "RECEIVED TRANSFER - TURN OFF PUBLIC NETWORK")
		{
			$a[] = $text['label-sql_shutdown_side_r_t_p_n'];
		}
		else if($row["hangup_side"] == "RECEIVED TRANSFER - EXTENSION OFF")
		{
			$a[] = $text['label-sql_shutdown_side_r_t_e_o'];
		}
		else if($row["hangup_side"] == "EXTENSION")
		{
			$a[] = $text['label-sql_shutdown_side_extension'];
		}
		else if($row["hangup_side"] == "PABX - TRANSFER")
		{
			$a[] = $text['label-sql_shutdown_side_pabx_t'];
		}
		else if($row["hangup_side"] == "SIMULTANEOUS DISCONNECTION")
		{
			$a[] = $text['label-sql_shutdown_side_s_d'];
		}
		else if($row["hangup_side"] == "PABX - CALL WITHOUT AUDIO")
		{
			$a[] = $text['label-sql_shutdown_side_p_c_w_a'];
		}
		else
		{
			$a[] = $row["hangup_side"];
		}
		
		//download
		if(strlen($link_recordings))
		{
			$a[] = "<div class='cRecording' ><a href='#' class='bPlay' data-down='../recordings/recording_play.php?a=download&type=rec&filename=". base64_encode($link_recordings) . "' data-play='../recordings/recording_play.php?a=download&type=rec&filename=" . base64_encode($link_recordings) . "' ><i class='fas fa-headphones'></i></a><a class='bDown' href='../recordings/recording_play.php?a=download&type=rec&t=bin&filename=" . base64_encode($link_recordings) . "' ><i class='fas fa-download'></i></a></div>";
		}
		else
		{
			$a[] = "";
		}

		$a[] = "<span data-uuid='".$row['uuid_a']."' class='fxCall'><i class='fal fa-eye'></i></span>";
		
		if(if_group("superadmin"))
		{
			$a[] = "<a href='../xml_cdr/xml_cdr_details.php?uuid=".$row['uuid_a']."' target='_blank' class='label'>"."&nbsp...&nbsp;"."</a>";
			
			if (strlen($row['uuid_b']) > 0)
			{
				$a[] = "<a href='../xml_cdr/xml_cdr_details.php?uuid=".$row['uuid_b']."' target='_blank' class='label'>"."&nbsp...&nbsp;"."</a>";
			}
			else
			{
				$a[] = "";
			}
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
		"draw" 							=> intval($requestData['draw']),
		"recordsTotal" 					=> intval($recordsTotal),
		"recordsFiltered" 				=> intval($recordsTotal),
		"recordsInboundTotal" 			=> intval($result_total_direction_inbound),
		"recordsInboundTotalAnswered" 	=> intval($result_total_direction_inbound_answered),
		"recordsInboundCanceled" 		=> intval($result_inbound_canceled),
		"recordsInboundCapillarity" 	=> intval($result_inbound_capillarity),
		"recordsOutboundTotal" 			=> intval($result_total_direction_outbound),
		"recordsOutboundAnswered"		=> intval($result_outbound_answered),
		"recordsOutboundCanceled"		=> intval($result_outbound_canceled),
		"recordsInternalTotal" 			=> intval($result_total_direction_internal),
		"recordsInternalAnswered"		=> intval($result_total_direction_internal_answered),
		"recordsInternalCanceled"		=> intval($result_internal_canceled),
		"recordsTmaAvg" 				=> $result_answer_avg_time[0]['answer_avg_time'],
		"recordsQueueAvg" 				=> $result_queue_avg_time[0]['queue_avg_time'],
		"recordsTotaCanceled" 			=> intval($result_queue_canceled_total),
		"recordsMaxCalls" 				=> $max_total,
		"data" 							=> $nested
	);
	
	
	fwrite($log_file, $log);
	fclose($log_file);
	
	
	echo json_encode($json_data);
	
	
	function Report_Languages($string, $text)
	{
		$t = '';
		switch ($string) 
		{
			case "ATT_TRANSFER" :
				$t = $text['label-att_transfer'];
				return  $t;
			break;
			case "ANSWERED" :
				$t = $text['label-shutdown_reason_sql22'];
				return  $t;
			break;
			case "BLIND" :
				$t = $text['label-blind'];
				$t = "CEGA";
				return  $t;
			break;
			case "BUSY" :
				$t = $text['label-shutdown_reason_sql21'];
				return $t;
			break;
			case "CALL NORMALLY ANSWERED - EXTENSION OFF" :
				$t = $text['label-shutdown_reason_sql14'];
				return $t;
			break;
			case "CALL NORMALLY ANSWERED - SIMULTANEOUS OFF" :
				$t = $text['label-shutdown_reason_sql15'];
				return $t;
			break;
			case "CALL NORMALLY ANSWERED - PUBLIC NETWORK TURN OFF" :
				$t = $text['label-shutdown_reason_sql16'];
				return $t;
			break;
			case "CALL WITHOUT AUDIO" :
				$t = $text['label-shutdown_reason_sql11'];
				return $t;
			break;
			case "CALL FAILURE" :
				$t = $text['label-shutdown_reason_sql20'];
				return $t;
			break;
			case "CANCELED IN QUEUE" : 
				$t = $text['label-shutdown_reason_sql1'];
				return $t;
			break;
			case "DESTINATION NOT FOUND" :
				$t = $text['label-shutdown_reason_sql19'];
				return $t;
			break;
			case "EXTENSION" :
				$t = $text['label-sql_shutdown_side_extension'];
				return $t;
			break;
			case "EXTENSION REFUSED CALL" :
				$t = $text['label-shutdown_reason_sql8'];
				return $t;
			break;
			case "EXTENSION DID NOT MEET - TOUCHED UNTIL PABX TURN OFF - WITHOUT TRANSFER" :
				$t = $text['label-shutdown_reason_sql9'];
				return $t;
			break;
			case "EXTENSION DID NOT MEET - TOUCHED UNTIL PABX TURN OFF - OVERFLOW" :
				$t = $text['label-shutdown_reason_sql10'];
				return $t;
			break;
			case "EXTENSION - NOT ANSWERED" :
				$t = $text['label-shutdown_reason_extension_not_answered'];
				return $t;
			break;
			case "CANCELED IN QUEUE" :
				$t = $text['label-shutdown_reason_sql1'];
				return $t;
			break;
			case "EXTENSION CANCEL" :
				$t = $text['label-shutdown_reason_sql17'];
				return $t;
			break;
			case "EXTENSION IS NOT MEETING" :
				$t = $text['label-shutdown_reason_sql18'];
				return $t;
			break;
			case "LOSE_RACE" :
				$t = $text['label-shutdown_lose_race'];
				return $t;
			break;
			case "NO AGENT - CUSTOMER HANGUP" :
				$t = $text['label-shutdown_reason_sql2'];
				return $t;
			break;
			case "NO AGENT - PABX HANGUP" : 
				$t = $text['label-shutdown_reason_sql3'];
				return $t;
			break;
			case "NORMAL_CLEARING" : 
				$t = $text['label-shutdown_reason_normal_clearing'];
				return $t;
			break;
			case "NORMAL_UNSPECIFIED" : 
				$t = $text['label-shutdown_reason_normal_clearing'];
				return $t;
			break;
			case "NO_ANSWER" :
				$t = $text['label-shutdown_reason_not_answered'];
				return  $t;
			break;
			case "NOT ANSWERED" :
				$t = $text['label-shutdown_reason_not_answered'];
				return  $t;
			break;
			case "NO AGENT - CALL TIMEOUT" :
				$t = $text['label-shutdown_reason_sql4'];
				return $t;
			break;
			case "ORIGINATOR_CANCEL" :
				$t = $text['label-shutdown_originator_cancel'];
				return $t;
			break;
			case "OVERFLOW QUEUE" :
				$t = $text['label-shutdown_reason_sql13'];
				return $t;
			break;
			case "RECEIVED TRANSFER - NORMAL DISCONNECTION" :
				$t = $text['label-shutdown_reason_sql5'];
				return $t;
			break;
			case "RECEIVED TRANSFER - CANCELED TRANSFER" : 
				$t = $text['label-shutdown_reason_sql6'];
				return $t;
			break;
			case "RECEIVED TRANSFER - TURN OFF PUBLIC NETWORK" :
				$t = $text['label-sql_shutdown_side_r_t_p_n'];
				return $t;
			break;
			case "RECEIVED TRANSFER - RECEIVED TRANSFER - EXTENSION OFF" :
				$t = $text['label-sql_shutdown_side_r_t_e_o'];
				return $t;
			break;
			case "RECEIVED TRANSFER - MISSED TRANSFER" :
				$t = $text['label-shutdown_reason_sql7'];
				return $t;
			break;
			case "SIMULTANEOUS DISCONNECTION" :
				$t = $text['label-sql_shutdown_side_s_d'];
				return $t;
			break;
			case "TIMEOUT QUEUE" :
				$t = $text['label-shutdown_reason_sql12'];
				return $t;
			break;
			case "USER_BUSY" :
				$t = $text['label-shutdown_reason_sql21'];
				return $t;
			break;
			case "PABX - CALL WITHOUT AUDIO" :
				$t = $text['label-sql_shutdown_side_p_c_w_a'];
				return $t;
			break;
			case "PABX - TRANSFER" :
				$t = $text['label-sql_shutdown_side_pabx_t'];
				return $t;
			break;
			case "PUBLIC NETWORK" :
				$t = $text['label-sql_shutdown_side_public_net'];
				return $t;
			break;
		
			default : return $string;
		}
	}
?>
