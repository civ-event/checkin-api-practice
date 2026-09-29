-- 打卡即发奖，已签天数就是已领状态。claimed_days 没有读写方，删掉以免和 checked_days 各记各的。

ALTER TABLE daily_check_in_user_data
  DROP COLUMN claimed_days;
