<?php // PHP 起始

declare(strict_types=1); // 严格类型

namespace Checkin\Entities; // 实体命名空间

use Checkin\Entities\Repositories\DailyCheckInUserDataRepository; // 自定义仓储类
use Doctrine\ORM\Mapping as ORM; // Doctrine Attribute 映射别名

#[ORM\Entity(repositoryClass: DailyCheckInUserDataRepository::class)] // 声明实体 + 绑定仓储
#[ORM\Table(name: 'daily_check_in_user_data')] // 对应表名
#[ORM\UniqueConstraint(name: 'uniq_user_role_activity_month', columns: ['user_role_primary_id', 'activity_id', 'year_month'])] // 角色+活动+月份唯一
#[ORM\Index(name: 'idx_user_role', columns: ['user_role_primary_id'])] // 按角色查的索引
#[ORM\Index(name: 'idx_activity', columns: ['activity_id'])] // 按活动查的索引
class DailyCheckInUserData
{
    #[ORM\Id] // 主键
    #[ORM\GeneratedValue] // 自增
    #[ORM\Column(name: 'id', type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null; // 新建未落库时为 null

    #[ORM\Column(name: 'user_role_primary_id', type: 'bigint', options: ['unsigned' => true])]
    private int $userRolePrimaryId = 0; // 角色主键

    #[ORM\Column(name: 'activity_id', type: 'bigint', options: ['unsigned' => true])]
    private int $activityId = 0; // 活动实例 ID
    // 映射名以反引号开头，Doctrine 才会在 SQL 里给这一列加反引号。。手写 DBAL insert/update 不会，那种地方要自己写反引号
    #[ORM\Column(name: '`year_month`', type: 'integer')]
    private int $yearMonth = 0;

    /** @var list<int> 已签天数，JSON 存库 */
    #[ORM\Column(name: 'checked_days', type: 'json')]
    private array $checkedDays = [];

    /** @var list<int> 已领奖天数，JSON 存库 */
    #[ORM\Column(name: 'claimed_days', type: 'json')]
    private array $claimedDays = [];

    #[ORM\Column(name: 'total_checked', type: 'integer', options: ['default' => 0])]
    private int $totalChecked = 0; // 已签天数个数（冗余字段，方便查询）

    #[ORM\Column(name: 'last_check_time', type: 'integer', options: ['default' => 0])]
    private int $lastCheckTime = 0; // 上次签到 Unix 时间戳；0 表示从未签

    /** 本月已经用掉的补签次数 */
    #[ORM\Column(name: 'makeup_used', type: 'integer', options: ['default' => 0])]
    private int $makeupUsed = 0;

    #[ORM\Column(name: 'created_at', type: 'integer', options: ['default' => 0])]
    private int $createdAt = 0; // 创建时间戳

    #[ORM\Column(name: 'updated_at', type: 'integer', options: ['default' => 0])]
    private int $updatedAt = 0; // 更新时间戳

    public function getId(): ?int
    {
        return $this->id; // 主键 getter
    }

    public function getUserRolePrimaryId(): int
    {
        return $this->userRolePrimaryId;
    }

    public function setUserRolePrimaryId(int $userRolePrimaryId): static
    {
        $this->userRolePrimaryId = $userRolePrimaryId;

        return $this; // 链式调用
    }

    public function getActivityId(): int
    {
        return $this->activityId;
    }

    public function setActivityId(int $activityId): static
    {
        $this->activityId = $activityId;

        return $this;
    }

    public function getYearMonth(): int
    {
        return $this->yearMonth;
    }

    public function setYearMonth(int $yearMonth): static
    {
        $this->yearMonth = $yearMonth;

        return $this;
    }

    /** @return list<int> */
    public function getCheckedDays(): array
    {
        return $this->checkedDays; // 返回已签列表副本语义上是内部数组引用，学习项目可接受
    }

    /** @return list<int> */
    public function getClaimedDays(): array
    {
        return $this->claimedDays;
    }

    public function isDayClaimed(int $checkDay): bool
    {
        return in_array($checkDay, $this->claimedDays, true);
    }


    public function getTotalChecked(): int
    {
        return $this->totalChecked;
    }

    public function getLastCheckTime(): int
    {
        return $this->lastCheckTime;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function setCreatedAt(int $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(int $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /** 某档位天是否已在 checkedDays 里 */
    public function isDayChecked(int $checkDay): bool
    {
        return in_array($checkDay, $this->checkedDays, true); // 严格类型比较
    }

    /**
     * 按指定时区判断「今天」是否已签到。
     * 用 lastCheckTime 的日期与「现在」同日比较。
     */
    public function isCheckedToday(string $timezone): bool
    {
        if ($this->lastCheckTime === 0) {
            return false; // 从未签过
        }

        $now = time(); // 当前 Unix 秒
        if ($now < $this->lastCheckTime) {
            // 数据异常：最后签到时间在未来
            throw new \RuntimeException('Inconsistent check-in data: lastCheckTime is in the future.');
        }

        $tz = new \DateTimeZone($timezone); // 业务时区
        // @timestamp 是 UTC 瞬间，再 setTimezone 转到业务时区取日历日
        $last = (new \DateTimeImmutable('@' . $this->lastCheckTime))->setTimezone($tz)->format('Y-m-d');
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone($tz)->format('Y-m-d');

        return $last === $today; // 同一自然日则认为今天已签
    }

    public function getMakeupUsed(): int
    {
        return $this->makeupUsed;
    }

    /** 消耗一次补签。调用前要先确认还没到上限 */
    public function consumeMakeup(): static
    {
        $this->makeupUsed++;
        $this->updatedAt = time();

        return $this;
    }

    /** 标记某天已签：追加天数、重算总数、更新 last_check_time */
    public function markDay(int $checkDay): static
    {
        if (!$this->isDayChecked($checkDay)) { // 幂等：已签则跳过
            $this->checkedDays[] = $checkDay; // 追加
            sort($this->checkedDays); // 升序便于阅读/比较
            $this->checkedDays = array_values($this->checkedDays); // 重置连续下标
            $this->totalChecked = count($this->checkedDays); // 同步冗余计数
            $now = time();
            $this->lastCheckTime = $now; // 记录本次签到时刻
            $this->updatedAt = $now;
        }

        return $this;
    }
    public function markClaimed(int $checkDay): static
    {
        if (!$this->isDayClaimed($checkDay)) {
            $this->claimedDays[] = $checkDay;
            sort($this->claimedDays);
            $this->claimedDays = array_values($this->claimedDays);
            $this->updatedAt = time();
        }

        return $this;
    }
}
