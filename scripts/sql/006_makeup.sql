ALTER TABLE daily_check_in_user_data
  ADD COLUMN makeup_used INT NOT NULL DEFAULT 0 COMMENT '本月已用补签次数' AFTER last_check_time;