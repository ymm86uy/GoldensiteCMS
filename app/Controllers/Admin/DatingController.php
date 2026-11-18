<?php
namespace App\Controllers\Admin;
/** Compatibility entry point; all flows share the canonical community implementation. */
final class DatingController {
 public function index(): void { (new DatingCommunityAdminController())->index(); }
 public function publicSite(): void { (new \App\Controllers\DatingCommunityController())->index(); }
}
