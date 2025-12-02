<?php
include 'db.php';

$soldResult = $conn->query("SELECT c.customer_name, c.project_name, c.appointment_date, c.bonus_amount, u.user_name
                            FROM call_log c
                            LEFT JOIN manageuser u ON c.user_id = u.id
                            WHERE c.visit_status='Sold'
                            ORDER BY c.appointment_date DESC");
?>
<table class="table table-bordered table-striped text-center">
    <thead class="table-warning">
        <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Project</th>
            <th>Date</th>
            <th>Assigned User</th>
            <th>Bonus (Rs.)</th>
        </tr>
    </thead>
    <tbody>
        <?php $s=1; $totalSoldBonus=0; while($sold=$soldResult->fetch_assoc()): ?>
        <tr>
            <td><?= $s++ ?></td>
            <td><?= htmlspecialchars($sold['customer_name']) ?></td>
            <td><?= htmlspecialchars($sold['project_name']) ?></td>
            <td><?= htmlspecialchars($sold['appointment_date']) ?></td>
            <td><?= htmlspecialchars($sold['user_name']) ?></td>
            <td><?= number_format($sold['bonus_amount'],2) ?></td>
        </tr>
        <?php $totalSoldBonus += $sold['bonus_amount']; endwhile; ?>
        <tr class="fw-bold">
            <td colspan="5">Total Bonus</td>
            <td>Rs. <?= number_format($totalSoldBonus,2) ?></td>
        </tr>
    </tbody>
</table>
