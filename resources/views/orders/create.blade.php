@extends('layouts.public', ['title' => 'Hacer un pedido'])

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<style>#customer-map{height:380px;border-radius:16px;margin:14px 0;border:1px solid #aebdb9}.map-help{background:#e8f5f1;padding:12px;border-radius:10px}.file-heading{display:flex;gap:12px;align-items:center}.file-thumb{width:72px;height:72px;object-fit:cover;border-radius:10px}.compact-toggle{display:flex;align-items:center;gap:8px}.composition-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px}.composition-choice{display:grid;grid-template-columns:auto 54px 1fr;gap:8px;align-items:center}.order-controls{display:flex;gap:4px}.order-controls button{padding:5px 8px}.page-preview{display:flex;gap:10px;flex-wrap:wrap}.preview-page{min-width:180px;border:1px solid #aebdb9;border-radius:10px;padding:10px}.preview-images{display:grid;gap:6px}.preview-images img{width:100%;height:70px;object-fit:contain;border-radius:8px;background:#f3f4f6}.combined-file [data-individual]{display:none}</style>
@endpush

@section('content')
    <main class="wrap section">
        <p class="muted">Pedido · todos los pasos</p>
        <div class="progress"><i></i></div>
        <h1>Cuéntanos qué necesitas</h1>

        @if ($errors->any())
            <div class="errors">
                <b>Revisa estos datos:</b>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="order-form" method="post" enctype="multipart/form-data" action="{{ route('orders.store') }}">
            @csrf
            <input id="quote-token" type="hidden" name="quote_token" value="">
            <input id="submission-token" type="hidden" name="submission_token" value="{{ $submissionToken }}">

            <h2>1. Servicio</h2>
            <div class="grid">
                @foreach ($services as $service)
                    <label class="card">
                        <input style="width:auto" type="radio" name="service_id" value="{{ $service->id }}"
                            data-name="{{ $service->name }}" @checked(old('service_id') == $service->id) required>
                        <b>{{ $service->icon }} {{ $service->name }}</b>
                        <p class="muted">{{ $service->description }}</p>
                    </label>
                @endforeach
            </div>

            <h2>2. Tus datos</h2>
            <div class="two">
                <label>Nombre completo<input name="customer_name" value="{{ old('customer_name') }}" required></label>
                <label>WhatsApp (8 dígitos)<input name="phone" inputmode="numeric" pattern="[267][0-9]{7}" value="{{ old('phone') }}" required></label>
            </div>
            <label>Correo opcional<input type="email" name="email" value="{{ old('email') }}"></label>

            <h2>3. Pedido y archivos</h2>
            <div id="file-field" hidden>
                <label>Archivos para imprimir<input id="files" type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></label>
                <div id="file-validation-error" class="errors" hidden></div>
                <section id="image-composition" class="card" hidden>
                    <label class="compact-toggle"><input id="combine-images" type="checkbox" name="combine_images" value="1"> Combinar varias imágenes en una misma página</label>
                    <p id="composition-warning" class="errors" hidden>Selecciona al menos 2 imágenes para poder combinarlas.</p>
                    <div id="composition-options" hidden></div>
                </section>
                <label><input id="individual-config" type="checkbox"> Configurar un archivo de forma diferente</label>
                <div id="file-list" class="stack"><p class="muted">Ningún archivo seleccionado.</p></div>
            </div>
            <div id="dynamic-service-options"></div>

            <h2>4. Ubicación y entrega</h2>
            <p id="location-status" class="muted">Confirma tu ubicación. El servidor validará la ruta.</p>
            <button type="button" onclick="locateCustomer()">Usar mi ubicación actual</button>
            <p class="map-help">Haz clic sobre el mapa o arrastra el marcador hasta la entrada exacta de tu domicilio.</p>
            <div id="customer-map" role="application" aria-label="Selecciona tu ubicación de entrega"></div>
            <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude') }}">
            <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude') }}">
            <input type="hidden" id="delivery-confirmed" name="delivery_confirmed" value="{{ old('delivery_confirmed', 0) }}">
            <div class="two">
                <label>Departamento<input name="department" value="{{ old('department', 'San Miguel') }}"></label>
                <label>Municipio o distrito<input name="municipality" value="{{ old('municipality', 'San Miguel') }}" required></label>
                <label>Colonia o residencial<input name="neighborhood" value="{{ old('neighborhood') }}"></label>
                <label>Calle<input name="street" value="{{ old('street') }}"></label>
                <label>Número de casa<input name="house_number" value="{{ old('house_number') }}"></label>
            </div>
            <label>Referencias<textarea name="address_reference">{{ old('address_reference') }}</textarea></label>
            <label>Fotografía opcional de la fachada<input type="file" name="facade_photo" accept="image/jpeg,image/png"></label>
            <div class="two">
                <label>Fecha y hora solicitada<input type="datetime-local" name="requested_at" required></label>
                <label>Forma de pago<select name="payment_method"><option value="cash">Efectivo contra entrega</option><option value="transfer">Transferencia bancaria</option></select></label>
            </div>
            <label><input style="width:auto" type="checkbox" name="urgent" value="1"> Entrega urgente (+$1.00, sujeta a disponibilidad)</label>

            <h2>5. Revisa y confirma tu pedido</h2>
            <div id="price-summary" class="card" aria-live="polite">
                <h3>Resumen del pedido</h3>
                <p class="muted">Selecciona un servicio y una ubicación para calcular el precio.</p>
            </div>

            <label><input style="width:auto" type="checkbox" name="confirmed" value="1" required> He revisado los archivos, la cantidad de copias y las características del pedido</label>
            <label id="acceptance-label"><input id="price-accepted" style="width:auto" type="checkbox" name="price_accepted" value="1" required> <span>He revisado las características del pedido y acepto el precio total mostrado</span></label>
            <p class="muted">Las reimpresiones ocasionadas por archivos o instrucciones incorrectas proporcionadas por el cliente pueden generar un costo adicional.</p>
            <button id="submit-order" type="submit" disabled>Calculando precio…</button>
        </form>
    </main>

    <script>
        const form = document.getElementById('order-form');
        const summary = document.getElementById('price-summary');
        const submitButton = document.getElementById('submit-order');
        const accepted = document.getElementById('price-accepted');
        const quoteToken = document.getElementById('quote-token');
        let calculationTimer;
        let calculating = false;
        let validPreview = false;
        let lastPreview = null;
        let submitting = false;
        let calculationSequence = 0;
        let activePreviewRequest = null;

        const money = value => '$' + Number(value || 0).toFixed(2);
        const field = name => form.elements[name];
        const checked = name => Boolean(field(name)?.checked);
            const selectedService = () => form.querySelector('input[name="service_id"]:checked');
            let configuredServiceId = null;
            let selectedFiles = [];
            let compositionOrderIds = [];
            let compositionSelectedIds = [];
            const fileClientIds = new WeakMap();
            let nextClientId = 0;
            let serviceConfiguration = {};
            const maxFiles = 10;
            const maxFileBytes = 10 * 1024 * 1024;
            const maxTotalBytes = 50 * 1024 * 1024;
            const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

        const escapeHtml = value => String(value).replace(/[&<>'"]/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
        })[character]);
        const clientIdFor = file => {
            if (!fileClientIds.has(file)) fileClientIds.set(file, `file-${Date.now()}-${++nextClientId}`);
            return fileClientIds.get(file);
        };

        function validateSelectedFiles(files) {
            const facade = field('facade_photo')?.files?.[0];
            const allFiles = facade ? [...files, facade] : files;
            if (allFiles.length > maxFiles) return 'Puedes subir un máximo de 10 archivos por pedido.';
            for (const file of allFiles) {
                if (file.size > maxFileBytes) return `“${file.name}” supera el máximo permitido de 10 MB.`;
                const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
                if (!allowedExtensions.includes(extension)) return `El formato de “${file.name}” no está permitido.`;
            }
            if (allFiles.reduce((total, file) => total + file.size, 0) > maxTotalBytes) {
                return 'El conjunto de archivos supera el máximo permitido de 50 MB.';
            }
            return null;
        }

        function showFileValidationError(message) {
            const box = document.getElementById('file-validation-error');
            box.hidden = !message;
            box.textContent = message ? `${message} Por seguridad, selecciona nuevamente los archivos que no pudieron cargarse.` : '';
        }

        function fileDetails() {
            return [...document.querySelectorAll('.file-card')].map(card => {
                const combined = compositionSelectedIds.includes(card.dataset.clientId);
                return ({
                client_id: card.dataset.clientId,
                name: card.dataset.name,
                document_type: card.dataset.type,
                source_pages: Number(card.querySelector('[data-pages]').value),
                copies: combined ? null : Number(card.querySelector('[data-copies]').value),
                paper_size: card.querySelector('[data-paper]')?.value || field('paper_size')?.value || 'carta',
                double_sided: card.querySelector('[data-duplex]')?.checked ?? checked('double_sided'),
                stapled: card.querySelector('[data-stapled]')?.checked ?? checked('stapled'),
                });
            });
        }

        async function detectedFile(file) {
            const extension = file.name.split('.').pop().toLowerCase();
            if (['jpg', 'jpeg', 'png'].includes(extension)) return {type: 'image', pages: 1, detection: 'automatic'};
            if (extension === 'pdf') {
                const text = await file.text();
                const pages = (text.match(/\/Type\s*\/Page\b/g) || []).length;
                return {type: 'pdf', pages: pages || 1, detection: pages ? 'automatic' : 'estimated'};
            }
            return {type: 'word', pages: 1, detection: 'estimated'};
        }

        async function renderFileCards() {
            const list = document.getElementById('file-list');
            const existing = fileDetails();
            if (!selectedFiles.length) {
                list.innerHTML = '<p class="muted">Ningún archivo seleccionado.</p>';
                document.getElementById('image-composition').hidden = true;
                return;
            }
            const detected = await Promise.all(selectedFiles.map(detectedFile));
            const individual = document.getElementById('individual-config').checked;
            const globalPaper = field('paper_size')?.value || 'carta';
            list.innerHTML = selectedFiles.map((file, index) => {
                const meta = detected[index];
                const previous = existing[index] || {};
                meta.pages = previous.source_pages || meta.pages;
                const pages = meta.detection === 'automatic'
                    ? `<p>${meta.pages} página(s) detectada(s)<input data-pages type="hidden" name="file_details[${index}][source_pages]" value="${meta.pages}"></p>`
                    : `<label>Cantidad aproximada de páginas<input data-pages type="number" min="1" max="10000" name="file_details[${index}][source_pages]" value="${meta.pages}" required></label>`;
                const selectedPaper = previous.paper_size || globalPaper;
                const settings = individual ? `<div class="two"><label>Papel<select data-paper name="file_details[${index}][paper_size]"><option value="carta" ${selectedPaper==='carta'?'selected':''}>Carta</option><option value="oficio" ${selectedPaper==='oficio'?'selected':''}>Oficio</option></select></label>${serviceConfiguration.allows_duplex?`<label><input data-duplex type="checkbox" name="file_details[${index}][double_sided]" value="1" ${previous.double_sided?'checked':''}> Doble cara</label>`:''}${serviceConfiguration.allows_stapling?`<label><input data-stapled type="checkbox" name="file_details[${index}][stapled]" value="1" ${previous.stapled?'checked':''}> Engrapado</label>`:''}</div>` : `<input data-paper type="hidden" name="file_details[${index}][paper_size]" value="${globalPaper}">`;
                const copies = previous.copies || 1;
                const safeName = escapeHtml(file.name);
                const thumbnail = meta.type === 'image' ? `<img class="file-thumb" src="${URL.createObjectURL(file)}" alt="Vista previa de ${safeName}">` : '';
                const clientId = clientIdFor(file);
                return `<section class="card file-card" data-index="${index}" data-client-id="${clientId}" data-name="${safeName}" data-type="${meta.type}"><input type="hidden" name="file_details[${index}][client_id]" value="${clientId}"><input type="hidden" name="file_details[${index}][name]" value="${safeName}"><input type="hidden" name="file_details[${index}][document_type]" value="${meta.type}"><button type="button" class="remove-file" data-index="${index}" style="float:right;background:#991b1b">Eliminar</button><div class="file-heading">${thumbnail}<h3>${index + 1}. ${safeName}</h3></div><p>${meta.type.toUpperCase()} · ${Math.ceil(file.size / 1024)} KB · ${meta.detection === 'automatic' ? 'páginas detectadas' : 'páginas estimadas'}</p>${pages}<div data-individual><label>Cantidad de copias<input data-copies type="number" min="1" max="1000" name="file_details[${index}][copies]" value="${copies}" required></label>${settings}<p data-file-total><b>Resultado: ${meta.pages * copies} impresiones</b></p></div><p data-combined-label hidden><b>Incluida en la página combinada</b></p></section>`;
            }).join('');
            renderCompositionOptions(detected);
            list.querySelectorAll('.remove-file').forEach(button => button.addEventListener('click', () => removeFile(Number(button.dataset.index))));
            list.querySelectorAll('[data-pages],[data-copies]').forEach(input => input.addEventListener('input', updateFileTotals));
            updateFileTotals();
            scheduleCalculation();
        }

        function renderCompositionOptions(detected) {
            const imageIndexes = detected.map((item, index) => item.type === 'image' ? index : null).filter(index => index !== null);
            const section = document.getElementById('image-composition');
            const options = document.getElementById('composition-options');
            section.hidden = imageIndexes.length < 2;
            if (section.hidden) {
                document.getElementById('combine-images').checked = false;
                options.hidden = true;
                return;
            }
            const orderedIndexes = [...imageIndexes].sort((a, b) => {
                const aPosition = compositionOrderIds.indexOf(clientIdFor(selectedFiles[a]));
                const bPosition = compositionOrderIds.indexOf(clientIdFor(selectedFiles[b]));
                return (aPosition < 0 ? 999 : aPosition) - (bPosition < 0 ? 999 : bPosition);
            });
            const selectAll = compositionSelectedIds.length === 0;
            const choices = orderedIndexes.map(index => {
                const file = selectedFiles[index];
                const clientId = clientIdFor(file);
                const selected = selectAll || compositionSelectedIds.includes(clientId);
                return `<div class="card composition-choice" data-client-id="${clientId}"><input aria-label="Incluir ${escapeHtml(file.name)}" type="checkbox" name="combined_file_ids[]" value="${clientId}" ${selected ? 'checked' : ''}><img class="file-thumb" src="${URL.createObjectURL(file)}" alt="Vista previa de ${escapeHtml(file.name)}"><span><b>${escapeHtml(file.name)}</b><span class="order-controls"><button type="button" data-move="up" aria-label="Subir ${escapeHtml(file.name)}">↑ Subir</button><button type="button" data-move="down" aria-label="Bajar ${escapeHtml(file.name)}">↓ Bajar</button></span></span></div>`;
            }).join('');
            options.innerHTML = `<p><b>Selecciona y ordena las imágenes que se combinarán</b></p><div class="composition-grid">${choices}</div><p id="composition-count" class="muted"></p><div class="two"><label>Máximo de imágenes por página<select name="images_per_page"></select></label><label>Cantidad de copias de la página combinada<input type="number" name="combined_copies" min="1" max="1000" value="1"></label></div><p id="composition-explanation" class="muted"></p><div id="composition-pages" class="page-preview"></div><p class="muted">La última página puede contener menos imágenes cuando la cantidad no sea exacta.</p><label>¿Cómo quieres organizar las imágenes?<textarea name="combined_instructions" maxlength="1000" placeholder="Ejemplo: coloca las dos imágenes lado a lado y que ocupen toda la hoja."></textarea></label>`;
            options.querySelectorAll('input,select,textarea').forEach(input => input.disabled = true);
            options.querySelectorAll('input[name="combined_file_ids[]"]').forEach(input => input.addEventListener('change', updateCompositionState));
            options.querySelector('select[name="images_per_page"]').addEventListener('change', updateCompositionState);
            options.querySelectorAll('[data-move]').forEach(button => button.addEventListener('click', () => moveCompositionImage(button)));
            updateCompositionState();
        }

        function updateCompositionState() {
            const toggle = document.getElementById('combine-images');
            let enabled = toggle.checked;
            const options = document.getElementById('composition-options');
            let selectedIds = enabled ? [...options.querySelectorAll('input[name="combined_file_ids[]"]:checked')].map(input => input.value) : [];
            const warning = document.getElementById('composition-warning');
            if (enabled && selectedIds.length < 2) {
                toggle.checked = false;
                enabled = false;
                warning.hidden = false;
            } else {
                warning.hidden = true;
            }
            options.hidden = !enabled;
            options.querySelectorAll('input,select,textarea').forEach(input => input.disabled = !enabled);
            if (!enabled) selectedIds = [];
            compositionOrderIds = [...options.querySelectorAll('.composition-choice')].map(choice => choice.dataset.clientId);
            compositionSelectedIds = selectedIds;
            const selected = selectedIds.map(id => selectedFiles.findIndex(file => clientIdFor(file) === id)).filter(index => index >= 0);
            document.querySelectorAll('.file-card').forEach(card => {
                const combined = selected.includes(Number(card.dataset.index));
                card.classList.toggle('combined-file', combined);
                card.querySelector('[data-combined-label]').hidden = !combined;
                card.querySelector('[data-copies]').disabled = combined;
            });
            const count = document.getElementById('composition-count');
            if (count) count.textContent = `Imágenes seleccionadas: ${selected.length}`;
            const perPageSelect = field('images_per_page');
            const previousPerPage = Number(perPageSelect?.value || 2);
            if (perPageSelect && enabled) {
                perPageSelect.innerHTML = Array.from({length: selected.length - 1}, (_, offset) => offset + 2)
                    .map(value => `<option value="${value}">${value === selected.length ? `${value} imágenes en una sola página` : `${value} imágenes por página`}</option>`)
                    .join('');
                perPageSelect.value = String(Math.min(Math.max(2, previousPerPage), selected.length));
            }
            const perPage = Number(perPageSelect?.value || 2);
            const pages = Math.ceil(selected.length / perPage);
            const distribution = selected.length === perPage ? `Tus ${selected.length} imágenes ocuparán 1 página.` : `Tus ${selected.length} imágenes ocuparán ${pages} página(s), con un máximo de ${perPage} en cada una.`;
            const explanation = document.getElementById('composition-explanation');
            if (explanation) explanation.textContent = distribution;
            const pagePreview = document.getElementById('composition-pages');
            if (pagePreview) pagePreview.innerHTML = Array.from({length: pages}, (_, pageIndex) => {
                const indexes = selected.slice(pageIndex * perPage, (pageIndex + 1) * perPage);
                const columns = perPage <= 2 ? 2 : perPage <= 3 ? 3 : perPage <= 4 ? 2 : perPage <= 6 ? 3 : 4;
                return `<section class="preview-page"><b>Página ${pageIndex + 1}</b><div class="preview-images" style="grid-template-columns:repeat(${columns},minmax(0,1fr))">${indexes.map(index => `<img src="${URL.createObjectURL(selectedFiles[index])}" alt="${escapeHtml(selectedFiles[index].name)}" title="${escapeHtml(selectedFiles[index].name)}">`).join('')}</div></section>`;
            }).join('');
            updateFileTotals();
            scheduleCalculation();
        }

        function moveCompositionImage(button) {
            const choice = button.closest('.composition-choice');
            const sibling = button.dataset.move === 'up' ? choice.previousElementSibling : choice.nextElementSibling;
            if (!sibling) return;
            if (button.dataset.move === 'up') choice.parentElement.insertBefore(choice, sibling);
            else choice.parentElement.insertBefore(sibling, choice);
            updateCompositionState();
        }

        function updateFileTotals() {
            document.querySelectorAll('.file-card').forEach(card => {
                const total = Number(card.querySelector('[data-pages]').value || 0) * Number(card.querySelector('[data-copies]').value || 0);
                card.querySelector('[data-file-total]').innerHTML = `<b>${total} impresiones</b>`;
            });
        }

        function syncFileInput() {
            const transfer = new DataTransfer();
            selectedFiles.forEach(file => transfer.items.add(file));
            document.getElementById('files').files = transfer.files;
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            syncFileInput();
            renderFileCards();
        }

        function updateSubmitState() {
            submitButton.disabled = calculating || !validPreview || !accepted.checked;
        }

        function scheduleCalculation() {
            clearTimeout(calculationTimer);
            calculationTimer = setTimeout(calculatePreview, 650);
        }

        function affectsCalculation(target) {
            const name = target.name || '';
            return [
                'service_id', 'pages', 'copies', 'paper_size', 'double_sided', 'urgent',
                'quantity', 'variant_id', 'images_per_page', 'combined_copies',
                'combine_images', 'combined_file_ids[]', 'addon_ids[]',
            ].includes(name) || name.startsWith('file_details[');
        }

        async function calculatePreview() {
            const service = selectedService();
            if (!service) {
                validPreview = false;
                updateSubmitState();
                return;
            }

            const sequence = ++calculationSequence;
            activePreviewRequest?.abort();
            activePreviewRequest = new AbortController();
            calculating = true;
            validPreview = false;
            quoteToken.value = '';
            accepted.checked = false;
            updateSubmitState();
            summary.innerHTML = '<h3>Resumen del pedido</h3><p class="muted">Calculando el costo de entrega...</p>';

            const details = fileDetails();
            const payload = {
                service_id: service.value,
                submission_token: field('submission_token').value,
                pages: field('pages')?.value || null,
                copies: field('copies')?.value || null,
                paper_size: field('paper_size')?.value || null,
                double_sided: checked('double_sided') ? 1 : 0,
                orientation: field('orientation')?.value || null,
                latitude: field('latitude').value || null,
                longitude: field('longitude').value || null,
                delivery_confirmed: document.getElementById('delivery-confirmed').value === '1' ? 1 : 0,
                urgent: checked('urgent') ? 1 : 0,
                variant_id: field('variant_id')?.value || null,
                addon_ids: [...form.querySelectorAll('input[name="addon_ids[]"]:checked')].map(input => input.value),
                file_details: details,
                combine_images: document.getElementById('combine-images').checked ? 1 : 0,
                combined_file_ids: [...form.querySelectorAll('input[name="combined_file_ids[]"]:checked:not(:disabled)')].map(input => input.value),
                images_per_page: field('images_per_page')?.value || null,
                combined_copies: field('combined_copies')?.value || null,
                combined_instructions: field('combined_instructions')?.value || null,
            };
            if (!details.length && field('quantity')) payload.quantity = field('quantity').value || null;

            try {
                const response = await fetch('{{ route('orders.calculate') }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': field('_token').value},
                    body: JSON.stringify(payload),
                    signal: activePreviewRequest.signal,
                });
                const result = await response.json();
                if (response.status === 429) {
                    const wait = result.retry_after ? ` Podrás intentarlo nuevamente en ${result.retry_after} segundos.` : '';
                    throw new Error((result.message || 'Se hicieron varios intentos en poco tiempo.') + wait);
                }
                if (!response.ok) throw new Error(result.message || 'No fue posible calcular el precio.');
                if (sequence !== calculationSequence) return false;
                lastPreview = result;
                quoteToken.value = result.quote_token;
                renderServiceConfiguration(result);
                renderPreview(result);
                validPreview = ['success', 'outside_coverage'].includes(result.calculation_status);
                return validPreview;
            } catch (error) {
                if (error.name === 'AbortError' || sequence !== calculationSequence) return false;
                summary.innerHTML = `<h3>Resumen del pedido</h3><div class="errors">${error.message}</div>`;
                submitButton.textContent = 'Reintentar cálculo';
                return false;
            } finally {
                if (sequence === calculationSequence) {
                    calculating = false;
                    activePreviewRequest = null;
                    updateSubmitState();
                }
            }
        }

        function renderPreview(result) {
            const delivery = result.delivery;
            const quoteRequest = result.service_requires_quote;
            accepted.name = quoteRequest ? 'quote_requested' : 'price_accepted';
            document.querySelector('#acceptance-label span').textContent = quoteRequest
                ? 'He revisado los datos y deseo enviar esta solicitud para recibir una cotización.'
                : 'He revisado las características del pedido y acepto el precio total mostrado';
            const address = [field('neighborhood').value, field('street').value, field('house_number').value, field('municipality').value].filter(Boolean).join(', ');
            let rows = `<p><b>Servicio:</b> ${result.service_name}</p>`;
            if(result.file_details.length) {
                const individualFiles = result.file_details.filter(detail => !detail.included_in_composition);
                if (individualFiles.length) rows += '<h3>Impresiones individuales</h3>';
                individualFiles.forEach(detail => rows += `<div class="card"><b>${detail.name}</b><br>${detail.source_pages} página(s) × ${detail.copies} copia(s) = <b>${detail.print_units} impresión(es)</b><br>Subtotal: <b>${money(detail.subtotal)}</b></div>`);
                if (result.image_composition) {
                    const composition = result.image_composition;
                    rows += `<div class="card"><h3>Diseño combinado</h3><p><b>Imágenes seleccionadas:</b> ${composition.image_count}</p>${composition.page_groups.map((names, index) => `<p><b>Página ${index + 1}:</b><br>${names.map(name => `• ${escapeHtml(name)}`).join('<br>')}</p>`).join('')}<p><b>Distribución:</b> máximo ${composition.images_per_page} imágenes por página<br><b>Páginas resultantes:</b> ${composition.resulting_pages}<br><b>Copias del diseño completo:</b> ${composition.copies}<br><b>Páginas que se imprimirán:</b> ${composition.billable_pages}<br><b>Precio por página:</b> ${money(composition.unit_price)}<br><b>Subtotal:</b> ${money(composition.subtotal)}</p></div>`;
                }
                const combinedCount = result.image_composition?.image_count || 0;
                const combinedPrints = result.image_composition?.billable_pages || 0;
                const individualPrints = individualFiles.reduce((total, detail) => total + detail.print_units, 0);
                rows += `<h3>Resumen del servicio</h3><p><b>Archivos subidos:</b> ${result.file_details.length}<br><b>Imágenes combinadas:</b> ${combinedCount}<br><b>Archivos impresos individualmente:</b> ${individualFiles.length}<br><b>Impresiones individuales:</b> ${individualPrints}<br><b>Impresiones del diseño combinado:</b> ${combinedPrints}<br>${result.image_composition ? `<b>Páginas finales por cada copia del diseño:</b> ${result.image_composition.resulting_pages}<br>` : ''}<b>Total de páginas que se imprimirán:</b> ${result.print_units}</p>`;
            } else rows += `<p><b>Archivos:</b> ${document.getElementById('files').files.length}</p>`;
            if(result.pages && !result.file_details.length) rows += `<p><b>Páginas:</b> ${result.pages}</p>`;
            if(result.copies) rows += `<p><b>Copias:</b> ${result.copies}</p>`;
            if(field('paper_size')) rows += `<p><b>Papel:</b> ${field('paper_size').selectedOptions[0].text}</p>`;
            if(field('topic')) rows += `<p><b>Tema:</b> ${field('topic').value}</p>`;
            if(field('education_level')) rows += `<p><b>Nivel:</b> ${field('education_level').value}</p>`;
            if(result.variant) rows += `<p><b>Variante/paquete:</b> ${result.variant.name}</p>`;
            if(result.addons.length) rows += `<p><b>Complementos:</b> ${result.addons.map(addon => addon.name+' '+money(addon.price)).join(', ')}</p>`;
            rows += `<p><b>Entrega:</b> ${address || 'Ubicación marcada'}</p>`;

            if (result.work_subtotal !== null) {
                rows += `<hr><p style="display:flex;justify-content:space-between"><span>Subtotal del servicio</span><b>${money(result.work_subtotal)}</b></p>`;
            } else {
                rows += '<div class="flash"><b>Este trabajo necesita revisión.</b><br>El precio del trabajo será enviado antes de comenzar. No se realizará ningún trabajo sin tu aprobación.</div>';
            }

            if (delivery) {
                rows += `<h3>Domicilio</h3><p style="display:flex;justify-content:space-between"><span>Tarifa base</span><b>${money(delivery.base)}</b></p>`;
                rows += `<p style="display:flex;justify-content:space-between"><span>${Number(result.distance_km).toFixed(2)} km × ${money(delivery.per_km)}</span><b>${money(result.distance_km * delivery.per_km)}</b></p>`;
                if (delivery.factor > 1) rows += `<p style="display:flex;justify-content:space-between"><span>Recogida y devolución</span><b>× ${delivery.factor}</b></p>`;
                rows += `<p style="display:flex;justify-content:space-between"><span>Costo de domicilio</span><b>${money(delivery.subtotal)}</b></p>`;
                if (delivery.urgent > 0) rows += `<p style="display:flex;justify-content:space-between"><span>Servicio urgente</span><b>${money(delivery.urgent)}</b></p>`;
                rows += `<p class="muted">Distancia ${Number(result.distance_km).toFixed(2)} km · ${result.duration_minutes} minutos aproximadamente</p>`;
            }

            if (result.calculation_status === 'missing_coordinates') {
                rows += '<div class="errors"><b>Confirma el punto de entrega en el mapa.</b><br>Necesitamos las coordenadas antes de calcular el domicilio.</div>';
                submitButton.textContent = 'Confirma la ubicación para continuar';
            } else if (result.calculation_status === 'route_provider_error') {
                rows += '<div class="errors"><b>No pudimos calcular el domicilio en este momento. Intenta nuevamente.</b><br><button type="button" onclick="calculatePreview()">Reintentar cálculo del domicilio</button></div>';
                rows += '<h2>Total pendiente hasta calcular el domicilio</h2>';
                submitButton.textContent = 'Domicilio pendiente de cálculo';
            } else if (result.calculation_status === 'invalid_service_price') {
                rows += '<div class="errors"><b>Falta configurar el precio de este servicio.</b></div>';
                submitButton.textContent = 'Precio no disponible';
            } else if (result.calculation_status === 'outside_coverage') {
                rows += '<div class="errors"><b>El domicilio está fuera de la cobertura automática.</b><br>Puedes enviar la solicitud para que el negocio confirme únicamente el domicilio.</div>';
                rows += '<h2>Total pendiente hasta confirmar el domicilio</h2>';
                submitButton.textContent = 'Enviar solicitud para confirmar domicilio';
            } else if (result.service_requires_quote) {
                rows += `<h2>Trabajo pendiente de cotización</h2><p>Forma de pago: ${field('payment_method').selectedOptions[0].text} · Fecha: ${field('requested_at').value || 'pendiente'}</p>`;
                submitButton.textContent = 'Enviar solicitud de cotización';
            } else {
                rows += `<hr><h2 style="display:flex;justify-content:space-between"><span>TOTAL</span><span>${money(result.total)}</span></h2>`;
                rows += `<p>Forma de pago: ${field('payment_method').selectedOptions[0].text} · Fecha: ${field('requested_at').value || 'pendiente'}</p>`;
                submitButton.textContent = `Confirmar pedido por ${money(result.total)}`;
            }
            summary.innerHTML = `<h3>Resumen del pedido</h3>${rows}`;
        }

        function renderServiceConfiguration(result) {
            if (configuredServiceId === result.service_id) return;
            configuredServiceId = result.service_id;
            const config = result.configuration;
            serviceConfiguration = config;
            document.getElementById('file-field').hidden = !config.requires_file;
            if (!config.requires_file) document.getElementById('files').value = '';
            let html = '<div class="two">';
            if (config.requires_pages && !config.requires_file) html += '<label>Cantidad aproximada de páginas<input type="number" min="1" name="pages" required></label>';
            if (config.requires_copies && !config.requires_file) html += `<label>${result.pickup_return?'Cantidad de juegos de copias':'Cantidad de copias'}<input type="number" min="1" name="copies" required></label>`;
            if (config.requires_quantity && !config.requires_file && !config.tiers.length) html += `<label>Cantidad de ${result.billing_unit || 'artículos'}<input type="number" min="1" name="quantity" required></label>`;
            if (config.allows_letter || config.allows_legal) html += `<label>Tamaño del papel<select name="paper_size">${config.allows_letter?'<option value="carta">Carta</option>':''}${config.allows_legal?'<option value="oficio">Oficio</option>':''}</select></label>`;
            if (config.requires_topic) html += '<label>¿Sobre qué tema necesitas el trabajo?<textarea name="topic" placeholder="Ejemplo: Historia de Cristóbal Colón" required></textarea></label>';
            if (config.requires_education_level) html += '<label>Grado o nivel educativo<input name="education_level" required></label>';
            if (config.requires_output_format) html += '<label>Formato de entrega<select name="output_format" required><option value="pdf">PDF</option><option value="images">Imágenes</option><option value="word">Word</option><option value="both">Word y PDF</option><option value="single_pdf">Un solo PDF</option></select></label>';
            html += '</div><div class="checks">';
            if (config.allows_duplex) html += '<label><input style="width:auto" type="checkbox" name="double_sided" value="1"> Doble cara</label>';
            if (config.allows_cover) html += '<label><input style="width:auto" type="checkbox" name="cover" value="1"> Necesito portada</label>';
            if (config.allows_images) html += '<label><input style="width:auto" type="checkbox" name="images_required" value="1"> Necesito imágenes</label>';
            if (config.allows_stapling) html += '<label><input style="width:auto" type="checkbox" name="stapled" value="1"> Engrapado</label>';
            html += '</div>';
            const groups=Object.groupBy?Object.groupBy(config.variants,v=>v.group_name):config.variants.reduce((a,v)=>((a[v.group_name]??=[]).push(v),a),{});
            Object.entries(groups).forEach(([group,options])=>{if(options.length===1)html+=`<input type="hidden" name="variant_id" value="${options[0].id}">`;else html+=`<label>${group}<select name="variant_id">${options.map(v=>`<option value="${v.id}">${v.name}${Number(v.price)>0?' (+'+money(v.price)+')':''}</option>`).join('')}</select></label>`});
            if (config.tiers.length) html += `<div class="card"><b>¿Cuántas tarjetas necesitas?</b>${config.tiers.map(t=>`<label><input style="width:auto" type="radio" name="quantity" value="${t.minimum_quantity}" required> ${t.label} — ${money(t.price)}</label>`).join('')}</div>`;
            if (config.addons.length) html += `<div class="card"><b>Complementos</b>${config.addons.map(a=>`<label><input style="width:auto" type="checkbox" name="addon_ids[]" value="${a.id}"> ${a.name} — ${a.price_type==='per_unit'?money(a.price)+' por unidad':money(a.price)}</label>`).join('')}</div>`;
            html += '<label>Instrucciones adicionales<textarea name="instructions" maxlength="1000" placeholder="Escribe aquí cualquier detalle importante para preparar tu pedido"></textarea></label>';
            document.getElementById('dynamic-service-options').innerHTML = html;
        }

        let customerMap;
        let customerMarker;

        function setCustomerLocation(latitude, longitude, recalculate = true) {
            const lat = Number(latitude);
            const lng = Number(longitude);
            field('latitude').value = lat.toFixed(7);
            field('longitude').value = lng.toFixed(7);
            document.getElementById('delivery-confirmed').value = '1';
            customerMarker.setLatLng([lat, lng]);
            customerMap.panTo([lat, lng]);
            document.getElementById('location-status').textContent = '✓ Ubicación marcada. Puedes arrastrar el marcador para ajustarla.';
            if (recalculate) calculatePreview();
        }

        function locateCustomer() {
            const status = document.getElementById('location-status');
            status.textContent = 'Obteniendo ubicación…';
            navigator.geolocation.getCurrentPosition(position => {
                setCustomerLocation(position.coords.latitude, position.coords.longitude);
            }, () => {
                status.textContent = 'No pudimos obtener tu ubicación. Puedes enviar una solicitud para cotización manual.';
                calculatePreview();
            });
        }

        const initialLatitude = Number(field('latitude').value || 13.483874);
        const initialLongitude = Number(field('longitude').value || -88.203702);
        customerMap = L.map('customer-map').setView([initialLatitude, initialLongitude], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(customerMap);
        customerMarker = L.marker([initialLatitude, initialLongitude], {draggable: true}).addTo(customerMap);
        customerMap.on('click', event => setCustomerLocation(event.latlng.lat, event.latlng.lng));
        customerMarker.on('dragend', event => { const point = event.target.getLatLng(); setCustomerLocation(point.lat, point.lng); });
        if (field('latitude').value && field('longitude').value) setCustomerLocation(initialLatitude, initialLongitude, false);

        form.addEventListener('input', event => {
            if (affectsCalculation(event.target)) scheduleCalculation();
            updateSubmitState();
        });
        form.addEventListener('change', event => {
            if (event.target.name === 'service_id') configuredServiceId = null;
            if (affectsCalculation(event.target)) scheduleCalculation();
            updateSubmitState();
        });
        accepted.addEventListener('change', updateSubmitState);
        document.getElementById('files').addEventListener('change', event => {
            const files = [...event.target.files];
            const error = validateSelectedFiles(files);
            showFileValidationError(error);
            if (error) {
                selectedFiles = [];
                event.target.value = '';
            } else {
                selectedFiles = files;
            }
            renderFileCards();
        });
        field('facade_photo')?.addEventListener('change', event => {
            const error = validateSelectedFiles([...document.getElementById('files').files]);
            showFileValidationError(error);
            if (error) event.target.value = '';
        });
        document.getElementById('individual-config').addEventListener('change', renderFileCards);
        document.getElementById('combine-images').addEventListener('change', event => {
            if (event.target.checked) {
                const selectedChoices = document.querySelectorAll('input[name="combined_file_ids[]"]:checked');
                if (selectedChoices.length < 2) {
                    document.querySelectorAll('input[name="combined_file_ids[]"]').forEach(input => input.checked = true);
                }
            }
            updateCompositionState();
        });
        form.addEventListener('submit', async event => {
            const uploadError = validateSelectedFiles([...document.getElementById('files').files]);
            showFileValidationError(uploadError);
            const selectedNames = compositionSelectedIds;
            const previewNames = lastPreview?.image_composition?.file_ids || [];
            const compositionMismatch = document.getElementById('combine-images').checked
                && (selectedNames.length !== previewNames.length || selectedNames.some((name, index) => name !== previewNames[index]));
            if (compositionMismatch) showFileValidationError('No pudimos conservar todas las imágenes seleccionadas. Revisa los archivos antes de continuar.');
            event.preventDefault();
            if (uploadError || compositionMismatch || !validPreview || calculating || !accepted.checked || submitting) return;

            submitting = true;
            const originalButtonText = submitButton.textContent || 'Confirmar y enviar pedido';
            let redirected = false;
            submitButton.disabled = true;
            submitButton.textContent = 'Enviando pedido…';
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': field('_token').value},
                    body: new FormData(form),
                });
                if (response.redirected && response.ok) {
                    redirected = true;
                    window.location.assign(response.url);
                    return;
                }
                const result = await response.json();
                if (!response.ok) {
                    const retryMessage = response.status === 429 && result.retry_after
                        ? `${result.message} Podrás intentarlo nuevamente en ${result.retry_after} segundos.`
                        : result.message;
                    const messages = Object.values(result.errors || {}).flat();
                    if (!messages.length) messages.push(retryMessage || 'No pudimos enviar el pedido. Intenta nuevamente.');
                    summary.innerHTML = `<h3>Revisa estos datos</h3><div class="errors"><ul>${messages.map(message => `<li>${escapeHtml(message)}</li>`).join('')}</ul></div><p>Los archivos seleccionados se conservaron para que puedas corregir el formulario.</p>`;
                    if (result.errors?.price_accepted) {
                        field('confirmed').checked = false;
                        accepted.checked = false;
                        const notice = messages.map(message => `<li>${escapeHtml(message)}</li>`).join('');
                        await calculatePreview();
                        summary.insertAdjacentHTML('afterbegin', `<div class="errors"><ul>${notice}</ul></div>`);
                    }
                }
            } catch (error) {
                summary.innerHTML = '<h3>No pudimos enviar el pedido</h3><div class="errors">Revisa tu conexión e intenta nuevamente. Los archivos continúan seleccionados.</div>';
            } finally {
                submitting = false;
                if (!redirected) {
                    submitButton.textContent = originalButtonText;
                    updateSubmitState();
                }
            }
        });
    </script>
@endsection
