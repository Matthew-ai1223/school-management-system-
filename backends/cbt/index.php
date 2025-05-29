<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBT Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        h1 {
            color: #06BBCC;
            margin-bottom: 20px;
            font-size: 2.5rem;
            font-weight: bold;
            letter-spacing: 1px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }
        h3 {
            color: #6c757d;
            margin-bottom: 20px;
        }
        button {
            background-color: #06BBCC;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin: 5px;
        }
        button:hover {
            background-color: #0599A3;
        }
        .alert {
            margin-top: 20px;
            padding: 15px;
            background-color: #e0f7fa;
            color: #006064;
            border-radius: 5px;
        }

        /* Media Query for Mobile Devices */
        @media (max-width: 576px) {
            .container {
                margin: 20px auto;
                padding: 15px;
            }
            h1 {
                font-size: 2rem;
            }
            button {
                padding: 8px 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>WELCOME TO 300PLUS CBT Test</h1>
        <!-- <h3>To continue with the CBT test</h3>
        <button onclick="location.href='login.php'">Login</button>
        <h3>Do not have an account?</h3>
        <button onclick="location.href='register.php'">Sign Up</button> -->
       
    </div>
    <div class="container alert">
        <h3>The CBT test is currently unavailable. You will be notified as soon as it becomes available. Thank you.</h3>
        <button onclick="window.history.back()">Previous Page</button>
    </div>
</body>
</html>