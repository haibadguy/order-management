<?php
// Load địa chỉ từ api.json
function loadAddressData() {
    $json_file = __DIR__ . '/../api.json';
    if (!file_exists($json_file)) {
        error_log("api.json file not found at: " . $json_file);
        return null;
    }
    
    $json_data = file_get_contents($json_file);
    if ($json_data === false) {
        error_log("Failed to read api.json file");
        return null;
    }
    
    $data = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        return null;
    }
    
    return $data;
}

// Lấy danh sách tỉnh/thành phố
function getProvinces() {
    $data = loadAddressData();
    if (!$data) return [];
    return array_map(function($province) {
        return [
            'code' => $province['code'],
            'name' => $province['name']
        ];
    }, $data);
}

// Lấy danh sách quận/huyện theo tỉnh/thành phố
function getDistricts($provinceCode) {
    $data = loadAddressData();
    if (!$data) return [];
    
    foreach ($data as $province) {
        if ($province['code'] == $provinceCode) {
            return array_map(function($district) {
                return [
                    'code' => $district['code'],
                    'name' => $district['name']
                ];
            }, $province['districts']);
        }
    }
    return [];
}

// Lấy danh sách phường/xã theo quận/huyện
function getWards($districtCode) {
    $data = loadAddressData();
    if (!$data) return [];
    
    foreach ($data as $province) {
        foreach ($province['districts'] as $district) {
            if ($district['code'] == $districtCode) {
                return array_map(function($ward) {
                    return [
                        'code' => $ward['code'],
                        'name' => $ward['name']
                    ];
                }, $district['wards']);
            }
        }
    }
    return [];
}

// Lấy tên địa chỉ đầy đủ từ mã
function getFullAddress($provinceCode, $districtCode, $wardCode) {
    $data = loadAddressData();
    if (!$data) return '';
    
    $address = [];
    foreach ($data as $province) {
        if ($province['code'] == $provinceCode) {
            $address[] = $province['name'];
            foreach ($province['districts'] as $district) {
                if ($district['code'] == $districtCode) {
                    $address[] = $district['name'];
                    foreach ($district['wards'] as $ward) {
                        if ($ward['code'] == $wardCode) {
                            $address[] = $ward['name'];
                            break;
                        }
                    }
                    break;
                }
            }
            break;
        }
    }
    return implode(', ', array_reverse($address));
} 