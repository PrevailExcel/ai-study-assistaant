<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #000000;
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #3B52DB;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 30px;
        }

        .checkmark svg {
            width: 50px;
            height: 50px;
            stroke: #ffffff;
            stroke-width: 3;
            fill: none;
        }

        h1 {
            font-size: 32px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        p {
            font-size: 18px;
            color: #a0a0a0;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .button {
            display: inline-block;
            background-color: #3B52DB;
            color: #ffffff;
            padding: 14px 40px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .button:hover {
            background-color: #2d3fb3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="checkmark">
            <svg viewBox="0 0 52 52">
                <path d="M14 27l9 9 16-16" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h1>Thank You!</h1>
        <p>Your payment was successful. You can go back to your dashboard now.</p>
        <a href="/capture" class="button">Continue</a>
    </div>
</body>
</html>
