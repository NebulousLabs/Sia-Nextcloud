<?php
/**
 *
 * @author Johnathan Howell <me@johnathanhowell.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_External_Sia\AppInfo;
use OCA\Files_External_Sia\Backend\Sia;
use OCP\AppFramework\App;
use OCA\Files_External\Lib\Config\IBackendProvider;


class Application extends App implements IBackendProvider {
	public function __construct(array $urlParams = array()) {
		parent::__construct('files_external_sia', $urlParams);
	}
	public function register() {
		$server = $this->getContainer()->getServer();
		$backendService = $server->query('OCA\\Files_External\\Service\\BackendService');
		$backendService->registerBackendProvider($this);
	}
	/**
	 * @{inheritdoc}
	 */
	public function getBackends() {
		$container = $this->getContainer();
		return [
			$container->query(Sia::class)
		];
	}
}
