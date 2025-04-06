<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>درگاه پرداخت تست</title>
</head>

<body>
    <div class="hero">
        <h1>درگاه پرداخت تست</h1>
        <form action="{{ $callbackUrl }}" method="post">
            @csrf
            <button type="submit" name='status' value='successed'>تراکنش موفق</button>
            <button type="submit" name='status' value='failed'>تراکنش نا موفق</button>
        </form>
    </div>

    <style>
        body {
            text-align: center;
            font-family: Tahoma;
            background-color: #f5f5f5;
            color: #333;
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hero {
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            padding: 50px;
            text-align: center;
        }

        h1 {
            margin: 0 0 30px 0;
        }

        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 10px;
            cursor: pointer;
        }

        button[value="failed"] {
            background-color: #f44336;
        }
    </style>
</body>

</html>
