# Sia-Nextcloud

This is the [Nextcloud](https://nextcloud.com/) Sia storage backend implementation. It adds a 'Sia' option to the External Files Nextcloud app, allowing users to upload and download from a Sia node through the Nextcloud UI.

## Requirements

* A `siad` instance must be running on the same system as the Nextcloud server.
  * The `siad` instance must be fully synchronized.
  * The `siad` instance must have a rental allowance set.

## Video Tutorial

A slightly outdated video setup tutorial is available on YouTube:

* [Integrating Sia, blockchain based storage, in Nextcloud](https://www.youtube.com/watch?v=Ut--X4u69vw)

Note that the video is from a previous version of Sia-Nextcloud that did not require a "Renter data directory" parameter during configuration.

