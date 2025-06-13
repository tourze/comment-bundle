<?php

namespace Tourze\CommentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentMention;

class CommentMentionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentMention::class);
    }

    public function findByComment(Comment $comment): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.comment = :comment')
            ->setParameter('comment', $comment)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByMentionedUser(string $userId, array $options = []): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.mentionedUserId = :userId')
            ->setParameter('userId', $userId);

        if (isset($options['is_notified'])) {
            $qb->andWhere('m.isNotified = :isNotified')
               ->setParameter('isNotified', $options['is_notified']);
        }

        $orderDirection = $options['order_direction'] ?? 'DESC';
        $qb->orderBy('m.createdAt', $orderDirection);

        if ($limit = $options['limit'] ?? null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findUnnotifiedMentions(int $limit = 100): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.isNotified = :isNotified')
            ->setParameter('isNotified', false)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnnotifiedByUser(string $userId): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.mentionedUserId = :userId')
            ->andWhere('m.isNotified = :isNotified')
            ->setParameter('userId', $userId)
            ->setParameter('isNotified', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAsNotified(array $mentionIds): int
    {
        if (empty($mentionIds)) {
            return 0;
        }

        return $this->createQueryBuilder('m')
            ->update()
            ->set('m.isNotified', ':isNotified')
            ->set('m.notifiedAt', ':notifiedAt')
            ->where('m.id IN (:ids)')
            ->setParameter('isNotified', true)
            ->setParameter('notifiedAt', new \DateTimeImmutable())
            ->setParameter('ids', $mentionIds)
            ->getQuery()
            ->execute();
    }

    public function removeMentionsByComment(Comment $comment): int
    {
        return $this->createQueryBuilder('m')
            ->delete()
            ->where('m.comment = :comment')
            ->setParameter('comment', $comment)
            ->getQuery()
            ->execute();
    }

    public function findDuplicateMention(Comment $comment, string $mentionedUserId): ?CommentMention
    {
        return $this->createQueryBuilder('m')
            ->where('m.comment = :comment')
            ->andWhere('m.mentionedUserId = :userId')
            ->setParameter('comment', $comment)
            ->setParameter('userId', $mentionedUserId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getMentionStatistics(): array
    {
        return $this->createQueryBuilder('m')
            ->select([
                'COUNT(m.id) as total_mentions',
                'COUNT(CASE WHEN m.isNotified = true THEN 1 END) as notified_mentions',
                'COUNT(CASE WHEN m.isNotified = false THEN 1 END) as pending_mentions'
            ])
            ->getQuery()
            ->getSingleResult();
    }
}