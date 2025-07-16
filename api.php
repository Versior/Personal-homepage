<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    exit(0);
}

require_once "config.php";

$action = $_GET["action"] ?? "";

switch ($action) {
    case "visitor_info":
        getVisitorInfo();
        break;
    case "hot_search":
        getHotSearch();
        break;
    case "visitor_stats":
        getVisitorStats();
        break;
    case "posts":
        getPosts();
        break;
    case "post_detail":
        getPostDetail();
        break;
    case "like_post":
        likePost();
        break;
    case "add_comment":
        addComment();
        break;
    case "messages":
        getMessages();
        break;
    case "add_message":
        addMessage();
        break;
    case "friend_links":
        getFriendLinks();
        break;
    case "site_config":
        getSiteConfig();
        break;
    case "qq_info":
        getQQInfo();
        break;
    default:
        echo json_encode(["error" => "Invalid action"]);
}

function getVisitorInfo() {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $ip = getClientIP();
    $userAgent = getUserAgent();
    $agentInfo = parseUserAgent($userAgent);
    
    // 获取IP定位API
    $query = "SELECT config_value FROM site_config WHERE config_key = 'ip_location_api'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $locationApi = $stmt->fetchColumn();
    
    $location = getLocationByIP($ip, $locationApi);
    
    // 记录访客
    $query = "INSERT INTO visitor_logs (ip_address, user_agent, location, device, browser) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$ip, $userAgent, $location, $agentInfo["device"], $agentInfo["browser"]]);
    
    // 获取访客排名
    $query = "SELECT COUNT(*) as rank FROM visitor_logs WHERE visit_time < (SELECT visit_time FROM visitor_logs WHERE id = LAST_INSERT_ROWID())"; // Changed LAST_INSERT_ID() to LAST_INSERT_ROWID() for SQLite
    $stmt = $db->prepare($query);
    $stmt->execute();
    $rank = $stmt->fetchColumn() + 1;
    
    // 获取总访客数
    $query = "SELECT COUNT(*) as total FROM visitor_logs";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total = $stmt->fetchColumn();
    
    echo json_encode([
        "ip" => $ip,
        "location" => $location,
        "device" => $agentInfo["device"],
        "browser" => $agentInfo["browser"],
        "rank" => $rank,
        "total" => $total
    ]);
}

function getHotSearch() {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "SELECT config_value FROM site_config WHERE config_key = 'hot_search_api'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $apiUrl = $stmt->fetchColumn();
    
    $context = stream_context_create([
        "http" => [
            "timeout" => 5,
            "method" => "GET"
        ]
    ]);
    
    $response = @file_get_contents($apiUrl, false, $context);
    if ($response) {
        echo $response;
    } else {
        echo json_encode(["error" => "Failed to fetch hot search data"]);
    }
}

function getVisitorStats() {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    // 今日访客
    $query = "SELECT COUNT(*) as today FROM visitor_logs WHERE DATE(visit_time) = DATE('now', 'localtime')"; // Changed CURDATE() to DATE('now', 'localtime') for SQLite
    $stmt = $db->prepare($query);
    $stmt->execute();
    $today = $stmt->fetchColumn();
    
    // 总访客
    $query = "SELECT COUNT(*) as total FROM visitor_logs";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $total = $stmt->fetchColumn();
    
    // 最近7天访客趋势
    $query = "SELECT DATE(visit_time) as date, COUNT(*) as count FROM visitor_logs 
              WHERE visit_time >= DATE('now', '-7 days') 
              GROUP BY DATE(visit_time) ORDER BY date"; // Changed DATE_SUB(CURDATE(), INTERVAL 7 DAY) to DATE('now', '-7 days') for SQLite
    $stmt = $db->prepare($query);
    $stmt->execute();
    $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "today" => $today,
        "total" => $total,
        "trend" => $trend
    ]);
}

function getPosts() {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "SELECT id, title, content, likes, created_at FROM posts ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($posts);
}

function getPostDetail() {
    $postId = $_GET["id"] ?? 0;
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    // 获取文章详情
    $query = "SELECT * FROM posts WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        echo json_encode(["error" => "Post not found"]);
        return;
    }
    
    // 获取评论
    $query = "SELECT * FROM post_comments WHERE post_id = ? ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$postId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $post["comments"] = $comments;
    echo json_encode($post);
}

