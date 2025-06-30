<?php

namespace Tourze\CommentBundle\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentVote;
use Tourze\CommentBundle\Enum\VoteType;

class CommentVoteTest extends TestCase
{
    public function test_construct_setsDefaults(): void
    {
        $vote = new CommentVote();

        $this->assertNull($vote->getId());
        $this->assertNull($vote->getVoterId());
        $this->assertNull($vote->getVoterIp());
        $this->assertNull($vote->getCreateTime());
    }

    public function test_settersAndGetters_workCorrectly(): void
    {
        $vote = new CommentVote();
        $comment = new Comment();
        $now = new \DateTimeImmutable();

        $vote->setComment($comment);
        $vote->setVoterId('user123');
        $vote->setVoterIp('127.0.0.1');
        $vote->setVoteType(VoteType::LIKE);
        $vote->setCreateTime(new \DateTimeImmutable());

        $this->assertEquals($comment, $vote->getComment());
        $this->assertEquals('user123', $vote->getVoterId());
        $this->assertEquals('127.0.0.1', $vote->getVoterIp());
        $this->assertEquals(VoteType::LIKE, $vote->getVoteType());
        $this->assertInstanceOf(\DateTimeImmutable::class, $vote->getCreateTime());
    }

    public function test_setVoteType_acceptsValidTypes(): void
    {
        $vote = new CommentVote();

        $vote->setVoteType(VoteType::LIKE);
        $this->assertEquals(VoteType::LIKE, $vote->getVoteType());

        $vote->setVoteType(VoteType::DISLIKE);
        $this->assertEquals(VoteType::DISLIKE, $vote->getVoteType());
    }

    public function test_setVoteType_throwsExceptionForInvalidType(): void
    {
        $vote = new CommentVote();

        $this->expectException(\TypeError::class);

        /** @phpstan-ignore-next-line */
        $vote->setVoteType('invalid_type');
    }

    public function test_isLike_detectsLikeVotes(): void
    {
        $vote = new CommentVote();

        $vote->setVoteType(VoteType::LIKE);
        $this->assertTrue($vote->isLike());
        $this->assertFalse($vote->isDislike());

        $vote->setVoteType(VoteType::DISLIKE);
        $this->assertFalse($vote->isLike());
        $this->assertTrue($vote->isDislike());
    }

    public function test_isAnonymous_detectsAnonymousVotes(): void
    {
        $vote = new CommentVote();
        $this->assertTrue($vote->isAnonymous());

        $vote->setVoterId('user123');
        $this->assertFalse($vote->isAnonymous());

        $vote->setVoterId(null);
        $this->assertTrue($vote->isAnonymous());
    }

    public function test_voteConstants_haveCorrectValues(): void
    {
        $this->assertEquals('like', VoteType::LIKE->value);
        $this->assertEquals('dislike', VoteType::DISLIKE->value);
    }

    public function test_fluentInterface_worksCorrectly(): void
    {
        $comment = new Comment();
        $now = new \DateTimeImmutable();

        $vote = (new CommentVote())
            ->setComment($comment)
            ->setVoterId('user456')
            ->setVoterIp('192.168.1.1')
            ->setVoteType(VoteType::DISLIKE)
            ->setCreateTime($now);

        $this->assertEquals($comment, $vote->getComment());
        $this->assertEquals('user456', $vote->getVoterId());
        $this->assertEquals('192.168.1.1', $vote->getVoterIp());
        $this->assertEquals(VoteType::DISLIKE, $vote->getVoteType());
        $this->assertEquals($now, $vote->getCreateTime());
    }

    public function test_voteWithComment_relationshipWorks(): void
    {
        $comment = new Comment();
        $comment->setTargetType('article');
        $comment->setTargetId('123');
        $comment->setContent('Great article!');

        $vote = new CommentVote();
        $vote->setComment($comment);
        $vote->setVoterId('user789');
        $vote->setVoteType(VoteType::LIKE);

        $this->assertEquals($comment, $vote->getComment());
        $this->assertEquals('user789', $vote->getVoterId());
        $this->assertTrue($vote->isLike());
        $this->assertFalse($vote->isAnonymous());
    }

