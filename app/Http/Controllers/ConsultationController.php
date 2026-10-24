<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\Attorney;
use App\Models\PracticeArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Handles consultation request form submissions — distinct from
 * ContactController's general inquiries. A client lands here when they
 * already know they want to sit down with an attorney, so this form asks
 * for more commitment-level detail: which practice area, a phone number,
 * and a preferred date/time, demonstrating the request flow in this prototype.
 */
class ConsultationController extends Controller
{
    private const TIME_SLOTS = [
        'Morning (9:00 AM – 12:00 PM)',
        'Afternoon (1:00 PM – 5:00 PM)',
    ];

    public function show()
    {
        $practiceAreas = PracticeArea::orderBy('sort_order')->get();
        $timeSlots = self::TIME_SLOTS;
        $recommendedAttorneys = collect();
        $recommendedIds = session()->get('recommended_attorney_ids', []);
        if ($recommendedIds) {
            $ranked = Attorney::with('practiceArea')->whereIn('id', $recommendedIds)->get()->keyBy('id');
            $recommendedAttorneys = collect($recommendedIds)->map(fn ($id) => $ranked->get($id))->filter();
        }

        return view('pages.consultation', compact('practiceAreas', 'timeSlots', 'recommendedAttorneys'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'client_type' => ['required', 'in:individual,business,organization'],
            'client_location' => ['required', 'string', 'max:120'],
            'practice_area_id' => ['required', 'exists:practice_areas,id'],
            'legal_profile' => ['required', 'string', 'max:2000'],
            'client_requirements' => ['required', 'string', 'max:2000'],
            'preferred_date' => ['required', 'date', 'after_or_equal:today'],
            'preferred_time' => ['required', 'in:' . implode(',', self::TIME_SLOTS)],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $recommendations = $this->recommendAttorneys($data['practice_area_id'], $data['client_location']);
        $inquiry = Inquiry::create($data + [
            'status' => 'new',
            'type' => Inquiry::TYPE_CONSULTATION,
            'attorney_id' => $recommendations->first()?->id,
        ]);

$notificationEmail = config('safalaw.notification_email');
$emailSent = false;

if ($notificationEmail) {
    try {
        $body =
            "A new consultation request was submitted on the SafaLaw website.\n\n"
            . "Name: {$data['full_name']}\n"
            . "Email: {$data['email']}\n"
            . "Phone: {$data['phone']}\n"
            . "Client type: {$data['client_type']}\n"
            . "Location: {$data['client_location']}\n"
            . "Practice area ID: {$data['practice_area_id']}\n"
            . "Preferred date: {$data['preferred_date']}\n"
            . "Preferred time: {$data['preferred_time']}\n\n"
            . "Legal profile:\n{$data['legal_profile']}\n\n"
            . "Client requirements:\n{$data['client_requirements']}\n\n"
            . "Message:\n{$data['message']}\n\n"
            . "Note: This is a consultation request, not a confirmed appointment.\n";

        Mail::raw($body, function ($mail) use ($notificationEmail, $data) {
            $mail->to($notificationEmail)
                ->subject('New SafaLaw Consultation Request')
                ->replyTo($data['email'], $data['full_name']);
        });

        $emailSent = true;
    } catch (\Throwable $exception) {
        report($exception);
    }
}

        return redirect()
    ->route('consultation')
    ->with(
        'status',
        $emailSent
            ? 'Your consultation request was saved and the email notification was submitted successfully. The appointment is not confirmed until the firm responds.'
            : 'Your consultation request was saved, but the email notification could not be sent. Please contact SAFALAW directly.'
    )
            ->with('recommended_attorney_ids', $recommendations->pluck('id')->all());
    }

    private function recommendAttorneys(int $practiceAreaId, string $location)
    {
        $location = mb_strtolower(trim($location));

        return Attorney::query()
            ->with('practiceArea')
            ->where('practice_area_id', $practiceAreaId)
            ->where('is_available', true)
            ->orderByDesc('sample_rating')
            ->orderByDesc('years_experience')
            ->get()
            ->filter(function (Attorney $attorney) use ($location) {
                $coverage = $attorney->coverage_areas ?? [];
                if ($location === '' || $coverage === [] || in_array('All Philippines', $coverage, true)) {
                    return true;
                }

                return collect($coverage)->contains(fn ($area) =>
                    str_contains($location, mb_strtolower($area))
                    || str_contains(mb_strtolower($area), $location)
                );
            })
            ->take(3)
            ->values();
    }
}
