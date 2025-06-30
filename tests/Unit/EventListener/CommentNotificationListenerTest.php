<?php

namespace Tourze\CommentBundle\Tests\Unit\EventListener;

use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Event\CommentApprovedEvent;
use Tourze\CommentBundle\Event\CommentCreatedEvent;
use Tourze\CommentBundle\EventListener\CommentNotificationListener;
use Tourze\CommentBundle\Service\NotificationService;

class CommentNotificationListenerTest extends TestCase
{
    private CommentNotificationListener $listener;
    private NotificationService $notificationService;

    protected function setUp(): void
    {
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->listener = new CommentNotificationListener($this->notificationService);
    }

    public function test_getSubscribedEvents_returnsCorrectEvents(): void
    {
        $events = CommentNotificationListener::getSubscribedEvents();
        
        $this->assertArrayHasKey(CommentCreatedEvent::NAME, $events);
        $this->assertArrayHasKey(CommentApprovedEvent::NAME, $events);
        $this->assertEquals('onCommentCreated', $events[CommentCreatedEvent::NAME]);
        $this->assertEquals('onCommentApproved', $events[CommentApprovedEvent::NAME]);
    }

    public function test_onCommentCreated_withParentComment_notifiesReply(): void
    {
        $parentComment = $this->createMock(Comment::class);
        $comment = $this->createMock(Comment::class);
        $comment->method('getParent')->willReturn($parentComment);
        $comment->method('isPending')->willReturn(false);
        
        $event = new CommentCreatedEvent($comment);
        
        $this->notificationService->expects($this->once())
            ->method('notifyReply')
            ->with($comment);
        
        $this->notificationService->expects($this->never())
            ->method('notifyAdminNewComment');
        
        $this->listener->onCommentCreated($event);
    }

    public function test_onCommentCreated_withPendingComment_notifiesAdmin(): void
    {
        $comment = $this->createMock(Comment::class);
        $comment->method('getParent')->willReturn(null);
        $comment->method('isPending')->willReturn(true);
        
        $event = new CommentCreatedEvent($comment);
        
        $this->notificationService->expects($this->never())
            ->method('notifyReply');
        
        $this->notificationService->expects($this->once())
            ->method('notifyAdminNewComment')
            ->with($comment);
        
        $this->listener->onCommentCreated($event);
    }

    public function test_onCommentCreated_withReplyAndPending_notifiesBoth(): void
    {
        $parentComment = $this->createMock(Comment::class);
        $comment = $this->createMock(Comment::class);
        $comment->method('getParent')->willReturn($parentComment);
        $comment->method('isPending')->willReturn(true);
        
        $event = new CommentCreatedEvent($comment);
        
        $this->notificationService->expects($this->once())
            ->method('notifyReply')
            ->with($comment);
        
        $this->notificationService->expects($this->once())
            ->method('notifyAdminNewComment')
            ->with($comment);
        
        $this->listener->onCommentCreated($event);
    }

    public function test_onCommentApproved_notifiesApprovalAndProcessesMentions(): void
    {
        $comment = $this->createMock(Comment::class);
        $event = new CommentApprovedEvent($comment);
        
        $this->notificationService->expects($this->once())
            ->method('notifyCommentApproved')
            ->with($comment);
        
        $this->notificationService->expects($this->once())
            ->method('processMentionNotifications')
            ->with($comment);
        
        $this->listener->onCommentApproved($event);
    }
}