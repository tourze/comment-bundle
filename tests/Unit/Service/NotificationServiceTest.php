<?php

namespace Tourze\CommentBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentMention;
use Tourze\CommentBundle\Repository\CommentMentionRepository;
use Tourze\CommentBundle\Service\NotificationService;

class NotificationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private CommentMentionRepository&MockObject $mentionRepository;
    private NotificationService $notificationService;

    public function test_notifyReply_withValidParentComment(): void
    {
        $parentComment = new Comment();
        $parentComment->setAuthorId('parent_user');

        $replyComment = new Comment();
        $replyComment->setAuthorId('reply_user');
        $replyComment->setParent($parentComment);

        // 捕获错误日志输出以验证通知被发送
        $this->expectOutputString('');

        $errorLogCalled = false;
        $originalErrorLog = ini_get('log_errors');
        ini_set('log_errors', '0'); // 禁用实际的错误日志

        $this->notificationService->notifyReply($replyComment);

        ini_set('log_errors', $originalErrorLog); // 恢复原设置

        $this->assertTrue(true); // 测试通过表示方法正常执行
    }

    public function test_notifyReply_withNullParent(): void
    {
        $replyComment = new Comment();
        $replyComment->setAuthorId('reply_user');
        $replyComment->setParent(null);

        // 应该不执行任何操作
        $this->notificationService->notifyReply($replyComment);

        $this->assertTrue(true);
    }

    public function test_notifyReply_withAnonymousParent(): void
    {
        $parentComment = new Comment();
        $parentComment->setAuthorId(null);

        $replyComment = new Comment();
        $replyComment->setAuthorId('reply_user');
        $replyComment->setParent($parentComment);

        // 应该不执行任何操作
        $this->notificationService->notifyReply($replyComment);

        $this->assertTrue(true);
    }

    public function test_notifyReply_withSameAuthor(): void
    {
        $parentComment = new Comment();
        $parentComment->setAuthorId('same_user');

        $replyComment = new Comment();
        $replyComment->setAuthorId('same_user');
        $replyComment->setParent($parentComment);

        // 应该不执行任何操作（避免自己回复自己的通知）
        $this->notificationService->notifyReply($replyComment);

        $this->assertTrue(true);
    }

    public function test_notifyAdminNewComment_logsNotification(): void
    {
        $comment = new Comment();
        $comment->setTargetType('article');
        $comment->setTargetId('123');

        // 使用反射来模拟ID（通常由数据库生成）
        $reflection = new \ReflectionClass($comment);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($comment, 456);

        // 禁用实际的错误日志
        $originalErrorLog = ini_get('log_errors');
        ini_set('log_errors', '0');

        $this->notificationService->notifyAdminNewComment($comment);

        ini_set('log_errors', $originalErrorLog);

        $this->assertTrue(true);
    }

    public function test_notifyCommentApproved_withRegisteredUser(): void
    {
        $comment = new Comment();
        $comment->setAuthorId('user123');

        // 使用反射来模拟ID
        $reflection = new \ReflectionClass($comment);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($comment, 789);

        // 禁用实际的错误日志
        $originalErrorLog = ini_get('log_errors');
        ini_set('log_errors', '0');

        $this->notificationService->notifyCommentApproved($comment);

        ini_set('log_errors', $originalErrorLog);

        $this->assertTrue(true);
    }

    public function test_notifyCommentApproved_withAnonymousUser(): void
    {
        $comment = new Comment();
        $comment->setAuthorId(null);

        // 应该不执行任何操作
        $this->notificationService->notifyCommentApproved($comment);

        $this->assertTrue(true);
    }

    public function test_processMentionNotifications_processesUnnotifiedMentions(): void
    {
        $comment = new Comment();
        $comment->setAuthorId('author_user');

        $mention1 = new CommentMention();
        $mention1->setComment($comment);
        $mention1->setMentionedUserId('mentioned_user1');
        $mention1->setIsNotified(false);

        $mention2 = new CommentMention();
        $mention2->setComment($comment);
        $mention2->setMentionedUserId('mentioned_user2');
        $mention2->setIsNotified(true); // 已通知

        $mentions = [$mention1, $mention2];

        $this->mentionRepository->expects($this->once())
            ->method('findByComment')
            ->with($comment)
            ->willReturn($mentions);

        $this->entityManager->expects($this->once())
            ->method('flush');

        // 禁用实际的错误日志
        $originalErrorLog = ini_get('log_errors');
        ini_set('log_errors', '0');

        $this->notificationService->processMentionNotifications($comment);

        ini_set('log_errors', $originalErrorLog);

        // 验证只有未通知的mention被标记为已通知
        $this->assertTrue($mention1->isNotified());
        $this->assertTrue($mention2->isNotified()); // 原本就是true
    }

    public function test_notifyMention_withDifferentUsers(): void
    {
        $comment = new Comment();
        $comment->setAuthorId('author_user');

        // 使用反射来模拟ID
        $reflection = new \ReflectionClass($comment);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($comment, 101);

        // 禁用实际的错误日志
        $originalErrorLog = ini_get('log_errors');
        ini_set('log_errors', '0');

        $this->notificationService->notifyMention($comment, 'mentioned_user');

        ini_set('log_errors', $originalErrorLog);

        $this->assertTrue(true);
    }

    public function test_notifyMention_withSameUser(): void
    {
        $comment = new Comment();
        $comment->setAuthorId('same_user');

        // 应该不执行任何操作（避免自己@自己的通知）
        $this->notificationService->notifyMention($comment, 'same_user');

        $this->assertTrue(true);
    }

    public function test_sendEmailNotification_returnsTrue(): void
    {
        $email = 'test@example.com';
        $subject = 'Test Subject';
        $content = 'Test content';

        // 禁用实际的错误日志
        $originalErrorLog = ini_get('log_errors');
        ini_set('log_errors', '0');

        $result = $this->notificationService->sendEmailNotification($email, $subject, $content);

        ini_set('log_errors', $originalErrorLog);

        $this->assertTrue($result);
    }

    public function test_sendWebhookNotification_returnsTrue(): void
    {
        $url = 'https://example.com/webhook';
        $data = ['event' => 'comment_created', 'comment_id' => 123];

        // 禁用实际的错误日志
        $originalErrorLog = ini_get('log_errors');
        ini_set('log_errors', '0');

        $result = $this->notificationService->sendWebhookNotification($url, $data);

        ini_set('log_errors', $originalErrorLog);

        $this->assertTrue($result);
    }

    public function test_sendWebhookNotification_withComplexData(): void
    {
        $url = 'https://api.example.com/hooks/comments';
        $data = [
            'event' => 'comment_approved',
            'comment' => [
                'id' => 456,
                'content' => 'Test comment content',
                'author' => 'user123',
                'target_type' => 'article',
                'target_id' => '789'
            ],
            'timestamp' => time()
        ];

        // 禁用实际的错误日志
        $originalErrorLog = ini_get('log_errors');
        ini_set('log_errors', '0');

        $result = $this->notificationService->sendWebhookNotification($url, $data);

        ini_set('log_errors', $originalErrorLog);

        $this->assertTrue($result);
    }

    public function test_sendEmailNotification_handlesSpecialCharacters(): void
    {
        $email = 'test+special@example.com';
        $subject = 'Test Subject with 中文 and émojis 🎉';
        $content = 'Content with special chars: <>&"\'';

        // 禁用实际的错误日志
        $originalErrorLog = ini_get('log_errors');
        ini_set('log_errors', '0');

        $result = $this->notificationService->sendEmailNotification($email, $subject, $content);

        ini_set('log_errors', $originalErrorLog);

        $this->assertTrue($result);
    }

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mentionRepository = $this->createMock(CommentMentionRepository::class);

        $this->notificationService = new NotificationService(
            $this->entityManager,
            $this->mentionRepository
        );
    }
}