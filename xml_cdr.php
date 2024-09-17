// old
<?php
require_once "root.php";
require_once "resources/require.php";
require_once "resources/check_auth.php";
require_once "app_languages.php";

if (permission_exists('xml_cdr_view')) {
	//access granted
} else {
	echo "access denied";
	exit;
}

ini_set('memory_limit', '-1');

/**
		add multi-lingual support
 */

foreach ($text as $key => $value) {
	$text[$key] = $value[$_SESSION['domain']['language']['code']];
}

/**
		title
 */

$document['title'] = $text['title'];

/**
		additional includes
 */

require_once "resources/header.php";

/**
		xml cdr include
 */
?>

<style>
	.dataTable>thead>tr>th,
	.dataTable>tbody>tr>td {
		white-space: nowrap;
	}
</style>

<div class="card card-transparent card-report">
	<div class="card-header ">
		<div class="card-title">
			<?= $text['title']; ?>
		</div>
		<div class="card-description">
			<?= $text['description']; ?>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="col-md-12">
				<div class="card card-filter-simple card-borderless">
					<ul class="nav nav-tabs nav-tabs-simple" role="tablist" data-init-reponsive-tabs="dropdownfx">
						<li class="nav-item">
							<a class="active" data-toggle="tab" role="tab" data-target="#simpleFilter" href="#"><?= $text['label-filter-simple']; ?></a>
						</li>
					</ul>
					<div class="tab-content">
						<div class="tab-pane active" id="simpleFilter">
							<div class="row column-seperation">
								<button class="btn btn-rounded btn-filter-simple btn-primary m-b-10" data-filter="TODAY"><?= $text['label-today']; ?></button>
								<button class="btn btn-rounded btn-filter-simple btn-primary m-b-10" data-filter="15MINUTES"><?= $text['label-15-minutes']; ?></button>
								<button class="btn btn-rounded btn-filter-simple btn-primary m-b-10" data-filter="30MINUTES"><?= $text['label-30-minutes']; ?></button>
								<button class="btn btn-rounded btn-filter-simple btn-primary m-b-10" data-filter="1HOUR"><?= $text['label-1-hour']; ?></button>
								<button class="btn btn-rounded btn-filter-simple btn-primary m-b-10" data-filter="2HOURS"><?= $text['label-2-hour']; ?></button>
								<button class="btn btn-rounded btn-filter-simple btn-primary m-b-10" data-filter="3HOURS"><?= $text['label-3-hour']; ?></button>
								<button class="btn btn-rounded btn-filter-simple btn-primary m-b-10" data-filter="6HOURS"><?= $text['label-6-hour']; ?></button>
								<button class="btn btn-rounded btn-filter-simple btn-primary m-b-10" data-filter="12HOURS"><?= $text['label-12-hour']; ?></button>
								<button class="btn btn-rounded btn-filter-simple btn-primary m-b-10" data-filter="1LASTMONTH"><?= $text['label-last-month']; ?></button>
								<button class="btn btn-rounded btn-filter-simple btn-primary m-b-10" data-filter="1MONTH"><?= $text['label-month']; ?></button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="card-block">
		<form role="form" method="POST" class="formReport" id="formReport">
			<div class="row">
				<div class="col-md-12">
					<div class="export-options-container export-options-report pull-right">
						<div class="exportOptions">
							<button type="button" id="eCSV" class="btn"><span><i class="fal fa-file-excel"></i></span></button>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-3">
					<div class="form-group form-group-default input-group">
						<div class="form-input-group">
							<label><?= $text['label-start_range']; ?></label>
							<input type="text" name="DATA_INI" id="DATA_INI_HH_MM" value="<?= date('Y-m-d 00:00'); ?>" class="form-control datepicker">
						</div>
						<div class="input-group-addon">
							<i class="fa fa-calendar"></i>
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="form-group form-group-default input-group">
						<div class="form-input-group">
							<label><?= $text['label-end_range']; ?></label>
							<input type="email" name="DATA_END" id="DATA_END_HH_MM" value="<?= date('Y-m-d 23:59'); ?>" class="form-control datepicker">
						</div>
						<div class="input-group-addon">
							<i class="fa fa-calendar"></i>
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="form-group form-group-default form-group-default-select2">
						<label><?= $text['label-survey']; ?></label>
						<select name="SURVEY" id="SURVEY" class=" full-width" data-init-plugin="select2">
							<?php
							$sql  = "select survey_name, survey_uuid ";
							$sql .= "from v_surveys ";
							$sql .= "where domain_uuid = '" . $_SESSION['domain_uuid'] . "' ";
							$sql .= "order by ";
							$sql .= "survey_name asc ";
							$prep_statement = $db->prepare(check_sql($sql));
							$prep_statement->execute();
							$result_e = $prep_statement->fetchAll(PDO::FETCH_NAMED);
							foreach ($result_e as $row) {
								echo "<option value='" . $row['survey_uuid'] . "' >" . $row['survey_name'] . "</option>\n";
							}
							unset($prep_statement);
							?>
						</select>
					</div>
				</div>
				<div class="col-md-3">
					<div class="form-group form-group-default form-group-default-select2">
						<label><?= $text['label-queue']; ?></label>
						<select name="CC_QUEUE" id="CC_QUEUE" class=" full-width" data-init-plugin="select2">
							<?php if (!if_group("cc_manager")) {
							?>
								<option value=""><?= $text['label-all']; ?></option>
							<?php
							}
							?>
							<?php
							$sql = "select queue_extension, queue_name from v_call_center_queues ";
							$sql .= "where domain_uuid = '$domain_uuid' ";
							if (if_group("cc_manager")) {
								$sql .= "and queue_cc_manager LIKE '%@" . $_SESSION['username'] . "@%' ";
							}
							$sql .= "order by ";
							$sql .= "queue_extension asc ";
							$prep_statement = $db->prepare(check_sql($sql));
							$prep_statement->execute();
							$result_e = $prep_statement->fetchAll(PDO::FETCH_NAMED);
							foreach ($result_e as $row) {
								$selected = ($cc_queue == $row['queue_name']) ? "selected" : null;
								echo "<option value='" . $row['queue_name'] . "' " . $selected . ">" . $row['queue_extension'] . " - " . $row['queue_name'] . "</option>\n";
							}
							unset($prep_statement);
							?>
						</select>
					</div>
				</div>
				<div class="col-md-3">
					<div class="form-group form-group-default form-group-default-select2">
						<label><?= $text['label-agent']; ?></label>
						<select name="CC_AGENT" id="CC_AGENT" class=" full-width" data-init-plugin="select2">
							<option value=""><?= $text['label-all']; ?></option>
							<?php
							$sql  = "select agent_name cc_agent  ";
							$sql .= "from v_call_center_agents ";
							$sql .= "where domain_uuid = '" . $_SESSION['domain_uuid'] . "' ";
							if (if_group("cc_manager")) {
								$sql .= "and supervisor_name LIKE '%@" . $_SESSION['username'] . "@%' ";
							}
							$sql .= "order by ";
							$sql .= "cc_agent asc ";
							$prep_statement = $db->prepare(check_sql($sql));
							$prep_statement->execute();
							$result_e = $prep_statement->fetchAll(PDO::FETCH_NAMED);
							foreach ($result_e as $row) {
								echo "<option value='" . $row['cc_agent'] . "'>" . $row['cc_agent'] . "</option>\n";
							}
							unset($prep_statement);
							?>
						</select>
					</div>
				</div>
				<div class="col-md-3">
					<div class="form-group form-group-default">
						<label><?= $text['label-protocol']; ?></label>
						<input type="text" class="form-control" name="PROTOCOL" id="PROTOCOL" maxlength='255' value="">
					</div>
				</div>
				<div class="col-md-3">
					<div class="form-group form-group-default form-group-default-select2">
						<label><?= $text['label-question']; ?></label>
						<select name="QUESTION" id="QUESTION" class=" full-width" data-init-plugin="select2">
						</select>
					</div>
				</div>
				<div class="col-md-3">
					<div class="form-group form-group-default form-group-default-select2">
						<label><?= $text['label-survey_answer']; ?></label>
						<select name="SURVEY_DIGIT" id="SURVEY_DIGIT" class=" full-width" data-init-plugin="select2">
							<option value=""><?= $text['label-all']; ?></option>
							<option value="true"><?= $text['label-survey_answer_true']; ?></option>
							<option value="false"><?= $text['label-survey_answer_false']; ?></option>
						</select>
					</div>
				</div>
				<div class="col-md-12">
					<div class="container-report-btn">
						<div class="pull-right">
							<a href="xml_cdr.php" class="btn btn-report"><?= $text['button-reset']; ?></a>
							<button type="button" id="gtb" class="btn btn-report"><?= $text['button-search']; ?></button>
						</div>
					</div>
				</div>
			</div>
		</form>
		<div class="row">
			<div id="charts">
			</div>
			<div class="clearfix"></div>
			<div id="rtb">
			</div>
		</div>
	</div>
</div>
<?php

/**
		show the footer
 */

require_once "resources/footer.php";

?>

<script>
	$(document).ready(function() {

		//carregar perguntas da pesquisa selecionada
		const survey = $('#SURVEY').val();
		$.get(`filter_question.php?survey=${survey}`, function(data) {
			// Adiciona o HTML obtido ao elemento <select>
			$('#QUESTION').html(data);
		});


		$("#SURVEY").change(function() {
			const survey = $(this).val();
			$.get(`filter_question.php?survey=${survey}`, function(data) {
				// Adiciona o HTML obtido ao elemento <select>
				$('#QUESTION').html(data);
			});
		});


		$("#QUESTION").change(function() {
			const survey = $('#SURVEY').val();
			const question = $(this).val();
			$.get(`filter_answer_digit.php?survey=${survey}&question=${question}`, function(data) {
				// Adiciona o HTML obtido ao elemento <select>
				$('#SURVEY_DIGIT').html(data);
			});
		});


	});
</script>
