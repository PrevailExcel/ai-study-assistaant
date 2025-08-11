<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>iQuest Mail</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://my.iquest.com.ng/images/bww(1).png" rel="icon" />

    <style>
        @font-face {
            font-family: montserrat;
            src: url('/assets/fonts/Montserrat-Regular.ttf')
        }

        section {
            position: relative;
            display: block;
            margin: auto;
            min-width: 600px;
            box-sizing: border-box;
        }

        .pad {
            padding: 24px 48px;
        }


        h2 {
            font-weight: 400;
            font-size: 24px;
        }

        h2,
        p {
            margin-bottom: 20px;
        }
    </style>

</head>

<body lang="en" style="font-family: montserrat; text-align: center; width: 100%;">
    <main style="box-shadow: 2px 2px 5px rgba(0,0,0,.2); display: flex; flex-direction: column; items-align: center;">

        <section style="background-color: #07294d;">
            <div class="pad" style="display: flex; flex-wrap: wrap; display: flex; justify-content: center; text-align: center; align-items: center; width: 100%;">
                <img src="https://my.iquest.com.ng/images/bww(1).png" style="height: 96px;" alt="icon" />
                {{-- <h2 class="font-bold text-md ml-6 text-yellow-300">Log Complaint</h2> --}}
            </div>
            <div style="margin-bottom: -16px; width: 100%; height: 100px;">

                <img src="https://my.iquest.com.ng/images/curve.png" style="width: 100%; height: 100%;" alt="curve" />
            </div>
        </section>

        <section class="pad" style="text-align: left; background-color: white;">


            <h2 style="font-weight: bold; margin-bottom: 20px;">{{ $details['title'] }}</h2>

            <div style="background: #eee; padding: 8px; border-radius: 5px;">
                <p style="margin-bottom: 20px; font-size: 14px;">{{ $details['body'] }}
                    <br>
                    Open your app to check your wallet now.                    
                    <br/>
                    <br/>
                    <b>Remember, you can withdraw your rewards directly to your bank account.</b>
                    <br/>
                    <br/>
                    Refer more people to get even cash: 25% of their activation fee. 
                    <br>Your referral code is: <b>{{ $details['code'] }}</b>
                </p>

                <p style="margin-bottom: 10px; font-size: 10px;">
                    {{ \Carbon\carbon::now()->format('d M, Y  H:i A') }}</p>

                <p style="margin-bottom: 5px; font-size: 10px;">


                </p>
            </div>

            <p style="margin-bottom: 20px; font-size: 14px;">Check our website at<a
                    href="https://iquest.com.ng">https://iquest.com.ng</a> to see our services and other updates. <br />
            </p>





            <p style="margin: 20px 0px; font-weight: bold; font-size: 14px;">Regards: iQuest Co Ltd</p>
        </section>

        <section
            style="display: flex; justify-content: center; padding: 35px; padding-bottom: 10px; background-color: #13c8f4;">

            <a style="margin-left: 20px;" href="#" target="_blank">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#fc0" viewBox="0 0 24 24" stroke-width="1.0"
                    stroke="#fc0" style="width: 24px; height: 24px;">
                    <path id="Logo_00000038394049246713568260000012923108920998390947_"
                        d="M21.543,7.104c0.014,0.211,0.014,0.423,0.014,0.636  c0,6.507-4.954,14.01-14.01,14.01v-0.004C4.872,21.75,2.252,20.984,0,19.539c0.389,0.047,0.78,0.07,1.172,0.071  c2.218,0.002,4.372-0.742,6.115-2.112c-2.107-0.04-3.955-1.414-4.6-3.42c0.738,0.142,1.498,0.113,2.223-0.084  c-2.298-0.464-3.95-2.483-3.95-4.827c0-0.021,0-0.042,0-0.062c0.685,0.382,1.451,0.593,2.235,0.616  C1.031,8.276,0.363,5.398,1.67,3.148c2.5,3.076,6.189,4.946,10.148,5.145c-0.397-1.71,0.146-3.502,1.424-4.705  c1.983-1.865,5.102-1.769,6.967,0.214c1.103-0.217,2.16-0.622,3.127-1.195c-0.368,1.14-1.137,2.108-2.165,2.724  C22.148,5.214,23.101,4.953,24,4.555C23.339,5.544,22.507,6.407,21.543,7.104z" />
                </svg>
            </a>
            <a style="margin-left: 20px;" href="#" target="_blank">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#fc0" viewBox="0 0 24 24" stroke-width="1.0"
                    stroke="#fc0" style="width: 24px; height: 24px;">
                    <g>
                        <path
                            d="M24,12.073c0,5.989-4.394,10.954-10.13,11.855v-8.363h2.789l0.531-3.46H13.87V9.86c0-0.947,0.464-1.869,1.95-1.869h1.509   V5.045c0,0-1.37-0.234-2.679-0.234c-2.734,0-4.52,1.657-4.52,4.656v2.637H7.091v3.46h3.039v8.363C4.395,23.025,0,18.061,0,12.073   c0-6.627,5.373-12,12-12S24,5.445,24,12.073z" />
                    </g>
                </svg>
            </a>
            <a style="margin-left: 20px; margin-right: 20px;" href="#" target="_blank">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#fc0" viewBox="0 0 24 24" stroke-width="1.0"
                    stroke="#fc0" style="width: 24px; height: 24px;">
                    <g>
                        <path
                            d="M12,2.162c3.204,0,3.584,0.012,4.849,0.07c1.308,0.06,2.655,0.358,3.608,1.311c0.962,0.962,1.251,2.296,1.311,3.608   c0.058,1.265,0.07,1.645,0.07,4.849c0,3.204-0.012,3.584-0.07,4.849c-0.059,1.301-0.364,2.661-1.311,3.608   c-0.962,0.962-2.295,1.251-3.608,1.311c-1.265,0.058-1.645,0.07-4.849,0.07s-3.584-0.012-4.849-0.07   c-1.291-0.059-2.669-0.371-3.608-1.311c-0.957-0.957-1.251-2.304-1.311-3.608c-0.058-1.265-0.07-1.645-0.07-4.849   c0-3.204,0.012-3.584,0.07-4.849c0.059-1.296,0.367-2.664,1.311-3.608c0.96-0.96,2.299-1.251,3.608-1.311   C8.416,2.174,8.796,2.162,12,2.162 M12,0C8.741,0,8.332,0.014,7.052,0.072C5.197,0.157,3.355,0.673,2.014,2.014   C0.668,3.36,0.157,5.198,0.072,7.052C0.014,8.332,0,8.741,0,12c0,3.259,0.014,3.668,0.072,4.948c0.085,1.853,0.603,3.7,1.942,5.038   c1.345,1.345,3.186,1.857,5.038,1.942C8.332,23.986,8.741,24,12,24c3.259,0,3.668-0.014,4.948-0.072   c1.854-0.085,3.698-0.602,5.038-1.942c1.347-1.347,1.857-3.184,1.942-5.038C23.986,15.668,24,15.259,24,12   c0-3.259-0.014-3.668-0.072-4.948c-0.085-1.855-0.602-3.698-1.942-5.038c-1.343-1.343-3.189-1.858-5.038-1.942   C15.668,0.014,15.259,0,12,0z" />
                        <path
                            d="M12,5.838c-3.403,0-6.162,2.759-6.162,6.162c0,3.403,2.759,6.162,6.162,6.162s6.162-2.759,6.162-6.162   C18.162,8.597,15.403,5.838,12,5.838z M12,16c-2.209,0-4-1.791-4-4s1.791-4,4-4s4,1.791,4,4S14.209,16,12,16z" />
                        <circle cx="18.406" cy="5.594" r="1.44" />
                    </g>
                </svg>
            </a>

        </section>

        <section style="text-align: center; color: black; background: #13c8f4; padding-bottom: 48px; font-size: 12px;">
            Visit <a href="https://iquest.com.ng"
                style="color: black !important; text-decoration: underline">https://iquest.com.ng</a> for more
        </section>

    </main>
</body>

</html>
