<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

// Handle add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        redirect_with_flash('?role=admin&page=categories', 'Category name is required.', 'error');
    }
    $exists = $db->prepare("SELECT id FROM categories WHERE name = ?")->execute([$name])->fetchColumn();
    if ($exists) {
        redirect_with_flash('?role=admin&page=categories', 'That category already exists.', 'error');
    }
    $db->prepare("INSERT INTO categories (name, status) VALUES (?, 'active')")->execute([$name]);
    redirect_with_flash('?role=admin&page=categories', 'Category added.');
}

// Handle delete
if (isset($_GET['delete'])) {
    $cat_id = (int)$_GET['delete'];
    $in_use = (int)$db->prepare("SELECT COUNT(*) FROM jobs WHERE category_id = ?")->execute([$cat_id])->fetchColumn();
    if ($in_use > 0) {
        redirect_with_flash(
            '?role=admin&page=categories',
            "That category still has {$in_use} job(s). Reassign or delete those jobs first.",
            'error'
        );
    }
    $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$cat_id]);
    redirect_with_flash('?role=admin&page=categories', 'Category deleted.');
}

// Handle toggle status
if (isset($_GET['toggle'])) {
    $cat_id = (int)$_GET['toggle'];
    $cat = $db->prepare("SELECT status FROM categories WHERE id = ?")->execute([$cat_id])->fetch();
    if ($cat) {
        $new_status = $cat['status'] === 'active' ? 'inactive' : 'active';
        $db->prepare("UPDATE categories SET status = ? WHERE id = ?")->execute([$new_status, $cat_id]);
        redirect_with_flash('?role=admin&page=categories', 'Category status updated.');
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$rows = '';
foreach ($categories as $c) {
    $job_count = (int)$db->prepare("SELECT COUNT(*) FROM jobs WHERE category_id = ?")->execute([$c['id']])->fetchColumn();
    $status_badge = $c['status'] === 'active' ? 'badge-active' : 'badge-closed';
    $id = (int)$c['id'];
    $rows .= "<tr>
        <td>📂</td>
        <td>" . htmlspecialchars($c['name']) . "</td>
        <td>{$job_count} jobs</td>
        <td><span class='badge $status_badge'>" . ucfirst($c['status']) . "</span></td>
        <td>
            <a href='?role=admin&page=categories&amp;toggle=$id' class='btn btn-warning btn-sm'>Toggle</a>
            <a href='?role=admin&page=categories&amp;delete=$id' class='btn btn-danger btn-sm' onclick='return confirm(\"Delete this category?\")'>Delete</a>
        </td>
    </tr>";
}
if (!$categories) {
    $rows = "<tr><td colspan='5' class='text-muted'>No categories yet.</td></tr>";
}

render('admin/categories.html', [
    'total_categories' => count($categories),
    'category_rows' => new RawHtml($rows),
    'notification' => flash_message()
]);