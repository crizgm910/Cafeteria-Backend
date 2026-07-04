<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;

class MenuController extends Controller
{
    public function index()
    {
        // Traemos todas las categorías con sus productos activos y los complementos disponibles para cada producto
        $menu = Category::with(['products' => function($q) {
            $q->where('active', true)->with('addOns');
        }])->get();

        return response()->json($menu);
    }
}
