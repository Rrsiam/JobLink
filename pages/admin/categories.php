<?php
require_once __DIR__ . '/../../config.php';
require_role('admin');

$db = getDB();

// Handle add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    if ($name) {
        $db->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$name]);
        header('Location: ?role=admin&page=categories&success=Category added');
        exit;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$_GET['delete']]);
    header('Location: ?role=admin&page=categories&success=Category deleted');
    exit;
}

// Handle toggle status
if (isset($_GET['toggle'])) {
    $cat = $db->prepare("SELECT status FROM categories WHERE id = ?")->execute([$_GET['toggle']])->fetch();
    if ($cat) {
        $new_status = $cat['status'] === 'active' ? 'inactive' : 'active';
        $db->prepare("UPDATE categories SET status = ? WHERE id = ?")->execute([$new_status, $_GET['toggle']]);
        header('Location: ?role=admin&page=categories&success=Status updated');
        exit;
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$rows = '';
foreach ($categories as $c) {
    $job_count = $db->prepare("SELECT COUNT(*) FROM jobs WHERE category_id = ?")->execute([$c['id']])->fetchColumn();
    $status_badge = $c['status'] === 'active' ? 'badge-active' : 'badge-closed';
    $rows .= "<tr>
        <td>📂</td>
        <td>{$c['name']}</td>
        <td>{$job_count} jobs</td>
        <td><span class='badge $status_badge'>" . ucfirst($c['status']) . "</span></td>
        <td>
            <a href='?role=admin&page=categories&toggle={$c['id']}' class='btn btn-warning btn-sm'>Toggle</a>
            <a href='?role=admin&page=categories&delete={$c['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Delete this category?\")'>Delete</a>
        </td>
    </tr>";
}

render('admin/categories.html', [
    'total_categories' => count($categories),
    'category_rows' => $rows
]);