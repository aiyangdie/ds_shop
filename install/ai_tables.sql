-- AI 会话 / 消息 / 审计日志表（也可由 Store::ensureSchema 自动创建）
CREATE TABLE IF NOT EXISTS `pre_ai_session` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(120) NOT NULL DEFAULT '新对话',
  `model` VARCHAR(64) DEFAULT NULL,
  `msg_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `tool_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1,
  `addtime` DATETIME DEFAULT NULL,
  `updatetime` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_updatetime` (`updatetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_ai_message` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` INT UNSIGNED NOT NULL,
  `role` VARCHAR(16) NOT NULL,
  `content` MEDIUMTEXT,
  `tool_trace` MEDIUMTEXT,
  `usage_json` VARCHAR(255) DEFAULT NULL,
  `addtime` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_addtime` (`addtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pre_ai_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `message_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `tool_name` VARCHAR(64) NOT NULL,
  `tool_label` VARCHAR(64) DEFAULT NULL,
  `arguments` MEDIUMTEXT,
  `result` MEDIUMTEXT,
  `ok` TINYINT NOT NULL DEFAULT 0,
  `error_msg` VARCHAR(500) DEFAULT NULL,
  `operator` VARCHAR(64) DEFAULT NULL,
  `ip` VARCHAR(64) DEFAULT NULL,
  `model` VARCHAR(64) DEFAULT NULL,
  `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
  `request_id` VARCHAR(64) DEFAULT NULL,
  `addtime` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_tool` (`tool_name`),
  KEY `idx_addtime` (`addtime`),
  KEY `idx_ok` (`ok`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
