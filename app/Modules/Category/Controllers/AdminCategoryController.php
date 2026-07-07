<?php

declare(strict_types=1);

namespace App\Modules\Category\Controllers;

use App\Modules\Admin\Support\AdminControllerBase;
use App\Modules\Category\Requests\CategoryRequest;
use App\Modules\Category\Services\CategoryService;

class AdminCategoryController extends AdminControllerBase
{
    public function __construct(
        private ?CategoryService $categories = null,
        private ?CategoryRequest $categoryRequest = null
    ) {
        parent::__construct();
        $this->categories ??= new CategoryService();
        $this->categoryRequest ??= new CategoryRequest();
    }

    public function index(): void
    {
        $this->requireLogin();

        $page = $this->paginateAdminList($this->categories->allWithPostCount(), '/admin/categories');

        $this->render('admin/categories/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'categories' => $page['items'],
            'pagination' => $page['pagination'],
            'category' => $this->categories->defaultData(),
            'errors' => [],
            'flash' => $this->pullFlash(),
            'mode' => 'create',
        ]);
    }

    public function create(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/categories');
            return;
        }

        $category = $this->categories->dataFromRequest($_POST);
        $errors = $this->categoryRequest->validate($category);

        if (empty($errors)) {
            $created = $this->categories->create($category);

            $this->recordAdminActivity('category_create', $this->currentAdmin(), '', 'success', '创建分类《' . (string) $category['name'] . '》。', [
                'resource_type' => 'category',
                'resource_id' => (int) $created['id'],
                'name' => (string) $category['name'],
                'snapshot' => $this->auditSnapshot(array_merge($category, ['slug' => (string) $created['slug']]), $this->categoryAuditFields()),
            ]);

            $this->flash('分类已创建');
            $this->redirect('/admin/categories');
            return;
        }

        $this->recordValidationFailedActivity('category_create_failed', '创建分类失败：表单校验未通过。', $errors, [
            'resource_type' => 'category',
            'name' => (string) ($category['name'] ?? ''),
        ]);

        if ($this->wantsJson()) {
            $this->jsonValidationFailure('分类创建失败，请检查表单内容', $errors);
            return;
        }

        $this->renderCategoryIndex($category, $errors, 'create');
    }

    public function edit(int $id): void
    {
        $this->requireLogin();

        $existingCategory = $this->categories->find($id);
        if ($existingCategory === null) {
            $this->flash('分类不存在或已被删除', 'error');
            $this->redirect('/admin/categories');
            return;
        }

        $errors = [];
        $category = $existingCategory;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $category = array_merge($existingCategory, $this->categories->dataFromRequest($_POST));
            $errors = $this->categoryRequest->validate($category, $id);

            if (empty($errors)) {
                $updatedCategory = $this->categories->update($id, $category);
                $changes = $this->auditChanges($existingCategory, $updatedCategory, $this->categoryAuditFields());

                $this->recordAdminActivity('category_update', $this->currentAdmin(), '', 'success', '编辑分类《' . (string) ($updatedCategory['name'] ?? $category['name']) . '》（' . $this->auditChangeSummary($changes) . '）。', [
                    'resource_type' => 'category',
                    'resource_id' => $id,
                    'name' => (string) ($updatedCategory['name'] ?? $category['name']),
                    'changes' => $changes,
                ]);

                $this->flash('分类已更新');
                $this->redirect('/admin/categories');
                return;
            }

            $this->recordValidationFailedActivity('category_update_failed', '编辑分类失败：表单校验未通过。', $errors, [
                'resource_type' => 'category',
                'resource_id' => $id,
                'name' => (string) ($category['name'] ?? $existingCategory['name'] ?? ''),
            ]);

            if ($this->wantsJson()) {
                $this->jsonValidationFailure('分类更新失败，请检查表单内容', $errors);
                return;
            }
        }

        $this->renderCategoryIndex($category, $errors, 'edit');
    }

    public function delete(int $id): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/categories');
            return;
        }

        $existingCategory = $this->categories->find($id);
        $fallbackCategoryId = $this->categories->defaultGroupId($id);

        if ($this->categories->delete($id)) {
            $this->recordAdminActivity('category_delete', $this->currentAdmin(), '', 'success', '删除分类《' . (string) ($existingCategory['name'] ?? ('ID ' . $id)) . '》。', [
                'resource_type' => 'category',
                'resource_id' => $id,
                'name' => (string) ($existingCategory['name'] ?? ''),
                'moved_posts_to_category_id' => $fallbackCategoryId,
                'snapshot' => is_array($existingCategory) ? $this->auditSnapshot($existingCategory, $this->categoryAuditFields()) : [],
            ]);
            $this->flash('分类已删除，原分类下文章已设为未分类');
        } else {
            $this->recordAdminActivity('category_delete_failed', $this->currentAdmin(), '', 'warning', '删除分类失败：ID ' . $id . ' 不存在。', [
                'resource_type' => 'category',
                'resource_id' => $id,
            ]);
            $this->flash('分类不存在或已被删除', 'error');
        }

        $this->redirect('/admin/categories');
    }

    private function renderCategoryIndex(array $category, array $errors, string $mode): void
    {
        $page = $this->paginateAdminList($this->categories->allWithPostCount(), '/admin/categories');

        $this->render('admin/categories/index', [
            'admin' => $_SESSION['admin'] ?? null,
            'categories' => $page['items'],
            'pagination' => $page['pagination'],
            'category' => $category,
            'errors' => $errors,
            'flash' => null,
            'mode' => $mode,
        ]);
    }
}
