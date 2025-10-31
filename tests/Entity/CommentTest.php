<?php

namespace Tourze\CommentBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentVote;
use Tourze\CommentBundle\Enum\CommentStatus;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(Comment::class)]
final class CommentTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new Comment();
    }

    /**
     * @return \Generator<string, array{string, mixed}>
     */
    public static function propertiesProvider(): \Generator
    {
        yield 'valid' => ['valid', true];
    }

    public function testConstructSetsDefaults(): void
    {
        $comment = new Comment();

        $this->assertNull($comment->getId());
        $this->assertEquals(CommentStatus::PENDING, $comment->getStatus());
        $this->assertEquals(0, $comment->getLikesCount());
        $this->assertEquals(0, $comment->getDislikesCount());
        $this->assertFalse($comment->isPinned());
        $this->assertNull($comment->getCreateTime());
        $this->assertNull($comment->getUpdateTime());
        $this->assertNull($comment->getDeleteTime());
        $this->assertCount(0, $comment->getReplies());
        $this->assertCount(0, $comment->getVotes());
    }

    public function testSettersAndGettersWorkCorrectly(): void
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
        $comment->setStatus(CommentStatus::APPROVED);
        $comment->setLikesCount(5);
        $comment->setDislikesCount(2);
        $comment->setPinned(true);
        $comment->setCreateTime($now);
        $comment->setUpdateTime($now);
        $comment->setDeleteTime($now);

        $this->assertEquals('article', $comment->getTargetType());
        $this->assertEquals('123', $comment->getTargetId());
        $this->assertEquals('Test content', $comment->getContent());
        $this->assertEquals('user123', $comment->getAuthorId());
        $this->assertEquals('Test User', $comment->getAuthorName());
        $this->assertEquals('test@example.com', $comment->getAuthorEmail());
        $this->assertEquals('127.0.0.1', $comment->getAuthorIp());
        $this->assertEquals('Mozilla/5.0', $comment->getUserAgent());
        $this->assertEquals(CommentStatus::APPROVED, $comment->getStatus());
        $this->assertEquals(5, $comment->getLikesCount());
        $this->assertEquals(2, $comment->getDislikesCount());
        $this->assertTrue($comment->isPinned());
        $this->assertEquals($now, $comment->getCreateTime());
        $this->assertEquals($now, $comment->getUpdateTime());
        $this->assertEquals($now, $comment->getDeleteTime());
    }

    public function testParentAndRepliesRelationshipWorks(): void
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

    public function testVotesRelationshipWorks(): void
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

    public function testIsAnonymousDetectsAnonymousComments(): void
    {
        $comment = new Comment();
        $this->assertTrue($comment->isAnonymous());

        $comment->setAuthorId('user123');
        $this->assertFalse($comment->isAnonymous());

        $comment->setAuthorId(null);
        $this->assertTrue($comment->isAnonymous());
    }

    public function testStatusMethodsWorkCorrectly(): void
    {
        $comment = new Comment();

        $comment->setStatus(CommentStatus::APPROVED);
        $this->assertTrue($comment->isApproved());
        $this->assertFalse($comment->isPending());
        $this->assertFalse($comment->isRejected());

        $comment->setStatus(CommentStatus::PENDING);
        $this->assertFalse($comment->isApproved());
        $this->assertTrue($comment->isPending());
        $this->assertFalse($comment->isRejected());

        $comment->setStatus(CommentStatus::REJECTED);
        $this->assertFalse($comment->isApproved());
        $this->assertFalse($comment->isPending());
        $this->assertTrue($comment->isRejected());
    }

    public function testIsDeletedDetectsDeletedComments(): void
    {
        $comment = new Comment();
        $this->assertFalse($comment->isDeleted());

        $comment->setDeleteTime(new \DateTimeImmutable());
        $this->assertTrue($comment->isDeleted());
    }

    public function testGetDepthCalculatesCorrectDepth(): void
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

    public function testHasRepliesDetectsReplies(): void
    {
        $comment = new Comment();
        $reply = new Comment();

        $this->assertFalse($comment->hasReplies());

        $comment->addReply($reply);
        $this->assertTrue($comment->hasReplies());
    }

    public function testGetScoreCalculatesCorrectScore(): void
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
