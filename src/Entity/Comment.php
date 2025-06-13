<?php

namespace Tourze\CommentBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\CommentBundle\Enum\CommentStatus;
use Tourze\CommentBundle\Repository\CommentRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineIpBundle\Attribute\CreateIpColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineUserBundle\Attribute\CreatedByColumn;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(name: 'comment', options: ['comment' => '评论表'])]
#[ORM\Index(name: 'comment_idx_target', columns: ['target_type', 'target_id'])]
#[ORM\Index(name: 'comment_idx_parent', columns: ['parent_id'])]
class Comment implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 50, options: ['comment' => '目标类型'])]
    private string $targetType;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '目标ID'])]
    private string $targetId;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '评论内容'])]
    private string $content;

    #[CreatedByColumn]
    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '作者ID'])]
    private ?string $authorId = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '作者姓名'])]
    private ?string $authorName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '作者邮箱'])]
    private ?string $authorEmail = null;

    #[CreateIpColumn]
    #[ORM\Column(type: Types::STRING, length: 45, nullable: true, options: ['comment' => '作者IP地址'])]
    private ?string $authorIp = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '用户代理'])]
    private ?string $userAgent = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Comment $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['remove'], fetch: 'EXTRA_LAZY')]
    private Collection $replies;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 20, enumType: CommentStatus::class, options: ['default' => 'pending', 'comment' => '评论状态'])]
    private CommentStatus $status = CommentStatus::PENDING;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0, 'comment' => '点赞数'])]
    private int $likesCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0, 'comment' => '踩数'])]
    private int $dislikesCount = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false, 'comment' => '是否置顶'])]
    private bool $pinned = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '删除时间'])]
    private ?\DateTime $deleteTime = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true, 'comment' => '是否有效'])]
    private bool $valid = true;

    #[ORM\OneToMany(mappedBy: 'comment', targetEntity: CommentVote::class, cascade: ['remove'], fetch: 'EXTRA_LAZY')]
    private Collection $votes;

    public function __construct()
    {
        $this->replies = new ArrayCollection();
        $this->votes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function setTargetType(string $targetType): self
    {
        $this->targetType = $targetType;
        return $this;
    }

    public function getTargetId(): string
    {
        return $this->targetId;
    }

    public function setTargetId(string $targetId): self
    {
        $this->targetId = $targetId;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getAuthorId(): ?string
    {
        return $this->authorId;
    }

    public function setAuthorId(?string $authorId): self
    {
        $this->authorId = $authorId;
        return $this;
    }

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(?string $authorName): self
    {
        $this->authorName = $authorName;
        return $this;
    }

    public function getAuthorEmail(): ?string
    {
        return $this->authorEmail;
    }

    public function setAuthorEmail(?string $authorEmail): self
    {
        $this->authorEmail = $authorEmail;
        return $this;
    }

    public function getAuthorIp(): ?string
    {
        return $this->authorIp;
    }

    public function setAuthorIp(?string $authorIp): self
    {
        $this->authorIp = $authorIp;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function addReply(Comment $reply): self
    {
        if (!$this->replies->contains($reply)) {
            $this->replies->add($reply);
            $reply->setParent($this);
        }
        return $this;
    }

    public function removeReply(Comment $reply): self
    {
        if ($this->replies->removeElement($reply)) {
            if ($reply->getParent() === $this) {
                $reply->setParent(null);
            }
        }
        return $this;
    }

    public function getParent(): ?Comment
    {
        return $this->parent;
    }

    public function setParent(?Comment $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    public function getStatus(): CommentStatus
    {
        return $this->status;
    }

    public function setStatus(CommentStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getLikesCount(): int
    {
        return $this->likesCount;
    }

    public function setLikesCount(int $likesCount): self
    {
        $this->likesCount = $likesCount;
        return $this;
    }

    public function getDislikesCount(): int
    {
        return $this->dislikesCount;
    }

    public function setDislikesCount(int $dislikesCount): self
    {
        $this->dislikesCount = $dislikesCount;
        return $this;
    }

    public function isPinned(): bool
    {
        return $this->pinned;
    }

    public function setPinned(bool $pinned): self
    {
        $this->pinned = $pinned;
        return $this;
    }

    public function getDeleteTime(): ?\DateTime
    {
        return $this->deleteTime;
    }

    public function setDeleteTime(?\DateTime $deleteTime): self
    {
        $this->deleteTime = $deleteTime;
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

    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(CommentVote $vote): self
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setComment($this);
        }
        return $this;
    }

    public function removeVote(CommentVote $vote): self
    {
        $this->votes->removeElement($vote);
        return $this;
    }

    public function isAnonymous(): bool
    {
        return $this->authorId === null;
    }

    public function isApproved(): bool
    {
        return $this->status === CommentStatus::APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === CommentStatus::PENDING;
    }

    public function isRejected(): bool
    {
        return $this->status === CommentStatus::REJECTED;
    }

    public function isDeleted(): bool
    {
        return $this->deleteTime !== null || $this->status === CommentStatus::DELETED;
    }

    public function getDepth(): int
    {
        $depth = 0;
        $parent = $this->parent;
        while ($parent !== null) {
            $depth++;
            $parent = $parent->getParent();
        }
        return $depth;
    }

    public function hasReplies(): bool
    {
        return !$this->replies->isEmpty();
    }

    public function getScore(): int
    {
        return $this->likesCount - $this->dislikesCount;
    }

    public function __toString(): string
    {
        return sprintf('Comment #%d: %s', $this->id ?? 0, mb_substr($this->content, 0, 50));
    }
}