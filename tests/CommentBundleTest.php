<?php

declare(strict_types=1);

namespace Tourze\CommentBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommentBundle\CommentBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(CommentBundle::class)]
#[RunTestsInSeparateProcesses]
final class CommentBundleTest extends AbstractBundleTestCase
{
}
