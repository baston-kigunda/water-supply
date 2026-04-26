<?php
require_once '../includes/functions.php';
require_once '../config/database.php';
requireLogin();

$reference = isset($_GET['reference']) ? $_GET['reference'] : '';
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$user_id = $_SESSION['user_id'];

if (!$reference) {
    header('Location: user-payments.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h4 class="mb-0">Payment Status</h4>
                    </div>
                    <div class="card-body text-center">
                        <div id="loading">
                            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
                            <p class="mt-3">Waiting for M-Pesa payment confirmation...</p>
                            <p class="text-muted">Please check your phone and enter your PIN to complete payment.</p>
                        </div>
                        
                        <div id="success" style="display:none;">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            <h3 class="mt-3 text-success">Payment Successful!</h3>
                            <p>Your payment has been received.</p>
                            <p><strong>Transaction ID:</strong> <span id="trans_id"></span></p>
                            <p><strong>Amount:</strong> <span id="trans_amount"></span></p>
                            <a href="user-payments.php" class="btn btn-success mt-3">View Payment History</a>
                        </div>
                        
                        <div id="failed" style="display:none;">
                            <i class="bi bi-x-circle-fill text-danger" style="font-size: 4rem;"></i>
                            <h3 class="mt-3 text-danger">Payment Failed</h3>
                            <p id="fail_msg">Payment could not be completed.</p>
                            <a href="user-payments.php?bill_id=<?php echo $bill_id; ?>" class="btn btn-primary mt-3">Try Again</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const reference = '<?php echo $reference; ?>';
        let attempts = 0;
        const maxAttempts = 20; // Check for ~1 minute
        
        function checkStatus() {
            fetch('/water%20supply/api/api-payments.php?action=confirm', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'reference=' + encodeURIComponent(reference)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data.status === 'completed') {
                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('success').style.display = 'block';
                    document.getElementById('trans_id').innerText = data.data.reference;
                    document.getElementById('trans_amount').innerText = 'KES ' + data.data.amount;
                } else if (data.success && data.data.status === 'failed') {
                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('failed').style.display = 'block';
                    document.getElementById('fail_msg').innerText = 'Payment failed. Please try again.';
                } else {
                    attempts++;
                    if (attempts < maxAttempts) {
                        setTimeout(checkStatus, 3000);
                    } else {
                        document.getElementById('loading').style.display = 'none';
                        document.getElementById('failed').style.display = 'block';
                        document.getElementById('fail_msg').innerText = 'Payment timeout. Please check your payment history.';
                    }
                }
            })
            .catch(() => {
                attempts++;
                if (attempts < maxAttempts) setTimeout(checkStatus, 3000);
            });
        }
        
        setTimeout(checkStatus, 3000);
        checkStatus();
    </script>
    
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>