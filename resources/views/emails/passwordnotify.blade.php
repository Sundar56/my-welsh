<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Notification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f2f4f8;
            font-family: Helvetica, Arial, sans-serif;
        }

        .email-container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.08);
            font-family: Helvetica, Arial, sans-serif;
        }

        .email-header {
            background-color: #683BB4;
            padding: 30px;
            text-align: center;
        }

        .email-header img {
            height: 60px;
        }

        .email-body {
            padding: 30px;
        }

        .email-body h2 {
            color: #ED6D1B;
            margin-bottom: 20px;
        }

        .email-body p {
            color: #333;
            line-height: 1.6;
            font-size: 15px;
        }

        .button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 28px;
            background-color: #683BB4;
            color: #ffffff;
            text-decoration: none;
            border-radius: 30px;
            font-weight: bold;
        }

        .footer {
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #777;
            background-color: #f3f3f3;
            border-bottom-left-radius: 30px;
            border-bottom-right-radius: 30px;
        }

        @media screen and (max-width: 600px) {
            .email-body {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="email-container">

        <div class="email-header">
            <img src="{{ $data['logo'] ?? '-' }}" alt="School Logo" style="height: 60px;">
        </div

            <!-- Body Content -->
        <div class="email-body">
            @if($data['emailType'] == 'newuser')
            <h2>Welcome,</h2>
            <p>Thank you for registering with us. Your account has been successfully created. Below are your login details:</p>
            <p><strong>Email:</strong> {{ $data['email'] ?? '-' }}</p>
            <p><strong>Password:</strong> {{ $data['password'] ?? '-' }}</p>
            <p>Please keep this information secure. You can now access your dashboard using the button below.</p>

            @elseif($data['emailType'] == 'activate')
            <h2>Hello,</h2>
            <p>Thank you for registering with us. Your account has been successfully activated!</p>
            <p>You can now explore and access all the resources and features available to you based on your current subscription plan.</p>

            @elseif($data['emailType'] == 'cancelled')
            <h2>Hi,</h2>
            <p>We're confirming that your subscription {{ $data['resourceName'] ?? '-' }} has been successfully cancelled.</p>
            <p>Your access will remain active until {{ $data['endDate'] ?? '-' }}, after which your account will no longer be billed.</p>
            <p>You’re always welcome back — we’ll be here if you decide to return.</p>

            @elseif($data['emailType'] == 'subscription')
            <h2>Hi,</h2>
            <p>This is a reminder that your {{ $data['resourceName'] ?? '-' }} subscription, which was valid until {{ $data['endDate'] ?? '-' }}, has now expired. Unfortunately, you no longer have access to your subscription resources.</p>
            <p>If you'd like to continue enjoying our services, we encourage you to renew your subscription. You can do so easily by visiting your account page or following the link below:</p>

            @elseif($data['emailType'] == 'trail')
            <h2>Hello,</h2>
            <p>Your trial period has ended. Please consider upgrading your account to continue using our services.</p>

            @else
            <h2>Password Reset Successful</h2>
            <p>Hello,</p>
            <p>Your password has been reset successfully. Please use the temporary password below to log in:</p>
            <strong>Temporary Password:</strong>
            <div style="display: flex; justify-content: center; background-color: #f4f0fc; border-left: 4px solid #683BB4; padding: 10px 18px; border-radius: 10px; margin: 15px 0; min-width: 200px; max-width: 300px;">
                <p style="margin: 0; font-size: 16px; color: #333; text-align: center;">
                    <span style="color: #ED6D1B; font-weight: bold;">{{ $data['password'] ?? '-' }}</span>
                </p>
            </div>
            <p>We recommend changing this password after logging in for security reasons.</p>
            @endif
            <a href="{{ $data['redirectLogin'] ?? '-' }}" style="color: #ffffff !important;" class="button">Login</a>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Ffa-la-la. All rights reserved.
        </div>
    </div>
</body>

</html>