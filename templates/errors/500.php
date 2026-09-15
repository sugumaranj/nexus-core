<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Server Error &mdash; NexusCore</title>
    <link href="<?= function_exists('asset') ? asset('assets/css/bootstrap.min.css') : '/assets/css/bootstrap.min.css' ?>" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            color: #212529;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            text-align: center;
            max-width: 600px;
            padding: 2rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        p {
            color: #6c757d;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">&#9888;</div>
        <h1>500</h1>
        <h2 class="h4 mb-3">Internal Server Error</h2>
        <p>Something went wrong on our end. We've logged the error and will investigate it. Please try again later or contact support if the problem persists.</p>
        <a href="<?= function_exists('base_url') ? base_url() : '/' ?>" class="btn btn-primary">Return to Dashboard</a>
    </div>
</body>
</html>
