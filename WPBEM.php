<?php
/**
 * Plugin Name: Wordpress-BEM bridge
 * Description: Позволяет писать шаблоны в терминах БЭМ
 * Version: 0.3.1
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
    protected $scope = 'public';
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
     * Aвтоматичестки определяет нужную платформу на основе браузера
     * пользователя
     * @return {String} platform name
     */
    function autoselect_patoform()
    {
        $MobileDetect = new \Mobile_Detect;
        if (is_admin()) {
            $platform = 'admin';
        } elseif ($MobileDetect->isMobile()) {
            $platform = 'touch-phone';
        } else {
            $platform = 'desktop';
        }
        return $platform;
    }

    /**
     * Инициализирует свойства зависящие от платформы.
     * @return void
     */
    function init_platform()
    {
        $this->set_platform();
        $platform = $this->platform;
        $this->bundles_url = get_bloginfo('template_url')."/$platform.pages/";
        $this->bundles_path = TEMPLATEPATH."/$platform.pages/";
    }

    /**
     * Устанавливает внутренюю переменную $platform в соответствии с переданным
     * параметром, или на основе браузера пользователя, если платформа не
     * указана.
     * @param string $platform платформа
     * @return void
     */
    function set_platform($platform = null)
    {
        $this->platform = $platform? $platform : $this->autoselect_patoform();
    }

    /**
     * Подключает шаблонизаторы.
     * @return void
     */
    function init_bundle()
    {
        $this->btree = (include $this->locate_bundle($this->bundle, true, 'btree.php', true));
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
        self::$stat = exec("cd ".TEMPLATEPATH." && ./node_modules/enb/bin/enb make $platform.pages/$target");
        add_action('wp_head', array($this, 'inject_stat'));
    }

    /**
     * Добавляет отладочную информацию в вывод.
     * @callback
     */
    function inject_stat(){
        $stat = self::$stat;
        echo "<script>console.log('$this->platform.$this->bundle: $stat');</script>";
    }


    /**
     * Подключает и возвращает бандл с шаблонами для текущей платформы
     * @param string $bundle bundle name
     * @return mixed include result
     */
    public function get_bundle($bundle, $nofallback = false)
    {
        return (include $this->locate_bundle($bundle, $nofallback));
    }

    /**
     * Ищет бандл на файловой системе.
     * @param srting $bundle искомый бандл
     * @param bool $nofb не использовать fallback механизм
     * @param srting $suffix расширение файла. (bh.php, btree.php)
     * @return bundle path if found, has fallback to desktop level
     */
    function locate_bundle($bundle, $nofb = false, $suffix = 'php', $rewritePlatform = false)
    {
        $subpath = $bundle.DIRECTORY_SEPARATOR.$bundle.'.'.$suffix;
        if(file_exists($this->bundles_path.$subpath)) {
            return $this->bundles_path.$subpath;
        } elseif (file_exists(TEMPLATEPATH."/desktop.pages/".$subpath)) {
            if ($rewritePlatform){ $this->set_platform('desktop');}
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

    /**
     * Выводит json строку
     */
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
     * Оборачивает данные в корневой блок и выводит готовую html строку. Главным
     * образом предназначена для отдачи целых страниц. В 99% случаях именно этот
     * метод стоит вызвать для получения результата.
     * @param mixed $data исходные данные для шаблонизации
     * @return void
     */
    public function render($data = null){
        $this->html($this->get_ctx($data));
    }

    public function set_scope($scope = 'public') {
        $this->scope = $scope;
        return $this;
    }

    /**
     * Оборачивает данные в корневой блок - входную точку для шаблонизатора
     * @return array|object данные в формате BEMJSON
     */
    function get_ctx($data){
        return $this->build_tree([
            'block' => 'root',
            'scope' => $this->scope,
            'view' => $this->bundle,
            'head' => $this->get_wphead(),
            'footer' => $this->get_wpfoot(),
            'title' => wp_title('', false),
            'data' => $data
        ]);
    }

    /**
     * Строит BEMJSON по переданным данным. Аналог BEMTREE
     * @return array|object BEMJSON
     */
    public function build_tree($data){
        return $this->btree->processBemJson($data);
    }

    /**
     * Выполняет wordpress hook wp_head 
     * @return string результат выполнения хука
     */
    function get_wphead(){
        ob_start();
        wp_head();
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    /**
     * Выполняет wordpress hook wp_footer
     * @return string результат выполнения хука
     */
    function get_wpfoot(){
        ob_start();
        wp_footer();
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    /**
     * Регистрирует статические файлы с помощью стандартного Wordpress
     * механизма.
     * @return void
     */
    function register_bundle_static()
    {
        $BUNDLE = $this->bundle;
        $BUNDLES_URL = $this->bundles_url;
        $ver = wp_get_theme()->version;
        wp_register_script($BUNDLE."-js", $BUNDLES_URL.$BUNDLE.'/'.$BUNDLE.'.js', array(), $ver, true);
        wp_register_style($BUNDLE."-css", $BUNDLES_URL.$BUNDLE.'/'.$BUNDLE.'.css', [], $ver );
        wp_enqueue_script($BUNDLE.'-js');
        wp_enqueue_style($BUNDLE.'-css');
    }
}

