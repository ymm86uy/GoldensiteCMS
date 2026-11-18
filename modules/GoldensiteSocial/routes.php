<?php
use Modules\GoldensiteSocial\{Audio,Controller};

$router->get('/social',[Controller::class,'page']);
$router->get('/api/social/echoes',[Controller::class,'feed']);
$router->post('/api/social/echoes',[Controller::class,'publish']);
$router->get('/api/social/frequencies',[Controller::class,'map']);
$router->post('/api/social/presence',[Controller::class,'presence']);
$router->get('/api/social/echoes/{id}/waves',[Controller::class,'waves']);
$router->post('/api/social/echoes/{id}/waves',[Controller::class,'wave']);
$router->post('/api/social/echoes/{id}/resonance',[Controller::class,'resonate']);
$router->get('/api/social/echoes/{id}/audio',[Audio::class,'stream']);
$router->get('/admin/social-network',[Controller::class,'settings']);
$router->post('/admin/social-network',[Controller::class,'settings']);
