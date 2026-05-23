<?php
/**
 * ROS Configuration Endpoint
 * 
 * Provides runtime configuration for ROS bridge connections.
 * Browsers can call this endpoint to discover the correct robot hostname
 * and ROSbridge WebSocket URL, regardless of proxy setup.
 * 
 * Usage (JavaScript):
 *   fetch('/api/ros-config').then(r => r.json()).then(cfg => {
 *       console.log('Robot:', cfg.vehicle_name);
 *       console.log('ROSbridge:', cfg.rosbridge_url);
 *   });
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get environment variables set by the dashboard container
$vehicle_name = getenv('VEHICLE_NAME') ?: getenv('HOSTNAME') ?: 'unknown';

// Derive the rosbridge host with this precedence:
//   1. ROSBRIDGE_HOST env var (explicit operator override)
//   2. The Host: header of the incoming request (strip any :port)
//      — this is what the browser used to reach us, so it's reachable from the client
//   3. VEHICLE_NAME.local (legacy last-resort fallback)
$request_host = null;
if (!empty($_SERVER['HTTP_HOST'])) {
    $request_host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']);
}
$rosbridge_host = getenv('ROSBRIDGE_HOST') ?: ($request_host ?: $vehicle_name . '.local');
$rosbridge_port = getenv('ROSBRIDGE_PORT') ?: 9001;

// Determine if we're behind HTTPS
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
            $_SERVER['REQUEST_SCHEME'] === 'https';

$rosbridge_scheme = $is_https ? 'wss' : 'ws';

// Build the full ROSbridge WebSocket URL
$rosbridge_url = sprintf(
    '%s://%s:%d/rosbridge_websocket',
    $rosbridge_scheme,
    $rosbridge_host,
    $rosbridge_port
);

echo json_encode([
    'vehicle_name' => $vehicle_name,
    'robot_hostname' => $rosbridge_host,
    'rosbridge_url' => $rosbridge_url,
    'rosbridge_host' => $rosbridge_host,
    'rosbridge_port' => $rosbridge_port,
    'rosbridge_scheme' => $rosbridge_scheme,
    'timestamp' => time(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
