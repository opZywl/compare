CREATE TABLE `cdr_cc` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`uuid` CHAR(36) NOT NULL COLLATE 'utf8_unicode_ci',
	`start_epoch` DECIMAL(10,0) NULL DEFAULT NULL,
	`answer_epoch` DECIMAL(10,0) NULL DEFAULT NULL,
	`end_epoch` DECIMAL(10,0) NULL DEFAULT NULL,
	`cc_queue_joined_epoch` DECIMAL(10,0) NULL DEFAULT NULL,
	`cc_queue_answered_epoch` DECIMAL(10,0) NULL DEFAULT NULL,
	`cc_queue_canceled_epoch` DECIMAL(10,0) NULL DEFAULT NULL,
	`cc_queue_end_epoch` DECIMAL(10,0) NULL DEFAULT NULL,
	`cc_queue_inc_timeout_epoch` DECIMAL(10,0) NULL DEFAULT NULL,
	`cc_issue_sound` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`cc_side` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`cc_member_uuid` CHAR(36) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`cc_queue` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`cc_member_session_uuid` CHAR(36) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`cc_agent` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`cc_agent_outbound` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`cc_agent_type` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	`cc_queue_inc` INT(11) NULL DEFAULT NULL,
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `start_epoch_idx` (`start_epoch`) USING BTREE,
	INDEX `end_epoch_idx` (`end_epoch`) USING BTREE,
	INDEX `cc_side_idx` (`cc_side`) USING BTREE,
	INDEX `uuid_idx` (`uuid`) USING BTREE,
	INDEX `cc_queue` (`cc_queue`) USING BTREE,
	INDEX `cc_agent` (`cc_agent`) USING BTREE,
	INDEX `cc_member_session_uuid` (`cc_member_session_uuid`) USING BTREE,
	INDEX `cc_issue_sound` (`cc_issue_sound`) USING BTREE
)
COLLATE='utf8_unicode_ci'
ENGINE=InnoDB
ROW_FORMAT=COMPACT
AUTO_INCREMENT=854160
;
