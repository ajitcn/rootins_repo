<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 3rem; color: #333; }
        .container { max-width: 38rem; margin: 0 auto; text-align: center; }
        a { color: #0d6efd; }
    </style>
</head>
<body>
    <main class="container">
        <h1>Access Denied</h1>
        <p>You do not have permission to access this page.</p>
        <a href="<?= esc(base_url('admin')) ?>">Return to dashboard</a>
    </main>
</body>
</html>
