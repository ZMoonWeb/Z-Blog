<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Controllers;

use App\Modules\Admin\Support\AdminControllerBase;
use App\Modules\Announcement\Requests\AnnouncementRequest;
use App\Modules\Announcement\Services\AnnouncementService;

class AdminAnnouncementController extends AdminControllerBase
{
    public function __construct(
        private ?AnnouncementService $announcements = null,
        private ?AnnouncementRequest $announcementRequest = null
    ) {
        parent::__construct();
        $this->announcements ??= new AnnouncementService();
        $this->announcementRequest ??= new AnnouncementRequest();
    }

    public function index(): void
    {
        $this->requireLogin();
        $this->announcements->seedDefaults();

        $page = $this->paginateAdminList($this->announcements->all(), '/admin/announcements');

        $this->render('admin/announcements/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'announcements' => $page['items'],
            'pagination' => $page['pagination'],
            'flash' => $this->pullFlash(),
        ]);
    }

    public function create(): void
    {
        $this->requireLogin();
        $this->announcements->seedDefaults();

        $errors = [];
        $announcement = $this->announcements->defaultData();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $announcement = $this->announcements->dataFromRequest($_POST);
            $errors = $this->announcementRequest->validate($announcement);

            if (empty($errors)) {
                $announcementId = $this->announcements->create($announcement);

                $this->recordAdminActivity('announcement_create', $this->currentAdmin(), '', 'success', '创建公告 #' . $announcementId . '。', [
                    'resource_type' => 'announcement',
                    'resource_id' => $announcementId,
                    'snapshot' => $this->auditSnapshot($announcement, $this->announcementAuditFields()),
                ]);

                $this->flash('公告已创建');
                $this->redirect('/admin/announcements');
                return;
            }

            $this->recordValidationFailedActivity('announcement_create_failed', '创建公告失败：表单校验未通过。', $errors, [
                'resource_type' => 'announcement',
            ]);

            if ($this->wantsJson()) {
                $this->jsonValidationFailure('公告创建失败，请检查表单内容', $errors);
                return;
            }
        }

        $this->render('admin/announcements/form', [
            'admin' => $_SESSION['admin'] ?? null,
            'announcement' => $announcement,
            'errors' => $errors,
            'mode' => 'create',
        ]);
    }

    public function edit(int $id): void
    {
        $this->requireLogin();
        $this->announcements->seedDefaults();

        $existing = $this->announcements->find($id);
        if ($existing === null) {
            $this->flash('公告不存在或已被删除', 'error');
            $this->redirect('/admin/announcements');
            return;
        }

        $errors = [];
        $announcement = $existing;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $announcement = array_merge($existing, $this->announcements->dataFromRequest($_POST));
            $errors = $this->announcementRequest->validate($announcement);

            if (empty($errors)) {
                $updatedAnnouncement = $this->announcements->update($id, $announcement);
                $changes = $this->auditChanges($existing, $updatedAnnouncement, $this->announcementAuditFields());

                $this->recordAdminActivity('announcement_update', $this->currentAdmin(), '', 'success', '编辑公告 #' . $id . '（' . $this->auditChangeSummary($changes) . '）。', [
                    'resource_type' => 'announcement',
                    'resource_id' => $id,
                    'changes' => $changes,
                ]);

                $this->flash('公告已更新');
                $this->redirect('/admin/announcements');
                return;
            }

            $this->recordValidationFailedActivity('announcement_update_failed', '编辑公告失败：表单校验未通过。', $errors, [
                'resource_type' => 'announcement',
                'resource_id' => $id,
            ]);

            if ($this->wantsJson()) {
                $this->jsonValidationFailure('公告更新失败，请检查表单内容', $errors);
                return;
            }
        }

        $this->render('admin/announcements/form', [
            'admin' => $_SESSION['admin'] ?? null,
            'announcement' => $announcement,
            'errors' => $errors,
            'mode' => 'edit',
        ]);
    }

    public function delete(int $id): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/announcements');
            return;
        }

        $existing = $this->announcements->find($id);

        if ($this->announcements->delete($id)) {
            $this->recordAdminActivity('announcement_delete', $this->currentAdmin(), '', 'success', '删除公告 #' . $id . '。', [
                'resource_type' => 'announcement',
                'resource_id' => $id,
                'snapshot' => is_array($existing) ? $this->auditSnapshot($existing, $this->announcementAuditFields()) : [],
            ]);
            $this->flash('公告已删除');
        } else {
            $this->recordAdminActivity('announcement_delete_failed', $this->currentAdmin(), '', 'warning', '删除公告失败：ID ' . $id . ' 不存在。', [
                'resource_type' => 'announcement',
                'resource_id' => $id,
            ]);
            $this->flash('公告不存在或已被删除', 'error');
        }

        $this->redirect('/admin/announcements');
    }
}
