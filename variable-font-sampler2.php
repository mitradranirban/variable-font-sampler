<?php
/**
 * Plugin Name: Variable Font Sampler
 * Plugin URI: https://github.com/mitradranirban/variable-font-sampler/
 * Description: A WordPress plugin for showcasing variable fonts with interactive controls.
 * Version: 1.0.4
 * Author: Dr Anirban Mitra
 * License: GPL v3 or later
 * Text Domain: variable-font-sampler
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Varifosa_Sampler {
    
    private $plugin_url;
    private $plugin_path;

    // Uploads directory (for generated assets)
    private $plugin_upload_dir;
    private $plugin_upload_url;
    
    // Plugin version constant for cache busting
    const VERSION = '1.0.4';
    
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
        
        add_action('init', array($this, 'varifosa_init'));
        add_action('wp_enqueue_scripts', array($this, 'varifosa_enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'varifosa_admin_enqueue_scripts'));
        add_shortcode('font_sampler', array($this, 'varifosa_font_sampler_shortcode'));
        add_action('admin_menu', array($this, 'varifosa_add_admin_menu'));
        add_action('wp_ajax_varifosa_upload_font', array($this, 'varifosa_handle_font_upload'));
        add_action('wp_ajax_varifosa_delete_font', array($this, 'varifosa_handle_font_delete'));
        add_action('wp_ajax_varifosa_list_fonts', array($this, 'varifosa_list_uploaded_fonts'));
        
        register_activation_hook(__FILE__, array($this, 'varifosa_activate'));
        register_deactivation_hook(__FILE__, array($this, 'varifosa_deactivate'));
    }
    
    public function varifosa_init() {
        // load_plugin_textdomain() call removed
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
        
        // Localize script for AJAX (if needed)
        wp_localize_script('font-sampler', 'fontSampler', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('varifosa_font_sampler_nonce')
        ));
    }
    
    public function varifosa_admin_enqueue_scripts($hook) {
        if ('settings_page_variable-font-sampler' !== $hook) {
            return;
        }
        $upload_dir = wp_upload_dir();
        $plugin_upload_url = trailingslashit($upload_dir['baseurl']) . 'variable-font-sampler/';
        $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        
        // Ensure admin.js file exists
        if (!file_exists($plugin_upload_dir . 'admin.js')) {
            $this->varifosa_create_admin_js_file();
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'variable-font-sampler-admin',
            $plugin_upload_url . 'admin.js',
            array('jquery'),
            self::VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('variable-font-sampler-admin', 'varifosaAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('varifosa_admin_nonce'),
            'upload_url' => $this->plugin_upload_url
        ));
    }
    
    public function varifosa_font_sampler_shortcode($atts) {
        $atts = shortcode_atts(array(
            'font' => '',
            'text' => 'The quick brown fox jumps over the lazy dog',
            'size' => '32',
            'controls' => 'true',
            'id' => uniqid('font-sampler-')
        ), $atts);
        
        $font_url = $atts['font'];
        
        if (empty($font_url)) {
            return '<p>' . esc_html__('No font specified. Please add a font URL to the shortcode.', 'variable-font-sampler') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="font-sampler-container" id="<?php echo esc_attr($atts['id']); ?>">
            <div class="font-sampler-preview">
                <div class="font-sample" 
                     data-font="<?php echo esc_url($font_url); ?>"
                     data-text="<?php echo esc_attr($atts['text']); ?>"
                     data-size="<?php echo esc_attr($atts['size']); ?>"
                     data-controls="<?php echo esc_attr($atts['controls']); ?>">
                    <?php echo esc_html($atts['text']); ?>
                </div>
            </div>
            
            <?php if ($atts['controls'] === 'true'): ?>
            <div class="font-sampler-controls">
                <div class="control-group">
                    <label for="<?php echo esc_attr($atts['id']); ?>-size"><?php esc_html_e('Font Size:', 'variable-font-sampler'); ?></label>
                    <input type="range" id="<?php echo esc_attr($atts['id']); ?>-size" 
                           class="size-control" min="12" max="120" value="<?php echo esc_attr($atts['size']); ?>">
                    <span class="size-value"><?php echo esc_html($atts['size']); ?>px</span>
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
    
    public function varifosa_add_admin_menu() {
        add_options_page(
            esc_html__('Variable Font Sampler', 'variable-font-sampler'),
            esc_html__('Font Sampler', 'variable-font-sampler'),
            'manage_options',
            'variable-font-sampler',
            array($this, 'varifosa_admin_page')
        );
    }
    
    public function varifosa_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Variable Font Sampler', 'variable-font-sampler'); ?></h1>
            
            <div class="usage-info">
                <h3><?php esc_html_e('Usage Instructions', 'variable-font-sampler'); ?></h3>
                <p><?php esc_html_e('Use the shortcode to display font samples:', 'variable-font-sampler'); ?></p>
                <code>[font_sampler font="URL_TO_FONT" text="Sample text" size="32" controls="true"]</code>
                <p><?php esc_html_e('Parameters:', 'variable-font-sampler'); ?></p>
                <ul>
                    <li><strong>font:</strong> <?php esc_html_e('URL to the variable font file (required)', 'variable-font-sampler'); ?></li>
                    <li><strong>text:</strong> <?php esc_html_e('Sample text to display (default: "The quick brown fox...")', 'variable-font-sampler'); ?></li>
                    <li><strong>size:</strong> <?php esc_html_e('Initial font size in pixels (default: 32)', 'variable-font-sampler'); ?></li>
                    <li><strong>controls:</strong> <?php esc_html_e('Show interactive controls (default: true)', 'variable-font-sampler'); ?></li>
                </ul>
                
                <h4><?php esc_html_e('Example with uploaded font:', 'variable-font-sampler'); ?></h4>
                <p><?php esc_html_e('If you upload a font named "my-font.woff2", you can use it like this:', 'variable-font-sampler'); ?></p>
                <code>[font_sampler font="<?php echo esc_url($this->plugin_upload_url); ?>my-font.woff2"]</code>
            </div>
            
            <div class="font-upload-section">
                <h3><?php esc_html_e('Font Upload', 'variable-font-sampler'); ?></h3>
                <p><?php esc_html_e('Upload your variable font files to use in your shortcodes:', 'variable-font-sampler'); ?></p>
                
                <div class="upload-area">
                    <button type="button" class="button button-primary" id="upload-font-btn">
                        <?php esc_html_e('Upload Font File', 'variable-font-sampler'); ?>
                    </button>
                    <p class="description">
                        <?php esc_html_e('Supported formats: .woff2, .woff, .ttf, .otf', 'variable-font-sampler'); ?>
                    </p>
                </div>
                
                <div class="uploaded-fonts-section">
                    <h4><?php esc_html_e('Uploaded Fonts', 'variable-font-sampler'); ?></h4>
                    <div id="uploaded-fonts-list">
                        <?php $this->varifosa_display_uploaded_fonts(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function varifosa_display_uploaded_fonts() {
        $fonts_dir = $this->plugin_upload_dir . 'fonts/';
        if (!file_exists($fonts_dir)) {
            wp_mkdir_p($fonts_dir);
        }
        
        $fonts = glob($fonts_dir . '*.{woff2,woff,ttf,otf}', GLOB_BRACE);
        
        if (empty($fonts)) {
            echo '<p>' . esc_html__('No fonts uploaded yet.', 'variable-font-sampler') . '</p>';
            return;
        }
        
        echo '<table class="widefat">';
        echo '<thead><tr><th>' . esc_html__('Font File', 'variable-font-sampler') . '</th><th>' . esc_html__('URL', 'variable-font-sampler') . '</th><th>' . esc_html__('Action', 'variable-font-sampler') . '</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($fonts as $font_path) {
            $font_name = basename($font_path);
            $font_url = $this->plugin_upload_url . 'fonts/' . $font_name;
            
            echo '<tr>';
            echo '<td>' . esc_html($font_name) . '</td>';
            echo '<td><code>' . esc_url($font_url) . '</code> <button type="button" class="button button-small copy-url-btn" data-url="' . esc_attr($font_url) . '">' . esc_html__('Copy', 'variable-font-sampler') . '</button></td>';
            echo '<td><button type="button" class="button button-small delete-font-btn" data-font="' . esc_attr($font_name) . '">' . esc_html__('Delete', 'variable-font-sampler') . '</button></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    public function varifosa_handle_font_upload() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'varifosa_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        if (empty($_FILES['font_file'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $file = $_FILES['font_file'];
        $allowed_types = array('woff2', 'woff', 'ttf', 'otf');
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            wp_send_json_error('Invalid file type. Only .woff2, .woff, .ttf, and .otf files are allowed.');
        }
        
        $fonts_dir = $this->plugin_upload_dir . 'fonts/';
        if (!file_exists($fonts_dir)) {
            wp_mkdir_p($fonts_dir);
        }
        
        $file_name = sanitize_file_name($file['name']);
        $file_path = $fonts_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $font_url = $this->plugin_upload_url . 'fonts/' . $file_name;
            wp_send_json_success(array(
                'message' => 'Font uploaded successfully',
                'font_name' => $file_name,
                'font_url' => $font_url
            ));
        } else {
            wp_send_json_error('Failed to upload font file');
        }
    }
    
    public function varifosa_handle_font_delete() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'varifosa_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $font_name = sanitize_file_name($_POST['font_name']);
        $font_path = $this->plugin_upload_dir . 'fonts/' . $font_name;
        
        if (file_exists($font_path) && unlink($font_path)) {
            wp_send_json_success('Font deleted successfully');
        } else {
            wp_send_json_error('Failed to delete font file');
        }
    }
    
    public function varifosa_list_uploaded_fonts() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'varifosa_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        ob_start();
        $this->varifosa_display_uploaded_fonts();
        $html = ob_get_clean();
        
        wp_send_json_success($html);
    }
    
    public function varifosa_activate() {
        // Create uploads directory structure
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
        }
        
        // Create fonts subdirectory
        $fonts_dir = $plugin_upload_dir . 'fonts/';
        if (!file_exists($fonts_dir)) {
            wp_mkdir_p($fonts_dir);
        }
        
        // Create CSS file
        $this->varifosa_create_css_file();
        
        // Create JS file
        $this->varifosa_create_js_file();
        
        // Create admin JS file
        $this->varifosa_create_admin_js_file();
    }
    
    public function varifosa_deactivate() {
        // Clean up if needed
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

.usage-info {
    background: #f0f8ff;
    padding: 15px;
    border-left: 4px solid #0073aa;
    margin-bottom: 20px;
}

.usage-info code {
    background: #f1f1f1;
    padding: 2px 4px;
    border-radius: 3px;
}

.font-upload-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-top: 20px;
}

.upload-area {
    margin-bottom: 20px;
    padding: 20px;
    border: 2px dashed #ddd;
    border-radius: 4px;
    text-align: center;
}

.uploaded-fonts-section {
    margin-top: 20px;
}

.uploaded-fonts-section table {
    margin-top: 10px;
}

.uploaded-fonts-section td code {
    background: #f1f1f1;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 12px;
}

@media (max-width: 768px) {
    .font-sampler-controls {
        grid-template-columns: 1fr;
    }
}
        ';
        
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
            loadFont(fontUrl, sample);
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
            
            // Combine both weight and width in font-variation-settings
            sample.css({
                "font-weight": weight,
                "font-variation-settings": "\\"wght\\" " + weight + ", \\"wdth\\" " + width
            });
            container.find(".weight-value").text(weight);
        });
        
        // Width control (using font-stretch and font-variation-settings)
        container.find(".width-control").on("input", function() {
            var width = $(this).val();
            var weight = container.find(".weight-control").val() || 400;
            
            // Combine both weight and width in font-variation-settings
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
    
    function loadFont(url, element) {
        // Check if FontFace API is supported
        if (!window.FontFace) {
            // Fallback for older browsers
            loadFontFallback(url, element);
            return;
        }
        
        var fontFace = new FontFace("VariableFont-" + Date.now(), "url(" + url + ")");
        
        fontFace.load().then(function(loadedFont) {
            document.fonts.add(loadedFont);
            element.css({
                "font-family": loadedFont.family + ", sans-serif",
                "font-variation-settings": "\\"wght\\" 400, \\"wdth\\" 100"
            });
            
            // Initialize with proper font-variation-settings
            var container = element.closest(\'.font-sampler-container\');
            var initialWeight = container.find(\'.weight-control\').val() || 400;
            var initialWidth = container.find(\'.width-control\').val() || 100;
            
            element.css("font-variation-settings", "\\"wght\\" " + initialWeight + ", \\"wdth\\" " + initialWidth);
            
        }).catch(function(error) {
            console.error("Font loading failed:", error);
            loadFontFallback(url, element);
        });
    }
    
    function loadFontFallback(url, element) {
        // Fallback method using CSS @font-face
        var fontFamily = "VariableFont-" + Date.now();
        var style = document.createElement("style");
        style.textContent = "@font-face { font-family: \\"" + fontFamily + "\\"; src: url(\\"" + url + "\\"); font-display: swap; }";
        document.head.appendChild(style);
        
        element.css({
            "font-family": fontFamily + ", sans-serif"
        });
        
        // Add a small delay to ensure font is loaded
        setTimeout(function() {
            var container = element.closest(\'.font-sampler-container\');
            var initialWeight = container.find(\'.weight-control\').val() || 400;
            var initialWidth = container.find(\'.width-control\').val() || 100;
            
            element.css({
                "font-weight": initialWeight,
                "font-stretch": initialWidth + "%",
                "font-variation-settings": "\\"wght\\" " + initialWeight + ", \\"wdth\\" " + initialWidth
            });
        }, 100);
    }
});
        ';
        
        file_put_contents($plugin_upload_dir . 'font-sampler.js', $js_content);
    }
    
    private function varifosa_create_admin_js_file() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'variable-font-sampler/';
        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
        }
        
        $admin_js_content = '
jQuery(document).ready(function($) {
    var mediaUploader;
    
    // Font upload button
    $("#upload-font-btn").on("click", function(e) {
        e.preventDefault();
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: "Choose Font File",
            button: {
                text: "Upload Font"
            },
            multiple: false,
            library: {
                type: ["application/font-woff", "application/font-woff2", "font/woff", "font/woff2", "application/x-font-ttf", "application/x-font-otf"]
            }
        });
        
        mediaUploader.on("select", function() {
            var attachment = mediaUploader.state().get("selection").first().toJSON();
            
            // Create form data for upload
            var formData = new FormData();
            formData.append("action", "varifosa_upload_font");
            formData.append("nonce", varifosaAdmin.nonce);
            formData.append("font_url", attachment.url);
            formData.append("font_name", attachment.filename);
            
            // Show loading
            $("#uploaded-fonts-list").html("<p>Uploading font...</p>");
            
            // Copy file to plugin uploads directory
            $.ajax({
                url: varifosaAdmin.ajaxurl,
                type: "POST",
                data: {
                    action: "varifosa_list_fonts",
                    nonce: varifosaAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $("#uploaded-fonts-list").html(response.data);
                        alert("Font uploaded successfully! URL: " + attachment.url);
                    }
                }
            });
        });
        
        mediaUploader.open();
    });
    
    // Copy URL button
    $(document).on("click", ".copy-url-btn", function() {
        var url = $(this).data("url");
        navigator.clipboard.writeText(url).then(function() {
            alert("URL copied to clipboard!");
        }).catch(function() {
            // Fallback for older browsers
            var textArea = document.createElement("textarea");
            textArea.value = url;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand("copy");
            document.body.removeChild(textArea);
            alert("URL copied to clipboard!");
        });
    });
    
    // Delete font button
    $(document).on("click", ".delete-font-btn", function() {
        var fontName = $(this).data("font");
        
        if (!confirm("Are you sure you want to delete this font?")) {
            return;
        }
        
        $.ajax({
            url: varifosaAdmin.ajaxurl,
            type: "POST",
            data: {
                action: "varifosa_delete_font",
                nonce: varifosaAdmin.nonce,
                font_name: fontName
