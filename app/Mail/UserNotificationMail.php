<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $application;
    public $jobPost;

    public function __construct($application, $jobPost)
    {
        $this->application = $application;
        $this->jobPost = $jobPost;
    }

    public function build()
    {
        return $this->subject('Job Application Received')
                    ->view('emails.user_notification')
                    ->with([
                        'applicant_name' => $this->application->applicant_name,
                        'job_title' => $this->jobPost->job_title
                    ]);
    }
}
