<?php

namespace PixelYourSite;
defined('ABSPATH') || exit;

/**
 * PYS_Logger class.
 *
 */
class PYS_Logger
{

    protected $isEnabled = false;
    /**
     * Stores open file handles.
     */
    protected $handle = null;

	protected $log_path = null;

	public function __construct( ) {
		$this->log_path = trailingslashit( PYS_FREE_PATH ).'logs/';
	}

    public function init() {
        $this->isEnabled = PYS()->getOption('pys_logs_enable');
    }

    /**
     * Destructor.
     *
     * Cleans up open file handles.
     */
    public function __destruct() {
        if ( is_resource( $this->handle ) ) {
            fclose( $this->handle ); // @codingStandardsIgnoreLine.
        }
    }

    public function debug($message,$args = null) {
        $this->log('debug',$message,$args);
    }

    public function error($message,$args = null) {
        $this->log('error',$message,$args);
    }

	protected function log($level,$message,$args = null) {
        if(!$this->isEnabled) return;
        if($args) {
            $message .= " \nArgs: ".print_r($args,true);
        }
        $this->handle(time(),$level,$message,[]);
    }

    /**
     * Handle a log entry.
     *
     * @param int    $timestamp Log timestamp.
     * @param string $level emergency|alert|critical|error|warning|notice|info|debug.
     * @param string $message Log message.
     * @param array  $context {
     *      Additional information for log handlers.
     * }
     *
     * @return bool False if value was not handled and true if value was handled.
     */
	protected function handle( $timestamp, $level, $message, $context ) {

        $time_string = date( 'c', $timestamp );
        $entry = "{$time_string} {$level} {$message}";

        return $this->add( $entry );
    }

    /**
     * Open log file for writing.
     *
     * @param string $mode Optional. File mode. Default 'a'.
     * @return bool Success.
     */
    protected function open(  $mode = 'a' ) {
        if ( $this->is_open() ) {
            return true;
        }

        $file = static::get_log_file_path(  );

        if ( $file ) {
            if ( ! file_exists( $file ) ) {
                if( !is_dir( $this->log_path ) ) {
                    mkdir( $this->log_path, 0777, true );
                }
                $temphandle = @fopen( $file, 'w+' ); // @codingStandardsIgnoreLine.
                if ( is_resource( $temphandle ) ) {
                    @fclose( $temphandle ); // @codingStandardsIgnoreLine.

                    @chmod( $file, FS_CHMOD_FILE ); // @codingStandardsIgnoreLine.
                }
            }

            $resource = @fopen( $file, $mode ); // @codingStandardsIgnoreLine.

            if ( $resource ) {
                $this->handle = $resource;
                return true;
            }
        }

        return false;
    }

    /**
     * Get a log file path.
     *
     * @return string The log file path or false if path cannot be determined.
     */
    public static function get_log_file_path( ) {
        return trailingslashit( PYS_FREE_PATH ).'logs/' . static::get_log_file_name( );
    }
    public static function get_log_file_url( ) {

        return trailingslashit( PYS_FREE_URL ) .'logs/'. static::get_log_file_name( );
    }

    public function downloadLogFile() {
        if ( ! current_user_can( 'manage_pys' ) ) {
            return;
        }
        $file = static::get_log_file_path();
        if ($file) {

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($file).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));

            if (file_exists($file)) {
                readfile($file);
            } else {
                error_log("File not found: " . $file);
            }
            exit;
        } else {
            http_response_code(404);
            echo "File not found.";
        }
    }

    /**
     * Get a log file name.
     *
     * File names consist of the handle, followed by the date, followed by a hash, .log.
     *
     * @return string The log file name or false if cannot be determined.
     */
    public static function get_log_file_name( ) {
        return 'pys_debug.log';
    }

    /**
     * Check if a handle is open.
     *
     * @return bool True if $handle is open.
     */
    protected function is_open( ) {
        return is_resource( $this->handle );
    }

    /**
     * Close a handle.
     *
     * @return bool success
     */
    protected function close() {
        $result = false;

        if ( $this->is_open() ) {
            $result = fclose( $this->handle ); // @codingStandardsIgnoreLine.
            $this->handle = null;
        }

        return $result;
    }

    /**
     * Add a log entry to chosen file.
     *
     * @param string $entry Log entry text.
     *
     * @return bool True if write was successful.
     */
    protected function add( $entry ) {
        $result = false;

        if ( $this->open() && is_resource( $this->handle ) ) {
            $result = fwrite( $this->handle, $entry . PHP_EOL ); // @codingStandardsIgnoreLine.
        }

        return false !== $result;
    }

    public function getLogs( ) {
        if(is_file( static::get_log_file_path() ))
            return file_get_contents(static::get_log_file_path());
        return "";
    }

    /**
     * Remove/delete the chosen file.
     *
     * @return bool
     */
    public function remove( )
    {
        if ( ! current_user_can( 'manage_pys' ) ) {
            return;
        }
        $removed = false;
        $file = realpath($this::get_log_file_path());
        if (is_file($file) && is_writable($file)) { // phpcs:ignore WordPress.VIP.FileSystemWritesDisallow.file_ops_is_writable
            $this->close(); // Close first to be certain no processes keep it alive after it is unlinked.
            $removed = unlink($file); // phpcs:ignore WordPress.VIP.FileSystemWritesDisallow.file_ops_unlink
        }

        return $removed;
    }
}