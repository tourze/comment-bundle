<?php

namespace Tourze\CommentBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\CommentBundle\Enum\VoteType;
use Tourze\CommentBundle\Repository\CommentVoteRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineIpBundle\Attribute\CreateIpColumn;
use Tourze\DoctrineTimestampBundle\Traits\CreateTimeAware;
use Tourze\DoctrineUserBundle\Attribute\CreatedByColumn;

#[ORM\Entity(repositoryClass: CommentVoteRepository::class)]
#[ORM\Table(name: 'comment_vote', options: ['comment' => '评论投票表'])]
#[ORM\UniqueConstraint(name: 'unique_vote', columns: ['comment_id', 'voter_id', 'voter_ip'])]
#[ORM\Index(name: 'comment_vote_idx_comment', columns: ['comment_id'])]
class CommentVote implements \Stringable
{
    use CreateTimeAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Comment::class, inversedBy: 'votes', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinColumn(name: 'comment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Comment $comment;

    #[CreatedByColumn]
    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '投票者ID'])]
    private ?string $voterId = null;

    #[CreateIpColumn]
    #[ORM\Column(type: Types::STRING, length: 45, nullable: true, options: ['comment' => '投票者IP地址'])]
    private ?string $voterIp = null;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: VoteType::class, options: ['comment' => '投票类型'])]
    private VoteType $voteType;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true, 'comment' => '是否有效'])]
    private bool $valid = true;

    public function getComment(): Comment
    {
        return $this->comment;
    }

    public function setComment(Comment $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getVoterId(): ?string
    {
        return $this->voterId;
    }

    public function setVoterId(?string $voterId): self
    {
        $this->voterId = $voterId;
        return $this;
    }

    public function getVoterIp(): ?string
    {
        return $this->voterIp;
    }

    public function setVoterIp(?string $voterIp): self
    {
        $this->voterIp = $voterIp;
        return $this;
    }

    public function getVoteType(): VoteType
    {
        return $this->voteType;
    }

    public function setVoteType(VoteType $voteType): self
    {
        $this->voteType = $voteType;
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

    public function isLike(): bool
    {
        return $this->voteType === VoteType::LIKE;
    }

    public function isDislike(): bool
    {
        return $this->voteType === VoteType::DISLIKE;
    }

    public function isAnonymous(): bool
    {
        return $this->voterId === null;
    }

    public function __toString(): string
    {
        return sprintf('Vote #%d: %s on Comment #%d', $this->id ?? 0, $this->voteType->value, $this->comment->getId() ?? 0);
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
