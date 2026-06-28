<?php

namespace App\Services;

use App\Models\CategoryRule;

class CategoryService
{
    private array $rules = [];

    public function __construct()
    {
        $this->rules = CategoryRule::all()->toArray();
    }

    public function categorize(string $description): string
    {
        $upper       = strtoupper($description);
        $bestCat     = 'Não categorizado';
        $bestLen     = 0;

        foreach ($this->rules as $rule) {
            if (empty($rule['pattern'])) continue;
            if (@preg_match('/' . $rule['pattern'] . '/i', '') === 1) continue;
            if (@preg_match('/' . $rule['pattern'] . '/i', $upper, $matches) === 1) {
                $len = strlen($matches[0]);
                if ($len > $bestLen) {
                    $bestLen = $len;
                    $bestCat = $rule['category'];
                }
            }
        }

        return $bestCat;
    }

    public static function defaults(): array
    {
        return [
            ['pattern' => 'CARREFOUR|MERCADO|SUPERMERCADO|ATACADAO|ASSAI|EXTRA', 'category' => 'Mercado'],
            ['pattern' => 'FARMACIA|DROGARIA|DROGA|ULTRAFARMA',                  'category' => 'Farmácia'],
            ['pattern' => 'POSTO|COMBUSTIVEL|SHELL|IPIRANGA|PETROBRAS',          'category' => 'Combustível'],
            ['pattern' => 'IFOOD|RAPPI|UBER EATS|DELIVERY|PIZZA|LANCHE|KEETA|JAMES|GOOMER|AIQFOME', 'category' => 'Delivery'],
            ['pattern' => 'NETFLIX|SPOTIFY|AMAZON|APPLE|DISNEY|GLOBOPLAY',                        'category' => 'Assinaturas'],
            ['pattern' => 'UBER|99POP|TAXI|ONIBUS|METRO|BILHETE|UBERRIDES|CABIFY|BUSER',          'category' => 'Transporte'],
            ['pattern' => 'CERVEJA|BAR |BOTECO|RESTAURANTE|CHURRASCO',          'category' => 'Lazer'],
        ];
    }
}
