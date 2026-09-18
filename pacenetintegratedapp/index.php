<?php
/**
 * PACENET PRO - Modern Multi-Router Cloud NOC Controller & Hotspot Billing System
 * Root entry point - redirects to modern React Single Page Application (SPA)
 */
if (isset($_SERVER['SERVER_PORT']) && strval($_SERVER['SERVER_PORT']) === '8080') {
    include_once(__DIR__ . '/join.php');
    exit;
}

header("Location: /app/");
exit;
