<?php

namespace Tourze\CommentBundle\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentVote;

class CommentTest extends TestCase
{
    public function test_construct_setsDefaults(): void
    {
        $comment = new Comment();

        $this->assertNull($comment->getId());
        $this->assertEquals('pending', $comment->getStatus());
        $this->assertEquals(0, $comment->getLikesCount());
        $this->assertEquals(0, $comment->getDislikesCount());
        $this->assertFalse($comment->isPinned());
        $this->assertInstanceOf(\DateTimeImmutable::class, $comment->getCreatedAt());
        $this->assertNull($comment->getUpdatedAt());
        $this->assertNull($comment->getDeletedAt());
        $this->assertEmpty($comment->getReplies());
        $this->assertEmpty($comment->getVotes());
    }

    public function test_settersAndGetters_workCorrectly(): void
    {
        $comment = new Comment();
        $now = new \DateTimeImmutable();

        $comment->setTargetType('article');
        $comment->setTargetId('123');
        $comment->setContent('Test content');
        $comment->setAuthorId('user123');
        $comment->setAuthorName('Test User');
        $comment->setAuthorEmail('test@example.com');
        $comment->setAuthorIp('127.0.0.1');
        $comment->setUserAgent('Mozilla/5.0');
        $comment->setStatus('approved');
        $comment->setLikesCount(5);
        $comment->setDislikesCount(2);
        $comment->setIsPinned(true);
        $comment->setCreatedAt($now);
        $comment->setUpdatedAt($now);
        $comment->setDeletedAt($now);

        $this->assertEquals('article', $comment->getTargetType());
        $this->assertEquals('123', $comment->getTargetId());
        $this->assertEquals('Test content', $comment->getContent());
        $this->assertEquals('user123', $comment->getAuthorId());
        $this->assertEquals('Test User', $comment->getAuthorName());
        $this->assertEquals('test@example.com', $comment->getAuthorEmail());
        $this->assertEquals('127.0.0.1', $comment->getAuthorIp());
        $this->assertEquals('Mozilla/5.0', $comment->getUserAgent());
        $this->assertEquals('approved', $comment->getStatus());
        $this->assertEquals(5, $comment->getLikesCount());
        $this->assertEquals(2, $comment->getDislikesCount());
        $this->assertTrue($comment->isPinned());
        $this->assertEquals($now, $comment->getCreatedAt());
        $this->assertEquals($now, $comment->getUpdatedAt());
        $this->assertEquals($now, $comment->getDeletedAt());
    }

    public function test_parentAndReplies_relationshipWorks(): void
    {
        $parent = new Comment();
        $reply1 = new Comment();
        $reply2 = new Comment();

        $parent->addReply($reply1);
        $parent->addReply($reply2);

        $this->assertEquals($parent, $reply1->getParent());
        $this->assertEquals($parent, $reply2->getParent());
        $this->assertCount(2, $parent->getReplies());
        $this->assertTrue($parent->getReplies()->contains($reply1));
        $this->assertTrue($parent->getReplies()->contains($reply2));

        $parent->removeReply($reply1);
        $this->assertCount(1, $parent->getReplies());
        $this->assertFalse($parent->getReplies()->contains($reply1));
        $this->assertNull($reply1->getParent());
    }

    public function test_votes_relationshipWorks(): void
    {
        $comment = new Comment();
        $vote1 = new CommentVote();
        $vote2 = new CommentVote();

        $comment->addVote($vote1);
        $comment->addVote($vote2);

        $this->assertEquals($comment, $vote1->getComment());
        $this->assertEquals($comment, $vote2->getComment());
        $this->assertCount(2, $comment->getVotes());
        $this->assertTrue($comment->getVotes()->contains($vote1));
        $this->assertTrue($comment->getVotes()->contains($vote2));

        $comment->removeVote($vote1);
        $this->assertCount(1, $comment->getVotes());
        $this->assertFalse($comment->getVotes()->contains($vote1));
    }

    public function test_isAnonymous_detectsAnonymousComments(): void
    {
        $comment = new Comment();
        $this->assertTrue($comment->isAnonymous());

        $comment->setAuthorId('user123');
        $this->assertFalse($comment->isAnonymous());

        $comment->setAuthorId(null);
        $this->assertTrue($comment->isAnonymous());
    }

    public function test_statusMethods_workCorrectly(): void
    {
        $comment = new Comment();

        $comment->setStatus('approved');
        $this->assertTrue($comment->isApproved());
        $this->assertFalse($comment->isPending());
        $this->assertFalse($comment->isRejected());

        $comment->setStatus('pending');
        $this->assertFalse($comment->isApproved());
        $this->assertTrue($comment->isPending());
        $this->assertFalse($comment->isRejected());

        $comment->setStatus('rejected');
        $this->assertFalse($comment->isApproved());
        $this->assertFalse($comment->isPending());
        $this->assertTrue($comment->isRejected());
    }

    public function test_isDeleted_detectsDeletedComments(): void
    {
        $comment = new Comment();
        $this->assertFalse($comment->isDeleted());

        $comment->setDeletedAt(new \DateTimeImmutable());
        $this->assertTrue($comment->isDeleted());
    }

    public function test_getDepth_calculatesCorrectDepth(): void
    {
        $level0 = new Comment();
        $level1 = new Comment();
        $level2 = new Comment();
        $level3 = new Comment();

        $level1->setParent($level0);
        $level2->setParent($level1);
        $level3->setParent($level2);

        $this->assertEquals(0, $level0->getDepth());
        $this->assertEquals(1, $level1->getDepth());
        $this->assertEquals(2, $level2->getDepth());
        $this->assertEquals(3, $level3->getDepth());
    }

    public function test_hasReplies_detectsReplies(): void
    {
        $comment = new Comment();
        $reply = new Comment();

        $this->assertFalse($comment->hasReplies());

        $comment->addReply($reply);
        $this->assertTrue($comment->hasReplies());
    }

    public function test_getScore_calculatesCorrectScore(): void
    {
        $comment = new Comment();

        $this->assertEquals(0, $comment->getScore());

        $comment->setLikesCount(10);
        $comment->setDislikesCount(3);
        $this->assertEquals(7, $comment->getScore());

        $comment->setLikesCount(2);
        $comment->setDislikesCount(5);
        $this->assertEquals(-3, $comment->getScore());
    }
}