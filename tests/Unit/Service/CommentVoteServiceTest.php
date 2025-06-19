<?php

namespace Tourze\CommentBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
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

class CommentVoteServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private CommentVoteRepository&MockObject $voteRepository;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private CommentVoteService $voteService;

    public function test_vote_createsNewVote(): void
    {
        $comment = new Comment();
        $comment->setStatus(CommentStatus::APPROVED);

        $voteType = VoteType::LIKE;
        $voterId = 'user123';
        $voterIp = '127.0.0.1';

        $this->voteRepository->expects($this->once())
            ->method('findByCommentAndVoter')
            ->with($comment, $voterId, $voterIp)
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(CommentVote::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(CommentVotedEvent::class),
                CommentVotedEvent::NAME
            );

        $result = $this->voteService->vote($comment, $voteType, $voterId, $voterIp);

        $this->assertTrue($result);
        $this->assertEquals(1, $comment->getLikesCount());
    }

    public function test_vote_removesExistingVoteOfSameType(): void
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
            ->willReturn($existingVote);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($existingVote);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(CommentVotedEvent::class),
                CommentVotedEvent::NAME
            );

        $result = $this->voteService->vote($comment, $voteType, $voterId, $voterIp);

        $this->assertTrue($result);
        $this->assertEquals(0, $comment->getLikesCount());
    }

    public function test_vote_updatesExistingVoteOfDifferentType(): void
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
            ->willReturn($existingVote);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(CommentVotedEvent::class),
                CommentVotedEvent::NAME
            );

        $result = $this->voteService->vote($comment, $voteType, $voterId, $voterIp);

        $this->assertTrue($result);
        $this->assertEquals(0, $comment->getLikesCount());
        $this->assertEquals(1, $comment->getDislikesCount());
        $this->assertEquals(VoteType::DISLIKE, $existingVote->getVoteType());
    }

    public function test_vote_throwsExceptionForInvalidVoteType(): void
    {
        $comment = new Comment();
        $this->expectException(\TypeError::class);

        $this->voteService->vote($comment, 'invalid_type', 'user123');
    }

    public function test_removeVote_removesExistingVote(): void
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
            ->willReturn($vote);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($vote);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(CommentVotedEvent::class),
                CommentVotedEvent::NAME
            );

        $result = $this->voteService->removeVote($comment, $voterId, $voterIp);

        $this->assertTrue($result);
        $this->assertEquals(0, $comment->getLikesCount());
    }

    public function test_removeVote_returnsFalseWhenNoVoteExists(): void
    {
        $comment = new Comment();
        $voterId = 'user123';
        $voterIp = '127.0.0.1';

        $this->voteRepository->expects($this->once())
            ->method('findByCommentAndVoter')
            ->with($comment, $voterId, $voterIp)
            ->willReturn(null);

        $this->entityManager->expects($this->never())
            ->method('remove');

        $result = $this->voteService->removeVote($comment, $voterId, $voterIp);

        $this->assertFalse($result);
    }

    public function test_hasVoted_callsRepository(): void
    {
        $comment = new Comment();
        $voterId = 'user123';
        $voterIp = '127.0.0.1';

        $this->voteRepository->expects($this->once())
            ->method('hasVoted')
            ->with($comment, $voterId, $voterIp)
            ->willReturn(true);

        $result = $this->voteService->hasVoted($comment, $voterId, $voterIp);

        $this->assertTrue($result);
    }

    public function test_getVoteType_callsRepository(): void
    {
        $comment = new Comment();
        $voterId = 'user123';
        $voterIp = '127.0.0.1';
        $expectedVoteType = VoteType::LIKE;

        $this->voteRepository->expects($this->once())
            ->method('getVoteType')
            ->with($comment, $voterId, $voterIp)
            ->willReturn('like');

        $result = $this->voteService->getVoteType($comment, $voterId, $voterIp);

        $this->assertEquals($expectedVoteType, $result);
    }

    public function test_getVoteStatistics_callsRepository(): void
    {
        $comment = new Comment();
        $expectedStats = ['likes' => 10, 'dislikes' => 3];

        $this->voteRepository->expects($this->once())
            ->method('getVoteStatistics')
            ->with($comment)
            ->willReturn($expectedStats);

        $result = $this->voteService->getVoteStatistics($comment);

        $this->assertEquals($expectedStats, $result);
    }

    public function test_getVotesByVoter_callsRepository(): void
    {
        $voterId = 'user123';
        $voterIp = '127.0.0.1';
        $options = ['limit' => 10];
        $expectedVotes = [new CommentVote(), new CommentVote()];

        $this->voteRepository->expects($this->once())
            ->method('findVotesByVoter')
            ->with($voterId, $voterIp, $options)
            ->willReturn($expectedVotes);

        $result = $this->voteService->getVotesByVoter($voterId, $voterIp, $options);

        $this->assertEquals($expectedVotes, $result);
    }

    public function test_canVote_withApprovedComment(): void
    {
        $comment = new Comment();
        $comment->setStatus(CommentStatus::APPROVED);

        $this->assertTrue($this->voteService->canVote($comment, 'user123', '127.0.0.1'));
    }

    public function test_canVote_withPendingComment(): void
    {
        $comment = new Comment();
        $comment->setStatus(CommentStatus::PENDING);

        $this->assertFalse($this->voteService->canVote($comment, 'user123', '127.0.0.1'));
    }

    public function test_canVote_withDeletedComment(): void
    {
        $comment = new Comment();
        $comment->setStatus(CommentStatus::APPROVED);
        $comment->setDeleteTime(new \DateTimeImmutable());

        $this->assertFalse($this->voteService->canVote($comment, 'user123', '127.0.0.1'));
    }

    public function test_canVote_withNoVoterIdentification(): void
    {
        $comment = new Comment();
        $comment->setStatus(CommentStatus::APPROVED);

        $this->assertFalse($this->voteService->canVote($comment, null, null));
    }

    public function test_refreshCommentVoteCounts_updatesComment(): void
    {
        $comment = new Comment();
        $comment->setLikesCount(5);
        $comment->setDislikesCount(2);

        $actualStats = ['likes' => 8, 'dislikes' => 3];

        $this->voteRepository->expects($this->once())
            ->method('getVoteStatistics')
            ->with($comment)
            ->willReturn($actualStats);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->voteService->refreshCommentVoteCounts($comment);

        $this->assertEquals($actualStats['likes'], $result->getLikesCount());
        $this->assertEquals($actualStats['dislikes'], $result->getDislikesCount());
    }

    public function test_updateCommentVoteCount_preventsNegativeCounts(): void
    {
        $comment = new Comment();
        $comment->setLikesCount(0);
        $comment->setDislikesCount(0);

        // 通过创建一个投票然后删除来测试负值保护
        $this->voteRepository->method('findByCommentAndVoter')->willReturn(null);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

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
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->voteRepository = $this->createMock(CommentVoteRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->voteService = new CommentVoteService(
            $this->entityManager,
            $this->voteRepository,
            $this->eventDispatcher
        );
    }
}