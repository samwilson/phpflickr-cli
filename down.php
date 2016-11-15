<?php

use Samwilson\FlickrUpDown\Down;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

$down = new Down( $apiKey, $apiSecret, $dataDir );
$down->downloadAll();
