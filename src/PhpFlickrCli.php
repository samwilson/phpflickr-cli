<?php declare(strict_types = 1);

namespace Samwilson\PhpFlickrCli;

use Symfony\Component\Console\Application;

class PhpFlickrCli extends Application {

    public function __construct(string $name = 'PhpFlickr CLI', string $version = '0.1.0') {
        parent::__construct($name, $version);
    }

}
