<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACE Model College - Payment System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .payment-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            overflow: hidden;
        }
        .payment-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 2rem;
            text-align: center;
        }
        .card-body {
            padding: 2.5rem;
        }
        .btn-payment {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 50px;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
            margin: 1rem 0;
        }
        .btn-payment:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .btn-school {
            background: linear-gradient(45deg, #11998e, #38ef7d);
        }
        .btn-tutorial {
            background: linear-gradient(45deg, #fc466b, #3f5efb);
        }
        .logo {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .payment-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-lg-8 col-md-10">
                <div class="payment-card">
                    <div class="card-header">
                        <div class="logo">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h1 class="mb-0">ACE Model College</h1>
                        <p class="mb-0">Payment System</p>
                    </div>
                    <div class="card-body text-center">
                        <h2 class="mb-4">Select Payment Type</h2>
                        <p class="text-muted mb-4">Choose the type of payment you want to make</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="payment-icon">
                                    <i class="fas fa-school text-success"></i>
                                </div>
                                <h4>School Payment</h4>
                                <p class="text-muted">Tuition fees, examination fees, and other school-related payments</p>
                                <a href="school_payment.php" class="btn btn-payment btn-school">
                                    <i class="fas fa-arrow-right me-2"></i>
                                    School Payment
                                </a>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="payment-icon">
                                    <i class="fas fa-chalkboard-teacher text-primary"></i>
                                </div>
                                <h4>ACE Tutorial Payment</h4>
                                <p class="text-muted">Tutorial fees and extra-curricular learning payments</p>
                                <a href="tutorial_payment.php" class="btn btn-payment btn-tutorial">
                                    <i class="fas fa-arrow-right me-2"></i>
                                    Tutorial Payment
                                </a>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-4 border-top">
                            <a href="payment_history.php" class="btn btn-outline-secondary">
                                <i class="fas fa-history me-2"></i>
                                View Payment History
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
