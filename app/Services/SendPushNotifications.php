<?php

namespace App\Services;

use App\Jobs\SendNotificationsJob;
use App\Mail\RewardMail;
use App\Mail\WelcomeMail;
use App\Notifications\SendPushNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Kutia\Larafirebase\Facades\Larafirebase;

class SendPushNotifications
{
    public function afterRegistering($data)
    {
        $title = 'Hey ' . $data['name'] . ', Welcome to iQuest';
        $body = "We can't wait for you to start using iQuest and seeing your excellent results. \nAs always, our support team can be reached by replying this mail if you ever get stuck. \n Have a great day!";

        // Send the Firebase Notification
        $SERVER_API_KEY = env('FIREBASE_SERVER_KEY', "AAAAMuzDsj0:APA91bFDdY6sHd_3FfiFPrYlXTKL22wPyoIRB6rhlVrEA_ye7Zrjv6yA-Cm_7wSl2CKZeWqEOIyxyaYVOAMMOb6JOrmT5SHyudCWdTQHgrLMG5Z5ZvDYeooFzjudKn2_4PkkFeHmYz3G");
        $firedata = [
            "registration_ids" => [$data['notification_id']],
            "notification" => [
                "title" => $title,
                "body" => $body,
            ]
        ];
        $dataString = json_encode($firedata);

        $headers = [
            'Authorization: key=' . $SERVER_API_KEY,
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);

        $response = curl_exec($ch);

        // Send email
        $details = [
            'title' => $title,
            'body' => $body,
            'code' => $data['code']
        ];
        SendNotificationsJob::dispatch('welcome', $data, $details);
        return true;
    }

    public function returningUser($data)
    {
        $title = 'Hey ' . $data['name'] . ', we are glad to have you back';
        $body = "We can't wait for you to start using iQuest and seeing your excellent results. \nAs always, our support team can be reached by replying this mail if you ever get stuck. \n Have a great day!";

        // Send the Firebase Notification
        $SERVER_API_KEY = env('FIREBASE_SERVER_KEY', "AAAAMuzDsj0:APA91bFDdY6sHd_3FfiFPrYlXTKL22wPyoIRB6rhlVrEA_ye7Zrjv6yA-Cm_7wSl2CKZeWqEOIyxyaYVOAMMOb6JOrmT5SHyudCWdTQHgrLMG5Z5ZvDYeooFzjudKn2_4PkkFeHmYz3G");
        $firedata = [
            "registration_ids" => [$data['notification_id']],
            "notification" => [
                "title" => $title,
                "body" => $body,
            ]
        ];
        $dataString = json_encode($firedata);

        $headers = [
            'Authorization: key=' . $SERVER_API_KEY,
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);

        $response = curl_exec($ch);

        // Send email
        $details = [
            'title' => $title,
            'body' => $body,
            'code' => $data['code']
        ];
        SendNotificationsJob::dispatch('welcome', $data, $details);
        return true;
    }

    public function referralReward($data, $transaction)
    {
        $title = 'Congratulations, ₦' . $transaction . ' have just been addded to your wallet';
        $body = "One of your referrals just activated their account, and you have been rewarded with 20% of their activation fee!";


        // Send the Firebase Notification
        $SERVER_API_KEY = env('FIREBASE_SERVER_KEY', "AAAAMuzDsj0:APA91bFDdY6sHd_3FfiFPrYlXTKL22wPyoIRB6rhlVrEA_ye7Zrjv6yA-Cm_7wSl2CKZeWqEOIyxyaYVOAMMOb6JOrmT5SHyudCWdTQHgrLMG5Z5ZvDYeooFzjudKn2_4PkkFeHmYz3G");
        $firedata = [
            "registration_ids" => [$data['notification_id']],
            "notification" => [
                "title" => $title,
                "body" => $body,
            ]
        ];
        $dataString = json_encode($firedata);

        $headers = [
            'Authorization: key=' . $SERVER_API_KEY,
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);

        $response = curl_exec($ch);

        // Send email
        $details = [
            'title' => $title,
            'body' => $body,
            'code' => $data['code']
        ];
        SendNotificationsJob::dispatch('referral', $data, $details);
        return true;
    }

    public function referralRewardWithdrawal($data, $transaction)
    {
        $title = 'Congratulations, NGN' . number_format($transaction) . ' have been sent to your bank account';
        $body = "Your money is on the way to your account. Continue to refer to earn big with iQuest!";


        // Send the Firebase Notification
        $SERVER_API_KEY = env('FIREBASE_SERVER_KEY', "AAAAMuzDsj0:APA91bFDdY6sHd_3FfiFPrYlXTKL22wPyoIRB6rhlVrEA_ye7Zrjv6yA-Cm_7wSl2CKZeWqEOIyxyaYVOAMMOb6JOrmT5SHyudCWdTQHgrLMG5Z5ZvDYeooFzjudKn2_4PkkFeHmYz3G");
        $firedata = [
            "registration_ids" => [$data['notification_id']],
            "notification" => [
                "title" => $title,
                "body" => $body,
            ]
        ];
        $dataString = json_encode($firedata);

        $headers = [
            'Authorization: key=' . $SERVER_API_KEY,
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);

        $response = curl_exec($ch);

        // Send email
        $details = [
            'title' => $title,
            'body' => $body,
            'code' => $data['code']
        ];
        SendNotificationsJob::dispatch('referral', $data, $details);
        return true;
    }

    public function notifyInfluencer($data, $code)
    {
        $title = $code . ' is your new special referral code.';
        $body = "Congratulations! You have been granted a special influencer ranking with us. Refer more users and get paid even more as an iQuest influencer!";


        // Send the Firebase Notification
        $SERVER_API_KEY = env('FIREBASE_SERVER_KEY', "AAAAMuzDsj0:APA91bFDdY6sHd_3FfiFPrYlXTKL22wPyoIRB6rhlVrEA_ye7Zrjv6yA-Cm_7wSl2CKZeWqEOIyxyaYVOAMMOb6JOrmT5SHyudCWdTQHgrLMG5Z5ZvDYeooFzjudKn2_4PkkFeHmYz3G");
        $firedata = [
            "registration_ids" => [$data['notification_id']],
            "notification" => [
                "title" => $title,
                "body" => $body,
            ]
        ];
        $dataString = json_encode($firedata);

        $headers = [
            'Authorization: key=' . $SERVER_API_KEY,
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);

        $response = curl_exec($ch);

        // Send email
        $details = [
            'title' => $title,
            'body' => $body,
            'code' => $data['code']
        ];
        SendNotificationsJob::dispatch('referral', $data, $details);
        return true;
    }

    public function notify($ids, $details)
    {
        $title = $details['title'];
        $body = $details['body'];

        // Send the Firebase Notification
        $SERVER_API_KEY = env('FIREBASE_SERVER_KEY', "AAAAMuzDsj0:APA91bFDdY6sHd_3FfiFPrYlXTKL22wPyoIRB6rhlVrEA_ye7Zrjv6yA-Cm_7wSl2CKZeWqEOIyxyaYVOAMMOb6JOrmT5SHyudCWdTQHgrLMG5Z5ZvDYeooFzjudKn2_4PkkFeHmYz3G");
        $firedata = [
            "registration_ids" => $ids,
            "notification" => [
                "title" => $title,
                "body" => $body,
            ]
        ];
        $dataString = json_encode($firedata);

        $headers = [
            'Authorization: key=' . $SERVER_API_KEY,
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);

        $response = curl_exec($ch);

        return true;
    }
}
