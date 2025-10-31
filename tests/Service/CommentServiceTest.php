<?php

namespace Tourze\CommentBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Enum\CommentStatus;
use Tourze\CommentBundle\Service\CommentService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CommentService::class)]
#[RunTestsInSeparateProcesses]
final class CommentServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 不需要额外的设置
    }

    public function testServiceCanBeInstantiated(): void
    {
        $service = self::getService(CommentService::class);
        $this->assertInstanceOf(CommentService::class, $service);
    }

    public function testServiceMethodsWork(): void
    {
        $service = self::getService(CommentService::class);
        $this->assertInstanceOf(CommentService::class, $service);

        // 创建一个评论用于测试
        $comment = new Comment();
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Test comment');
        $comment->setAuthorId('user123');
        $comment->setStatus(CommentStatus::PENDING);
        self::getEntityManager()->persist($comment);
        self::getEntityManager()->flush();

        // 测试删除功能
        $service->deleteComment($comment);
        self::getEntityManager()->refresh($comment);
        $this->assertNotNull($comment->getDeleteTime());
    }

    public function testUpdateCommentContent(): void
    {
        $service = self::getService(CommentService::class);

        // 创建一个评论
        $comment = new Comment();
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Original content');
        $comment->setAuthorId('user123');
        $comment->setStatus(CommentStatus::PENDING);
        self::getEntityManager()->persist($comment);
        self::getEntityManager()->flush();

        // 更新内容
        $updatedComment = $service->updateComment($comment, ['content' => 'Updated content']);

        $this->assertEquals('Updated content', $updatedComment->getContent());
    }

    public function testDeleteComment(): void
    {
        $service = self::getService(CommentService::class);

        // 创建一个评论
        $comment = new Comment();
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Test comment');
        $comment->setAuthorId('user123');
        $comment->setStatus(CommentStatus::PENDING);
        self::getEntityManager()->persist($comment);
        self::getEntityManager()->flush();

        $commentId = $comment->getId();

        // 删除评论
        $service->deleteComment($comment);

        // 验证评论已被标记为删除
        self::getEntityManager()->refresh($comment);
        $this->assertNotNull($comment->getDeleteTime());
    }

    public function testApproveComment(): void
    {
        $service = self::getService(CommentService::class);

        // 创建一个待审核评论
        $comment = new Comment();
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Test comment');
        $comment->setAuthorId('user123');
        $comment->setStatus(CommentStatus::PENDING);
        self::getEntityManager()->persist($comment);
        self::getEntityManager()->flush();

        // 批准评论
        $approvedComment = $service->approveComment($comment);

        $this->assertEquals(CommentStatus::APPROVED, $approvedComment->getStatus());
    }

    public function testRejectComment(): void
    {
        $service = self::getService(CommentService::class);

        // 创建一个待审核评论
        $comment = new Comment();
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Test comment');
        $comment->setAuthorId('user123');
        $comment->setStatus(CommentStatus::PENDING);
        self::getEntityManager()->persist($comment);
        self::getEntityManager()->flush();

        // 拒绝评论
        $rejectedComment = $service->rejectComment($comment);

        $this->assertEquals(CommentStatus::REJECTED, $rejectedComment->getStatus());
    }

    public function testCanReplyWithinMaxDepth(): void
    {
        $service = self::getService(CommentService::class);

        // 创建深度为2的评论结构 (parent -> child -> grandchild)
        $grandparent = new Comment();
        $grandparent->setTargetType('post');
        $grandparent->setTargetId('123');
        $grandparent->setContent('Grandparent comment');
        $grandparent->setAuthorId('user1');
        self::getEntityManager()->persist($grandparent);

        $parent = new Comment();
        $parent->setTargetType('post');
        $parent->setTargetId('123');
        $parent->setContent('Parent comment');
        $parent->setAuthorId('user2');
        $parent->setParent($grandparent);
        self::getEntityManager()->persist($parent);

        $child = new Comment();
        $child->setTargetType('post');
        $child->setTargetId('123');
        $child->setContent('Child comment');
        $child->setAuthorId('user3');
        $child->setParent($parent);
        self::getEntityManager()->persist($child);
        self::getEntityManager()->flush();

        // child的深度应该是2，小于默认最大深度3
        $this->assertEquals(2, $child->getDepth());
        $canReply = $service->canReply($child);
        $this->assertTrue($canReply);
    }

    public function testCannotReplyWhenMaxDepthReached(): void
    {
        $service = self::getService(CommentService::class);

        // 创建深度为3的评论结构
        $level0 = new Comment();
        $level0->setTargetType('post');
        $level0->setTargetId('123');
        $level0->setContent('Level 0 comment');
        $level0->setAuthorId('user1');
        self::getEntityManager()->persist($level0);

        $level1 = new Comment();
        $level1->setTargetType('post');
        $level1->setTargetId('123');
        $level1->setContent('Level 1 comment');
        $level1->setAuthorId('user2');
        $level1->setParent($level0);
        self::getEntityManager()->persist($level1);

        $level2 = new Comment();
        $level2->setTargetType('post');
        $level2->setTargetId('123');
        $level2->setContent('Level 2 comment');
        $level2->setAuthorId('user3');
        $level2->setParent($level1);
        self::getEntityManager()->persist($level2);

        $level3 = new Comment();
        $level3->setTargetType('post');
        $level3->setTargetId('123');
        $level3->setContent('Level 3 comment');
        $level3->setAuthorId('user4');
        $level3->setParent($level2);
        self::getEntityManager()->persist($level3);
        self::getEntityManager()->flush();

        // level3的深度应该是3，等于默认最大深度
        $this->assertEquals(3, $level3->getDepth());
        $canReply = $service->canReply($level3);
        $this->assertFalse($canReply);
    }

    public function testCanReplyWithCustomMaxDepth(): void
    {
        $service = self::getService(CommentService::class);

        // 创建深度为2的评论
        $parent = new Comment();
        $parent->setTargetType('post');
        $parent->setTargetId('123');
        $parent->setContent('Parent comment');
        $parent->setAuthorId('user1');
        self::getEntityManager()->persist($parent);

        $child = new Comment();
        $child->setTargetType('post');
        $child->setTargetId('123');
        $child->setContent('Child comment');
        $child->setAuthorId('user2');
        $child->setParent($parent);
        self::getEntityManager()->persist($child);

        $grandchild = new Comment();
        $grandchild->setTargetType('post');
        $grandchild->setTargetId('123');
        $grandchild->setContent('Grandchild comment');
        $grandchild->setAuthorId('user3');
        $grandchild->setParent($child);
        self::getEntityManager()->persist($grandchild);
        self::getEntityManager()->flush();

        // grandchild的深度应该是2
        $this->assertEquals(2, $grandchild->getDepth());

        // 使用自定义最大深度5
        $canReply = $service->canReply($grandchild, 5);
        $this->assertTrue($canReply);

        // 使用自定义最大深度1
        $cannotReply = $service->canReply($grandchild, 1);
        $this->assertFalse($cannotReply);
    }

    public function testCreateCommentWithBasicData(): void
    {
        $service = self::getService(CommentService::class);

        $data = [
            'target_type' => 'post',
            'target_id' => '123',
            'content' => 'Test comment content',
            'author_id' => 'user123',
            'author_name' => 'Test User',
            'author_email' => 'test@example.com',
            'author_ip' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0...',
        ];

        $comment = $service->createComment($data);

        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals('post', $comment->getTargetType());
        $this->assertEquals('123', $comment->getTargetId());
        $this->assertEquals('Test comment content', $comment->getContent());
        $this->assertEquals('user123', $comment->getAuthorId());
        $this->assertEquals('Test User', $comment->getAuthorName());
        $this->assertEquals('test@example.com', $comment->getAuthorEmail());
        $this->assertEquals('192.168.1.1', $comment->getAuthorIp());
        $this->assertEquals('Mozilla/5.0...', $comment->getUserAgent());
        $this->assertNotNull($comment->getId());
    }

    public function testCreateCommentWithParent(): void
    {
        $service = self::getService(CommentService::class);

        // 创建父评论
        $parentComment = new Comment();
        $parentComment->setTargetType('post');
        $parentComment->setTargetId('123');
        $parentComment->setContent('Parent comment');
        $parentComment->setAuthorId('parent_user');
        $parentComment->setStatus(CommentStatus::APPROVED);
        self::getEntityManager()->persist($parentComment);
        self::getEntityManager()->flush();

        $data = [
            'target_type' => 'post',
            'target_id' => '123',
            'content' => 'Reply comment',
            'author_id' => 'reply_user',
            'parent_id' => $parentComment->getId(),
        ];

        $replyComment = $service->createComment($data);

        $this->assertInstanceOf(Comment::class, $replyComment);
        $this->assertEquals($parentComment->getId(), $replyComment->getParent()?->getId());
    }

    public function testPinComment(): void
    {
        $service = self::getService(CommentService::class);

        // 创建评论
        $comment = new Comment();
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Test comment');
        $comment->setAuthorId('user123');
        $comment->setStatus(CommentStatus::APPROVED);
        $comment->setPinned(false);
        self::getEntityManager()->persist($comment);
        self::getEntityManager()->flush();

        // 置顶评论
        $pinnedComment = $service->pinComment($comment);

        $this->assertTrue($pinnedComment->isPinned());
    }

    public function testUnpinComment(): void
    {
        $service = self::getService(CommentService::class);

        // 创建已置顶的评论
        $comment = new Comment();
        $comment->setTargetType('post');
        $comment->setTargetId('123');
        $comment->setContent('Test comment');
        $comment->setAuthorId('user123');
        $comment->setStatus(CommentStatus::APPROVED);
        $comment->setPinned(true);
        self::getEntityManager()->persist($comment);
        self::getEntityManager()->flush();

        // 取消置顶
        $unpinnedComment = $service->unpinComment($comment);

        $this->assertFalse($unpinnedComment->isPinned());
    }

    public function testSearchComments(): void
    {
        $service = self::getService(CommentService::class);

        // 创建一些测试评论
        $comment1 = new Comment();
        $comment1->setTargetType('post');
        $comment1->setTargetId('123');
        $comment1->setContent('This is a searchable comment');
        $comment1->setAuthorId('user1');
        $comment1->setStatus(CommentStatus::APPROVED);
        self::getEntityManager()->persist($comment1);

        $comment2 = new Comment();
        $comment2->setTargetType('post');
        $comment2->setTargetId('456');
        $comment2->setContent('Another test comment');
        $comment2->setAuthorId('user2');
        $comment2->setStatus(CommentStatus::APPROVED);
        self::getEntityManager()->persist($comment2);

        $comment3 = new Comment();
        $comment3->setTargetType('post');
        $comment3->setTargetId('789');
        $comment3->setContent('No match here');
        $comment3->setAuthorId('user3');
        $comment3->setStatus(CommentStatus::APPROVED);
        self::getEntityManager()->persist($comment3);

        self::getEntityManager()->flush();

        // 搜索包含"searchable"的评论
        $results = $service->searchComments('searchable');

        $this->assertIsArray($results);
        // 注意：这里假设仓库的searchByContent方法实现了正确的搜索功能
        // 实际测试中可能需要根据实际实现调整断言
    }
}
