<?php

declare(strict_types=1);

namespace App\Livewire\Kiosk;

use App\Enums\KioskDeviceKind;
use App\Enums\KioskRequestStatus;
use App\Models\KioskDevice;
use App\Models\KioskEnrolmentRequest;
use App\Support\DeviceType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

/**
 * The review queue for operators' kiosk requests.
 *
 * Gated on `kiosk.activate`, not `kiosk.manage`. Those are different powers
 * and the seeder says so: `kiosk.activate` is "may turn a device into a
 * kiosk" and belongs to supervisors, who are the people actually on the floor
 * when somebody asks; `kiosk.manage` is the fleet screen — renaming, rotating
 * tokens, revoking — and is admin territory. Approving a request is the first
 * of those, so putting this queue on the fleet screen would have hidden it
 * from every supervisor who can act on it.
 */
class EnrolmentRequests extends Component
{
    /** Request being approved, and the name it will be enrolled under. */
    public ?int $approvingId = null;

    public string $deviceName = '';

    /** Request being declined, and why. */
    public ?int $decliningId = null;

    public string $declineReason = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('kiosk.activate') === true, 403);
    }

    public function startApprove(int $id): void
    {
        $request = $this->openRequest($id);

        if ($request === null) {
            return;
        }

        $this->approvingId = $id;
        // Pre-filled with what the operator suggested: they are looking at the
        // device, the reviewer usually is not.
        $this->deviceName = $request->suggested_name;
        $this->decliningId = null;
    }

    public function startDecline(int $id): void
    {
        if ($this->openRequest($id) === null) {
            return;
        }

        $this->decliningId = $id;
        $this->declineReason = '';
        $this->approvingId = null;
    }

    public function cancel(): void
    {
        $this->reset(['approvingId', 'deviceName', 'decliningId', 'declineReason']);
    }

    /**
     * Approve: create the device, and leave it for the asking browser to
     * redeem. No cookie is set here — the reviewer is not sitting at the
     * tablet, and a device enrolled to the reviewer's browser would be the
     * wrong browser and would also log them out of their own session.
     */
    public function approve(): void
    {
        abort_unless(auth()->user()?->can('kiosk.activate') === true, 403);

        $this->validate([
            'deviceName' => ['required', 'string', 'max:120'],
        ], [], ['deviceName' => __('app.kiosk_devices.name')]);

        $request = $this->openRequest((int) $this->approvingId);

        if ($request === null) {
            $this->cancel();

            return;
        }

        DB::transaction(function () use ($request): void {
            $device = KioskDevice::create([
                'name' => $this->deviceName,
                'token' => Str::random(64),
                'kind' => $this->kindFor(DeviceType::detect($request->user_agent)),
                'is_active' => true,
            ]);

            $request->forceFill([
                'status' => KioskRequestStatus::Approved,
                'reviewed_by_id' => auth()->id(),
                'reviewed_at' => now(),
                'kiosk_device_id' => $device->id,
            ])->save();
        });

        activity('kiosk')
            ->causedBy(auth()->user())
            ->performedOn($request)
            ->log('kiosk.enrolment_request_approved');

        $this->cancel();

        session()->flash('status', __('app.kiosk_requests.approved_flash'));
    }

    public function decline(): void
    {
        abort_unless(auth()->user()?->can('kiosk.activate') === true, 403);

        $this->validate([
            // Required: "no" without a reason is the kind of answer that gets
            // asked again tomorrow.
            'declineReason' => ['required', 'string', 'max:500'],
        ], [], ['declineReason' => __('app.kiosk_requests.decline_reason')]);

        $request = $this->openRequest((int) $this->decliningId);

        if ($request === null) {
            $this->cancel();

            return;
        }

        $request->forceFill([
            'status' => KioskRequestStatus::Declined,
            'reviewed_by_id' => auth()->id(),
            'reviewed_at' => now(),
            'decline_reason' => $this->declineReason,
        ])->save();

        activity('kiosk')
            ->causedBy(auth()->user())
            ->performedOn($request)
            ->log('kiosk.enrolment_request_declined');

        $this->cancel();

        session()->flash('status', __('app.kiosk_requests.declined_flash'));
    }

    /**
     * A request that is still open. Anything already decided is returned as
     * null so a double-tap, or a second reviewer, cannot decide it twice.
     */
    private function openRequest(int $id): ?KioskEnrolmentRequest
    {
        return KioskEnrolmentRequest::query()
            ->whereKey($id)
            ->pending()
            ->first();
    }

    private function kindFor(DeviceType $type): KioskDeviceKind
    {
        return match ($type) {
            DeviceType::Tablet => KioskDeviceKind::Tablet,
            DeviceType::Phone => KioskDeviceKind::Phone,
            DeviceType::Computer => KioskDeviceKind::Desktop,
            default => KioskDeviceKind::Other,
        };
    }

    public function render(): View
    {
        return view('livewire.kiosk.enrolment-requests', [
            'pendingRequests' => KioskEnrolmentRequest::query()
                ->with('requestedBy:id,full_name,employee_number')
                ->pending()
                // Oldest first: somebody has been waiting longest.
                ->orderBy('created_at')
                ->get(),
            'decided' => KioskEnrolmentRequest::query()
                ->with(['requestedBy:id,full_name', 'reviewedBy:id,full_name', 'device:id,name'])
                ->whereNot('status', KioskRequestStatus::Pending->value)
                ->orderByDesc('reviewed_at')
                ->limit(10)
                ->get(),
        ])->layout('layouts.app');
    }
}
