<?php

use Kinoplenka\Balancer\Balancer;

require __DIR__ . '/../vendor/autoload.php';

$balancer = new Balancer('token');

$response = $balancer->get('list');

dump($response);