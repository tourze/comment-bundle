<?php

namespace Tourze\CommentBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentMention;
use Tourze\CommentBundle\Service\NotificationService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(NotificationService::class)]
#[RunTestsInSeparateProcesses]
final class NotificationServiceTest extends AbstractIntegrationTestCase
{
    private NotificationService $notificationService;

    protected function onSetUp(): void
    {
        $this->notificationService = self::getService(NotificationService::class);
    }

    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(NotificationService::class, $this->notificationService);
    }

    public function testNotifyReplyWithValidParentComment(): void
    {
        $parentComment = new Comment();
        $parentComment->setAuthorId('parent_user');

        $replyComment = new Comment();
        $replyComment->setAuthorId('reply_user');
        $replyComment->setParent($parentComment);

        // 这个方法主要是日志记录，测试不会抛出异常即可
        $this->expectNotToPerformAssertions();
        $this->notificationService->notifyReply($replyComment);
    }

    public function testNotifyReplyWithNullParent(): void
    {
        $replyComment = new Comment();
        $replyComment->setAuthorId('reply_user');
        $replyComment->setParent(null);

        // 测试不会抛出异常
        $this->expectNotToPerformAssertions();
        $this->notificationService->notifyReply($replyComment);
    }

    public function testNotifyAdminNewCommentLogsNotification(): void
    {
        $comment = new Comment();
        $comment->setTargetType('article');
        $comment->setTargetId('123');

        // 测试不会抛出异常
        $this->expectNotToPerformAssertions();
        $this->notificationService->notifyAdminNewComment($comment);
    }

    public function testNotifyCommentApprovedWithRegisteredUser(): void
    {
        $comment = new Comment();
        $comment->setAuthorId('user123');

        // 测试不会抛出异常
        $this->expectNotToPerformAssertions();
        $this->notificationService->notifyCommentApproved($comment);
    }

    public function testNotifyCommentApprovedWithAnonymousUser(): void
    {
        $comment = new Comment();
        $comment->setAuthorId(null);

        // 测试不会抛出异常
        $this->expectNotToPerformAssertions();
        $this->notificationService->notifyCommentApproved($comment);
    }

    public function testSendEmailNotificationReturnsTrue(): void
    {
        $email = 'test@example.com';
        $subject = 'Test Subject';
        $content = 'Test content';

        $result = $this->notificationService->sendEmailNotification($email, $subject, $content);

        $this->assertTrue($result);
    }

    public function testSendWebhookNotificationReturnsTrue(): void
    {
        $url = 'https://example.com/webhook';
        $data = ['event' => 'comment_created', 'comment_id' => 123];

        $result = $this->notificationService->sendWebhookNotification($url, $data);

        $this->assertTrue($result);
    }

    public function testNotifyMentionWithValidUser(): void
    {
        $comment = new Comment();
        $comment->setAuthorId('author_user');
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Comment with @mention');

        // 测试不会抛出异常，主要是日志记录
        $this->expectNotToPerformAssertions();
        $this->notificationService->notifyMention($comment, 'mentioned_user');
    }

    public function testNotifyMentionSkipsSelfMention(): void
    {
        $comment = new Comment();
        $comment->setAuthorId('same_user');
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Comment with self mention');

        // 当用户@自己时，不应该发送通知
        $this->expectNotToPerformAssertions();
        $this->notificationService->notifyMention($comment, 'same_user');
    }

    public function testNotifyMentionWithAnonymousUser(): void
    {
        $comment = new Comment();
        $comment->setAuthorId(null); // 匿名用户
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Anonymous comment with @mention');

        // 匿名用户的@提及也应该正常处理
        $this->expectNotToPerformAssertions();
        $this->notificationService->notifyMention($comment, 'mentioned_user');
    }

    public function testProcessMentionNotificationsWithUnnotifiedMentions(): void
    {
        // 创建评论
        $comment = new Comment();
        $comment->setAuthorId('author_user');
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Comment with @mention');
        self::getEntityManager()->persist($comment);
        self::getEntityManager()->flush();

        // 创建CommentMention记录
        $mention = new CommentMention();
        $mention->setComment($comment);
        $mention->setMentionedUserId('mentioned_user');
        $mention->setMentionedUserName('Mentioned User');
        $mention->setNotified(false); // 未通知状态
        self::getEntityManager()->persist($mention);
        self::getEntityManager()->flush();

        // 处理提及通知
        $this->notificationService->processMentionNotifications($comment);

        // 验证提及记录被标记为已通知
        self::getEntityManager()->refresh($mention);
        $this->assertTrue($mention->isNotified());
        $this->assertNotNull($mention->getNotifyTime());
    }

    public function testProcessMentionNotificationsSkipsAlreadyNotified(): void
    {
        // 创建评论
        $comment = new Comment();
        $comment->setAuthorId('author_user');
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Comment with @mention');
        self::getEntityManager()->persist($comment);
        self::getEntityManager()->flush();

        // 创建已通知的CommentMention记录
        $mention = new CommentMention();
        $mention->setComment($comment);
        $mention->setMentionedUserId('mentioned_user');
        $mention->setMentionedUserName('Mentioned User');
        $mention->setNotified(true); // 已通知状态
        $mention->setNotifyTime(new \DateTimeImmutable('-1 hour'));
        self::getEntityManager()->persist($mention);
        self::getEntityManager()->flush();

        $originalNotifyTime = $mention->getNotifyTime();

        // 处理提及通知
        $this->notificationService->processMentionNotifications($comment);

        // 验证通知时间没有改变
        self::getEntityManager()->refresh($mention);
        $this->assertTrue($mention->isNotified());
        $currentNotifyTime = $mention->getNotifyTime();
        self::assertNotNull($currentNotifyTime, 'NotifyTime should not be null after refresh');
        self::assertNotNull($originalNotifyTime, 'Original NotifyTime should not be null');
        $this->assertEquals($originalNotifyTime->getTimestamp(), $currentNotifyTime->getTimestamp());
    }

    public function testProcessMentionNotificationsWithNoMentions(): void
    {
        // 创建没有提及的评论
        $comment = new Comment();
        $comment->setAuthorId('author_user');
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Regular comment without mentions');
        self::getEntityManager()->persist($comment);
        self::getEntityManager()->flush();

        // 处理提及通知不应该抛出异常
        $this->expectNotToPerformAssertions();
        $this->notificationService->processMentionNotifications($comment);
    }
}
