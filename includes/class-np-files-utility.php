<?php
defined('ABSPATH') or die;

class NpFilesUtility {

    /**
     * Clear directory
     *
     * @param string $dir
     * @param bool   $hard - if need to delete directory itself
     *
     * @throws Exception
     */
    public static function emptyDir($dir, $hard = false) {
        if (!file_exists($dir) || !is_readable($dir)) {
            return;
        }

        if (is_file($dir) && false === @unlink($dir)) {
            throw new Exception("Can't unlink $dir");
        }

        if (!is_dir($dir)) {
            return;
        }

        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != '.' && $object != '..') {
                $path = $dir . '/' . $object;
                if (strtolower(filetype($path)) == 'dir') {
                    self::emptyDir($path, true);
                } else if (false === @unlink($path)) {
                    throw new Exception("Can't unlink $path");
                }
            }
        }
        reset($objects);
        if ($hard && false === @rmdir($dir)) {
            throw new Exception("Can't rmdir $dir");
        }
    }

    /**
     * Copy file or directory recursive
     *
     * @param string $source
     * @param string $destination
     */
    public static function copyRecursive($source, $destination) {
        if (is_file($source)) {
            if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0777, true)) {
                return;
            }
            copy($source, $destination);

        } else if (is_dir($source)) {
            if (!is_dir($destination) && !mkdir($destination)) {
                return;
            }
            if ($dh = opendir($source)) {
                while (($file = readdir($dh)) !== false) {
                    if ('.' == $file || '..' == $file) {
                        continue;
                    }
                    self::copyRecursive($source . '/' . $file, $destination . '/' . $file);
                }
                closedir($dh);
            }
        }
    }

    /**
     * Create directory if it not exists
     *
     * @param string $dir
     *
     * @throws Exception
     */
    public static function createDir($dir) {
        if (!is_dir($dir) && false === @mkdir($dir, 0777, true)) {
            throw new Exception("Can't mkdir $dir");
        }
    }


    /**
     * Extract zip
     *
     * @param string $source
     * @param string $dest
     *
     * @throws Exception
     */
    public static function extractZip($source, $dest) {
        $source = self::normalizePath($source);
        $dest = self::normalizePath($dest);

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            if ($zip->open($source) !== true) {
                throw new Exception('ZipArchive open error');
            }
            if ($zip->extractTo($dest) !== true) {
                throw new Exception('ZipArchive extractTo error');
            }
            $zip->close();
        } else {
            // PHP 5.2.4 fallback
            include_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
            $archive = new PclZip($source);

            if (0 == $archive->extract(PCLZIP_OPT_PATH, $dest)) {
                throw new Exception('PclZip extract error: ' . $archive->errorInfo(true));
            }
        }
    }

    /**
     * Replace backslashes
     *
     * @param string $path
     *
     * @return string
     */
    public static function normalizePath($path) {
        return str_replace("\\", "/", $path);
    }
}