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