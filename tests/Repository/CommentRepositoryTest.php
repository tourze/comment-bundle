<?php

namespace Tourze\CommentBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Enum\CommentStatus;
use Tourze\CommentBundle\Repository\CommentRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(CommentRepository::class)]
#[RunTestsInSeparateProcesses]
final class CommentRepositoryTest extends AbstractRepositoryTestCase
{
    private CommentRepository $repository;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(CommentRepository::class);
    }

    public function testFindByWithNullableFieldShouldReturnEntities(): void
    {
        $comment1 = $this->createTestComment();
        $comment2 = $this->createTestComment();
        $comment1->setAuthorName('User One');
        $comment2->setAuthorName(null);
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);

        $commentsWithName = $this->repository->findBy(['authorName' => 'User One']);
        $commentsWithNullName = $this->repository->findBy(['authorName' => null]);

        $this->assertCount(1, $commentsWithName);
        $this->assertEquals('User One', $commentsWithName[0]->getAuthorName());
        $this->assertIsArray($commentsWithNullName);
    }

    public function testCountByNullableFieldShouldReturnCorrectCount(): void
    {
        $comment1 = $this->createTestComment();
        $comment2 = $this->createTestComment();
        $comment3 = $this->createTestComment();
        $comment1->setAuthorEmail('user1@example.com');
        $comment2->setAuthorEmail('user2@example.com');
        $comment3->setAuthorEmail(null);
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);
        $this->persistAndFlush($comment3);

        $nullCount = $this->repository->count(['authorEmail' => null]);
        $notNullCount = $this->repository->count(['authorEmail' => 'user1@example.com']);

        $this->assertGreaterThanOrEqual(1, $nullCount);
        $this->assertEquals(1, $notNullCount);
    }

    public function testCountByAssociationParentShouldReturnCorrectNumber(): void
    {
        $parent = $this->createTestComment();
        $reply1 = $this->createTestComment();
        $reply2 = $this->createTestComment();
        $reply1->setParent($parent);
        $reply2->setParent($parent);
        $this->persistAndFlush($parent);
        $this->persistAndFlush($reply1);
        $this->persistAndFlush($reply2);

        $count = $this->repository->count(['parent' => $parent]);

        $this->assertEquals(2, $count);
    }

    public function testSaveShouldPersistComment(): void
    {
        $comment = $this->createTestComment();

        $this->repository->save($comment);

        $this->assertNotNull($comment->getId());
        $this->assertEquals('Test comment', $comment->getContent());
    }

    public function testSaveShouldNotFlushWhenFlushIsFalse(): void
    {
        $comment = $this->createTestComment();

        $this->repository->save($comment, false);
        self::getEntityManager()->flush();

        $foundComment = $this->repository->find($comment->getId());
        $this->assertInstanceOf(Comment::class, $foundComment);
    }

    public function testRemoveShouldDeleteComment(): void
    {
        $comment = $this->createTestComment();
        $this->persistAndFlush($comment);
        $commentId = $comment->getId();

        $this->repository->remove($comment);

        $foundComment = $this->repository->find($commentId);
        $this->assertNull($foundComment);
    }

    public function testRemoveShouldNotFlushWhenFlushIsFalse(): void
    {
        $comment = $this->createTestComment();
        $this->persistAndFlush($comment);
        $commentId = $comment->getId();

        $this->repository->remove($comment, false);
        self::getEntityManager()->flush();

        $foundComment = $this->repository->find($commentId);
        $this->assertNull($foundComment);
    }

    public function testFindByTargetShouldReturnCommentsForTarget(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setTargetType('post');
        $comment1->setTargetId('100');
        $comment2 = $this->createTestComment();
        $comment2->setTargetType('post');
        $comment2->setTargetId('100');
        $comment3 = $this->createTestComment();
        $comment3->setTargetType('article');
        $comment3->setTargetId('200');
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);
        $this->persistAndFlush($comment3);

        $comments = $this->repository->findByTarget('post', '100');

        $this->assertCount(2, $comments);
        foreach ($comments as $comment) {
            $this->assertEquals('post', $comment->getTargetType());
            $this->assertEquals('100', $comment->getTargetId());
        }
    }

    public function testFindByTargetShouldRespectStatusOption(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setTargetType('post');
        $comment1->setTargetId('100');
        $comment1->setStatus(CommentStatus::APPROVED);
        $comment2 = $this->createTestComment();
        $comment2->setTargetType('post');
        $comment2->setTargetId('100');
        $comment2->setStatus(CommentStatus::PENDING);
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);

        $approvedComments = $this->repository->findByTarget('post', '100', ['status' => 'approved']);
        $pendingComments = $this->repository->findByTarget('post', '100', ['status' => 'pending']);

        $this->assertCount(1, $approvedComments);
        $this->assertCount(1, $pendingComments);
        $this->assertEquals(CommentStatus::APPROVED, $approvedComments[0]->getStatus());
        $this->assertEquals(CommentStatus::PENDING, $pendingComments[0]->getStatus());
    }

    public function testFindByTargetShouldRespectParentOnlyOption(): void
    {
        $parent = $this->createTestComment();
        $parent->setTargetType('post');
        $parent->setTargetId('100');
        $reply = $this->createTestComment();
        $reply->setTargetType('post');
        $reply->setTargetId('100');
        $reply->setParent($parent);
        $this->persistAndFlush($parent);
        $this->persistAndFlush($reply);

        $allComments = $this->repository->findByTarget('post', '100');
        $parentOnlyComments = $this->repository->findByTarget('post', '100', ['parent_only' => true]);

        $this->assertCount(2, $allComments);
        $this->assertCount(1, $parentOnlyComments);
        $this->assertNull($parentOnlyComments[0]->getParent());
    }

    public function testFindByTargetShouldRespectOrderAndLimit(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $comment = $this->createTestComment();
            $comment->setTargetType('post');
            $comment->setTargetId('100');
            $comment->setCreateTime(new \DateTimeImmutable('2023-01-0' . $i));
            $this->persistAndFlush($comment);
        }

        $ascComments = $this->repository->findByTarget('post', '100', ['order_direction' => 'ASC', 'limit' => 3]);
        $descComments = $this->repository->findByTarget('post', '100', ['order_direction' => 'DESC', 'limit' => 2]);

        $this->assertCount(3, $ascComments);
        $this->assertCount(2, $descComments);
    }

    public function testCountByTargetShouldReturnCorrectCount(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setTargetType('post');
        $comment1->setTargetId('100');
        $comment1->setStatus(CommentStatus::APPROVED);
        $comment2 = $this->createTestComment();
        $comment2->setTargetType('post');
        $comment2->setTargetId('100');
        $comment2->setStatus(CommentStatus::APPROVED);
        $comment3 = $this->createTestComment();
        $comment3->setTargetType('post');
        $comment3->setTargetId('100');
        $comment3->setStatus(CommentStatus::PENDING);
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);
        $this->persistAndFlush($comment3);

        $approvedCount = $this->repository->countByTarget('post', '100', 'approved');
        $pendingCount = $this->repository->countByTarget('post', '100', 'pending');

        $this->assertEquals(2, $approvedCount);
        $this->assertEquals(1, $pendingCount);
    }

    public function testFindByAuthorShouldReturnAuthorComments(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setAuthorId('user123');
        $comment2 = $this->createTestComment();
        $comment2->setAuthorId('user123');
        $comment3 = $this->createTestComment();
        $comment3->setAuthorId('user456');
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);
        $this->persistAndFlush($comment3);

        $comments = $this->repository->findByAuthor('user123');

        $this->assertCount(2, $comments);
        foreach ($comments as $comment) {
            $this->assertEquals('user123', $comment->getAuthorId());
        }
    }

    public function testFindByAuthorShouldRespectOptions(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setAuthorId('user123');
        $comment1->setStatus(CommentStatus::APPROVED);
        $comment1->setCreateTime(new \DateTimeImmutable('2023-01-01'));
        $comment2 = $this->createTestComment();
        $comment2->setAuthorId('user123');
        $comment2->setStatus(CommentStatus::PENDING);
        $comment2->setCreateTime(new \DateTimeImmutable('2023-01-02'));
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);

        $approvedComments = $this->repository->findByAuthor('user123', ['status' => 'approved']);
        $limitedComments = $this->repository->findByAuthor('user123', ['limit' => 1]);
        $ascComments = $this->repository->findByAuthor('user123', ['order_direction' => 'ASC']);

        $this->assertCount(1, $approvedComments);
        $this->assertCount(1, $limitedComments);
        $this->assertEquals($comment1->getId(), $ascComments[0]->getId());
    }

    public function testSearchByContentShouldReturnMatchingComments(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setContent('This is a test comment');
        $comment2 = $this->createTestComment();
        $comment2->setContent('Another test message');
        $comment3 = $this->createTestComment();
        $comment3->setContent('No match here');
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);
        $this->persistAndFlush($comment3);

        $comments = $this->repository->searchByContent('test');

        $this->assertCount(2, $comments);
        foreach ($comments as $comment) {
            $this->assertStringContainsString('test', strtolower($comment->getContent()));
        }
    }

    public function testSearchByContentShouldRespectOptions(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setContent('Test comment');
        $comment1->setTargetType('post');
        $comment1->setStatus(CommentStatus::APPROVED);
        $comment2 = $this->createTestComment();
        $comment2->setContent('Test comment');
        $comment2->setTargetType('article');
        $comment2->setStatus(CommentStatus::PENDING);
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);

        $postComments = $this->repository->searchByContent('test', ['target_type' => 'post']);
        $pendingComments = $this->repository->searchByContent('test', ['status' => 'pending']);
        $limitedComments = $this->repository->searchByContent('test', ['limit' => 1]);

        $this->assertCount(1, $postComments);
        $this->assertCount(1, $pendingComments);
        $this->assertCount(1, $limitedComments);
    }

    public function testFindPendingCommentsShouldReturnPendingComments(): void
    {
        // Get initial count
        $initialCount = count($this->repository->findPendingComments());

        $comment1 = $this->createTestComment();
        $comment1->setStatus(CommentStatus::PENDING);
        $comment1->setCreateTime(new \DateTimeImmutable('2023-01-01'));
        $comment2 = $this->createTestComment();
        $comment2->setStatus(CommentStatus::PENDING);
        $comment2->setCreateTime(new \DateTimeImmutable('2023-01-02'));
        $comment3 = $this->createTestComment();
        $comment3->setStatus(CommentStatus::APPROVED);
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);
        $this->persistAndFlush($comment3);

        $pendingComments = $this->repository->findPendingComments();

        $this->assertCount($initialCount + 2, $pendingComments);
        $newComments = array_slice($pendingComments, 0, 2);
        foreach ($newComments as $comment) {
            $this->assertEquals(CommentStatus::PENDING, $comment->getStatus());
        }
        $this->assertEquals($comment1->getId(), $newComments[0]->getId());
    }

    public function testFindPendingCommentsShouldRespectLimit(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $comment = $this->createTestComment();
            $comment->setStatus(CommentStatus::PENDING);
            $this->persistAndFlush($comment);
        }

        $comments = $this->repository->findPendingComments(['limit' => 3]);

        $this->assertCount(3, $comments);
    }

    public function testFindByIpAddressShouldReturnCommentsByIp(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setAuthorIp('192.168.1.1');
        $comment2 = $this->createTestComment();
        $comment2->setAuthorIp('192.168.1.1');
        $comment3 = $this->createTestComment();
        $comment3->setAuthorIp('192.168.1.2');
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);
        $this->persistAndFlush($comment3);

        $comments = $this->repository->findByIpAddress('192.168.1.1');

        $this->assertCount(2, $comments);
        foreach ($comments as $comment) {
            $this->assertEquals('192.168.1.1', $comment->getAuthorIp());
        }
    }

    public function testFindByIpAddressShouldRespectSinceOption(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setAuthorIp('192.168.1.1');
        $comment1->setCreateTime(new \DateTimeImmutable('2023-01-01'));
        $comment2 = $this->createTestComment();
        $comment2->setAuthorIp('192.168.1.1');
        $comment2->setCreateTime(new \DateTimeImmutable('2023-01-03'));
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);

        $allComments = $this->repository->findByIpAddress('192.168.1.1');
        $recentComments = $this->repository->findByIpAddress('192.168.1.1', new \DateTimeImmutable('2023-01-02'));

        $this->assertCount(2, $allComments);
        $this->assertCount(1, $recentComments);
        $this->assertEquals($comment2->getId(), $recentComments[0]->getId());
    }

    public function testFindRepliesByParentShouldReturnReplies(): void
    {
        $parent = $this->createTestComment();
        $reply1 = $this->createTestComment();
        $reply1->setParent($parent);
        $reply1->setCreateTime(new \DateTimeImmutable('2023-01-01'));
        $reply2 = $this->createTestComment();
        $reply2->setParent($parent);
        $reply2->setCreateTime(new \DateTimeImmutable('2023-01-02'));
        $this->persistAndFlush($parent);
        $this->persistAndFlush($reply1);
        $this->persistAndFlush($reply2);

        $replies = $this->repository->findRepliesByParent($parent);

        $this->assertCount(2, $replies);
        foreach ($replies as $reply) {
            $replyParent = $reply->getParent();
            $this->assertNotNull($replyParent);
            $this->assertEquals($parent->getId(), $replyParent->getId());
        }
        $this->assertEquals($reply1->getId(), $replies[0]->getId());
    }

    public function testFindRepliesByParentShouldRespectOptions(): void
    {
        $parent = $this->createTestComment();
        $reply1 = $this->createTestComment();
        $reply1->setParent($parent);
        $reply1->setStatus(CommentStatus::APPROVED);
        $reply2 = $this->createTestComment();
        $reply2->setParent($parent);
        $reply2->setStatus(CommentStatus::PENDING);
        $this->persistAndFlush($parent);
        $this->persistAndFlush($reply1);
        $this->persistAndFlush($reply2);

        $approvedReplies = $this->repository->findRepliesByParent($parent, ['status' => 'approved']);
        $limitedReplies = $this->repository->findRepliesByParent($parent, ['limit' => 1]);

        $this->assertCount(1, $approvedReplies);
        $this->assertCount(1, $limitedReplies);
    }

    public function testFindRecentCommentsShouldReturnRecentComments(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $comment = $this->createTestComment();
            $comment->setCreateTime(new \DateTimeImmutable('2023-01-0' . $i));
            $comment->setStatus(CommentStatus::APPROVED);
            $this->persistAndFlush($comment);
        }

        $recentComments = $this->repository->findRecentComments(3);

        $this->assertCount(3, $recentComments);
        foreach ($recentComments as $comment) {
            $this->assertEquals(CommentStatus::APPROVED, $comment->getStatus());
        }
    }

    public function testFindRecentCommentsShouldRespectStatus(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setStatus(CommentStatus::APPROVED);
        $comment2 = $this->createTestComment();
        $comment2->setStatus(CommentStatus::PENDING);
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);

        $approvedComments = $this->repository->findRecentComments(10, 'approved');
        $pendingComments = $this->repository->findRecentComments(10, 'pending');

        $this->assertGreaterThanOrEqual(1, count($approvedComments));
        $this->assertGreaterThanOrEqual(1, count($pendingComments));
    }

    public function testFindPopularCommentsShouldReturnPopularComments(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setTargetType('post');
        $comment1->setTargetId('100');
        $comment1->setLikesCount(10);
        $comment2 = $this->createTestComment();
        $comment2->setTargetType('post');
        $comment2->setTargetId('100');
        $comment2->setLikesCount(5);
        $comment3 = $this->createTestComment();
        $comment3->setTargetType('article');
        $comment3->setTargetId('200');
        $comment3->setLikesCount(20);
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);
        $this->persistAndFlush($comment3);

        $popularComments = $this->repository->findPopularComments('post', '100', 5);

        $this->assertCount(2, $popularComments);
        $this->assertEquals($comment1->getId(), $popularComments[0]->getId());
        $this->assertEquals($comment2->getId(), $popularComments[1]->getId());
    }

    public function testCountWithIsNullShouldReturnCorrectCount(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setAuthorEmail('user1@example.com');
        $comment1->setUserAgent('Mozilla/5.0');
        $comment2 = $this->createTestComment();
        $comment2->setDeleteTime(new \DateTimeImmutable());
        $comment3 = $this->createTestComment();
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);
        $this->persistAndFlush($comment3);

        $nullEmailCount = $this->repository->count(['authorEmail' => null]);
        $nullUserAgentCount = $this->repository->count(['userAgent' => null]);
        $nullDeleteTimeCount = $this->repository->count(['deleteTime' => null]);

        $this->assertGreaterThanOrEqual(2, $nullEmailCount);
        $this->assertGreaterThanOrEqual(2, $nullUserAgentCount);
        $this->assertGreaterThanOrEqual(2, $nullDeleteTimeCount);
    }

    public function testFindByWithIsNullShouldReturnEntities(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setAuthorEmail('user1@example.com');
        $comment1->setUserAgent('Mozilla/5.0');
        $comment2 = $this->createTestComment();
        $comment2->setDeleteTime(new \DateTimeImmutable());
        $comment3 = $this->createTestComment();
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);
        $this->persistAndFlush($comment3);

        $nullEmailComments = $this->repository->findBy(['authorEmail' => null]);
        $nullUserAgentComments = $this->repository->findBy(['userAgent' => null]);
        $nullDeleteTimeComments = $this->repository->findBy(['deleteTime' => null]);

        $this->assertIsArray($nullEmailComments);
        $this->assertIsArray($nullUserAgentComments);
        $this->assertIsArray($nullDeleteTimeComments);
        $this->assertGreaterThanOrEqual(2, count($nullEmailComments));
        $this->assertGreaterThanOrEqual(2, count($nullUserAgentComments));
        $this->assertGreaterThanOrEqual(2, count($nullDeleteTimeComments));
    }

    public function testFindByWithAuthorNameIsNullShouldReturnEntities(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setAuthorName('John Doe');
        $comment2 = $this->createTestComment();
        $comment2->setAuthorName(null);
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);

        $nullAuthorNameComments = $this->repository->findBy(['authorName' => null]);
        $nonNullAuthorNameComments = $this->repository->findBy(['authorName' => 'John Doe']);

        $this->assertIsArray($nullAuthorNameComments);
        $this->assertIsArray($nonNullAuthorNameComments);
        $this->assertGreaterThanOrEqual(1, count($nullAuthorNameComments));
        $this->assertCount(1, $nonNullAuthorNameComments);
    }

    public function testFindByWithAuthorIpIsNullShouldReturnEntities(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setAuthorIp('192.168.1.1');
        $comment2 = $this->createTestComment();
        $comment2->setAuthorIp(null);
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);

        $nullAuthorIpComments = $this->repository->findBy(['authorIp' => null]);
        $nonNullAuthorIpComments = $this->repository->findBy(['authorIp' => '192.168.1.1']);

        $this->assertIsArray($nullAuthorIpComments);
        $this->assertIsArray($nonNullAuthorIpComments);
        $this->assertGreaterThanOrEqual(1, count($nullAuthorIpComments));
        $this->assertCount(1, $nonNullAuthorIpComments);
    }

    public function testCountWithAuthorNameIsNullShouldReturnCorrectCount(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setAuthorName('John Doe');
        $comment2 = $this->createTestComment();
        $comment2->setAuthorName(null);
        $comment3 = $this->createTestComment();
        $comment3->setAuthorName(null);
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);
        $this->persistAndFlush($comment3);

        $nullAuthorNameCount = $this->repository->count(['authorName' => null]);
        $nonNullAuthorNameCount = $this->repository->count(['authorName' => 'John Doe']);

        $this->assertGreaterThanOrEqual(2, $nullAuthorNameCount);
        $this->assertEquals(1, $nonNullAuthorNameCount);
    }

    public function testCountWithAuthorIpIsNullShouldReturnCorrectCount(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setAuthorIp('192.168.1.1');
        $comment2 = $this->createTestComment();
        $comment2->setAuthorIp(null);
        $comment3 = $this->createTestComment();
        $comment3->setAuthorIp(null);
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);
        $this->persistAndFlush($comment3);

        $nullAuthorIpCount = $this->repository->count(['authorIp' => null]);
        $nonNullAuthorIpCount = $this->repository->count(['authorIp' => '192.168.1.1']);

        $this->assertGreaterThanOrEqual(2, $nullAuthorIpCount);
        $this->assertEquals(1, $nonNullAuthorIpCount);
    }

    public function testCountWithDeleteTimeIsNullShouldReturnCorrectCount(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setDeleteTime(new \DateTimeImmutable());
        $comment2 = $this->createTestComment();
        $comment2->setDeleteTime(null);
        $comment3 = $this->createTestComment();
        $comment3->setDeleteTime(null);
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);
        $this->persistAndFlush($comment3);

        $nullDeleteTimeCount = $this->repository->count(['deleteTime' => null]);
        $nonNullDeleteTimeCount = $this->repository->count(['deleteTime' => $comment1->getDeleteTime()]);

        $this->assertGreaterThanOrEqual(2, $nullDeleteTimeCount);
        $this->assertEquals(1, $nonNullDeleteTimeCount);
    }

    public function testCountByAuthorIdShouldReturnCorrectNumber(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setAuthorId('specific_user');
        $comment2 = $this->createTestComment();
        $comment2->setAuthorId('specific_user');
        $comment3 = $this->createTestComment();
        $comment3->setAuthorId('other_user');
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);
        $this->persistAndFlush($comment3);

        $specificUserCount = $this->repository->count(['authorId' => 'specific_user']);
        $otherUserCount = $this->repository->count(['authorId' => 'other_user']);

        $this->assertEquals(2, $specificUserCount);
        $this->assertEquals(1, $otherUserCount);
    }

    public function testFindOneByAssociationParentShouldReturnMatchingEntity(): void
    {
        $parent = $this->createTestComment();
        $reply = $this->createTestComment();
        $reply->setParent($parent);
        $this->persistAndFlush($parent);
        $this->persistAndFlush($reply);

        $foundReply = $this->repository->findOneBy(['parent' => $parent]);

        $this->assertInstanceOf(Comment::class, $foundReply);
        $foundReplyParent = $foundReply->getParent();
        $this->assertNotNull($foundReplyParent);
        $this->assertEquals($parent->getId(), $foundReplyParent->getId());
    }

    public function testFindOneByTargetTypeShouldReturnMatchingEntity(): void
    {
        $comment1 = $this->createTestComment();
        $comment1->setTargetType('post');
        $comment2 = $this->createTestComment();
        $comment2->setTargetType('article');
        $this->persistAndFlush($comment1);
        $this->persistAndFlush($comment2);

        $foundPost = $this->repository->findOneBy(['targetType' => 'post']);
        $foundArticle = $this->repository->findOneBy(['targetType' => 'article']);

        $this->assertInstanceOf(Comment::class, $foundPost);
        $this->assertEquals('post', $foundPost->getTargetType());
        $this->assertInstanceOf(Comment::class, $foundArticle);
        $this->assertEquals('article', $foundArticle->getTargetType());
    }

    private function createTestComment(): Comment
    {
        $comment = new Comment();
        $comment->setTargetType('article');
        $comment->setTargetId('123');
        $comment->setContent('Test comment');
        $comment->setStatus(CommentStatus::APPROVED);
        $comment->setAuthorId('author123');

        return $comment;
    }

    /**
     * @return ServiceEntityRepository<Comment>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }

    protected function createNewEntity(): object
    {
        $comment = new Comment();
        $comment->setTargetType('article');
        $comment->setTargetId('123');
        $comment->setContent('Test comment');
        $comment->setStatus(CommentStatus::APPROVED);
        $comment->setAuthorId('author123');

        return $comment;
    }
}
