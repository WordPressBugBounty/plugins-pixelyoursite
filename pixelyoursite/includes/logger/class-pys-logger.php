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

    /**
     * Log file size cap (bytes) and its floor; see get_max_size().
     */
    const LOG_DEFAULT_MAX_SIZE = 10485760;
    const LOG_MIN_MAX_SIZE     = 1048576;
    const LOG_ABSOLUTE_MAX_SIZE = 268435456;

    /**
     * getLogs( $lines ): backward read chunk and hard byte cap.
     */
    const LOG_TAIL_CHUNK     = 65536;
    const LOG_TAIL_MAX_BYTES = 4194304;

    /**
     * Option name for storing log file random suffix
     */
    const LOG_SUFFIX_OPTION = 'pys_free_log_file_suffix';

	public function __construct( ) {
		$this->log_path = trailingslashit( PYS_FREE_PATH ).'logs/';
	}

    public function init() {
        $this->isEnabled = PYS()->getOption('pys_logs_enable');

        // Migrate old log files to new randomized names (one-time operation)
        $this->maybe_migrate_log_files();

        // Always ensure protection files exist, regardless of logging being enabled
        // This prevents PII exposure even if logs were created before and logging is now disabled
        $this->create_protection_files();
    }

    /**
     * Get or generate random suffix for log file names
     * This suffix is stored in WordPress options and persists across requests
     *
     * @return string Random suffix (32 characters hex string)
     */
    public static function get_log_suffix() {
        $suffix = get_option( self::LOG_SUFFIX_OPTION );

        if ( empty( $suffix ) ) {
            $suffix = self::generate_random_suffix();
            update_option( self::LOG_SUFFIX_OPTION, $suffix, false );
        }

        return $suffix;
    }

    /**
     * Generate a cryptographically secure random suffix
     *
     * @return string Random suffix (32 characters hex string)
     */
    protected static function generate_random_suffix() {
        if ( function_exists( 'wp_generate_password' ) ) {
            // Use WordPress function for better compatibility
            return wp_hash( wp_generate_password( 32, true, true ) . time() . wp_rand() );
        }

        // Fallback to PHP random_bytes
        return bin2hex( random_bytes( 16 ) );
    }

    /**
     * Migrate old log files to new randomized names
     * This runs once when the suffix doesn't exist yet
     *
     * @return void
     */
    protected function maybe_migrate_log_files() {
        // Check if migration is needed (suffix option doesn't exist)
        $suffix = get_option( self::LOG_SUFFIX_OPTION );

        if ( ! empty( $suffix ) ) {
            // Already migrated
            return;
        }

        // Generate new suffix
        $new_suffix = self::generate_random_suffix();
        update_option( self::LOG_SUFFIX_OPTION, $new_suffix, false );

        // Migrate existing log files
        $this->migrate_log_file( 'pys_debug.log', 'pys_debug_' . $new_suffix . '.log' );
    }

    /**
     * Rename a log file from old name to new name
     *
     * @param string $old_name Old file name
     * @param string $new_name New file name
     * @return bool True if renamed successfully or file didn't exist
     */
    protected function migrate_log_file( $old_name, $new_name ) {
        $old_path = $this->log_path . $old_name;
        $new_path = $this->log_path . $new_name;

        if ( file_exists( $old_path ) && is_writable( $old_path ) ) {
            return @rename( $old_path, $new_path );
        }

        return true;
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
                    if (!mkdir($concurrentDirectory = $this->log_path, 0755, true) && !is_dir($concurrentDirectory)) {
                        throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                    }
                }
                $temphandle = @fopen( $file, 'w+' ); // @codingStandardsIgnoreLine.
                if ( is_resource( $temphandle ) ) {
                    @fclose( $temphandle ); // @codingStandardsIgnoreLine.
                    if ( ! defined( 'FS_CHMOD_FILE' ) ) {
                        define( 'FS_CHMOD_FILE', 0644 );
                    }
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
     * Create protection files (.htaccess and index.php) in logs directory
     * to prevent direct access to log files
     *
     * @return void
     */
    protected function create_protection_files() {
        if ( ! is_dir( $this->log_path ) ) {
            return;
        }

        // Create .htaccess file to deny access
        $htaccess_file = $this->log_path . '.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            $htaccess_content = "# Deny access to all files in this directory\n";
            $htaccess_content .= "# Apache 2.4+\n";
            $htaccess_content .= "<IfModule authz_core_module>\n";
            $htaccess_content .= "    Require all denied\n";
            $htaccess_content .= "</IfModule>\n\n";
            $htaccess_content .= "# Apache 2.2\n";
            $htaccess_content .= "<IfModule !authz_core_module>\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</IfModule>\n";

            @file_put_contents( $htaccess_file, $htaccess_content );
            @chmod( $htaccess_file, 0644 );
        }

        // Create index.php file to prevent directory listing
        $index_file = $this->log_path . 'index.php';
        if ( ! file_exists( $index_file ) ) {
            $index_content = "<?php\n// Silence is golden.\n";
            @file_put_contents( $index_file, $index_content );
            @chmod( $index_file, 0644 );
        }
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
        if ( ! current_user_can( 'manage_pys' ) || ! current_user_can( 'manage_pys_data_export' ) ) {
            return;
        }
		$file = static::get_log_file_path();
		if ($file) {

			// Cap an already-oversized file first, even when logging is off now.
			$this->maybe_trim();
			clearstatcache( true, $file );

			$h = is_file( $file ) ? @fopen( $file, 'rb' ) : false;
			if ( ! $h ) {
				error_log( "File not found: " . $file );
				exit;
			}
			$stat = fstat( $h );
			$size = $stat ? (int) $stat['size'] : 0;

			// Drop any buffered output so the stream below carries the file only.
			for ( $level = ob_get_level(); $level > 0; $level-- ) {
				@ob_end_clean();
			}

			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="'.basename($file).'"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . $size);

			// Exactly $size bytes: lines appended meanwhile must not overrun Content-Length.
			$out = fopen( 'php://output', 'wb' );
			stream_copy_to_stream( $h, $out, $size );
			fclose( $h );
			exit;
		} else {
			http_response_code(404);
			echo "File not found.";
		}
	}

    /**
     * Get a log file name.
     *
     * File names consist of the handle, followed by a random suffix, .log.
     * The random suffix prevents direct access to log files on Nginx servers.
     *
     * @return string The log file name.
     */
    public static function get_log_file_name( ) {
        return 'pys_debug_' . self::get_log_suffix() . '.log';
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

            if ( false !== $result ) {
                $stat = fstat( $this->handle );
                if ( $stat && $stat['size'] > $this->get_max_size() ) {
                    $this->maybe_trim( (int) $stat['size'] );
                }
            }
        }

        return false !== $result;
    }

    /**
     * Maximum size of the log file on disk, in bytes.
     *
     * Default 10 MB, filter `pys_log_max_size`, clamped to 1 MB..256 MB.
     *
     * @return int
     */
    protected function get_max_size() {
        $max = (int) apply_filters( 'pys_log_max_size', self::LOG_DEFAULT_MAX_SIZE, $this );

        return min( self::LOG_ABSOLUTE_MAX_SIZE, max( self::LOG_MIN_MAX_SIZE, $max ) );
    }

    /**
     * Cut the log file down to its newest half when it is over the size cap.
     *
     * add() holds a write-only 'a' handle, so this opens its own 'c+' handle
     * for read/truncate/write. flock() is advisory and a writer that does not
     * take it (the 'a' handle in add()) can still append between our read and
     * our write: such a line may be lost. That is accepted for a debug log.
     *
     * @param int|null $known_size File size when the caller already has it.
     *
     * @return bool True when the file was trimmed.
     */
    public function maybe_trim( $known_size = null ) {
        $file = static::get_log_file_path();
        $max  = $this->get_max_size();

        if ( null === $known_size ) {
            if ( ! is_file( $file ) ) {
                return false;
            }
            clearstatcache( true, $file );
            $known_size = (int) @filesize( $file );
        }

        if ( $known_size <= $max ) {
            return false;
        }

        $handle = @fopen( $file, 'c+' ); // @codingStandardsIgnoreLine.
        if ( ! $handle ) {
            return false;
        }

        $trimmed = false;

        if ( flock( $handle, LOCK_EX ) ) {
            $stat = fstat( $handle );
            $size = $stat ? (int) $stat['size'] : 0;

            if ( $size > $max ) {
                $keep = intdiv( $max, 2 );
                // One byte before the cut tells us whether the cut is already on a line start.
                fseek( $handle, $size - $keep - 1 );
                $data = (string) stream_get_contents( $handle );

                if ( "\n" === substr( $data, 0, 1 ) ) {
                    $data = substr( $data, 1 );
                } else {
                    $nl   = strpos( $data, "\n" );
                    $data = false === $nl ? '' : substr( $data, $nl + 1 );
                }

                ftruncate( $handle, 0 );
                rewind( $handle );
                fwrite( $handle, $data ); // @codingStandardsIgnoreLine.
                fflush( $handle );
                $trimmed = true;
            }

            flock( $handle, LOCK_UN );
        }

        fclose( $handle ); // @codingStandardsIgnoreLine.

        return $trimmed;
    }

    /**
     * Read the log.
     *
     * @param int $lines 0 reads the whole file (old behaviour). A positive
     *                   number returns only the last $lines lines, read
     *                   backwards in chunks and never more than 4 MB.
     *
     * @return string Valid UTF-8 when $lines > 0.
     */
    public function getLogs( $lines = 0 ) {
        $file  = static::get_log_file_path();
        $lines = (int) $lines;

        if ( ! is_file( $file ) ) {
            return "";
        }

        if ( $lines <= 0 ) {
            return file_get_contents( $file );
        }

        $handle = @fopen( $file, 'rb' ); // @codingStandardsIgnoreLine.
        if ( ! $handle ) {
            return "";
        }

        $stat  = fstat( $handle );
        $pos   = $stat ? (int) $stat['size'] : 0;
        $buf   = '';
        $read  = 0;
        $found = 0;

        while ( $pos > 0 && $read < self::LOG_TAIL_MAX_BYTES && $found <= $lines ) {
            $chunk = min( self::LOG_TAIL_CHUNK, $pos, self::LOG_TAIL_MAX_BYTES - $read );
            $pos  -= $chunk;
            fseek( $handle, $pos );
            $data   = (string) fread( $handle, $chunk );
            $read  += strlen( $data );
            $found += substr_count( $data, "\n" );
            $buf    = $data . $buf;
        }

        fclose( $handle ); // @codingStandardsIgnoreLine.

        $trailing = "\n" === substr( $buf, -1 );
        $parts    = explode( "\n", $trailing ? substr( $buf, 0, -1 ) : $buf );

        // The read did not reach the start of the file: the first line is cut.
        // A single line longer than the byte cap has no newline to cut at, so it is kept.
        if ( $pos > 0 && count( $parts ) > 1 ) {
            array_shift( $parts );
        }
        if ( count( $parts ) > $lines ) {
            $parts = array_slice( $parts, -$lines );
        }

        $out = implode( "\n", $parts ) . ( $trailing ? "\n" : '' );

        // A cut inside a multi-byte character makes esc_html()/htmlspecialchars() return ''.
        // Own cleanup: wp_check_invalid_utf8() is unreliable on WP < 6.9 (iconv without //IGNORE)
        // and skips non-UTF-8 blog charsets.
        if ( ! preg_match( '//u', $out ) ) {
            $out = function_exists( 'mb_scrub' ) ? mb_scrub( $out, 'UTF-8' ) : (string) @iconv( 'UTF-8', 'UTF-8//IGNORE', $out );
        }

        return $out;
    }

    /**
     * Remove/delete the chosen file.
     *
     * @return bool
     */
    public function remove( )
    {
        if ( ! current_user_can( 'manage_pys' ) || ! current_user_can( 'manage_pys_data_export' ) ) {
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