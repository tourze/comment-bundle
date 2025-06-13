<?php

namespace Tourze\CommentBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentVote;
use Tourze\CommentBundle\Enum\VoteType;

class CommentVoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentVote::class);
    }

    public function countVotesByType(Comment $comment, string $voteType): int
    {
        return $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.comment = :comment')
            ->andWhere('v.voteType = :voteType')
            ->setParameter('comment', $comment)
            ->setParameter('voteType', $voteType)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getVoteStatistics(Comment $comment): array
    {
        $result = $this->createQueryBuilder('v')
            ->select([
                'COUNT(CASE WHEN v.voteType = :like THEN 1 END) as likes',
                'COUNT(CASE WHEN v.voteType = :dislike THEN 1 END) as dislikes',
                'COUNT(v.id) as total'
            ])
            ->where('v.comment = :comment')
            ->setParameter('comment', $comment)
            ->setParameter('like', VoteType::LIKE)
            ->setParameter('dislike', VoteType::DISLIKE)
            ->getQuery()
            ->getSingleResult();

        return [
            'likes' => (int) $result['likes'],
            'dislikes' => (int) $result['dislikes'],
            'total' => (int) $result['total'],
            'score' => (int) $result['likes'] - (int) $result['dislikes']
        ];
    }

    public function findVotesByVoter(string $voterId = null, string $voterIp = null, array $options = []): array
    {
        $qb = $this->createQueryBuilder('v');

        if ($voterId !== null) {
            $qb->where('v.voterId = :voterId')
               ->setParameter('voterId', $voterId);
        } elseif ($voterIp !== null) {
            $qb->where('v.voterIp = :voterIp')
               ->andWhere('v.voterId IS NULL')
               ->setParameter('voterIp', $voterIp);
        } else {
            return [];
        }

        if ($options['vote_type'] ?? null) {
            $qb->andWhere('v.voteType = :voteType')
               ->setParameter('voteType', $options['vote_type']);
        }

        $orderDirection = $options['order_direction'] ?? 'DESC';
        $qb->orderBy('v.createdAt', $orderDirection);

        if ($limit = $options['limit'] ?? null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function removeVotesByComment(Comment $comment): int
    {
        return $this->createQueryBuilder('v')
            ->delete()
            ->where('v.comment = :comment')
            ->setParameter('comment', $comment)
            ->getQuery()
            ->execute();
    }

    public function hasVoted(Comment $comment, ?string $voterId = null, ?string $voterIp = null): bool
    {
        $vote = $this->findByCommentAndVoter($comment, $voterId, $voterIp);
        return $vote !== null;
    }

    public function findByCommentAndVoter(Comment $comment, ?string $voterId = null, ?string $voterIp = null): ?CommentVote
    {
        $qb = $this->createQueryBuilder('v')
            ->where('v.comment = :comment')
            ->setParameter('comment', $comment);

        if ($voterId !== null) {
            $qb->andWhere('v.voterId = :voterId')
               ->setParameter('voterId', $voterId);
        } else {
            $qb->andWhere('v.voterId IS NULL');
        }

        if ($voterIp !== null) {
            $qb->andWhere('v.voterIp = :voterIp')
               ->setParameter('voterIp', $voterIp);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getVoteType(Comment $comment, ?string $voterId = null, ?string $voterIp = null): ?string
    {
        $vote = $this->findByCommentAndVoter($comment, $voterId, $voterIp);
        return $vote?->getVoteType();
    }
}