<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exception Trace &mdash; NexusCore Debug</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f8f9fa; color: #212529; padding: 2rem; }
        .trace-container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-left: 5px solid #dc3545; }
        .exception-class { font-size: 1.1rem; color: #dc3545; font-weight: bold; margin-bottom: 0.5rem; }
        .exception-message { font-size: 1.5rem; font-weight: bold; margin-bottom: 1.5rem; word-break: break-word; }
        .exception-location { font-size: 1rem; color: #495057; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #dee2e6; }
        .trace-block { background-color: #212529; color: #f8f9fa; padding: 1.5rem; border-radius: 6px; overflow-x: auto; font-family: "SFMono-Regular", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 0.9rem; line-height: 1.5; }
        .footer { margin-top: 2rem; text-align: center; color: #6c757d; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="trace-container">
        <div class="exception-class"><?= htmlspecialchars(get_class($exception)) ?></div>
        <div class="exception-message"><?= htmlspecialchars($exception->getMessage()) ?></div>
        <div class="exception-location">
            in <strong><?= htmlspecialchars($exception->getFile()) ?></strong> on line <strong><?= $exception->getLine() ?></strong>
        </div>
        
        <h3 style="font-size: 1.1rem; margin-bottom: 1rem;">Stack Trace:</h3>
        <div class="trace-block"><?= htmlspecialchars($exception->getTraceAsString()) ?></div>
    </div>
    <div class="footer">
        NexusCore Development Mode (APP_DEBUG=true)
    </div>
</body>
</html>
