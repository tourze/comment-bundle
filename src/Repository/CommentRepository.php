<?php

namespace Tourze\CommentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Enum\CommentStatus;

class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    public function findByTarget(string $targetType, string $targetId, array $options = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.targetType = :targetType')
            ->andWhere('c.targetId = :targetId')
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId);

        if ($options['status'] ?? null) {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $options['status']);
        } else {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', CommentStatus::APPROVED);
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
                   ->addOrderBy('c.createTime', 'DESC');
                break;
            case 'likes':
                $qb->orderBy('c.likesCount', $orderDirection)
                   ->addOrderBy('c.createTime', 'DESC');
                break;
            default:
                $qb->orderBy('c.pinned', 'DESC')
                   ->addOrderBy('c.createTime', $orderDirection);
        }

        if ($limit = $options['limit'] ?? null) {
            $qb->setMaxResults($limit);
        }

        if ($offset = $options['offset'] ?? null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    public function findRepliesByParent(Comment $parent, array $options = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.parent = :parent')
            ->setParameter('parent', $parent);

        if ($options['status'] ?? null) {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $options['status']);
        } else {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', CommentStatus::APPROVED);
        }

        $orderDirection = $options['order_direction'] ?? 'ASC';
        $qb->orderBy('c.createTime', $orderDirection);

        if ($limit = $options['limit'] ?? null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByTarget(string $targetType, string $targetId, string $status = 'approved'): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.targetType = :targetType')
            ->andWhere('c.targetId = :targetId')
            ->andWhere('c.status = :status')
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByAuthor(string $authorId, array $options = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.authorId = :authorId')
            ->setParameter('authorId', $authorId);

        if ($options['status'] ?? null) {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $options['status']);
        }

        $orderDirection = $options['order_direction'] ?? 'DESC';
        $qb->orderBy('c.createTime', $orderDirection);

        if ($limit = $options['limit'] ?? null) {
            $qb->setMaxResults($limit);
        }

        if ($offset = $options['offset'] ?? null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    public function searchByContent(string $keyword, array $options = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.content LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%');

        if ($options['target_type'] ?? null) {
            $qb->andWhere('c.targetType = :targetType')
               ->setParameter('targetType', $options['target_type']);
        }

        if ($options['status'] ?? null) {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $options['status']);
        } else {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', CommentStatus::APPROVED);
        }

        $orderDirection = $options['order_direction'] ?? 'DESC';
        $qb->orderBy('c.createTime', $orderDirection);

        if ($limit = $options['limit'] ?? null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findPendingComments(array $options = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', CommentStatus::PENDING)
            ->orderBy('c.createdAt', 'ASC');

        if ($limit = $options['limit'] ?? null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByIpAddress(string $ipAddress, ?\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.authorIp = :ip')
            ->setParameter('ip', $ipAddress)
            ->orderBy('c.createTime', 'DESC');

        if ($since !== null) {
            $qb->andWhere('c.createdAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    public function getCommentStatistics(?string $targetType = null, ?string $targetId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select([
                'COUNT(c.id) as total_comments',
                'COUNT(CASE WHEN c.status = \'approved\' THEN 1 END) as approved_comments',
                'COUNT(CASE WHEN c.status = \'pending\' THEN 1 END) as pending_comments',
                'COUNT(CASE WHEN c.status = \'rejected\' THEN 1 END) as rejected_comments',
                'SUM(c.likesCount) as total_likes',
                'SUM(c.dislikesCount) as total_dislikes',
            ]);

        if ($targetType !== null) {
            $qb->where('c.targetType = :targetType')
               ->setParameter('targetType', $targetType);
            
            if ($targetId !== null) {
                $qb->andWhere('c.targetId = :targetId')
                   ->setParameter('targetId', $targetId);
            }
        }

        return $qb->getQuery()->getSingleResult();
    }

    public function findRecentComments(int $limit = 10, string $status = 'approved'): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', $status)
            ->orderBy('c.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPopularComments(string $targetType, string $targetId, int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.targetType = :targetType')
            ->andWhere('c.targetId = :targetId')
            ->andWhere('c.status = :status')
            ->setParameter('targetType', $targetType)
            ->setParameter('targetId', $targetId)
            ->setParameter('status', 'approved')
            ->orderBy('c.likesCount', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    private function addTargetFilter(QueryBuilder $qb, ?string $targetType = null, ?string $targetId = null): QueryBuilder
    {
        if ($targetType !== null) {
            $qb->andWhere('c.targetType = :targetType')
               ->setParameter('targetType', $targetType);
        }

        if ($targetId !== null) {
            $qb->andWhere('c.targetId = :targetId')
               ->setParameter('targetId', $targetId);
        }

        return $qb;
    }
}