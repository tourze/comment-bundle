<?php

namespace Tourze\CommentBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Repository\CommentMentionRepository;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CommentMentionRepository $mentionRepository
    ) {
    }

    public function notifyReply(Comment $comment): void
    {
        $parent = $comment->getParent();
        
        if ($parent === null || $parent->getAuthorId() === null) {
            return;
        }

        // 避免自己回复自己的通知
        if ($parent->getAuthorId() === $comment->getAuthorId()) {
            return;
        }

        // 这里可以集成邮件服务、站内信服务等
        // 暂时记录日志
        error_log(sprintf(
            'Reply notification: User %s replied to comment %d by user %s',
            $comment->getAuthorId() ?? 'anonymous',
            $parent->getId(),
            $parent->getAuthorId()
        ));
    }

    public function notifyAdminNewComment(Comment $comment): void
    {
        // 通知管理员有新评论需要审核
        error_log(sprintf(
            'Admin notification: New comment %d needs moderation on %s:%s',
            $comment->getId(),
            $comment->getTargetType(),
            $comment->getTargetId()
        ));
    }

    public function notifyCommentApproved(Comment $comment): void
    {
        if ($comment->getAuthorId() === null) {
            return;
        }

        // 通知评论作者评论已通过审核
        error_log(sprintf(
            'Approval notification: Comment %d by user %s has been approved',
            $comment->getId(),
            $comment->getAuthorId()
        ));
    }

    public function processMentionNotifications(Comment $comment): void
    {
        $mentions = $this->mentionRepository->findByComment($comment);
        
        foreach ($mentions as $mention) {
            if (!$mention->isNotified()) {
                $this->notifyMention($comment, $mention->getMentionedUserId());
                $mention->setNotified(true);
            }
        }
        
        $this->entityManager->flush();
    }

    public function notifyMention(Comment $comment, string $mentionedUserId): void
    {
        // 避免自己@自己的通知
        if ($comment->getAuthorId() === $mentionedUserId) {
            return;
        }

        // 发送@提及通知
        error_log(sprintf(
            'Mention notification: User %s mentioned user %s in comment %d',
            $comment->getAuthorId() ?? 'anonymous',
            $mentionedUserId,
            $comment->getId()
        ));
    }

    public function sendEmailNotification(string $email, string $subject, string $content): bool
    {
        // 这里可以集成实际的邮件发送服务
        // 如 Symfony Mailer, SwiftMailer 等
        error_log(sprintf(
            'Email notification: To=%s, Subject=%s',
            $email,
            $subject
        ));
        
        return true;
    }

    public function sendWebhookNotification(string $url, array $data): bool
    {
        // 这里可以发送 webhook 通知
        error_log(sprintf(
            'Webhook notification: URL=%s, Data=%s',
            $url,
            json_encode($data)
        ));
        
        return true;
    }
}
