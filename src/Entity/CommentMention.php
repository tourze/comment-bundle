<?php

namespace Tourze\CommentBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\CommentBundle\Repository\CommentMentionRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\CreateTimeAware;

#[ORM\Entity(repositoryClass: CommentMentionRepository::class)]
#[ORM\Table(name: 'comment_mention', options: ['comment' => '评论提及表'])]
#[ORM\UniqueConstraint(name: 'unique_mention', columns: ['comment_id', 'mentioned_user_id'])]
#[ORM\Index(name: 'comment_mention_idx_comment', columns: ['comment_id'])]
class CommentMention implements \Stringable
{
    use CreateTimeAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Comment::class, fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(name: 'comment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Comment $comment;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '被提及用户ID'])]
    private string $mentionedUserId;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '被提及用户名'])]
    private ?string $mentionedUserName = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false, 'comment' => '是否已通知'])]
    private bool $notified = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '通知时间'])]
    private ?\DateTimeImmutable $notifyTime = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true, 'comment' => '是否有效'])]
    private bool $valid = true;

    public function __construct()
    {
        $this->createTime = new \DateTimeImmutable();
    }

    public function getComment(): Comment
    {
        return $this->comment;
    }

    public function setComment(Comment $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getMentionedUserId(): string
    {
        return $this->mentionedUserId;
    }

    public function setMentionedUserId(string $mentionedUserId): self
    {
        $this->mentionedUserId = $mentionedUserId;
        return $this;
    }

    public function getMentionedUserName(): ?string
    {
        return $this->mentionedUserName;
    }

    public function setMentionedUserName(?string $mentionedUserName): self
    {
        $this->mentionedUserName = $mentionedUserName;
        return $this;
    }

    public function isNotified(): bool
    {
        return $this->notified;
    }

    public function setNotified(bool $notified): self
    {
        $this->notified = $notified;
        if ($notified && $this->notifyTime === null) {
            $this->notifyTime = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getNotifyTime(): ?\DateTimeImmutable
    {
        return $this->notifyTime;
    }

    public function setNotifyTime(?\DateTimeImmutable $notifyTime): self
    {
        $this->notifyTime = $notifyTime;
        return $this;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): self
    {
        $this->valid = $valid;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('Mention #%d: @%s in Comment #%d', $this->id ?? 0, $this->mentionedUserId, $this->comment->getId() ?? 0);
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}