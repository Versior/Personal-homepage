--
-- Table structure for table `site_config`
--
CREATE TABLE IF NOT EXISTS `site_config` (
  `config_key` TEXT NOT NULL,
  `config_value` TEXT,
  PRIMARY KEY (`config_key`)
);

--
-- Dumping data for table `site_config`
--
INSERT OR IGNORE INTO `site_config` (`config_key`, `config_value`) VALUES
("hot_search_api", "http://api.guiguiya.com/api/hotlist?type=douyin"),
("ip_location_api", "https://api.xinyew.cn/api/baiduchaip?ip="),
("qq_nickname_api", "https://api.ilingku.com/int/v1/qqname?qq=QQ号码"),
("qq_avatar_api", "http://q1.qlogo.cn/g?b=qq&nk=QQ号码&s=100"),
("auto_status", "1"),
("current_status", "😴 睡觉中");

--
-- Table structure for table `visitor_logs`
--
CREATE TABLE IF NOT EXISTS `visitor_logs` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `ip_address` TEXT DEFAULT NULL,
  `user_agent` TEXT,
  `location` TEXT DEFAULT NULL,
  `device` TEXT DEFAULT NULL,
  `browser` TEXT DEFAULT NULL,
  `visit_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--
-- Table structure for table `posts`
--
CREATE TABLE IF NOT EXISTS `posts` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `title` TEXT NOT NULL,
  `content` TEXT,
  `likes` INTEGER DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--
-- Table structure for table `post_comments`
--
CREATE TABLE IF NOT EXISTS `post_comments` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `post_id` INTEGER DEFAULT NULL,
  `qq_number` TEXT DEFAULT NULL,
  `nickname` TEXT DEFAULT NULL,
  `avatar_url` TEXT DEFAULT NULL,
  `content` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
);

--
-- Table structure for table `messages`
--
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `qq_number` TEXT DEFAULT NULL,
  `nickname` TEXT DEFAULT NULL,
  `avatar_url` TEXT DEFAULT NULL,
  `content` TEXT,
  `ip_address` TEXT DEFAULT NULL,
  `location` TEXT DEFAULT NULL,
  `device` TEXT DEFAULT NULL,
  `browser` TEXT DEFAULT NULL,
  `is_approved` INTEGER DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--
-- Table structure for table `friend_links`
--
CREATE TABLE IF NOT EXISTS `friend_links` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` TEXT NOT NULL,
  `url` TEXT NOT NULL,
  `avatar_url` TEXT DEFAULT NULL,
  `sort_order` INTEGER DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--
-- Table structure for table `admin_users`
--
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `username` TEXT NOT NULL UNIQUE,
  `password` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

--
-- Dumping data for table `admin_users`
--
INSERT OR IGNORE INTO `admin_users` (`username`, `password`) VALUES
("", "");

