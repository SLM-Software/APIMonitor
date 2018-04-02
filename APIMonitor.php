<?php
/**
 * Created by PhpStorm.
 * User: syacko
 * Date: 3/27/18
 * Time: 9:23 AM
 *
 **/

require __DIR__ . '/vendor/autoload.php';

$myAPIMonitor = new \UTILITY\APIMonitor();
$myAPIMonitor->executeAPI();