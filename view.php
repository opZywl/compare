<?php
// novo
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
?>

	<script>
		$(document).ready(function()
		{
			$('#tPagination').DataTable({
				"bLengthChange": false,
				"iDisplayLength": 100,
				"searching": false,
				"info": false,
				"processing": false,
				"serverSide": true,
				"orderable": false,
				"ordering": false,
				"ajax": {
					type: "POST",
					url: "model.php",
					dataFilter: function(data)
					{
						var json = $.parseJSON(data);
						$("#TotRecord").text(json.recordsFiltered);
						return data;
					},
					data: {
							"SURVEY": $("select#SURVEY").val(),
							"CC_QUEUE": $("select#CC_QUEUE").val(),
							"CC_AGENT": $("select#CC_AGENT").val(),
							"PROTOCOL": $("#PROTOCOL").val(),
							"DATA_INI": $("#DATA_INI_HH_MM").val(),
							"DATA_END": $("#DATA_END_HH_MM").val()
						  },					
					error: function()
					{
						$("#tPagination").append('<tbody><tr><th colspan="3">No data found in the server</th></tr></tbody>');
					},
					beforeSend: function()
					{
						$("#loader").html('<div class="loader-backdrop fade show"><div class="loader"><i class="fas fa-spinner fa-spin"></i><div class="clearfix"></div><span>Loading...</span></div></div>');
					},
					complete: function()
					{
						$(".loader-backdrop").remove();
					}
				}
			});
		});
	</script>
	<div class="clearfix"></div>
	<div class="col-md-12">
		<div class="container-total-records">
			<h5 ><?= $text['label-total-records']; ?> : <span id="TotRecord"></span></h5>
		</div>
	</div>
	<div class="clearfix"></div>
	<div class="col-md-12">
		<div class="export-options-container">
		</div>
	</div>
	<div class="clearfix"></div>
	<div class="col-md-12">
		<table class="table table-striped table-responsive-block dataTable no-footer" style="width: 100%;" width="100%" id="tPagination" >
			<thead>
				<tr>
					<th></th>
					<th><?= $text['label-direction']; ?></th>
					<th><?= $text['label-number']; ?></th>
					<th><?= $text['label-protocol']; ?></th>
					<th><?= $text['label-date']; ?></th>
					<th><?= $text['label-queue']; ?></th>
					<th><?= $text['label-agent']; ?></th>
					<th><?= $text['label-survey']; ?></th>
					<th><?= $text['label-question']; ?></th>
					<th><?= $text['label-answer']; ?></th>
					<th><?= $text['label-digit']; ?></th>
					<th><?= $text['label-download']; ?></th>
				</tr>
			</thead>
		</table>
	</div>
