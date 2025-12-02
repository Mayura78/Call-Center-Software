<?php
include 'db.php';

$sql = "SELECT * FROM appointments ORDER BY id DESC";
$res = $conn->query($sql);
$i = 1;

?>
<table class="table table-bordered">
    <thead>
        <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Number</th>
            <th>Project</th>
            <th>Reason</th>
            <th>Date</th>
            <th>Time</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>

<?php
if ($res->num_rows > 0) {
    while($row = $res->fetch_assoc()) {
?>

<tr>
    <td><?php echo $i++; ?></td>
    <td><?php echo $row['customer_name']; ?></td>
    <td><?php echo $row['number_called']; ?></td>
    <td><?php echo $row['project_name']; ?></td>
    <td><?php echo $row['appointment_reason']; ?></td>
    <td><?php echo $row['confirm_date']; ?></td>
    <td><?php echo $row['confirm_time']; ?></td>

    <td>
        <?php if ($row['status'] == "Completed") { ?>
            <span class="badge bg-success p-2">Completed</span>
        <?php } else if ($row['status'] == "Cancelled") { ?>
            <span class="badge bg-danger p-2">Cancelled</span>
        <?php } else { ?>
            <span class="badge bg-warning text-dark p-2">Pending</span>
        <?php } ?>
    </td>

    <td>
        <button class="btn btn-primary btn-sm"
            onclick="openStatusModal(<?php echo $row['id']; ?>, '<?php echo $row['status']; ?>')">
            Update Status
        </button>
    </td>
</tr>

<?php } } else { ?>

<tr><td colspan="9" class="text-center">No Appointments Found</td></tr>

<?php } ?>

</tbody>
</table>
