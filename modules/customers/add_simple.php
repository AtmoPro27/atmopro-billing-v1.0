<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

echo "<h1>Test Form</h1>";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "<pre>";
    echo "Form submitted!\n";
    print_r($_POST);
    echo "</pre>";
    echo "<script>alert('Form submitted!');</script>";
} else {
    echo '
    <form method="POST">
        <input type="text" name="test" placeholder="Test input">
        <button type="submit">Submit</button>
    </form>
    ';
}

include '../../includes/footer.php';
?>
