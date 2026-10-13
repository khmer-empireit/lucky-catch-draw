<?php

namespace Database\Seeders;

use App\Enums\CampaignStatus;
use App\Enums\RoleName;
use App\Jobs\Import\CommitImportJob;
use App\Jobs\Import\ValidateImportJob;
use App\Models\Campaign;
use App\Models\Import;
use App\Models\User;
use App\Services\Audit\Auditor;
use App\Services\Import\FileInspector;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class DemoCampaignSeeder extends Seeder
{
    /**
     * A representative mix of real Khmer given + family names, used
     * alongside Faker's English names for a realistic bilingual
     * participant list.
     *
     * @var list<string>
     */
    private const KHMER_NAMES = [
        'សុខ ដារា', 'ចាន់ សុភា', 'លី សុវណ្ណា', 'ហេង សុខា', 'ពៅ រតនា',
        'ញឹម សារាវុធ', 'គឹម ស្រីម៉ុម', 'ទេព វណ្ណា', 'ឱម សុវណ្ណារ៉ាត់', 'គង់ ចាន់ថា',
        'ផល្គុន ចន្ទតារា', 'សាន សុខលីម', 'យិន ច័ន្ទនីរដ្ឋ', 'វង្ស ពិសិដ្ឋ', 'នួន សុផល',
        'ហ៊ុន ម៉ានី', 'ឡេង សុភារិទ្ធ', 'អ៊ឹង សីហា', 'ប៊ុន រតនៈ', 'ស៊ុន លក្ខិណា',
    ];

    public function run(): void
    {
        if (Campaign::where('name', 'Khmer New Year Lucky Draw')->exists()) {
            return;
        }

        $admin = User::whereHas('roles', fn ($q) => $q->where('name', RoleName::SuperAdmin->value))->first();

        if (! $admin) {
            return;
        }

        $campaign = Campaign::create([
            'name' => 'Khmer New Year Lucky Draw',
            'description' => 'Demo campaign seeded with a realistic bilingual participant list for local QA and demos.',
            'start_at' => now()->subDays(3),
            'end_at' => now()->addDays(4),
            'winners_to_select' => 5,
            'allow_repeat_winners' => false,
            'prize_name' => 'MacBook Air M4',
            'status' => CampaignStatus::Draft,
            'created_by' => $admin->id,
        ]);

        $rows = $this->buildRows();
        $relativePath = 'imports/'.Str::uuid().'.xlsx';
        $absolutePath = $this->writeXlsx($rows, $relativePath);

        $import = Import::create([
            'campaign_id' => $campaign->id,
            'original_file_name' => 'khmer-new-year-participants.xlsx',
            'stored_path' => $relativePath,
            'file_sha256' => FileInspector::sha256($absolutePath),
            'status' => 'mapped',
            'column_map' => ['name' => 0, 'profile_url' => 1, 'email' => 2, 'phone' => 3, 'external_id' => 4],
            'created_by' => $admin->id,
        ]);

        (new ValidateImportJob($import->id))->handle();
        (new CommitImportJob($import->id, $admin->id))->handle(app(Auditor::class));

        $campaign->update([
            'status' => CampaignStatus::Ready,
            'participants_locked_at' => now(),
        ]);

        app(Auditor::class)->log('campaign.list_confirmed', $campaign, [
            'eligible_count' => $campaign->eligibleParticipants()->count(),
            'seeded' => true,
        ], $admin);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function buildRows(): array
    {
        $rows = [['Participant Name', 'Profile URL', 'Email', 'Phone Number', 'Participant ID']];

        $platforms = [
            fn (string $handle) => "https://facebook.com/{$handle}",
            fn (string $handle) => "https://youtube.com/@{$handle}",
            fn (string $handle) => "https://www.tiktok.com/@{$handle}",
        ];

        $usedHandles = [];
        $valid = [];

        for ($i = 1; $i <= 150; $i++) {
            $isKhmer = $i % 3 === 0;
            $name = $isKhmer
                ? self::KHMER_NAMES[array_rand(self::KHMER_NAMES)]
                : fake()->name();

            do {
                $handle = fake()->unique()->userName();
            } while (isset($usedHandles[$handle]));
            $usedHandles[$handle] = true;

            $url = $platforms[$i % 3]($handle);
            $email = $i % 4 === 0 ? '' : fake()->safeEmail();
            $phone = $i % 5 === 0 ? '' : '+855 '.fake()->numerify('## ### ###');
            $externalId = 'P-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);

            $row = [$name, $url, $email, $phone, $externalId];
            $rows[] = $row;
            $valid[] = $row;
        }

        // A handful of intentional duplicates — decorated with www./m.
        // prefixes, trailing slashes, and tracking params — to demonstrate
        // normalization-based duplicate detection during the real import.
        $duplicateSources = array_rand($valid, 6);

        foreach ((array) $duplicateSources as $index) {
            [$name, $url] = $valid[$index];
            $decorated = str($url)
                ->replace('https://www.', 'https://')
                ->replace('https://', 'https://www.')
                ->append('/?utm_source=newsletter&utm_campaign=cny')
                ->toString();

            $rows[] = [$name.' (dup)', $decorated, '', '', ''];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function writeXlsx(array $rows, string $relativePath): string
    {
        Storage::disk('local')->makeDirectory(dirname($relativePath));
        $absolutePath = Storage::disk('local')->path($relativePath);

        $writer = new Writer();
        $writer->openToFile($absolutePath);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $absolutePath;
    }
}
