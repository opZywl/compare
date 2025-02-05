CREATE TABLE `cdr_variables` (
	`uuid` CHAR(36) NOT NULL COLLATE 'utf8_unicode_ci',
	`sip_h_referred_by` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`referred_by_user` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`sip_refer_to` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`sip_redirect_contact_user_0` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`sip_reason` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`endpoint_disposition` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`sip_hangup_disposition` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`transfer_source` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`transfer_destination` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`transfer_to` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`caller_id_name` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`caller_id_number` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`callee_id_number` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`sip_from_user` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`sip_to_user` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`destination_number` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`destination_number_last` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`gateway_uuid` CHAR(36) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`modalidade` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`uuid_record_name` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`cc_record_filename` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`last_app` VARCHAR(100) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`dialed_user` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`pickup` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`picked_up_uuid` CHAR(36) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`pick_num` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`effective_caller_id_number` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`hangup_cause` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`record_session` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`caller_id_number_normalized` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`destination_number_normalized` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`digits` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`originate_disposition` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`sip_req_user` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`protocol` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`playback_ms` DECIMAL(10,0) NULL DEFAULT NULL,
	PRIMARY KEY (`uuid`) USING BTREE,
	INDEX `destination_number_normalized_idx` (`destination_number_normalized`) USING BTREE,
	INDEX `caller_id_number_idx` (`caller_id_number_normalized`) USING BTREE,
	INDEX `sip_to_user_idx` (`sip_to_user`) USING BTREE,
	INDEX `caller_id_number_normalized_idx` (`caller_id_number_normalized`) USING BTREE,
	INDEX `destination_number_idx` (`destination_number`) USING BTREE,
	INDEX `sip_from_user_idx` (`sip_from_user`) USING BTREE,
	INDEX `sip_req_user_idx` (`sip_req_user`) USING BTREE,
	INDEX `gateway_uuid` (`gateway_uuid`) USING BTREE
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;
