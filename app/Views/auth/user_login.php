<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rootins::Admin Login</title>

    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f4f7fb;
            font-family: 'Segoe UI', sans-serif;
        }

        .login-wrapper {
            min-height: 100vh;
        }

        .left-section {
            background: linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.45)),
                        url('<?= base_url("assets/img/home-bg.jpg?v=123")?>');
            background-size: cover;
            background-position: center;
            color: #fff;
            position: relative;
        }

        .overlay-content {
            position: absolute;
            bottom: 60px;
            left: 50px;
            right: 50px;
        }

        .overlay-content h1 {
            font-size: 42px;
            font-weight: 700;
        }

        .overlay-content p {
            font-size: 16px;
            opacity: 0.9;
        }

        .right-section {
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .login-box {
            width: 100%;
            max-width: 420px;
        }

        .brand-logo {
           position: relative;
           left: -10px;
        }
        .brand-logo img{
            width: 150px;
        }


        .login-title {
            font-size: 20px;
            font-weight: 600;
            color: #727272;
            margin: 0;
        }

        .login-subtitle {
            color: #777;
            margin-bottom: 30px;
        }

        .form-control {
            height: 50px;
            border-radius: 10px;
            font-size: 15px;
        }

        .input-group-text {
            border-radius: 10px 0 0 10px;
            background: #f8f9fa;
        }

        .btn-login {
            height: 50px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
        }

        .forgot-link {
            font-size: 14px;
        }
        .theme-btn{
            background: #6fc3bc;
            color: #fff;
        }

        @media(max-width: 768px) {
            .left-section {
                display: none;
            }

            .right-section {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row login-wrapper">

        <!-- Left Side -->
        <div class="col-md-6 left-section d-none d-md-block">
            <div class="overlay-content">
                <h3>Rootins Admin Panel</h3>
                <p>
                    Manage users, leads, patients and other operations at one place.
                </p>
            </div>
        </div> 
        <!-- Right Side -->
        <div class="col-md-6 right-section">

            <div class="login-box">


                <div class="brand-logo">
                    <img src="<?= base_url('assets/img/logo.png')?>">
                </div>

                <h2 class="login-title">Welcome Back</h2>
                <p class="login-subtitle">
                    Login to access the Admin Dashboard
                </p>


                <?php if (session()->getFlashdata('error')) : ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= session()->getFlashdata('error') ?>
                        <button type="button" class="close" data-dismiss="alert">
                            <span>&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
 

                <?= form_open('system/user-auth-authenticate', 'autocomplete="off"')?>

                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                            </div>
                            <input type="email" class="form-control" name="user_id" placeholder="Enter email">

                        </div>
                        <div class="validation-error"><?= display_form_error(validation_errors(), 'user_id')?></div>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                            </div>
                            <input type="password" class="form-control" name="user_password" placeholder="Enter password">
                        </div>
                        <div class="validation-error"><?= display_form_error(validation_errors(), 'user_password')?></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <!-- <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="remember">
                            <label class="custom-control-label" for="remember">
                                Remember Me
                            </label>
                        </div> -->

                        <a href="<?= base_url('account/password-recovery')?>" class="forgot-link">
                            Forgot Password?
                        </a>
                    </div>

                    <button type="submit" class="btn theme-btn btn-block btn-login">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Login
                    </button>

                <?= form_close()?>

                <div class="text-center mt-4 text-muted">
                    © <?= date('Y')?> www.newrootsapps.com
                </div>

            </div>
        </div>

    </div>
</div>

</body>
</html>