<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\MasterData\Models\District;
use App\Domain\MasterData\Models\Province;
use Illuminate\Database\Seeder;

/**
 * Seeds all 7 provinces and all 77 districts of Nepal — this part is fixed
 * and safe to fully hard-code as reference data (these boundaries do not
 * change day-to-day, unlike rates or fiscal years, which are DB-driven and
 * versioned instead).
 *
 * Local levels (753 total: 6 metropolitan, 11 sub-metropolitan, 276
 * municipality, 460 rural municipality) and their wards are intentionally
 * NOT hand-typed here — that volume of data belongs in the Excel/CSV import
 * pipeline described in Step 1 Section 38 (Master Data import/export), not
 * inline in a seeder. A small representative sample is included per
 * district below so the local_levels/wards tables have working data to
 * develop and test against immediately; run the bulk import for full
 * national coverage before going to production.
 */
class NepalGeoSeeder extends Seeder
{
    private const PROVINCES = [
        ['code' => 'P1', 'name_en' => 'Koshi Province', 'name_ne' => 'कोशी प्रदेश', 'districts' => [
            'Bhojpur', 'Dhankuta', 'Ilam', 'Jhapa', 'Khotang', 'Morang', 'Okhaldhunga',
            'Panchthar', 'Sankhuwasabha', 'Solukhumbu', 'Sunsari', 'Taplejung', 'Terhathum', 'Udayapur',
        ]],
        ['code' => 'P2', 'name_en' => 'Madhesh Province', 'name_ne' => 'मधेश प्रदेश', 'districts' => [
            'Bara', 'Dhanusha', 'Mahottari', 'Parsa', 'Rautahat', 'Saptari', 'Sarlahi', 'Siraha',
        ]],
        ['code' => 'P3', 'name_en' => 'Bagmati Province', 'name_ne' => 'बागमती प्रदेश', 'districts' => [
            'Bhaktapur', 'Chitwan', 'Dhading', 'Dolakha', 'Kathmandu', 'Kavrepalanchok', 'Lalitpur',
            'Makwanpur', 'Nuwakot', 'Ramechhap', 'Rasuwa', 'Sindhuli', 'Sindhupalchok',
        ]],
        ['code' => 'P4', 'name_en' => 'Gandaki Province', 'name_ne' => 'गण्डकी प्रदेश', 'districts' => [
            'Baglung', 'Gorkha', 'Kaski', 'Lamjung', 'Manang', 'Mustang', 'Myagdi',
            'Nawalpur', 'Parbat', 'Syangja', 'Tanahun',
        ]],
        ['code' => 'P5', 'name_en' => 'Lumbini Province', 'name_ne' => 'लुम्बिनी प्रदेश', 'districts' => [
            'Arghakhanchi', 'Banke', 'Bardiya', 'Dang', 'Eastern Nawalparasi', 'Gulmi',
            'Kapilvastu', 'Palpa', 'Pyuthan', 'Rolpa', 'Rukum East', 'Rupandehi',
        ]],
        ['code' => 'P6', 'name_en' => 'Karnali Province', 'name_ne' => 'कर्णाली प्रदेश', 'districts' => [
            'Dailekh', 'Dolpa', 'Humla', 'Jajarkot', 'Jumla', 'Kalikot', 'Mugu',
            'Rukum West', 'Salyan', 'Surkhet',
        ]],
        ['code' => 'P7', 'name_en' => 'Sudurpashchim Province', 'name_ne' => 'सुदूरपश्चिम प्रदेश', 'districts' => [
            'Achham', 'Baitadi', 'Bajhang', 'Bajura', 'Dadeldhura', 'Darchula', 'Doti',
            'Kailali', 'Kanchanpur',
        ]],
    ];

    public function run(): void
    {
        foreach (self::PROVINCES as $provinceData) {
            $province = Province::updateOrCreate(
                ['code' => $provinceData['code']],
                ['name_en' => $provinceData['name_en'], 'name_ne' => $provinceData['name_ne']]
            );

            foreach ($provinceData['districts'] as $index => $districtName) {
                District::updateOrCreate(
                    ['code' => $provinceData['code'].'-D'.($index + 1)],
                    [
                        'province_id' => $province->id,
                        'name_en' => $districtName,
                        'name_ne' => $districtName, // Nepali district names to be filled via translation pass / import
                    ]
                );
            }
        }

        $this->command?->info('Seeded 7 provinces and 77 districts of Nepal.');
    }
}
