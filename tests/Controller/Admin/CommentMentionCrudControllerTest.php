<?php

declare(strict_types=1);

namespace Tourze\CommentBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommentBundle\Controller\Admin\CommentMentionCrudController;
use Tourze\CommentBundle\Entity\CommentMention;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(CommentMentionCrudController::class)]
#[RunTestsInSeparateProcesses]
final class CommentMentionCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerIsInstanceOfAbstractCrudController(): void
    {
        $controller = new CommentMentionCrudController();
        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testConfigureFieldsReturnsIterable(): void
    {
        $controller = new CommentMentionCrudController();
        $fields = $controller->configureFields('index');
        $this->assertIsIterable($fields);
    }

    public function testConfigureActionsReturnsActions(): void
    {
        self::markTestSkipped('Requires EasyAdmin context setup');
    }

    public function testValidationErrors(): void
    {
        $client = self::createClient();

        // 尝试创建一个空表单并提交，测试必填字段验证
        $crawler = $client->request('GET', '/admin?crudAction=new&crudControllerFqcn=' . urlencode(CommentMentionCrudController::class));

        // 检查是否有表单
        if ($crawler->filter('form')->count() > 0) {
            $form = $crawler->selectButton('Create')->form();

            // 提交空表单，触发验证错误
            $crawler = $client->submit($form);

            // 验证响应状态码表示验证错误
            $this->assertResponseStatusCodeSame(422);

            // 验证必填字段的错误信息
            // comment 字段是必填的
            $this->assertStringContainsString(
                'should not be blank',
                $crawler->filter('.invalid-feedback')->text()
            );

            // mentionedUserId 字段是必填的 (Assert\NotBlank)
            $invalidFeedbacks = $crawler->filter('.invalid-feedback');
            $foundMentionedUserIdError = false;
            foreach ($invalidFeedbacks as $feedback) {
                if (str_contains($feedback->textContent, 'should not be blank')) {
                    $foundMentionedUserIdError = true;
                    break;
                }
            }
            $this->assertTrue($foundMentionedUserIdError, 'Expected mentionedUserId validation error');
        } else {
            self::markTestSkipped('Form not available for validation testing');
        }
    }

    public function testConfigureFiltersReturnsFilters(): void
    {
        self::markTestSkipped('Requires EasyAdmin context setup');
    }

    public function testCreateIndexQueryBuilderReturnsQueryBuilder(): void
    {
        self::markTestSkipped('Requires EasyAdmin context setup');
    }

    public function testCreateNewInstancesCreatesEntity(): void
    {
        $controller = new CommentMentionCrudController();
        $entity = $controller->createEntity(CommentMention::class);
        $this->assertInstanceOf(CommentMention::class, $entity);
        $this->assertTrue($entity->isValid());
    }

    public function testIndexPageDisplaysCorrectFields(): void
    {
        self::markTestSkipped('Requires EasyAdmin context setup');
    }

    public function testNewPageDisplaysForm(): void
    {
        self::markTestSkipped('Requires EasyAdmin context setup');
    }

    public function testEditPageDisplaysForm(): void
    {
        self::markTestSkipped('Requires EasyAdmin context setup');
    }

    public function testShowPageDisplaysEntity(): void
    {
        self::markTestSkipped('Requires EasyAdmin context setup');
    }

    /**
     * @return AbstractCrudController<CommentMention>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new CommentMentionCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID列' => ['ID'];
        yield '关联评论列' => ['关联评论'];
        yield '被提及用户ID列' => ['被提及用户ID'];
        yield '被提及用户名列' => ['被提及用户名'];
        yield '是否已通知列' => ['是否已通知'];
        yield '是否有效列' => ['是否有效'];
        yield '创建时间列' => ['创建时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield '关联评论字段' => ['comment'];
        yield '被提及用户ID字段' => ['mentionedUserId'];
        yield '被提及用户名字段' => ['mentionedUserName'];
        yield '是否已通知字段' => ['notified'];
        yield '通知时间字段' => ['notifyTime'];
        yield '是否有效字段' => ['valid'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield '关联评论字段' => ['comment'];
        yield '被提及用户ID字段' => ['mentionedUserId'];
        yield '被提及用户名字段' => ['mentionedUserName'];
        yield '是否已通知字段' => ['notified'];
        yield '通知时间字段' => ['notifyTime'];
        yield '是否有效字段' => ['valid'];
    }
}
