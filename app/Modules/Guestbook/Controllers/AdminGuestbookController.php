<?php

declare(strict_types=1);

namespace App\Modules\Guestbook\Controllers;

use App\Models\GuestbookMessage;
use App\Modules\Admin\Support\AdminControllerBase;
use App\Modules\Guestbook\Requests\GuestbookRequest;
use App\Modules\Guestbook\Services\GuestbookService;

class AdminGuestbookController extends AdminControllerBase
{
    public function __construct(
        private ?GuestbookService $guestbook = null,
        private ?GuestbookRequest $guestbookRequest = null
    ) {
        parent::__construct();
        $this->guestbook ??= new GuestbookService();
        $this->guestbookRequest ??= new GuestbookRequest();
    }

    public function index(): void
    {
        $this->requireLogin();
        $this->guestbook->createTable();

        $page = $this->paginateAdminList($this->guestbook->all(), '/admin/guestbook');

        $this->render('admin/guestbook/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'messages' => $page['items'],
            'pagination' => $page['pagination'],
            'stats' => $this->guestbook->adminStats(),
            'flash' => $this->pullFlash(),
        ]);
    }

    public function moderate(int $id, string $action): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/guestbook');
            return;
        }

        $message = $this->guestbook->find($id);
        if ($message === null) {
            $this->recordAdminActivity('guestbook_action_failed', $this->currentAdmin(), '', 'warning', '留言操作失败：留言 #' . $id . ' 不存在。', [
                'resource_type' => 'guestbook_message',
                'resource_id' => $id,
                'requested_action' => $action,
            ]);
            $this->flash('留言不存在', 'error');
            $this->redirect('/admin/guestbook');
            return;
        }

        switch ($action) {
            case 'approve':
                $this->guestbook->updateStatus($id, GuestbookMessage::STATUS_APPROVED);
                $this->recordGuestbookActivity('guestbook_approve', $message, $this->guestbook->find($id) ?? $message, '通过留言 #' . $id . '。');
                $this->flash('留言已通过');
                break;
            case 'hide':
                $this->guestbook->updateStatus($id, GuestbookMessage::STATUS_HIDDEN);
                $this->recordGuestbookActivity('guestbook_hide', $message, $this->guestbook->find($id) ?? $message, '隐藏留言 #' . $id . '。');
                $this->flash('留言已隐藏');
                break;
            case 'delete':
                if ($this->guestbook->delete($id)) {
                    $this->recordGuestbookActivity('guestbook_delete', $message, $this->guestbook->find($id) ?? array_merge($message, ['is_deleted' => 1]), '删除留言 #' . $id . '。');
                } else {
                    $this->recordAdminActivity('guestbook_delete_failed', $this->currentAdmin(), '', 'warning', '删除留言失败：留言 #' . $id . ' 已被删除或不存在。', [
                        'resource_type' => 'guestbook_message',
                        'resource_id' => $id,
                        'snapshot' => $this->auditSnapshot($message, $this->guestbookAuditFields()),
                    ]);
                }
                $this->flash('留言已删除');
                break;
            case 'restore':
                if ($this->guestbook->restore($id)) {
                    $this->recordGuestbookActivity('guestbook_restore', $message, $this->guestbook->find($id) ?? $message, '恢复留言 #' . $id . '。');
                    $this->flash('留言已恢复');
                } else {
                    $this->recordAdminActivity('guestbook_restore_failed', $this->currentAdmin(), '', 'warning', '恢复留言失败：留言 #' . $id . ' 未被删除或已恢复。', [
                        'resource_type' => 'guestbook_message',
                        'resource_id' => $id,
                    ]);
                    $this->flash('留言未被删除或已恢复', 'error');
                }
                break;
            default:
                $this->recordAdminActivity('guestbook_action_unknown', $this->currentAdmin(), '', 'warning', '未知留言操作：' . $action . '。', [
                    'resource_type' => 'guestbook_message',
                    'resource_id' => $id,
                    'requested_action' => $action,
                ]);
                $this->flash('未知操作', 'error');
        }

        $this->redirect('/admin/guestbook');
    }

    public function reply(int $id): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/guestbook');
            return;
        }

        $message = $this->guestbook->find($id);
        if ($message === null) {
            $this->recordAdminActivity('guestbook_reply_update_failed', $this->currentAdmin(), '', 'warning', '更新留言回复失败：留言 #' . $id . ' 不存在。', [
                'resource_type' => 'guestbook_message',
                'resource_id' => $id,
            ]);
            $this->flash('留言不存在', 'error');
            $this->redirect('/admin/guestbook');
            return;
        }

        $reply = trim((string) ($_POST['admin_reply'] ?? ''));
        $errors = $this->guestbookRequest->validate(['admin_reply' => $reply], 'reply');
        if (!empty($errors)) {
            $this->recordValidationFailedActivity('guestbook_reply_update_failed', '更新留言回复失败：表单校验未通过。', $errors, [
                'resource_type' => 'guestbook_message',
                'resource_id' => $id,
                'nickname' => (string) ($message['nickname'] ?? ''),
            ]);
            $this->flash((string) reset($errors), 'error');
            $this->redirect('/admin/guestbook');
            return;
        }

        $this->guestbook->updateReply($id, $reply);
        $this->recordGuestbookActivity('guestbook_reply_update', $message, $this->guestbook->find($id) ?? $message, ($reply !== '' ? '保存留言 #' . $id . ' 的站长回复。' : '清空留言 #' . $id . ' 的站长回复。'));
        $this->flash($reply !== '' ? '站长回复已保存' : '站长回复已清空');
        $this->redirect('/admin/guestbook');
    }
}
