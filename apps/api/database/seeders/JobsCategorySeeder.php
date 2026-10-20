<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class JobsCategorySeeder extends Seeder
{
    public function run(): void
    {
        // Apenas adiciona categorias em falta; nunca altera categorias ou preços existentes.
        foreach ([
            'Eletricidade',
            'CCTV e Segurança',
            'Canalização',
            'Design Gráfico',
            'Fotografia e Vídeo',
            'Manutenção Informática',
            'Redes e Telecomunicações',
            'Marketing Digital',
            'Programação e Websites',
            'Formação Profissional',
        ] as $name) {
            ServiceCategory::firstOrCreate(['name' => $name], ['active' => true]);
        }
    }
}
