<?php

require_once dirname(__FILE__).'/../lib/Rocket/Autoloader.php';
Rocket\Autoloader::register();

Rocket\Test\Harness::getInstance()->flushTestDatabases();
