<?php

namespace Tourze\CommentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Enum\CommentStatus;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<Comment>
 */
#[AsRepository(entityClass: Comment::class)]
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    public function save(Comment $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Comment $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param array{status?: string, parent_only?: bool, order_by?: string, order_direction?: string, limit?: int, offset?: int} $options
     * @return array<Comment>
     */
    public function findByTarget(string $targetType, string $targetId, array $options = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.targetType = :targetType')
            ->andWhere('c.targetId = :targetId')
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
        ;

        if (isset($options['status'])) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $options['status'])
            ;
        } else {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', CommentStatus::APPROVED)
            ;
        }

        if ($options['parent_only'] ?? false) {
            $qb->andWhere('c.parent IS NULL');
        }

        $orderBy = $options['order_by'] ?? 'created_at';
        $orderDirection = $options['order_direction'] ?? 'DESC';

        switch ($orderBy) {
            case 'score':
                $qb->addSelect('(c.likesCount - c.dislikesCount) as HIDDEN score')
                    ->orderBy('score', $orderDirection)
                    ->addOrderBy('c.createTime', 'DESC')
                ;
                break;
            case 'likes':
                $qb->orderBy('c.likesCount', $orderDirection)
                    ->addOrderBy('c.createTime', 'DESC')
                ;
                break;
            default:
                $qb->orderBy('c.pinned', 'DESC')
                    ->addOrderBy('c.createTime', $orderDirection)
                ;
        }

        if (($limit = $options['limit'] ?? null) !== null) {
            $qb->setMaxResults($limit);
        }

        if (($offset = $options['offset'] ?? null) !== null) {
            $qb->setFirstResult($offset);
        }

        /** @var array<Comment> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @param array{status?: string, order_direction?: string, limit?: int} $options
     * @return array<Comment>
     */
    public function findRepliesByParent(Comment $parent, array $options = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.parent = :parent')
            ->setParameter('parent', $parent)
        ;

        if (isset($options['status'])) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $options['status'])
            ;
        } else {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', CommentStatus::APPROVED)
            ;
        }

        $orderDirection = $options['order_direction'] ?? 'ASC';
        $qb->orderBy('c.createTime', $orderDirection);

        if (($limit = $options['limit'] ?? null) !== null) {
            $qb->setMaxResults($limit);
        }

        /** @var array<Comment> */
        return $qb->getQuery()->getResult();
    }

    public function countByTarget(string $targetType, string $targetId, string $status = 'approved'): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.targetType = :targetType')
            ->andWhere('c.targetId = :targetId')
            ->andWhere('c.status = :status')
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $result;
    }

    /**
     * @param array{status?: string, order_direction?: string, limit?: int, offset?: int} $options
     * @return array<Comment>
     */
    public function findByAuthor(string $authorId, array $options = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.authorId = :authorId')
            ->setParameter('authorId', $authorId)
        ;

        if (isset($options['status'])) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $options['status'])
            ;
        }

        $orderDirection = $options['order_direction'] ?? 'DESC';
        $qb->orderBy('c.createTime', $orderDirection);

        if (($limit = $options['limit'] ?? null) !== null) {
            $qb->setMaxResults($limit);
        }

        if (($offset = $options['offset'] ?? null) !== null) {
            $qb->setFirstResult($offset);
        }

        /** @var array<Comment> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @param array{target_type?: string, status?: string, order_direction?: string, limit?: int} $options
     * @return array<Comment>
     */
    public function searchByContent(string $keyword, array $options = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.content LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
        ;

        if (isset($options['target_type'])) {
            $qb->andWhere('c.targetType = :targetType')
                ->setParameter('targetType', $options['target_type'])
            ;
        }

        if (isset($options['status'])) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $options['status'])
            ;
        } else {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', CommentStatus::APPROVED)
            ;
        }

        $orderDirection = $options['order_direction'] ?? 'DESC';
        $qb->orderBy('c.createTime', $orderDirection);

        if (($limit = $options['limit'] ?? null) !== null) {
            $qb->setMaxResults($limit);
        }

        /** @var array<Comment> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @param array{limit?: int} $options
     * @return array<Comment>
     */
    public function findPendingComments(array $options = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', CommentStatus::PENDING)
            ->orderBy('c.createTime', 'ASC')
        ;

        if (($limit = $options['limit'] ?? null) !== null) {
            $qb->setMaxResults($limit);
        }

        /** @var array<Comment> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<Comment>
     */
    public function findByIpAddress(string $ipAddress, ?\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.authorIp = :ip')
            ->setParameter('ip', $ipAddress)
            ->orderBy('c.createTime', 'DESC')
        ;

        if (null !== $since) {
            $qb->andWhere('c.createTime >= :since')
                ->setParameter('since', $since)
            ;
        }

        /** @var array<Comment> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @return array{total_comments: int, approved_comments: int, pending_comments: int, rejected_comments: int, total_likes: int, total_dislikes: int}
     */
    public function getCommentStatistics(?string $targetType = null, ?string $targetId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select([
                'COUNT(c.id) as total_comments',
                'SUM(CASE WHEN c.status = \'approved\' THEN 1 ELSE 0 END) as approved_comments',
                'SUM(CASE WHEN c.status = \'pending\' THEN 1 ELSE 0 END) as pending_comments',
                'SUM(CASE WHEN c.status = \'rejected\' THEN 1 ELSE 0 END) as rejected_comments',
                'SUM(c.likesCount) as total_likes',
                'SUM(c.dislikesCount) as total_dislikes',
            ])
        ;

        if (null !== $targetType) {
            $qb->where('c.targetType = :targetType')
                ->setParameter('targetType', $targetType)
            ;

            if (null !== $targetId) {
                $qb->andWhere('c.targetId = :targetId')
                    ->setParameter('targetId', $targetId)
                ;
            }
        }

        /** @var array{total_comments: int, approved_comments: int, pending_comments: int, rejected_comments: int, total_likes: int, total_dislikes: int} */
        return $qb->getQuery()->getSingleResult();
    }

    /**
     * @return array<Comment>
     */
    public function findRecentComments(int $limit = 10, string $status = 'approved'): array
    {
        /** @var array<Comment> */
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', $status)
            ->orderBy('c.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<Comment>
     */
    public function findPopularComments(string $targetType, string $targetId, int $limit = 5): array
    {
        /** @var array<Comment> */
        return $this->createQueryBuilder('c')
            ->where('c.targetType = :targetType')
            ->andWhere('c.targetId = :targetId')
            ->andWhere('c.status = :status')
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->setParameter('status', 'approved')
            ->orderBy('c.likesCount', 'DESC')
            ->addOrderBy('c.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }
}
