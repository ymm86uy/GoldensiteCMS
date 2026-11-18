<?php
namespace App\Controllers\Admin;
final class DownloadsController { public function index(): void { (new DownloadLibraryController())->index(); } }
