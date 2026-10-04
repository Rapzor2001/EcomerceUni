<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            ['name' => 'Hoodies', 'description' => 'Capas pesadas para días fríos y noches largas.', 'image_path' => 'photo-1556821840-3a63f95609a7'],
            ['name' => 'Camisetas', 'description' => 'Gráficos limpios, cortes amplios y algodón premium.', 'image_path' => 'photo-1521572163474-6864f9cf17ab'],
            ['name' => 'Pantalones', 'description' => 'Siluetas cargo y denim para movimiento diario.', 'image_path' => 'photo-1506629905607-d405d7d7f5c3'],
            ['name' => 'Chaquetas', 'description' => 'Piezas exteriores para completar el uniforme.', 'image_path' => 'photo-1591047139829-d91aecb6caea'],
            ['name' => 'Accesorios', 'description' => 'Los detalles que hacen personal cada look.', 'image_path' => 'photo-1521369909029-2afed882baee'],
        ])->mapWithKeys(function (array $category) {
            $category['slug'] = Str::slug($category['name']);
            $category['is_active'] = true;

            return [$category['name'] => Category::updateOrCreate(['slug' => $category['slug']], $category)];
        });

        $products = [
            ['Hoodies', 'Hoodie Obsidian', 239900, 24, 'Hoodie oversized de 420 gsm con interior afelpado, bordado frontal y capucha estructurada.', 'photo-1556821840-3a63f95609a7'],
            ['Hoodies', 'Hoodie Concrete Zip', 259900, 18, 'Hoodie con cierre completo, fit relajado y acabados desgastados.', 'photo-1578681994506-b8f463449011'],
            ['Hoodies', 'Hoodie Signal Sand', 229900, 21, 'Hoodie color arena con gráfico tonal y puños reforzados.', 'photo-1620799140408-edc6dcb6d633'],
            ['Camisetas', 'Camiseta After Dark', 119900, 42, 'Camiseta boxy de algodón pesado con estampado frontal monocromático.', 'photo-1521572163474-6864f9cf17ab'],
            ['Camisetas', 'Camiseta Static Black', 109900, 38, 'Camiseta negra relaxed fit con detalle gráfico posterior.', 'photo-1503341504253-dff4815485f1'],
            ['Camisetas', 'Camiseta Core White', 99900, 46, 'Básico premium de cuello alto y caída recta.', 'photo-1622445275576-721325763afe'],
            ['Camisetas', 'Camiseta Chrome Grey', 119900, 29, 'Jersey gris de tacto lavado con tipografía reflectiva.', 'photo-1627225924765-552d49cf47ad'],
            ['Pantalones', 'Cargo Utility Black', 249900, 19, 'Pantalón cargo amplio con seis bolsillos y ajuste en el tobillo.', 'photo-1506629905607-d405d7d7f5c3'],
            ['Pantalones', 'Denim Ash Straight', 229900, 16, 'Jean de pierna recta en lavado gris ceniza y costuras contrastantes.', 'photo-1541099649105-f69ad21f3246'],
            ['Pantalones', 'Pantalón Nylon Transit', 219900, 23, 'Pantalón técnico liviano, repelente al agua y de secado rápido.', 'photo-1517841905240-472988babdf9'],
            ['Chaquetas', 'Chaqueta Varsity Night', 329900, 12, 'Chaqueta varsity de paño, mangas contrastantes y parches bordados.', 'photo-1551028719-00167b16eac5'],
            ['Chaquetas', 'Bomber Phantom', 349900, 14, 'Bomber acolchada de fit corto con bolsillo utilitario en manga.', 'photo-1591047139829-d91aecb6caea'],
            ['Chaquetas', 'Chaqueta Coach Shadow', 279900, 20, 'Chaqueta coach impermeable con cierre a presión y logo minimal.', 'photo-1551488831-00ddcb6c6bd3'],
            ['Accesorios', 'Gorra District 6 Panel', 89900, 35, 'Gorra estructurada con bordado frontal y cierre metálico.', 'photo-1521369909029-2afed882baee'],
            ['Accesorios', 'Beanie Essential', 69900, 40, 'Beanie acanalado de punto suave con etiqueta tejida.', 'photo-1523779917675-b6ed3a42a561'],
            ['Accesorios', 'Bolso Crossbody Grid', 149900, 27, 'Bolso crossbody compacto con compartimentos internos y correa ajustable.', 'photo-1553062407-98eeb64c6a62'],
            ['Accesorios', 'Medias Logo Pack', 49900, 60, 'Pack de tres pares de medias deportivas con tejido acolchado.', 'photo-1582966772680-860e372bb558'],
            ['Accesorios', 'Cadena Steel Link', 79900, 32, 'Cadena de acero inoxidable con acabado pulido.', 'photo-1611652022419-a9419f74343d'],
        ];

        foreach ($products as [$category, $name, $price, $stock, $description, $image]) {
            Product::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['category_id' => $categories[$category]->id, 'name' => $name, 'description' => $description, 'price' => $price, 'stock' => $stock, 'images' => ["https://images.unsplash.com/{$image}?auto=format&fit=crop&w=1200&q=85"], 'is_active' => true]
            );
        }

        User::updateOrCreate(['email' => 'admin@noirdistrict.test'], ['name' => 'Administrador District', 'phone' => '3000000001', 'shipping_address' => 'Calle 93 # 13-45, Bogotá, Colombia', 'role' => 'admin', 'email_verified_at' => now(), 'password' => Hash::make('Admin123!')]);
        User::updateOrCreate(['email' => 'cliente@noirdistrict.test'], ['name' => 'Cliente District', 'phone' => '3000000002', 'shipping_address' => 'Carrera 7 # 72-41, Bogotá, Colombia', 'role' => 'customer', 'email_verified_at' => now(), 'password' => Hash::make('Cliente123!')]);
    }
}
