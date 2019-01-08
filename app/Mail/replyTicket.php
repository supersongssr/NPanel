<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class replyTicket extends Mailable
{
    use Queueable, SerializesModels;

    protected $title;
    protected $content;

    public function __construct($title, $content)
    {
        $this->title = $title;
        $this->content = $content;
    }

    public function build()
    {   
        //subject这里变成 titile可以有
        return $this->view('emails.replyTicket')->subject("$this->title")->with([
            'title'   => $this->title,
            'content' => $this->content
        ]);
    }
}
