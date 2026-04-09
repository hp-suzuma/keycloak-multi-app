<?php

namespace Database\Seeders;

use App\Models\RouteAssignment;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->defaultAssignments() as $assignment) {
            RouteAssignment::query()->updateOrCreate(
                ['sub' => $assignment['sub']],
                $assignment,
            );
        }
    }

    private function defaultAssignments(): array
    {
        return [
            [
                'sub' => 'tenant-user-a',
                'display_name' => 'Alice default assignment',
                'site_code' => 'A',
                'server_url' => 'https://a.example.com',
                'is_active' => true,
                'priority' => 100,
                'notes' => 'Seeded default route for alice.',
            ],
            [
                'sub' => 'tenant-user-b',
                'display_name' => 'Bob default assignment',
                'site_code' => 'B',
                'server_url' => 'https://b.example.com',
                'is_active' => true,
                'priority' => 100,
                'notes' => 'Seeded default route for bob.',
            ],
        ];
    }
}