    public function test_anonymousVote_worksCorrectly(): void
    {
        $comment = new Comment();
        $vote = new CommentVote();
        
        $vote->setComment($comment);
        $vote->setVoterId(null);
        $vote->setVoterIp('203.0.113.1');
        $vote->setVoteType(VoteType::DISLIKE);

        $this->assertTrue($vote->isAnonymous());
        $this->assertTrue($vote->isDislike());
        $this->assertNull($vote->getVoterId());
        $this->assertEquals('203.0.113.1', $vote->getVoterIp());
    }

    public function test_registeredUserVote_worksCorrectly(): void
    {
        $comment = new Comment();
        $vote = new CommentVote();
        
        $vote->setComment($comment);
        $vote->setVoterId('user_premium');
        $vote->setVoterIp('10.0.0.1');
        $vote->setVoteType(VoteType::LIKE);

        $this->assertFalse($vote->isAnonymous());
        $this->assertTrue($vote->isLike());
        $this->assertEquals('user_premium', $vote->getVoterId());
        $this->assertEquals('10.0.0.1', $vote->getVoterIp());
    }

    public function test_createdAtIsImmutable(): void
    {
        $vote = new CommentVote();
        $vote->setCreateTime(new \DateTimeImmutable('2023-01-01 10:00:00'));
        
        $createTime = $vote->getCreateTime();
        $this->assertNotNull($createTime);
        $originalTime = $createTime->format('Y-m-d H:i:s');

        // DateTimeImmutable 是不可变的，所以修改不会影响原始对象
        $newTime = $createTime->modify('+1 hour');

        // 验证原时间未被修改
        $this->assertEquals($originalTime, $createTime->format('Y-m-d H:i:s'));
        $this->assertEquals('2023-01-01 11:00:00', $newTime->format('Y-m-d H:i:s'));
    }

    public function test_multipleVotesOnSameComment(): void
    {
        $comment = new Comment();
        
        $likeVote = new CommentVote();
        $likeVote->setComment($comment);
        $likeVote->setVoterId('user1');
        $likeVote->setVoteType(VoteType::LIKE);

        $dislikeVote = new CommentVote();
        $dislikeVote->setComment($comment);
        $dislikeVote->setVoterId('user2');
        $dislikeVote->setVoteType(VoteType::DISLIKE);

        $this->assertEquals($comment, $likeVote->getComment());
        $this->assertEquals($comment, $dislikeVote->getComment());
        $this->assertTrue($likeVote->isLike());
        $this->assertTrue($dislikeVote->isDislike());
        $this->assertNotEquals($likeVote->getVoterId(), $dislikeVote->getVoterId());
    }

    public function test_voteTypeValidation_edgeCases(): void
    {
        $vote = new CommentVote();

        // 测试空字符串
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-next-line */
        $vote->setVoteType('');
    }

    public function test_voteTypeValidation_caseSensitive(): void
    {
        $vote = new CommentVote();

        // 测试大小写敏感
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-next-line */
        $vote->setVoteType('LIKE');
    }

    public function test_voteTypeValidation_similarButInvalidValues(): void
    {
        $vote = new CommentVote();

        $invalidTypes = ['likes', 'dislike_it', 'thumbs_up', 'thumbs_down'];

        foreach ($invalidTypes as $invalidType) {
            try {
                /** @phpstan-ignore-next-line */
                $vote->setVoteType($invalidType);
                $this->fail("Expected exception for invalid vote type: {$invalidType}");
            } catch (\TypeError $e) {
                $this->assertStringContainsString('must be of type', $e->getMessage());
            }
        }
    }
}