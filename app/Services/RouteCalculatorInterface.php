<?php
namespace App\Services; interface RouteCalculatorInterface { public function calculate(float $originLatitude,float $originLongitude,float $destinationLatitude,float $destinationLongitude): RouteResult; }
