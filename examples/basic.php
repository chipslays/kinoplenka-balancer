<?php

use Kinoplenka\Balancer\Balancer;

require __DIR__ . '/../vendor/autoload.php';

$balancer = new Balancer('YOUR_TOKEN_HERE');

$response = $balancer->get('list');

dump($response);