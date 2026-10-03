<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::table('services',function(Blueprint $t){$t->boolean('requires_copies')->default(false);$t->boolean('requires_topic')->default(false);$t->boolean('requires_education_level')->default(false);$t->boolean('allows_cover')->default(false);$t->boolean('allows_images')->default(false);$t->boolean('allows_stapling')->default(false);$t->boolean('requires_output_format')->default(false);$t->boolean('requires_pickup_instructions')->default(false);$t->boolean('requires_deposit')->default(false);});
  Schema::table('orders',function(Blueprint $t){$t->boolean('requires_quote')->default(false)->index();$t->boolean('requires_deposit')->default(false);$t->json('service_configuration_snapshot')->nullable();$t->string('topic')->nullable();$t->string('education_level')->nullable();$t->string('output_format')->nullable();$t->boolean('images_required')->nullable();});
  foreach(['impresion-blanco-negro'=>['requires_file'=>1,'requires_pages'=>1,'requires_copies'=>1,'allows_stapling'=>1],'impresion-color'=>['requires_file'=>1,'requires_pages'=>1,'requires_copies'=>1,'allows_stapling'=>1],'copias-fisicas'=>['requires_file'=>0,'requires_pages'=>1,'requires_copies'=>1,'requires_pickup_instructions'=>1,'allows_stapling'=>1],'escaneo'=>['requires_file'=>0,'requires_pages'=>1,'requires_output_format'=>1,'allows_duplex'=>0],'trabajo-escolar'=>['requires_file'=>0,'requires_pages'=>1,'requires_topic'=>1,'requires_education_level'=>1,'allows_cover'=>1,'allows_images'=>1,'allows_duplex'=>0],'digitacion'=>['requires_file'=>0,'requires_pages'=>1,'requires_topic'=>1,'requires_output_format'=>1,'allows_duplex'=>0]] as $slug=>$values) \Illuminate\Support\Facades\DB::table('services')->where('slug',$slug)->update($values);
 }
 public function down():void{Schema::table('orders',fn(Blueprint $t)=>$t->dropColumn(['requires_quote','requires_deposit','service_configuration_snapshot','topic','education_level','output_format','images_required']));Schema::table('services',fn(Blueprint $t)=>$t->dropColumn(['requires_copies','requires_topic','requires_education_level','allows_cover','allows_images','allows_stapling','requires_output_format','requires_pickup_instructions','requires_deposit']));}
};
