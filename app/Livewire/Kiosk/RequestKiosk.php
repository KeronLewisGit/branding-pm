<?php

declare(strict_types=1);

namespace App\Livewire\Kiosk;

use App\Http\Controllers\Kiosk\KioskEnrolmentRequestController;
use App\Http\Middleware\EnsureKioskDevice;
use App\Support\DeviceType;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Ask for this browser to become a kiosk" — the operator's side.
 *
 * A Livewire component rather than a controller view, and that is not a
 * stylistic preference. `layouts.app` sizes its sidebar through
 * `x-bind:class`, and Alpine arrives ONLY inside Livewire's injected script —
 * the same trap `kiosk/activate.blade.php` documents for its device fields.
 * Rendered from a plain controller this page shipped no Livewire script, so
 * Alpine never booted, the aside kept its static `w-full` and `md:!flex`, and
 * the sidebar filled the screen with the page pushed off the bottom: a blank
 * screen behind a fully extended menu.
 *
 * Every other office screen is a full-page Livewire component. This one being
 * the exception is what broke it, so it stops being the exception.
 *
 * The form still POSTs to KioskEnrolmentRequestController. Submitting is a
 * one-shot action that must set cookies and redirect, which a plain form does
 * better than a component round trip.
 */
#[Layout('layouts::app')]
class RequestKiosk extends Component
{
    public function render(): View
    {
        return view('livewire.kiosk.request-kiosk', [
            // An operator on an already-enrolled browser has nothing to ask
            // for; the chrome does not offer this, but a bookmark might.
            'alreadyEnrolled' => EnsureKioskDevice::enrolledDevice(request()) !== null,
            'pending' => KioskEnrolmentRequestController::currentRequest(request()),
            'suggestedName' => $this->suggestName(),
        ])->title(__('app.kiosk_requests.title'));
    }

    /**
     * A name a supervisor can recognise, so the request does not arrive called
     * "Tablet". Overridable by the operator, and again by the reviewer.
     */
    private function suggestName(): string
    {
        $type = DeviceType::detect(request()->userAgent());

        /*
         * Capitalised here, not in the language file. Those labels are written
         * lower case because they are used mid-sentence ("this tablet is not
         * enrolled"); dropped into a name field as-is they produced
         * "tablet — Darnell Joseph", which reads like a bug rather than a
         * suggestion. An unrecognised User-Agent gives "Device", which is
         * honest about knowing nothing.
         */
        return __('app.kiosk_requests.suggested_name', [
            'type' => Str::ucfirst($type->label()),
            'user' => auth()->user()?->full_name ?? '',
        ]);
    }
}
