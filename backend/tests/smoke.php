<?php
declare(strict_types=1);require __DIR__.'/../vendor/autoload.php';if(PHP_VERSION_ID<80200||!extension_loaded('pdo')){fwrite(STDERR,"PHP 8.2+ e PDO necessários\n");exit(1);}echo "LIRANDZU API smoke checks passed.\n";
