<?php
// 数据库配置
class Database {
    private $db_file = __DIR__ . "/data/database.sqlite";
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("sqlite:" . $this->db_file);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            return null;
        }
        return $this->conn;
    }
}

// 工具函数
function log_message($message) {
    error_log($message);
}

// 获取客户端IP
function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// 获取用户代理
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

// 解析用户代理
function parseUserAgent($userAgent) {
    $device = 'Unknown';
    $browser = 'Unknown';
    
    // 检测设备
    if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
        $device = 'Mobile';
    } else {
        $device = 'Desktop';
    }
    
    // 检测浏览器
    if (preg_match('/Chrome/', $userAgent)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Firefox/', $userAgent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari/', $userAgent)) {
        $browser = 'Safari';
    } elseif (preg_match('/Edge/', $userAgent)) {
        $browser = 'Edge';
    }
    
    return ['device' => $device, 'browser' => $browser];
}

// 根据IP获取位置
function getLocationByIP($ip, $apiUrl) {
    if (empty($apiUrl)) {
        return '未知位置';
    }
    
    $url = str_replace('{ip}', $ip, $apiUrl);
    $url = str_replace('ip=', 'ip=' . $ip, $url); // 兼容现有API格式
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'GET'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if ($response) {
        $data = json_decode($response, true);
        
        // 优先支持用户指定的 api.xinyew.cn 格式
        if (isset($data['status']) && $data['status'] === '0' && isset($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
            $locationData = $data['data'][0];
            if (isset($locationData['location'])) {
                return $locationData['location'];
            }
        }
        
        // 支持 ip-api.com 格式
        if (isset($data['status']) && $data['status'] === 'success') {
            $location = '';
            if (isset($data['country'])) {
                $location .= $data['country'];
            }
            if (isset($data['regionName'])) {
                $location .= ' ' . $data['regionName'];
            }
            if (isset($data['city'])) {
                $location .= ' ' . $data['city'];
            }
            return trim($location) ?: '未知位置';
        }
        
        // 通用格式支持
        if (isset($data['location'])) {
            return $data['location'];
        } elseif (isset($data['city']) && isset($data['region'])) {
            return $data['region'] . ' ' . $data['city'];
        }
    }
    
    return '未知位置';
}

?>

