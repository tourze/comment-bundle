<?php

declare(strict_types=1);

namespace Tourze\CommentBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\CommentBundle\Entity\CommentVote;
use Tourze\CommentBundle\Enum\VoteType;

/**
 * 评论投票管理控制器
 *
 * @extends AbstractCrudController<CommentVote>
 */
#[AdminCrud(
    routePath: '/comment/vote',
    routeName: 'comment_vote',
)]
final class CommentVoteCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CommentVote::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('评论投票')
            ->setEntityLabelInPlural('评论投票列表')
            ->setPageTitle('index', '评论投票列表')
            ->setPageTitle('new', '新建投票记录')
            ->setPageTitle('edit', '编辑投票记录')
            ->setPageTitle('detail', '投票记录详情')
            ->setHelp('index', '管理用户对评论的投票记录（点赞/踩）')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['voterId', 'voterIp'])
            ->setPaginatorPageSize(20)
            ->showEntityActionsInlined()
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->hideOnForm();
        yield $this->createCommentAssociationField();
        yield $this->createVoteTypeField();
        yield $this->createVoterIdField();
        yield $this->createVoterIpField();
        yield $this->createValidField();
        yield $this->createCreateTimeField();
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions->add(Crud::PAGE_INDEX, Action::DETAIL);

        // 安全地更新 NEW action
        try {
            $actions->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('添加投票记录');
            });
        } catch (\InvalidArgumentException) {
            $actions->add(Crud::PAGE_INDEX, Action::NEW)
                ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                    return $action->setLabel('添加投票记录');
                })
            ;
        }

        // 安全地更新 EDIT action
        try {
            $actions->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setLabel('编辑');
            });
        } catch (\InvalidArgumentException) {
            $actions->add(Crud::PAGE_INDEX, Action::EDIT)
                ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                    return $action->setLabel('编辑');
                })
            ;
        }

        // 安全地更新 DELETE action
        try {
            $actions->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setLabel('删除');
            });
        } catch (\InvalidArgumentException) {
            $actions->add(Crud::PAGE_INDEX, Action::DELETE)
                ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                    return $action->setLabel('删除');
                })
            ;
        }

        $actions->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
            return $action->setLabel('查看详情');
        });

        $actions->reorder(Crud::PAGE_INDEX, [
            Action::DETAIL,
            Action::EDIT,
            Action::DELETE,
        ]);

        $actions->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');

        return $actions;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('comment', '评论'))
            ->add(TextFilter::new('voteType', '投票类型'))
            ->add(TextFilter::new('voterId', '投票者ID'))
            ->add(TextFilter::new('voterIp', '投票者IP'))
            ->add(BooleanFilter::new('valid', '有效状态'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }

    private function createCommentAssociationField(): AssociationField
    {
        return AssociationField::new('comment', '评论')
            ->setRequired(true)
            ->setHelp('选择要投票的评论')
            ->formatValue(function ($value, $entity) {
                return $this->formatCommentValue($value);
            })
        ;
    }

    private function formatCommentValue(mixed $value): string
    {
        if (null === $value || !is_object($value) || !method_exists($value, 'getContent')) {
            return '-';
        }

        $content = $value->getContent();
        if (!$content) {
            $id = $this->getEntityIdString($value);

            return sprintf('#%s (无内容)', $id);
        }

        $contentString = $this->normalizeToString($content);
        $shortContent = mb_strlen($contentString) > 50 ? mb_substr($contentString, 0, 50) . '...' : $contentString;
        $id = $this->getEntityIdString($value);

        return sprintf('#%s %s', $id, $shortContent);
    }

    private function getEntityIdString(mixed $entity): string
    {
        if (!is_object($entity) || !method_exists($entity, 'getId')) {
            return 'unknown';
        }

        $id = $entity->getId();
        if (null === $id) {
            return 'unknown';
        }

        if (is_string($id)) {
            return $id;
        }

        if (is_numeric($id)) {
            return (string) $id;
        }

        if (is_object($id) && method_exists($id, '__toString')) {
            return (string) $id;
        }

        return 'unknown';
    }

    private function normalizeToString(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }
        if (is_numeric($content)) {
            return (string) $content;
        }
        if (is_object($content) && method_exists($content, '__toString')) {
            return (string) $content;
        }

        return '';
    }

    private function createVoteTypeField(): ChoiceField
    {
        return ChoiceField::new('voteType', '投票类型')
            ->setRequired(true)
            ->setHelp('选择投票类型：点赞或踩')
            ->setChoices([
                VoteType::LIKE->getLabel() => VoteType::LIKE,
                VoteType::DISLIKE->getLabel() => VoteType::DISLIKE,
            ])
            ->renderAsBadges([
                VoteType::LIKE->value => 'success',
                VoteType::DISLIKE->value => 'danger',
            ])
        ;
    }

    private function createVoterIdField(): TextField
    {
        return TextField::new('voterId', '投票者ID')
            ->setMaxLength(100)
            ->setRequired(false)
            ->setHelp('投票者的用户ID，为空表示匿名投票')
            ->formatValue(fn ($value) => $value ?? '匿名用户')
        ;
    }

    private function createVoterIpField(): TextField
    {
        return TextField::new('voterIp', '投票者IP')
            ->setMaxLength(45)
            ->setRequired(false)
            ->setHelp('投票者的IP地址')
            ->hideOnIndex()
            ->formatValue(fn ($value) => $value ?? '-')
        ;
    }

    private function createValidField(): BooleanField
    {
        return BooleanField::new('valid', '有效状态')
            ->setRequired(true)
            ->setHelp('投票是否有效')
            ->renderAsSwitch(false)
        ;
    }

    private function createCreateTimeField(): DateTimeField
    {
        return DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
            ->setHelp('投票记录的创建时间')
            ->formatValue(function ($value) {
                if (!$value || !is_object($value) || !method_exists($value, 'format')) {
                    return '-';
                }

                return $value->format('Y-m-d H:i:s');
            })
        ;
    }

    public function createEntity(string $entityFqcn): CommentVote
    {
        $vote = new CommentVote();

        // 设置默认值
        $vote->setValid(true);

        // 尝试从请求中获取用户信息
        $user = $this->getUser();
        if (null !== $user) {
            $vote->setVoterId($user->getUserIdentifier());
        }

        return $vote;
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        // 在更新前可以添加业务逻辑
        if ($entityInstance instanceof CommentVote) {
            // 例如：记录投票变更日志等
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->select('entity', 'comment')
            ->leftJoin('entity.comment', 'comment')
            ->orderBy('entity.createTime', 'DESC')
        ;
    }
}
