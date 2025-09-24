<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Stock;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);


        // Sample Customers
        Customer::create(['name' => 'Ganesh Silva', 'email' => 'ganesh.silva@gmail.com']);
        Customer::create(['name' => 'Anushka Perera', 'email' => 'anushka.perera@yahoo.com']);
        Customer::create(['name' => 'Nihal Rajapaksha', 'email' => 'nihal.rajapaksha@hotmail.com']);
        Customer::create(['name' => 'Sitha Ranasinghe', 'email' => 'sitha.ranasinghe@gmail.com']);
        Customer::create(['name' => 'Janaka Gunaratne', 'email' => 'janaka.gunaratne@yahoo.com']);

         // Sample Products and Stocks
        $product1 = Product::create(['name' => 'Smartphone - Galaxy S21', 'price' => 800.00]);
        Stock::create(['product_id' => $product1->id, 'quantity' => 50]);

        $product2 = Product::create(['name' => 'Laptop - Dell XPS 13', 'price' => 1200.00]);
        Stock::create(['product_id' => $product2->id, 'quantity' => 30]);

        $product3 = Product::create(['name' => 'Wireless Headphones - Bose QuietComfort', 'price' => 300.00]);
        Stock::create(['product_id' => $product3->id, 'quantity' => 70]);

        $product4 = Product::create(['name' => 'Smartwatch - Apple Watch Series 7', 'price' => 400.00]);
        Stock::create(['product_id' => $product4->id, 'quantity' => 40]);

        $product5 = Product::create(['name' => 'Bluetooth Speaker - JBL Flip 5', 'price' => 100.00]);
        Stock::create(['product_id' => $product5->id, 'quantity' => 80]);

        $product6 = Product::create(['name' => '4K TV - LG OLED55CX', 'price' => 1500.00]);
        Stock::create(['product_id' => $product6->id, 'quantity' => 20]);

        $product7 = Product::create(['name' => 'Digital Camera - Canon EOS 90D', 'price' => 1300.00]);
        Stock::create(['product_id' => $product7->id, 'quantity' => 25]);

        $product8 = Product::create(['name' => 'Gaming Console - PlayStation 5', 'price' => 500.00]);
        Stock::create(['product_id' => $product8->id, 'quantity' => 60]);

        $product9 = Product::create(['name' => 'Smartphone - iPhone 13', 'price' => 999.00]);
        Stock::create(['product_id' => $product9->id, 'quantity' => 45]);

        $product10 = Product::create(['name' => 'Tablet - iPad Pro', 'price' => 799.00]);
        Stock::create(['product_id' => $product10->id, 'quantity' => 35]);
    }
}
