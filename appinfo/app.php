<?php

if (class_exists('\OCA\Files_External\AppInfo\Application')) {
	OC_App::loadApp('files_external');
	(new \OCA\Files_External_Sia\AppInfo\Application())->register();
}
