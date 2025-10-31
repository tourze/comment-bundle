<?php

namespace Tourze\CommentBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentMention;
use Tourze\CommentBundle\Enum\CommentStatus;
use Tourze\CommentBundle\Repository\CommentMentionRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(CommentMentionRepository::class)]
#[RunTestsInSeparateProcesses]
final class CommentMentionRepositoryTest extends AbstractRepositoryTestCase
{
    private CommentMentionRepository $repository;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(CommentMentionRepository::class);
    }

    public function testConstructSetsEntityClass(): void
    {
        $this->assertInstanceOf(CommentMentionRepository::class, $this->repository);
    }

    public function testCountByAssociationCommentShouldReturnCorrectNumber(): void
    {
        $comment = $this->createTestComment();
        $mention1 = $this->createTestMention($comment, 'user1');
        $mention2 = $this->createTestMention($comment, 'user2');
        $this->persistAndFlush($mention1);
        $this->persistAndFlush($mention2);

        $count = $this->repository->count(['comment' => $comment]);

        $this->assertEquals(2, $count);
    }

    public function testSaveShouldPersistMention(): void
    {
        $comment = $this->createTestComment();
        $mention = $this->createTestMention($comment, 'user123');

        $this->repository->save($mention);

        $this->assertNotNull($mention->getId());
        $this->assertEquals('user123', $mention->getMentionedUserId());
    }

    public function testSaveShouldNotFlushWhenFlushIsFalse(): void
    {
        $comment = $this->createTestComment();
        $mention = $this->createTestMention($comment, 'user123');

        $this->repository->save($mention, false);
        self::getEntityManager()->flush();

        $foundMention = $this->repository->find($mention->getId());
        $this->assertInstanceOf(CommentMention::class, $foundMention);
    }

    public function testRemoveShouldDeleteMention(): void
    {
        $comment = $this->createTestComment();
        $mention = $this->createTestMention($comment, 'user123');
        $this->persistAndFlush($mention);
        $mentionId = $mention->getId();

        $this->repository->remove($mention);

        $foundMention = $this->repository->find($mentionId);
        $this->assertNull($foundMention);
    }

    public function testRemoveShouldNotFlushWhenFlushIsFalse(): void
    {
        $comment = $this->createTestComment();
        $mention = $this->createTestMention($comment, 'user123');
        $this->persistAndFlush($mention);
        $mentionId = $mention->getId();

        $this->repository->remove($mention, false);
        self::getEntityManager()->flush();

        $foundMention = $this->repository->find($mentionId);
        $this->assertNull($foundMention);
    }

    public function testFindByMentionedUserShouldReturnUserMentions(): void
    {
        $comment1 = $this->createTestComment();
        $comment2 = $this->createTestComment();
        $mention1 = $this->createTestMention($comment1, 'user123');
        $mention2 = $this->createTestMention($comment2, 'user123');
        $mention3 = $this->createTestMention($comment1, 'user456');
        $this->persistAndFlush($mention1);
        $this->persistAndFlush($mention2);
        $this->persistAndFlush($mention3);

        $mentions = $this->repository->findByMentionedUser('user123');

        $this->assertCount(2, $mentions);
        foreach ($mentions as $mention) {
            $this->assertEquals('user123', $mention->getMentionedUserId());
        }
    }

    public function testFindByMentionedUserShouldRespectIsNotifiedOption(): void
    {
        $comment1 = $this->createTestComment();
        $comment2 = $this->createTestComment();
        $mention1 = $this->createTestMention($comment1, 'user123');
        $mention2 = $this->createTestMention($comment2, 'user123');
        $mention1->setNotified(true);
        $mention2->setNotified(false);
        $this->persistAndFlush($mention1);
        $this->persistAndFlush($mention2);

        $notifiedMentions = $this->repository->findByMentionedUser('user123', ['is_notified' => true]);
        $unnotifiedMentions = $this->repository->findByMentionedUser('user123', ['is_notified' => false]);

        $this->assertCount(1, $notifiedMentions);
        $this->assertCount(1, $unnotifiedMentions);
        $this->assertTrue($notifiedMentions[0]->isNotified());
        $this->assertFalse($unnotifiedMentions[0]->isNotified());
    }

    public function testFindByMentionedUserShouldRespectOrderDirection(): void
    {
        $comment1 = $this->createTestComment();
        $comment2 = $this->createTestComment();
        $mention1 = $this->createTestMention($comment1, 'user123');
        $mention2 = $this->createTestMention($comment2, 'user123');
        $mention1->setCreateTime(new \DateTimeImmutable('2023-01-01'));
        $mention2->setCreateTime(new \DateTimeImmutable('2023-01-02'));
        $this->persistAndFlush($mention1);
        $this->persistAndFlush($mention2);

        $ascMentions = $this->repository->findByMentionedUser('user123', ['order_direction' => 'ASC']);
        $descMentions = $this->repository->findByMentionedUser('user123', ['order_direction' => 'DESC']);

        $this->assertEquals($mention1->getId(), $ascMentions[0]->getId());
        $this->assertEquals($mention2->getId(), $descMentions[0]->getId());
    }

    public function testFindByMentionedUserShouldRespectLimit(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $comment = $this->createTestComment();
            $mention = $this->createTestMention($comment, 'user123');
            $this->persistAndFlush($mention);
        }

        $mentions = $this->repository->findByMentionedUser('user123', ['limit' => 3]);

        $this->assertCount(3, $mentions);
    }

    public function testFindUnnotifiedMentionsShouldReturnUnnotifiedMentions(): void
    {
        // Get initial count
        $initialCount = count($this->repository->findUnnotifiedMentions());

        $comment1 = $this->createTestComment();
        $comment2 = $this->createTestComment();
        $comment3 = $this->createTestComment();
        $mention1 = $this->createTestMention($comment1, 'user1');
        $mention2 = $this->createTestMention($comment2, 'user2');
        $mention3 = $this->createTestMention($comment3, 'user3');
        $mention1->setNotified(true);
        $mention2->setNotified(false);
        $mention3->setNotified(false);
        $this->persistAndFlush($mention1);
        $this->persistAndFlush($mention2);
        $this->persistAndFlush($mention3);

        $unnotifiedMentions = $this->repository->findUnnotifiedMentions();

        $this->assertCount($initialCount + 2, $unnotifiedMentions);
        $newMentions = array_slice($unnotifiedMentions, -2);
        foreach ($newMentions as $mention) {
            $this->assertFalse($mention->isNotified());
        }
    }

    public function testFindUnnotifiedMentionsShouldRespectLimit(): void
    {
        $comment = $this->createTestComment();
        for ($i = 1; $i <= 5; ++$i) {
            $mention = $this->createTestMention($comment, 'user' . $i);
            $mention->setNotified(false);
            $this->persistAndFlush($mention);
        }

        $mentions = $this->repository->findUnnotifiedMentions(3);

        $this->assertCount(3, $mentions);
    }

    public function testCountUnnotifiedByUserShouldReturnCorrectCount(): void
    {
        $comment1 = $this->createTestComment();
        $comment2 = $this->createTestComment();
        $mention1 = $this->createTestMention($comment1, 'user123');
        $mention2 = $this->createTestMention($comment2, 'user123');
        $mention3 = $this->createTestMention($comment1, 'user456');
        $mention1->setNotified(true);
        $mention2->setNotified(false);
        $mention3->setNotified(false);
        $this->persistAndFlush($mention1);
        $this->persistAndFlush($mention2);
        $this->persistAndFlush($mention3);

        $count = $this->repository->countUnnotifiedByUser('user123');

        $this->assertEquals(1, $count);
    }

    public function testMarkAsNotifiedShouldUpdateMentions(): void
    {
        $comment = $this->createTestComment();
        $mention1 = $this->createTestMention($comment, 'user1');
        $mention2 = $this->createTestMention($comment, 'user2');
        $mention1->setNotified(false);
        $mention2->setNotified(false);
        $this->persistAndFlush($mention1);
        $this->persistAndFlush($mention2);

        $mention1Id = $mention1->getId();
        $mention2Id = $mention2->getId();
        $this->assertNotNull($mention1Id);
        $this->assertNotNull($mention2Id);
        $updatedCount = $this->repository->markAsNotified([$mention1Id, $mention2Id]);

        $this->assertEquals(2, $updatedCount);
        self::getEntityManager()->refresh($mention1);
        self::getEntityManager()->refresh($mention2);
        $this->assertTrue($mention1->isNotified());
        $this->assertTrue($mention2->isNotified());
        $this->assertNotNull($mention1->getNotifyTime());
        $this->assertNotNull($mention2->getNotifyTime());
    }

    public function testMarkAsNotifiedShouldReturnZeroForEmptyArray(): void
    {
        $updatedCount = $this->repository->markAsNotified([]);

        $this->assertEquals(0, $updatedCount);
    }

    public function testRemoveMentionsByCommentShouldDeleteAllMentionsForComment(): void
    {
        $comment1 = $this->createTestComment();
        $comment2 = $this->createTestComment();
        $mention1 = $this->createTestMention($comment1, 'user1');
        $mention2 = $this->createTestMention($comment1, 'user2');
        $mention3 = $this->createTestMention($comment2, 'user3');
        $this->persistAndFlush($mention1);
        $this->persistAndFlush($mention2);
        $this->persistAndFlush($mention3);

        $deletedCount = $this->repository->removeMentionsByComment($comment1);

        $this->assertEquals(2, $deletedCount);
        $this->assertCount(0, $this->repository->findBy(['comment' => $comment1]));
        $this->assertCount(1, $this->repository->findBy(['comment' => $comment2]));
    }

    public function testFindDuplicateMentionShouldReturnExistingMention(): void
    {
        $comment = $this->createTestComment();
        $mention = $this->createTestMention($comment, 'user123');
        $this->persistAndFlush($mention);

        $foundMention = $this->repository->findDuplicateMention($comment, 'user123');

        $this->assertInstanceOf(CommentMention::class, $foundMention);
        $this->assertEquals($mention->getId(), $foundMention->getId());
    }

    public function testFindDuplicateMentionShouldReturnNullWhenNoMatch(): void
    {
        $comment = $this->createTestComment();
        $mention = $this->createTestMention($comment, 'user123');
        $this->persistAndFlush($mention);

        $foundMention = $this->repository->findDuplicateMention($comment, 'user456');

        $this->assertNull($foundMention);
    }

    private function createTestComment(): Comment
    {
        $comment = new Comment();
        $comment->setTargetType('article');
        $comment->setTargetId('123');
        $comment->setContent('Test comment');
        $comment->setStatus(CommentStatus::APPROVED);
        $comment->setAuthorId('author123');

        $this->persistAndFlush($comment);

        return $comment;
    }

    private function createTestMention(Comment $comment, string $userId): CommentMention
    {
        $mention = new CommentMention();
        $mention->setComment($comment);
        $mention->setMentionedUserId($userId);

        return $mention;
    }

    public function testFindOneByAssociationCommentShouldReturnMatchingEntity(): void
    {
        $comment = $this->createTestComment();
        $mention = $this->createTestMention($comment, 'user123');
        $this->persistAndFlush($mention);

        $foundMention = $this->repository->findOneBy(['comment' => $comment]);

        $this->assertInstanceOf(CommentMention::class, $foundMention);
        $this->assertEquals($comment->getId(), $foundMention->getComment()->getId());
        $this->assertEquals('user123', $foundMention->getMentionedUserId());
    }

    public function testFindByCommentShouldReturnMentionsOrderedByCreateTime(): void
    {
        $comment1 = $this->createTestComment();
        $comment2 = $this->createTestComment();
        $mention1 = $this->createTestMention($comment1, 'user1');
        $mention2 = $this->createTestMention($comment1, 'user2');
        $mention3 = $this->createTestMention($comment2, 'user3');

        $mention1->setCreateTime(new \DateTimeImmutable('2023-01-01'));
        $mention2->setCreateTime(new \DateTimeImmutable('2023-01-02'));

        $this->persistAndFlush($mention1);
        $this->persistAndFlush($mention2);
        $this->persistAndFlush($mention3);

        $mentions = $this->repository->findByComment($comment1);

        $this->assertCount(2, $mentions);
        $this->assertEquals('user1', $mentions[0]->getMentionedUserId());
        $this->assertEquals('user2', $mentions[1]->getMentionedUserId());

        foreach ($mentions as $mention) {
            $this->assertEquals($comment1->getId(), $mention->getComment()->getId());
        }
    }

    /**
     * @return ServiceEntityRepository<CommentMention>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }

    protected function createNewEntity(): object
    {
        $mention = new CommentMention();
        $mention->setMentionedUserId('user123');

        // 创建一个基本的 Comment 实体来满足外键约束
        $comment = new Comment();
        $comment->setTargetType('test_type');
        $comment->setTargetId('test_id');
        $comment->setAuthorId('author_id');
        $comment->setContent('Test comment for mention');
        self::getEntityManager()->persist($comment);
        self::getEntityManager()->flush();

        $mention->setComment($comment);

        return $mention;
    }
}
