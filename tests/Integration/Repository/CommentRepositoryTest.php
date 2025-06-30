<?php

namespace Tourze\CommentBundle\Tests\Integration\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Repository\CommentRepository;

class CommentRepositoryTest extends TestCase
{
    private CommentRepository $repository;
    private EntityManagerInterface $entityManager;
    private ManagerRegistry $registry;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->registry = $this->createMock(ManagerRegistry::class);

        $metadata = new ClassMetadata(Comment::class);
        $this->entityManager->expects($this->any())
            ->method('getClassMetadata')
            ->willReturn($metadata);

        $this->registry->expects($this->any())
            ->method('getManagerForClass')
            ->with(Comment::class)
            ->willReturn($this->entityManager);

        $this->repository = new CommentRepository($this->registry);
    }

    public function test_construct_initializesRepository(): void
    {
        $this->assertInstanceOf(CommentRepository::class, $this->repository);
    }
}