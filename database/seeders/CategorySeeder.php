<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Default categories seeded for every new user.
     * Keywords are matched case-insensitively against transaction descriptions.
     */
    public static array $defaults = [
        [
            'name'     => 'Alimentação',
            'color'    => '#f97316',
            'icon'     => '🍽️',
            'keywords' => [
                'IFOOD', 'RAPPI', 'UBER EATS', 'RESTAURANTE', 'LANCHONETE',
                'PADARIA', 'ACOUGUE', 'MERCADO', 'SUPERMERCADO', 'HORTIFRUTI',
                'ATACADO', 'CARREFOUR', 'EXTRA', 'PÃO DE AÇÚCAR', 'ASSAI',
                'MC DONALDS', 'MCDONALDS', 'BURGER KING', 'SUBWAY', 'KFC',
                'PIZZA', 'SUSHI',
            ],
        ],
        [
            'name'     => 'Transporte',
            'color'    => '#3b82f6',
            'icon'     => '🚗',
            'keywords' => [
                'UBER', '99POP', 'CABIFY', 'INDRIVER', 'TAXI',
                'COMBUSTIVEL', 'GASOLINA', 'ETANOL', 'POSTO ', 'SHELL',
                'IPIRANGA', 'BR DISTRIBUIDORA', 'ESTACIONAMENTO', 'PEDAGIO',
                'METRO', 'METRÔ', 'BILHETE UNICO', 'ONIBUS', 'ÔNIBUS',
            ],
        ],
        [
            'name'     => 'Moradia',
            'color'    => '#8b5cf6',
            'icon'     => '🏠',
            'keywords' => [
                'ALUGUEL', 'CONDOMINIO', 'CONDOMÍNIO', 'IPTU', 'IMOBILIARIA',
                'LUZ ', 'ENEL', 'CEMIG', 'COPEL', 'ENERGISA', 'EQUATORIAL',
                'AGUA ', 'SABESP', 'SANEPAR', 'EMBASA', 'CEDAE',
                'GAS ', 'COMGAS', 'CEGÁS',
                'INTERNET', 'CLARO', 'VIVO', 'TIM', 'OI ', 'NET ',
            ],
        ],
        [
            'name'     => 'Saúde',
            'color'    => '#10b981',
            'icon'     => '🏥',
            'keywords' => [
                'FARMACIA', 'FARMÁCIA', 'DROGARIA', 'DROGASIL', 'DROGA RAIA',
                'ULTRAFARMA', 'PANVEL', 'MEDICO', 'MÉDICO', 'CONSULTA',
                'HOSPITAL', 'CLINICA', 'CLÍNICA', 'LABORATORIO', 'LABORATÓRIO',
                'EXAME', 'PLANO DE SAUDE', 'SULAMERICA', 'AMIL', 'UNIMED',
                'BRADESCO SAUDE', 'HAPVIDA', 'NOTREDAME',
            ],
        ],
        [
            'name'     => 'Lazer',
            'color'    => '#ec4899',
            'icon'     => '🎭',
            'keywords' => [
                'NETFLIX', 'SPOTIFY', 'PRIME VIDEO', 'DISNEY', 'HBO', 'GLOBOPLAY',
                'CINEMA', 'CINEMARK', 'UCI ', 'CINEPOLIS', 'TEATR',
                'SHOW ', 'INGRESSO', 'TICKETMASTER', 'EVENTBRITE',
                'ACADEMIA', 'SMARTFIT', 'BLUEFIT', 'CROSSFIT',
                'BAR ', 'BALADA', 'NIGHT',
            ],
        ],
        [
            'name'     => 'Educação',
            'color'    => '#f59e0b',
            'icon'     => '📚',
            'keywords' => [
                'ESCOLA', 'COLEGIO', 'COLÉGIO', 'FACULDADE', 'UNIVERSIDADE',
                'CURSO', 'ALURA', 'UDEMY', 'COURSERA', 'DUOLINGO',
                'LIVRARIA', 'AMAZON LIVRO', 'SARAIVA',
            ],
        ],
        [
            'name'     => 'Compras',
            'color'    => '#06b6d4',
            'icon'     => '🛍️',
            'keywords' => [
                'AMAZON', 'MERCADO LIVRE', 'SHOPEE', 'AMERICANAS',
                'MAGAZINE LUIZA', 'MAGALU', 'CASAS BAHIA', 'RENNER',
                'RIACHUELO', 'SHEIN', 'ZARA', 'C&A', 'H&M',
                'ALIEXPRESS', 'WISH ', 'NETSHOES', 'CENTAURO',
            ],
        ],
        [
            'name'     => 'Serviços',
            'color'    => '#64748b',
            'icon'     => '⚙️',
            'keywords' => [
                'TARIFA', 'IOF', 'ANUIDADE', 'SEGURO', 'CARTORIO',
                'DETRAN', 'MULTA', 'IPVA',
                'GOOGLE', 'APPLE', 'MICROSOFT', 'ADOBE',
                'CONTADOR', 'ADVOGADO',
            ],
        ],
        [
            'name'     => 'Outros',
            'color'    => '#94a3b8',
            'icon'     => '📦',
            'keywords' => [],
        ],
    ];

    /**
     * Seed default categories for a specific user.
     * Called on new user registration and from DatabaseSeeder for dev.
     */
    public static function seedForUser(User $user): void
    {
        foreach (self::$defaults as $data) {
            Category::firstOrCreate(
                ['user_id' => $user->id, 'name' => $data['name']],
                [
                    'color'    => $data['color'],
                    'icon'     => $data['icon'],
                    'keywords' => $data['keywords'],
                ]
            );
        }
    }

    public function run(): void
    {
        User::all()->each(fn (User $user) => self::seedForUser($user));
    }
}
