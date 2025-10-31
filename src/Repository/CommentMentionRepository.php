<?php

namespace Tourze\CommentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentMention;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<CommentMention>
 */
#[AsRepository(entityClass: CommentMention::class)]
class CommentMentionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentMention::class);
    }

    public function save(CommentMention $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CommentMention $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array<CommentMention>
     */
    public function findByComment(Comment $comment): array
    {
        /** @var array<CommentMention> */
        return $this->createQueryBuilder('m')
            ->where('m.comment = :comment')
            ->setParameter('comment', $comment)
            ->orderBy('m.createTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param array{is_notified?: bool, order_direction?: string, limit?: int} $options
     * @return array<CommentMention>
     */
    public function findByMentionedUser(string $userId, array $options = []): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.mentionedUserId = :userId')
            ->setParameter('userId', $userId)
        ;

        if (isset($options['is_notified'])) {
            $qb->andWhere('m.notified = :isNotified')
                ->setParameter('isNotified', $options['is_notified'])
            ;
        }

        $orderDirection = $options['order_direction'] ?? 'DESC';
        $qb->orderBy('m.createTime', $orderDirection);

        if (($limit = $options['limit'] ?? null) !== null) {
            $qb->setMaxResults($limit);
        }

        /** @var array<CommentMention> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<CommentMention>
     */
    public function findUnnotifiedMentions(int $limit = 100): array
    {
        /** @var array<CommentMention> */
        return $this->createQueryBuilder('m')
            ->where('m.notified = :isNotified')
            ->setParameter('isNotified', false)
            ->orderBy('m.createTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function countUnnotifiedByUser(string $userId): int
    {
        $result = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.mentionedUserId = :userId')
            ->andWhere('m.notified = :isNotified')
            ->setParameter('userId', $userId)
            ->setParameter('isNotified', false)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $result;
    }

    /**
     * @param array<int> $mentionIds
     */
    public function markAsNotified(array $mentionIds): int
    {
        if ([] === $mentionIds) {
            return 0;
        }

        /** @var int */
        return $this->createQueryBuilder('m')
            ->update()
            ->set('m.notified', ':isNotified')
            ->set('m.notifyTime', ':notifyTime')
            ->where('m.id IN (:ids)')
            ->setParameter('isNotified', true)
            ->setParameter('notifyTime', new \DateTime())
            ->setParameter('ids', $mentionIds)
            ->getQuery()
            ->execute()
        ;
    }

    public function removeMentionsByComment(Comment $comment): int
    {
        /** @var int */
        return $this->createQueryBuilder('m')
            ->delete()
            ->where('m.comment = :comment')
            ->setParameter('comment', $comment)
            ->getQuery()
            ->execute()
        ;
    }

    public function findDuplicateMention(Comment $comment, string $mentionedUserId): ?CommentMention
    {
        /** @var CommentMention|null */
        return $this->createQueryBuilder('m')
            ->where('m.comment = :comment')
            ->andWhere('m.mentionedUserId = :userId')
            ->setParameter('comment', $comment)
            ->setParameter('userId', $mentionedUserId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @return array{total_mentions: int, notified_mentions: int, pending_mentions: int}
     */
    public function getMentionStatistics(): array
    {
        /** @var array{total_mentions: int, notified_mentions: int, pending_mentions: int} */
        return $this->createQueryBuilder('m')
            ->select([
                'COUNT(m.id) as total_mentions',
                'COUNT(CASE WHEN m.notified = true THEN 1 END) as notified_mentions',
                'COUNT(CASE WHEN m.notified = false THEN 1 END) as pending_mentions',
            ])
            ->getQuery()
            ->getSingleResult()
        ;
    }
}
