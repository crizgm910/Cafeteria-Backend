<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\ReservationBlock;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReservationBlockController extends Controller
{
    public function index(Request $request)
    {
        $v = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date', 'service_area_id' => 'nullable|integer|exists:service_areas,id']);
        $q = ReservationBlock::with(['area', 'table', 'creator:id,name'])->orderBy('starts_at');
        if (isset($v['from'])) $q->where('ends_at', '>=', $v['from']); if (isset($v['to'])) $q->where('starts_at', '<=', $v['to']); if (isset($v['service_area_id'])) $q->where('service_area_id', $v['service_area_id']);
        return response()->json($q->get());
    }
    public function store(Request $request) { $d=$this->validated($request); $d['created_by']=$request->user()->id; return response()->json(ReservationBlock::create($d)->load(['area','table']), 201); }
    public function update(Request $request, ReservationBlock $reservationBlock) { $reservationBlock->update($this->validated($request, true, $reservationBlock)); return response()->json($reservationBlock->fresh(['area','table'])); }
    public function destroy(ReservationBlock $reservationBlock) { $reservationBlock->update(['active'=>false]); return response()->json(['message'=>'Bloqueo desactivado.']); }
    private function validated(Request $request, bool $partial=false, ?ReservationBlock $block=null): array
    {
        $required=$partial?'sometimes':'required';
        $d=$request->validate(['service_area_id'=>'nullable|integer|exists:service_areas,id','dining_table_id'=>'nullable|integer|exists:dining_tables,id','starts_at'=>"$required|date",'ends_at'=>"$required|date",'reason'=>"$required|string|max:500",'active'=>'sometimes|boolean']);
        $area=array_key_exists('service_area_id',$d)?$d['service_area_id']:$block?->service_area_id; $table=array_key_exists('dining_table_id',$d)?$d['dining_table_id']:$block?->dining_table_id;
        if(!$area&&!$table) throw ValidationException::withMessages(['service_area_id'=>'Selecciona un área o una mesa.']);
        if($table && $area && !DiningTable::whereKey($table)->where('service_area_id', $area)->exists()) throw ValidationException::withMessages(['dining_table_id'=>'La mesa no pertenece al área seleccionada.']);
        $start=$d['starts_at']??$block?->starts_at; $end=$d['ends_at']??$block?->ends_at; if($start&&$end&&strtotime((string)$end)<=strtotime((string)$start)) throw ValidationException::withMessages(['ends_at'=>'El fin debe ser posterior al inicio.']);
        return $d;
    }
}
