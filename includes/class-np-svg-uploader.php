<?php

class NpSvgUploader
{
    /**
     * Construct uploader svg format in wp
     */
    function __construct(){
        add_action('admin_init', array($this, 'addSvgSupport'));
        add_action('admin_footer', array($this, 'fixSvgThumbnailSize'));
        add_filter('upload_mimes', array($this, 'addSvgMime'));
        add_filter('wp_check_filetype_and_ext', array($this, 'wpCheckFiletypeAndExt'), 100, 4);
        add_filter('wp_generate_attachment_metadata', array( $this, 'wpGenerateAttachmentMetadata'), 10, 2);
    }

    /**
     * Fix svg thumbnail size
     */
    function fixSvgThumbnailSize(){
        echo '<style>.attachment-info .thumbnail img[src$=".svg"],#postimagediv .inside img[src$=".svg"]{width:100%}</style>';
    }

    /**
     * Generate Attachment Metadata
     *
     * @param array $metadata
     * @param int   $attachment_id
     *
     * @return array
     */
    function wpGenerateAttachmentMetadata($metadata, $attachment_id){
        if (get_post_mime_type($attachment_id) == 'image/svg+xml') {
            $svg_path = get_attached_file($attachment_id);
            $dimensions = $this->svgDimensions($svg_path);
            $metadata['width'] = $dimensions->width;
            $metadata['height'] = $dimensions->height;
        }
        return $metadata;
    }

    /**
     * Check file type and ext
     *
     * @param array   $filetype_ext_data
     * @param string  $file
     * @param string  $filename
     * @param boolean $mimes
     *
     * @return array
     */
    function wpCheckFiletypeAndExt($filetype_ext_data, $file, $filename, $mimes){
        if (substr($filename, -4) == '.svg') {
            $filetype_ext_data['ext'] = 'svg';
            $filetype_ext_data['type'] = 'image/svg+xml';
        }
        if (substr($filename, -5) == '.svgz') {
            $filetype_ext_data['ext'] = 'svgz';
            $filetype_ext_data['type'] = 'image/svg+xml';
        }
        return $filetype_ext_data;
    }

    /**
     * Add svg mime
     *
     * @param array $mimes
     *
     * @return array
     */
    public function addSvgMime($mimes = array()){
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }

    /**
     * Svg dimensions
     *
     * @param object $svg
     *
     * @return object
     */
    function svgDimensions($svg){
        $svg = simplexml_load_file($svg);
        $width = 0;
        $height = 0;
        if ($svg) {
            $attributes = $svg->attributes();
            if (isset($attributes->width, $attributes->height)) {
                $width = floatval($attributes->width);
                $height = floatval($attributes->height);
            } elseif (isset($attributes->viewBox)) {
                $sizes = explode(" ", $attributes->viewBox);
                if (isset($sizes[2], $sizes[3])) {
                    $width = floatval($sizes[2]);
                    $height = floatval($sizes[3]);
                }
            }
        }
        return (object)array('width' => $width, 'height' => $height);
    }
}