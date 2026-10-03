<?php
namespace App\Services;
use App\Models\{Order,Quote,QuoteNotification};
interface WhatsAppNotifierInterface { public function prepareQuoteReady(Order $order, Quote $quote): QuoteNotification; }
