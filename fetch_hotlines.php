<?php
include 'db.php';

$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM hotlines WHERE 1";

if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND (hotline_name LIKE '%$search%' OR hotline_number LIKE '%$search%')";
}

$sql .= " ORDER BY hotline_name ASC";
$result = $conn->query($sql);

if ($result->num_rows > 0): ?>
<table class="table table-bordered table-hover align-middle">
    <thead class="table-primary">
        <tr>
            <th>#</th>
            <th>Hotline Name</th>
            <th>Hotline Number</th>
            <th>Status</th>
            <th>Created Date</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php $i=1; while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($row['hotline_name']) ?></td>
            <td><?= htmlspecialchars($row['hotline_number']) ?></td>
            <td>
                <span class="badge <?= $row['status']=='active' ? 'bg-success' : 'bg-danger' ?>">
                    <?= ucfirst($row['status']) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($row['created_at']) ?></td>
            <td>
                <a href="edit_hotline.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square"></i></a>
                <a href="delete_hotline.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')"><i class="bi bi-trash"></i></a>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php else: ?>
<div class="alert alert-warning text-center">No hotlines found.</div>
<?php endif; ?>
