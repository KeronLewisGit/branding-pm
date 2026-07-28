<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Set some default values. It is possible to add all defines that can be set
    | in dompdf_config.inc.php. You can also override the entire config file.
    |
    */

    'show_warnings' => false,   // Throw an Exception on warnings from dompdf

    'public_path' => null,  // Override the public path if needed

    /*
     * Dejavu Sans font is missing glyphs for converted entities, turn it off if you need to show &euro; and &pound;.
     */
    'convert_entities' => true,

    'options' => [
        /**
         * The location of the DOMPDF font directory
         *
         * The location of the directory where DOMPDF will store fonts and font metrics
         * Note: This directory has to exist and be writable by the webserver process.
         */
        'font_dir' => storage_path('fonts'),

        /**
         * The location of the DOMPDF font cache directory
         *
         * This directory contains the cached font metrics for the fonts used by DOMPDF.
         * This directory can be the same as DOMPDF_FONT_DIR
         *
         * Note: This directory has to exist and be writable by the webserver process.
         */
        'font_cache' => storage_path('fonts'),

        /**
         * The location of a temporary directory.
         *
         * The directory specified must be writable by the executing process.
         * The temporary directory is required to download remote images and when
         * using the PDFLib back end.
         */
        'temp_dir' => sys_get_temp_dir(),

        /**
         * dompdf's "chroot"
         *
         * Utilized by dompdf's default file:// protocol URI validation rule.
         * All local files opened by dompdf must be in a subdirectory of the directory
         * or directories specified by this option.
         * DO NOT set this value to '/' since this could allow an attacker to use dompdf to
         * read any files on the server. This should be an absolute path.
         *
         * ==== IMPORTANT ====
         * This setting may increase the risk of system exploit. Do not change
         * this settings without understanding the consequences. Additional
         * documentation is available on the dompdf wiki at:
         * https://github.com/dompdf/dompdf/wiki
         */
        'chroot' => realpath(base_path()),

        /**
         * Protocol whitelist
         *
         * Protocols and PHP wrappers allowed in URIs, and the validation rules
         * that determine if a resource may be loaded. Full support is not guaranteed
         * for the protocols/wrappers specified by this array.
         */
        'allowed_protocols' => [
            'data://' => ['rules' => []],
            'file://' => ['rules' => []],
            'http://' => ['rules' => []],
            'https://' => ['rules' => []],
        ],

        /**
         * Operational artifact (log files, temporary files) path validation
         */
        'artifactPathValidation' => null,

        /**
         * @var string
         */
        'log_output_file' => null,

        /**
         * Whether to enable font subsetting or not.
         */
        'enable_font_subsetting' => false,

        /**
         * The PDF rendering backend to use
         *
         * Valid settings are 'PDFLib', 'CPDF', 'GD', and 'auto'. 'auto' will
         * look for PDFLib and use it if found, or if not it will fall back on
         * CPDF. 'GD' renders PDFs to graphic files.
         */
        'pdf_backend' => 'CPDF',

        /**
         * html target media view which should be rendered into pdf.
         * List of types and parsing rules for future extensions:
         * http://www.w3.org/TR/REC-html40/types.html
         *   screen, tty, tv, projection, handheld, print, braille, aural, all
         */
        'default_media_type' => 'screen',

        /**
         * The default paper size.
         *
         * North America standard is "letter"; other countries generally "a4"
         * @see \Dompdf\Adapter\CPDF::PAPER_SIZES for valid sizes
         */
        'default_paper_size' => 'letter',

        /**
         * The default paper orientation.
         *
         * The orientation of the page (portrait or landscape).
         */
        'default_paper_orientation' => 'portrait',

        /**
         * The default font family
         *
         * Used if no suitable fonts can be found. This must exist in the font folder.
         */
        'default_font' => 'serif',

        /**
         * Image DPI setting
         *
         * This setting determines the default DPI setting for images and fonts.
         */
        'dpi' => 96,

        /**
         * Enable embedded PHP
         *
         * If this setting is set to true then DOMPDF will automatically evaluate
         * embedded PHP contained within <script type="text/php"> ... </script> tags.
         *
         * ==== IMPORTANT ====
         * Enabling this for documents you do not trust (e.g. arbitrary remote html
         * pages) is a security risk. Set this option to false if you wish to process
         * untrusted documents. This setting may increase the risk of system exploit.
         */
        'enable_php' => false,

        /**
         * Enable inline JavaScript
         *
         * If this setting is set to true then DOMPDF will automatically insert
         * JavaScript code contained within <script type="text/javascript"> ... </script> tags.
         */
        'enable_javascript' => true,

        /**
         * Enable remote file access
         *
         * If this setting is set to true, DOMPDF will access remote sites for
         * images and CSS files as required.
         *
         * ==== IMPORTANT ====
         * This can be a security risk, in particular in combination with enable_php
         * and allowing remote html code to be passed to $dompdf = new DOMPDF();
         * $dompdf->load_html(...); This allows anonymous users to download legally
         * doubtful internet content which on serving may expose your server.
         *
         * This runtime is offline-first — leave remote access disabled.
         */
        'enable_remote' => false,

        /**
         * List of allowed remote hosts
         *
         * Each value of the array must be a valid hostname.
         * This will be used to filter which resources can be loaded in combination
         * with enable_remote. When set to null, all remote hosts are allowed.
         */
        'allowed_remote_hosts' => null,

        /**
         * A ratio applied to the fonts height to be more like browsers' line height
         */
        'font_height_ratio' => 1.1,

        /**
         * Use the HTML5 Lib parser
         *
         * @deprecated This feature is now always on in dompdf 2.x
         */
        'enable_html5_parser' => true,
    ],

];
