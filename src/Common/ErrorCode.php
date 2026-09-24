<?php // PHP 起始

declare(strict_types=1); // 严格类型

namespace Checkin\Common; // 公共常量所在命名空间

/**
 * 业务错误码。响应体里的 code 和 HTTP 状态是两套数：
 * code 0 成功，1xxxx 是预期内的拒绝；50000 是没被接住的异常（SQL、类找不到等）。
 * APP_DEBUG=1 时 50000 的 data 里有 type/file/line。curl -s 只打印 body，看不到 HTTP 状态。
 */
final class ErrorCode
{
    public const INVALID_CHECK_DAY = 10001; // 请求的签到天不在配置里
    public const ALREADY_CHECKED_IN = 10002; // 该天已签，或今天已签过
    public const CHECK_IN_ORDER_ERROR = 10003; // 未按顺序签（跳天）
    public const MISSING_CONTEXT = 10004; // 没带 Bearer，或 token 无效/过期；HTTP 401
    public const INVALID_PARAM = 10005; // 参数非法（如缺 check_day）
    public const LOCK_BUSY = 10006; // 锁被占用
    public const NOT_CHECKED = 10007; // 这一天还没签，不能领
    public const ALREADY_CLAIMED = 10008; // 这一天已经领过
    public const INVALID_ACCESS_TOKEN = 10009; // accessToken 无效
    public const ROLE_NOT_OWNED = 10010;       // 该角色不属于这个玩家
    public const INVALID_RECHARGE_TIER = 10011; // 档位不在配置里
    public const RECHARGE_NOT_REACHED = 10012;  // 当月累计未达到
    public const RECHARGE_ALREADY_CLAIMED = 10013; // 该档已领
    public const ACTIVITY_NOT_RUNNING = 10014; // 活动未配置，或当前时间不在开始和结束之间
    public const CHECK_IN_DAY_NOT_REACHED = 10015; // 这一档还没到活动开始后的可签天数
    public const MAKEUP_LIMIT_EXCEEDED = 10016; // 本月补签次数用完
}
