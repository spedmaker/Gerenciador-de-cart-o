<?php

use App\Models\CategoryRule;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private array $rules = [
        ['pattern' => 'NETFLIX|SPOTIFY|AMAZON|APPLE|DISNEY|GLOBOPLAY',              'category' => 'Assinaturas'],
        ['pattern' => 'POSTO|COMBUSTIVEL|SHELL|IPIRANGA|PETROBRAS',                 'category' => 'Combustível'],
        ['pattern' => 'SHOPEE|MERCADOLIVRE|SHEIN',                                  'category' => 'Compras online'],
        ['pattern' => 'IFOOD|RAPPI|KEETA|JAMES',                                    'category' => 'Delivery'],
        ['pattern' => 'FARMACIA|DROGARIA|DROGA|ULTRAFARMA|Medipreco',               'category' => 'Farmácia'],
        ['pattern' => 'CERVEJA|BAR |BOTECO|RESTAURANTE|CHURRASCO',                  'category' => 'Lazer'],
        ['pattern' => 'FISIA|NFS',                                                   'category' => 'Lentes'],
        ['pattern' => 'CARREFOUR|MERCADO|SUPERMERCADO|ATACADAO|ASSAI|EXTRA',        'category' => 'Mercado'],
        ['pattern' => 'LUCICLEIDE|PANIFICADORA',                                    'category' => 'Padaria'],
        ['pattern' => 'Wellhub|Gympass',                                             'category' => 'Produtos academia'],
        ['pattern' => 'PIZZARIA|lanchonete|BURGER KING|LIVORNO',                    'category' => 'Refeição'],
        ['pattern' => 'UBER|99POP|TAXI|ONIBUS|METRO|BILHETE|UBERRIDES|CABIFY|BUSER', 'category' => 'Transporte'],
    ];

    public function up(): void
    {
        foreach ($this->rules as $rule) {
            CategoryRule::firstOrCreate(['category' => $rule['category']], $rule);
        }
    }

    public function down(): void
    {
        $categories = array_column($this->rules, 'category');
        CategoryRule::whereIn('category', $categories)->delete();
    }
};
