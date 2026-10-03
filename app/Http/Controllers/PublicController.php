<?php

namespace App\Http\Controllers;

use App\Events\OrderCreated;
use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\OrderFile;
use App\Models\Service;
use App\Models\Setting;
use App\Services\OrderCodeGenerator;
use App\Services\OrderPreviewService;
use App\Services\OrderQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublicController extends Controller
{
    public function home()
    {
        return view('home', ['services' => Service::where('active', true)->orderBy('sort_order')->get()]);
    }

    public function create()
    {
        return view('orders.create', [
            'services' => Service::where('active', true)->orderBy('sort_order')->get(),
            'submissionToken' => (string) Str::uuid(),
        ]);
    }

    public function calculate(Request $request, OrderPreviewService $previewService, OrderQuoteService $quotes): JsonResponse
    {
        $data = $request->validate($this->previewRules(), $this->uploadMessages());
        $this->validateFileConfiguration($data);
        $preview = $previewService->calculate($data);
        $token = $quotes->issue($request, $preview, $previewService);

        return response()->json($preview + ['quote_token' => $token]);
    }

    public function store(Request $request, OrderPreviewService $previewService, OrderCodeGenerator $codeGenerator, OrderQuoteService $quotes)
    {
        $submissionToken = (string) $request->input('submission_token', '');
        $existingSubmission = $submissionToken !== '' ? $request->session()->get('order_submission.'.$submissionToken) : null;
        if (! empty($existingSubmission)) {
            return redirect()->route('orders.show', [$existingSubmission['code'], $existingSubmission['public_token']]);
        }
        $submittedToken = (string) $request->input('quote_token', '');
        $existingQuote = $request->session()->get('order_preview.'.$submittedToken);
        if (! empty($existingQuote['used_order'])) {
            return redirect()->route('orders.show', [$existingQuote['used_order']['code'], $existingQuote['used_order']['public_token']]);
        }
        $this->assertUploadsCompleted($request);
        $this->assertUploadCollectionLimits($request);
        $data = $request->validate($this->orderRules(), $this->uploadMessages(), [
            'files' => 'archivos',
            'files.*' => 'archivo',
        ]);
        $data['submission_token'] ??= (string) Str::uuid();
        $service = Service::findOrFail($data['service_id']);
        $request->validate([
            $service->calculation_type === 'quote' ? 'quote_requested' : 'price_accepted' => 'required|accepted',
        ]);
        $data['file_details'] = $this->canonicalFileDetails($request, $service);
        $this->validateFileConfiguration($data);

        $conditional = [];
        if ($service->requires_topic) {
            $conditional['topic'] = 'required|string|max:500';
        }
        if ($service->requires_education_level) {
            $conditional['education_level'] = 'required|string|max:100';
        }
        if ($service->requires_output_format) {
            $conditional['output_format'] = 'required|in:pdf,images,word,both,single_pdf';
        }
        if ($service->requires_copies && empty($data['file_details'])) {
            $conditional['copies'] = 'required|integer|min:1';
        }
        $request->validate($conditional);

        $requirements = [];
        if ($service->requires_pages && empty($data['file_details'])) {
            $requirements['pages'] = 'required|integer|min:1';
        }
        if ($service->pricing_model === 'per_page' && empty($data['file_details'])) {
            $requirements['copies'] = 'required|integer|min:1';
        }
        if ($service->requires_quantity && empty($data['file_details'])) {
            $requirements['quantity'] = 'required|integer|min:1';
        }
        if ($service->requires_file) {
            $requirements['files'] = 'required|array|min:1';
        }
        $request->validate($requirements);

        $preview = $previewService->calculate($data);
        $verification = $quotes->verify($request, $data['quote_token'], $preview, $previewService);
        if ($verification['status'] !== 'valid') {
            $message = match ($verification['status']) {
                'expired', 'missing' => 'El tiempo para confirmar este precio terminó. Actualizamos la cotización para que puedas revisarla nuevamente.',
                'configuration_changed' => 'La configuración del pedido cambió. Revisa el resumen actualizado antes de confirmar.',
                'price_changed' => sprintf(
                    'El precio fue actualizado de $%s a $%s. Revisa el nuevo total antes de confirmar.',
                    number_format(($verification['old_prices']['total_cents'] ?? 0) / 100, 2),
                    number_format(($verification['new_prices']['total_cents'] ?? 0) / 100, 2),
                ),
                default => 'Esta cotización ya fue utilizada.',
            };
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'reason' => $verification['status'],
                    'changed_fields' => $verification['changed_fields'] ?? [],
                    'requires_new_acceptance' => true,
                    'errors' => ['price_accepted' => [$message]],
                ], 422);
            }
            throw ValidationException::withMessages(['price_accepted' => $message]);
        }

        if (in_array($preview['calculation_status'], ['missing_coordinates', 'route_provider_error', 'invalid_service_price'], true)) {
            throw ValidationException::withMessages([
                'delivery' => match ($preview['calculation_status']) {
                    'missing_coordinates' => 'Confirma el punto de entrega antes de enviar el pedido.',
                    'route_provider_error' => 'No pudimos calcular el domicilio. Intenta nuevamente.',
                    default => 'El servicio no tiene una tarifa válida configurada.',
                },
            ]);
        }

        $storedPaths = [];

        try {
            $order = DB::transaction(function () use ($data, $request, $preview, $service, $codeGenerator, &$storedPaths) {
                $attributes = Arr::except($data, [
                    'confirmed', 'delivery_confirmed', 'price_accepted', 'quote_requested', 'quote_token', 'submission_token', 'files', 'file_details', 'combine_images', 'combined_file_ids', 'images_per_page', 'combined_copies', 'combined_instructions', 'facade_photo', 'quantity', 'variant_id', 'addon_ids',
                ]);
                $allowed = ['pages' => $service->requires_pages, 'copies' => $service->requires_copies || $service->pricing_model === 'per_page', 'paper_size' => $service->allows_letter || $service->allows_legal, 'double_sided' => $service->allows_duplex, 'cover' => $service->allows_cover, 'stapled' => $service->allows_stapling, 'topic' => $service->requires_topic, 'education_level' => $service->requires_education_level, 'output_format' => $service->requires_output_format, 'images_required' => $service->allows_images];
                foreach ($allowed as $field => $isAllowed) {
                    if (! $isAllowed) {
                        unset($attributes[$field]);
                    }
                }
                $delivery = $preview['delivery'];

                $order = Order::create(array_merge($attributes, [
                    'status' => $preview['service_requires_quote'] ? 'pending_quote' : 'received',
                    'urgent' => $request->boolean('urgent'),
                    'double_sided' => $service->allows_duplex ? $request->boolean('double_sided') : false,
                    'cover' => $service->allows_cover ? $request->boolean('cover') : false,
                    'stapled' => $service->allows_stapling ? $request->boolean('stapled') : false,
                    'work_subtotal' => $preview['work_subtotal'],
                    'delivery_fee' => $delivery['total'] ?? null,
                    'total' => $preview['total'],
                    'distance_km' => $preview['distance_km'],
                    'duration_minutes' => $preview['duration_minutes'],
                    'route_provider' => $preview['route_provider'],
                    'route_calculated_at' => $preview['distance_km'] !== null ? now() : null,
                    'base_fee_snapshot' => $delivery['base'] ?? null,
                    'price_per_km_snapshot' => $delivery['per_km'] ?? null,
                    'minimum_fee_snapshot' => $delivery['minimum'] ?? null,
                    'pickup_factor_snapshot' => $delivery['factor'] ?? null,
                    'urgent_surcharge_snapshot' => $delivery['urgent'] ?? null,
                    'public_token' => hash('sha256', Str::random(64)),
                    'requires_quote' => $preview['manual_quote'] && in_array($preview['reason'], ['service_quote', 'image_combination'], true),
                    'requires_deposit' => $service->requires_deposit,
                    'service_configuration_snapshot' => $preview['configuration'],
                ]));

                $order->update(['code' => $codeGenerator->generate()]);

                $storedFiles = [];
                foreach ($request->file('files', []) as $index => $file) {
                    $storedFiles[$index] = $this->storeOrderFile($order, $file, 'document', $storedPaths, $preview['file_details'][$index] ?? [], $index);
                }

                if ($preview['image_composition']) {
                    $composition = $preview['image_composition'];
                    $compositionFileIds = collect($composition['file_indexes'])->map(fn ($index) => $storedFiles[$index]->id)->all();
                    if (count($compositionFileIds) !== $composition['image_count']) {
                        throw ValidationException::withMessages([
                            'combined_file_ids' => 'No pudimos conservar todas las imágenes seleccionadas. Revisa los archivos antes de continuar.',
                        ]);
                    }
                    $order->imageComposition()->create([
                        'order_file_ids' => $compositionFileIds,
                        'images_per_page' => $composition['images_per_page'],
                        'resulting_pages' => $composition['resulting_pages'],
                        'copies' => $composition['copies'],
                        'billable_pages' => $composition['billable_pages'],
                        'instructions' => $composition['instructions'],
                        'unit_price' => $composition['unit_price'],
                        'subtotal' => $composition['subtotal'],
                    ]);
                }

                if ($request->hasFile('facade_photo')) {
                    $this->storeOrderFile($order, $request->file('facade_photo'), 'facade', $storedPaths);
                }

                $item = $order->items()->create([
                    'service_id' => $preview['service_id'], 'service_name' => $preview['service_name'],
                    'pricing_model' => $preview['pricing_model'], 'quantity' => $preview['print_units'] ?: ($preview['quantity'] ?? 1),
                    'unit_price' => $preview['unit_price'], 'subtotal' => $preview['work_subtotal'],
                    'snapshot' => Arr::except($preview, ['configuration', 'quote_token']),
                ]);
                if ($preview['variant']) {
                    $item->options()->create(['type' => $preview['variant']['type'], 'name' => $preview['variant']['name'], 'price' => $preview['variant']['price'], 'snapshot' => $preview['variant']]);
                }
                foreach ($preview['addons'] as $addon) {
                    $item->options()->create(['type' => 'addon', 'name' => $addon['name'], 'price' => $addon['price'], 'snapshot' => $addon]);
                }

                $order->histories()->create(['to_status' => $order->status]);

                return $order;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        $quotes->markUsed($request, $data['quote_token'], [
            'id' => $order->id,
            'code' => $order->code,
            'public_token' => $order->public_token,
        ]);
        $request->session()->put('order_submission.'.$data['submission_token'], [
            'id' => $order->id,
            'code' => $order->code,
            'public_token' => $order->public_token,
        ]);
        AdminNotification::firstOrCreate(
            ['order_id' => $order->id, 'channel' => 'database', 'type' => 'new_order'],
            ['status' => 'sent', 'scheduled_at' => now(), 'sent_at' => now()],
        );
        AdminNotification::firstOrCreate(
            ['order_id' => $order->id, 'channel' => 'telegram', 'type' => 'new_order'],
            ['status' => config('services.telegram.enabled') ? 'pending' : 'skipped', 'scheduled_at' => now(), 'last_error' => config('services.telegram.enabled') ? null : 'Telegram no está habilitado.'],
        );
        OrderCreated::dispatch($order);

        return redirect()->route('orders.show', [$order->code, $order->public_token]);
    }

    public function show(string $code, string $token)
    {
        $order = Order::with(['service', 'files', 'quotes'])->where('code', $code)->where('public_token', $token)->firstOrFail();

        return view('orders.show', compact('order'));
    }

    public function lookup(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('orders.lookup');
        }

        $key = 'lookup:'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($key, 10), 429);
        RateLimiter::hit($key, 60);
        $data = $request->validate(['code' => 'required|string|max:30']);
        $code = strtoupper(preg_replace('/\s+/', '', $data['code']));
        $order = Order::with(['service', 'histories', 'quotes'])
            ->where('code', $code)
            ->first();

        return view('orders.lookup', compact('order'));
    }

    private function previewRules(): array
    {
        return [
            'service_id' => 'required|exists:services,id',
            'submission_token' => 'nullable|uuid',
            'pages' => 'nullable|integer|min:1|max:10000',
            'copies' => 'nullable|integer|min:1|max:1000',
            'paper_size' => 'nullable|in:carta,oficio',
            'double_sided' => 'nullable|boolean',
            'orientation' => 'nullable|in:vertical,horizontal',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'delivery_confirmed' => 'nullable|boolean',
            'urgent' => 'nullable|boolean',
            'topic' => 'nullable|string|max:500', 'education_level' => 'nullable|string|max:100', 'output_format' => 'nullable|in:pdf,images,word,both,single_pdf', 'images_required' => 'nullable|boolean',
            'quantity' => 'nullable|integer|min:1|max:100000', 'variant_id' => 'nullable|integer', 'addon_ids' => 'nullable|array', 'addon_ids.*' => 'integer',
            'file_details' => 'nullable|array|max:10', 'file_details.*.client_id' => 'required_with:file_details|string|max:64|distinct', 'file_details.*.name' => 'nullable|string|max:255', 'file_details.*.document_type' => 'required_with:file_details|in:image,pdf,word,document', 'file_details.*.source_pages' => 'required_with:file_details|integer|min:1|max:10000', 'file_details.*.copies' => 'nullable|integer|min:1|max:1000', 'file_details.*.paper_size' => 'nullable|in:carta,oficio', 'file_details.*.double_sided' => 'nullable|boolean', 'file_details.*.stapled' => 'nullable|boolean',
            'combine_images' => 'nullable|boolean',
            'combined_file_ids' => 'exclude_unless:combine_images,1|array|min:2|max:10',
            'combined_file_ids.*' => 'string|max:64|distinct',
            'images_per_page' => 'exclude_unless:combine_images,1|required|integer|min:2|max:10',
            'combined_copies' => 'exclude_unless:combine_images,1|required|integer|min:1|max:1000',
            'combined_instructions' => 'exclude_unless:combine_images,1|nullable|string|max:1000',
        ];
    }

    private function orderRules(): array
    {
        return $this->previewRules() + [
            'customer_name' => 'required|string|max:120',
            'phone' => ['required', 'regex:/^[267]\d{7}$/'],
            'email' => 'nullable|email',
            'instructions' => 'nullable|string|max:3000',
            'details' => 'nullable|string|max:3000',
            'requested_at' => 'required|date',
            'payment_method' => 'required|in:cash,transfer',
            'department' => 'nullable|string|max:120',
            'municipality' => 'required|string|max:120',
            'neighborhood' => 'nullable|string|max:120',
            'street' => 'nullable|string|max:180',
            'house_number' => 'nullable|string|max:40',
            'address_reference' => 'nullable|string|max:500',
            'confirmed' => 'accepted',
            'price_accepted' => 'sometimes|accepted',
            'quote_requested' => 'sometimes|accepted',
            'quote_token' => 'required|string|size:64',
            'cover' => 'nullable|boolean',
            'stapled' => 'nullable|boolean',
            'files' => [
                'nullable',
                'array',
                'max:10',
                function (string $attribute, mixed $files, \Closure $fail): void {
                    $total = collect($files)->sum(fn ($file) => (int) $file->getSize());
                    if ($total > 50 * 1024 * 1024) {
                        $fail('El conjunto de archivos supera el máximo permitido de 50 MB.');
                    }
                },
            ],
            'files.*' => 'required|file|max:10240|mimetypes:application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'facade_photo' => 'nullable|file|max:'.((int) env('UPLOAD_MAX_MB', 10) * 1024).'|mimetypes:image/jpeg,image/png',
        ];
    }

    private function assertUploadsCompleted(Request $request): void
    {
        $uploads = $request->allFiles()['files'] ?? [];

        foreach ((array) $uploads as $index => $file) {
            if ($file->isValid()) {
                continue;
            }

            $error = $file->getError();
            Log::warning('Carga de pedido rechazada por PHP', [
                'file_index' => $index,
                'upload_error' => $error,
                'declared_size' => $file->getSize(),
                'request_content_length' => $request->server('CONTENT_LENGTH'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ]);

            throw ValidationException::withMessages([
                'files' => $this->uploadErrorMessage($error, $index),
            ]);
        }
    }

    private function assertUploadCollectionLimits(Request $request): void
    {
        $files = collect($request->file('files', []));
        if ($request->hasFile('facade_photo')) {
            $files->push($request->file('facade_photo'));
        }

        if ($files->count() > 10) {
            throw ValidationException::withMessages([
                'files' => 'Puedes subir un máximo de 10 archivos por pedido.',
            ]);
        }

        if ($files->sum(fn ($file) => (int) $file->getSize()) > 50 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'files' => 'El conjunto de archivos supera el máximo permitido de 50 MB.',
            ]);
        }
    }

    private function uploadErrorMessage(int $error, int|string $index): string
    {
        $position = ((int) $index) === 0 ? 'El primer archivo' : 'Uno de los archivos';

        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "$position supera el tamaño máximo permitido de 10 MB.",
            UPLOAD_ERR_PARTIAL => 'Uno de los archivos no terminó de cargarse. Intenta nuevamente.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'No pudimos guardar uno de los archivos. Intenta nuevamente.',
            default => 'Uno de los archivos no pudo cargarse. Intenta nuevamente.',
        };
    }

    private function uploadMessages(): array
    {
        return [
            'files.max' => 'Puedes subir un máximo de 10 archivos por pedido.',
            'files.*.required' => 'Uno de los archivos no pudo cargarse. Selecciónalo nuevamente.',
            'files.*.uploaded' => 'Uno de los archivos no pudo cargarse. Selecciónalo nuevamente.',
            'files.*.max' => 'Uno de los archivos supera el máximo permitido de 10 MB.',
            'files.*.mimetypes' => 'El formato de uno de los archivos no está permitido.',
            'files.*.file' => 'Uno de los elementos seleccionados no es un archivo válido.',
            'combined_file_ids.required' => 'Selecciona al menos dos imágenes para combinarlas.',
            'combined_file_ids.min' => 'Selecciona al menos dos imágenes para combinarlas.',
            'combined_file_ids.*.distinct' => 'Una imagen no puede repetirse en el diseño combinado.',
            'combined_copies.required' => 'Indica cuántas copias deseas de la página combinada.',
            'combined_copies.integer' => 'La cantidad de copias de la página combinada debe ser un número entero.',
            'combined_copies.min' => 'Indica al menos una copia de la página combinada.',
            'images_per_page.required' => 'Selecciona el máximo de imágenes por página.',
            'images_per_page.integer' => 'El máximo de imágenes por página debe ser un número entero.',
        ];
    }

    private function canonicalFileDetails(Request $request, Service $service): array
    {
        $submitted = array_values($request->input('file_details', []));
        if ($submitted === []) {
            return [];
        }

        return collect($request->file('files', []))->map(function ($file, $index) use ($submitted, $request, $service) {
            $detail = $submitted[$index] ?? [];
            $mime = $file->getMimeType();
            $documentType = str_starts_with($mime, 'image/') ? 'image' : ($mime === 'application/pdf' ? 'pdf' : 'word');
            $detectedPages = $this->detectPages($file, $documentType);
            $sourcePages = $detectedPages ?? max(1, (int) ($detail['source_pages'] ?? 1));
            $paper = $detail['paper_size'] ?? $request->input('paper_size', 'carta');
            abort_if(($paper === 'carta' && ! $service->allows_letter) || ($paper === 'oficio' && ! $service->allows_legal), 422, 'El tamaño de papel no está permitido para este servicio.');

            return [
                'client_id' => (string) ($detail['client_id'] ?? ''),
                'name' => $file->getClientOriginalName(),
                'document_type' => $documentType,
                'source_pages' => $sourcePages,
                'page_detection' => $detectedPages !== null ? 'automatic' : 'estimated',
                'copies' => isset($detail['copies']) ? (int) $detail['copies'] : null,
                'paper_size' => $paper,
                'double_sided' => $service->allows_duplex && filter_var($detail['double_sided'] ?? $request->boolean('double_sided'), FILTER_VALIDATE_BOOL),
                'stapled' => $service->allows_stapling && filter_var($detail['stapled'] ?? $request->boolean('stapled'), FILTER_VALIDATE_BOOL),
            ];
        })->all();
    }

    private function validateFileConfiguration(array $data): void
    {
        $details = array_values($data['file_details'] ?? []);
        $enabled = filter_var($data['combine_images'] ?? false, FILTER_VALIDATE_BOOL);
        $combinedIds = $enabled
            ? array_values(array_unique(array_map('strval', $data['combined_file_ids'] ?? [])))
            : [];
        if ($enabled && count($combinedIds) < 2) {
            throw ValidationException::withMessages(['combined_file_ids' => 'Selecciona al menos dos imágenes para combinarlas.']);
        }
        foreach ($details as $detail) {
            $clientId = (string) ($detail['client_id'] ?? '');
            $isCombined = in_array($clientId, $combinedIds, true);
            if ($isCombined && ($detail['document_type'] ?? null) !== 'image') {
                throw ValidationException::withMessages(['combined_file_ids' => 'Solo puedes incluir imágenes JPG, JPEG, PNG o WEBP en el diseño combinado.']);
            }
            if (! $isCombined && empty($detail['copies'])) {
                $name = (string) ($detail['name'] ?? 'seleccionado');
                throw ValidationException::withMessages(['file_details' => "Indica cuántas copias deseas del archivo «{$name}». "]);
            }
        }
        $knownIds = array_map('strval', array_column($details, 'client_id'));
        if (array_diff($combinedIds, $knownIds) !== []) {
            throw ValidationException::withMessages(['combined_file_ids' => 'No pudimos conservar todas las imágenes seleccionadas. Revisa los archivos antes de continuar.']);
        }
        if ((int) ($data['images_per_page'] ?? 0) > count($combinedIds)) {
            throw ValidationException::withMessages([
                'images_per_page' => 'El máximo de imágenes por página no puede superar la cantidad de imágenes seleccionadas.',
            ]);
        }
    }

    private function detectPages($file, string $documentType): ?int
    {
        if ($documentType === 'image') {
            return 1;
        }
        if ($documentType !== 'pdf') {
            return null;
        }
        $contents = file_get_contents($file->getRealPath());
        preg_match_all('/\/Type\s*\/Page\b/', $contents ?: '', $matches);

        return count($matches[0]) ?: null;
    }

    private function storeOrderFile(Order $order, $file, string $type, array &$storedPaths, array $detail = [], int $sortOrder = 0): OrderFile
    {
        $path = $file->store("orders/{$order->id}", 'local');
        $storedPaths[] = $path;

        return $order->files()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'type' => $type,
            'document_type' => $detail['document_type'] ?? null,
            'source_pages' => $detail['source_pages'] ?? null,
            'page_detection' => $detail['page_detection'] ?? null,
            'copies' => $detail['copies'] ?? null,
            'print_units' => isset($detail['source_pages'], $detail['copies']) ? $detail['source_pages'] * $detail['copies'] : null,
            'paper_size' => $detail['paper_size'] ?? null,
            'double_sided' => $detail['double_sided'] ?? false,
            'stapled' => $detail['stapled'] ?? false,
            'unit_price' => $detail['unit_price'] ?? null,
            'subtotal' => $detail['subtotal'] ?? null,
            'sort_order' => $sortOrder,
            'expires_at' => now()->addDays((int) Setting::value('file_retention_days', 30)),
        ]);
    }
}
