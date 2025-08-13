<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Ffa-la-la Invoice</title>
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

        .email-body p,
        .email-body li {
            color: #333;
            line-height: 1.6;
            font-size: 15px;
        }

        .invoice-box {
            background-color: #f4f0fc;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .invoice-box p {
            margin: 6px 0;
        }

        .button {
            display: inline-block;
            margin-top: 25px;
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
            <img src="{{$data['logo'] ?? '-'}}" alt="School Logo" style="height: 60px;">
        </div>

        <div class="email-body">
            <h2>Invoice for Subscription</h2>
            <p>Hello,</p>
            <p>Thank you for choosing <strong>Ffa-la-la</strong>! Below are the details of your subscription invoice:</p>

            <div class="invoice-box">
                <div style="text-align: right;">
                    <p><strong>Invoice Number: </strong>{{$data['invoiceNumber'] ?? '-'}}</p>
                    <p><strong>Issue Date: </strong>{{$data['issueDate'] ?? '-'}}</p>
                </div>
                @php
                $items = $data['invoiceData'] ?? [];
                $total = 0;
                @endphp
                <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 20px; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #eae3f9;">
                            <th style="text-align: left; padding: 8px; border: 1px solid #ddd;">Email</th>
                            <th style="text-align: left; padding: 8px; border: 1px solid #ddd;">Plan</th>
                            <th style="text-align: right; padding: 8px; border: 1px solid #ddd;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                        @php
                        $amount = floatval($item['amount'] ?? 0);
                        $total += $amount;
                        @endphp
                        <tr>
                            <td style="padding: 8px; border: 1px solid #ddd;">{{ $item['user_email'] ?? '-' }}</td>
                            <td style="padding: 8px; border: 1px solid #ddd;">{{ $item['resource_name'] ?? '-' }}</td>
                            <td style="text-align: right; padding: 8px; border: 1px solid #ddd;">£{{ number_format($amount, 2) }}</td>
                        </tr>
                        @endforeach
                        <tr style="font-weight: bold;">
                            <td colspan="2" style="text-align: right; padding: 8px; border: 1px solid #ddd;">Total</td>
                            <td style="text-align: right; padding: 8px; border: 1px solid #ddd;">£{{ number_format($total, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p>Click the button below to login:</p>
            <a href="{{$data['redirectLogin'] ?? '-'}}" style="color: #ffffff !important;" class="button">Login</a>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Ffa-la-la. All rights reserved.
        </div>
    </div>
</body>

</html>