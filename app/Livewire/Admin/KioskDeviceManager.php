<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\KioskDeviceKind;
use App\Models\KioskDevice;
use App\Models\Location;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
// Aliased: Livewire\Attributes\Url below is the same name to PHP.
use Illuminate\Support\Facades\URL as UrlFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Kiosk device management (route `admin.kiosk`), gated on `kiosk.manage`.
 *
 * Before this screen existed a device could only be enrolled by creating a
 * `kiosk_devices` row by hand — through a seeder or tinker — and then
 * knowing to visit `/kiosk/enrol/{id}` on the device itself. Nothing in the
 * UI said so, and a clean install has no devices at all, so `/kiosk` was a
 * flat 403 with no way forward.
 *
 * A kiosk is not necessarily a tablet. It may be a laptop on a bench, a panel
 * PC bolted to a machine, or a phone in a pocket, and the two enrolment
 * methods are not interchangeable between them:
 *
 *   - **Scan a QR code** — for anything carried to this screen. Leads for
 *     tablets and phones.
 *   - **Enrol this browser** — for the machine the administrator is sitting
 *     at, which cannot scan a code displayed on its own screen. Leads for
 *     laptops, desktops and anything unrecognised.
 *
 * `KioskDeviceKind` decides which is offered first; both stay available for
 * every kind, so guessing wrong costs a scroll rather than a dead end.
 *
 * Enrolment is by **temporary signed link** either way. The alternative —
 * logging in as an admin on the device — means typing an admin password on a
 * shared shop-floor machine in front of whoever is standing there.
 */
#[Layout('layouts::app')]
class KioskDeviceManager extends Component
{
    use AuthorizesRequests;

    /**
     * How long an enrolment link stays valid. Long enough to walk from the
     * office to the floor, short enough that a screenshot left in a chat is
     * useless by the time anyone finds it.
     */
    public const LINK_TTL_MINUTES = 15;

