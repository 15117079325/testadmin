<?php
namespace maqu;

use Monolog\Logger;
use \Monolog\Handler\RotatingFileHandler;

/**
 *
 * Class Log
 * @package maqu
 */
class Log {

    private static $logger = null;

    /**
     * 构造函数
     * Log constructor.
     */
    private function __construct(){



    }

    protected static function getLogger(){
        if(!static::$logger instanceof Logger)
        {
            self::$logger = new Logger('default');
            self::$logger->pushHandler(new RotatingFileHandler(ROOT_PATH.'temp/logs/lumen.log', 5,\Monolog\Logger::INFO));
        }

        return self::$logger;
    }

    private function __clone(){}

    /**
     * Adds a log record at the INFO level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
    public static function debug($message,array $context = array()){
        $logger = self::getLogger();
        return $logger->debug($message,$context);
    }

    /**
     * Adds a log record at the INFO level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
    public static function info($message,array $context = array()){
        $logger = self::getLogger();
        return $logger->info($message,$context);
    }

    /**
     * Adds a log record at the NOTICE level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
    public static function notice($message,array $context = array()){
        $logger = self::getLogger();
        return $logger->notice($message,$context);
    }

    /**
     * Adds a log record at the WARNING level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
    public static function warn($message,array $context = array()){
        $logger = self::getLogger();
        return $logger->warn($message,$context);
    }

    /**
     * Adds a log record at the ERROR level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
    public static function error($message,array $context = array()){
        $logger = self::getLogger();
        return $logger->error($message,$context);
    }

    /**
     * Adds a log record at the CRITICAL level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
    public static function crit($message,array $context = array()){
        $logger = self::getLogger();
        return $logger->crit($message,$context);
    }

    /**
     * Adds a log record at the ALERT level.
     *
     * This method allows for compatibility with common interfaces.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
    public static function alert($message,array $context = array()){
        $logger = self::getLogger();
        return $logger->alert($message,$context);
    }

}