<?php
require_once __DIR__ . '/../../includes/auth_check.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();

        $fullname = $_POST['full_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Cek apakah user ada
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            // Update user yang ada (gunakan kolom fullname)
            $sql = "UPDATE users SET fullname = ?, email = ?";
            $params = [$fullname, $email];

            if (!empty($new_password) && $new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql .= ", password = ?";
                $params[] = $hashed_password;
            }

            $sql .= " WHERE id = ?";
            $params[] = $user_id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            // Insert user baru jika belum ada
            $sql = "INSERT INTO users (id, username, fullname, email, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $_SESSION['user_name'], $fullname, $email, $_SESSION['user_role']]);

            if (!empty($new_password) && $new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
            }
        }

        // Update session
        $_SESSION['user_name'] = $fullname;

        header("Location: index.php?success=1");
        exit();

    } catch(PDOException $e) {
        header("Location: index.php?error=1");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>
