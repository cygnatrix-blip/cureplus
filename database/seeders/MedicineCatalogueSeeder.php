<?php

namespace Database\Seeders;

use App\Models\Medicine;
use App\Support\Specialties;
use Illuminate\Database\Seeder;

/**
 * Generic medicines for every department. Safe for production and repeatable: it adds what is
 * missing and files what is already there, without touching brands or anything an admin added.
 */
class MedicineCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $existing = Medicine::query()->whereNull('brand_name')->get(['id', 'generic_name', 'strength', 'form'])
            ->keyBy(fn (Medicine $medicine) => $this->key($medicine->generic_name, $medicine->strength, $medicine->form));
        $new = [];

        foreach (self::rows() as $row) {
            $key = $this->key($row['generic_name'], $row['strength'], $row['form']);

            if (isset($existing[$key])) {
                Medicine::query()->whereKey($existing[$key]->id)->update(['system' => $row['system'], 'specialty' => $row['specialty']]);

                continue;
            }

            $new[] = $row + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()];
        }

        foreach (array_chunk($new, 200) as $chunk) {
            Medicine::query()->insert($chunk);
        }
    }

    /**
     * @return list<array{generic_name: string, strength: string|null, form: string, specialty: string, system: string}>
     */
    public static function rows(): array
    {
        $groups = array_merge(require __DIR__.'/data/medicines_allopathy.php', require __DIR__.'/data/medicines_traditional.php');
        $rows = [];

        foreach ($groups as $specialty => $lines) {
            foreach ($lines as $line) {
                [$name, $strengths, $form] = explode('|', $line);

                foreach ($strengths === '' ? [null] : explode(',', $strengths) as $strength) {
                    $rows[self::key($name, $strength, $form)] ??= [
                        'generic_name' => $name,
                        'strength' => $strength,
                        'form' => $form,
                        'specialty' => $specialty,
                        'system' => (string) Specialties::systemOf($specialty),
                    ];
                }
            }
        }

        return array_values($rows);
    }

    private static function key(string $name, ?string $strength, ?string $form): string
    {
        return mb_strtolower("{$name}|{$strength}|{$form}");
    }
}
