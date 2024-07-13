<?php
// Script
function recursive_array_search($needle, $haystack) {
	if ( ! is_array( $haystack ) ) return false;
	foreach ( $haystack as $key => $value ) {
		if ( $value === $needle ) {
			return $key;
		} else if ( is_array( $value ) ) {
			// multi search
			$key_result = recursive_array_search( $needle, $value );
			if ( $key_result !== false ) {
				return $key . '|' . $key_result;
			}
		}
	}
	return false;
}

function searchItemsByKey($array, $key) {
	$results = array();
	if (is_array($array)) {
		if (isset($array[$key]) && key($array) == $key) { $results[] = $array[$key]; }
		foreach ($array as $sub_array) { $results = array_merge($results, searchItemsByKey($sub_array, $key)); }
	}
	return  $results;
}

$sidebarMenu = array(
	'ho' => array(
		array(
			'title' => 'Ringkasan',
			'page_id' => 'dashboard',
			'page_link' => 'dashboard',
			'icon' => sprintf('%s/image/icons/bxs_dashboard-%s.svg', DIR_ASSET, $buildVersion),
			'isShow' => false,
			'isDropdown' => false
		),
		array(
			'title' => 'Pengelolaan Kinerja',
			'page_id' => 'performance',
			'page_link' => '#!',
			'icon' => sprintf('%s/image/icons/mingcute_user-star-fill-%s.svg', DIR_ASSET, $buildVersion),
			'subMenu' => array(
				array(
					'title' => 'Pengisian KPI',
					'page_id' => 'manage-kpi',
					'page_link' => 'manage/kpi',
					'icon' => sprintf('%s/image/icons/solar_document-add-bold-%s.svg', DIR_ASSET, $buildVersion),
					'isShow' => false
				),
				array(
					'title' => 'Persetujuan KPI',
					'page_id' => 'approval-kpi',
					'page_link' => 'approval/kpi',
					'icon' => sprintf('%s/image/icons/material-symbols_approval-%s.svg', DIR_ASSET, $buildVersion),
					'isShow' => false,
					'underConstruction' => false
				),
				array(
					'title' => 'Master KPI',
					'page_id' => 'master-kpi',
					'page_link' => 'master/kpi',
					'icon' => sprintf('%s/image/icons/fluent_document-cube-20-filled-%s.svg', DIR_ASSET, $buildVersion),
					'isShow' => false
				),
				array(
					'title' => 'Arsip KPI',
					'page_id' => 'archive-kpi',
					'page_link' => 'archive/kpi',
					'icon' => sprintf('%s/image/icons/entypo_archive-%s.svg', DIR_ASSET, $buildVersion),
					'isShow' => false,
					'underConstruction' => true
				),
			),
			'isShow' => true,
			'isDropdown' => true,
			'topSplitBar' => true,
			'underConstruction' => false
		),
		array(
			'title' => 'Master',
			'page_id' => 'master',
			'page_link' => '#!',
			'icon' => sprintf('%s/image/icons/fluent_document-cube-20-filled-%s.svg', DIR_ASSET, $buildVersion),
			'subMenu' => array(
				array(
					'title' => 'Data Pegawai',
					'page_id' => 'master-employee',
					'page_link' => 'master/employee',
					'icon' => sprintf('%s/image/icons/fa6-solid_user-group-%s.svg', DIR_ASSET, $buildVersion),
					'isShow' => false
				),
				array(
					'title' => 'Data Jabatan ',
					'page_id' => 'master-position',
					'page_link' => 'master/position',
					'icon' => sprintf('%s/image/icons/material-symbols_work-%s.svg', DIR_ASSET, $buildVersion),
					'isShow' => false
				),
				array(
					'title' => 'Data Divisi',
					'page_id' => 'master-division',
					'page_link' => 'master/division',
					'icon' => sprintf('%s/image/icons/fa-solid_layer-group-%s.svg', DIR_ASSET, $buildVersion),
					'isShow' => false
				),
				array(
					'title' => 'Data Departemen',
					'page_id' => 'master-department',
					'page_link' => 'master/department',
					'icon' => sprintf('%s/image/icons/fa-solid_layer-group-%s.svg', DIR_ASSET, $buildVersion),
					'isShow' => false
				),
				array(
					'title' => 'Data Direktorat',
					'page_id' => 'master-directorate',
					'page_link' => 'master/directorate',
					'icon' => sprintf('%s/image/icons/fa-solid_layer-group-%s.svg', DIR_ASSET, $buildVersion),
					'isShow' => false,
				),
			),
			'isShow' => true,
			'isDropdown' => true,
			// 'topSplitBar' => true,
			'underConstruction' => false
		),
		// array(
		// 	'title' => 'Sales',
		// 	'page_id' => 'sales',
		// 	'page_link' => 'sales',
		// 	'icon' => sprintf('%s/image/icons/fa6-solid_money-bill-trend-up-%s.svg', DIR_ASSET, $buildVersion),
		// 	'isShow' => false,
		// 	'isDropdown' => false,
		// 	'topSplitBar' => false,
		// 	'underConstruction' => false,
		// ),
		array(
			'title' => 'Akun',
			'page_id' => 'profile',
			'page_link' => 'profile',
			'icon' => sprintf('%s/image/icons/ri_user-fill-%s.svg', DIR_ASSET, $buildVersion),
			'isShow' => false,
			'isDropdown' => false,
			'topSplitBar' => false,
			'underConstruction' => false,
		),
	),
);
$pageIDList = array();
foreach ($accountPrivileges['permission']['privileges']['pages'] as $perPage) {
	if (array_key_exists('link', $perPage) && array_key_exists('view', $perPage)) {
		if (!isEmptyVar($perPage['link']) && is_bool($perPage['view'])) {
			$pageIDList[$perPage['link']] = boolval($perPage['view']);
		}
	}
}

