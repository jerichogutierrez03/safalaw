<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\PracticeArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Handles general questions only — "I have a quick question" territory.
 * For an actual consultation booking request, see ConsultationController.
 * The two form types are stored separately to demonstrate the different
 * inquiry flows in the student project prototype.
 */
class ContactController extends Controller
{
    public function show()
    {
        $practiceAreas = PracticeArea::orderBy('sort_order')->get();

        return view('pages.contact', compact('practiceAreas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:75'],
            'last_name' => ['required', 'string', 'max:75'],
            'email' => ['required', 'email', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $fullName = trim($data['first_name'] . ' ' . $data['last_name']);
        unset($data['first_name'], $data['last_name']);

        Inquiry::create($data + [
            'full_name' => $fullName,
            'practice_area_id' => null,
            'status' => 'new',
            'type' => Inquiry::TYPE_GENERAL,
        ]);

$notificationEmail = config('safalaw.notification_email');
$emailSent = false;

if ($notificationEmail) {
    try {
        Mail::raw(
            "A new general inquiry was submitted on the SafaLaw website.\n\n"
            . "Name: {$fullName}\n"
            . "Email: {$data['email']}\n\n"
            . "Message:\n{$data['message']}\n",
            function ($mail) use ($notificationEmail, $data, $fullName) {
                $mail->to($notificationEmail)
                    ->subject('New SafaLaw Contact Message')
                    ->replyTo($data['email'], $fullName);
            }
        );

        $emailSent = true;
    } catch (\Throwable $exception) {
        report($exception);
    }
}

        return redirect()
    ->route('contact')
    ->with(
        'status',
        $emailSent
            ? 'Your message was saved and the email notification was submitted successfully.'
            : 'Your message was saved, but the email notification could not be sent. Please contact SAFALAW directly.'
    );
    }

}
