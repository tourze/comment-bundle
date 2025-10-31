<?php

namespace Tourze\CommentBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentVote;
use Tourze\CommentBundle\Enum\CommentStatus;
use Tourze\CommentBundle\Enum\VoteType;
use Tourze\CommentBundle\Event\CommentVotedEvent;
use Tourze\CommentBundle\Repository\CommentVoteRepository;
use Tourze\CommentBundle\Service\CommentVoteService;

/**
 * @internal
 */
#[CoversClass(CommentVoteService::class)]
final class CommentVoteServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManagerMock;

    private CommentVoteRepository&MockObject $voteRepository;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private CommentVoteService $voteService;

    public function testVoteCreatesNewVote(): void
    {
        $comment = new Comment();
        $comment->setStatus(CommentStatus::APPROVED);

        $voteType = VoteType::LIKE;
        $voterId = 'user123';
        $voterIp = '127.0.0.1';

        $this->voteRepository->expects($this->once())
            ->method('findByCommentAndVoter')
            ->with($comment, $voterId, $voterIp)
            ->willReturn(null)
        ;

        $this->entityManagerMock->expects($this->once())
            ->method('persist')
            ->with(self::isInstanceOf(CommentVote::class))
        ;

        $this->entityManagerMock->expects($this->once())
            ->method('flush')
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CommentVotedEvent::class))
        ;

        $result = $this->voteService->vote($comment, $voteType, $voterId, $voterIp);

        $this->assertTrue($result);
        $this->assertEquals(1, $comment->getLikesCount());
    }

    public function testVoteRemovesExistingVoteOfSameType(): void
    {
        $comment = new Comment();
        $comment->setStatus(CommentStatus::APPROVED);
        $comment->setLikesCount(1);

        $voteType = VoteType::LIKE;
        $voterId = 'user123';
        $voterIp = '127.0.0.1';

        $existingVote = new CommentVote();
        $existingVote->setVoteType(VoteType::LIKE);
        $existingVote->setComment($comment);

        $this->voteRepository->expects($this->exactly(2))
            ->method('findByCommentAndVoter')
            ->with($comment, $voterId, $voterIp)
            ->willReturn($existingVote)
        ;

        $this->entityManagerMock->expects($this->once())
            ->method('remove')
            ->with($existingVote)
        ;

        $this->entityManagerMock->expects($this->once())
            ->method('flush')
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CommentVotedEvent::class))
        ;

        $result = $this->voteService->vote($comment, $voteType, $voterId, $voterIp);

        $this->assertTrue($result);
        $this->assertEquals(0, $comment->getLikesCount());
    }

    public function testVoteUpdatesExistingVoteOfDifferentType(): void
    {
        $comment = new Comment();
        $comment->setStatus(CommentStatus::APPROVED);
        $comment->setLikesCount(1);
        $comment->setDislikesCount(0);

        $voteType = VoteType::DISLIKE;
        $voterId = 'user123';
        $voterIp = '127.0.0.1';

        $existingVote = new CommentVote();
        $existingVote->setVoteType(VoteType::LIKE);
        $existingVote->setComment($comment);
        $existingVote->setVoterId($voterId);

        $this->voteRepository->expects($this->once())
            ->method('findByCommentAndVoter')
            ->with($comment, $voterId, $voterIp)
            ->willReturn($existingVote)
        ;

        $this->entityManagerMock->expects($this->once())
            ->method('flush')
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CommentVotedEvent::class))
        ;

        $result = $this->voteService->vote($comment, $voteType, $voterId, $voterIp);

        $this->assertTrue($result);
        $this->assertEquals(0, $comment->getLikesCount());
        $this->assertEquals(1, $comment->getDislikesCount());
        $this->assertEquals(VoteType::DISLIKE, $existingVote->getVoteType());
    }

    public function testVoteThrowsExceptionForInvalidVoteType(): void
    {
        $comment = new Comment();
        $this->expectException(\TypeError::class);

        /* @phpstan-ignore-next-line */
        $this->voteService->vote($comment, 'invalid_type', 'user123');
    }

    public function testRemoveVoteRemovesExistingVote(): void
    {
        $comment = new Comment();
        $comment->setLikesCount(1);

        $voterId = 'user123';
        $voterIp = '127.0.0.1';

        $vote = new CommentVote();
        $vote->setVoteType(VoteType::LIKE);
        $vote->setComment($comment);

        $this->voteRepository->expects($this->once())
            ->method('findByCommentAndVoter')
            ->with($comment, $voterId, $voterIp)
            ->willReturn($vote)
        ;

        $this->entityManagerMock->expects($this->once())
            ->method('remove')
            ->with($vote)
        ;

        $this->entityManagerMock->expects($this->once())
            ->method('flush')
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CommentVotedEvent::class))
        ;

        $result = $this->voteService->removeVote($comment, $voterId, $voterIp);

        $this->assertTrue($result);
        $this->assertEquals(0, $comment->getLikesCount());
    }

    public function testRemoveVoteReturnsFalseWhenNoVoteExists(): void
    {
        $comment = new Comment();
        $voterId = 'user123';
        $voterIp = '127.0.0.1';

        $this->voteRepository->expects($this->once())
            ->method('findByCommentAndVoter')
            ->with($comment, $voterId, $voterIp)
            ->willReturn(null)
        ;

        $this->entityManagerMock->expects($this->never())
            ->method('remove')
        ;

        $result = $this->voteService->removeVote($comment, $voterId, $voterIp);

        $this->assertFalse($result);
    }

    public function testHasVotedCallsRepository(): void
    {
        $comment = new Comment();
        $voterId = 'user123';
        $voterIp = '127.0.0.1';

        $this->voteRepository->expects($this->once())
            ->method('hasVoted')
            ->with($comment, $voterId, $voterIp)
            ->willReturn(true)
        ;

        $result = $this->voteService->hasVoted($comment, $voterId, $voterIp);

        $this->assertTrue($result);
    }

    public function testGetVoteTypeCallsRepository(): void
    {
        $comment = new Comment();
        $voterId = 'user123';
        $voterIp = '127.0.0.1';
        $expectedVoteType = VoteType::LIKE;

        $this->voteRepository->expects($this->once())
            ->method('getVoteType')
            ->with($comment, $voterId, $voterIp)
            ->willReturn('like')
        ;

        $result = $this->voteService->getVoteType($comment, $voterId, $voterIp);

        $this->assertEquals($expectedVoteType, $result);
    }

    public function testGetVoteStatisticsCallsRepository(): void
    {
        $comment = new Comment();
        $expectedStats = ['likes' => 10, 'dislikes' => 3];

        $this->voteRepository->expects($this->once())
            ->method('getVoteStatistics')
            ->with($comment)
            ->willReturn($expectedStats)
        ;

        $result = $this->voteService->getVoteStatistics($comment);

        $this->assertEquals($expectedStats, $result);
    }

    public function testGetVotesByVoterCallsRepository(): void
    {
        $voterId = 'user123';
        $voterIp = '127.0.0.1';
        $options = ['limit' => 10];
        $expectedVotes = [new CommentVote(), new CommentVote()];

        $this->voteRepository->expects($this->once())
            ->method('findVotesByVoter')
            ->with($voterId, $voterIp, $options)
            ->willReturn($expectedVotes)
        ;

        $result = $this->voteService->getVotesByVoter($voterId, $voterIp, $options);

        $this->assertEquals($expectedVotes, $result);
    }

    public function testCanVoteWithApprovedComment(): void
    {
        $comment = new Comment();
        $comment->setStatus(CommentStatus::APPROVED);

        $this->assertTrue($this->voteService->canVote($comment, 'user123', '127.0.0.1'));
    }

    public function testCanVoteWithPendingComment(): void
    {
        $comment = new Comment();
        $comment->setStatus(CommentStatus::PENDING);

        $this->assertFalse($this->voteService->canVote($comment, 'user123', '127.0.0.1'));
    }

    public function testCanVoteWithDeletedComment(): void
    {
        $comment = new Comment();
        $comment->setStatus(CommentStatus::APPROVED);
        $comment->setDeleteTime(new \DateTimeImmutable());

        $this->assertFalse($this->voteService->canVote($comment, 'user123', '127.0.0.1'));
    }

    public function testCanVoteWithNoVoterIdentification(): void
    {
        $comment = new Comment();
        $comment->setStatus(CommentStatus::APPROVED);

        $this->assertFalse($this->voteService->canVote($comment, null, null));
    }

    public function testRefreshCommentVoteCountsUpdatesComment(): void
    {
        $comment = new Comment();
        $comment->setLikesCount(5);
        $comment->setDislikesCount(2);

        $actualStats = ['likes' => 8, 'dislikes' => 3];

        $this->voteRepository->expects($this->once())
            ->method('getVoteStatistics')
            ->with($comment)
            ->willReturn($actualStats)
        ;

        $this->entityManagerMock->expects($this->once())
            ->method('flush')
        ;

        $result = $this->voteService->refreshCommentVoteCounts($comment);

        $this->assertEquals($actualStats['likes'], $result->getLikesCount());
        $this->assertEquals($actualStats['dislikes'], $result->getDislikesCount());
    }

    public function testUpdateCommentVoteCountPreventsNegativeCounts(): void
    {
        $comment = new Comment();
        $comment->setLikesCount(0);
        $comment->setDislikesCount(0);

        // 通过创建一个投票然后删除来测试负值保护
        $this->voteRepository->method('findByCommentAndVoter')->willReturn(null);
        $this->entityManagerMock->method('persist');
        $this->entityManagerMock->method('flush');

        // 创建点赞
        $this->voteService->vote($comment, VoteType::LIKE, 'user123');
        $this->assertEquals(1, $comment->getLikesCount());

        // 模拟一个已存在的点赞投票被删除
        $existingVote = new CommentVote();
        $existingVote->setVoteType(VoteType::LIKE);
        $existingVote->setComment($comment);

        $this->voteRepository->method('findByCommentAndVoter')->willReturn($existingVote);
        $comment->setLikesCount(0); // 重置为0

        $this->voteService->removeVote($comment, 'user123');

        // 确保计数不会变为负数
        $this->assertGreaterThanOrEqual(0, $comment->getLikesCount());
        $this->assertGreaterThanOrEqual(0, $comment->getDislikesCount());
    }

    protected function setUp(): void
    {
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        /*
         * 使用具体类 CommentVoteRepository 而不是接口的原因：
         * 1. CommentVoteRepository 继承自 Doctrine 的 ServiceEntityRepository
         * 2. 包含特定的投票查询方法，没有标准的仓储接口
         * 3. 在测试中需要模拟具体的查询方法实现
         */
        $this->voteRepository = $this->createMock(CommentVoteRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->voteService = new CommentVoteService(
            $this->entityManagerMock,
            $this->voteRepository,
            $this->eventDispatcher
        );
    }
}
