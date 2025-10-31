<?php

namespace Tourze\CommentBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CommentBundle\Repository\CommentMentionRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\CreateTimeAware;

#[ORM\Entity(repositoryClass: CommentMentionRepository::class)]
#[ORM\Table(name: 'comment_mention', options: ['comment' => '评论提及表'])]
#[ORM\UniqueConstraint(name: 'unique_mention', columns: ['comment_id', 'mentioned_user_id'])]
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

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '被提及用户ID'])]
    private string $mentionedUserId;

    #[Assert\Length(max: 100)]
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '被提及用户名'])]
    private ?string $mentionedUserName = null;

    #[Assert\Type(type: 'bool')]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false, 'comment' => '是否已通知'])]
    private bool $notified = false;

    #[Assert\Type(type: '\DateTimeImmutable')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '通知时间'])]
    private ?\DateTimeImmutable $notifyTime = null;

    #[Assert\Type(type: 'bool')]
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

    public function setComment(Comment $comment): void
    {
        $this->comment = $comment;
    }

    public function getMentionedUserId(): string
    {
        return $this->mentionedUserId;
    }

    public function setMentionedUserId(string $mentionedUserId): void
    {
        $this->mentionedUserId = $mentionedUserId;
    }

    public function getMentionedUserName(): ?string
    {
        return $this->mentionedUserName;
    }

    public function setMentionedUserName(?string $mentionedUserName): void
    {
        $this->mentionedUserName = $mentionedUserName;
    }

    public function isNotified(): bool
    {
        return $this->notified;
    }

    public function setNotified(bool $notified): void
    {
        $this->notified = $notified;
        if ($notified && null === $this->notifyTime) {
            $this->notifyTime = new \DateTimeImmutable();
        }
    }

    public function getNotifyTime(): ?\DateTimeImmutable
    {
        return $this->notifyTime;
    }

    public function setNotifyTime(?\DateTimeImmutable $notifyTime): void
    {
        $this->notifyTime = $notifyTime;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
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
