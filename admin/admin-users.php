<?php
// ========================================
// FILE: admin-users.php
// PURPOSE: Manage system users (view, add, edit, suspend)
// ========================================

require_once '../includes/functions.php';
require_once '../config/database.php';

requireAdmin();
$message = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new user
                $username = $_POST['username'];
                $email = $_POST['email'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $full_name = $_POST['full_name'];
                $phone = $_POST['phone'];
                $address = $_POST['address'];
                $role = $_POST['role'];
                
                $stmt = $conn->prepare("
                    INSERT INTO users (username, email, password_hash, full_name, phone_number, address, user_role) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssssss", $username, $email, $password, $full_name, $phone, $address, $role);
                
                if ($stmt->execute()) {
                    $message = "User added successfully!";
                } else {
                    $error = "Error adding user: " . $conn->error;
                }
                break;
            case 'edit':
                // Edit user
                $user_id = $_POST['user_id'];
                $full_name = $_POST['full_name'];
                $email = $_POST['email'];
                $phone = $_POST['phone'];
                $address = $_POST['address'];
                $role = $_POST['role'];
                $status = $_POST['status'];
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET full_name=?, email=?, phone_number=?, address=?, user_role=?, account_status=?
                    WHERE user_id=?
                ");
                $stmt->bind_param("ssssssi", $full_name, $email, $phone, $address, $role, $status, $user_id);
                if ($stmt->execute()) {
                    $message = "User updated successfully!";
                } else {
                    $error = "Error updating user: " . $conn->error;
                }
                break; 
            case 'delete':
                // Delete user (soft delete by setting status to inactive)
                $user_id = $_POST['user_id']; 
                $stmt = $conn->prepare("UPDATE users SET account_status='inactive' WHERE user_id=?");
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $message = "User deactivated successfully!";
                } else {
                    $error = "Error deactivating user: " . $conn->error;
                }
                break;
        }
    }
}
// Get all users
$users = [];
$result = $conn->query("
    SELECT u.*, 
           COUNT(DISTINCT sm.meter_id) as meter_count,
           COUNT(DISTINCT b.bill_id) as bill_count
    FROM users u
    LEFT JOIN smart_meters sm ON u.user_id = sm.user_id
    LEFT JOIN bills b ON u.user_id = b.user_id
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$page_title = "Manage Users";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="admin-dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin-users.php">
                                <i class="bi bi-people"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-meters.php">
                                <i class="bi bi-water"></i> Smart Meters
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-bills.php">
                                <i class="bi bi-receipt"></i> Bills
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-schedules.php">
                                <i class="bi bi-calendar"></i> Water Schedules
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-leaks.php">
                                <i class="bi bi-exclamation-triangle"></i> Leak Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin-reports.php">
                                <i class="bi bi-file-text"></i> Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Users</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="bi bi-person-plus"></i> Add New User
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Users Table -->
                <div class="card dashboard-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="usersTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Full Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Meters</th>
                                        <th>Bills</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['user_id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone_number']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $user['user_role'] == 'admin' ? 'danger' : 
                                                        ($user['user_role'] == 'staff' ? 'warning' : 'info'); 
                                                ?>">
                                                    <?php echo ucfirst($user['user_role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $user['account_status'] == 'active' ? 'success' : 'secondary'; 
                                                ?>">
                                                    <?php echo ucfirst($user['account_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $user['meter_count']; ?></td>
                                            <td><?php echo $user['bill_count']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if ($user['user_role'] != 'admin'): ?>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo $user['full_name']; ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-control" id="role" name="role" required>
                                <option value="consumer">Consumer</option>
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="edit_phone" name="phone">
                        </div>
                        <div class="mb-3">
                            <label for="edit_address" class="form-label">Address</label>
                            <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-control" id="edit_role" name="role" required>
                                <option value="consumer">Consumer</option>
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-control" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Deactivate User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <p>Are you sure you want to deactivate user: <strong id="delete_user_name"></strong>?</p>
                        <p class="text-warning">This will prevent them from logging in, but all their data will be preserved.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Deactivate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#usersTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 10
            });
        });
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_phone').value = user.phone_number || '';
            document.getElementById('edit_address').value = user.address || '';
            document.getElementById('edit_role').value = user.user_role;
            document.getElementById('edit_status').value = user.account_status;  
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        function deleteUser(user_id, user_name) {
            document.getElementById('delete_user_id').value = user_id;
            document.getElementById('delete_user_name').textContent = user_name;
            
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>