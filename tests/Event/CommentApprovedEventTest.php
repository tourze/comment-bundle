<?php

namespace Tourze\CommentBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Event\CommentApprovedEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(CommentApprovedEvent::class)]
final class CommentApprovedEventTest extends AbstractEventTestCase
{
    public function testConstructSetsComment(): void
    {
        $comment = new Comment();
        $event = new CommentApprovedEvent($comment);

        $this->assertSame($comment, $event->getComment());
    }

    public function testGetCommentReturnsCorrectComment(): void
    {
        $comment = new Comment();
        $comment->setContent('Test comment');

        $event = new CommentApprovedEvent($comment);

        $this->assertSame($comment, $event->getComment());
        $this->assertEquals('Test comment', $event->getComment()->getContent());
    }
}
