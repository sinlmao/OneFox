<?php

/** 
 * @author ryan<zer0131@vip.qq.com>
 * @desc 核心入口文件
 */

namespace OneFox;

final class Onefox {
    
    private static $_ext = '.php';
    private static $_startTime = 0;
    private static $_memoryStart = 0;
    private static $_error = array();

    public static function start(){
        if (!defined('APP_PATH')) {
            die('APP_PATH is not difined.');
        }
        if (!defined('ONEFOX_PATH')) {
            die('ONEFOX_PATH is not defined.');
        }
        
        //--------记录程序开始时间及使用内存--------//
        self::$_startTime = microtime(true);
        self::$_memoryStart = memory_get_usage(true);
        
        //--------设置时区--------//
        date_default_timezone_set("PRC");
        
        //--------定义常量--------//
        define('IS_CLI',PHP_SAPI=='cli' ? true:false);
        !defined('DS') && define('DS', DIRECTORY_SEPARATOR);//目录分隔符
        !defined('PATH_DEEP') && define('PATH_DEEP', 3);//Controller目录结构
        !defined('DEBUG') && define('DEBUG', false);//调试模式
        !defined('LOG_PATH') && define('LOG_PATH', APP_PATH.DS.'Log');//日志目录
        !defined('CONF_PATH') && define('CONF_PATH', APP_PATH.DS.'Config');//配置目录
        !defined('TPL_PATH') && define('TPL_PATH', APP_PATH.DS.'Tpl');//模板目录
        !defined('DEFAULT_MODULE') && define('DEFAULT_MODULE', 'Index');//默认执行模块
        !defined('DEFAULT_CONTROLLER') && define('DEFAULT_CONTROLLER', 'Index');//默认执行控制器
        !defined('DEFAULT_ACTION') && define('DEFAULT_ACTION', 'index');//默认执行方法
        !defined('XSS_MODE') && define('XSS_MODE', true);//开启XSS过滤
        !defined('ADDSLASHES_MODE') && define('ADDSLASHES_MODE', false);//不使用addslashes
        if(version_compare(PHP_VERSION,'5.4.0','<')){
            ini_set('magic_quotes_runtime',0);
            define('MAGIC_QUOTES_GPC',get_magic_quotes_gpc()?true:false);
        }
        else{
            define('MAGIC_QUOTES_GPC',false);
        }
        
        //--------设置错误显示--------//
        if (DEBUG) {
            ini_set('display_errors', 'On');
            error_reporting(E_ALL ^ E_NOTICE);
        }
        
        //--------自动注册类--------//
        spl_autoload_register(array('OneFox\Onefox', 'autoload'));
        
        //--------运行结束执行--------//
        register_shutdown_function(array('OneFox\Onefox', 'end'));
        
        //--------自定义错误处理--------//
        set_error_handler(array('OneFox\Onefox', 'errorHandler'));
        
        //--------处理未捕捉的异常--------//
        set_exception_handler(array('OneFox\Onefox', 'exceptionHandler'));
       
        //--------处理请求数据--------//
        Request::deal();
        
        //--------简单路由--------//
        Dispatcher::dipatcher();
        
        //--------执行--------//
        self::_exec();
        
        return ;
    }
    
    public static function autoload($className){
        $class = $className;
        $path = strtr($class, '\\', DS);
        if (0 === strpos($class, 'OneFox\\')) {
            $file = ONEFOX_PATH.substr($path, strlen('OneFox')).self::$_ext;//加载框架类
        } else {
            $file = APP_PATH.DS.$path.self::$_ext;//加载应用类
        }
        if (is_file($file)) {
            require_once $file;
            return class_exists($className);
        }
        return false;
    }
    
    private static function _exec(){
        define('CURRENT_MODULE', Dispatcher::getModuleName());
        define('CURRENT_CONTROLLER', Dispatcher::getControllerName());
        define('CURRENT_ACTION', Dispatcher::getActionName());
        $className = 'Controller\\';
        $className .= empty(CURRENT_MODULE) ? '' : ucfirst(CURRENT_MODULE).'\\';
        $className .= ucfirst(CURRENT_CONTROLLER).'Controller';
        if (!class_exists($className)) {
            throw new \RuntimeException('类不存在');
        }
        try{
            $obj = new \ReflectionClass($className);
            
            if ($obj->isAbstract()) {
                throw new \RuntimeException('抽象方法不可被实例化');
            }
            
            $class = $obj->newInstance();
            
            //前置操作
            if ($obj->hasMethod(CURRENT_ACTION.'Before')) {
                $beforeMethod = $obj->getMethod(CURRENT_ACTION.'Before');
                if ($beforeMethod->isPublic() && !$beforeMethod->isStatic()) {
                    $beforeMethod->invoke($class);
                }
            }
            
            $method = $obj->getMethod(CURRENT_ACTION.'Action');
            if ($method->isPublic() && !$method->isStatic()) {
                $method->invoke($class);
            } else {
                throw new \RuntimeException('请求方法不存在');
            }
            
            
            //后置操作
            if ($obj->hasMethod(CURRENT_ACTION.'After')) {
                $afterMethod = $obj->getMethod(CURRENT_ACTION.'After');
                if ($afterMethod->isPublic() && !$afterMethod->isStatic()) {
                    $afterMethod->invoke($class);
                }
            }
        } catch (\ReflectionException $e) {
            self::_halt($e);
        }
    }
    
    public static function errorHandler($errno, $errstr, $errfile, $errline){
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    
    public static function exceptionHandler($e){
        self::$_error['message'] = $e->getMessage();
        self::$_error['file'] = $e->getFile();
        self::$_error['line'] = $e->getLine();
        self::$_error['trace'] = $e->getTraceAsString();
    }
    
    public static function end(){
        if (self::$_error) {
            $e = self::$_error;
            self::$_error = array();
            self::_halt($e);
        }
        if (DEBUG) {
            $log_info['url'] = $_SERVER['REQUEST_URI'];
            $log_info['runtime'] = number_format((microtime(true) - self::$_startTime) * 1000, 0).'ms';
            $log_info['runmem'] = number_format( (memory_get_usage(true) - self::$_memoryStart) / (1024), 0, ",", "." ).'kb';
            C::log($log_info);
        }
    }
    
    private static function _halt($e){
        if (DEBUG) {
            if(IS_CLI){
                exit(iconv('UTF-8','gbk',$e['message']).PHP_EOL.'FILE: '.$e['file'].'('.$e['line'].')'.PHP_EOL.$e['trace']);
            }
            include_once ONEFOX_PATH.DS.'Tpl'.DS.'excetion.html';
        } else {
            $url = Config::get('404_page');
            if ($url) {
                Response::redirect($url);
            }
            header('HTTP/1.1 404 Not Found');
            header('Status:404 Not Found');
            include_once ONEFOX_PATH.DS.'Tpl'.DS.'404.html';
        }
    }
}

Onefox::start();
