<?php
/**
 * Plugin Name: Variable Font Sampler
 * Plugin URI: https://github.com/mitradranirban/variable-font-sampler/blob/main/variable-font-sampler.php
 * Description: A WordPress plugin for showcasing variable fonts with interactive controls.
 * Version: 1.1.0
 * Author: Dr Anirban Mitra
 * Author URI: https://fonts.atipra.in
 * License: GPL v3 or later
 * Text Domain: variable-font-sampler
 * Updated: 2025-07-11 09:00:00
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Varifosa_Sampler {
    
    private $plugin_url;
    private $plugin_path;
    private $plugin_upload_dir;
    private $plugin_upload_url;
    private $obfuscated_dir = 'fonts'; // Name of the subdir in uploads for fonts

    // Plugin version constant for cache busting
    const VERSION = '1.1.0';
    
    public function __construct() {
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_path = plugin_dir_path(__FILE__);

        // Setup uploads directory paths/urls for assets
        $upload_dir = wp_upload_dir();
        $this->plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        $this->plugin_upload_url = trailingslashit($upload_dir['baseurl']) . 'variable-font-sampler/';
        if (!file_exists($this->plugin_upload_dir)) {
            wp_mkdir_p($this->plugin_upload_dir);
        }

        // Ensure obfuscated font folder exists
        $this->ensure_obfuscated_fonts_dir();

        add_action('init', array($this, 'varifosa_init'));
        add_action('wp_enqueue_scripts', array($this, 'varifosa_enqueue_scripts'));
        add_shortcode('font_sampler', array($this, 'varifosa_font_sampler_shortcode'));
        
        register_activation_hook(__FILE__, array($this, 'varifosa_activate'));
        register_deactivation_hook(__FILE__, array($this, 'varifosa_deactivate'));

        // Admin interface
        add_action('admin_menu', array($this, 'varifosa_admin_menu'));
        add_action('admin_post_varifosa_upload_font', array($this, 'varifosa_handle_upload'));
        add_action('admin_enqueue_scripts', array($this, 'varifosa_admin_enqueue'));

        // Obfuscate font file access
        add_action('init', array($this, 'varifosa_register_font_rewrite'));
        add_action('template_redirect', array($this, 'varifosa_font_serve'));
    }

    private function ensure_obfuscated_fonts_dir() {
        $font_dir = $this->plugin_upload_dir . $this->obfuscated_dir . '/';
        if (!file_exists($font_dir)) {
            wp_mkdir_p($font_dir);
            // Add .htaccess to block direct access if running on Apache
            $htaccess = $font_dir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all\n");
            }
        }
    }
    
    public function varifosa_init() {
        // Initialize plugin
    }
    
    public function varifosa_enqueue_scripts() {
        // Enqueue our custom font sampler script and CSS from uploads directory
        $upload_dir = wp_upload_dir();
        $plugin_upload_url = trailingslashit($upload_dir['baseurl']) . 'variable-font-sampler/';
        $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        
        // Ensure files exist (in case activation did not run)
        if (!file_exists($plugin_upload_dir . 'font-sampler.css')) {
            $this->varifosa_create_css_file();
        }
        if (!file_exists($plugin_upload_dir . 'font-sampler.js')) {
            $this->varifosa_create_js_file();
        }

        wp_enqueue_style(
            'font-sampler',
            $plugin_upload_url . 'font-sampler.css',
            array(),
            self::VERSION
        );
        wp_enqueue_script(
            'font-sampler',
            $plugin_upload_url . 'font-sampler.js',
            array('jquery'),
            self::VERSION,
            true
        );
        
        // Localize script with translations
        wp_localize_script('font-sampler', 'fontSampler', array(
            'i18n' => array(
                'fontLoadError' => esc_html__('Failed to load the font. Please check if the font file is accessible.', 'variable-font-sampler'),
                'invalidFont' => esc_html__('Invalid font file. Please upload a valid variable font.', 'variable-font-sampler')
            )
        ));
    }

    /**
     * Admin Menu for Font Upload
     */
    public function varifosa_admin_menu() {
        add_menu_page(
            __('Variable Font Sampler', 'variable-font-sampler'),
            __('Font Sampler', 'variable-font-sampler'),
            'manage_options',
            'varifosa-font-sampler',
            array($this, 'varifosa_admin_page'),
            'dashicons-editor-bold',
            80
        );
    }

    /**
     * Enqueue admin scripts/styles
     */
    public function varifosa_admin_enqueue($hook) {
        if ($hook === 'toplevel_page_varifosa-font-sampler') {
            wp_enqueue_style('varifosa-admin', $this->plugin_url . 'admin.css', array(), self::VERSION);
        }
    }

    /**
     * Admin Page Output
     */
    public function varifosa_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.'));
        }
        $msg = '';
        if (!empty($_GET['uploaded'])) {
            $msg = esc_html__('Font uploaded successfully!', 'variable-font-sampler');
        } elseif (!empty($_GET['error'])) {
            $msg = esc_html__('Error uploading font.', 'variable-font-sampler');
        }

        $font_files = $this->varifosa_list_fonts();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Variable Font Sampler - Upload Fonts', 'variable-font-sampler'); ?></h1>
            <?php if ($msg): ?>
                <div class="notice notice-success"><p><?php echo $msg; ?></p></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('varifosa_upload_font', 'varifosa_nonce'); ?>
                <input type="hidden" name="action" value="varifosa_upload_font">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="fontfile"><?php esc_html_e('Variable Font File (.ttf, .otf, .woff2, .woff):', 'variable-font-sampler'); ?></label></th>
                        <td>
                            <input type="file" id="fontfile" name="fontfile" accept=".ttf,.otf,.woff2,.woff" required>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Upload Font', 'variable-font-sampler')); ?>
            </form>
            <hr>
            <h2><?php esc_html_e('Uploaded Fonts', 'variable-font-sampler'); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Font File', 'variable-font-sampler'); ?></th>
                        <th><?php esc_html_e('Obfuscated URL (use in shortcode)', 'variable-font-sampler'); ?></th>
                        <th><?php esc_html_e('Actions', 'variable-font-sampler'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($font_files as $file): ?>
                        <tr>
                            <td><?php echo esc_html($file['name']); ?></td>
                            <td>
                                <input type="text" readonly value="<?php echo esc_url($file['url']); ?>" style="width:100%">
                            </td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(['delete_font' => $file['hash'], '_wpnonce' => wp_create_nonce('varifosa_delete_font_' . $file['hash'])], menu_page_url('varifosa-font-sampler', false))); ?>"
                                   onclick="return confirm('<?php esc_js_e('Are you sure you want to delete this font?', 'variable-font-sampler'); ?>')">
                                   <?php esc_html_e('Delete', 'variable-font-sampler'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($font_files)): ?>
                        <tr><td colspan="3"><?php esc_html_e('No fonts uploaded yet.', 'variable-font-sampler'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <p><?php esc_html_e('Copy the above Obfuscated URL and use it as the "font" attribute in the [font_sampler] shortcode.', 'variable-font-sampler'); ?></p>
        </div>
        <?php
        // Handle font deletion here (since we need to show the message on the admin page)
        $this->varifosa_handle_font_delete();
    }

    /**
     * Handle Font Upload
     */
    public function varifosa_handle_upload() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to upload.', 'variable-font-sampler'));
        }
        check_admin_referer('varifosa_upload_font', 'varifosa_nonce');
        $redirect = admin_url('admin.php?page=varifosa-font-sampler');
        if (!isset($_FILES['fontfile']) || $_FILES['fontfile']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(add_query_arg('error', 1, $redirect));
            exit;
        }

        $file = $_FILES['fontfile'];
        $allowed_types = array(
            'ttf'   => 'font/ttf',
            'otf'   => 'font/otf',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2'
        );
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowed_types)) {
            wp_redirect(add_query_arg('error', 1, $redirect));
            exit;
        }

        $font_dir = $this->plugin_upload_dir . $this->obfuscated_dir . '/';
        $obfuscated_name = md5(uniqid('', true) . $file['name'] . time()) . '.' . $ext;

        // Move the font file
        if (move_uploaded_file($file['tmp_name'], $font_dir . $obfuscated_name)) {
            // Set restrictive permissions
            @chmod($font_dir . $obfuscated_name, 0640);
            wp_redirect(add_query_arg('uploaded', 1, $redirect));
        } else {
            wp_redirect(add_query_arg('error', 1, $redirect));
        }
        exit;
    }

    /**
     * List fonts in the obfuscated directory
     */
    private function varifosa_list_fonts() {
        $font_dir = $this->plugin_upload_dir . $this->obfuscated_dir . '/';
        $url_base = home_url('/varifosa-font/');
        $files = array();
        if (file_exists($font_dir)) {
            $list = glob($font_dir . '*.{ttf,otf,woff,woff2}', GLOB_BRACE);
            foreach ($list as $file) {
                $filename = basename($file);
                $hash = pathinfo($filename, PATHINFO_FILENAME);
                $files[] = array(
                    'name' => $filename,
                    'hash' => $hash,
                    'url'  => $url_base . $hash
                );
            }
        }
        return $files;
    }

    /**
     * Font deletion handler (on admin page load)
     */
    private function varifosa_handle_font_delete() {
        if (!isset($_GET['delete_font']) || !current_user_can('manage_options')) {
            return;
        }
        $hash = preg_replace('/[^a-f0-9]/', '', $_GET['delete_font']);
        $nonce = $_GET['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'varifosa_delete_font_' . $hash)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Invalid nonce for font deletion.', 'variable-font-sampler') . '</p></div>';
            return;
        }
        $font_dir = $this->plugin_upload_dir . $this->obfuscated_dir . '/';
        $success = false;
        foreach (['ttf', 'otf', 'woff', 'woff2'] as $ext) {
            $font_file = $font_dir . $hash . '.' . $ext;
            if (file_exists($font_file)) {
                $success = @unlink($font_file);
            }
        }
        if ($success) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Font deleted successfully.', 'variable-font-sampler') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('Failed to delete font file.', 'variable-font-sampler') . '</p></div>';
        }
    }

    /**
     * Register rewrite rule to serve fonts with obfuscated URL
     */
    public function varifosa_register_font_rewrite() {
        add_rewrite_rule(
            '^varifosa-font/([a-f0-9]+)$',
            'index.php?varifosa_font=$matches[1]',
            'top'
        );
        add_rewrite_tag('%varifosa_font%', '([a-f0-9]+)');
    }

    /**
     * Serve font file if obfuscated URL is hit (with permission check)
     */
    public function varifosa_font_serve() {
        $font_hash = get_query_var('varifosa_font');
        if (!$font_hash) {
            return;
        }

        // Only allow logged-in users to access font files (further restrict with capabilities if needed)
        if (!is_user_logged_in()) {
            status_header(403);
            exit('Forbidden');
        }

        $font_dir = $this->plugin_upload_dir . $this->obfuscated_dir . '/';
        foreach (['ttf', 'otf', 'woff', 'woff2'] as $ext) {
            $font_file = $font_dir . $font_hash . '.' . $ext;
            if (file_exists($font_file)) {
                $mime = $this->varifosa_get_mime_type($ext);
                header('Content-Type: ' . $mime);
                header('Content-Disposition: inline; filename="font.' . $ext . '"');
                header('Content-Length: ' . filesize($font_file));
                @readfile($font_file);
                exit;
            }
        }
        status_header(404);
        exit('Font not found');
    }

    private function varifosa_get_mime_type($ext) {
        $map = [
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2'
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }
    
    public function varifosa_font_sampler_shortcode($atts) {
        $atts = shortcode_atts(array(
            'font' => '',
            'text' => 'The quick brown fox jumps over the lazy dog',
            'size' => '32',
            'controls' => 'true',
            'id' => uniqid('font-sampler-')
        ), $atts);
        
        // Validate font URL
        if (!empty($atts['font'])) {
            $font_url = esc_url($atts['font'], array('http', 'https'));
            if (empty($font_url)) {
                return sprintf(
                    '<div class="font-error">%s</div>',
                    esc_html__('Invalid font URL provided.', 'variable-font-sampler')
                );
            }
        } else {
            return sprintf(
                '<div class="font-error">%s</div>',
                esc_html__('No font URL specified.', 'variable-font-sampler')
            );
        }
        
        // Sanitize size
        $size = absint($atts['size']);
        if ($size < 12 || $size > 120) {
            $size = 32; // Default if outside valid range
        }
        
        ob_start();
        ?>
        <div class="font-sampler-container" id="<?php echo esc_attr($atts['id']); ?>">
            <div class="font-sampler-preview">
                <div class="font-sample" 
                     data-font="<?php echo esc_url($font_url); ?>"
                     data-text="<?php echo esc_attr($atts['text']); ?>"
                     data-size="<?php echo esc_attr($size); ?>"
                     data-controls="<?php echo esc_attr($atts['controls']); ?>">
                    <?php echo esc_html($atts['text']); ?>
                </div>
            </div>
            
            <?php if ($atts['controls'] === 'true'): ?>
            <div class="font-sampler-controls">
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-size"><?php esc_html_e('Font Size:', 'variable-font-sampler'); ?></label>
                    <input type="range" id="<?php echo esc_attr($atts['id']); ?>-size" 
                           class="size-control" min="12" max="120" value="<?php echo esc_attr($size); ?>">
                    <span class="size-value"><?php echo esc_html($size); ?>px</span>
                </div>
                
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-weight"><?php esc_html_e('Font Weight:', 'variable-font-sampler'); ?></label>
                    <input type="range" id="<?php echo esc_attr($atts['id']); ?>-weight" 
                           class="weight-control" min="100" max="900" value="400" step="100">
                    <span class="weight-value">400</span>
                </div>
                
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-width"><?php esc_html_e('Font Width:', 'variable-font-sampler'); ?></label>
                    <input type="range" id="<?php echo esc_attr($atts['id']); ?>-width" 
                           class="width-control" min="50" max="200" value="100">
                    <span class="width-value">100%</span>
                </div>
                
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-text"><?php esc_html_e('Sample Text:', 'variable-font-sampler'); ?></label>
                    <input type="text" id="<?php echo esc_attr($atts['id']); ?>-text" 
                           class="text-control" value="<?php echo esc_attr($atts['text']); ?>">
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function varifosa_create_css_file() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
        }
        
        $css_content = '
.font-sampler-container {
    margin: 20px 0;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f9f9f9;
}

.font-sampler-preview {
    margin-bottom: 20px;
    padding: 20px;
    background: white;
    border-radius: 4px;
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.font-sample {
    text-align: center;
    line-height: 1.2;
    word-wrap: break-word;
    max-width: 100%;
}

.font-sampler-controls {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.control-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.control-group label {
    font-weight: bold;
    font-size: 14px;
}

.control-group input[type="range"] {
    width: 100%;
}

.control-group input[type="text"] {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.size-value,
.weight-value,
.width-value {
    font-size: 12px;
    color: #666;
    text-align: center;
}

.font-error {
    background-color: #f8d7da;
    color: #721c24;
    padding: 10px;
    margin-bottom: 10px;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
    text-align: center;
}

@media (max-width: 768px) {
    .font-sampler-controls {
        grid-template-columns: 1fr;
    }
}';
        
        file_put_contents($plugin_upload_dir . 'font-sampler.css', $css_content);
    }
    
    private function varifosa_create_js_file() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
        }
        
        $js_content = '
jQuery(document).ready(function($) {
    // Initialize font samplers
    $(".font-sampler-container").each(function() {
        var container = $(this);
        var sample = container.find(".font-sample");
        var fontUrl = sample.data("font");
        
        if (fontUrl) {
            loadFont(fontUrl, sample).catch(function(error) {
                console.error("Font loading failed:", error);
                sample.before(\'<div class="font-error">\' + 
                    fontSampler.i18n.fontLoadError + 
                    \'</div>\');
            });
        }

        // Size control
        container.find(".size-control").on("input", function() {
            var size = $(this).val();
            sample.css("font-size", size + "px");
            container.find(".size-value").text(size + "px");
        });
        
        // Weight control
        container.find(".weight-control").on("input", function() {
            var weight = $(this).val();
            var width = container.find(".width-control").val() || 100;
            
            sample.css({
                "font-weight": weight,
                "font-variation-settings": "\\"wght\\" " + weight + ", \\"wdth\\" " + width
            });
            container.find(".weight-value").text(weight);
        });
        
        // Width control
        container.find(".width-control").on("input", function() {
            var width = $(this).val();
            var weight = container.find(".weight-control").val() || 400;
            
            sample.css({
                "font-stretch": width + "%",
                "font-variation-settings": "\\"wght\\" " + weight + ", \\"wdth\\" " + width
            });
            container.find(".width-value").text(width + "%");
        });
        
        // Text control
        container.find(".text-control").on("input", function() {
            var text = $(this).val();
            sample.text(text);
        });
    });
    
    async function loadFont(url, element) {
        try {
            if (!window.FontFace) {
                throw new Error("FontFace API not supported");
            }
            
            const fontFace = new FontFace("VariableFont-" + Date.now(), "url(" + url + ")");
            const loadedFont = await fontFace.load();
            document.fonts.add(loadedFont);
            
            element.css({
                "font-family": loadedFont.family + ", sans-serif",
                "font-variation-settings": "\\"wght\\" 400, \\"wdth\\" 100"
            });
            
            const container = element.closest(".font-sampler-container");
            const initialWeight = container.find(".weight-control").val() || 400;
            const initialWidth = container.find(".width-control").val() || 100;
            
            element.css("font-variation-settings", 
                "\\"wght\\" " + initialWeight + ", \\"wdth\\" " + initialWidth);
                
        } catch (error) {
            await loadFontFallback(url, element);
        }
    }
    
    function loadFontFallback(url, element) {
        return new Promise((resolve, reject) => {
            const fontFamily = "VariableFont-" + Date.now();
            const style = document.createElement("style");
            style.textContent = 
                "@font-face { " +
                "font-family: \\"" + fontFamily + "\\"; " +
                "src: url(\\"" + url + "\\"); " +
                "font-display: swap; " +
                "}";
            document.head.appendChild(style);
            
            element.css({
                "font-family": fontFamily + ", sans-serif"
            });
            
            setTimeout(function() {
                const container = element.closest(".font-sampler-container");
                const initialWeight = container.find(".weight-control").val() || 400;
                const initialWidth = container.find(".width-control").val() || 100;
                
                element.css({
                    "font-weight": initialWeight,
                    "font-stretch": initialWidth + "%",
                    "font-variation-settings": 
                        "\\"wght\\" " + initialWeight + ", \\"wdth\\" " + initialWidth
                });
                resolve();
            }, 100);
        });
    }
});';
        
        file_put_contents($plugin_upload_dir . 'font-sampler.js', $js_content);
    }
    
    public function varifosa_activate() {
        // Create uploads directory structure
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
        }

        // Make sure obfuscated font dir and .htaccess exist
        $this->ensure_obfuscated_fonts_dir();
        
        // Create CSS file
        $this->varifosa_create_css_file();
        
        // Create JS file
        $this->varifosa_create_js_file();

        // Add rewrite rules (flush after activation)
        $this->varifosa_register_font_rewrite();
        flush_rewrite_rules();
    }
    
    public function varifosa_deactivate() {
        // Cleanup tasks if needed
        flush_rewrite_rules();
    }
}

// Initialize the plugin
new Varifosa_Sampler();
?>
