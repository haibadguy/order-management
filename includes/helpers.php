<?php
// Hàm định dạng ngày tháng
function formatDate($date, $format = 'd/m/Y H:i') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

/**
 * Format a number as Vietnamese currency (chỉ hiển thị đơn vị "đ")
 * @param float|int $amount
 * @param bool $showUnit
 * @return string
 */
function formatCurrency($amount, $showUnit = true) {
    if (!is_numeric($amount)) return '0' . ($showUnit ? 'đ' : '');
    $formatted = number_format(abs($amount), 0, ',', '.');
    $unit = $showUnit ? 'đ' : '';
    return $amount < 0 ? "-$formatted$unit" : "$formatted$unit";
}

/**
 * Format phần trăm, hiển thị đúng định dạng VN
 * @param float $value
 * @param int $decimals
 * @return string
 */
function formatPercent($value, $decimals = 1) {
    return number_format($value, $decimals, ',', '.') . '%';
}

// Rút gọn chuỗi có độ dài lớn
function truncateString($string, $length = 50, $append = '...') {
    if (strlen($string) > $length) {
        $string = substr($string, 0, $length) . $append;
    }
    return $string;
}

// Tạo slug từ chuỗi tiếng Việt
function createSlug($string) {
    $search = array(
        '#(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)#',
        '#(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)#',
        '#(ì|í|ị|ỉ|ĩ)#',
        '#(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)#',
        '#(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)#',
        '#(ỳ|ý|ỵ|ỷ|ỹ)#',
        '#(đ)#',
        '#[^a-z0-9\-_]#'
    );
    $replace = array('a', 'e', 'i', 'o', 'u', 'y', 'd', '-');

    $string = strtolower($string);
    $string = preg_replace($search, $replace, $string);
    $string = preg_replace('/(-)+/', '-', $string);
    $string = trim($string, '-');

    return $string;
}

// Lấy trạng thái đơn hàng
function getOrderStatus($status) {
    $statuses = [
        'pending'    => ['text' => 'Chờ xử lý',   'class' => 'warning'],
        'confirmed'  => ['text' => 'Đã xác nhận', 'class' => 'info'],
        'processing' => ['text' => 'Đang xử lý',  'class' => 'primary'],
        'shipping'   => ['text' => 'Đang giao',   'class' => 'info'],
        'completed'  => ['text' => 'Hoàn thành',  'class' => 'success'],
        'cancelled'  => ['text' => 'Đã hủy',      'class' => 'danger'],
        'refunded'   => ['text' => 'Đã hoàn tiền','class' => 'secondary']
    ];
    return $statuses[$status] ?? ['text' => 'Không xác định', 'class' => 'secondary'];
}

// Lấy trạng thái thanh toán
function getPaymentStatus($status) {
    $statuses = [
        'pending'  => ['text' => 'Chờ thanh toán', 'class' => 'warning'],
        'paid'     => ['text' => 'Đã thanh toán',  'class' => 'success'],
        'failed'   => ['text' => 'Thất bại',       'class' => 'danger'],
        'refunded' => ['text' => 'Đã hoàn tiền',   'class' => 'info']
    ];
    return $statuses[$status] ?? ['text' => 'Không xác định', 'class' => 'secondary'];
}

// Lấy phương thức thanh toán
function getPaymentMethod($method) {
    $methods = [
        'cod'           => 'Tiền mặt khi nhận hàng',
        'bank_transfer' => 'Chuyển khoản ngân hàng',
        'credit_card'   => 'Thẻ tín dụng',
        'momo'          => 'Ví MoMo',
        'zalopay'       => 'ZaloPay'
    ];
    return $methods[$method] ?? 'Không xác định';
}
