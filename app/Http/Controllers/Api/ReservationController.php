<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\ServiceArea;
use App\Services\ReservationAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    private const TRANSITIONS = [
        'pending' => ['approved', 'cancelled'],
        'approved' => ['checked_in', 'cancelled', 'no_show'],
        'checked_in' => ['seated', 'cancelled'],
        'seated' => ['completed'],
        'ready' => ['completed', 'cancelled'],
        'cancelled' => [], 'completed' => [], 'no_show' => [],
    ];

    public function index(Request $request)
    {
        $v=$request->validate(['status'=>'nullable|in:all,pending,approved,checked_in,seated,ready,cancelled,completed,no_show','date'=>'nullable|in:all,today,tomorrow,week','service_area_id'=>'nullable|integer|exists:service_areas,id','per_page'=>'nullable|integer|min:1|max:100']);
        $q=Reservation::with(['area:id,name','table:id,service_area_id,code,name,max_capacity','assigner:id,name']);
        if(isset($v['status'])&&$v['status']!=='all')$q->where('status',$v['status']);
        if(isset($v['service_area_id']))$q->where('service_area_id',$v['service_area_id']);
        if(isset($v['date'])&&$v['date']!=='all'){
            $today=now()->toDateString();
            if($v['date']==='today')$q->whereDate('date',$today);
            elseif($v['date']==='tomorrow')$q->whereDate('date',now()->addDay()->toDateString());
            else $q->whereBetween('date',[$today,now()->addDays(7)->toDateString()]);
        }
        $q->orderBy('date')->orderBy('time')->orderBy('id');
        return response()->json(isset($v['per_page'])?$q->paginate($v['per_page']):$q->get());
    }

    public function store(Request $request, ReservationAvailabilityService $availability)
    {
        $request->merge(['idempotency_key'=>trim((string)$request->header('Idempotency-Key'))]);
        $v=$request->validate([
            'idempotency_key'=>'required|string|min:16|max:100','service_area_id'=>'required|integer|exists:service_areas,id',
            'name'=>'required|string|max:255','email'=>'required|email|max:255','phone'=>['required','string','max:30','regex:/^[0-9+()\-\s]{7,30}$/'],
            'date'=>'required|date_format:Y-m-d|after_or_equal:today','time'=>'required|date_format:H:i','guests'=>'required|integer|min:1|max:20',
        ]);
        $payload=['service_area_id'=>(int)$v['service_area_id'],'name'=>trim($v['name']),'email'=>Str::lower(trim($v['email'])),'phone'=>trim($v['phone']),'date'=>$v['date'],'time'=>$v['time'],'guests'=>(int)$v['guests']];
        $fingerprint=hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE));
        if($existing=Reservation::where('idempotency_key',$v['idempotency_key'])->first())return $this->idempotentResponse($existing,$fingerprint,true);

        try {
            $reservation=DB::transaction(function()use($payload,$v,$fingerprint,$availability){
                $area=ServiceArea::lockForUpdate()->findOrFail($payload['service_area_id']);
                $start=CarbonImmutable::createFromFormat('Y-m-d H:i',$payload['date'].' '.$payload['time'],config('app.timezone'));
                if(!$start->isFuture())throw ValidationException::withMessages(['time'=>'La reservación debe ser para una hora futura.']);
                [$table,$start,$end]=$availability->assign($area,$start,$payload['guests']);
                return Reservation::create($payload+['dining_table_id'=>$table->id,'starts_at'=>$start,'ends_at'=>$end,'status'=>'pending','idempotency_key'=>$v['idempotency_key'],'request_fingerprint'=>$fingerprint]);
            },3);
        } catch(UniqueConstraintViolationException $e){
            $reservation=Reservation::where('idempotency_key',$v['idempotency_key'])->first(); if(!$reservation)throw $e;
            return $this->idempotentResponse($reservation,$fingerprint,true);
        }
        return $this->idempotentResponse($reservation,$fingerprint,false);
    }

    public function assign(Request $request, Reservation $reservation, ReservationAvailabilityService $availability)
    {
        $v=$request->validate(['dining_table_id'=>'required|integer|exists:dining_tables,id','lock_version'=>'required|integer|min:0','staff_notes'=>'nullable|string|max:1000']);
        $updated=DB::transaction(function()use($request,$reservation,$v,$availability){
            $reservation=Reservation::lockForUpdate()->findOrFail($reservation->id);
            if($reservation->lock_version!==(int)$v['lock_version'])throw ValidationException::withMessages(['lock_version'=>'La reservación cambió. Actualiza la vista.']);
            if(!in_array($reservation->status,ReservationAvailabilityService::BLOCKING_STATUSES,true))throw ValidationException::withMessages(['status'=>'La reservación ya no admite reasignación.']);
            $table=DiningTable::lockForUpdate()->findOrFail($v['dining_table_id']);
            if($table->max_capacity<$reservation->guests||$table->min_capacity>$reservation->guests)throw ValidationException::withMessages(['dining_table_id'=>'La capacidad de la mesa no es compatible.']);
            if(!$availability->isTableAvailable($table,CarbonImmutable::instance($reservation->starts_at),CarbonImmutable::instance($reservation->ends_at),$reservation->id))throw ValidationException::withMessages(['dining_table_id'=>'La mesa no está disponible para ese intervalo.']);
            $reservation->update(['service_area_id'=>$table->service_area_id,'dining_table_id'=>$table->id,'staff_notes'=>$v['staff_notes']??$reservation->staff_notes,'assigned_by'=>$request->user()->id,'lock_version'=>$reservation->lock_version+1]);
            return $reservation->fresh(['area','table','assigner']);
        },3);
        return response()->json(['message'=>'Mesa asignada.','reservation'=>$updated]);
    }

    public function updateStatus(Request $request, Reservation $reservation)
    {
        $v=$request->validate(['status'=>'required|in:approved,checked_in,seated,ready,cancelled,completed,no_show','lock_version'=>'nullable|integer|min:0','staff_notes'=>'nullable|string|max:1000']);
        $updated=DB::transaction(function()use($request,$reservation,$v){
            $reservation=Reservation::lockForUpdate()->findOrFail($reservation->id);
            if(isset($v['lock_version'])&&$reservation->lock_version!==(int)$v['lock_version'])throw ValidationException::withMessages(['lock_version'=>'La reservación cambió. Actualiza la vista.']);
            if(!in_array($v['status'],self::TRANSITIONS[$reservation->status]??[],true))throw ValidationException::withMessages(['status'=>"No se permite pasar de {$reservation->status} a {$v['status']}."]);
            $timestamps=['approved'=>'approved_at','checked_in'=>'checked_in_at','seated'=>'seated_at','completed'=>'completed_at','cancelled'=>'cancelled_at','no_show'=>'no_show_at'];
            $data=['status'=>$v['status'],'lock_version'=>$reservation->lock_version+1,'assigned_by'=>$request->user()->id];
            if(isset($timestamps[$v['status']]))$data[$timestamps[$v['status']]]=now();
            if(array_key_exists('staff_notes',$v))$data['staff_notes']=$v['staff_notes'];
            $reservation->update($data);
            return $reservation->fresh(['area','table','assigner']);
        },3);
        return response()->json(['message'=>'Estado de reservación actualizado.','reservation'=>$updated]);
    }

    private function idempotentResponse(Reservation $reservation,string $fingerprint,bool $replayed)
    {
        if(!hash_equals((string)$reservation->request_fingerprint,$fingerprint))return response()->json(['message'=>'La clave de idempotencia ya fue utilizada con otra reservación.','code'=>'IDEMPOTENCY_CONFLICT'],409);
        $reservation=$reservation->fresh(['area','table']) ?? $reservation->loadMissing(['area','table']);
        return response()->json(['message'=>$replayed?'Reservación recuperada sin duplicarla.':'Solicitud de reservación recibida.','reservation'=>$reservation,'idempotent_replay'=>$replayed],$replayed?200:201);
    }
}
