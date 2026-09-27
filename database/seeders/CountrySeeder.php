<?php

namespace Database\Seeders;

use App\Models\MasterListItem;
use Illuminate\Database\Seeder;

/**
 * Spec B1's Countries and Nationalities lists, seeded with the standard
 * 195-country list (rather than left for an Admin to type in by hand) so
 * both dropdowns are usable out of the box. Nationality mirrors the same
 * list — this prototype doesn't distinguish "citizen of Nigeria" from
 * "Nigerian" as separate concepts — but stays its own admin-manageable
 * master list per spec's explicit "Nationalities, Countries" wording.
 */
class CountrySeeder extends Seeder
{
    public const COUNTRIES = [
        'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua & Barbuda', 'Argentina',
        'Armenia', 'Australia', 'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh',
        'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia',
        'Bosnia & Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso',
        'Burundi', 'Cabo Verde', 'Cambodia', 'Cameroon', 'Canada', 'Central African Republic',
        'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo (Congo-Brazzaville)', 'Costa Rica',
        'Croatia', 'Cuba', 'Cyprus', 'Czechia', 'Democratic Republic of the Congo', 'Denmark',
        'Djibouti', 'Dominica', 'Dominican Republic', 'Ecuador', 'Egypt', 'El Salvador',
        'Equatorial Guinea', 'Eritrea', 'Estonia', 'Eswatini', 'Ethiopia', 'Fiji', 'Finland',
        'France', 'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada',
        'Guatemala', 'Guinea', 'Guinea-Bissau', 'Guyana', 'Haiti', 'Holy See', 'Honduras',
        'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy',
        'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati', 'Kuwait', 'Kyrgyzstan',
        'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania',
        'Luxembourg', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta',
        'Marshall Islands', 'Mauritania', 'Mauritius', 'Mexico', 'Micronesia', 'Moldova', 'Monaco',
        'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar', 'Namibia', 'Nauru', 'Nepal',
        'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea',
        'North Macedonia', 'Norway', 'Oman', 'Pakistan', 'Palau', 'Palestine State', 'Panama',
        'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar',
        'Romania', 'Russia', 'Rwanda', 'Saint Kitts & Nevis', 'Saint Lucia',
        'Saint Vincent & the Grenadines', 'Samoa', 'San Marino', 'Sao Tome & Principe',
        'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia',
        'Slovenia', 'Solomon Islands', 'Somalia', 'South Africa', 'South Korea', 'South Sudan',
        'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden', 'Switzerland', 'Syria', 'Tajikistan',
        'Tanzania', 'Thailand', 'Timor-Leste', 'Togo', 'Tonga', 'Trinidad & Tobago', 'Tunisia',
        'Turkey', 'Turkmenistan', 'Tuvalu', 'Uganda', 'Ukraine', 'United Arab Emirates',
        'United Kingdom', 'United States of America', 'Uruguay', 'Uzbekistan', 'Vanuatu',
        'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe',
    ];

    public function run(): void
    {
        foreach ([MasterListItem::TYPE_COUNTRY, MasterListItem::TYPE_NATIONALITY] as $type) {
            foreach (self::COUNTRIES as $i => $name) {
                MasterListItem::firstOrCreate(
                    ['type' => $type, 'name' => $name],
                    ['sort_order' => $i, 'is_active' => true]
                );
            }
        }
    }
}
