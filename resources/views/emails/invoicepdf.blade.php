<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Subscription Invoice</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 40px;
      color: #333;
    }

    .invoice-box {
      max-width: 800px;
      margin: auto;
      border: 1px solid #eee;
      padding: 30px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
    }

    h2 {
      text-align: center;
      color: #683BB4;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin: 20px 0;
    }

    th,
    td {
      padding: 10px;
      border: 1px solid #ddd;
      text-align: left;
    }

    th {
      background-color: #f4f0fc;
    }

    .total {
      font-weight: bold;
    }

    .amount {
      text-align: right;
    }

    .footer {
      margin-top: 30px;
      font-size: 12px;
      color: #666;
      text-align: center;
    }
  </style>
</head>

<body>
  <div class="invoice-box">
    <h2>Subscription Invoice</h2>

    <div style="text-align: right;">
      <p><strong>Invoice Number: </strong>{{$data['invoiceNumber'] ?? '-'}}</p>
      <p><strong>Issue Date: </strong>{{$data['issueDate'] ?? '-'}}</p>
    </div>

    @php
    $items = $data['invoiceData'] ?? [];
    $total = 0;
    @endphp

    <table>
      <thead>
        <tr>
          <th>Email</th>
          <th>Plan</th>
          <th class="amount">Amount</th>
        </tr>
      </thead>
      <tbody>
        @foreach($items as $item)
        @php
        $amount = floatval($item['amount'] ?? 0);
        $total += $amount;
        @endphp
        <tr>
          <td>{{ $item['user_email'] ?? '-' }}</td>
          <td>{{ $item['resource_name'] ?? '-' }}</td>
          <td class="amount">£{{ number_format($amount, 2) }}</td>
        </tr>
        @endforeach

        <tr class="total">
          <td colspan="2" class="amount">Total</td>
          <td class="amount">£{{ number_format($total, 2) }}</td>
        </tr>
      </tbody>
    </table>

    <p><strong>Payment Terms:</strong> Payment due within 14 days.</p>
    <p><strong>Payment Method:</strong> Bank Transfer or Secure Online Payment</p>
    <p><strong>Reference:</strong> Use invoice number when making payment</p>

    <div class="footer">
      © {{ now()->year }} Ffa-la-la. All rights reserved.
    </div>
  </div>
</body>

</html>