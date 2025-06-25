<?php
// Remove authentication requirement since this is public data
// require_once '../includes/auth.php';
require_once '../includes/address_api.php';

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Set JSON response type
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$action = $_GET['action'] ?? '';
$code = $_GET['code'] ?? '';

try {
    switch ($action) {
        case 'provinces':
            $data = getProvinces();
            if (empty($data)) {
                throw new Exception('Failed to load provinces data');
            }
            echo json_encode($data);
            break;
            
        case 'districts':
            if (empty($code)) {
                throw new Exception('Missing province code');
            }
            $data = getDistricts($code);
            if ($data === null) {
                throw new Exception('Failed to load districts data');
            }
            echo json_encode($data);
            break;
            
        case 'wards':
            if (empty($code)) {
                throw new Exception('Missing district code');
            }
            $data = getWards($code);
            if ($data === null) {
                throw new Exception('Failed to load wards data');
            }
            echo json_encode($data);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log('Address API Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 