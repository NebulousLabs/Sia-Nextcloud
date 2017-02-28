<?php
/**
 * @copyright Copyright (c) 2017, Nebulous
 *
 * @author Johnathan Howell <me@johnathanhowell.com>
 *
 * @license MIT
 * */

namespace Sia;

require_once __DIR__ . '/Requests/library/Requests.php';
\Requests::register_autoloader();

class Client {
	private $apiaddr;

	private function apiGet($route) {
		$url = $this->apiaddr . $route;
		$res = \Requests::get($url, array('User-Agent' => 'Sia-Agent'));

		if ( $res->status_code < 200 || $res->status_code > 299 || !$res->success ) {
			throw new \Exception(json_decode($res->body)->message);
		}

		return json_decode($res->body);
	}

	private function apiPost($route) {
		$url = $this->apiaddr . $route;
		$res = \Requests::post($url, array('User-Agent' => 'Sia-Agent'));

		if ( $res->status_code < 200 || $res->status_code > 299 || !$res->success ) {
			throw new \Exception(json_decode($res->body)->message);
		}

		return json_decode($res->body);
	}

	public function __construct($apiaddr) {
		if (!is_string($apiaddr)) {
			throw new \InvalidArgumentException('api addr must be a string');
		}
		$this->apiaddr = 'http://' . $apiaddr;
	}	

	// Daemon API
	// version returns a string representation of the current Sia daemon version.
	public function version() {
		return $this->apiGet('/daemon/version')->version;
	}

	// Wallet API
	// wallet returns the wallet object
	public function wallet() {
		return $this->apiGet('/wallet');
	}

	// Renter API
	// renterSettings returns the renter settings
	public function renterSettings() {
		return $this->apiGet('/renter');
	}

	// renterFiles returns the files in the renter
	public function renterFiles() {
		return $this->apiGet('/renter/files')->files;
	}

	// renterContracts returns the contracts being used by the renter
	public function renterContracts() {
		return $this->apiGet('/renter/contracts')->contracts;
	}

	// download downloads the file at $siapath to $dest.
	public function download($siapath, $dest) {
		$this->apiGet('/renter/downloadasync/' . $siapath . '?destination=' . $dest);
	}

	public function downloads() {
		return $this->apiGet('/renter/downloads')->downloads;
	}

	public function upload($siapath, $src) {
		$this->apiPost('/renter/upload/' . $siapath . '?source=' . $src);
	}

	public function delete($siapath) {
		$this->apiPost('/renter/delete/' . $siapath);
	}

	public function rename($siapath, $newsiapath) {
		$this->apiPost('/renter/rename/' . $siapath . '?newsiapath=' . $newsiapath);
	}
}

