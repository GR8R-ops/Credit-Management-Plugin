<?php
$vendorCredits = [
    ['credit_id'=>1,'user_id'=>101,'vendor_id'=>201,'vendor_name'=>'YogaOne','service_type'=>'Mat Yoga','balance'=>50,'last_updated'=>'2025-06-28 14:30'],
    ['credit_id'=>2,'user_id'=>102,'vendor_id'=>202,'vendor_name'=>'FlyFit','service_type'=>'Aerial Yoga','balance'=>30,'last_updated'=>'2025-06-27 09:15'],
    ['credit_id'=>3,'user_id'=>103,'vendor_id'=>203,'vendor_name'=>'PowerZen','service_type'=>'Power Yoga','balance'=>75,'last_updated'=>'2025-06-29 08:00']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vendor Credit Bundles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#f7f9fc;font-family:'Segoe UI',sans-serif;}
        .credit-table-container{background:#fff;border-radius:1rem;box-shadow:0 6px 24px rgba(0,0,0,.06);}
        .credit-table-header{background:linear-gradient(90deg,#0d6efd 0%,#3d8bfd 100%);color:#fff;padding:1rem 1.5rem;}
        .credit-badge{font-size:.95rem;font-weight:500;border-radius:.75rem;}
        .vendor-id{color:#0d6efd;}
        .table th,.table td{vertical-align:middle!important;text-align:center;}
        .table-hover tbody tr:hover{background:#f8f9fa;}
    </style>
</head>
<body>
    <div class="container my-4">
        <div class="credit-table-container">
            <div class="credit-table-header">
                <h2 style="font-size:1.2rem;margin:0;">Vendor Credit Bundles Overview</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th colspan="6" class="text-start">
                                <a href="javascript:history.back()" class="btn btn-outline-primary btn-sm me-2">&larr; Back</a>
                            </th>
                        </tr>
                        <tr>
                            <th>#</th><th>User ID</th><th>Vendor</th><th>Service</th><th>Balance</th><th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($vendorCredits as $credit): ?>
                        <tr>
                            <td class="fw-semibold"><?= $credit['credit_id'] ?></td>
                            <td><span class="badge bg-secondary rounded-pill px-2 py-1">#<?= $credit['user_id'] ?></span></td>
                            <td><span class="vendor-id fw-semibold">#<?= $credit['vendor_id'] ?></span><br><small class="text-muted"><?= $credit['vendor_name'] ?></small></td>
                            <td><?= $credit['service_type'] ?></td>
                            <td><span class="badge bg-success credit-badge px-2 py-1"><?= number_format($credit['balance'],2) ?> Cr</span></td>
                            <td><small class="text-muted"><?= $credit['last_updated'] ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
