<?php

namespace App\Jobs;

use App\Mail\RewardMail;
use App\Mail\WelcomeMail;
use App\Services\SendPushNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $type;
    protected $data;
    protected $details;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($type, $data, $details)
    {
        $this->type = $type;
        $this->data = $data;
        $this->details = $details;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->type == 'welcome')
           Mail::to($this->data['email'])->send(new WelcomeMail($this->details));
        else if ($this->type == 'referral')
           Mail::to($this->data['email'])->send(new RewardMail($this->details));
        else if ($this->type == 'notify') {
            $push = new SendPushNotifications();
            $push->notify($this->data, $this->details);
        }
    }
}
