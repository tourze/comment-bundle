<?php

namespace Tourze\CommentBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentVote;
use Tourze\CommentBundle\Enum\VoteType;
use Tourze\CommentBundle\Event\CommentVotedEvent;
use Tourze\CommentBundle\Repository\CommentVoteRepository;

class CommentVoteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CommentVoteRepository $voteRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function vote(Comment $comment, VoteType $voteType, ?string $voterId = null, ?string $voterIp = null): bool
    {

        // 检查是否已经投票
        $existingVote = $this->voteRepository->findByCommentAndVoter($comment, $voterId, $voterIp);

        if ($existingVote !== null) {
            // 如果投票类型相同，取消投票
            if ($existingVote->getVoteType() === $voteType) {
                return $this->removeVote($comment, $voterId, $voterIp);
            }
            
            // 如果投票类型不同，更新投票
            return $this->updateVote($existingVote, $voteType);
        }

        // 创建新投票
        return $this->createVote($comment, $voteType, $voterId, $voterIp);
    }

    public function getVoteType(Comment $comment, ?string $voterId = null, ?string $voterIp = null): ?VoteType
    {
        $voteType = $this->voteRepository->getVoteType($comment, $voterId, $voterIp);
        return $voteType !== null ? VoteType::from($voteType) : null;
    }

    public function removeVote(Comment $comment, ?string $voterId = null, ?string $voterIp = null): bool
    {
        $vote = $this->voteRepository->findByCommentAndVoter($comment, $voterId, $voterIp);

        if ($vote === null) {
            return false;
        }

        $voteType = $vote->getVoteType();

        $this->entityManager->remove($vote);
        $this->updateCommentVoteCount($comment, $voteType, -1);
        $this->entityManager->flush();

        // 触发事件
        $this->eventDispatcher->dispatch(
            new CommentVotedEvent($comment, $voteType, 'removed', $voterId),
            CommentVotedEvent::NAME
        );

        return true;
    }

    private function updateCommentVoteCount(Comment $comment, VoteType $voteType, int $delta): void
    {
        if ($voteType === VoteType::LIKE) {
            $comment->setLikesCount(max(0, $comment->getLikesCount() + $delta));
        } elseif ($voteType === VoteType::DISLIKE) {
            $comment->setDislikesCount(max(0, $comment->getDislikesCount() + $delta));
        }
    }

    private function updateVote(CommentVote $vote, VoteType $newVoteType): bool
    {
        $oldVoteType = $vote->getVoteType();
        $comment = $vote->getComment();

        $vote->setVoteType($newVoteType);

        // 更新评论的投票统计
        $this->updateCommentVoteCount($comment, $oldVoteType, -1);
        $this->updateCommentVoteCount($comment, $newVoteType, 1);

        $this->entityManager->flush();

        // 触发事件
        $this->eventDispatcher->dispatch(
            new CommentVotedEvent($comment, $newVoteType, 'updated', $vote->getVoterId()),
            CommentVotedEvent::NAME
        );

        return true;
    }

    private function createVote(Comment $comment, VoteType $voteType, ?string $voterId = null, ?string $voterIp = null): bool
    {
        $vote = new CommentVote();
        $vote->setComment($comment);
        $vote->setVoteType($voteType);
        $vote->setVoterId($voterId);
        $vote->setVoterIp($voterIp);

        $this->entityManager->persist($vote);
        $this->updateCommentVoteCount($comment, $voteType, 1);
        $this->entityManager->flush();

        // 触发事件
        $this->eventDispatcher->dispatch(
            new CommentVotedEvent($comment, $voteType, 'created', $voterId),
            CommentVotedEvent::NAME
        );

        return true;
    }

    public function hasVoted(Comment $comment, ?string $voterId = null, ?string $voterIp = null): bool
    {
        return $this->voteRepository->hasVoted($comment, $voterId, $voterIp);
    }

    public function getVotesByVoter(?string $voterId = null, ?string $voterIp = null, array $options = []): array
    {
        return $this->voteRepository->findVotesByVoter($voterId, $voterIp, $options);
    }

    public function canVote(Comment $comment, ?string $voterId = null, ?string $voterIp = null): bool
    {
        // 检查评论是否已批准
        if (!$comment->isApproved()) {
            return false;
        }

        // 检查评论是否已删除
        if ($comment->isDeleted()) {
            return false;
        }

        // 检查是否为匿名用户且没有IP地址
        if ($voterId === null && $voterIp === null) {
            return false;
        }

        return true;
    }

    public function refreshCommentVoteCounts(Comment $comment): Comment
    {
        $stats = $this->getVoteStatistics($comment);

        $comment->setLikesCount($stats['likes']);
        $comment->setDislikesCount($stats['dislikes']);

        $this->entityManager->flush();

        return $comment;
    }

    public function getVoteStatistics(Comment $comment): array
    {
        return $this->voteRepository->getVoteStatistics($comment);
    }
}