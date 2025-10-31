<?php

namespace Tourze\CommentBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CommentBundle\Enum\CommentStatus;
use Tourze\CommentBundle\Repository\CommentRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineIpBundle\Attribute\CreateIpColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineUserBundle\Attribute\CreatedByColumn;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(name: 'comment', options: ['comment' => '评论表'])]
#[ORM\Index(name: 'comment_idx_target', columns: ['target_type', 'target_id'])]
class Comment implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 50, options: ['comment' => '目标类型'])]
    private string $targetType;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '目标ID'])]
    private string $targetId;

    #[Assert\NotBlank]
    #[Assert\Length(max: 10000)]
    #[ORM\Column(type: Types::TEXT, options: ['comment' => '评论内容'])]
    private string $content;

    #[Assert\Length(max: 100)]
    #[CreatedByColumn]
    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '作者ID'])]
    private ?string $authorId = null;

    #[Assert\Length(max: 100)]
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '作者姓名'])]
    private ?string $authorName = null;

    #[Assert\Email]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '作者邮箱'])]
    private ?string $authorEmail = null;

    #[Assert\Length(max: 45)]
    #[CreateIpColumn]
    #[ORM\Column(type: Types::STRING, length: 45, nullable: true, options: ['comment' => '作者IP地址'])]
    private ?string $authorIp = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '用户代理'])]
    private ?string $userAgent = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Comment $parent = null;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['remove'], fetch: 'EXTRA_LAZY')]
    private Collection $replies;

    #[Assert\Choice(callback: [CommentStatus::class, 'cases'])]
    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 20, enumType: CommentStatus::class, options: ['default' => 'pending', 'comment' => '评论状态'])]
    private CommentStatus $status = CommentStatus::PENDING;

    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0, 'comment' => '点赞数'])]
    private int $likesCount = 0;

    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0, 'comment' => '踩数'])]
    private int $dislikesCount = 0;

    #[Assert\Type(type: 'bool')]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false, 'comment' => '是否置顶'])]
    private bool $pinned = false;

    #[Assert\Type(type: '\DateTimeImmutable')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '删除时间'])]
    private ?\DateTimeImmutable $deleteTime = null;

    #[Assert\Type(type: 'bool')]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true, 'comment' => '是否有效'])]
    private bool $valid = true;

    /** @var Collection<int, CommentVote> */
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

    public function setTargetType(string $targetType): void
    {
        $this->targetType = $targetType;
    }

    public function getTargetId(): string
    {
        return $this->targetId;
    }

    public function setTargetId(string $targetId): void
    {
        $this->targetId = $targetId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getAuthorId(): ?string
    {
        return $this->authorId;
    }

    public function setAuthorId(?string $authorId): void
    {
        $this->authorId = $authorId;
    }

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(?string $authorName): void
    {
        $this->authorName = $authorName;
    }

    public function getAuthorEmail(): ?string
    {
        return $this->authorEmail;
    }

    public function setAuthorEmail(?string $authorEmail): void
    {
        $this->authorEmail = $authorEmail;
    }

    public function getAuthorIp(): ?string
    {
        return $this->authorIp;
    }

    public function setAuthorIp(?string $authorIp): void
    {
        $this->authorIp = $authorIp;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    /**
     * @return Collection<int, Comment>
     */
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

    public function setParent(?Comment $parent): void
    {
        $this->parent = $parent;
    }

    public function getStatus(): CommentStatus
    {
        return $this->status;
    }

    public function setStatus(CommentStatus $status): void
    {
        $this->status = $status;
    }

    public function getLikesCount(): int
    {
        return $this->likesCount;
    }

    public function setLikesCount(int $likesCount): void
    {
        $this->likesCount = $likesCount;
    }

    public function getDislikesCount(): int
    {
        return $this->dislikesCount;
    }

    public function setDislikesCount(int $dislikesCount): void
    {
        $this->dislikesCount = $dislikesCount;
    }

    public function isPinned(): bool
    {
        return $this->pinned;
    }

    public function setPinned(bool $pinned): void
    {
        $this->pinned = $pinned;
    }

    public function getDeleteTime(): ?\DateTimeImmutable
    {
        return $this->deleteTime;
    }

    public function setDeleteTime(?\DateTimeImmutable $deleteTime): void
    {
        $this->deleteTime = $deleteTime;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    /**
     * @return Collection<int, CommentVote>
     */
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
        return null === $this->authorId;
    }

    public function isApproved(): bool
    {
        return CommentStatus::APPROVED === $this->status;
    }

    public function isPending(): bool
    {
        return CommentStatus::PENDING === $this->status;
    }

    public function isRejected(): bool
    {
        return CommentStatus::REJECTED === $this->status;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deleteTime || CommentStatus::DELETED === $this->status;
    }

    public function getDepth(): int
    {
        $depth = 0;
        $parent = $this->parent;
        while (null !== $parent) {
            ++$depth;
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
