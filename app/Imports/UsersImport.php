<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UsersImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        if (! isset($row['email'])) {
            return null;
        }

        $nim_npp = $row['nimnpp'] ?? $row['nim_npp'] ?? $row['nim'] ?? $row['npp'] ?? null;

        $rawRole = strtolower(trim((string) ($row['role'] ?? 'mahasiswa')));
        $normalizedRole = str_replace([' ', '-'], '_', $rawRole);
        $allowedRoles = ['admin', 'mahasiswa', 'dosen', 'kaprodi', 'koordinator_ta'];
        $role = in_array($normalizedRole, $allowedRoles, true) ? $normalizedRole : 'mahasiswa';

        return User::firstOrCreate(
            ['email' => $row['email']],
            [
                'name' => $row['name'] ?? 'Unknown User',
                'role' => $role,
                'identity_number' => $nim_npp,
                'password' => Hash::make('knowledgebank123'),
            ]
        );
    }
}
