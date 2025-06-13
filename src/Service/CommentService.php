<?php

namespace Tourze\CommentBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentMention;
use Tourze\CommentBundle\Event\CommentApprovedEvent;
use Tourze\CommentBundle\Event\CommentCreatedEvent;
use Tourze\CommentBundle\Event\CommentDeletedEvent;
use Tourze\CommentBundle\Event\CommentUpdatedEvent;
use Tourze\CommentBundle\Repository\CommentMentionRepository;
use Tourze\CommentBundle\Repository\CommentRepository;

class CommentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CommentRepository $commentRepository,
        private readonly CommentMentionRepository $mentionRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ContentFilterService $contentFilter,
        private readonly MentionParserService $mentionParser
    ) {
    }

    public function createComment(array $data): Comment
    {
        $comment = new Comment();
        $comment->setTargetType($data['target_type']);
        $comment->setTargetId($data['target_id']);
        $comment->setContent($data['content']);
        
        if (!empty($data['author_id'])) {
            $comment->setAuthorId($data['author_id']);
        }
        
        if (!empty($data['author_name'])) {
            $comment->setAuthorName($data['author_name']);
        }
        
        if (!empty($data['author_email'])) {
            $comment->setAuthorEmail($data['author_email']);
        }
        
        if (!empty($data['author_ip'])) {
            $comment->setAuthorIp($data['author_ip']);
        }
        
        if (!empty($data['user_agent'])) {
            $comment->setUserAgent($data['user_agent']);
        }
        
        if (!empty($data['parent_id'])) {
            $parent = $this->commentRepository->find($data['parent_id']);
            if ($parent) {
                $comment->setParent($parent);
            }
        }

        // 自动审核内容
        if ($this->contentFilter->isContentSafe($comment->getContent())) {
            $comment->setStatus('approved');
        } else {
            $comment->setStatus('pending');
        }

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        // 处理@提及
        $this->processMentions($comment);

        // 触发事件
        $this->eventDispatcher->dispatch(
            new CommentCreatedEvent($comment),
            CommentCreatedEvent::NAME
        );

        return $comment;
    }

    private function processMentions(Comment $comment): void
    {
        // 删除旧的提及记录
        $this->mentionRepository->removeMentionsByComment($comment);

        // 解析新的@提及
        $mentions = $this->mentionParser->parseMentions($comment->getContent());

        foreach ($mentions as $mention) {
            $commentMention = new CommentMention();
            $commentMention->setComment($comment);
            $commentMention->setMentionedUserId($mention['user_id']);
            if (!empty($mention['user_name'])) {
                $commentMention->setMentionedUserName($mention['user_name']);
            }

            $this->entityManager->persist($commentMention);
        }

        $this->entityManager->flush();
    }

    public function updateComment(Comment $comment, array $data): Comment
    {
        $oldContent = $comment->getContent();

        if (isset($data['content'])) {
            $comment->setContent($data['content']);
            $comment->setUpdatedAt(new \DateTimeImmutable());

            // 重新审核内容
            if ($this->contentFilter->isContentSafe($comment->getContent())) {
                $comment->setStatus('approved');
            } else {
                $comment->setStatus('pending');
            }
        }

        $this->entityManager->flush();

        // 如果内容发生变化，重新处理@提及
        if ($oldContent !== $comment->getContent()) {
            $this->processMentions($comment);
        }

        // 触发事件
        $this->eventDispatcher->dispatch(
            new CommentUpdatedEvent($comment),
            CommentUpdatedEvent::NAME
        );

        return $comment;
    }

    public function deleteComment(Comment $comment, bool $softDelete = true): void
    {
        if ($softDelete) {
            $comment->setDeletedAt(new \DateTimeImmutable());
            $comment->setStatus('deleted');
            $this->entityManager->flush();
        } else {
            $this->entityManager->remove($comment);
            $this->entityManager->flush();
        }

        // 触发事件
        $this->eventDispatcher->dispatch(
            new CommentDeletedEvent($comment),
            CommentDeletedEvent::NAME
        );
    }

    public function approveComment(Comment $comment): Comment
    {
        $comment->setStatus('approved');
        $this->entityManager->flush();

        // 触发事件
        $this->eventDispatcher->dispatch(
            new CommentApprovedEvent($comment),
            CommentApprovedEvent::NAME
        );

        return $comment;
    }

    public function rejectComment(Comment $comment): Comment
    {
        $comment->setStatus('rejected');
        $this->entityManager->flush();

        return $comment;
    }

    public function pinComment(Comment $comment): Comment
    {
        $comment->setIsPinned(true);
        $this->entityManager->flush();

        return $comment;
    }

    public function unpinComment(Comment $comment): Comment
    {
        $comment->setIsPinned(false);
        $this->entityManager->flush();

        return $comment;
    }

    public function getCommentsByTarget(string $targetType, string $targetId, array $options = []): array
    {
        return $this->commentRepository->findByTarget($targetType, $targetId, $options);
    }

    public function getCommentReplies(Comment $comment, array $options = []): array
    {
        return $this->commentRepository->findRepliesByParent($comment, $options);
    }

    public function getCommentCount(string $targetType, string $targetId, string $status = 'approved'): int
    {
        return $this->commentRepository->countByTarget($targetType, $targetId, $status);
    }

    public function searchComments(string $keyword, array $options = []): array
    {
        return $this->commentRepository->searchByContent($keyword, $options);
    }

    public function getPendingComments(array $options = []): array
    {
        return $this->commentRepository->findPendingComments($options);
    }

    public function getCommentsByAuthor(string $authorId, array $options = []): array
    {
        return $this->commentRepository->findByAuthor($authorId, $options);
    }

    public function getCommentsByIp(string $ipAddress, \DateTimeInterface $since = null): array
    {
        return $this->commentRepository->findByIpAddress($ipAddress, $since);
    }

    public function getCommentById(int $id): ?Comment
    {
        return $this->commentRepository->find($id);
    }

    public function getStatistics(string $targetType = null, string $targetId = null): array
    {
        return $this->commentRepository->getCommentStatistics($targetType, $targetId);
    }

    public function getRecentComments(int $limit = 10, string $status = 'approved'): array
    {
        return $this->commentRepository->findRecentComments($limit, $status);
    }

    public function getPopularComments(string $targetType, string $targetId, int $limit = 5): array
    {
        return $this->commentRepository->findPopularComments($targetType, $targetId, $limit);
    }

    public function canReply(Comment $comment, int $maxDepth = 3): bool
    {
        return $comment->getDepth() < $maxDepth;
    }

    public function isAuthor(Comment $comment, ?string $authorId = null, ?string $authorIp = null): bool
    {
        if ($authorId !== null && $comment->getAuthorId() === $authorId) {
            return true;
        }

        if ($authorId === null && $authorIp !== null && $comment->getAuthorIp() === $authorIp) {
            return true;
        }

        return false;
    }
}