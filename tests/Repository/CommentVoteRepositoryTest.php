<?php

namespace Tourze\CommentBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentVote;
use Tourze\CommentBundle\Enum\CommentStatus;
use Tourze\CommentBundle\Enum\VoteType;
use Tourze\CommentBundle\Repository\CommentVoteRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(CommentVoteRepository::class)]
#[RunTestsInSeparateProcesses]
final class CommentVoteRepositoryTest extends AbstractRepositoryTestCase
{
    private CommentVoteRepository $repository;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(CommentVoteRepository::class);
    }

    public function testSaveVoteSuccessfully(): void
    {
        $comment = $this->createTestComment();
        $vote = $this->createTestVote($comment, VoteType::LIKE, 'user123');

        $this->repository->save($vote);

        $this->assertNotNull($vote->getId());

        $foundVote = $this->repository->find($vote->getId());
        $this->assertInstanceOf(CommentVote::class, $foundVote);
        $this->assertEquals(VoteType::LIKE, $foundVote->getVoteType());
        $this->assertEquals('user123', $foundVote->getVoterId());
    }

    public function testRemoveVoteSuccessfully(): void
    {
        $comment = $this->createTestComment();
        $vote = $this->createTestVote($comment, VoteType::LIKE, 'user123');
        $this->repository->save($vote);

        $voteId = $vote->getId();
        $this->assertNotNull($voteId);

        $this->repository->remove($vote);

        $foundVote = $this->repository->find($voteId);
        $this->assertNull($foundVote);
    }

    public function testFindByCommentAndVoterWithUserId(): void
    {
        $comment = $this->createTestComment();
        $vote = $this->createTestVote($comment, VoteType::LIKE, 'user123');
        $this->repository->save($vote);

        $foundVote = $this->repository->findByCommentAndVoter($comment, 'user123');

        $this->assertInstanceOf(CommentVote::class, $foundVote);
        $this->assertEquals('user123', $foundVote->getVoterId());
        $this->assertEquals(VoteType::LIKE, $foundVote->getVoteType());
    }

    public function testFindByCommentAndVoterWithIpAddress(): void
    {
        $comment = $this->createTestComment();
        $vote = $this->createTestVote($comment, VoteType::DISLIKE, null, '192.168.1.1');
        $this->repository->save($vote);

        $foundVote = $this->repository->findByCommentAndVoter($comment, null, '192.168.1.1');

        $this->assertInstanceOf(CommentVote::class, $foundVote);
        $this->assertNull($foundVote->getVoterId());
        $this->assertEquals('192.168.1.1', $foundVote->getVoterIp());
        $this->assertEquals(VoteType::DISLIKE, $foundVote->getVoteType());
    }

    public function testFindByCommentAndVoterReturnsNullWhenNotFound(): void
    {
        $comment = $this->createTestComment();

        $foundVote = $this->repository->findByCommentAndVoter($comment, 'nonexistent');

        $this->assertNull($foundVote);
    }

    public function testCountVotesByTypeReturnsCorrectCount(): void
    {
        $comment = $this->createTestComment();

        $vote1 = $this->createTestVote($comment, VoteType::LIKE, 'user1');
        $vote2 = $this->createTestVote($comment, VoteType::LIKE, 'user2');
        $vote3 = $this->createTestVote($comment, VoteType::DISLIKE, 'user3');
        $this->repository->save($vote1);
        $this->repository->save($vote2);
        $this->repository->save($vote3);

        $likeCount = $this->repository->countVotesByType($comment, VoteType::LIKE->value);
        $dislikeCount = $this->repository->countVotesByType($comment, VoteType::DISLIKE->value);

        $this->assertEquals(2, $likeCount);
        $this->assertEquals(1, $dislikeCount);
    }

    public function testGetVoteStatisticsReturnsCorrectStatistics(): void
    {
        $comment = $this->createTestComment();

        $vote1 = $this->createTestVote($comment, VoteType::LIKE, 'user1');
        $vote2 = $this->createTestVote($comment, VoteType::LIKE, 'user2');
        $vote3 = $this->createTestVote($comment, VoteType::LIKE, 'user3');
        $vote4 = $this->createTestVote($comment, VoteType::DISLIKE, 'user4');
        $this->repository->save($vote1);
        $this->repository->save($vote2);
        $this->repository->save($vote3);
        $this->repository->save($vote4);

        $stats = $this->repository->getVoteStatistics($comment);

        $this->assertIsArray($stats);
        $this->assertEquals(3, $stats['likes']);
        $this->assertEquals(1, $stats['dislikes']);
        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(2, $stats['score']); // 3 - 1 = 2
    }

    public function testFindVotesByVoterWithUserId(): void
    {
        $comment1 = $this->createTestComment();
        $comment2 = $this->createTestComment();

        $vote1 = $this->createTestVote($comment1, VoteType::LIKE, 'user123');
        $vote2 = $this->createTestVote($comment2, VoteType::DISLIKE, 'user123');
        $vote3 = $this->createTestVote($comment1, VoteType::LIKE, 'user456'); // Different user
        $this->repository->save($vote1);
        $this->repository->save($vote2);
        $this->repository->save($vote3);

        $votes = $this->repository->findVotesByVoter('user123');

        $this->assertCount(2, $votes);
        foreach ($votes as $vote) {
            $this->assertEquals('user123', $vote->getVoterId());
        }
    }

    public function testFindVotesByVoterWithIpAddress(): void
    {
        $comment = $this->createTestComment();

        $vote = $this->createTestVote($comment, VoteType::LIKE, null, '192.168.1.1');
        $this->repository->save($vote);

        $votes = $this->repository->findVotesByVoter(null, '192.168.1.1');

        $this->assertCount(1, $votes);
        $this->assertEquals('192.168.1.1', $votes[0]->getVoterIp());
        $this->assertNull($votes[0]->getVoterId());
    }

    public function testFindVotesByVoterWithOptions(): void
    {
        $comment = $this->createTestComment();

        $vote1 = $this->createTestVote($comment, VoteType::LIKE, 'user123');
        $vote2 = $this->createTestVote($comment, VoteType::DISLIKE, 'user123');
        $this->repository->save($vote1);
        $this->repository->save($vote2);

        $likeVotes = $this->repository->findVotesByVoter('user123', null, ['vote_type' => VoteType::LIKE->value]);
        $limitedVotes = $this->repository->findVotesByVoter('user123', null, ['limit' => 1]);

        $this->assertCount(1, $likeVotes);
        $this->assertEquals(VoteType::LIKE, $likeVotes[0]->getVoteType());

        $this->assertCount(1, $limitedVotes);
    }

    public function testHasVotedReturnsTrueWhenVoteExists(): void
    {
        $comment = $this->createTestComment();
        $vote = $this->createTestVote($comment, VoteType::LIKE, 'user123');
        $this->repository->save($vote);

        $hasVoted = $this->repository->hasVoted($comment, 'user123');

        $this->assertTrue($hasVoted);
    }

    public function testHasVotedReturnsFalseWhenVoteDoesNotExist(): void
    {
        $comment = $this->createTestComment();

        $hasVoted = $this->repository->hasVoted($comment, 'user123');

        $this->assertFalse($hasVoted);
    }

    public function testGetVoteTypeReturnsCorrectType(): void
    {
        $comment = $this->createTestComment();
        $vote = $this->createTestVote($comment, VoteType::DISLIKE, 'user123');
        $this->repository->save($vote);

        $voteType = $this->repository->getVoteType($comment, 'user123');

        $this->assertEquals(VoteType::DISLIKE->value, $voteType);
    }

    public function testGetVoteTypeReturnsNullWhenVoteDoesNotExist(): void
    {
        $comment = $this->createTestComment();

        $voteType = $this->repository->getVoteType($comment, 'user123');

        $this->assertNull($voteType);
    }

    public function testRemoveVotesByCommentRemovesAllVotes(): void
    {
        $comment = $this->createTestComment();

        $vote1 = $this->createTestVote($comment, VoteType::LIKE, 'user1');
        $vote2 = $this->createTestVote($comment, VoteType::DISLIKE, 'user2');
        $vote3 = $this->createTestVote($comment, VoteType::LIKE, 'user3');
        $this->repository->save($vote1);
        $this->repository->save($vote2);
        $this->repository->save($vote3);

        $removedCount = $this->repository->removeVotesByComment($comment);

        $this->assertEquals(3, $removedCount);

        $remainingVotes = $this->repository->findBy(['comment' => $comment]);
        $this->assertCount(0, $remainingVotes);
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

    private function createTestVote(
        Comment $comment,
        VoteType $voteType,
        ?string $voterId = null,
        ?string $voterIp = null,
    ): CommentVote {
        $vote = new CommentVote();
        $vote->setComment($comment);
        $vote->setVoteType($voteType);

        if (null !== $voterId) {
            $vote->setVoterId($voterId);
        }

        if (null !== $voterIp) {
            $vote->setVoterIp($voterIp);
        }

        return $vote;
    }

    public function testFindOneByShouldRespectOrderByClause(): void
    {
        $comment = $this->createTestComment();
        $vote1 = $this->createTestVote($comment, VoteType::LIKE, 'user1');
        $vote2 = $this->createTestVote($comment, VoteType::DISLIKE, 'user2');
        $vote1->setCreateTime(new \DateTimeImmutable('2023-01-01'));
        $vote2->setCreateTime(new \DateTimeImmutable('2023-01-02'));
        $this->repository->save($vote1);
        $this->repository->save($vote2);

        $foundVote = $this->repository->findOneBy(
            ['comment' => $comment],
            ['createTime' => 'DESC']
        );

        $this->assertInstanceOf(CommentVote::class, $foundVote);
        $this->assertEquals(VoteType::DISLIKE, $foundVote->getVoteType());
    }

    public function testFindByWithNullableFieldShouldReturnEntities(): void
    {
        $comment = $this->createTestComment();
        $vote1 = $this->createTestVote($comment, VoteType::LIKE, 'user1');
        $vote2 = $this->createTestVote($comment, VoteType::DISLIKE, null, '192.168.1.1');
        $this->repository->save($vote1);
        $this->repository->save($vote2);

        $votesWithVoterId = $this->repository->findBy(['voterId' => 'user1']);
        $votesWithNullVoterId = $this->repository->findBy(['voterId' => null]);

        $this->assertCount(1, $votesWithVoterId);
        $this->assertEquals('user1', $votesWithVoterId[0]->getVoterId());
        $this->assertIsArray($votesWithNullVoterId);
    }

    public function testCountByNullableFieldShouldReturnCorrectCount(): void
    {
        $comment = $this->createTestComment();
        $vote1 = $this->createTestVote($comment, VoteType::LIKE, 'user1');
        $vote2 = $this->createTestVote($comment, VoteType::LIKE, 'user2');
        $vote3 = $this->createTestVote($comment, VoteType::DISLIKE, null, '192.168.1.1');
        $this->repository->save($vote1);
        $this->repository->save($vote2);
        $this->repository->save($vote3);

        $nullCount = $this->repository->count(['voterId' => null]);
        $notNullCount = $this->repository->count(['voterId' => 'user1']);

        $this->assertGreaterThanOrEqual(1, $nullCount);
        $this->assertEquals(1, $notNullCount);
    }

    public function testFindByCommentAssociationShouldReturnVotes(): void
    {
        $comment = $this->createTestComment();
        $vote1 = $this->createTestVote($comment, VoteType::LIKE, 'user1');
        $vote2 = $this->createTestVote($comment, VoteType::DISLIKE, 'user2');
        $this->repository->save($vote1);
        $this->repository->save($vote2);

        $votes = $this->repository->findBy(['comment' => $comment]);

        $this->assertCount(2, $votes);
        foreach ($votes as $vote) {
            $this->assertEquals($comment->getId(), $vote->getComment()->getId());
        }
    }

    public function testCountByCommentAssociationShouldReturnCorrectCount(): void
    {
        $comment = $this->createTestComment();
        $vote1 = $this->createTestVote($comment, VoteType::LIKE, 'user1');
        $vote2 = $this->createTestVote($comment, VoteType::DISLIKE, 'user2');
        $this->repository->save($vote1);
        $this->repository->save($vote2);

        $count = $this->repository->count(['comment' => $comment]);

        $this->assertEquals(2, $count);
    }

    public function testCountVotesByTypeAssociationShouldReturnCorrectCount(): void
    {
        $comment = $this->createTestComment();
        $vote1 = $this->createTestVote($comment, VoteType::LIKE, 'user1');
        $vote2 = $this->createTestVote($comment, VoteType::LIKE, 'user2');
        $vote3 = $this->createTestVote($comment, VoteType::DISLIKE, 'user3');
        $this->repository->save($vote1);
        $this->repository->save($vote2);
        $this->repository->save($vote3);

        $likeCount = $this->repository->count(['comment' => $comment, 'voteType' => VoteType::LIKE->value]);
        $dislikeCount = $this->repository->count(['comment' => $comment, 'voteType' => VoteType::DISLIKE->value]);

        $this->assertEquals(2, $likeCount);
        $this->assertEquals(1, $dislikeCount);
    }

    public function testFindByMultipleConditionsShouldReturnCorrectVotes(): void
    {
        $comment = $this->createTestComment();
        $vote1 = $this->createTestVote($comment, VoteType::LIKE, 'user1');
        $vote2 = $this->createTestVote($comment, VoteType::LIKE, 'user2');
        $vote3 = $this->createTestVote($comment, VoteType::DISLIKE, 'user1');
        $this->repository->save($vote1);
        $this->repository->save($vote2);
        $this->repository->save($vote3);

        $likesByUser1 = $this->repository->findBy([
            'comment' => $comment,
            'voteType' => VoteType::LIKE->value,
            'voterId' => 'user1',
        ]);

        $this->assertCount(1, $likesByUser1);
        $this->assertEquals(VoteType::LIKE, $likesByUser1[0]->getVoteType());
        $this->assertEquals('user1', $likesByUser1[0]->getVoterId());
    }

    public function testFindByWithIsNullShouldReturnEntitiesForAllNullableFields(): void
    {
        $comment = $this->createTestComment();
        $vote1 = $this->createTestVote($comment, VoteType::LIKE, 'user1');
        $vote1->setVoterIp('192.168.1.1');
        $vote2 = $this->createTestVote($comment, VoteType::DISLIKE, null, '192.168.1.2');
        $vote3 = $this->createTestVote($comment, VoteType::LIKE, 'user3');
        $this->repository->save($vote1);
        $this->repository->save($vote2);
        $this->repository->save($vote3);

        $nullUserIdVotes = $this->repository->findBy(['voterId' => null]);
        $nullIpVotes = $this->repository->findBy(['voterIp' => null]);

        $this->assertIsArray($nullUserIdVotes);
        $this->assertIsArray($nullIpVotes);
        $this->assertGreaterThanOrEqual(1, count($nullUserIdVotes));
        $this->assertGreaterThanOrEqual(1, count($nullIpVotes));
    }

    public function testCountWithIsNullShouldReturnCorrectCountForAllNullableFields(): void
    {
        $comment = $this->createTestComment();
        $vote1 = $this->createTestVote($comment, VoteType::LIKE, 'user1');
        $vote1->setVoterIp('192.168.1.1');
        $vote2 = $this->createTestVote($comment, VoteType::DISLIKE, null, '192.168.1.2');
        $vote3 = $this->createTestVote($comment, VoteType::LIKE, 'user3');
        $this->repository->save($vote1);
        $this->repository->save($vote2);
        $this->repository->save($vote3);

        $nullUserIdCount = $this->repository->count(['voterId' => null]);
        $nullIpCount = $this->repository->count(['voterIp' => null]);

        $this->assertGreaterThanOrEqual(1, $nullUserIdCount);
        $this->assertGreaterThanOrEqual(1, $nullIpCount);
    }

    public function testCountByAssociationCommentShouldReturnCorrectNumber(): void
    {
        $comment1 = $this->createTestComment();
        $comment2 = $this->createTestComment();

        $vote1 = $this->createTestVote($comment1, VoteType::LIKE, 'user1');
        $vote2 = $this->createTestVote($comment1, VoteType::DISLIKE, 'user2');
        $vote3 = $this->createTestVote($comment1, VoteType::LIKE, 'user3');
        $vote4 = $this->createTestVote($comment2, VoteType::LIKE, 'user4');
        $this->repository->save($vote1);
        $this->repository->save($vote2);
        $this->repository->save($vote3);
        $this->repository->save($vote4);

        $count = $this->repository->count(['comment' => $comment1]);
        $this->assertSame(3, $count);
    }

    public function testFindOneByAssociationCommentShouldReturnMatchingEntity(): void
    {
        $comment = $this->createTestComment();
        $vote = $this->createTestVote($comment, VoteType::LIKE, 'user123');
        $this->repository->save($vote);

        $foundVote = $this->repository->findOneBy(['comment' => $comment]);

        $this->assertInstanceOf(CommentVote::class, $foundVote);
        $this->assertEquals($comment->getId(), $foundVote->getComment()->getId());
    }

    /**
     * @return ServiceEntityRepository<CommentVote>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }

    protected function createNewEntity(): object
    {
        $vote = new CommentVote();
        $vote->setVoteType(VoteType::LIKE);
        $vote->setVoterId('user123');

        // 创建一个基本的 Comment 实体来满足外键约束
        $comment = new Comment();
        $comment->setTargetType('test_type');
        $comment->setTargetId('test_id');
        $comment->setAuthorId('author_id');
        $comment->setContent('Test comment for vote');
        self::getEntityManager()->persist($comment);
        self::getEntityManager()->flush();

        $vote->setComment($comment);

        return $vote;
    }
}