$restrictPage = $accountPrivileges['permission']['privileges']['pages'];
$serveMenu = $sidebarMenu[$userType] ?? null;
if (is_array($serveMenu)) {
	$pageIDKeys = array_keys($pageIDList);
	foreach($serveMenu as $idx => $perMenu) {
		if (array_key_exists('page_id', $perMenu)) {
			if (in_array($perMenu['page_id'], $pageIDKeys)) {
				if ($pageIDList[$perMenu['page_id']] === false) {
					$serveMenu[$idx]['isRestricted'] = true;
				}
			}
		}
		if (array_key_exists('subMenu', $perMenu)) {
			if (is_array($perMenu['subMenu']) && count($perMenu['subMenu']) >= 1) {
				foreach($perMenu['subMenu'] as $idx2 => $subMenu) {
					if (in_array($subMenu['page_id'], $pageIDKeys)) {
						if ($pageIDList[$subMenu['page_id']] === false) {
							$serveMenu[$idx]['subMenu'][$idx2]['isRestricted'] = true;
						}
					}
				}
			}
		}
	}
}


$activeMenu = searchArrAssoc($serveMenu, 'page_link', $active);
$activeIndex = recursive_array_search($active, $serveMenu);
$activeShowed = false;
if ($activeIndex !== false && $activeIndex !== null) {
	$activeIndex = explode('|', $activeIndex);
}

foreach ($restrictPage as $perPage) {
	if (array_key_exists('view', $perPage) && array_key_exists('link', $perPage)) {
		$idxMenu = array_search($perPage['link'], array_column($serveMenu, 'page_id')) ?? null;
		if (!is_null($idxMenu) && is_int($idxMenu)) {
			if (boolval($perPage['view']) == true) {
				$serveMenu[$idxMenu]['isShow'] = true;
			} else {
				$serveMenu[$idxMenu]['isShow'] = false;
			}
		}
	}
}
?>

