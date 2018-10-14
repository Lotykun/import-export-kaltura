<?php
namespace UEFORMA\ImportExportKaltura;
use UEFORMA\ImportExportKaltura\Helpers;
use UEFORMA\ImportExportKaltura\KalturaModel;
class ImportExportKaltura {
    public $kmOrigin;
    public $kmDest;
    
    public function __construct() {
        $this->kmOrigin = new KalturaModel("Origin");
        $this->kmDest = new KalturaModel("Destination");
    }
    
    public function executeimportAction() {
        $data = array();
        $data['error'] = FALSE;
        $entryid = Helpers::getRequestPostParam("entryid");
        $entry_origin = self::$kmOrigin->_client->media->get($entryid);
        $category_origin = self::$kmOrigin->_client->category->get($entry_origin->categoriesIds);
        
        $flavors = self::$kmOrigin->getFlavorsByEntryId($entryid);
        if ($flavors[0]->size > 500000){
            $data['error'] = TRUE;
            $data["errormsg"] = "SOURCE TOO BIG TO PROCCESS";
        }
        //$destination_path = UEFORMA_IMPORTEXPORTKALTURA_ROOT.'/tempvideo/';
        if (!$data['error']) {
            $response = self::$kmOrigin->download_temp_mp4($entry_origin);
            if (!$response) {
                $data['error'] = TRUE;
                $data["errormsg"] = "ERROR IN DOWNLOAD";
            }
            error_log("DOWNLOAD COMPLETE!");

            $entry_dest = self::$kmDest->upload_temp_mp4($entry_origin, $category_origin);
            if (!$entry_dest) {
                $data['error'] = TRUE;
                $data["errormsg"] = "ERROR IN UPLOAD";
            }
            error_log("UPLOAD COMPLETE!");

            $response = $this->set_entries_info($entry_origin,$entry_dest);
            if (!$response) {
                $data['error'] = TRUE;
                $data["errormsg"] = "ERROR IN SET INFO";
            }
        }
        wp_send_json($data);
    }
    
    public function set_entries_info($entry_origin, $entry_dest){
        global $wpdb;
        $row = array();
        $table_name = $wpdb->prefix."import_export_kaltura";
        
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $wp_info_origin = self::$kmOrigin->get_wpinfo_entry($entry_origin);
            $wp_info_dest = self::$kmDest->get_wpinfo_entry($entry_dest);

            //ORIGIN
            $row['entryidOrigin'] = $entry_origin->id;
            $row['nameOrigin'] = $entry_origin->name;
            $row['createdOrigin'] = date('Y-m-d', $entry_origin->createdAt);
            $row['urldataOrigin'] = $entry_origin->dataUrl;
            $row['urldownloadOrigin'] = $entry_origin->downloadUrl;
            $row['urlthumbOrigin'] = $entry_origin->thumbnailUrl;
            $row['kalturacatidOrigin'] = $entry_origin->categoriesIds;
            $row['kalturatagsOrigin'] = $entry_origin->tags;
            if (isset($wp_info_origin["id"])) {
                $row['wpidOrigin'] = $wp_info_origin["id"];
            }
            if (isset($wp_info_origin["thumbnailid"])) {
                $row['wpidthumbOrigin'] = $wp_info_origin["thumbnailid"];
            }
            if (isset($wp_info_origin["catid"])) {
                $row['wpcatidOrigin'] = $wp_info_origin["catid"];
            }
            if (isset($wp_info_origin["catid"])) {
                $row['wptagidsOrigin'] = $wp_info_origin["tags"];
            }
            
            //DESTINATION
            $row['entryidDest'] = $entry_dest->id;
            $row['nameDest'] = $entry_dest->name;
            $row['createdDest'] = date('Y-m-d',$entry_dest->createdAt);
            $row['urldataDest'] = $entry_dest->dataUrl;
            $row['urldownloadDest'] = $entry_dest->downloadUrl;
            $row['urlthumbDest'] = $entry_dest->thumbnailUrl;
            $row['kalturacatidDest'] = $entry_dest->categoriesIds;
            $row['kalturatagsDest'] = $entry_dest->tags;
            if (isset($wp_info_dest["id"])) {
                $row['wpidDest'] = $wp_info_dest["id"];
            }
            if (isset($wp_info_dest["thumbnailid"])) {
                $row['wpidthumbDest'] = $wp_info_dest["thumbnailid"];
            }
            if (isset($wp_info_dest["catid"])) {
                $row['wpcatidDest'] = $wp_info_dest["catid"];
            }
            if (isset($wp_info_dest["catid"])) {
                $row['wptagidsDest'] = $wp_info_dest["tags"];
            }
            
            $row['status'] = 'migrated';

            $result = $wpdb->insert($table_name,$row);
        }        
        return $result;
    }
    
    public function install(){
        // Create connection
        global $wpdb;

	$table_name = $wpdb->prefix."import_export_kaltura";
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		entryidOrigin varchar(55) DEFAULT '' NOT NULL,
                nameOrigin varchar(255) DEFAULT '' NOT NULL,
                createdOrigin datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                urldataOrigin varchar(255) DEFAULT '' NOT NULL,
                urldownloadOrigin varchar(255) DEFAULT '' NOT NULL,
                urlthumbOrigin varchar(255) DEFAULT '' NOT NULL,
                kalturacatidOrigin INT(11) NOT NULL,
                kalturatagsOrigin varchar(255) DEFAULT '',
                wpidOrigin INT(11),
                wpidthumbOrigin INT(11),
                wpcatidOrigin INT(11),
                wpcatsidsOrigin varchar(255) DEFAULT '',
                entryidDest varchar(55) DEFAULT '' NOT NULL,
                nameDest varchar(255) DEFAULT '' NOT NULL,
                createdDest datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                urldataDest varchar(255) DEFAULT '' NOT NULL,
                urldownloadDest varchar(255) DEFAULT '' NOT NULL,
                urlthumbDest varchar(255) DEFAULT '' NOT NULL,
                kalturacatidDest INT(11) NOT NULL,
                kalturatagsDest varchar(255) DEFAULT '',
                wpidDest INT(11),
                wpidthumbDest INT(11),
                wpcatidDest INT(11),
                wpcatsidsDest varchar(255) DEFAULT '',
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
    }
    
    public function deinstall(){
        /*ACTIONS TO DO WHEN PLUGIN IS DEINSTALLED*/
    }
}