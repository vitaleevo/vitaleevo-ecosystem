<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Tecnologia e TI','Marketing e Design','Eletricidade','Automação Industrial','CCTV e Segurança','Formação Profissional'] as $name) {
            ServiceCategory::firstOrCreate(['name'=>$name], ['active'=>true]);
        }

        foreach ([
            ['code'=>'trial','name'=>'Teste','price_kz'=>0],
            ['code'=>'basic','name'=>'Básico','price_kz'=>50000],
            ['code'=>'advanced','name'=>'Avançado','price_kz'=>200000],
        ] as $plan) {
            Plan::updateOrCreate(['code'=>$plan['code']], $plan + ['active'=>true]);
        }
    }
}
