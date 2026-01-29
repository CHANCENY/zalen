<?php

require_once 'vendor/autoload.php';

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;

$serverUrl = 'http://localhost:9515/'; // ChromeDriver draait standaard op deze poort
$capabilities = DesiredCapabilities::chrome();

try {
    // Verbind met ChromeDriver
    $driver = RemoteWebDriver::create($serverUrl, $capabilities);
    
    // Bezoek een website
    $driver->get('https://www.google.com');

    echo "Title of the page is: " . $driver->getTitle() . "\n";

    // Sluit de browser
    $driver->quit();
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
