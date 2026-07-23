param(
    [int]$Workers = 8,
    [int]$BasePort = 8110,
    [string]$Schema = '',
    [switch]$SkipSetup,
    [switch]$KeepSchema,
    [string]$ResultPath = ''
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$php = 'C:\xampp\php\php.exe'
$schema = if ($Schema -ne '') { $Schema } else { 'tgr_concurrency_' + (Get-Date -Format 'yyyyMMddHHmmss') }
$servers = @()
$logFiles = @()
$failure = $null
$resultData = [ordered]@{ Schema = $schema; Stage = 'starting'; Result = 'RUNNING' }

function Invoke-Artisan {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)

    & $php artisan @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Artisan falló: $($Arguments -join ' ')"
    }
}

function Invoke-ConcurrentPost {
    param(
        [string]$Path,
        [string]$Body,
        [string[]]$Ports,
        [string]$Authorization = '',
        [string]$SharedIdempotencyKey = '',
        [switch]$UniqueIdempotencyKeys
    )

    $useUniqueKeys = $UniqueIdempotencyKeys.IsPresent
    0..9 | ForEach-Object -Parallel {
        $portList = $using:Ports
        $port = $portList[$_ % $portList.Count]
        $headers = @{ Accept = 'application/json' }
        if ($using:Authorization -ne '') {
            $headers.Authorization = $using:Authorization
        }
        if ($using:useUniqueKeys) {
            $headers['Idempotency-Key'] = "concurrency-unique-$($_)-$([guid]::NewGuid())"
        } elseif ($using:SharedIdempotencyKey -ne '') {
            $headers['Idempotency-Key'] = $using:SharedIdempotencyKey
        }

        $response = Invoke-WebRequest `
            -Uri "http://127.0.0.1:$port$($using:Path)" `
            -Method Post `
            -ContentType 'application/json' `
            -Headers $headers `
            -Body $using:Body `
            -SkipHttpErrorCheck

        $json = $null
        try { $json = $response.Content | ConvertFrom-Json } catch {}
        [pscustomobject]@{
            Status = [int]$response.StatusCode
            TicketId = $json.ticket_id
            Code = $json.code
            Error = $json.error
        }
    } -ThrottleLimit $Ports.Count
}

