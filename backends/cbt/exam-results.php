<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Submitted - ACE MODEL COLLEGE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a73e8;
            --success-color: #34a853;
            --background-color: #f8f9fa;
        }

        body {
            background: var(--background-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .result-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
            max-width: 600px;
            width: 90%;
            margin: 20px;
        }

        .success-icon {
            font-size: 80px;
            color: var(--success-color);
            margin-bottom: 20px;
            animation: bounce 1s ease;
        }

        .thank-you-title {
            color: var(--success-color);
            font-size: 2rem;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .message {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .btn-dashboard {
            background: var(--primary-color);
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
            border: none;
        }

        .btn-dashboard:hover {
            background: #1557b0;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 115, 232, 0.2);
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-30px);
            }
            60% {
                transform: translateY(-15px);
            }
        }

        .divider {
            height: 1px;
            background: #e0e0e0;
            margin: 30px 0;
        }

        .info-text {
            color: #888;
            font-size: 0.9rem;
            margin-top: 20px;
        }

        .school-logo {
            max-width: 120px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <img src="../assets/images/logo.png" alt="ACE MODEL COLLEGE" class="school-logo">
        <i class='bx bxs-check-circle success-icon'></i>
        <h1 class="thank-you-title">Thank You!</h1>
        <p class="message">
            Your exam has been successfully submitted. We appreciate your dedication and effort.
            Your results will be available in your dashboard shortly.
        </p>
        <div class="divider"></div>
        <a href="dashboard.php" class="btn-dashboard">
            <i class='bx bxs-dashboard'></i> Return to Dashboard
        </a>
        <p class="info-text">
            If you have any questions, please contact your subject teacher or the examination office.
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent going back to exam page
        history.pushState(null, null, location.href);
        window.onpopstate = function() {
            history.go(1);
        };

        // Auto redirect after 5 seconds
        setTimeout(function() {
            window.location.href = 'dashboard.php';
        }, 5000);
    </script>
</body>
</html>