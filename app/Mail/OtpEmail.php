<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $details;
    public $subjectLine;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($details, $subjectLine = 'OTP verifikasi | topscore.id')
    {
        $this->details = $details;
        $this->subjectLine = $subjectLine;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->subjectLine)
                    ->html($this->details);
    }
}
