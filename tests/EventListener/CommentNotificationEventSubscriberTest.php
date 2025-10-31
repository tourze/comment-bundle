<?php

namespace Tourze\CommentBundle\Tests\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Enum\CommentStatus;
use Tourze\CommentBundle\Event\CommentApprovedEvent;
use Tourze\CommentBundle\Event\CommentCreatedEvent;
use Tourze\CommentBundle\EventListener\CommentNotificationEventSubscriber;
use Tourze\CommentBundle\Service\NotificationService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(CommentNotificationEventSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class CommentNotificationEventSubscriberTest extends AbstractEventSubscriberTestCase
{
    protected function onSetUp(): void
    {
        // 不需要额外的设置
    }

    public function testGetSubscribedEventsReturnsCorrectEvents(): void
    {
        $events = CommentNotificationEventSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('comment.created', $events);
        $this->assertArrayHasKey('comment.approved', $events);
        $this->assertEquals('onCommentCreated', $events['comment.created']);
        $this->assertEquals('onCommentApproved', $events['comment.approved']);
    }

    public function testListenerCanBeInstantiated(): void
    {
        $listener = self::getService(CommentNotificationEventSubscriber::class);
        $this->assertInstanceOf(CommentNotificationEventSubscriber::class, $listener);
    }

    public function testOnCommentCreatedWithReplyNotifiesParentAuthor(): void
    {
        // 创建父评论
        $parentComment = new Comment();
        $parentComment->setAuthorId('parent_user');
        $parentComment->setTargetType('post');
        $parentComment->setTargetId('123');
        $parentComment->setContent('Parent comment');
        $parentComment->setStatus(CommentStatus::APPROVED);

        // 创建回复评论
        $replyComment = new Comment();
        $replyComment->setAuthorId('reply_user');
        $replyComment->setTargetType('post');
        $replyComment->setTargetId('123');
        $replyComment->setContent('Reply comment');
        $replyComment->setParent($parentComment);
        $replyComment->setStatus(CommentStatus::APPROVED);

        // 创建Mock NotificationService并注入到容器
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())
            ->method('notifyReply')
            ->with($replyComment)
        ;

        // 不期望调用管理员通知，因为评论已审核
        $notificationService->expects($this->never())
            ->method('notifyAdminNewComment')
        ;

        // 在容器中注册Mock服务
        self::getContainer()->set(NotificationService::class, $notificationService);

        // 从容器获取订阅者
        $subscriber = self::getService(CommentNotificationEventSubscriber::class);
        $event = new CommentCreatedEvent($replyComment);

        $subscriber->onCommentCreated($event);
    }

    public function testOnCommentCreatedWithPendingCommentNotifiesAdmin(): void
    {
        // 创建待审核评论
        $comment = new Comment();
        $comment->setAuthorId('test_user');
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Test comment');
        $comment->setStatus(CommentStatus::PENDING);

        // 创建Mock NotificationService
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())
            ->method('notifyAdminNewComment')
            ->with($comment)
        ;

        // 不期望调用回复通知，因为不是回复
        $notificationService->expects($this->never())
            ->method('notifyReply')
        ;

        // 在容器中注册Mock服务
        self::getContainer()->set(NotificationService::class, $notificationService);

        // 从容器获取订阅者
        $subscriber = self::getService(CommentNotificationEventSubscriber::class);
        $event = new CommentCreatedEvent($comment);

        $subscriber->onCommentCreated($event);
    }

    public function testOnCommentCreatedWithApprovedTopLevelCommentDoesNotNotify(): void
    {
        // 创建已审核的顶级评论
        $comment = new Comment();
        $comment->setAuthorId('test_user');
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Test comment');
        $comment->setStatus(CommentStatus::APPROVED);

        // 创建Mock NotificationService
        $notificationService = $this->createMock(NotificationService::class);

        // 不期望调用任何通知方法
        $notificationService->expects($this->never())
            ->method('notifyReply')
        ;
        $notificationService->expects($this->never())
            ->method('notifyAdminNewComment')
        ;

        // 在容器中注册Mock服务
        self::getContainer()->set(NotificationService::class, $notificationService);

        // 从容器获取订阅者
        $subscriber = self::getService(CommentNotificationEventSubscriber::class);
        $event = new CommentCreatedEvent($comment);

        $subscriber->onCommentCreated($event);
    }

    public function testOnCommentApprovedNotifiesAuthorAndProcessesMentions(): void
    {
        // 创建已审核评论
        $comment = new Comment();
        $comment->setAuthorId('test_user');
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Test comment with @mention');
        $comment->setStatus(CommentStatus::APPROVED);

        // 创建Mock NotificationService
        $notificationService = $this->createMock(NotificationService::class);

        // 期望调用两个方法
        $notificationService->expects($this->once())
            ->method('notifyCommentApproved')
            ->with($comment)
        ;
        $notificationService->expects($this->once())
            ->method('processMentionNotifications')
            ->with($comment)
        ;

        // 在容器中注册Mock服务
        self::getContainer()->set(NotificationService::class, $notificationService);

        // 从容器获取订阅者
        $subscriber = self::getService(CommentNotificationEventSubscriber::class);
        $event = new CommentApprovedEvent($comment);

        $subscriber->onCommentApproved($event);
    }
}
