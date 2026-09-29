-- 签到改为按月一条进度。唯一键从「角色 + 活动」改成「角色 + 活动 + YYYYMM」，换月自动新开一轮。
-- year_month 是 MySQL 关键字，语句里必须加反引号。

ALTER TABLE daily_check_in_user_data
  ADD COLUMN `year_month` INT NOT NULL DEFAULT 202609 COMMENT '年月 YYYYMM' AFTER activity_id;

ALTER TABLE daily_check_in_user_data
  DROP INDEX uniq_user_role_activity,
  ADD UNIQUE KEY uniq_user_role_activity_month (user_role_primary_id, activity_id, `year_month`);

ALTER TABLE daily_check_in_gift_log
  ADD COLUMN `year_month` INT NOT NULL DEFAULT 202609 AFTER activity_id;

ALTER TABLE daily_check_in_gift_log
  DROP INDEX uniq_role_activity_day,
  ADD UNIQUE KEY uniq_role_activity_month_day (user_role_primary_id, activity_id, `year_month`, check_day);