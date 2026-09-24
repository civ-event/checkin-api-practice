<?php // PHP 起始

declare(strict_types=1); // 严格类型

namespace Checkin\Config; // 配置封装命名空间

/**
 * 把 config/checkin.php 的 days 表封装成有方法的对象，
 * 供 Service 判断合法天、下一签到天、礼物。
 */
final class DailyCheckInConfig
{
    /**
     * @param array<int, array{day: int, gift_id: int, gift_name: string}> $days
     *        key 为签到天（1~7），value 为该天礼物
     */
    public function __construct(
        private readonly array $days,
        private readonly int $makeupCheckInLimit,
    ) {}

    public function getMakeupCheckInLimit(): int
    {
        return $this->makeupCheckInLimit;
    }

    /** 从 PHP 配置文件加载；默认项目根下 config/checkin.php */
    public static function load(?string $path = null): self
    {
        $path ??= dirname(__DIR__, 2) . '/config/checkin.php'; // src/Config → 上两级到项目根
        /** @var array{days: array<int, array{day: int, gift_id: int, gift_name: string}>} $config */
        $config = require $path; // require 返回配置数组

        return new self($config['days'], max(0, (int) ($config['makeup_check_in_limit'] ?? 0)));
    }

    /** 该天是否在配置里（合法签到天） */
    public function isValidCheckDay(int $checkDay): bool
    {
        return isset($this->days[$checkDay]); // 用 key 是否存在判断
    }

    /** @return list<int> 配置中所有签到天，按 key 顺序 */
    public function getCheckDays(): array
    {
        return array_map('intval', array_keys($this->days)); // keys 转 int 列表
    }

    /**
     * 根据已签天数推导下一天；全部签完返回 null。
     *
     * @param list<int> $checkedDays
     */
    public function getNextCheckDay(array $checkedDays): ?int
    {
        foreach ($this->getCheckDays() as $day) { // 按配置顺序找第一个未签的
            if (!in_array($day, $checkedDays, true)) { // 严格比较
                return $day;
            }
        }

        return null; // 都签完了
    }

    /**
     * 取某天礼物；非法天抛 InvalidArgumentException
     *
     * @return array{day: int, gift_id: int, gift_name: string}
     */
    public function getGiftForDay(int $checkDay): array
    {
        if (!$this->isValidCheckDay($checkDay)) {
            throw new \InvalidArgumentException("invalid check day: {$checkDay}");
        }

        return $this->days[$checkDay]; // 返回礼物数组
    }
}
