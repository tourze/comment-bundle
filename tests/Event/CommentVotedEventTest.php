<?php

namespace Tourze\CommentBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Enum\VoteType;
use Tourze\CommentBundle\Event\CommentVotedEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(CommentVotedEvent::class)]
final class CommentVotedEventTest extends AbstractEventTestCase
{
    public function testConstructSetsAllProperties(): void
    {
        $comment = new Comment();
        $voteType = VoteType::LIKE;
        $action = 'added';
        $voterId = 'user123';

        $event = new CommentVotedEvent($comment, $voteType, $action, $voterId);

        $this->assertSame($comment, $event->getComment());
        $this->assertEquals($voteType, $event->getVoteType());
        $this->assertEquals($action, $event->getAction());
        $this->assertEquals($voterId, $event->getVoterId());
    }

    public function testConstructWithoutVoterId(): void
    {
        $comment = new Comment();
        $voteType = VoteType::DISLIKE;
        $action = 'removed';

        $event = new CommentVotedEvent($comment, $voteType, $action);

        $this->assertSame($comment, $event->getComment());
        $this->assertEquals($voteType, $event->getVoteType());
        $this->assertEquals($action, $event->getAction());
        $this->assertNull($event->getVoterId());
    }
}
