CREATE TABLE `cdr_main` (
	`uuid` CHAR(36) NOT NULL COLLATE 'utf8_unicode_ci',
	`bridge_uuid` CHAR(36) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`start_epoch` DECIMAL(10,0) NULL DEFAULT NULL,
	`answer_epoch` DECIMAL(10,0) NULL DEFAULT NULL,
	`end_epoch` DECIMAL(10,0) NULL DEFAULT NULL,
	`direction` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`sofia_profile_name` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`sip_from_user` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`sip_to_user` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`dialed_user` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`destination_number` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`caller_id_number` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`is_callback` CHAR(1) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`call_back_params` VARCHAR(45) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`originator` CHAR(36) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	PRIMARY KEY (`uuid`) USING BTREE,
	INDEX `main_idx` (`start_epoch`, `direction`, `sofia_profile_name`, `bridge_uuid`, `caller_id_number`, `destination_number`, `sip_from_user`, `sip_to_user`, `dialed_user`, `uuid`, `answer_epoch`, `end_epoch`, `is_callback`, `call_back_params`, `originator`) USING BTREE,
	INDEX `originator` (`originator`) USING BTREE
)
COLLATE='utf8_unicode_ci'
ENGINE=InnoDB
;
