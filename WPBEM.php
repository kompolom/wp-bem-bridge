<?php
/**
 * Plugin Name: Wordpress-BEM bridge
 * Description: Позволяет писать шаблоны в терминах БЭМ
 * Version: 0.2.0
 * Author: Evgeniy Baranov
 * Author URI: http://kompolom.ru
 * License: MIT
 *
 * @license     http://opensource.org/licenses/mit MIT
 * @package     Wordpress
 */

namespace kompolom;

define('block', 'block');
define('elem', 'elem');
define('mods', 'mods');
define('content', 'content');
define('mix', 'mix');
define('js', 'js');

class WPBEM {

    const HTML = 1,
          BEMJSON = 2;

    protected $bundles_path; //Путь к шаблонам
    protected $bundles_url; //Url старических файлов
    protected $platform = 'desktop'; //Текущая платформа
    protected $bundle = 'index'; //Текущий бандл
    protected $includeBemjson; //Подключить <bundle>.php
    public $bemjson = [];
    public $engine;
    static $instance;
    static $stat;

    protected function __construct($bundle = 'index', $includeBemjson){
        self::$instance = $this;
        $this->bundle = $bundle;
        $this->includeBemjson = $includeBemjson;
        $this->engine = new \BEM\BH();
        $this->init_platform();
        $this->init_bundle();

        if (filter_var(getenv('TPL_DEBUG'), FILTER_VALIDATE_BOOLEAN)) { $this->bem_make(); }
        $this->register_bundle_static();
        if ($this->includeBemjson) {
            $this->bemjson = (include $this->locate_bundle($this->bundle));
        }
    }

    public static function get_instance($bundle = 'index', $includeBemjson = false){
        return self::$instance? self::$instance : new WPBEM($bundle, $includeBemjson);
    }

    public static function instance($bundle = 'index', $includeBemjson = false){
        return self::$instance? self::$instance : new WPBEM($bundle, $includeBemjson);
    }

    /**
     * autodetect needle platform
     * @return {String} platform name
     */
    function autoselect_patoform()
    {
        $MobileDetect = new \Mobile_Detect;
        if (is_admin()) {
            $platform = 'admin';
        } elseif ($MobileDetect->isTablet()) {
            $platform = 'touch-pad';
        } elseif ($MobileDetect->isMobile()) {
            $platform = 'touch-phone';
        } else {
            $platform = 'desktop';
        }
        return $platform;
    }

    /**
     * Sets current platform global variavles
     * @param [$platform] platform name
     * @return void
     */
    function init_platform()
    {
        $this->platform = $this->autoselect_patoform();
        $platform = $this->platform;
        $this->bundles_url = get_bloginfo('template_url')."/$platform.pages/";
        $this->bundles_path = TEMPLATEPATH."/$platform.pages/";
    }

    /**
    * Provide bundle,
    * Provide BH.php template engine 
    * @param string $bundle selected bundle name
    * @return void
    */
    function init_bundle()
    {
        $this->engine = (include $this->locate_bundle($this->bundle, true, 'bh.php', true));
    }

    public static function bundle($name) {
        return self::get_instance()->get_bundle($name);
    }

    /**
     * Запускает сборку
     */
    function bem_make()
    {
        $platform = $this->platform;
        $target = $this->bundle;
        self::$stat = exec("cd ".TEMPLATEPATH." && ./node_modules/enb/bin/enb make $platform.pages/$target");// $platform.pages/$target");
        add_action('wp_head', array($this, 'inject_stat'));
    }

    function inject_stat(){
        $stat = self::$stat;
        echo "<script>console.log('$this->platform.$this->bundle: $stat');</script>";
    }


    /**
     * Returns needle bundle for current platform
     * @param string $bundle bundle name
     * @return mixed include result
     */
    public function get_bundle($bundle, $nofallback = false)
    {
        return (include $this->locate_bundle($bundle, $nofallback));
    }

    /**
     * Search bundle
     * @param srting $bundle_name bundle to search
     * @return bundle path if found, has fallback to desktop level
     */
    function locate_bundle($bundle, $nofb = false, $suffix = 'php', $rewritePlatform = false)
    {
        $subpath = $bundle.DIRECTORY_SEPARATOR.$bundle.'.'.$suffix;
        if(file_exists($this->bundles_path.$subpath)) {
            return $this->bundles_path.$subpath;
        } elseif (file_exists(TEMPLATEPATH."/desktop.pages/".$subpath)) {
            if ($rewritePlatform){ set_platform('desktop');}
            return TEMPLATEPATH."/desktop.pages/".$subpath;
        } elseif (!$nofb and file_exists(TEMPLATEPATH."/$bundle.$suffix")) {
            return TEMPLATEPATH."/$bundle.$suffix";
        } else {
            throw new Error("Bundle '$bundle' not found.");
        }
    }

    /**
     * Возвращает html строку
     * @public
     */
    public function get_html($bemjson = false) {
        return $this->res($bemjson? $bemjson : $this->bemjson);
    }

    /**
     * Выводит html строку
     * @public
     */
    public function html($bemjson = null) {
        echo $this->res($bemjson? $bemjson : $this->bemjson);
    }

    public function json($bemjson = null) {
        echo $this->res($bemjson? $bemjson : $this->bemjson, self::BEMJSON);
    }

    /**
     * Возвращает bemjson в html или json формате
     * @public
     */
    public function res($bemjson, $format = self::HTML) {
        return $format === self::HTML? $this->engine->apply($bemjson) : json_encode($bemjson, JSON_UNESCAPED_UNICODE);
    }

    /**
    * Register static files for bundle
    * @return void
    */
    function register_bundle_static()
    {
        $BUNDLE = $this->bundle;
        $BUNDLES_URL = $this->bundles_url;
        $ver = wp_get_theme()->version;
        wp_register_script($BUNDLE."-js", $BUNDLES_URL.$BUNDLE.'/_'.$BUNDLE.'.js', array(), $ver, true);
        wp_register_style($BUNDLE."-css", $BUNDLES_URL.$BUNDLE.'/_'.$BUNDLE.'.css', [], $ver );
        wp_enqueue_script($BUNDLE.'-js');
        wp_enqueue_style($BUNDLE.'-css');
    }
}

