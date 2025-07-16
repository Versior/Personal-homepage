<?php
require_once "config.php";

function fixVisitorStats() {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        echo "Database connection failed.\n";
        return;
    }

    // 检查 visitor_logs 表是否存在 visit_time 字段，如果不存在则添加
    $query = "PRAGMA table_info(visitor_logs)";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasVisitTime = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'visit_time') {
            $hasVisitTime = true;
            break;
        }
    }

    if (!$hasVisitTime) {
        echo "Adding visit_time column to visitor_logs table...\n";
        $db->exec("ALTER TABLE visitor_logs ADD COLUMN visit_time DATETIME DEFAULT CURRENT_TIMESTAMP");
        echo "visit_time column added.\n";
    }

    // 检查 schema.sql 文件，确保 visitor_logs 表的创建语句包含 visit_time DATETIME DEFAULT CURRENT_TIMESTAMP
    // 如果没有，建议用户手动更新 schema.sql
    $schema_content = file_get_contents(__DIR__ . "/schema.sql");
    if (strpos($schema_content, "visit_time DATETIME DEFAULT CURRENT_TIMESTAMP") === false) {
        echo "WARNING: schema.sql does not contain 'visit_time DATETIME DEFAULT CURRENT_TIMESTAMP' for visitor_logs table. Please update it manually for future installations.\n";
    }

    echo "Visitor statistics fix script completed.\n";
}

fixVisitorStats();
?>

