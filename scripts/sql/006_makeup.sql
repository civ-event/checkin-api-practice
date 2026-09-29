-- 月度签到补签次数。今天已经签过再签下一档时，这个数字加一。
-- 上限来自 config/checkin.php，不存在这张表的列里。

ALTER TABLE daily_check_in_user_data
  ADD COLUMN makeup_used INT NOT NULL DEFAULT 0 COMMENT '本月已用补签次数' AFTER last_check_time;