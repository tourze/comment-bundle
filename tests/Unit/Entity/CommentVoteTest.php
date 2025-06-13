<?php

namespace Tourze\CommentBundle\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentVote;

class CommentVoteTest extends TestCase
{
    public function test_construct_setsDefaults(): void
    {
        $vote = new CommentVote();

        $this->assertNull($vote->getId());
        $this->assertNull($vote->getVoterId());
        $this->assertNull($vote->getVoterIp());
        $this->assertInstanceOf(\DateTimeImmutable::class, $vote->getCreatedAt());
    }

    public function test_settersAndGetters_workCorrectly(): void
    {
        $vote = new CommentVote();
        $comment = new Comment();
        $now = new \DateTimeImmutable();

        $vote->setComment($comment);
        $vote->setVoterId('user123');
        $vote->setVoterIp('127.0.0.1');
        $vote->setVoteType(CommentVote::VOTE_LIKE);
        $vote->setCreatedAt($now);

        $this->assertEquals($comment, $vote->getComment());
        $this->assertEquals('user123', $vote->getVoterId());
        $this->assertEquals('127.0.0.1', $vote->getVoterIp());
        $this->assertEquals(CommentVote::VOTE_LIKE, $vote->getVoteType());
        $this->assertEquals($now, $vote->getCreatedAt());
    }

    public function test_setVoteType_acceptsValidTypes(): void
    {
        $vote = new CommentVote();

        $vote->setVoteType(CommentVote::VOTE_LIKE);
        $this->assertEquals(CommentVote::VOTE_LIKE, $vote->getVoteType());

        $vote->setVoteType(CommentVote::VOTE_DISLIKE);
        $this->assertEquals(CommentVote::VOTE_DISLIKE, $vote->getVoteType());
    }

    public function test_setVoteType_throwsExceptionForInvalidType(): void
    {
        $vote = new CommentVote();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid vote type');

        $vote->setVoteType('invalid_type');
    }

    public function test_isLike_detectsLikeVotes(): void
    {
        $vote = new CommentVote();

        $vote->setVoteType(CommentVote::VOTE_LIKE);
        $this->assertTrue($vote->isLike());
        $this->assertFalse($vote->isDislike());

        $vote->setVoteType(CommentVote::VOTE_DISLIKE);
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
        $this->assertEquals('like', CommentVote::VOTE_LIKE);
        $this->assertEquals('dislike', CommentVote::VOTE_DISLIKE);
    }

    public function test_fluentInterface_worksCorrectly(): void
    {
        $comment = new Comment();
        $now = new \DateTimeImmutable();

        $vote = (new CommentVote())
            ->setComment($comment)
            ->setVoterId('user456')
            ->setVoterIp('192.168.1.1')
            ->setVoteType(CommentVote::VOTE_DISLIKE)
            ->setCreatedAt($now);

        $this->assertEquals($comment, $vote->getComment());
        $this->assertEquals('user456', $vote->getVoterId());
        $this->assertEquals('192.168.1.1', $vote->getVoterIp());
        $this->assertEquals(CommentVote::VOTE_DISLIKE, $vote->getVoteType());
        $this->assertEquals($now, $vote->getCreatedAt());
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
        $vote->setVoteType(CommentVote::VOTE_LIKE);

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
        $vote->setVoteType(CommentVote::VOTE_DISLIKE);

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
        $vote->setVoteType(CommentVote::VOTE_LIKE);

        $this->assertFalse($vote->isAnonymous());
        $this->assertTrue($vote->isLike());
        $this->assertEquals('user_premium', $vote->getVoterId());
        $this->assertEquals('10.0.0.1', $vote->getVoterIp());
    }

    public function test_createdAtIsImmutable(): void
    {
        $vote = new CommentVote();
        $originalCreatedAt = $vote->getCreatedAt();

        // 尝试修改时间（这应该不会影响原始对象）
        $modifiedTime = $originalCreatedAt->modify('+1 hour');

        $this->assertEquals($originalCreatedAt, $vote->getCreatedAt());
        $this->assertNotEquals($modifiedTime, $vote->getCreatedAt());
    }

    public function test_multipleVotesOnSameComment(): void
    {
        $comment = new Comment();
        
        $likeVote = new CommentVote();
        $likeVote->setComment($comment);
        $likeVote->setVoterId('user1');
        $likeVote->setVoteType(CommentVote::VOTE_LIKE);

        $dislikeVote = new CommentVote();
        $dislikeVote->setComment($comment);
        $dislikeVote->setVoterId('user2');
        $dislikeVote->setVoteType(CommentVote::VOTE_DISLIKE);

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
        $this->expectException(\InvalidArgumentException::class);
        $vote->setVoteType('');
    }

    public function test_voteTypeValidation_caseSensitive(): void
    {
        $vote = new CommentVote();

        // 测试大小写敏感
        $this->expectException(\InvalidArgumentException::class);
        $vote->setVoteType('LIKE');
    }

    public function test_voteTypeValidation_similarButInvalidValues(): void
    {
        $vote = new CommentVote();

        $invalidTypes = ['likes', 'dislike_it', 'thumbs_up', 'thumbs_down'];

        foreach ($invalidTypes as $invalidType) {
            try {
                $vote->setVoteType($invalidType);
                $this->fail("Expected exception for invalid vote type: {$invalidType}");
            } catch (\InvalidArgumentException $e) {
                $this->assertEquals('Invalid vote type', $e->getMessage());
            }
        }
    }
}