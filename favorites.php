<?php
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

	if (!permission_exists('omnichannel_favorites_view')) {
		echo "access denied";
		exit;
	}

	// Suporte multi-linguagem
	require_once "app_languages.php";
	foreach ($text as $key => $value) {
		$text[$key] = $value[$_SESSION['domain']['language']['code']];
	}

	// Includes adicionais
	require_once "resources/header.php";
	require 'resources/guzzle/vendor/autoload.php';

	use GuzzleHttp\Client;
	use GuzzleHttp\Exception\RequestException;

	// Título do documento
	$document['title'] = $text['title-omnichannel_favorites'];
	
	$userOmnichannelCore = user_omnichannel_core($db);

	// Verificar se o Omnichannel está habilitado
	if (empty($userOmnichannelCore["base_uri"])) {
		require_once "resources/omnichannel_not_enabled.php";
		exit;
	}

	try {
		$client = new Client(['verify' => false, 'base_uri' => $userOmnichannelCore["base_uri"]]);
		$response = $client->request('GET', 'api/v1/channel/favorites/', [
			'headers' => [
				'Content-Type' => 'application/json',
				'X-Auth-Token' => $userOmnichannelCore["auth_token"],
				'X-User-Id' => $userOmnichannelCore["user_id"]
			],
		]);
		
		$rBody = json_decode($response->getBody(), true);
	} catch (RequestException $e) {
		require_once "resources/omnichannel_exception.php";
		exit;
	}

	// Manipulação dos resultados
	$rFavorites = $rBody['favorites'] ?? [];
	$rUsersAgent = $rBody['usersAgent'] ?? [];
	$rDepartments = $rBody['departments'] ?? [];
?>
<style>
	.label-favorites {
		color: #626262;
		background-color: #e6e6e6;
		border-radius: 10px;
	}
	.badge {
		font-family: 'Montserrat';
		font-size: 12px;
		font-weight: 500;
		background: transparent;
		background-color: transparent;
		padding: 8px 10px 0px 10px;
		border-radius: 6px;
		min-width: 15px;
		min-height: 20px;
	}
	.badge-owner {
		margin: 2px 2px;
		color: #fff;
		background-color: #84a3e1;
	}
</style>

<div class="card card-transparent">
	<div class="card-header">
		<div class="card-title">
			<?= $text['title-omnichannel_favorites']; ?>
		</div>
		<div class="card-description">
			<?= $text['description-omnichannel_favorites']; ?>
		</div>
		<div class="clearfix"></div>
		<?php if (permission_exists('omnichannel_favorites_add')) : ?>
			<div class="pull-right">
				<a class="btn btn-primary btn-primary-add btn-cons pull-right" href="favorites_edit.php"><?= $text['button-add']; ?></a>
			</div>
		<?php endif; ?>
		<div class="clearfix"></div>
		<input type="text" id="search-table" class="search-table form-control" placeholder="Search">
		<div class="clearfix"></div>
	</div>

	<div class="card-block">
		<table class="table table-hover table-responsive-block" id="tableWithExportOptions">
			<thead>
				<tr>
					<th style="width: 120px;"><?= $text['label-tag']; ?></th>
					<th><?= $text['label-message']; ?></th>
					<th><?= $text['label-users']; ?></th>
					<th><?= $text['label-department']; ?></th>
					<th class="table-options"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($rFavorites as $favorite) : ?>
					<tr>
						<td class="v-align-middle">
							<p><span class="label label-favorites"><?= htmlspecialchars($favorite['tag']); ?></span></p>
						</td>
						<td class="v-align-middle">
							<p><?= htmlspecialchars($favorite['name']); ?></p>
						</td>
						<td class="v-align-middle">
							<?php foreach ($rUsersAgent as $user) : ?>
								<?php if (in_array($user["_id"], $favorite['userId'])) : ?>
									<span class="badge badge-owner"><?= htmlspecialchars($user['name']); ?></span>
								<?php endif; ?>
							<?php endforeach; ?>
						</td>
						<td class="v-align-middle">
							<?php foreach ($rDepartments as $department) : ?>
								<?php if (in_array($department["_id"], $favorite['departmentId'])) : ?>
									<span class="badge badge-owner"><?= htmlspecialchars($department['name']); ?></span>
								<?php endif; ?>
							<?php endforeach; ?>
						</td>
						<td class="v-align-middle">
							<?php if (permission_exists('omnichannel_favorites_edit')) : ?>
								<a href='favorites_edit.php?id=<?= $favorite["_id"]; ?>'><i class="fal fa-edit"></i></a>
							<?php endif; ?>
							<?php if (permission_exists('omnichannel_favorites_delete')) : ?>
								<a href='favorites_delete.php?id=<?= $favorite["_id"]; ?>' onclick="return confirm('<?= $text['confirm-delete']; ?>')"><i class="fal fa-trash-alt"></i></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<?php
	require_once "resources/footer.php";
?>
<script>
	// Função de pesquisa otimizada
	document.getElementById('search-table').addEventListener('input', function () {
		var filter = this.value.toLowerCase();
		var rows = document.querySelectorAll('#tableWithExportOptions tbody tr');
		rows.forEach(function (row) {
			var tag = row.querySelector('td:nth-child(1) p').innerText.toLowerCase();
			var name = row.querySelector('td:nth-child(2) p').innerText.toLowerCase();
			if (tag.includes(filter) || name.includes(filter)) {
				row.style.display = '';
			} else {
				row.style.display = 'none';
			}
		});
	});
</script>
