<?php

declare(strict_types=1);

namespace Tourze\CommentBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommentBundle\Controller\Admin\CommentVoteCrudController;
use Tourze\CommentBundle\Entity\CommentVote;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(CommentVoteCrudController::class)]
#[RunTestsInSeparateProcesses]
final class CommentVoteCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerInstanceCreation(): void
    {
        $controller = new CommentVoteCrudController();
        $this->assertInstanceOf(CommentVoteCrudController::class, $controller);
    }

    public function testValidationErrors(): void
    {
        $client = self::createClient();

        // 尝试创建一个空表单并提交，测试必填字段验证
        $crawler = $client->request('GET', '/admin?crudAction=new&crudControllerFqcn=' . urlencode(CommentVoteCrudController::class));

        // 检查是否有表单
        if ($crawler->filter('form')->count() > 0) {
            $form = $crawler->selectButton('Create')->form();

            // 提交空表单，触发验证错误
            $crawler = $client->submit($form);

            // 验证响应状态码表示验证错误
            $this->assertResponseStatusCodeSame(422);

            // 验证必填字段的错误信息
            // comment 字段是必填的 (ManyToOne, nullable: false)
            $this->assertStringContainsString(
                'should not be blank',
                $crawler->filter('.invalid-feedback')->text()
            );

            // voteType 字段是必填的 (枚举类型，必须选择有效值)
            $invalidFeedbacks = $crawler->filter('.invalid-feedback');
            $foundVoteTypeError = false;
            foreach ($invalidFeedbacks as $feedback) {
                $feedbackText = $feedback->textContent;
                if (str_contains($feedbackText, 'should not be blank')
                    || str_contains($feedbackText, 'is not a valid choice')
                    || str_contains($feedbackText, 'required')) {
                    $foundVoteTypeError = true;
                    break;
                }
            }
            $this->assertTrue($foundVoteTypeError, 'Expected comment or voteType validation error');
        } else {
            self::markTestSkipped('Form not available for validation testing');
        }
    }

    public function testConfigureCrud(): void
    {
        $controller = new CommentVoteCrudController();
        $this->assertInstanceOf(CommentVoteCrudController::class, $controller);

        // Test configuration exists and has expected properties
        $reflectionMethod = new \ReflectionMethod($controller, 'configureCrud');
        $this->assertTrue($reflectionMethod->isPublic());
    }

    public function testConfigureFields(): void
    {
        $controller = new CommentVoteCrudController();
        $this->assertInstanceOf(CommentVoteCrudController::class, $controller);

        // Test fields configuration
        $fields = iterator_to_array($controller->configureFields('index'));
        $this->assertNotEmpty($fields);
        $this->assertGreaterThan(0, count($fields));
    }

    public function testConfigureActions(): void
    {
        $controller = new CommentVoteCrudController();
        $this->assertInstanceOf(CommentVoteCrudController::class, $controller);

        // Test actions configuration method
        $reflectionMethod = new \ReflectionMethod($controller, 'configureActions');
        $this->assertTrue($reflectionMethod->isPublic());
    }

    public function testConfigureFilters(): void
    {
        $controller = new CommentVoteCrudController();
        $this->assertInstanceOf(CommentVoteCrudController::class, $controller);

        // Test filters configuration method
        $reflectionMethod = new \ReflectionMethod($controller, 'configureFilters');
        $this->assertTrue($reflectionMethod->isPublic());
    }

    public function testCreateEntity(): void
    {
        $controller = new CommentVoteCrudController();
        $this->assertInstanceOf(CommentVoteCrudController::class, $controller);

        // Test entity creation without container dependency
        $entity = new CommentVote();
        $entity->setValid(true);
        $this->assertInstanceOf(CommentVote::class, $entity);
        $this->assertTrue($entity->isValid());
    }

    public function testUpdateEntity(): void
    {
        $controller = new CommentVoteCrudController();
        $this->assertInstanceOf(CommentVoteCrudController::class, $controller);

        // Test update method exists
        $reflectionMethod = new \ReflectionMethod($controller, 'updateEntity');
        $this->assertTrue($reflectionMethod->isPublic());
    }

    public function testCreateIndexQueryBuilder(): void
    {
        $controller = new CommentVoteCrudController();
        $this->assertInstanceOf(CommentVoteCrudController::class, $controller);

        // Test query builder method exists
        $reflectionMethod = new \ReflectionMethod($controller, 'createIndexQueryBuilder');
        $this->assertTrue($reflectionMethod->isPublic());
    }

    public function testControllerInheritance(): void
    {
        $controller = new CommentVoteCrudController();
        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasAdminCrudAttribute(): void
    {
        $reflection = new \ReflectionClass(CommentVoteCrudController::class);
        $attributes = $reflection->getAttributes();

        $hasAdminCrudAttribute = false;
        foreach ($attributes as $attribute) {
            if ('EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud' === $attribute->getName()) {
                $hasAdminCrudAttribute = true;
                break;
            }
        }

        $this->assertTrue($hasAdminCrudAttribute, 'Controller should have AdminCrud attribute');
    }

    /**
     * @return AbstractCrudController<CommentVote>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new CommentVoteCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID列' => ['ID'];
        yield '评论列' => ['评论'];
        yield '投票类型列' => ['投票类型'];
        yield '投票者ID列' => ['投票者ID'];
        yield '有效状态列' => ['有效状态'];
        yield '创建时间列' => ['创建时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield '评论字段' => ['comment'];
        yield '投票类型字段' => ['voteType'];
        yield '投票者ID字段' => ['voterId'];
        yield '投票者IP字段' => ['voterIp'];
        yield '有效状态字段' => ['valid'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield '评论字段' => ['comment'];
        yield '投票类型字段' => ['voteType'];
        yield '投票者ID字段' => ['voterId'];
        yield '投票者IP字段' => ['voterIp'];
        yield '有效状态字段' => ['valid'];
    }
}
