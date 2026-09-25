<?php

namespace Database\Seeders;

use App\Models\Sanction;
use Illuminate\Database\Seeder;

class SanctionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Breach of Confidentiality',
            'Call Avoidance',
            'Data Privacy Violation',
            'Failure to meet the quota/target',
            'Gross neglect of duty',
            'Insubordination',
            'Invalid Absence',
            'No Call No Show (NCNS)',
            'Sleeping during work hours',
            'Tardiness',
        ] as $name) {
            Sanction::query()->firstOrCreate(['name' => $name], ['description' => $name === 'Invalid Absence'
                ? 'An absence from a scheduled work shift that is not properly reported, approved, or supported by valid documentation in accordance with company policies and procedures. This includes failure to notify the immediate supervisor within the required timeframe, absence without authorization, or inability to provide acceptable justification for the missed workday. Such occurrences may be subject to attendance-related corrective or disciplinary action.'
                : null]);
        }
    }
}
