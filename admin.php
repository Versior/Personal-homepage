<?php
session_start();
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    exit(0);
}

require_once "config.php";

$action = $_REQUEST["action"] ?? "";

switch ($action) {
    case "login":
        adminLogin();
        break;
    case "logout":
        adminLogout();
        break;
    case "check_auth":
        checkAuth();
        break;
    case "dashboard_stats":
        getDashboardStats();
        break;
    case "get_site_config":
        getSiteConfig();
        break;
    case "update_site_config":
        updateSiteConfig();
        break;
    case "get_posts":
        getPosts();
        break;
    case "get_post":
        getPost();
        break;
    case "add_post":
        addPost();
        break;
    case "update_post":
        updatePost();
        break;
    case "delete_post":
        deletePost();
        break;
    case "get_messages":
        getAllMessages();
        break;
    case "approve_message":
        approveMessage();
        break;
    case "delete_message":
        deleteMessage();
        break;
    case "get_friends":
        getFriends();
        break;
    case "get_friend":
        getFriend();
        break;
    case "add_friend":
        addFriend();
        break;
    case "update_friend":
        updateFriend();
        break;
    case "delete_friend":
        deleteFriend();
        break;
    case "get_visitors":
        getVisitors();
        break;
    case "update_account":
        updateAccount();
        break;
    default:
        echo json_encode(["error" => "Invalid action"]);
}

function adminLogin() {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }
    
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    
    if (empty($username) || empty($password)) {
        echo json_encode(["error" => "用户名和密码不能为空"]);
        return;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "SELECT id, username, password FROM admin_users WHERE username = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && password_verify($password, $admin["password"])) {
        $_SESSION["admin_id"] = $admin["id"];
        $_SESSION["admin_username"] = $admin["username"];
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "用户名或密码错误"]);
    }
}

function adminLogout() {
    session_destroy();
    echo json_encode(["success" => true]);
}

function checkAuth() {
    if (isset($_SESSION["admin_id"])) {
        echo json_encode(["authenticated" => true]);
    } else {
        echo json_encode(["authenticated" => false]);
    }
}

function getDashboardStats() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    // 总访客数
    $query = "SELECT COUNT(*) as total FROM visitor_logs";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $totalVisitors = $stmt->fetchColumn();
    
    // 今日访客数
    $query = "SELECT COUNT(*) as today FROM visitor_logs WHERE DATE(visit_time) = DATE(\'now\', \'localtime\')";
    $stmt->execute();
    $todayVisitors = $stmt->fetchColumn();
    
    // 留言总数
    $query = "SELECT COUNT(*) as total FROM messages";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $totalMessages = $stmt->fetchColumn();
    
    // 文章总数
    $query = "SELECT COUNT(*) as total FROM posts";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $totalPosts = $stmt->fetchColumn();
    
    // 访客趋势 (每日)
    $query = "SELECT DATE(visit_time) as visit_date, COUNT(*) as count FROM visitor_logs GROUP BY visit_date ORDER BY visit_date ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $visitorTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "total_visitors" => $totalVisitors,
        "today_visitors" => $todayVisitors,
        "total_messages" => $totalMessages,
        "total_posts" => $totalPosts,
        "visitor_trends" => $visitorTrends
    ]);
}

function getSiteConfig() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
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

function updateSiteConfig() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $configs = [
        'site_title', 'site_icon', 'avatar_url', 'current_status', 'auto_status',
        'hot_search_api', 'ip_location_api', 'qq_nickname_api', 'qq_avatar_api'
    ];
    
    foreach ($configs as $key) {
        $value = $_POST[$key] ?? "";
        if ($key === 'auto_status') {
            $value = isset($_POST[$key]) ? '1' : '0';
        }
        
        $query = "INSERT OR REPLACE INTO site_config (config_key, config_value) VALUES (?, ?)";
        try {
            $stmt = $db->prepare($query);
            $stmt->execute([$key, $value]);
        } catch (PDOException $e) {
            log_message("Error updating site config for key {$key}: " . $e->getMessage());
            echo json_encode(["error" => "Failed to update config: " . $e->getMessage()]);
            return;
        }
    }
    
    echo json_encode(["success" => true]);
}

function getPosts() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
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

