<?php
include 'db.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    echo "<div class='alert alert-danger text-center'>Database connection failed.</div>";
    exit;
}

if (isset($_GET['user_id'])) {
    $id = (int)$_GET['user_id'];
    $stmt = $conn->prepare("SELECT * FROM manageuser WHERE id = ?");
    if (!$stmt) {
        echo "<div class='alert alert-danger text-center'>Query preparation failed: " . htmlspecialchars($conn->error) . "</div>";
        exit;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $username = htmlspecialchars($row['user_name'] ?? 'N/A');
        $email = htmlspecialchars($row['email'] ?? 'N/A');
        $role = htmlspecialchars($row['user_role'] ?? 'User');
        $created = htmlspecialchars($row['created_at'] ?? 'N/A');
        
        echo "
        <div class='p-2'>
            <div class='text-center mb-3'>
                <i class='bi bi-person-circle fs-1 text-primary'></i>
                <h5 class='mt-2'>{$username}</h5>
                <p class='text-muted'>{$email}</p>
            </div>
            <ul class='list-group'>
                <li class='list-group-item'><strong>User ID:</strong> {$row['id']}</li>
                <li class='list-group-item'><strong>Name:</strong> {$username}</li>
                <li class='list-group-item'><strong>Email:</strong> {$email}</li>
                <li class='list-group-item'><strong>Role:</strong> {$role}</li>
                <li class='list-group-item'><strong>Created On:</strong> {$created}</li>
            </ul>
        </div>";
    } else {
        echo "<div class='alert alert-warning text-center'>User not found.</div>";
    }
    $stmt->close();
} else {
    echo "<div class='alert alert-danger text-center'>Invalid request.</div>";
}
?>