    /**
     * A device is "online" if it has been seen within this window. The
     * middleware touches `last_seen_at` at most once a minute per device.
     */
    private const ONLINE_WINDOW_MINUTES = 5;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'kind')]
    public string $kindFilter = '';

    // ── Create / edit form ───────────────────────────────────────────

    public ?int $editingId = null;

    public string $name = '';

    /** App\Enums\KioskDeviceKind value — what the hardware actually is. */
    public string $kind = 'tablet';

    public string $locationId = '';

    public bool $isActive = true;

    // ── Modals ───────────────────────────────────────────────────────

    public ?int $enrollingId = null;

    public ?int $deletingId = null;

    public ?int $revokingId = null;

    /**
     * The generated link, held only for as long as the modal is open.
     * Regenerated on each open so a stale one is never shown as current.
     */
    public string $enrolUrl = '';

    public ?string $enrolExpiresAt = null;

    public function mount(): void
    {
        $this->authorize('kiosk.manage');
    }

    // ── Data ─────────────────────────────────────────────────────────

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function enrollingDevice(): ?KioskDevice
    {
        return $this->enrollingId === null
            ? null
            : KioskDevice::query()->find($this->enrollingId);
    }

    #[Computed]
    public function deletingDevice(): ?KioskDevice
    {
        return $this->deletingId === null
            ? null
            : KioskDevice::query()->find($this->deletingId);
    }

    #[Computed]
    public function revokingDevice(): ?KioskDevice
    {
        return $this->revokingId === null
            ? null
            : KioskDevice::query()->find($this->revokingId);
    }

    /**
     * The enrolment QR, inline SVG. Same reasoning as the sticker sheet:
     * nothing is fetched at render time, so this works on a plant PC with no
     * outside network.
     */
    public function enrolSvg(): string
    {
        if ($this->enrolUrl === '') {
            return '';
        }

        return (string) QrCode::format('svg')
            ->size(220)
            ->margin(0)
            ->errorCorrection('M')
            ->generate($this->enrolUrl);
    }

    public function isOnline(KioskDevice $device): bool
    {
        return $device->last_seen_at !== null
            && $device->last_seen_at->gt(now()->subMinutes(self::ONLINE_WINDOW_MINUTES));
    }

    // ── Create / edit ────────────────────────────────────────────────

    public function openCreateModal(): void
    {
        $this->authorize('kiosk.manage');

        $this->resetForm();
        $this->dispatch('open-modal', name: 'kiosk-device-form');
    }

    public function openEditModal(int $deviceId): void
    {
        $this->authorize('kiosk.manage');

        $device = KioskDevice::query()->findOrFail($deviceId);

        $this->resetForm();
        $this->editingId = $device->id;
        $this->name = $device->name;
        $this->kind = $device->kind->value;
        $this->locationId = (string) ($device->location_id ?? '');
        $this->isActive = $device->is_active;

        $this->dispatch('open-modal', name: 'kiosk-device-form');
    }

    public function save(): void
    {
        // Re-authorise: a Livewire action is a public HTTP endpoint, so the
        // check in mount() alone is not enough.
        $this->authorize('kiosk.manage');

        $this->validate();

        $data = [
            'name' => trim($this->name),
            'kind' => $this->kind,
            'location_id' => $this->locationId !== '' ? (int) $this->locationId : null,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId !== null) {
            $device = KioskDevice::query()->findOrFail($this->editingId);

            DB::transaction(fn () => $device->update($data));

            session()->flash('flash.success', __('app.kiosk_devices.updated_message', ['name' => $device->name]));
        } else {
            // The token is generated here and never shown or edited: it is
            // the shared secret between this row and the tablet's cookie.
            $device = DB::transaction(fn (): KioskDevice => KioskDevice::create(
                $data + ['token' => Str::random(64)]
            ));

            session()->flash('flash.success', __('app.kiosk_devices.created_message', ['name' => $device->name]));
        }

        $this->dispatch('close-modal', name: 'kiosk-device-form');
        $this->resetForm();
    }

    /**
     * Flip a device on or off. Deactivating takes effect on the tablet's very
     * next request — EnsureKioskDevice resolves through an `active()` scope —
     * so this is the "that tablet has walked out of the building" button.
     */
    public function toggleActive(int $deviceId): void
    {
        $this->authorize('kiosk.manage');

        $device = KioskDevice::query()->findOrFail($deviceId);

        DB::transaction(fn () => $device->update(['is_active' => ! $device->is_active]));

        activity('kiosk')
            ->causedBy(auth()->user())
            ->performedOn($device)
            ->log($device->is_active ? 'kiosk.device_activated' : 'kiosk.device_deactivated');

        session()->flash('flash.success', $device->is_active
            ? __('app.kiosk_devices.activated_message', ['name' => $device->name])
            : __('app.kiosk_devices.deactivated_message', ['name' => $device->name]));
    }

    // ── Enrolment ────────────────────────────────────────────────────

    /**
     * Mint a fresh temporary signed enrolment URL and show it as a QR.
     *
     * Regenerated every time the modal opens rather than stored: a link that
     * expired while the modal sat open on somebody's second monitor must not
     * be presented as though it still works.
     */
    public function openEnrolModal(int $deviceId): void
    {
        $this->authorize('kiosk.manage');

        $device = KioskDevice::query()->findOrFail($deviceId);

        if (! $device->is_active) {
            session()->flash('flash.error', __('app.kiosk_devices.enrol_blocked_inactive', ['name' => $device->name]));

            return;
        }

        $expires = now()->addMinutes(self::LINK_TTL_MINUTES);

        $this->enrollingId = $device->id;
        $this->enrolUrl = UrlFacade::temporarySignedRoute('kiosk.enrol.link', $expires, ['device' => $device->id]);
        $this->enrolExpiresAt = $expires->toIso8601String();

        unset($this->enrollingDevice);

        $this->dispatch('open-modal', name: 'kiosk-device-enrol');
    }

    public function closeEnrolModal(): void
    {
        $this->reset('enrollingId', 'enrolUrl', 'enrolExpiresAt');
        unset($this->enrollingDevice);
    }

    /**
     * Rotate the device token, which invalidates the `kiosk_device` cookie on
     * every browser currently enrolled as this device — the middleware looks
     * the token up on each request, so the old one simply stops resolving.
     *
     * This is the lost-or-stolen-tablet action. The row survives so the
     * replacement tablet keeps the same name, location and history.
     */
    public function revokeEnrolment(): void
    {
        if ($this->revokingId === null) {
            return;
        }

        $this->authorize('kiosk.manage');

        $device = KioskDevice::query()->findOrFail($this->revokingId);

        DB::transaction(fn () => $device->update(['token' => Str::random(64)]));

        activity('kiosk')
            ->causedBy(auth()->user())
            ->performedOn($device)
            ->log('kiosk.device_token_rotated');

        session()->flash('flash.success', __('app.kiosk_devices.revoked_message', ['name' => $device->name]));

        $this->dispatch('close-modal', name: 'kiosk-device-revoke');
        $this->revokingId = null;
    }

    public function confirmRevoke(int $deviceId): void
    {
        $this->authorize('kiosk.manage');

        $this->revokingId = KioskDevice::query()->findOrFail($deviceId)->id;
        unset($this->revokingDevice);

        $this->dispatch('open-modal', name: 'kiosk-device-revoke');
    }

    // ── Delete ───────────────────────────────────────────────────────

    public function confirmDelete(int $deviceId): void
    {
        $this->authorize('kiosk.manage');

        $this->deletingId = KioskDevice::query()->findOrFail($deviceId)->id;
        unset($this->deletingDevice);

        $this->dispatch('open-modal', name: 'kiosk-device-delete');
    }

    /**
     * `kiosk_devices` is not a maintenance record — no run, issue or
     * signature references it — so this is a real delete, not a soft one.
     * The activity log keeps the history of what the device did.
     */
    public function deleteDevice(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $this->authorize('kiosk.manage');

        $device = KioskDevice::query()->findOrFail($this->deletingId);
        $name = $device->name;

        DB::transaction(fn () => $device->delete());

        activity('kiosk')
            ->causedBy(auth()->user())
            ->withProperties(['name' => $name])
            ->log('kiosk.device_deleted');

        session()->flash('flash.success', __('app.kiosk_devices.deleted_message', ['name' => $name]));

        $this->dispatch('close-modal', name: 'kiosk-device-delete');
        $this->deletingId = null;
    }

    // ── Render ───────────────────────────────────────────────────────

    public function render(): View
    {
        $devices = KioskDevice::query()
            ->with('location:id,name')
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.addcslashes($this->search, '\\%_').'%';

                $query->where('name', 'like', $term);
            })
            ->when($this->kindFilter !== '', fn (Builder $query) => $query->where('kind', $this->kindFilter))
            // Never-seen devices first: those are the ones still waiting to be
            // set up.
            ->orderByRaw('last_seen_at IS NULL DESC')
            ->orderByDesc('last_seen_at')
            ->orderBy('name')
            ->get();

        return view('livewire.admin.kiosk-device-manager', [
            'devices' => $devices,
            'kinds' => KioskDeviceKind::cases(),
            'onlineWindowMinutes' => self::ONLINE_WINDOW_MINUTES,
        ])->title(__('app.kiosk_devices.title'));
    }

    // ── Validation ───────────────────────────────────────────────────

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('kiosk_devices', 'name')->ignore($this->editingId),
            ],
            'kind' => ['required', Rule::enum(KioskDeviceKind::class)],
            'locationId' => ['nullable', Rule::exists('locations', 'id')],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('app.common.name'),
            'kind' => __('app.kiosk_devices.kind_label'),
            'locationId' => __('app.kiosk_devices.location'),
        ];
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'kind', 'locationId', 'isActive');
        $this->resetValidation();
    }
}
