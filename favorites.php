<?php
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

	if (permission_exists('omnichannel_favorites_view'))
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
	
	require_once "app_languages.php";
	foreach($text as $key => $value)
	{
		$text[$key] = $value[$_SESSION['domain']['language']['code']];
	}
	
	/**
		additional includes
	*/
	
	require_once "resources/header.php";
	
	require 'resources/guzzle/vendor/autoload.php';

	use GuzzleHttp\Client;
	use GuzzleHttp\Exception\RequestException;
	use GuzzleHttp\Psr7\Request;

	/**
		title
	*/
	
	$document['title'] = $text['title-omnichannel_favorites'];
	
	$userOmnichannelCore = user_omnichannel_core($db);
	
	if(strlen($userOmnichannelCore["base_uri"]) == 0)
	{
		require_once "resources/omnichannel_not_enabled.php";
	}
	
	try
	{
		$client = new GuzzleHttp\Client(['verify' => false, 'base_uri' => $userOmnichannelCore["base_uri"]]);
		$response = $client->request('GET', 'api/v1/channel/favorites/', [
			'headers' => 	[
								'Content-Type' 		=> 'application/json',
								'X-Auth-Token'     	=> $userOmnichannelCore["auth_token"],
								'X-User-Id'      	=> $userOmnichannelCore["user_id"]
							],
			
		]);
		
		$rBody = json_decode($response->getBody());
	}
	catch (\GuzzleHttp\Exception\RequestException $e)
	{
		require_once "resources/omnichannel_exception.php";
	}
	
?>
	<style>
		.label-favorites{
			color: #626262;
			background-color: #e6e6e6;
			border-radius: 10px;
		}
	</style>
	
	<div class="card card-transparent">
		<div class="card-header ">
			<div class="card-title">
				<?= $text['title-omnichannel_favorites']; ?>
			</div>
			<div class="card-description">
				<?= $text['description-omnichannel_favorites']; ?>
			</div>
			<div class="clearfix"></div>
			<?php
			if(permission_exists('omnichannel_favorites_add'))
			{
				?>
					<div class="pull-right">
						<div class="col-xs-6">
							<a class="btn btn-primary btn-primary-add btn-cons pull-right" href="favorites_edit.php" ><?= $text['button-add']; ?></a>
						</div>
					</div>
				<?php
			}
			?>
			<div class="clearfix"></div>
			<div class="col-xs-12">
				<div class="export-options-container">
				</div>
			</div>
			<div class="clearfix"></div>
			<div class="col-xs-12">
				<input type="text" id="search-table" class="search-table form-control" placeholder="Search">
			</div>
			<div class="clearfix"></div>
		</div>
		<div class="card-block">
			<table class="table table-hover table-responsive-block" id="tableWithExportOptions">
				<thead>
					<tr>
						<th style="width: 120px;"><?= $text['label-tag']; ?></th>
						<th><?= $text['label-message']; ?></th>
						<th class="table-options"></th>
					</tr>
				</thead>
				<tbody>
					<?php
						foreach($rBody as $row)
						{
						?>
							<tr>
								<td class="v-align-middle">
									<p><span class="label label-favorites"><?= $row->tag; ?></span></p>
								</td>
								<td class="v-align-middle">
									<p><?= $row->name; ?></p>
								</td>
								<td class="v-align-middle">
									<?php
									if (permission_exists('omnichannel_favorites_edit'))
									{
										?>
											<a href='favorites_edit.php?id=<?= $row->_id; ?>' alt='<?= $text['button-edit']; ?>'><i class="fal fa-edit"></i></a>
										<?php
									}
									?>
									
									<?php
									if (permission_exists('omnichannel_favorites_delete'))
									{
										?>
											<a href='favorites_delete.php?id=<?= $row->_id; ?>' alt='<?= $text['button-delete']; ?>' onclick="return confirm('<?= $text['confirm-delete']; ?>')"><i class="fal fa-trash-alt"></i></a>
										<?php
									}
									?>
								</td>
							</tr>
						<?php
						}
					?>
				</tbody>
			</table>
		</div>
	</div>
	
<?php

	/**
		include the footer
	*/
	
	require_once "resources/footer.php";
?>
