<?php

declare(strict_types=1);

namespace Checkin\Common;

/**
 * 响应体里的业务码。成功仍由 JsonResultHandler 写成数字 0。
 * 拒绝和内部错误用字符串，客户端按字符串分支，不要再写 10001 这类数字。
 * 字符串码和 HTTP 状态是两套数：例如没带 token 是 UNAUTHORIZED + 401，
 * 锁被占用是 RESOURCE_BUSY + 409。HTTP 由抛出的地方或错误处理器决定。
 */
final class ErrorCode
{
    /** 没带 Bearer、token 无效或过期、假 access_token 不存在。HTTP 401。 */
    public const UNAUTHORIZED = 'UNAUTHORIZED';

    /** access_token 有效，但 server_id + role_id 不属于这个玩家。HTTP 404。 */
    public const ROLE_NOT_FOUND = 'ROLE_NOT_FOUND';

    /** 缺字段或类型不对，例如没传 check_day。HTTP 400。 */
    public const INVALID_PARAMETER = 'INVALID_PARAMETER';

    /** 活动未配置，或当前时间不在开始和结束之间。HTTP 404。 */
    public const ACTIVITY_NOT_FOUND = 'ACTIVITY_NOT_FOUND';

    /** 请求的签到天不在配置的 1～31 档里。HTTP 400。 */
    public const INVALID_CHECK_DAY = 'INVALID_CHECK_DAY';

    /** 这一天已经签过。HTTP 400。 */
    public const ALREADY_CHECKED_IN = 'ALREADY_CHECKED_IN';

    /** 没签下一档，跳天了。HTTP 400。 */
    public const CHECK_IN_ORDER_ERROR = 'CHECK_IN_ORDER_ERROR';

    /** 这一档还没到活动开始后的可签天数。HTTP 400。 */
    public const CHECK_IN_DAY_NOT_REACHED = 'CHECK_IN_DAY_NOT_REACHED';

    /** 今天已经签过，且本月补签次数用完。HTTP 400。 */
    public const MAKEUP_LIMIT_EXCEEDED = 'MAKEUP_LIMIT_EXCEEDED';

    /** 业务上已经领过这一档。HTTP 400。唯一索引撞车不用这个码。 */
    public const REWARD_ALREADY_CLAIMED = 'REWARD_ALREADY_CLAIMED';

    /** 充值门槛不在配置里。HTTP 400。 */
    public const INVALID_RECHARGE_TIER = 'INVALID_RECHARGE_TIER';

    /** 当月累计没达到这个门槛。HTTP 400。 */
    public const RECHARGE_NOT_REACHED = 'RECHARGE_NOT_REACHED';

    /** 锁没抢到，或唯一索引冲突。请求内容没问题，稍后重试。HTTP 409。 */
    public const RESOURCE_BUSY = 'RESOURCE_BUSY';

    /** 没被业务异常接住的错误。HTTP 500。调试模式下 data 里带 type/file/line。 */
    public const INTERNAL_ERROR = 'INTERNAL_ERROR';
}
