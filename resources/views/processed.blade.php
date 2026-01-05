<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Already Processed</title>
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

        .warning-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #F59E0B;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 30px;
        }

        .warning-icon svg {
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
        <div class="warning-icon">
            <svg viewBox="0 0 52 52">
                <path d="M26 16v12M26 34v2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h1>Payment Already Processed</h1>
        <p>This payment has already been processed. Please check your dashboard or contact support if you have any questions.</p>
        <a href="/capture" class="button">Confirm</a>
    </div>
</body>
</html>
