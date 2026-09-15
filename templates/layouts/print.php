<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Print View</title>
    <link rel="stylesheet" href="<?= asset('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/icons/bootstrap-icons/font/bootstrap-icons.css') ?>">
    <style>
        body { font-family: 'Inter', sans-serif; background: #fff; padding: 20px; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; background: #fff; }
            .page-break { page-break-after: always; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Render Content -->
        <?php require $contentFile; ?>
    </div>
</body>
</html>
