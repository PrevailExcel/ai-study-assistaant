<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed</title>
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

        .error-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #DC2626;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 30px;
        }

        .error-icon svg {
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
        <div class="error-icon">
            <svg viewBox="0 0 52 52">
                <path d="M16 16l20 20M36 16l-20 20" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h1>Something happened</h1>
        <p>Unfortunately, your payment could not be processed. Please try again or contact support if the issue persists.</p>
        <a href="/capture" class="button">Go Back</a>
    </div>
</body>
</html>
