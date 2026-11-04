<?php

namespace Database\Seeders;

use App\Models\Medicine;
use Illuminate\Database\Seeder;

/**
 * A small starter list, safe for production. The full catalogue is imported from
 * CSV in the Super Admin portal; these are generic names only.
 */
class MedicineCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['Paracetamol', '500 mg', 'Tablet'], ['Paracetamol', '650 mg', 'Tablet'], ['Ibuprofen', '400 mg', 'Tablet'],
            ['Amoxicillin', '500 mg', 'Capsule'], ['Azithromycin', '500 mg', 'Tablet'], ['Cefixime', '200 mg', 'Tablet'],
            ['Metformin', '500 mg', 'Tablet'], ['Glimepiride', '1 mg', 'Tablet'], ['Amlodipine', '5 mg', 'Tablet'],
            ['Telmisartan', '40 mg', 'Tablet'], ['Atorvastatin', '10 mg', 'Tablet'], ['Pantoprazole', '40 mg', 'Tablet'],
            ['Omeprazole', '20 mg', 'Capsule'], ['Domperidone', '10 mg', 'Tablet'], ['Ondansetron', '4 mg', 'Tablet'],
            ['Cetirizine', '10 mg', 'Tablet'], ['Levocetirizine', '5 mg', 'Tablet'], ['Montelukast', '10 mg', 'Tablet'],
            ['Salbutamol', '100 mcg', 'Inhaler'], ['Levothyroxine', '50 mcg', 'Tablet'], ['ORS', null, 'Sachet'],
            ['Vitamin D3', '60000 IU', 'Capsule'], ['Calcium Carbonate', '500 mg', 'Tablet'], ['Ferrous Ascorbate', '100 mg', 'Tablet'],
        ];

        foreach ($rows as [$generic, $strength, $form]) {
            Medicine::query()->firstOrCreate(['generic_name' => $generic, 'brand_name' => null, 'strength' => $strength], ['form' => $form]);
        }
    }
}
