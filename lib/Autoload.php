<?php
namespace UEFORMA\ImportExportKaltura;

class Autoload {

    static $instance = null;
    static $path;
    
    static function getInstance($path) {
        if (null == self::$instance) {
            self::$instance = new Autoload();
        }
        
        self::$path = $path;
        return self::$instance;
    }
    
    public function __construct() {
        spl_autoload_register( array( $this, 'autoload' ) );
    }
    
    public function autoload($class) {
        $classParts = explode("\\", $class);
        if (count($classParts)>1) {
            $classname = str_replace("_","",end($classParts));
            $namespace = $classParts[0]."\\".$classParts[1];
            if (__NAMESPACE__ === $namespace) {
                if (isset(self::$path) && !empty(self::$path)) {
                    $it = new \RecursiveDirectoryIterator(self::$path);
                    $iterator = new \RecursiveIteratorIterator($it);
                    $display = Array ( 'php' );
                    foreach($iterator as $file){
                        $parts = explode('.', $file);
                        if (in_array(strtolower(array_pop($parts)), $display)) {
                            if ($file->getFileName() == $classname.".php") {
                                if (!class_exists($class)) {
                                    require_once $file->getPathname();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
