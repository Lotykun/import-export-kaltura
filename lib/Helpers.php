<?php
/*EXTERNAL FUNCTIONS FOR THE PLUGIN... IN ORDER TO CALL THEM IS Helpers::function_name*/
namespace UEFORMA\ImportExportKaltura;
class Helpers {
    private static $_settings = null;
    
    public static function getRequestUrl() {
        return esc_url_raw($_SERVER['REQUEST_URI']);
    }
    public static function getServerUrl($location, $path = null) {
        $url = self::getOption('serviceUrl'.$location);
        $url = rtrim($url, '/');
        if ($path) {
            $url .= $path;
        }

        return esc_url_raw($url);
    }
    public static function getOption( $name, $default = null ) {
        $name    = is_string( $name ) ? $name : null;
        $default = is_bool( $default ) ? $default : null;

        if (is_null( self::$_settings)) {
            self::$_settings = self::getDefaultSettings();
        }
        $settings = self::$_settings;

        if (isset( $settings[$name])) {
            return $settings[$name];
        } else {
            return $default;
        }
    }
    public static function getDefaultSettings() {
        $defaultSettings = require( UEFORMA_IMPORTEXPORTKALTURA_ROOT . '/settings.php' );
        return $defaultSettings;
    }
    public static function getFileUploadParams($km) {
        $params = array(
            'host' => self::getServerUrl($km->_location),
            'ks' => $km->_session,
        );
        return $params;
    }
    public static function isEntryMigrated($entryid) {
        global $wpdb;
        $table_name = $wpdb->prefix."import_export_kaltura";
        $result = $wpdb->get_row('SELECT status FROM '.$table_name.' WHERE entryidOrigin = "'.$entryid.'"');
        if (isset($result)) {
            if ($result->status !== "migrated" && $result->status !== "done") {
                $response = FALSE;
            } else {
                $response = TRUE;
            }
        } else {
            $response = FALSE;
        }
        
        return $response;
    }
}