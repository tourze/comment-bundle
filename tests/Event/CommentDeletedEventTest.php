<?php

namespace Tourze\CommentBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Event\CommentDeletedEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(CommentDeletedEvent::class)]
final class CommentDeletedEventTest extends AbstractEventTestCase
{
    public function testConstructSetsComment(): void
    {
        $comment = new Comment();
        $event = new CommentDeletedEvent($comment);

        $this->assertSame($comment, $event->getComment());
    }

    public function testGetCommentReturnsCorrectComment(): void
    {
        $comment = new Comment();
        $comment->setContent('Deleted comment');

        $event = new CommentDeletedEvent($comment);

        $this->assertSame($comment, $event->getComment());
        $this->assertEquals('Deleted comment', $event->getComment()->getContent());
    }
}
