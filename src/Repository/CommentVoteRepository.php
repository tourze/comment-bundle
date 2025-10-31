<?php

namespace Tourze\CommentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentVote;
use Tourze\CommentBundle\Enum\VoteType;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<CommentVote>
 */
#[AsRepository(entityClass: CommentVote::class)]
class CommentVoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentVote::class);
    }

    public function save(CommentVote $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CommentVote $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function countVotesByType(Comment $comment, string $voteType): int
    {
        $result = $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.comment = :comment')
            ->andWhere('v.voteType = :voteType')
            ->setParameter('comment', $comment)
            ->setParameter('voteType', $voteType)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $result;
    }

    /**
     * @return array{likes: int, dislikes: int, total: int, score: int}
     */
    public function getVoteStatistics(Comment $comment): array
    {
        /** @var array{likes: mixed, dislikes: mixed, total: mixed} */
        $result = $this->createQueryBuilder('v')
            ->select([
                'SUM(CASE WHEN v.voteType = :like THEN 1 ELSE 0 END) as likes',
                'SUM(CASE WHEN v.voteType = :dislike THEN 1 ELSE 0 END) as dislikes',
                'COUNT(v.id) as total',
            ])
            ->where('v.comment = :comment')
            ->setParameter('comment', $comment)
            ->setParameter('like', VoteType::LIKE)
            ->setParameter('dislike', VoteType::DISLIKE)
            ->getQuery()
            ->getSingleResult()
        ;

        $likes = is_numeric($result['likes']) ? (int) $result['likes'] : 0;
        $dislikes = is_numeric($result['dislikes']) ? (int) $result['dislikes'] : 0;
        $total = is_numeric($result['total']) ? (int) $result['total'] : 0;

        return [
            'likes' => $likes,
            'dislikes' => $dislikes,
            'total' => $total,
            'score' => $likes - $dislikes,
        ];
    }

    /**
     * @param array{vote_type?: string, order_direction?: string, limit?: int} $options
     * @return array<CommentVote>
     */
    public function findVotesByVoter(?string $voterId = null, ?string $voterIp = null, array $options = []): array
    {
        $qb = $this->createQueryBuilder('v');

        if (null !== $voterId) {
            $qb->where('v.voterId = :voterId')
                ->setParameter('voterId', $voterId)
            ;
        } elseif (null !== $voterIp) {
            $qb->where('v.voterIp = :voterIp')
                ->andWhere('v.voterId IS NULL')
                ->setParameter('voterIp', $voterIp)
            ;
        } else {
            return [];
        }

        if (isset($options['vote_type'])) {
            $qb->andWhere('v.voteType = :voteType')
                ->setParameter('voteType', $options['vote_type'])
            ;
        }

        $orderDirection = $options['order_direction'] ?? 'DESC';
        $qb->orderBy('v.createTime', $orderDirection);

        if (($limit = $options['limit'] ?? null) !== null) {
            $qb->setMaxResults($limit);
        }

        /** @var array<CommentVote> */
        return $qb->getQuery()->getResult();
    }

    public function removeVotesByComment(Comment $comment): int
    {
        /** @var int */
        return $this->createQueryBuilder('v')
            ->delete()
            ->where('v.comment = :comment')
            ->setParameter('comment', $comment)
            ->getQuery()
            ->execute()
        ;
    }

    public function hasVoted(Comment $comment, ?string $voterId = null, ?string $voterIp = null): bool
    {
        $vote = $this->findByCommentAndVoter($comment, $voterId, $voterIp);

        return null !== $vote;
    }

    public function findByCommentAndVoter(Comment $comment, ?string $voterId = null, ?string $voterIp = null): ?CommentVote
    {
        $qb = $this->createQueryBuilder('v')
            ->where('v.comment = :comment')
            ->setParameter('comment', $comment)
        ;

        if (null !== $voterId) {
            $qb->andWhere('v.voterId = :voterId')
                ->setParameter('voterId', $voterId)
            ;
        } else {
            $qb->andWhere('v.voterId IS NULL');
        }

        if (null !== $voterIp) {
            $qb->andWhere('v.voterIp = :voterIp')
                ->setParameter('voterIp', $voterIp)
            ;
        }

        /** @var CommentVote|null */
        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getVoteType(Comment $comment, ?string $voterId = null, ?string $voterIp = null): ?string
    {
        $vote = $this->findByCommentAndVoter($comment, $voterId, $voterIp);

        return $vote?->getVoteType()->value;
    }
}