Push-Location $projectRoot
try {
    if (-not (Test-Path -LiteralPath $php)) {
        throw "No se encontró PHP de XAMPP en $php"
    }

    $env:APP_ENV = 'testing'
    $env:APP_DEBUG = 'false'
    $env:DB_CONNECTION = 'pgsql'
    $env:DB_SCHEMA = $schema

    if (-not $SkipSetup) {
        Invoke-Artisan tinker "--execute=DB::statement('CREATE SCHEMA $schema')"
        Write-Host "STAGE: schema_created"
        Invoke-Artisan migrate:fresh --seed --force --no-ansi
        Write-Host "STAGE: migrated_and_seeded"

    $setup = @'
$user = App\Models\User::create([
    'name' => 'Concurrency Owner',
    'email' => 'concurrency@example.test',
    'password' => Illuminate\Support\Facades\Hash::make('ConcurrencyOnly123'),
    'active' => true,
]);
$user->roles()->attach(App\Models\Role::where('slug', 'owner')->firstOrFail()->id);
$category = App\Models\Category::firstOrCreate(['slug' => 'concurrency'], ['name' => 'Concurrency']);
$station = App\Models\KitchenStation::firstOrCreate(['name' => 'Concurrency Station']);
$ingredient = App\Models\Ingredient::create([
    'sku' => 'ING-CONCURRENCY',
    'name' => 'Concurrency Stock',
    'unit_of_measure' => 'pieza',
    'current_stock' => 5,
    'minimum_stock' => 0,
    'cost_per_unit' => 1,
]);
$product = App\Models\Product::create([
    'sku' => 'PROD-CONCURRENCY',
    'name' => 'Concurrency Product',
    'description' => 'Producto de prueba temporal',
    'price' => 10,
    'category_id' => $category->id,
    'kitchen_station_id' => $station->id,
    'active' => true,
]);
$product->ingredients()->attach($ingredient->id, ['quantity_required' => 1]);
'@
        Invoke-Artisan tinker "--execute=$setup"
        Write-Host "STAGE: fixtures_created"
    }
    $resultData.Stage = 'fixtures_ready'

    $ports = 0..($Workers - 1) | ForEach-Object { [string]($BasePort + $_) }
    foreach ($port in $ports) {
        $stdout = Join-Path $env:TEMP "tgr-concurrency-$port.out.log"
        $stderr = Join-Path $env:TEMP "tgr-concurrency-$port.err.log"
        $logFiles += $stdout, $stderr
        $servers += Start-Process `
            -FilePath $php `
            -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', "--port=$port") `
            -WorkingDirectory $projectRoot `
            -WindowStyle Hidden `
            -RedirectStandardOutput $stdout `
            -RedirectStandardError $stderr `
            -PassThru
    }

    foreach ($port in $ports) {
        $ready = $false
        1..40 | ForEach-Object {
            if (-not $ready) {
                try {
                    $health = Invoke-WebRequest -Uri "http://127.0.0.1:$port/api/health" -SkipHttpErrorCheck
                    $ready = $health.StatusCode -eq 200
                } catch {
                    Start-Sleep -Milliseconds 250
                }
            }
        }
        if (-not $ready) { throw "El worker HTTP $port no inició." }
    }
    Write-Host "STAGE: workers_ready"
    $resultData.Stage = 'workers_ready'

    $loginBody = @{ email = 'concurrency@example.test'; password = 'ConcurrencyOnly123' } | ConvertTo-Json
    $login = Invoke-RestMethod -Uri "http://127.0.0.1:$($ports[0])/api/login" -Method Post -ContentType 'application/json' -Body $loginBody
    $authorization = "Bearer $($login.token)"
    Write-Host "STAGE: authenticated"
    $resultData.Stage = 'authenticated'

    $menu = Invoke-RestMethod -Uri "http://127.0.0.1:$($ports[0])/api/menu"
    $products = @($menu | ForEach-Object { $_.products })
    $regularProduct = $products | Where-Object { $_.sku -eq 'PROD-LATTE-16' } | Select-Object -First 1
    $limitedProduct = $products | Where-Object { $_.sku -eq 'PROD-CONCURRENCY' } | Select-Object -First 1
    if (-not $regularProduct -or -not $limitedProduct) { throw 'No se encontraron los productos de prueba.' }

    $sharedPayload = @{
        items = @(@{ product_id = $regularProduct.id; quantity = 1 })
        payment_method = 'pay_at_pickup'
        customer_name = 'Concurrency Idempotency'
        customer_phone = '5551234567'
        order_type = 'takeout'
    } | ConvertTo-Json -Depth 5
    $sharedKey = "concurrency-shared-$([guid]::NewGuid())"
    $idempotencyResults = @(Invoke-ConcurrentPost -Path '/api/checkout' -Body $sharedPayload -Ports $ports -SharedIdempotencyKey $sharedKey)

    $created = @($idempotencyResults | Where-Object Status -eq 201).Count
    $replayed = @($idempotencyResults | Where-Object Status -eq 200).Count
    $ticketIds = @($idempotencyResults.TicketId | Where-Object { $_ } | Sort-Object -Unique)
    $resultData.IdempotencyStatuses = @($idempotencyResults.Status)
    $resultData.IdempotencyCreated = $created
    $resultData.IdempotencyReplayed = $replayed
    $resultData.IdempotencyUniqueTickets = $ticketIds.Count
    if ($created -ne 1 -or $replayed -ne 9 -or $ticketIds.Count -ne 1) {
        throw "Idempotencia concurrente inválida: created=$created replayed=$replayed uniqueTickets=$($ticketIds.Count)"
    }
    Write-Host "STAGE: idempotency_passed"
    $resultData.Stage = 'idempotency_passed'

    $openBody = @{ opening_amount = 100 } | ConvertTo-Json
    $cashResults = @(Invoke-ConcurrentPost -Path '/api/cash-register/open' -Body $openBody -Ports $ports -Authorization $authorization)
    $cashCreated = @($cashResults | Where-Object Status -eq 201).Count
    $cashConflicts = @($cashResults | Where-Object Status -eq 409).Count
    $resultData.CashStatuses = @($cashResults.Status)
    $resultData.CashCreated = $cashCreated
    $resultData.CashConflicts = $cashConflicts
    if ($cashCreated -ne 1 -or $cashConflicts -ne 9) {
        throw "Apertura concurrente inválida: created=$cashCreated conflicts=$cashConflicts"
    }
    Write-Host "STAGE: cash_register_passed"
    $resultData.Stage = 'cash_register_passed'

    # Cada escenario debe medir la regla de negocio, no consumir el presupuesto
    # de rate limiting dejado por el escenario anterior.
    Invoke-Artisan tinker '--execute=Cache::flush()'

    $limitedPayload = @{
        items = @(@{ product_id = $limitedProduct.id; quantity = 1 })
        payment_method = 'pay_at_pickup'
        customer_name = 'Concurrency Stock'
        customer_phone = '5551234567'
        order_type = 'takeout'
    } | ConvertTo-Json -Depth 5
    $stockResults = @(Invoke-ConcurrentPost -Path '/api/checkout' -Body $limitedPayload -Ports $ports -UniqueIdempotencyKeys)
    $stockCreated = @($stockResults | Where-Object Status -eq 201).Count
    $stockRejected = @($stockResults | Where-Object Status -eq 422).Count
    $resultData.InventoryStatuses = @($stockResults.Status)
    $resultData.InventoryCreated = $stockCreated
    $resultData.InventoryRejected = $stockRejected
    if ($stockCreated -ne 5 -or $stockRejected -ne 5) {
        throw "Protección de inventario inválida: created=$stockCreated rejected=$stockRejected"
    }
    Write-Host "STAGE: inventory_passed"
    $resultData.Stage = 'inventory_passed'

    $summaryCode = @'
$ingredient = App\Models\Ingredient::where('sku', 'ING-CONCURRENCY')->firstOrFail();
echo json_encode([
    'limited_stock' => (float) $ingredient->current_stock,
    'limited_sales' => App\Models\InventoryTransaction::where('ingredient_id', $ingredient->id)->where('transaction_type', 'sale')->count(),
    'shared_ticket_count' => App\Models\Ticket::where('customer_name', 'Concurrency Idempotency')->count(),
    'open_cash_sessions' => App\Models\CashRegisterSession::whereNotNull('open_user_id')->count(),
]);
'@
    $verificationJson = (& $php artisan tinker "--execute=$summaryCode" | Out-String).Trim()
    $verification = $verificationJson | ConvertFrom-Json
    if ($verification.limited_stock -ne 0 -or $verification.limited_sales -ne 5 -or $verification.shared_ticket_count -ne 1 -or $verification.open_cash_sessions -ne 1) {
        throw "Estado final inconsistente: $verificationJson"
    }

    [pscustomobject]@{
        Schema = $schema
        Workers = $Workers
        Idempotency = "1 created, 9 replayed, 1 ticket"
        CashRegister = "1 created, 9 conflicts"
        Inventory = "5 created, 5 rejected, stock 0"
        Result = 'PASS'
    } | Format-List
    $resultData.Result = 'PASS'
}
catch {
    $failure = $_
    $resultData.Result = 'FAIL'
    $resultData.Error = $_.Exception.Message
    Write-Host "CONCURRENCY_TEST_ERROR: $($_.Exception.Message)"
    foreach ($logFile in $logFiles) {
        if (Test-Path -LiteralPath $logFile) {
            Write-Host "LOG: $logFile"
            Get-Content -LiteralPath $logFile -Tail 30
        }
    }
}
finally {
    foreach ($server in $servers) {
        if ($server -and -not $server.HasExited) {
            Stop-Process -Id $server.Id -Force -ErrorAction SilentlyContinue
        }
    }

    if (-not $KeepSchema) {
        $env:DB_SCHEMA = 'public'
        try {
            & $php artisan tinker "--execute=DB::statement('DROP SCHEMA IF EXISTS $schema CASCADE')" | Out-Null
        } catch {}
    }

    foreach ($logFile in $logFiles) {
        Remove-Item -LiteralPath $logFile -Force -ErrorAction SilentlyContinue
    }
    if ($ResultPath -ne '') {
        $resultData | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $ResultPath -Encoding utf8
    }
    Pop-Location
}

if ($failure) {
    Write-Host 'RESULT: FAIL'
    return
}
