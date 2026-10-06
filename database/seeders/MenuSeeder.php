<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuRole;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Menus de l'espace web (administrateur). Le gestionnaire utilise l'application mobile.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = Role::where('name', 'ADMIN')->value('id');

        $menus = [
            ['code' => 'dashboard',     'title' => 'Tableau de bord', 'url' => '/dashboard',     'icon' => 'bi-grid-1x2-fill',    'breadcrumbs' => false],
            ['code' => 'tenants',       'title' => 'Entreprises',     'url' => '/tenants',       'icon' => 'bi-buildings-fill',   'breadcrumbs' => true],
            ['code' => 'users',         'title' => 'Utilisateurs',    'url' => '/users',         'icon' => 'bi-people-fill',      'breadcrumbs' => true],
            ['code' => 'subscriptions', 'title' => 'Abonnements',     'url' => '/subscriptions', 'icon' => 'bi-calendar-check-fill', 'breadcrumbs' => true],
            ['code' => 'subscription-plans', 'title' => 'Forfaits',   'url' => '/subscription-plans', 'icon' => 'bi-tags-fill', 'breadcrumbs' => true],
        ];

        foreach ($menus as $position => $menu) {
            $model = Menu::updateOrCreate(
                ['code' => $menu['code']],
                $menu + ['type' => 'item', 'classes' => 'nav-item', 'position' => $position + 1],
            );

            MenuRole::firstOrCreate(['menu_id' => $model->id, 'role_id' => $adminId]);
        }

        // Anciens écrans du gestionnaire (désormais sur mobile)
        Menu::whereIn('code', ['products', 'categories', 'sales'])->delete();
    }
}
