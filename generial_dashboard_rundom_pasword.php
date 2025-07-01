<?php
// generial_dashboard_rundom_pasword.php
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

$generatedPassword = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $generatedPassword = generateRandomPassword();
    // Save to file
    file_put_contents(__DIR__ . '/dashboard_password.txt', $generatedPassword);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Random Password Generator</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        .password-box {
            margin: 20px 0;
            font-size: 1.2em;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
            display: inline-block;
        }
        button {
            background: #4CAF50;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
        }
        button:hover {
            background: #388e3c;
        }
        .copy-btn {
            margin-left: 10px;
            background: #2196F3;
        }
        .copy-btn:hover {
            background: #1769aa;
        }
        .success {
            color: green;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Random Password Generator</h2>
        <form method="POST">
            <button type="submit" name="generate">Generate Password</button>
        </form>
        <?php if ($generatedPassword): ?>
            <div class="password-box" id="passwordBox"><?php echo htmlspecialchars($generatedPassword); ?></div>
            <button class="copy-btn" onclick="copyPassword()">Copy</button>
            <div id="copySuccess" class="success" style="display:none;">Copied!</div>
        <?php endif; ?>
    </div>
    <script>
        function copyPassword() {
            var password = document.getElementById('passwordBox').textContent;
            navigator.clipboard.writeText(password).then(function() {
                document.getElementById('copySuccess').style.display = 'block';
                setTimeout(function() {
                    document.getElementById('copySuccess').style.display = 'none';
                }, 1500);
            });
        }
    </script>
</body>
</html>
