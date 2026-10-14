<?php
session_start();
session_destroy();

// Hapus cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - DEMO COMPANY</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0d5c1e, #0a4a16);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logout-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .logout-card .icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .logout-card h3 {
            color: #0d5c1e;
            margin-bottom: 10px;
        }
        .logout-card p {
            color: #666;
            margin-bottom: 20px;
        }
        .btn-logout {
            background: #0d5c1e;
            color: white;
            padding: 10px 30px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-logout:hover {
            background: #0a4a16;
            color: white;
        }
    </style>
</head>
<body>
    <div class="logout-card">
        <div class="icon">✅</div>
        <h3>Logout Berhasil</h3>
        <p>Anda telah berhasil keluar dari portal pelanggan.</p>
        <a href="index.php" class="btn-logout">Kembali ke Login</a>
    </div>
</body>
</html>
<?php exit(); ?>
