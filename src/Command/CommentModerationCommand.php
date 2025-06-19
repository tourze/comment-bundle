<?php

namespace Tourze\CommentBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\CommentBundle\Service\CommentService;

#[AsCommand(
    name: self::NAME,
    description: '评论审核管理命令'
)]
class CommentModerationCommand extends Command
{
    public const NAME = 'comment:moderation';
    
    public function __construct(
        private readonly CommentService $commentService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('list', 'l', InputOption::VALUE_NONE, '显示待审核评论列表')
            ->addOption('approve', 'a', InputOption::VALUE_REQUIRED, '批准指定ID的评论')
            ->addOption('reject', 'r', InputOption::VALUE_REQUIRED, '拒绝指定ID的评论')
            ->addOption('auto-approve', null, InputOption::VALUE_NONE, '自动批准所有安全的评论')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, '限制显示数量', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('list') !== null) {
            return $this->listPendingComments($io, (int) $input->getOption('limit'));
        }

        if (($approveId = $input->getOption('approve')) !== null) {
            return $this->approveComment($io, (int) $approveId);
        }

        if (($rejectId = $input->getOption('reject')) !== null) {
            return $this->rejectComment($io, (int) $rejectId);
        }

        if ($input->getOption('auto-approve') !== null) {
            return $this->autoApproveComments($io);
        }

        $io->info('使用 --help 查看可用选项');
        return Command::SUCCESS;
    }

    private function listPendingComments(SymfonyStyle $io, int $limit): int
    {
        $comments = $this->commentService->getPendingComments(['limit' => $limit]);

        if (empty($comments)) {
            $io->success('没有待审核的评论');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($comments as $comment) {
            $rows[] = [
                $comment->getId(),
                $comment->getTargetType(),
                $comment->getTargetId(),
                $comment->getAuthorName() ?? '匿名',
                mb_substr($comment->getContent(), 0, 50) . '...',
                $comment->getCreatedAt()->format('Y-m-d H:i:s')
            ];
        }

        $io->table(
            ['ID', '目标类型', '目标ID', '作者', '内容', '创建时间'],
            $rows
        );

        return Command::SUCCESS;
    }

    private function approveComment(SymfonyStyle $io, int $commentId): int
    {
        try {
            $comment = $this->commentService->getCommentById($commentId);
            if ($comment === null) {
                $io->error("评论 ID {$commentId} 不存在");
                return Command::FAILURE;
            }

            $this->commentService->approveComment($comment);
            $io->success("评论 ID {$commentId} 已批准");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("批准评论失败: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function rejectComment(SymfonyStyle $io, int $commentId): int
    {
        try {
            $comment = $this->commentService->getCommentById($commentId);
            if ($comment === null) {
                $io->error("评论 ID {$commentId} 不存在");
                return Command::FAILURE;
            }

            $this->commentService->rejectComment($comment);
            $io->success("评论 ID {$commentId} 已拒绝");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("拒绝评论失败: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function autoApproveComments(SymfonyStyle $io): int
    {
        $comments = $this->commentService->getPendingComments();
        $approvedCount = 0;

        foreach ($comments as $comment) {
            // 这里可以添加自动批准的逻辑
            // 例如检查内容安全性、作者信誉等
            if ($this->shouldAutoApprove($comment)) {
                $this->commentService->approveComment($comment);
                $approvedCount++;
            }
        }

        $io->success("自动批准了 {$approvedCount} 条评论");
        return Command::SUCCESS;
    }

    private function shouldAutoApprove($comment): bool
    {
        // 简单的自动批准逻辑
        // 可以根据实际需求扩展
        return !$comment->isAnonymous() && 
               mb_strlen($comment->getContent()) > 10 && 
               mb_strlen($comment->getContent()) < 1000;
    }
}