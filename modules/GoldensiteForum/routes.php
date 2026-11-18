<?php
use Modules\GoldensiteForum\Attachments;
use Modules\GoldensiteForum\Controllers\{ForumController,ReplyController,TopicController};
use Modules\GoldensiteForum\Controllers\Admin\ModerationController;

$router->get('/forum',[ForumController::class,'index']);
$router->get('/forum/board/{slug}',[ForumController::class,'board']);
$router->get('/forum/board/{slug}/new',[TopicController::class,'create']);
$router->post('/forum/board/{slug}/new',[TopicController::class,'store']);
$router->get('/forum/topic/{id}/{slug}',[TopicController::class,'show']);
$router->post('/forum/topic/{id}/{slug}/reply',[ReplyController::class,'store']);
$router->post('/forum/topic/{id}/{slug}/react',[ReplyController::class,'react']);
$router->post('/forum/topic/{id}/{slug}/action',[TopicController::class,'action']);
$router->get('/forum/tags',[ForumController::class,'tags']);
$router->post('/forum/preview',[ForumController::class,'preview']);
$router->get('/forum/notifications',[ForumController::class,'notifications']);
$router->get('/forum/notifications/unread',[ForumController::class,'unread']);
$router->post('/forum/notifications/read',[ForumController::class,'read']);
$router->post('/forum/notifications/preferences',[ForumController::class,'preferences']);
$router->get('/forum/file/{id}',[Attachments::class,'download']);
$router->get('/forum/brand/{kind}/{id}',[\Modules\GoldensiteForum\Appearance::class,'image']);
$router->get('/forum/assets/{type}',[ForumController::class,'assets']);
foreach (['GET','POST'] as $method) $router->add($method,'/admin/forum',[ModerationController::class,'index']);
