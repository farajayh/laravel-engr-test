<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InsurerSeeder extends Seeder
{
    private $insurers = [
        [
            'name'=>'Insurer A', 
            'code'=> 'INS-A',
            'claim_date_preference' => 'submission_date',
            'daily_processing_capacity' => 1000,
            'min_batch_size' => 10, 
            'max_batch_size' => 20,
            'specialty' => 'Cardiology'
        ],
        [
            'name'=>'Insurer B', 
            'code'=> 'INS-B',
            'claim_date_preference' => 'submission_date',
            'daily_processing_capacity' => 2000,
            'min_batch_size' => 2, 
            'max_batch_size' => 10,
            'specialty' => 'Neurology'
        ],
        [
            'name'=>'Insurer C',
            'code'=> 'INS-C',
            'claim_date_preference' => 'encounter_date',
            'daily_processing_capacity' => 600,
            'min_batch_size' => 1, 
            'max_batch_size' => 7,
            'specialty' => 'Oncology'
        ],
        [
            'name'=>'Insurer D',
            'code'=> 'INS-D',
            'claim_date_preference' => 'encounter_date',
            'daily_processing_capacity' => 1500,
            'min_batch_size' => 3, 
            'max_batch_size' => 8,
            'specialty' => 'Orthopedics'
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('insurers')->insert($this->insurers);
    }
}
