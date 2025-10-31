<?php

namespace Tourze\CommentBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Event\CommentCreatedEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(CommentCreatedEvent::class)]
final class CommentCreatedEventTest extends AbstractEventTestCase
{
    public function testConstructSetsComment(): void
    {
        $comment = new Comment();
        $event = new CommentCreatedEvent($comment);

        $this->assertSame($comment, $event->getComment());
    }

    public function testGetCommentReturnsCorrectComment(): void
    {
        $comment = new Comment();
        $comment->setContent('New comment');

        $event = new CommentCreatedEvent($comment);

        $this->assertSame($comment, $event->getComment());
        $this->assertEquals('New comment', $event->getComment()->getContent());
    }
}
