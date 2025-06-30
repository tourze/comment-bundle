<?php

namespace Tourze\CommentBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Entity\CommentVote;
use Tourze\CommentBundle\Repository\CommentVoteRepository;

class CommentVoteRepositoryTest extends TestCase
{
    private CommentVoteRepository $repository;
    private EntityManagerInterface $entityManager;
    private ManagerRegistry $registry;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->registry = $this->createMock(ManagerRegistry::class);

        $metadata = new ClassMetadata(CommentVote::class);
        $this->entityManager->expects($this->any())
            ->method('getClassMetadata')
            ->willReturn($metadata);

        $this->registry->expects($this->any())
            ->method('getManagerForClass')
            ->with(CommentVote::class)
            ->willReturn($this->entityManager);

        $this->repository = new CommentVoteRepository($this->registry);
    }

    public function test_construct_initializesRepository(): void
    {
        $this->assertInstanceOf(CommentVoteRepository::class, $this->repository);
    }
}