<?php

namespace Tourze\CommentBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Event\CommentUpdatedEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(CommentUpdatedEvent::class)]
final class CommentUpdatedEventTest extends AbstractEventTestCase
{
    public function testConstructSetsComment(): void
    {
        $comment = new Comment();
        $event = new CommentUpdatedEvent($comment);

        $this->assertSame($comment, $event->getComment());
    }

    public function testGetCommentReturnsCorrectComment(): void
    {
        $comment = new Comment();
        $comment->setContent('Updated comment');

        $event = new CommentUpdatedEvent($comment);

        $this->assertSame($comment, $event->getComment());
        $this->assertEquals('Updated comment', $event->getComment()->getContent());
    }
}