<section class="side-navbar">
	<div class="side-navbar-header">
		<div class="row align-items-center mb-3">
			<div class="col">
				<div class="side-navbar-logo mb-2">
					<img src="<?= path2url(sprintf('%s/image/logo/logo-kpi-%s.svg', DIR_ASSET, $buildVersion)); ?>" alt="Logo" class="svg-primary"/>
				</div>
			</div>
			<div class="col-auto">
				<div class="side-navbar-toggle">
					<button type="button" class="btn-toggle-sidebar">
						<img src="<?= path2url(sprintf('%s/image/icons/fluent_caret-left-16-filled-%s.svg', DIR_ASSET, $buildVersion)); ?>" alt="Icons Left"/>
					</button>
				</div>
			</div>
		</div>
	</div>
	<ul class="side-navbar-nav fnt-style3" data-scrollbar>
		<?php if (!isEmptyVar($serveMenu) && is_array($serveMenu) && count($serveMenu) >= 1) : ?>
			<?php foreach($serveMenu as $idx => $item) : ?>
				<?php if (array_key_exists('topSplitBar', $item)) : ?>
					<?php if (boolval($item['topSplitBar'])) : ?>
						<hr	/>
					<?php endif; ?>
				<?php endif; ?>
				<?php if (array_key_exists('isShow', $item)) : ?>
					<?php if (boolval($item['isShow'])) : ?>
						<?php
							$isItemActive = false;
							$underConstruction = false;
							$accessRestricted = false;
							if (isset($activeIndex[0]) && $activeIndex[0] == $idx) {
								if (isset($activeIndex[1]) && $activeIndex[1] === 'subMenu') {
									$isItemActive = true;
								} elseif (isset($activeIndex[1]) && $activeIndex[1] === 'page_id') {
									$isItemActive = true;
								}
							}
							if (array_key_exists('underConstruction', $item)) {
								$underConstruction = boolval($item['underConstruction']);
							}
							if (array_key_exists('isRestricted', $item)) {
								$accessRestricted = boolval($item['isRestricted']);
							}
						?>
						<li class="nav-item <?= ($underConstruction) ?  'under-construction' : '' ?> <?= ($accessRestricted) ?  'is-restricted' : '' ?> <?= ($isItemActive) ? 'active' : '' ?>">
							<?php if (array_key_exists('isDropdown', $item) && boolval($item['isDropdown']) == true) : ?>
								<button class="btn btn-sm dropdown-toggle <?= ($isItemActive) ? 'show':'' ?>" type="button" data-bs-toggle="dropdown" data-bs-auto-close="false" aria-expanded=" <?= ($isItemActive) ? 'true':'false' ?>"><img src="<?= path2url($item['icon']); ?>" alt="<?= $item['title'] . ' Icon'; ?>" /><?= $item['title']; ?></button>
								<ul class="dropdown-menu <?= ($isItemActive) ? 'show':'' ?>">
									<?php if (array_key_exists('subMenu', $item) && is_array($item['subMenu'])) : ?>
										<?php if (count($item['subMenu']) >= 1) : ?>
											<?php foreach ($item['subMenu'] as $idx2 => $perSub) : ?>
												<?php
													$isItemActive2 = false;
													if (isset($activeIndex[0]) && $activeIndex[0] == $idx) {
														if (isset($activeIndex[1]) && $activeIndex[1] === 'subMenu') {
															if (isset($activeIndex[2]) && $activeIndex[2] == $idx2) {
																$isItemActive2 = true;
															}
														}
													}
												?>
												<?php
													$underConstruction2 = false;
													$accessRestricted2 = false;
													if (array_key_exists('underConstruction', $perSub)) {
														$underConstruction2 = boolval($perSub['underConstruction']);
													}
													if (array_key_exists('isRestricted', $perSub)) {
														$accessRestricted2 = boolval($perSub['isRestricted']);
													}
												?>
												<li class="<?= ($isItemActive2) ? 'active' : '' ?><?= ($underConstruction2) ?  ' under-construction' : '' ?><?= ($accessRestricted2) ?  ' is-restricted' : '' ?>">
													<a class="dropdown-item" href="<?= sprintf('%s/%s%s', getURI(2), $perSub['page_link'], (EXT_PHP) ? '.php': ''); ?>">
														<img src="<?= path2url($perSub['icon']); ?>" alt="<?= $perSub['title'] . ' Icon'; ?>" />
														<?= $perSub['title']; ?>
													</a>
												</li>
											<?php endforeach; ?>
										<?php endif; ?>
									<?php endif; ?>
								</ul>
							<?php else: ?>
								<a class="nav-item-link" href="<?= sprintf('%s/%s%s', getURI(2), $item['page_link'], (EXT_PHP) ? '.php': ''); ?>"><img src="<?= path2url($item['icon']); ?>" alt="<?= $item['title'] . ' Icon'; ?>" /><?= $item['title']; ?></a>
							<?php endif; ?>
						</li>
					<?php endif; ?>
				<?php endif; ?>
				<?php if (array_key_exists('bottomSplitBar', $item)) : ?>
					<?php if (boolval($item['bottomSplitBar'])) : ?>
						<hr	/>
					<?php endif; ?>
				<?php endif; ?>
			<?php endforeach; ?>
		<?php endif; ?>
	</ul>
	<div class="side-navbar-action">
		<div class="row g-0">
			<div class="col">
				<ul class="action-list">
					<li class="action-item">
						<a href="<?= getURI(2).'/logout'; ?>" class="action-link action-logout" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-html="true" data-bs-custom-class="danger-tooltip" data-bs-title="Keluar akun">
							<img src="<?= path2url(sprintf('%s/image/icons/uis_signout-%s.svg', DIR_ASSET, $buildVersion)); ?>" alt="Icons Logout"/>
						</a>
					</li>
					<li class="action-item">
						<a href="#!Settings" class="action-link action-setting" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-html="true" data-bs-custom-class="dark-tooltip" data-bs-title="Setelan">
							<img src="<?= path2url(sprintf('%s/image/icons/solar_settings-bold-%s.svg', DIR_ASSET, $buildVersion)); ?>" alt="Icons Setting"/>
						</a>
					</li>
					<li class="action-item">
						<a href="#!Settings" class="action-link action-setting" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-html="true" data-bs-custom-class="dark-tooltip" data-bs-title="Bantuan">
							<img src="<?= path2url(sprintf('%s/image/icons/mingcute_question-fill-%s.svg', DIR_ASSET, $buildVersion)); ?>" alt="Icons Helpdesk"/>
						</a>
					</li>
				</ul>
			</div>
			<div class="col-auto"></div>
		</div>
	</div>
</section>