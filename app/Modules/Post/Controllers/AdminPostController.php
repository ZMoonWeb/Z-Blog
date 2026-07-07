<?php

declare(strict_types=1);

namespace App\Modules\Post\Controllers;

use App\Modules\Admin\Support\AdminControllerBase;
use App\Modules\Post\Requests\PostRequest;
use App\Modules\Post\Services\AdminPostService;

class AdminPostController extends AdminControllerBase
{
    public function __construct(
        private ?AdminPostService $posts = null,
        private ?PostRequest $postRequest = null
    ) {
        parent::__construct();
        $this->posts ??= new AdminPostService();
        $this->postRequest ??= new PostRequest();
    }

    public function index(): void
    {
        $this->requireLogin();

        $page = $this->paginateAdminList($this->posts->all(), '/admin/posts');

        $this->render('admin/posts/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'posts' => $page['items'],
            'pagination' => $page['pagination'],
            'flash' => $this->pullFlash(),
        ]);
    }

    public function create(): void
    {
        $this->requireLogin();

        $errors = [];
        $post = $this->posts->defaultData();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $post = $this->posts->dataFromRequest($_POST);
            $errors = $this->postRequest->validate($post);

            if (empty($errors)) {
                $created = $this->posts->create($post);
                $createdPost = $created['post'];

                $this->recordAdminActivity('post_create', $this->currentAdmin(), '', 'success', '创建文章《' . (string) $createdPost['title'] . '》。', [
                    'resource_type' => 'post',
                    'resource_id' => (int) $created['id'],
                    'title' => (string) $createdPost['title'],
                    'snapshot' => $this->auditSnapshot($createdPost, $this->postAuditFields()),
                ]);

                $this->flash('文章已创建');
                $this->redirect('/admin/posts');
                return;
            }

            $this->recordValidationFailedActivity('post_create_failed', '创建文章失败：表单校验未通过。', $errors, [
                'resource_type' => 'post',
                'title' => (string) ($post['title'] ?? ''),
            ]);

            if ($this->wantsJson()) {
                $this->jsonValidationFailure('文章创建失败，请检查表单内容', $errors);
                return;
            }
        }

        $this->render('admin/posts/create', [
            'admin' => $_SESSION['admin'] ?? null,
            'post' => $post,
            'categories' => $this->posts->categories(),
            'errors' => $errors,
        ]);
    }

    public function edit(int $id): void
    {
        $this->requireLogin();

        $existingPost = $this->posts->find($id);
        if ($existingPost === null) {
            $this->flash('文章不存在或已被删除', 'error');
            $this->redirect('/admin/posts');
            return;
        }

        $errors = [];
        $post = $existingPost;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $post = array_merge($existingPost, $this->posts->dataFromRequest($_POST));
            $errors = $this->postRequest->validate($post);

            if (empty($errors)) {
                $updatedPost = $this->posts->update($id, $post);
                $changes = $this->auditChanges($existingPost, $updatedPost, $this->postAuditFields());

                $this->recordAdminActivity('post_update', $this->currentAdmin(), '', 'success', '编辑文章《' . (string) ($updatedPost['title'] ?? $post['title']) . '》（' . $this->auditChangeSummary($changes) . '）。', [
                    'resource_type' => 'post',
                    'resource_id' => $id,
                    'title' => (string) ($updatedPost['title'] ?? $post['title']),
                    'changes' => $changes,
                ]);

                $this->flash('文章已更新');
                $this->redirect('/admin/posts');
                return;
            }

            $this->recordValidationFailedActivity('post_update_failed', '编辑文章失败：表单校验未通过。', $errors, [
                'resource_type' => 'post',
                'resource_id' => $id,
                'title' => (string) ($post['title'] ?? $existingPost['title'] ?? ''),
            ]);

            if ($this->wantsJson()) {
                $this->jsonValidationFailure('文章更新失败，请检查表单内容', $errors);
                return;
            }
        }

        $this->render('admin/posts/edit', [
            'admin' => $_SESSION['admin'] ?? null,
            'post' => $post,
            'categories' => $this->posts->categories(),
            'errors' => $errors,
        ]);
    }

    public function delete(int $id): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/posts');
            return;
        }

        $existingPost = $this->posts->find($id);

        if ($this->posts->delete($id)) {
            $this->recordAdminActivity('post_delete', $this->currentAdmin(), '', 'success', '删除文章《' . (string) ($existingPost['title'] ?? ('ID ' . $id)) . '》。', [
                'resource_type' => 'post',
                'resource_id' => $id,
                'title' => (string) ($existingPost['title'] ?? ''),
                'snapshot' => is_array($existingPost) ? $this->auditSnapshot($existingPost, $this->postAuditFields()) : [],
            ]);
            $this->flash('文章已删除');
        } else {
            $this->recordAdminActivity('post_delete_failed', $this->currentAdmin(), '', 'warning', '删除文章失败：ID ' . $id . ' 不存在。', [
                'resource_type' => 'post',
                'resource_id' => $id,
            ]);
            $this->flash('文章不存在或已被删除', 'error');
        }

        $this->redirect('/admin/posts');
    }
}
