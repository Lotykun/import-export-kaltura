<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace UEFORMA\ImportExportKaltura;
use UEFORMA\ImportExportKaltura\Autoload;

define('UEFORMA_IMPORTEXPORTKALTURA_PLUGIN_FILE', __FILE__ );
define('UEFORMA_IMPORTEXPORTKALTURA_ROOT', dirname( __FILE__ ));
define('UEFORMA_IMPORTEXPORTKALTURA_NAMESPACE', "ImportExportKaltura");

if (file_exists(UEFORMA_IMPORTEXPORTKALTURA_ROOT.'/lib/Autoload.php')) {
    require_once(UEFORMA_IMPORTEXPORTKALTURA_ROOT.'/lib/Autoload.php');
}
if (file_exists(UEFORMA_IMPORTEXPORTKALTURA_ROOT.'/vendor/autoload.php')) {
    require_once(UEFORMA_IMPORTEXPORTKALTURA_ROOT.'/vendor/autoload.php');
}

$Autoloader = Autoload::getInstance(UEFORMA_IMPORTEXPORTKALTURA_ROOT);
$uedmimportexport = new ImportExportKaltura();

/*$per_page = 15;

$page = 5;
$entries = $uedmimportexport->kmOrigin->list_media_entries_paged($page,$per_page);
if (count($entries->objects)>0){
    $totalCount = $entries->totalCount;
    $total_pages = ceil( $totalCount / $per_page );

    for ($i=1;$i<$total_pages;$i++) {
        foreach ($entries->objects as $entry) {
            $entryid = $entry->id;*/
            $entryid = "0_0w1g4lxn";
            echo "+++++++++++++INICIO SINCRONIZACION ENTRY++++++++++++++++++++++++++++++".PHP_EOL;
            echo $entryid.PHP_EOL;
            
            echo PHP_EOL;
            echo date("YmdHis",time())."->START getting entry".PHP_EOL;
            $entry_origin = $uedmimportexport->kmOrigin->_client->media->get($entryid);
            $category_origin = $uedmimportexport->kmOrigin->_client->category->get($entry_origin->categoriesIds);
            echo date("YmdHis",time())."->END getting entry".PHP_EOL;
            
            echo PHP_EOL;
            echo date("YmdHis",time())."->START downloading entry".PHP_EOL;
            $response = $uedmimportexport->kmOrigin->download_temp_mp4($entry_origin);
            echo date("YmdHis",time())."->END downloading entry".PHP_EOL;
            
            echo PHP_EOL;
            echo date("YmdHis",time())."->START uploading entry".PHP_EOL;
            $entry_dest = $uedmimportexport->kmDest->upload_temp_mp4($entry_origin, $category_origin);
            echo date("YmdHis",time())."->END uploading entry".PHP_EOL;
            
            echo PHP_EOL;
            echo date("YmdHis",time())."->START checking entry".PHP_EOL;
            $i=0;
            do {
                $entryReady = $uedmimportexport->kmDest->is_entry_ready($entry_dest);
                $okFlavors = $entryReady['flavorsok'];
                $totalFlavors = $entryReady['flavorstotal'];
                $length = (int)(($okFlavors/$totalFlavors)*100);
                echo printf("\r[%-100s] %d%% (%2d/%2d Flavors completed)", str_repeat("=", $length). ">", $length, $okFlavors, $totalFlavors);
                sleep(1);
                $i++;
            } while ($length < 100);
            echo "".PHP_EOL;
            echo date("YmdHis",time())."->END checking entry".PHP_EOL;
            $loty = "";
            
            echo PHP_EOL;
            echo date("YmdHis",time())."->START saving data entry".PHP_EOL;
            //$response = $this->set_entries_info($entry_origin,$entry_dest);
            echo date("YmdHis",time())."->END saving data entry".PHP_EOL;
            echo "+++++++++++++FIN SINCRONIZACION ENTRY++++++++++++++++++++++++++++++".PHP_EOL;
        /*}
        $entries = $uedmimportexport->kmOrigin->list_media_entries_paged($page,$per_page);
    }
}
*/