function getPost() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    $id = $_GET["id"] ?? 0;
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "SELECT * FROM posts WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($post) {
        echo json_encode($post);
    } else {
        echo json_encode(["error" => "Post not found"]);
    }
}

function addPost() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }
    
    $title = $_POST["title"] ?? "";
    $content = $_POST["content"] ?? "";
    
    if (empty($title) || empty($content)) {
        echo json_encode(["error" => "标题和内容不能为空"]);
        return;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "INSERT INTO posts (title, content) VALUES (?, ?)";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$title, $content]);
    
    if ($result) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Failed to add post"]);
    }
}

function updatePost() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }
    
    $id = $_POST["post_id"] ?? 0;
    $title = $_POST["title"] ?? "";
    $content = $_POST["content"] ?? "";
    
    if (empty($title) || empty($content)) {
        echo json_encode(["error" => "标题和内容不能为空"]);
        return;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "UPDATE posts SET title = ?, content = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$title, $content, $id]);
    
    if ($result) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Failed to update post"]);
    }
}

function deletePost() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }
    
    $id = $_POST["id"] ?? 0;
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "DELETE FROM posts WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Failed to delete post"]);
    }
}

function getAllMessages() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "SELECT * FROM messages ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($messages);
}

function approveMessage() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }
    
    $id = $_POST["id"] ?? 0;
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "UPDATE messages SET is_approved = 1 WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Failed to approve message"]);
    }
}

function deleteMessage() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }
    
    $id = $_POST["id"] ?? 0;
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "DELETE FROM messages WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Failed to delete message"]);
    }
}

function getFriends() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "SELECT * FROM friend_links ORDER BY sort_order, created_at";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($friends);
}

function getFriend() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    $id = $_GET["id"] ?? 0;
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "SELECT * FROM friend_links WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    $friend = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($friend) {
        echo json_encode($friend);
    } else {
        echo json_encode(["error" => "Friend link not found"]);
    }
}

function addFriend() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }
    
    $name = $_POST["name"] ?? "";
    $url = $_POST["url"] ?? "";
    $avatar = $_POST["avatar"] ?? "";
    $description = $_POST["description"] ?? "";
    $sortOrder = $_POST["sort_order"] ?? 0;
    
    if (empty($name) || empty($url)) {
        echo json_encode(["error" => "网站名称和URL不能为空"]);
        return;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "INSERT INTO friend_links (name, url, avatar_url, sort_order) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$name, $url, $avatar, $sortOrder]);
    
    if ($result) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Failed to add friend link"]);
    }
}

function updateFriend() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }
    
    $id = $_POST["friend_id"] ?? 0;
    $name = $_POST["name"] ?? "";
    $url = $_POST["url"] ?? "";
    $avatar = $_POST["avatar"] ?? "";
    $description = $_POST["description"] ?? "";
    $sortOrder = $_POST["sort_order"] ?? 0;
    
    if (empty($name) || empty($url)) {
        echo json_encode(["error" => "网站名称和URL不能为空"]);
        return;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "UPDATE friend_links SET name = ?, url = ?, avatar_url = ?, description = ?, sort_order = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$name, $url, $avatar, $description, $sortOrder, $id]);
    
    if ($result) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Failed to update friend link"]);
    }
}

function getVisitors() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $query = "SELECT * FROM visitor_logs ORDER BY visit_time DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($visitors);
}

function updateAccount() {
    if (!isset($_SESSION["admin_id"])) {
        echo json_encode(["error" => "Unauthorized"]);
        return;
    }
    
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => "Method not allowed"]);
        return;
    }
    
    $oldPassword = $_POST["old_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    
    if (empty($oldPassword) || empty($newPassword)) {
        echo json_encode(["error" => "旧密码和新密码不能为空"]);
        return;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo json_encode(["error" => "Database connection failed."]);
        return;
    }
    
    $adminId = $_SESSION["admin_id"];
    
    // 验证旧密码
    $query = "SELECT password FROM admin_users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$adminId]);
    $hashedPassword = $stmt->fetchColumn();
    
    if (!password_verify($oldPassword, $hashedPassword)) {
        echo json_encode(["error" => "旧密码不正确"]);
        return;
    }
    
    // 更新新密码
    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $query = "UPDATE admin_users SET password = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $result = $stmt->execute([$newHashedPassword, $adminId]);
    
    if ($result) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "密码更新失败"]);
    }
}

?>


