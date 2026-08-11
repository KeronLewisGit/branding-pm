<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Frequency;
use App\Enums\ResponseType;
use App\Enums\WorkCategory;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use App\Models\Machine;
use Illuminate\Database\Seeder;

class ChecklistTemplateSeeder extends Seeder
{
    /**
     * The 13 templates transcribed VERBATIM from docs/SPEC.md (one per source
     * paper sheet). Do not "fix" anything here:
     *
     * - Wording, capitalisation and punctuation are exactly as printed —
     *   `Clean media Clamps`, `Clean substrate Belt`, `Tooth Brush {Soft}`
     *   style braces, `HP R2000 [FB]`, the space in "Daily Maintenance of the
     *   HP 800 W" (seed-notes B5), `RollsRoller` one word in the template
     *   name vs `Rolls Roller` two words as the machine name (B6),
     *   "Follow Outlined Checks" vs "Follow Checks Outlined" (B9), and
     *   template 3's circular item 6 (B10). All intentional — see
     *   docs/seed-notes.md §B before touching any string in this file.
     *
     * `category` drives the schedule per seed-notes C1/C4:
     *   daily   → frequency daily,  per_shift true  (one run per shift)
     *   weekly  → frequency weekly, per_shift false
     *   general → frequency weekly, per_shift false ("general" describes the
     *             sheet, not a cadence — an ungenerated template produces no
     *             compliance record, so general sheets run weekly on Monday)
     * All weekly generation lands on Monday (weekly_weekday = 1, C5).
     *
     * @var list<array{machine: string, name: string, category: string, description: string, items: list<string>}>
     */
    private const TEMPLATES = [
        // 1 ──────────────────────────────────────────────────────────────
        [
            'machine' => 'matan',
            'name' => 'MATAN — Daily Maintenance',
            'category' => 'daily',
            'description' => 'Daily Maintenance of the MATAN',
            'items' => [
                'Cleaning the Vacuum Table',
                'Cleaning the Measure Media Sensor',
                'Cleaning the Ink Sink',
                'Emptying the Ink Collector',
                'Replacing the UV Filters',
                'Empty Bins',
                'Dust Frame',
                'Sweep Around Machine',
                'Remove any End Rolls and Neaten up WIP roll storage',
            ],
        ],
        // 2 ──────────────────────────────────────────────────────────────
        [
            'machine' => 'matan',
            'name' => 'MATAN — Weekly Maintenance',
            'category' => 'weekly',
            'description' => 'Weekly Maintenance of the MATAN',
            'items' => [
                'Cleaning the Vacuum Table',
                'Cleaning the Measure Media Sensor',
                'Cleaning the Ink Sink',
                'Emptying the Ink Collector',
                'Replacing the UV Filters',
                'Draining the Filter Separators',
                'Draining the Oil Filter Separator',
                'Draining the Water Filter Separator',
                'Cleaning the Free-fall Rollers',
                'Cleaning the Print Table Fan(s) on the Carriage',
                'Clean the Ionizer Bars',
                'Clean Mist Fans',
                'Lubricating the Carriage Bearings',
                'Clean Around Machine',
                'Empty Bins',
                'Mop Floor',
            ],
        ],
        // 3 ──────────────────────────────────────────────────────────────
        [
            'machine' => 'hp-stitch-1000',
            'name' => 'HP Stitch 1000 — Daily Maintenance',
            'category' => 'daily',
            'description' => 'Daily Maintenance of the HP Stitch 1000',
            'items' => [
                'Clean - Dust - Ink - Spots on machine',
                'Clean Pinch Rollers',
                'Sweep around Machine',
                'Clean Platen',
                'Check & Clean Print Head Fringe',
                'Follow Checks Outlined', // yes, as a task item — seed-notes B10
            ],
        ],
        // 4 ──────────────────────────────────────────────────────────────
        [
            'machine' => 'hp-r2000-fb',
            'name' => 'HP R2000 [FB] — Daily Maintenance',
            'category' => 'daily',
            'description' => 'Daily Maintenance of the HP R2000 [FB]',
            'items' => [
                'Clean - Dust - Ink - Spots on machine',
                'Clean substrate Belt',
                'Clean Print Heads',
            ],
        ],
        // 5 ──────────────────────────────────────────────────────────────
        [
            'machine' => 'hp-570-latex',
            'name' => 'HP 570 Latex — Daily Maintenance',
            'category' => 'daily',
            'description' => 'Daily Maintenance of the HP 570 Latex',
            'items' => [
                'Clean - Dust - Ink - Spots on machine',
                'Clean Pinch Rollers',
                'Clean media Clamps',
                'Clean Platen',
                'Sweep around Machine',
                'Clean Encoder',
            ],
        ],
        // 6 ── identical list to template 5 by design (two sheets, two
        //      machines, two sign-off trails — seed-notes B4). Description
        //      keeps the "HP 800 W" space (B5).
        [
            'machine' => 'hp-800w',
            'name' => 'HP 800W — Daily Maintenance',
            'category' => 'daily',
            'description' => 'Daily Maintenance of the HP 800 W',
            'items' => [
                'Clean - Dust - Ink - Spots on machine',
                'Clean Pinch Rollers',
                'Clean media Clamps',
                'Clean Platen',
                'Sweep around Machine',
                'Clean Encoder',
            ],
        ],
        // 7 ──────────────────────────────────────────────────────────────
        [
            'machine' => 'esko-c64-kongsberg',
            'name' => 'ESKO C64 Kongsberg — General Maintenance',
            'category' => 'general',
            'description' => 'Consult User manual where Required',
            'items' => [
                'Clean Dust Machine using Vacuum',
                'Clean around Machine',
                'Check Water Traps on Air Line',
                'Clean Guide-way & rails',
                'Apply Light Grease and Oil to Guide-way & rails',
                'Clean Carriage assembly of any dust buildup',
                'Check Vacuum bin and Empty as needed',
                'Empty Waste Bins',
                'Tidy up work station and around machine',
                'Check water level in chiller',
                'Clean tools and tool box',
                'Dust and wipe computer table',
            ],
        ],
        // 8 ──────────────────────────────────────────────────────────────
        [
            'machine' => 'hiker-grommet-machine',
            'name' => 'Hiker Grommet Machine — General Maintenance',
            'category' => 'general',
            'description' => 'Follow Outlined Checks',
            'items' => [
                'Check the UP and DOWN movement - To be smooth',
                'Dust Frame',
                'Clean around Machine',
            ],
        ],
        // 9 ──────────────────────────────────────────────────────────────
        [
            'machine' => 'mistral-1650-65-laminator',
            'name' => 'Mistral 1650-65 Laminator — General Maintenance',
            'category' => 'general',
            'description' => 'Follow Outlined Checks',
            'items' => [
                'Wipe Rollers with IPA/Water solution',
                'Dust and clean Frame',
                'Empty Bins',
                'Clean around Machine',
            ],
        ],
        // 10 ─────────────────────────────────────────────────────────────
        [
            'machine' => 'miller-112-cross-seamer',
            'name' => 'Miller 112 Cross Seamer — General Maintenance',
            'category' => 'general',
            'description' => 'Follow Outlined Checks',
            'items' => [
                'Clean around Machine and Empty Bins',
                'Check Air Line / system for any leaks',
                'Clean Overhead Track and apply light Grease',
                'Clean Air Filters as per Guidelines in Manual',
                'Check Belts Once Per Month', // carries its own frequency — seed-notes B7
                'Check Weld Roller for any Damage',
                'Check Micro Switch for Proper Operation',
                'Check Laser Lights are Operating and aligned',
                'Clean Machine Frame',
            ],
        ],
        // 11 ─────────────────────────────────────────────────────────────
        [
            'machine' => 'monti-antonio',
            'name' => 'Monti Antonio — Daily Maintenance',
            'category' => 'daily',
            'description' => 'Follow Checks Outlined',
            'items' => [
                'Dust and clean around machine',
            ],
        ],
        // 12 ─────────────────────────────────────────────────────────────
        [
            'machine' => 'monti-antonio',
            'name' => 'Monti Antonio — Weekly Maintenance',
            'category' => 'weekly',
            'description' => 'Follow Checks Outlined',
            'items' => [
                'Wipe rollers and frame with damp cloth',
                'Mop floor around machine',
            ],
        ],
        // 13 ── "RollsRoller" one word in the title, machine name is
        //       "Rolls Roller" — both verbatim, see seed-notes B6.
        [
            'machine' => 'rolls-roller',
            'name' => 'RollsRoller — Mounting Table, General Maintenance',
            'category' => 'general',
            'description' => 'Follow Outlined Checks',
            'items' => [
                'Dust Frame',
                'Clean around Machine',
                'Bleed and Check Compressor under Table',
                'Clean and Wipe Table surface with IPA/Water solution',
                'Check Bearing Track and apply Grease as needed',
            ],
        ],
    ];

