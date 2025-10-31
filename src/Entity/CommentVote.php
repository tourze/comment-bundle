<?php

namespace Tourze\CommentBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CommentBundle\Enum\VoteType;
use Tourze\CommentBundle\Repository\CommentVoteRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineIpBundle\Attribute\CreateIpColumn;
use Tourze\DoctrineTimestampBundle\Traits\CreateTimeAware;
use Tourze\DoctrineUserBundle\Attribute\CreatedByColumn;

#[ORM\Entity(repositoryClass: CommentVoteRepository::class)]
#[ORM\Table(name: 'comment_vote', options: ['comment' => '评论投票表'])]
#[ORM\UniqueConstraint(name: 'unique_vote', columns: ['comment_id', 'voter_id', 'voter_ip'])]
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

    #[Assert\Length(max: 100)]
    #[CreatedByColumn]
    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '投票者ID'])]
    private ?string $voterId = null;

    #[Assert\Length(max: 45)]
    #[CreateIpColumn]
    #[ORM\Column(type: Types::STRING, length: 45, nullable: true, options: ['comment' => '投票者IP地址'])]
    private ?string $voterIp = null;

    #[Assert\Choice(callback: [VoteType::class, 'cases'])]
    #[ORM\Column(type: Types::STRING, length: 10, enumType: VoteType::class, options: ['comment' => '投票类型'])]
    private VoteType $voteType;

    #[Assert\Type(type: 'bool')]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true, 'comment' => '是否有效'])]
    private bool $valid = true;

    public function getComment(): Comment
    {
        return $this->comment;
    }

    public function setComment(Comment $comment): void
    {
        $this->comment = $comment;
    }

    public function getVoterId(): ?string
    {
        return $this->voterId;
    }

    public function setVoterId(?string $voterId): void
    {
        $this->voterId = $voterId;
    }

    public function getVoterIp(): ?string
    {
        return $this->voterIp;
    }

    public function setVoterIp(?string $voterIp): void
    {
        $this->voterIp = $voterIp;
    }

    public function getVoteType(): VoteType
    {
        return $this->voteType;
    }

    public function setVoteType(VoteType $voteType): void
    {
        $this->voteType = $voteType;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    public function isLike(): bool
    {
        return VoteType::LIKE === $this->voteType;
    }

    public function isDislike(): bool
    {
        return VoteType::DISLIKE === $this->voteType;
    }

    public function isAnonymous(): bool
    {
        return null === $this->voterId;
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
