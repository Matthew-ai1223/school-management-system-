<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Class Teacher Login | <?php echo SCHOOL_NAME; ?></title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="../assets/plugins/fontawesome-free/css/all.min.css">
  <!-- icheck bootstrap -->
  <link rel="stylesheet" href="../assets/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="../assets/css/adminlte.min.css">
  
  <!-- Custom Header Styles -->
  <style>
    .login-header {
      background: linear-gradient(135deg, #0033a0, #005EB8);
      color: #fff;
      padding: 20px;
      border-radius: 5px;
      margin-bottom: 25px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    
    .login-header h1 {
      font-weight: 700;
      font-size: 24px;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    
    .login-header p {
      font-size: 16px;
      opacity: 0.9;
      margin-bottom: 0;
    }
    
    .login-header::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #FFD700, #FFA500);
    }
    
    .login-logo {
      font-size: 2.1rem;
      font-weight: 700;
      margin-bottom: 0.9rem;
      text-align: center;
    }
    
    .login-logo a {
      color: #495057;
      text-decoration: none;
    }
    
    .login-logo img {
      max-height: 80px;
      margin-bottom: 10px;
    }
    
    @media (max-width: 768px) {
      .login-header {
        padding: 15px;
      }
      .login-header h1 {
        font-size: 20px;
      }
    }
  </style>
</head>
<body class="hold-transition login-page">
<!-- Login page content begins --> 