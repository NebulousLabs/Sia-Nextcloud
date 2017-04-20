<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Johnathan Howell <me@johnathanhowell.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 * * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_External_Sia\Storage;

set_include_path(get_include_path() . PATH_SEPARATOR .
	\OC_App::getAppPath('files_external_sia') . '/sia-php');

require_once 'sia.php';

use OCP\Files\ForbiddenException;
use Icewind\Streams\IteratorDirectory;
use Icewind\Streams\CallbackWrapper;

class Sia extends \OC\Files\Storage\Common {
	private $client;
	private $apiaddr;
	private $datadir;


	public function __construct($arguments) {
		if (!isset($arguments['apiaddr']) || !is_string($arguments['apiaddr'])) {
			throw new \InvalidArgumentException('no api address set for Sia');
		}
		$this->client = new \Sia\Client($arguments['apiaddr']);
		$this->apiaddr = $arguments['apiaddr'];
		$this->datadir = realpath($arguments['datadir']);
	}

	// parsePaths takes an array of siafiles and a path and returns an array of
	// siafiles that contain $path at the beginning of their siapath.
	private function parsePaths($siafiles, $path) {
		$ret = array();

		foreach($siafiles as $siafile) {
			if ($path === "/") {
				array_push($ret, $siafile);
			} else if (strpos($siafile, $path) === 0) {
				$components = explode($path, $siafile, 2);
				array_push($ret, $components[count($components)-1]);
			}
		}

		return $ret;
	}

	// ls takes an array of siafiles and a path and returns an array of names of
	// directories or files in that path.
	private function ls($siafiles, $path) {
		$ret = array();
		$paths = $this->parsePaths($siafiles, $path . "/");

		foreach($paths as $siafile) {
			$filename = $siafile;
			$pathComponents = explode('/', $siafile);
			$isDir = count($pathComponents) > 1;

			if ($isDir) {
				$filename = $pathComponents[0];
			}

			$ret[$filename] = $filename;
		}
	
		return array_values($ret);
	}

	private function localFile($siapath) {
		$cleanpath = $siapath;
		if (strpos($siapath, '.ocTransferId') !== false) {
			$cleanpath = explode('.ocTransferId', $siapath)[0];
		}
		return $this->datadir . '/' . hash('sha256', $cleanpath);
	}

	public function __destruct() {
	}


	public function getId() {
		return 'sia::' . $this->apiaddr;
	}

	public function mkdir($path) {
		$tmpFile = \OCP\Files::tmpFile();
		$f = fopen($tmpFile, 'r+');
		fwrite($f, '0x4A');
		fclose($f);

		$this->client->upload($path . '/.dirinfo', $tmpFile);

		return true;
	}

	public function rmdir($path) {
		if ($path == "") {
			return false;
		}

		$files = $this->client->renterFiles();

		foreach ($files as $file) {
			if (strpos($file->siapath, $path) === 0) {
				$this->client->delete($file->siapath);
			}
		}

		return true;
	}

	public function opendir($path) {
		$siafiles = $this->client->renterFiles();
		$siapaths = array();
		foreach($siafiles as $siafile) {
			array_push($siapaths, $siafile->siapath);
		}

		return IteratorDirectory::wrap($this->ls($siapaths, $path));
	}

