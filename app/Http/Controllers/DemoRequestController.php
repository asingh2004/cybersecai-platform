<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class DemoRequestController extends Controller
{
    public function showForm()
    {
        return view('demo_request');
    }

    public function submitRequest(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:75',
            'email'   => 'required|email|max:120',
            'company' => 'nullable|string|max:90',
            'message' => 'required|string|max:900',
        ]);

        // Choose destination - from config/mail.php or fallback
        $recipient = config('mail.demo_address', env('MAIL_DEMO_ADDRESS', 'info@cybersecai.io'));

        // Build email body
        $body = "Demo Request\n\n"
              . "Name: {$data['name']}\n"
              . "Email: {$data['email']}\n"
              . "Company: {$data['company']}\n\n"
              . "{$data['message']}\n";

        // Real email send: will use MAIL_* from .env automatically!
        try {
            Mail::raw($body, function($m) use ($recipient, $data) {
                $m->to($recipient)
                  ->from(config('mail.from.address'), config('mail.from.name'))
                  ->replyTo($data['email'], $data['name'])
                  ->subject("New Demo Request from {$data['name']}");
            });
            Log::info("[DemoRequest] Demo request sent to $recipient from {$data['email']}");
            return back()->with('message', 'Thank you! Our team will contact you soon.');
        } catch (\Throwable $e) {
            Log::error("[DemoRequest] Failed to send demo request: " . $e->getMessage());
            return back()->with('message', 'Sorry, there was a problem submitting your request. Please email info@cybersecai.io directly.');
        }
    }
}