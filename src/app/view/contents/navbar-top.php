<?php
$pathImage = path2url(sprintf('%s/image', DIR_ASSET));
?>
<nav class="top-navbar navbar navbar-expand-md px-3 animate__animated animate__fadeIn">
	<button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
		<span class="navbar-toggler-icon"></span>
	</button>
	<div class="offcanvas d-block d-md-none offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
		<div class="offcanvas-header">
			<h5 class="offcanvas-title" id="offcanvasNavbarLabel">Offcanvas</h5>
			<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
		</div>
		<div class="offcanvas-body">
			<ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
				<li class="nav-item">
					<a class="nav-link active" aria-current="page" href="#">Home</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" href="#">Link</a>
				</li>
				<li class="nav-item dropdown">
					<a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
						Dropdown
					</a>
					<ul class="dropdown-menu">
						<li><a class="dropdown-item" href="#">Action</a></li>
						<li><a class="dropdown-item" href="#">Another action</a></li>
						<li>
							<hr class="dropdown-divider">
						</li>
						<li><a class="dropdown-item" href="#">Something else here</a></li>
					</ul>
				</li>
			</ul>
			<form class="d-flex mt-3" role="search">
				<input class="form-control me-2" type="search" placeholder="Search" aria-label="Search">
				<button class="btn btn-outline-success" type="submit">Search</button>
			</form>
		</div>
	</div>
	<div class="navbar-content w-100">
		<section class="row g-0 align-items-center">
			<div class="col-auto mr-auto me-auto">
				<div class="widget-item widget-date_time">
					<img src="<?= path2url(sprintf('%s/image/icons/ri_time-fill-%s.svg', DIR_ASSET, $buildVersion)); ?>" alt="Icons Datetime"/>
					<p class="wdt-value"><?= strftime('%A, %d/%m/%y', time()); ?></p>
				</div>
			</div>
			<div class="col-auto">
				<div class="widget-item widget-user_online">
					<?php
					$maxShowItem = 5;
					$listUsers = array();
					switch(strtolower(trim($userData->usr->role))) {
						case 'ho':
							// Serve data
							$listUsers = db_runQuery(array(
								'config_array' => $configMysql,
								'database_index' => 0,
								'input' => array('c', $userData->nik),
								'query' => sprintf('SELECT NIK, NAMA, META_AVATAR FROM %s WHERE NIK IN (SELECT NIK FROM %s WHERE LEVEL_CODE != ? AND NIK != ?) AND (APPROVAL_FLAG != -1 OR APPROVAL_FLAG IS NULL);', $appConfig['CORE']['tb_prefix'].'user_account', $appConfig['CORE']['tb_prefix'].'user_privileges'),
								'param' => 'ss',
								'getData' => true,
								'getAllRow' => true,
								'callback' => function($response) use($configMysql, $appConfig) {
									$output = $response['data'];
									$result = array(
										'all_nik' => array(),
										'all_user' => array() 
									);
									if(!isEmptyVar($output) && $output !== 'ZERO_DATA' && count($output) >= 1) {
										foreach($output as $perUser) {
											$result['all_nik'][] = $perUser['NIK'];
											$result['all_user'][$perUser['NIK']] = array(
												'nik' => $perUser['NIK'],
												'name' => ucwords(strtolower($perUser['NAMA'])),
												'avatar' => (!isEmptyVar($perUser['META_AVATAR'])) ? sprintf('%s/files/%s', getURI(2), $perUser['META_AVATAR']) : path2url(sprintf('%s/image/illustrations/avatar-%s.png', DIR_ASSET, $appConfig['CORE']['app_build_version'])),
												'status' => 'offline',
												'timeElapsed' => '-',
												'timeStamp' => 0
											);
										}
										if(count($result['all_nik']) >= 1) {
											$listUserSession = db_runQuery(array(
												'config_array' => $configMysql,
												'database_index' => 0,
												'input' => $result['all_nik'],
												'query' => sprintf('SELECT NIK, LAST_ACTIVITY FROM %s WHERE NIK IN (%s);', $appConfig['CORE']['tb_prefix'].'loggedin_device', implode(',', array_fill(0, count($result['all_nik']), '?'))),
												'param' => str_repeat('s', count($result['all_nik'])),
												'getData' => true,
												'getAllRow' => true
											));
											if(!isEmptyVar($listUserSession) && $listUserSession !== 'ZERO_DATA' && count($listUserSession) >= 1) {
												foreach($listUserSession as $perUser2) {
													if(isset($result['all_user'][$perUser2['NIK']])) {
														$lastActivity = time() - $perUser2['LAST_ACTIVITY'];
														if($lastActivity < (5*60)) { // If < 5-minutes user is online
															$result['all_user'][$perUser2['NIK']]['status'] = 'online';
															$result['all_user'][$perUser2['NIK']]['timeStamp'] = $lastActivity;
															unset($result['all_user'][$perUser2['NIK']]['timeElapsed']);
														} else if($lastActivity > (5*60) && $lastActivity < $appConfig['CORE']['session_user']['expire_time']) { // If > 5-minutes & < [session_expire_time]-minutes user is in idle
															$result['all_user'][$perUser2['NIK']]['status'] = 'idle';
															$result['all_user'][$perUser2['NIK']]['timeElapsed'] = rf_timeElapsedString(date('Y-m-d H:i:s', $perUser2['LAST_ACTIVITY']));
															$result['all_user'][$perUser2['NIK']]['timeStamp'] = $lastActivity;
														} else {
															$result['all_user'][$perUser2['NIK']]['status'] = 'offline';
															$result['all_user'][$perUser2['NIK']]['timeStamp'] = $lastActivity;
															unset($result['all_user'][$perUser2['NIK']]['timeElapsed']);
														}
													}
												}
												foreach($result['all_user'] as &$perUser3) {
													if(array_key_exists('status', $perUser3) && array_key_exists('timeStamp', $perUser3)) {
														if($perUser3['status'] === 'offline' && $perUser3['timeStamp'] == 0) {
															unset($perUser3['timeStamp']);
														}
													}
												}
											}
										}
									}
									return $result['all_user'];
								}
							));
							if(count($listUsers) >= 1) {
								$_tmpArray = array(
									'online' => array(),
									'idle' => array(),
									'offline' => array()
								);
								// Group by status
								foreach($listUsers as $perUser) {
									switch($perUser['status']) {
										case 'online': $_tmpArray['online'][] = $perUser; break;
										case 'idle': $_tmpArray['idle'][] = $perUser; break;
										case 'offline': $_tmpArray['offline'][] = $perUser; break;
										default: break;
									}
								}

								// Clear/resets list responder
								unset($listUsers);
								$listUsers = array();

								// Sort by latest activity
								foreach($_tmpArray as $statusKey => &$statusVal) {
									usort($statusVal, function ($item1, $item2) {
										return (array_key_exists('timeStamp', $item1) && array_key_exists('timeStamp', $item2)) ? $item1['timeStamp'] > $item2['timeStamp'] : false;
									});
								}
								unset($statusVal);

								// Clear un-used key
								foreach($_tmpArray as $statusKey => &$statusVal) {
									switch($statusKey) {
										case 'online': case 'offline':
											foreach($statusVal as &$perRelatedUser) {
												unset($perRelatedUser['timeElapsed']);
												unset($perRelatedUser['timeStamp']);
											}
											unset($perRelatedUser);
										break;
										case 'idle':
											foreach($statusVal as &$perRelatedUser) {
												unset($perRelatedUser['timeStamp']);
											}
											unset($perRelatedUser);
										break;
									}
								}
								unset($statusVal);

								// Re-insert again
								$listUsers = array_merge(array_values($_tmpArray['online']), array_values($_tmpArray['idle']), array_values($_tmpArray['offline']));
							}
						break;
					}
					// Output result
					if(count($listUsers) >= 1) {
						foreach($listUsers as $idx => $perRelatedUser) {
							$contentUserStatus = sprintf('<p class="fnt-style1 d-block p-0 m-0 mt-1 activity-status is-%s" data-weight="regular" style="font-size:10px;">%s</p>', strtolower($perRelatedUser['status']), ucfirst(strtolower($perRelatedUser['status'])));
							$contentUserActivity = '';
							if(array_key_exists('timeElapsed', $perRelatedUser)) {
								$contentUserActivity = sprintf('<p class="fnt-style1 d-block p-0 m-0 mt-1" data-weight="regular" style="font-size:10px;"><img src="%s" class="svg-white mr-1 me-1" style="width:13px;"/> %s</p>', path2url(sprintf('%s/image/icons/fluent_shifts-activity-16-filled-%s.svg', DIR_ASSET, $appConfig['CORE']['app_build_version'])), $perRelatedUser['timeElapsed']);
							}
							$contentTitleTooltip = sprintf('<h6 class="fnt-style2 d-block p-0 m-0" data-weight="bold" style="font-size:12px;">%s</h6>%s %s', $perRelatedUser['name'], $contentUserStatus, $contentUserActivity);
							if(($idx + 1) <= $maxShowItem) {
								echo sprintf('<div class="wuo-item is-%s" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="list-user-relationship" data-bs-html="true" data-bs-title="%s"><img src="%s" alt="Avatar"/></div>', strtolower($perRelatedUser['status']), str_replace('"', '\'', $contentTitleTooltip), $perRelatedUser['avatar']);
							} else {
								echo sprintf('<div class="wuo-item total-more">+%s</div>', (count($listUsers)-($idx)));
								break;
							}
						}
					}
					?>
				</div>
			</div>
			<div class="col-auto">
				<span class="widget-separator-y"></span>
				<div class="widget-item widget-user_chat mr-2 me-2">
					<button type="button" class="btn btn-widget-chat">
						<img src="<?= path2url(sprintf('%s/image/icons/bxs_chat-%s.svg', DIR_ASSET, $buildVersion)); ?>" alt="Icons Chat"/>
					</button>
				</div>
				<div class="widget-item widget-user_notification">
					<button type="button" class="btn btn-widget-notification">
						<img src="<?= path2url(sprintf('%s/image/icons/mdi_notifications-%s.svg', DIR_ASSET, $buildVersion)); ?>" alt="Icons Notification"/>
					</button>
				</div>
			</div>
			<div class="col-auto">
				<span class="widget-separator-y"></span>
				<div class="widget-item widget-user_profile">
					<button type="button" class="btn btn-widget-profile">
						<div class="wup-avatar">
							<img src="<?= $userProfile->avatar; ?>" alt="User Avatar"/>
						</div>
						<div class="wup-detail">
							<h6 class="wup-name"><?= $userProfile->name; ?></h6>
							<p class="wup-greet">Selamat malam 👋</p>
						</div>
						<span class="wup-mark">
							<img src="<?= path2url(sprintf('%s/image/icons/eva_arrow-down-fill-%s.svg', DIR_ASSET, $buildVersion)); ?>" alt="Icons Arrow down"/>
						</span>
					</button>
				</div>
			</div>
		</section>
	</div>
</nav>
<nav class="bread-navbar" style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
	<span class="breadcrumb-icon">
		<img src="<?= path2url(sprintf('%s/image/icons/tabler_flag-3-filled-%s.svg', DIR_ASSET, $buildVersion)); ?>" alt="Icons Breadcrumb"/>
	</span>
	<ol class="breadcrumb">
		<li class="breadcrumb-item active"><a href="<?= getURI(2).'/dashboard'; ?>">Ringkasan</a></li>
	</ol>
</nav>