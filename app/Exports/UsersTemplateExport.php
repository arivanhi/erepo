<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UsersTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['John Doe', 'john@student.univ.edu', 'mahasiswa', '21090120140001'],
            ['Dr. Smith', 'smith@lecturer.univ.edu', 'dosen', '198001012000011001']
        ];
    }

    public function headings(): array
    {
        return ['Name', 'Email', 'Role', 'NIM/NPP'];
    }
}
