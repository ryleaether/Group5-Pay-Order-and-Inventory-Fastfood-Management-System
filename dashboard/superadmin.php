<?php
session_start();
require_once "validation.php";

/* SECURITY CHECK */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: login.php");
    exit;
}

$val = new Validation();

/* HANDLE DELETE ADMIN*/
if (isset($_GET['delete'])) {
    $val->deleteAdmin($_GET['delete']);
    header("Location: superadmin.php");
    exit;
}

/* HANDLE UPDATE MAX DEVICES */
if (isset($_POST['update_devices'])) {
    $val->updateMaxDevices($_POST['admin_id'], $_POST['max_devices']);
    header("Location: superadmin.php");
    exit;
}

/* 
   SYSTEM STATS
 */
$total_admins = $val->countAdmins();
$total_active_devices = $val->countActiveSessions();

/* ALL ADMINS */
$admins = $val->getAllOwners();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Super Admin Dashboard</title>
    <link rel="stylesheet" href="../design/superadmin.css">
</head>

<body>

<h1>Welcome back, Your Grace! 👑</h1>

<!-- 
     LIVE STATS
 -->
<div class="topbar">

    <div class="card">
        <h2>Total Fastfood Owners</h2>
        <p><?= $total_admins ?></p>
    </div>

    <div class="card">
        <h2>Active Logged-in Devices</h2>
        <p><?= $total_active_devices ?></p>
    </div>

</div>

<!-- 
     ADMIN TABLE
 -->
<h2 style="text-align:center;">All Fastfood Owners</h2>

<table>
    <tr>
        <th>Username</th>
        <th>Email</th>
        <th>Fastfood</th>
        <th>Devices</th>
        <th>Max Devices</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>

    <?php foreach ($admins as $admin): ?>

        <?php
            $isOnline = $val->isAdminOnline($admin['admin_id']);
            $deviceCount = $val->countDevices($admin['admin_id']);
        ?>

        <tr>
            <td><?= $admin['username'] ?></td>
            <td><?= $admin['email'] ?></td>
            <td><?= $admin['fastfood_name'] ?></td>

            <!-- CURRENT DEVICES -->
            <td><?= $deviceCount ?></td>

            <!-- MAX DEVICES CONTROL -->
            <td>
                <form method="POST">
                    <input type="hidden" name="admin_id" value="<?= $admin['admin_id'] ?>">
                    <input type="number" name="max_devices" value="<?= $admin['max_devices'] ?? 1 ?>" min="1" style="width:60px;">
                    <button name="update_devices">Save</button>
                </form>
            </td>

            <!-- STATUS -->
            <td class="<?= $isOnline ? 'online' : 'offline' ?>">
                <?= $isOnline ? 'ONLINE' : 'OFFLINE' ?>
            </td>

            <!-- ACTIONS -->
            <td>
                        <button class="delete-btn open-delete-modal"
                data-id="<?= $admin['admin_id'] ?>">
            Delete
        </button>
            </td>
        </tr>

    <?php endforeach; ?>

</table>

<a class="logout-btn" href="logout.php">Logout</a>

<!-- DELETE MODAL -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <h3>⚠ Delete Admin</h3>
        <p>Are you sure you want to delete this account?</p>

        <div class="modal-actions">
            <button id="confirmDelete" class="confirm-btn">Yes, Delete</button>
            <button id="cancelDelete" class="cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<script>
let deleteId = null;

const modal = document.getElementById("deleteModal");
const confirmBtn = document.getElementById("confirmDelete");
const cancelBtn = document.getElementById("cancelDelete");

// OPEN MODAL
document.querySelectorAll(".open-delete-modal").forEach(btn => {
    btn.addEventListener("click", () => {
        deleteId = btn.dataset.id;
        modal.classList.add("show");
    });
});

// CONFIRM DELETE
confirmBtn.addEventListener("click", () => {

    confirmBtn.innerText = "Deleting...";
    confirmBtn.disabled = true;

    window.location.href = "superadmin.php?delete=" + deleteId;
});

// CANCEL
cancelBtn.addEventListener("click", () => {
    modal.classList.remove("show");
    deleteId = null;
});

// CLICK OUTSIDE CLOSE
modal.addEventListener("click", (e) => {
    if (e.target === modal) {
        modal.classList.remove("show");
    }
});
</script>

</body>
</html>