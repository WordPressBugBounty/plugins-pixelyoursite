<?php

namespace PixelYourSite;
defined('ABSPATH') || exit;

/**
 * containerDownloads class.
 *
 */
abstract class containerDownloads
{
    private $containers_path;

    public function __construct($containers_path) {
        $this->containers_path = $containers_path;
    }

    public function downloadLogFile($file) {
        if (!current_user_can('manage_pys')) {
            return;
        }
        if ($file) {
            $file = $this->containers_path . $file;

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
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
}