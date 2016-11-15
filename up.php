<?php

use Samwilson\FlickrUpDown\Up;

if (!isset($argv[1])) {
	echo "Provide a directory or file name as the first argument to up.php\n";
	exit(1);
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

$up = new Up( $apiKey, $apiSecret, $dataDir );
$up->upload( $argv[1] );
