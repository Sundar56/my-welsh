<!DOCTYPE html>
<html lang="cy">

<head>
    <meta charset="UTF-8">
    <title>Hysbysiad Defnyddiwr</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        /* same styles as original - keep for consistency */
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
            <img src="{{ $data['logo'] ?? '-' }}" alt="Logo Ysgol" style="height: 60px;">
        </div>

        <div class="email-body">
            @if($data['emailType'] == 'newuser')
            <h2>Croeso,</h2>
            <p>Diolch am gofrestru gyda ni. Mae eich cyfrif wedi'i greu'n llwyddiannus. Isod mae eich manylion mewngofnodi:</p>
            <p><strong>E-bost:</strong> {{ $data['email'] ?? '-' }}</p>
            <p><strong>Cyfrinair:</strong> {{ $data['password'] ?? '-' }}</p>
            <p>Cadwch y wybodaeth hon yn ddiogel. Gallwch nawr gyrchu eich dangosfwrdd gan ddefnyddio'r botwm isod.</p>

            @elseif($data['emailType'] == 'activate')
            <h2>Helo,</h2>
            <p>Diolch am gofrestru gyda ni. Mae eich cyfrif wedi'i actifadu'n llwyddiannus!</p>
            <p>Gallwch nawr archwilio a chael mynediad i'r holl adnoddau a nodweddion sydd ar gael yn eich cynllun tanysgrifio presennol.</p>

            @elseif($data['emailType'] == 'cancelled')
            <h2>Helo,</h2>
            <p>Rydym yn cadarnhau bod eich tanysgrifiad {{ $data['resourceName'] ?? '-' }} wedi'i ganslo'n llwyddiannus.</p>
            <p>Bydd eich mynediad yn aros yn weithredol tan {{ $data['endDate'] ?? '-' }}, ac ar ôl hynny ni fydd eich cyfrif yn cael ei godi mwyach.</p>
            <p>Rydych chi’n croeso bob amser i ddychwelyd — byddwn ni yma os penderfynwch ddod yn ôl.</p>

            @elseif($data['emailType'] == 'subscription')
            <h2>Helo,</h2>
            <p>Heddiw, hoffem eich atgoffa bod eich tanysgrifiad {{ $data['resourceName'] ?? '-' }}, a oedd yn ddilys tan {{ $data['endDate'] ?? '-' }}, bellach wedi dod i ben. Yn anffodus, nid oes gennych fynediad mwyach i'ch adnoddau tanysgrifiad.</p>
            <p>Os hoffech barhau i fwynhau ein gwasanaethau, rydym yn eich annog i adnewyddu eich tanysgrifiad. Gallwch wneud hynny'n hawdd trwy ymweld â'ch tudalen cyfrif neu ddilyn y ddolen isod:</p>

            @elseif($data['emailType'] == 'trail')
            <h2>Helo,</h2>
            <p>Mae eich cyfnod prawf wedi dod i ben. Ystyriwch uwchraddio eich cyfrif i barhau i ddefnyddio ein gwasanaethau.</p>

            @else
            <h2>Llwyddiant Ailosod Cyfrinair</h2>
            <p>Helo,</p>
            <p>Mae eich cyfrinair wedi'i ailosod yn llwyddiannus. Defnyddiwch y cyfrinair dros dro isod i fewngofnodi:</p>
            <strong>Cyfrinair Dros Dro:</strong>
            <div style="display: flex; justify-content: center; background-color: #f4f0fc; border-left: 4px solid #683BB4; padding: 10px 18px; border-radius: 10px; margin: 15px 0; min-width: 200px; max-width: 300px;">
                <p style="margin: 0; font-size: 16px; color: #333; text-align: center;">
                    <span style="color: #ED6D1B; font-weight: bold;">{{ $data['password'] ?? '-' }}</span>
                </p>
            </div>
            <p>Rydym yn argymell newid y cyfrinair hwn ar ôl mewngofnodi am resymau diogelwch.</p>
            @endif

            <a href="{{ $data['redirectLogin'] ?? '-' }}" style="color: #ffffff !important;" class="button">Mewngofnodi</a>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Ffa-la-la. Cedwir pob hawl.
        </div>
    </div>
</body>

</html>