	// test the node's upload capability by verifying that the node has some
	// contracts and the renter data directory is writeable.
	public function test() {
		try {
			$contracts = $this->client->renterContracts();
			if (count($contracts) === 0) {
				return false;
			}
			if (!is_writeable($this->datadir)) {
				return false;
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	// directorySize returns the aggregate filesize of every file in the directory supplied by $path.
	private function directorySize($path) {
		$files = $this->client->renterFiles();
		$sz = 0;
		foreach($files as $file) {
			if (strpos($file->siapath, $path) !== false) {
				$sz += $file->filesize;
			}
		}
		return $sz;
	}

	public function stat($path) {
		clearstatcache();
		$ret = array();
		if ($this->is_dir($path)) {
			$ret['size'] = $this->directorySize($path);
			return $ret;
		}

		$files = $this->client->renterFiles();
		foreach($files as $file) {
			if (strpos($file->siapath, $path) !== false) {
				$ret['size'] = $file->filesize;
				return $ret;
			}
		}

		return false;
	}

	public function filetype($path) {
		if ($this->is_dir($path)) {
			return 'dir';
		}
		return 'file';
	}

	public function is_dir($path) {
		if ($path === "") {
			return true;
		}

		if ($this->is_file($path)) {
			return false;
		}

		$files = $this->client->renterFiles();

		foreach($files as $file) {
			if (strpos($file->siapath, $path) === 0) {
				return true;
			}
		}

		return false;
	}

	public function is_file($path) {
		$files = $this->client->renterFiles();

		foreach($files as $file) {
			if ($file->siapath === $path) {
				return true;
			}
		}

		return false;
	}

	// filesize returns the file size in bytes of the file at $path.
	public function filesize($path) {
		if ($this->is_dir($path)) {
			return $this->directorySize($path);
		}
		
		$sz = 0;
		$siafiles = $this->client->renterFiles();

		foreach($siafiles as $siafile) {
			if ($siafile->siapath === $path) {
				$sz = $siafile->filesize;
				break;
			}
		}

		return $sz;
	}

	public function isDeletable($path) {
		if ($path === '') {
			return false;
		}

		if ($this->file_exists($path)) {
			return true;
		}

		return false;
	}

	// file_exists checks for the existence of the file at $path on the Sia node.
	public function file_exists($path) {
		return $this->is_dir($path) || $this->is_file($path);
	}

	public function isCreatable($path) {
		return true;
	}

	public function filemtime($path) {
		return false;
	}

	public function touch($path, $mtime = null) {
		return true;
	}

	public function unlink($path) {
		try {
			$this->client->delete($path);
			if (file_exists($this->localFile($path))) {
				unlink($this->localFile($path));
			}
		} catch (\Exception $e) {
			return false;
		}
		return true;
	}

	public function getPermissions($path) {
		return \OCP\Constants::PERMISSION_ALL;
	}

	public function rename($path1, $path2) {
		try {
			$this->client->rename($path1, $path2);
		} catch (\Exception $e) {
			return false;
		}
		return true;
	}

	// isFileInDownloads returns a bool true if a file exists in the Sia node's
	// downloads, otherwise it returns false.
	private function isFileInDownloads($siapath) {
		$exists = false;

		$downloads = $this->client->downloads();
		foreach($downloads as $download) {
			if ($download->siapath == $siapath && $download->error == "") {
				$exists = true;
			}
		}

		return $exists;
	}

	// waitUntilDownloaded spins until the requested file at $siapath has been
	// downloaded, and returns a handle to the downloaded file on disk.
	private function waitUntilDownloaded($siapath) {
		// if this file doesnt exist yet in downloads, spin for up to 20 seconds
		// until it appears.
		if (!$this->isFileInDownloads($siapath)) {
			$i = 0;
			$exists = false;
			while (!$exists) {
				if ($i == 20) {
					throw new \Exception($siapath . ' did not appear in downloads after 20 seconds.');
				}
				sleep(1);
				$downloads = $this->client->downloads();
				foreach($downloads as $download) {
					if ($download->siapath == $siapath) {
						$exists = true;
						break;
					}
				}
				$i++;
			}
		}

		// spin until the file has been downloaded.
		$localPath = '';
		$exists = false;
		while (!$exists) {
			sleep(1);
			$downloads = $this->client->downloads();
			foreach($downloads as $download) {
				if ($download->siapath == $siapath && $download->error !== "") {
					throw new \Exception($download->error);
				}
				if ($download->siapath == $siapath && $download->received == $download->filesize) {
					$localPath = $download->destination;
					$exists = true;
					break;
				}
			}
		}

		return fopen($localPath, 'r');
	}

	public function fopen($path, $mode) {
		switch ($mode) {
			case 'r':
			case 'rb':
				// if this file is on disk, just return a handle to it
				if (file_exists($this->localFile($path))) {
					return fopen($this->localFile($path), 'r');
				}

				// if this file hasn't been downloaded already, download it and return
				// the file handle.
				if (!$this->isFileInDownloads($path)) {
					$this->client->download($path, $this->localFile($path));
				}

				return $this->waitUntilDownloaded($path);
			case 'w':
			case 'wb':
			case 'a':
			case 'ab':
			case 'r+':
			case 'w+':
			case 'wb+':
			case 'a+':
			case 'x':
			case 'x+':
			case 'c':
			case 'c+':
				$localfile = $this->localFile($path);
				$handle = fopen($localfile, $mode);
				return CallbackWrapper::wrap($handle, null, null, function () use ($path, $localfile) {
					$this->client->upload($path, $localfile);
					while (true) {
						$files = $this->client->renterFiles();
						foreach ($files as $file) {
							if ($file->siapath == $path) {
								return;
							}
						}
					}
				});
		}
	}

	public function hasUpdated($path, $time) {
		return true;
	}

	public function isReadable($path) {
		return true;
	}
}
