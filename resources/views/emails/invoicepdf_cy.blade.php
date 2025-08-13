<!DOCTYPE html>
<html lang="cy">

<head>
  <meta charset="UTF-8">
  <title>Anfoneb Tanysgrifiad</title>
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

    <div class="email-header" style="text-align: center; margin-bottom: 20px;">
      @if(!empty($data['pdflogo']))
      <img src="{{ $data['logo'] }}" alt="Logo Ysgol" style="height: 60px;">
      @else
      <span style="font-size: 14px; color: #888;">Dim logo wedi’i ddarparu</span>
      @endif
    </div>

    <h2>Anfoneb Tanysgrifiad</h2>

    <div style="text-align: right;">
      <p><strong>Rhif Anfoneb: </strong>{{ $data['invoiceNumber'] ?? '-' }}</p>
      <p><strong>Dyddiad Cyhoeddi: </strong>{{ $data['issueDate'] ?? '-' }}</p>
    </div>

    @php
    $items = $data['invoiceData'] ?? [];
    $total = 0;
    @endphp

    <table>
      <thead>
        <tr>
          <th>E-bost</th>
          <th>Cynllun</th>
          <th class="amount">Swm</th>
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
          <td colspan="2" class="amount">Cyfanswm</td>
          <td class="amount">£{{ number_format($total, 2) }}</td>
        </tr>
      </tbody>
    </table>

    <p><strong>Telerau Talu:</strong> Dylid talu o fewn 14 diwrnod.</p>
    <p><strong>Dull Talu:</strong> Trosglwyddiad Banc neu Daliad Diogel Ar-lein</p>
    <p><strong>Cyfeirnod:</strong> Defnyddiwch rif yr anfoneb wrth dalu</p>

    <div class="footer">
      © {{ now()->year }} Ffa-la-la. Cedwir pob hawl.
    </div>
  </div>
</body>

</html>