function likePost() {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }
    
    $postId = $_POST["id"] ?? 0;
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "UPDATE posts SET likes = likes + 1 WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$postId]);
    
    if ($result) {
        $query = "SELECT likes FROM posts WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$postId]);
        $likes = $stmt->fetchColumn();
        
        echo json_encode(["success" => true, "likes" => $likes]);
    } else {
        echo json_encode(["error" => "Failed to like post"]);
    }
}

function addComment() {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }
    
    $postId = $_POST["post_id"] ?? 0;
    $qq = $_POST["qq"] ?? "";
    $content = $_POST["content"] ?? "";
    
    if (empty($qq) || empty($content)) {
        echo json_encode(["error" => "Missing required fields"]);
        return;
    }
    
    // 获取QQ信息
    $qqInfo = getQQInfoByNumber($qq);
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "INSERT INTO post_comments (post_id, qq_number, nickname, avatar_url, content) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$postId, $qq, $qqInfo["nickname"], $qqInfo["avatar"], $content]);
    
    if ($result) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Failed to add comment"]);
    }
}

function getMessages() {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "SELECT * FROM messages WHERE is_approved = 1 ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($messages);
}

function addMessage() {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }
    
    $qq = $_POST["qq"] ?? "";
    $content = $_POST["content"] ?? "";
    
    if (empty($qq) || empty($content)) {
        echo json_encode(["error" => "Missing required fields"]);
        return;
    }
    
    $ip = getClientIP();
    $userAgent = getUserAgent();
    $agentInfo = parseUserAgent($userAgent);
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    // 获取IP定位API
    $query = "SELECT config_value FROM site_config WHERE config_key = 'ip_location_api'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $locationApi = $stmt->fetchColumn();
    
    $location = getLocationByIP($ip, $locationApi);
    
    // 获取QQ信息
    $qqInfo = getQQInfoByNumber($qq);
    // 即使QQ信息获取失败，也允许留言，使用默认值
    $nickname = $qqInfo["nickname"] ?? "匿名用户";
    $avatar = $qqInfo["avatar"] ?? "assets/default-avatar.png";
    
    $query = "INSERT INTO messages (qq_number, nickname, avatar_url, content, ip_address, location, device, browser, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$qq, $nickname, $avatar, $content, $ip, $location, $agentInfo["device"], $agentInfo["browser"]]);
    
    if ($result) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Failed to add message"]);
    }
}

function getFriendLinks() {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "SELECT id, name, url, avatar_url as avatar, sort_order, created_at FROM friend_links ORDER BY sort_order, created_at";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($links);
}

function getSiteConfig() {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "SELECT config_key, config_value FROM site_config";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    echo json_encode($configs);
}

function getQQInfo() {
    $qq = $_GET["qq"] ?? "";
    if (empty($qq)) {
        echo json_encode(["error" => "QQ number is required"]);
        return;
    }
    $qqInfo = getQQInfoByNumber($qq);
    echo json_encode($qqInfo);
}

function getQQInfoByNumber($qqNumber) {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        return ["error" => "Database connection failed."];
    }

    $qq_nickname_api = "";
    $qq_avatar_api = "";

    $query = "SELECT config_value FROM site_config WHERE config_key = 'qq_nickname_api'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $qq_nickname_api = $stmt->fetchColumn();

    $query = "SELECT config_value FROM site_config WHERE config_key = 'qq_avatar_api'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $qq_avatar_api = $stmt->fetchColumn();

    $nickname = "匿名用户";
    $avatar = "assets/default-avatar.png"; // 默认头像

    // 获取QQ昵称
    if (!empty($qq_nickname_api)) {
        $nickname_url = str_replace("QQ号码", $qqNumber, $qq_nickname_api);
        $nickname_url = str_replace("123456", $qqNumber, $nickname_url); // 兼容用户提供的API格式
        $context = stream_context_create([
            "http" => [
                "timeout" => 3,
                "method" => "GET"
            ]
        ]);
        $response = @file_get_contents($nickname_url, false, $context);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data["name"])) {
                $nickname = $data["name"];
            }
        }
    }

    // 获取QQ头像
    if (!empty($qq_avatar_api)) {
        $avatar_url = str_replace("QQ号码", $qqNumber, $qq_avatar_api);
        // 尝试获取头像，不严格检查HTTP头，因为有些头像API可能不返回200但图片仍然有效
        // 或者某些服务器会阻止get_headers请求
        $avatar = $avatar_url;
    }

    return ["nickname" => $nickname, "avatar" => $avatar];
}

?>


