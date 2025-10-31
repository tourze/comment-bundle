<?php

namespace Tourze\CommentBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentMention;
use Tourze\CommentBundle\Enum\CommentStatus;
use Tourze\CommentBundle\Event\CommentApprovedEvent;
use Tourze\CommentBundle\Event\CommentCreatedEvent;
use Tourze\CommentBundle\Event\CommentDeletedEvent;
use Tourze\CommentBundle\Event\CommentUpdatedEvent;
use Tourze\CommentBundle\Repository\CommentMentionRepository;
use Tourze\CommentBundle\Repository\CommentRepository;

readonly class CommentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommentRepository $commentRepository,
        private CommentMentionRepository $mentionRepository,
        private EventDispatcherInterface $eventDispatcher,
        private ContentFilterService $contentFilter,
        private MentionParserService $mentionParser,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createComment(array $data): Comment
    {
        $comment = new Comment();
        $this->setCommentBasicData($comment, $data);
        $this->setCommentAuthorData($comment, $data);
        $this->setCommentParent($comment, $data);

        // 自动审核内容
        $this->setCommentStatus($comment);

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        // 处理@提及
        $this->processMentions($comment);

        // 触发事件
        $this->eventDispatcher->dispatch(new CommentCreatedEvent($comment));

        return $comment;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setCommentBasicData(Comment $comment, array $data): void
    {
        $comment->setTargetType($this->normalizeToString($data['target_type']));
        $comment->setTargetId($this->normalizeToString($data['target_id']));
        $comment->setContent($this->normalizeToString($data['content']));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setCommentAuthorData(Comment $comment, array $data): void
    {
        $this->setAuthorFieldIfPresent($data, 'author_id', function (string $value) use ($comment): void {
            $comment->setAuthorId($value);
        });
        $this->setAuthorFieldIfPresent($data, 'author_name', function (string $value) use ($comment): void {
            $comment->setAuthorName($value);
        });
        $this->setAuthorFieldIfPresent($data, 'author_email', function (string $value) use ($comment): void {
            $comment->setAuthorEmail($value);
        });
        $this->setAuthorFieldIfPresent($data, 'author_ip', function (string $value) use ($comment): void {
            $comment->setAuthorIp($value);
        });
        $this->setAuthorFieldIfPresent($data, 'user_agent', function (string $value) use ($comment): void {
            $comment->setUserAgent($value);
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param callable(string): void $setter
     */
    private function setAuthorFieldIfPresent(array $data, string $fieldName, callable $setter): void
    {
        if (!isset($data[$fieldName]) || '' === $data[$fieldName]) {
            return;
        }

        $value = $this->normalizeToString($data[$fieldName]);
        $setter($value);
    }

    private function normalizeToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function setCommentParent(Comment $comment, array $data): void
    {
        if (isset($data['parent_id'])) {
            $parent = $this->commentRepository->find($data['parent_id']);
            if (null !== $parent) {
                $comment->setParent($parent);
            }
        }
    }

    private function setCommentStatus(Comment $comment): void
    {
        if ($this->contentFilter->isContentSafe($comment->getContent())) {
            $comment->setStatus(CommentStatus::APPROVED);
        } else {
            $comment->setStatus(CommentStatus::PENDING);
        }
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
            $commentMention->setMentionedUserId($this->normalizeToString($mention['user_id']));

            if (isset($mention['user_name']) && '' !== $mention['user_name']) {
                $commentMention->setMentionedUserName($this->normalizeToString($mention['user_name']));
            }

            $this->entityManager->persist($commentMention);
        }

        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateComment(Comment $comment, array $data): Comment
    {
        $oldContent = $comment->getContent();

        if (isset($data['content'])) {
            $content = $this->normalizeToString($data['content']);
            $comment->setContent($content);
            $comment->setUpdateTime(new \DateTimeImmutable());

            // 重新审核内容
            if ($this->contentFilter->isContentSafe($comment->getContent())) {
                $comment->setStatus(CommentStatus::APPROVED);
            } else {
                $comment->setStatus(CommentStatus::PENDING);
            }
        }

        $this->entityManager->flush();

        // 如果内容发生变化，重新处理@提及
        if ($oldContent !== $comment->getContent()) {
            $this->processMentions($comment);
        }

        // 触发事件
        $this->eventDispatcher->dispatch(new CommentUpdatedEvent($comment));

        return $comment;
    }

    public function deleteComment(Comment $comment, bool $softDelete = true): void
    {
        if ($softDelete) {
            $comment->setDeleteTime(new \DateTimeImmutable());
            $comment->setStatus(CommentStatus::DELETED);
            $this->entityManager->flush();
        } else {
            $this->entityManager->remove($comment);
            $this->entityManager->flush();
        }

        // 触发事件
        $this->eventDispatcher->dispatch(new CommentDeletedEvent($comment));
    }

    public function approveComment(Comment $comment): Comment
    {
        $comment->setStatus(CommentStatus::APPROVED);
        $this->entityManager->flush();

        // 触发事件
        $this->eventDispatcher->dispatch(new CommentApprovedEvent($comment));

        return $comment;
    }

    public function rejectComment(Comment $comment): Comment
    {
        $comment->setStatus(CommentStatus::REJECTED);
        $this->entityManager->flush();

        return $comment;
    }

    public function pinComment(Comment $comment): Comment
    {
        $comment->setPinned(true);
        $this->entityManager->flush();

        return $comment;
    }

    public function unpinComment(Comment $comment): Comment
    {
        $comment->setPinned(false);
        $this->entityManager->flush();

        return $comment;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<Comment>
     */
    public function getCommentsByTarget(string $targetType, string $targetId, array $options = []): array
    {
        $normalizedOptions = $this->normalizeQueryOptions($options, [
            'status' => 'string',
            'parent_only' => 'bool',
            'order_by' => 'string',
            'order_direction' => 'string',
            'limit' => 'int',
            'offset' => 'int',
        ]);

        /** @var array{status?: string, parent_only?: bool, order_by?: string, order_direction?: string, limit?: int, offset?: int} $normalizedOptions */
        return $this->commentRepository->findByTarget($targetType, $targetId, $normalizedOptions);
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, string> $typeMap
     * @return array<string, mixed>
     */
    private function normalizeQueryOptions(array $options, array $typeMap): array
    {
        $normalized = [];

        foreach ($typeMap as $key => $type) {
            if (!isset($options[$key])) {
                continue;
            }

            $value = $this->castToType($options[$key], $type);
            if (null !== $value) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function castToType(mixed $value, string $type): mixed
    {
        return match ($type) {
            'string' => is_string($value) ? $value : null,
            'int' => is_int($value) ? $value : null,
            'bool' => is_bool($value) ? $value : null,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $options
     * @return array<Comment>
     */
    public function getCommentReplies(Comment $comment, array $options = []): array
    {
        $normalizedOptions = $this->normalizeQueryOptions($options, [
            'status' => 'string',
            'order_direction' => 'string',
            'limit' => 'int',
        ]);

        /** @var array{status?: string, order_direction?: string, limit?: int} $normalizedOptions */
        return $this->commentRepository->findRepliesByParent($comment, $normalizedOptions);
    }

    public function getCommentCount(string $targetType, string $targetId, string $status = 'approved'): int
    {
        return $this->commentRepository->countByTarget($targetType, $targetId, $status);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<Comment>
     */
    public function searchComments(string $keyword, array $options = []): array
    {
        $normalizedOptions = $this->normalizeQueryOptions($options, [
            'target_type' => 'string',
            'status' => 'string',
            'order_direction' => 'string',
            'limit' => 'int',
        ]);

        /** @var array{target_type?: string, status?: string, order_direction?: string, limit?: int} $normalizedOptions */
        return $this->commentRepository->searchByContent($keyword, $normalizedOptions);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<Comment>
     */
    public function getPendingComments(array $options = []): array
    {
        $normalizedOptions = $this->normalizeQueryOptions($options, [
            'limit' => 'int',
        ]);

        /** @var array{limit?: int} $normalizedOptions */
        return $this->commentRepository->findPendingComments($normalizedOptions);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<Comment>
     */
    public function getCommentsByAuthor(string $authorId, array $options = []): array
    {
        $normalizedOptions = $this->normalizeQueryOptions($options, [
            'status' => 'string',
            'order_direction' => 'string',
            'limit' => 'int',
            'offset' => 'int',
        ]);

        /** @var array{status?: string, order_direction?: string, limit?: int, offset?: int} $normalizedOptions */
        return $this->commentRepository->findByAuthor($authorId, $normalizedOptions);
    }

    /**
     * @return array<Comment>
     */
    public function getCommentsByIp(string $ipAddress, ?\DateTimeInterface $since = null): array
    {
        return $this->commentRepository->findByIpAddress($ipAddress, $since);
    }

    public function getCommentById(int $id): ?Comment
    {
        return $this->commentRepository->find($id);
    }

    /**
     * @return array<string, int|float>
     */
    public function getStatistics(?string $targetType = null, ?string $targetId = null): array
    {
        return $this->commentRepository->getCommentStatistics($targetType, $targetId);
    }

    /**
     * @return array<Comment>
     */
    public function getRecentComments(int $limit = 10, string $status = 'approved'): array
    {
        return $this->commentRepository->findRecentComments($limit, $status);
    }

    /**
     * @return array<Comment>
     */
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
        if (null !== $authorId && $comment->getAuthorId() === $authorId) {
            return true;
        }

        if (null === $authorId && null !== $authorIp && $comment->getAuthorIp() === $authorIp) {
            return true;
        }

        return false;
    }
}
