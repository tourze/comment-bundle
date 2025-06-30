<?php

namespace Tourze\CommentBundle\Tests\Integration\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Enum\CommentStatus;
use Tourze\CommentBundle\Event\CommentApprovedEvent;
use Tourze\CommentBundle\Event\CommentCreatedEvent;
use Tourze\CommentBundle\Event\CommentDeletedEvent;
use Tourze\CommentBundle\Event\CommentUpdatedEvent;
use Tourze\CommentBundle\Repository\CommentMentionRepository;
use Tourze\CommentBundle\Repository\CommentRepository;
use Tourze\CommentBundle\Service\CommentService;
use Tourze\CommentBundle\Service\ContentFilterService;
use Tourze\CommentBundle\Service\MentionParserService;

class CommentServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private CommentRepository&MockObject $commentRepository;
    private CommentMentionRepository&MockObject $mentionRepository;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ContentFilterService&MockObject $contentFilter;
    private MentionParserService&MockObject $mentionParser;
    private CommentService $commentService;

    public function test_createComment_withValidData(): void
    {
        $data = [
            'target_type' => 'article',
            'target_id' => '123',
            'content' => 'Test comment content',
            'author_id' => 'user123',
            'author_name' => 'Test User',
            'author_email' => 'test@example.com',
            'author_ip' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0'
        ];

        $this->contentFilter->expects($this->once())
            ->method('isContentSafe')
            ->with($data['content'])
            ->willReturn(true);

        $this->mentionParser->expects($this->once())
            ->method('parseMentions')
            ->with($data['content'])
            ->willReturn([]);

        $this->mentionRepository->expects($this->once())
            ->method('removeMentionsByComment');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Comment::class));

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(CommentCreatedEvent::class),
                CommentCreatedEvent::NAME
            );

        $comment = $this->commentService->createComment($data);

        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals($data['target_type'], $comment->getTargetType());
        $this->assertEquals($data['target_id'], $comment->getTargetId());
        $this->assertEquals($data['content'], $comment->getContent());
        $this->assertEquals($data['author_id'], $comment->getAuthorId());
        $this->assertEquals($data['author_name'], $comment->getAuthorName());
        $this->assertEquals($data['author_email'], $comment->getAuthorEmail());
        $this->assertEquals($data['author_ip'], $comment->getAuthorIp());
        $this->assertEquals($data['user_agent'], $comment->getUserAgent());
    }

    public function test_createComment_withUnsafeContent(): void
    {
        $data = [
            'target_type' => 'article',
            'target_id' => '123',
            'content' => 'Unsafe content with spam'
        ];

        $this->contentFilter->expects($this->once())
            ->method('isContentSafe')
            ->with($data['content'])
            ->willReturn(false);

        $this->mentionParser->expects($this->once())
            ->method('parseMentions')
            ->willReturn([]);

        $this->mentionRepository->expects($this->once())
            ->method('removeMentionsByComment');

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        $comment = $this->commentService->createComment($data);

        $this->assertEquals(CommentStatus::PENDING, $comment->getStatus());
    }

    public function test_createComment_withParent(): void
    {
        $parentComment = new Comment();
        $parentComment->setTargetType('article');
        $parentComment->setTargetId('123');
        $parentComment->setContent('Parent comment');

        $data = [
            'target_type' => 'article',
            'target_id' => '123',
            'content' => 'Reply comment',
            'parent_id' => 1
        ];

        $this->commentRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($parentComment);

        $this->contentFilter->expects($this->once())
            ->method('isContentSafe')
            ->willReturn(true);

        $this->mentionParser->expects($this->once())
            ->method('parseMentions')
            ->willReturn([]);

        $this->mentionRepository->expects($this->once())
            ->method('removeMentionsByComment');

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        $comment = $this->commentService->createComment($data);

        $this->assertEquals($parentComment, $comment->getParent());
    }

    public function test_updateComment_changesContent(): void
    {
        $comment = new Comment();
        $comment->setContent('Original content');

        $updateData = [
            'content' => 'Updated content'
        ];

        $this->contentFilter->expects($this->once())
            ->method('isContentSafe')
            ->with($updateData['content'])
            ->willReturn(true);

        $this->mentionParser->expects($this->once())
            ->method('parseMentions')
            ->with($updateData['content'])
            ->willReturn([]);

        $this->mentionRepository->expects($this->once())
            ->method('removeMentionsByComment');

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(CommentUpdatedEvent::class),
                CommentUpdatedEvent::NAME
            );

        $updatedComment = $this->commentService->updateComment($comment, $updateData);

        $this->assertEquals($updateData['content'], $updatedComment->getContent());
        $this->assertNotNull($updatedComment->getUpdateTime());
    }

    public function test_deleteComment_softDelete(): void
    {
        $comment = new Comment();

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(CommentDeletedEvent::class),
                CommentDeletedEvent::NAME
            );

        $this->commentService->deleteComment($comment, true);

        $this->assertNotNull($comment->getDeleteTime());
        $this->assertEquals(CommentStatus::DELETED, $comment->getStatus());
    }

    public function test_deleteComment_hardDelete(): void
    {
        $comment = new Comment();

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($comment);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(CommentDeletedEvent::class),
                CommentDeletedEvent::NAME
            );

        $this->commentService->deleteComment($comment, false);
    }

    public function test_approveComment_changesStatus(): void
    {
        $comment = new Comment();
        $comment->setStatus(CommentStatus::PENDING);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(CommentApprovedEvent::class),
                CommentApprovedEvent::NAME
            );

        $approvedComment = $this->commentService->approveComment($comment);

        $this->assertEquals(CommentStatus::APPROVED, $approvedComment->getStatus());
    }

    public function test_rejectComment_changesStatus(): void
    {
        $comment = new Comment();
        $comment->setStatus(CommentStatus::PENDING);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $rejectedComment = $this->commentService->rejectComment($comment);

        $this->assertEquals(CommentStatus::REJECTED, $rejectedComment->getStatus());
    }

    public function test_pinComment_setPinStatus(): void
    {
        $comment = new Comment();
        $comment->setPinned(false);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $pinnedComment = $this->commentService->pinComment($comment);

        $this->assertTrue($pinnedComment->isPinned());
    }

    public function test_unpinComment_removePinStatus(): void
    {
        $comment = new Comment();
        $comment->setPinned(true);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $unpinnedComment = $this->commentService->unpinComment($comment);

        $this->assertFalse($unpinnedComment->isPinned());
    }

    public function test_getCommentsByTarget_callsRepository(): void
    {
        $targetType = 'article';
        $targetId = '123';
        $options = ['limit' => 10];
        $expectedComments = [new Comment(), new Comment()];

        $this->commentRepository->expects($this->once())
            ->method('findByTarget')
            ->with($targetType, $targetId, $options)
            ->willReturn($expectedComments);

        $result = $this->commentService->getCommentsByTarget($targetType, $targetId, $options);

        $this->assertEquals($expectedComments, $result);
    }

    public function test_getCommentReplies_callsRepository(): void
    {
        $comment = new Comment();
        $options = ['limit' => 5];
        $expectedReplies = [new Comment(), new Comment()];

        $this->commentRepository->expects($this->once())
            ->method('findRepliesByParent')
            ->with($comment, $options)
            ->willReturn($expectedReplies);

        $result = $this->commentService->getCommentReplies($comment, $options);

        $this->assertEquals($expectedReplies, $result);
    }

    public function test_getCommentCount_callsRepository(): void
    {
        $targetType = 'article';
        $targetId = '123';
        $status = 'approved';
        $expectedCount = 25;

        $this->commentRepository->expects($this->once())
            ->method('countByTarget')
            ->with($targetType, $targetId, $status)
            ->willReturn($expectedCount);

        $result = $this->commentService->getCommentCount($targetType, $targetId, $status);

        $this->assertEquals($expectedCount, $result);
    }

    public function test_canReply_checksDepthLimit(): void
    {
        $level0Comment = new Comment();
        $level1Comment = new Comment();
        $level2Comment = new Comment();
        $level3Comment = new Comment();

        $level1Comment->setParent($level0Comment);
        $level2Comment->setParent($level1Comment);
        $level3Comment->setParent($level2Comment);

        $this->assertTrue($this->commentService->canReply($level0Comment, 3));
        $this->assertTrue($this->commentService->canReply($level1Comment, 3));
        $this->assertTrue($this->commentService->canReply($level2Comment, 3));
        $this->assertFalse($this->commentService->canReply($level3Comment, 3));
    }

    public function test_isAuthor_withAuthorId(): void
    {
        $comment = new Comment();
        $comment->setAuthorId('user123');

        $this->assertTrue($this->commentService->isAuthor($comment, 'user123'));
        $this->assertFalse($this->commentService->isAuthor($comment, 'user456'));
    }

    public function test_isAuthor_withAnonymousAndIp(): void
    {
        $comment = new Comment();
        $comment->setAuthorId(null);
        $comment->setAuthorIp('127.0.0.1');

        $this->assertTrue($this->commentService->isAuthor($comment, null, '127.0.0.1'));
        $this->assertFalse($this->commentService->isAuthor($comment, null, '192.168.1.1'));
    }

    public function test_getCommentById_callsRepository(): void
    {
        $id = 123;
        $expectedComment = new Comment();

        $this->commentRepository->expects($this->once())
            ->method('find')
            ->with($id)
            ->willReturn($expectedComment);

        $result = $this->commentService->getCommentById($id);

        $this->assertEquals($expectedComment, $result);
    }

    public function test_searchComments_callsRepository(): void
    {
        $keyword = 'search term';
        $options = ['limit' => 20];
        $expectedComments = [new Comment()];

        $this->commentRepository->expects($this->once())
            ->method('searchByContent')
            ->with($keyword, $options)
            ->willReturn($expectedComments);

        $result = $this->commentService->searchComments($keyword, $options);

        $this->assertEquals($expectedComments, $result);
    }

    public function test_getStatistics_callsRepository(): void
    {
        $targetType = 'article';
        $targetId = '123';
        $expectedStats = ['total' => 50, 'approved' => 45, 'pending' => 5];

        $this->commentRepository->expects($this->once())
            ->method('getCommentStatistics')
            ->with($targetType, $targetId)
            ->willReturn($expectedStats);

        $result = $this->commentService->getStatistics($targetType, $targetId);

        $this->assertEquals($expectedStats, $result);
    }

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->commentRepository = $this->createMock(CommentRepository::class);
        $this->mentionRepository = $this->createMock(CommentMentionRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->contentFilter = $this->createMock(ContentFilterService::class);
        $this->mentionParser = $this->createMock(MentionParserService::class);

        $this->commentService = new CommentService(
            $this->entityManager,
            $this->commentRepository,
            $this->mentionRepository,
            $this->eventDispatcher,
            $this->contentFilter,
            $this->mentionParser
        );
    }
}