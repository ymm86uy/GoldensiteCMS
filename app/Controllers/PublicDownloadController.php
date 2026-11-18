<?php
namespace App\Controllers;
final class PublicDownloadController { public function download(string $id): void { (new DownloadDeliveryController())->download($id); } }
