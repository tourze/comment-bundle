<?php

namespace Tourze\CommentBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\CommentBundle\Event\CommentApprovedEvent;
use Tourze\CommentBundle\Event\CommentCreatedEvent;
use Tourze\CommentBundle\Service\NotificationService;

class CommentNotificationListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CommentCreatedEvent::NAME => 'onCommentCreated',
            CommentApprovedEvent::NAME => 'onCommentApproved',
        ];
    }

    public function onCommentCreated(CommentCreatedEvent $event): void
    {
        $comment = $event->getComment();
        
        // 如果是回复，通知被回复的评论作者
        if ($comment->getParent() !== null) {
            $this->notificationService->notifyReply($comment);
        }
        
        // 通知管理员有新评论需要审核
        if ($comment->isPending()) {
            $this->notificationService->notifyAdminNewComment($comment);
        }
    }

    public function onCommentApproved(CommentApprovedEvent $event): void
    {
        $comment = $event->getComment();
        
        // 通知评论作者评论已通过审核
        $this->notificationService->notifyCommentApproved($comment);
        
        // 处理@提及通知
        $this->notificationService->processMentionNotifications($comment);
    }
}