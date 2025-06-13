<?php

namespace Tourze\CommentBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Repository\CommentRepository;
use Tourze\CommentBundle\Service\CommentService;

class CommentServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CommentService $commentService;
    private CommentRepository $commentRepository;

    public function test_createComment_withValidData(): void
    {
        $data = [
            'target_type' => 'article',
            'target_id' => '123',
            'content' => 'This is a test comment',
            'author_id' => 'user123',
            'author_name' => 'Test User',
            'author_email' => 'test@example.com',
            'author_ip' => '127.0.0.1'
        ];

        $comment = $this->commentService->createComment($data);

        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertNotNull($comment->getId());
        $this->assertEquals('article', $comment->getTargetType());
        $this->assertEquals('123', $comment->getTargetId());
        $this->assertEquals('This is a test comment', $comment->getContent());
        $this->assertEquals('user123', $comment->getAuthorId());
        $this->assertEquals('Test User', $comment->getAuthorName());
        $this->assertEquals('test@example.com', $comment->getAuthorEmail());
        $this->assertEquals('127.0.0.1', $comment->getAuthorIp());
        $this->assertEquals('approved', $comment->getStatus());
    }

    public function test_createComment_withAnonymousUser(): void
    {
        $data = [
            'target_type' => 'article',
            'target_id' => '123',
            'content' => 'Anonymous comment',
            'author_ip' => '192.168.1.1'
        ];

        $comment = $this->commentService->createComment($data);

        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertNull($comment->getAuthorId());
        $this->assertEquals('192.168.1.1', $comment->getAuthorIp());
        $this->assertTrue($comment->isAnonymous());
    }

    public function test_createReply_toExistingComment(): void
    {
        // 创建父评论
        $parentData = [
            'target_type' => 'article',
            'target_id' => '123',
            'content' => 'Parent comment',
            'author_id' => 'user123'
        ];
        $parentComment = $this->commentService->createComment($parentData);

        // 创建回复
        $replyData = [
            'target_type' => 'article',
            'target_id' => '123',
            'content' => 'Reply comment',
            'author_id' => 'user456',
            'parent_id' => $parentComment->getId()
        ];
        $replyComment = $this->commentService->createComment($replyData);

        $this->assertInstanceOf(Comment::class, $replyComment);
        $this->assertEquals($parentComment->getId(), $replyComment->getParent()->getId());
        $this->assertTrue($parentComment->hasReplies());
        $this->assertEquals(1, $replyComment->getDepth());
    }

    public function test_updateComment_changesContent(): void
    {
        $comment = $this->createTestComment();
        $originalContent = $comment->getContent();

        $updatedComment = $this->commentService->updateComment($comment, [
            'content' => 'Updated comment content'
        ]);

        $this->assertEquals('Updated comment content', $updatedComment->getContent());
        $this->assertNotEquals($originalContent, $updatedComment->getContent());
        $this->assertNotNull($updatedComment->getUpdatedAt());
    }

    private function createTestComment(
        string $targetType = 'article',
        string $targetId = '123',
        string $content = 'Test comment'
    ): Comment {
        $data = [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'content' => $content,
            'author_id' => 'user123'
        ];
        return $this->commentService->createComment($data);
    }

    public function test_deleteComment_softDelete(): void
    {
        $comment = $this->createTestComment();

        $this->commentService->deleteComment($comment, true);

        $this->assertNotNull($comment->getDeletedAt());
        $this->assertEquals('deleted', $comment->getStatus());
        $this->assertTrue($comment->isDeleted());
    }

    public function test_approveComment_changesStatus(): void
    {
        $comment = $this->createTestComment();
        $comment->setStatus('pending');
        $this->entityManager->flush();

        $approvedComment = $this->commentService->approveComment($comment);

        $this->assertEquals('approved', $approvedComment->getStatus());
        $this->assertTrue($approvedComment->isApproved());
    }

    public function test_getCommentsByTarget_returnsCorrectComments(): void
    {
        $this->createTestComment('article', '123', 'Comment 1');
        $this->createTestComment('article', '123', 'Comment 2');
        $this->createTestComment('article', '456', 'Comment 3');

        $comments = $this->commentService->getCommentsByTarget('article', '123');

        $this->assertCount(2, $comments);
        foreach ($comments as $comment) {
            $this->assertEquals('article', $comment->getTargetType());
            $this->assertEquals('123', $comment->getTargetId());
        }
    }

    public function test_getCommentCount_returnsCorrectCount(): void
    {
        $this->createTestComment('article', '123', 'Comment 1');
        $this->createTestComment('article', '123', 'Comment 2');
        $this->createTestComment('article', '456', 'Comment 3');

        $count = $this->commentService->getCommentCount('article', '123');

        $this->assertEquals(2, $count);
    }

    public function test_searchComments_findsMatchingComments(): void
    {
        $this->createTestComment('article', '123', 'This contains searchterm in content');
        $this->createTestComment('article', '123', 'This does not contain it');
        $this->createTestComment('article', '123', 'Another searchterm comment');

        $results = $this->commentService->searchComments('searchterm');

        $this->assertCount(2, $results);
        foreach ($results as $comment) {
            $this->assertStringContainsString('searchterm', $comment->getContent());
        }
    }

    public function test_canReply_respectsMaxDepth(): void
    {
        $level0 = $this->createTestComment();
        $level1 = $this->createReplyComment($level0);
        $level2 = $this->createReplyComment($level1);
        $level3 = $this->createReplyComment($level2);

        $this->assertTrue($this->commentService->canReply($level0));
        $this->assertTrue($this->commentService->canReply($level1));
        $this->assertTrue($this->commentService->canReply($level2));
        $this->assertFalse($this->commentService->canReply($level3, 3));
    }

    private function createReplyComment(Comment $parent): Comment
    {
        $data = [
            'target_type' => $parent->getTargetType(),
            'target_id' => $parent->getTargetId(),
            'content' => 'Reply to comment ' . $parent->getId(),
            'author_id' => 'user456',
            'parent_id' => $parent->getId()
        ];
        return $this->commentService->createComment($data);
    }

    protected function setUp(): void
    {
        $kernel = self::bootKernel(['environment' => 'test']);

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->commentService = static::getContainer()->get(CommentService::class);
        $this->commentRepository = static::getContainer()->get(CommentRepository::class);

        // 创建数据库表结构
        $this->createSchema();
    }

    private function createSchema(): void
    {
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}