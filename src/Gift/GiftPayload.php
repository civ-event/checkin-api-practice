<?php

declare(strict_types=1);

namespace Checkin\Gift;

use Doctrine\ORM\EntityManagerInterface;

/**
 * 组装线上 makeSendGiftRequest 的正文。
 * itemList 来自「名称*数量*道具ID」；对不上这个格式时用 gift_id，数量为 1。
 */
final class GiftPayload
{
    /**
     * @param array{day: int, gift_id: int, gift_name: string} $gift
     * @return array{rewardType: string, serverId: string, roleId: string, playerId: string, itemList: string}
     */
    public static function build(
        EntityManagerInterface $em,
        int $userRolePrimaryId,
        string $rewardType,
        array $gift,
    ): array {
        $row = $em->getConnection()->fetchAssociative(
            'SELECT r.server_id, r.role_id, u.player_id
             FROM activity_user_role r
             INNER JOIN activity_user u ON u.id = r.activity_user_id
             WHERE r.id = ?',
            [$userRolePrimaryId],
        );
        if ($row === false) {
            throw new \RuntimeException('role missing for gift');
        }

        return [
            'rewardType' => $rewardType,
            'serverId' => (string) $row['server_id'],
            'roleId' => (string) $row['role_id'],
            'playerId' => (string) $row['player_id'],
            'itemList' => self::itemList((string) $gift['gift_name'], (int) $gift['gift_id']),
        ];
    }

    private static function itemList(string $giftName, int $giftId): string
    {
        $parts = explode('*', $giftName);
        if (count($parts) === 3) {
            $encoded = json_encode([['id' => $parts[2], 'count' => (int) $parts[1]]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            return $encoded;
        }

        $encoded = json_encode([['id' => (string) $giftId, 'count' => 1]], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $encoded;
    }
}