    public function run(): void
    {
        $machineIds = Machine::pluck('id', 'code');

        foreach (self::TEMPLATES as $definition) {
            $category = WorkCategory::from($definition['category']);

            // C1: daily → daily; weekly and general → weekly (Monday).
            $frequency = $category === WorkCategory::Daily
                ? Frequency::Daily
                : Frequency::Weekly;

            $template = ChecklistTemplate::firstOrCreate(
                [
                    'machine_id' => $machineIds[$definition['machine']],
                    'name' => $definition['name'],
                ],
                [
                    'work_category' => $category,
                    'work_description' => $definition['description'],
                    'frequency' => $frequency,
                    'per_shift' => $category === WorkCategory::Daily, // C4
                    'weekly_weekday' => 1, // Monday — C5
                    'monthly_day' => 1,
                    'requires_supervisor_signoff' => true,
                    'grace_period_hours' => 24, // C6
                    'version' => 1,
                    'is_active' => true,
                ],
            );

            // Items only on first creation — re-seeding must not
            // duplicate rows or clobber edits made in the template builder.
            if (! $template->wasRecentlyCreated) {
                continue;
            }

            foreach ($definition['items'] as $sortOrder => $description) {
                ChecklistTemplateItem::create([
                    'checklist_template_id' => $template->id,
                    'sort_order' => $sortOrder, // 0-based, printed order
                    'description' => $description,
                    'response_type' => ResponseType::Check,
                    'is_required' => true,
                    'guidance' => null,
                    'requires_photo_on_fail' => false,
                    'is_active' => true,
                ]);
            }

        }
    }
}
